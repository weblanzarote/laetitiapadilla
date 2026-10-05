<?php
defined('AULA') || exit;

class Auth
{
    public static ?array $user = null;

    const REMEMBER_DAYS = 30;

    public static function startSession(): void
    {
        $dir = App::$dataDir . '/sessions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }
        ini_set('session.gc_maxlifetime', (string)(self::REMEMBER_DAYS * 86400));
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '200');
        ini_set('session.use_strict_mode', '1');
        session_name('aula_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => base_path(),
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $id = (int)($_SESSION['uid'] ?? 0);
        if (!$id) {
            return;
        }
        $u = Db::one('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$u || $u['status'] !== 'active' || !hash_equals(self::passwordVersion($u), (string)($_SESSION['pwv'] ?? ''))) {
            self::logout();
            return;
        }
        self::$user = $u;
        if (time() - (int)$u['last_seen_at'] > 300) {
            Db::q('UPDATE users SET last_seen_at = ? WHERE id = ?', [time(), $id]);
        }
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    /** Cambia si cambia la contraseña: así se cierran las demás sesiones. */
    private static function passwordVersion(array $u): string
    {
        return substr(hash('sha256', $u['password_hash']), 0, 20);
    }

    public static function attempt(string $email, string $password, bool $remember): void
    {
        $email = mb_strtolower(trim($email));
        $ip = client_ip();
        $since = time() - 900;
        if ((int)Db::val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > ?', [$ip, $since]) >= 12
            || (int)Db::val('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND created_at > ?', [$email, $since]) >= 6) {
            throw new UserError('Demasiados intentos fallidos. Espera 15 minutos o recupera tu contraseña.');
        }
        $u = Db::one('SELECT * FROM users WHERE email = ?', [$email]);
        if (!$u || !password_verify($password, $u['password_hash'])) {
            Db::insert('login_attempts', ['ip' => $ip, 'email' => $email, 'created_at' => time()]);
            throw new UserError('Email o contraseña incorrectos.');
        }
        if ($u['status'] !== 'active') {
            throw new UserError('Tu cuenta está desactivada. Escribe a tu profesora si crees que es un error.');
        }
        Db::delete('login_attempts', 'email = ?', [$email]);
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            $u['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            Db::q('UPDATE users SET password_hash = ? WHERE id = ?', [$u['password_hash'], $u['id']]);
        }
        self::login($u, $remember);
    }

    public static function login(array $u, bool $remember = false): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];
        $_SESSION['pwv'] = self::passwordVersion($u);
        Db::q('UPDATE users SET last_login_at = ?, last_seen_at = ? WHERE id = ?', [time(), time(), $u['id']]);
        if ($remember) {
            setcookie(session_name(), session_id(), [
                'expires' => time() + self::REMEMBER_DAYS * 86400,
                'path' => base_path(),
                'secure' => self::isHttps(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        self::$user = Db::one('SELECT * FROM users WHERE id = ?', [$u['id']]);
        do_action('user_login', self::$user);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        self::$user = null;
    }

    /** Tras cambiar la contraseña propia, mantiene viva esta sesión. */
    public static function refresh(): void
    {
        if (self::$user) {
            self::$user = Db::one('SELECT * FROM users WHERE id = ?', [self::$user['id']]);
            $_SESSION['pwv'] = self::passwordVersion(self::$user);
        }
    }

    public static function createUser(string $name, string $email, string $password, string $role = 'student'): int
    {
        $id = Db::insert('users', [
            'email' => mb_strtolower(trim($email)),
            'name' => trim($name),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'status' => 'active',
            'prefs' => '{}',
            'created_at' => time(),
        ]);
        do_action('user_created', $id);
        return $id;
    }

    public static function validatePassword(string $password, string $confirm): void
    {
        if (mb_strlen($password) < 8) {
            throw new UserError('La contraseña debe tener al menos 8 caracteres.');
        }
        if ($password !== $confirm) {
            throw new UserError('Las dos contraseñas no coinciden.');
        }
    }

    public static function validateEmail(string $email, int $exceptId = 0): string
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            throw new UserError('El email no parece válido.');
        }
        if (Db::val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $exceptId])) {
            throw new UserError('Ya existe una cuenta con ese email.');
        }
        return $email;
    }

    /** Crea un enlace de recuperación de contraseña (válido $hours horas). */
    public static function resetLink(int $userId, int $hours = 2): string
    {
        $token = bin2hex(random_bytes(24));
        Db::insert('password_resets', [
            'user_id' => $userId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => time() + $hours * 3600,
            'used' => 0,
            'created_at' => time(),
        ]);
        return abs_url('reset', ['t' => $token]);
    }

    public static function findReset(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        return Db::one(
            'SELECT * FROM password_resets WHERE token_hash = ? AND used = 0 AND expires_at > ?',
            [hash('sha256', $token), time()]
        );
    }

    /** Límite sencillo para acciones públicas (registro, recuperación). */
    public static function throttle(string $action, int $max, int $seconds): void
    {
        $key = '#' . $action;
        $count = (int)Db::val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND email = ? AND created_at > ?', [client_ip(), $key, time() - $seconds]);
        if ($count >= $max) {
            throw new UserError('Demasiados intentos seguidos. Espera un rato y vuelve a probar.');
        }
        Db::insert('login_attempts', ['ip' => client_ip(), 'email' => $key, 'created_at' => time()]);
    }
}
