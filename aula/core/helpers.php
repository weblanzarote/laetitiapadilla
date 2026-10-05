<?php
defined('AULA') || exit;

// Compatibilidad con PHP 7.4 (por si el servidor no tiene PHP 8).
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $h, string $n): bool { return $n === '' || strncmp($h, $n, strlen($n)) === 0; }
    function str_ends_with(string $h, string $n): bool { return $n === '' || substr($h, -strlen($n)) === $n; }
    function str_contains(string $h, string $n): bool { return $n === '' || strpos($h, $n) !== false; }
}

/** Error mostrable al usuario (validaciones de formularios, etc.). */
class UserError extends RuntimeException {}

/** Error HTTP (404, 403...). El código de la excepción es el estado HTTP. */
class HttpError extends RuntimeException {}

// ─── Salida y URLs ────────────────────────────────────────────────

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Ruta web de la carpeta del aula, con barra final (p. ej. "/aula/"). */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $base = rtrim($dir, '/') . '/';
    }
    return $base;
}

function url(string $route = '', array $params = []): string
{
    $query = $route !== '' ? ['r' => $route] + $params : $params;
    return base_path() . 'index.php' . ($query ? '?' . http_build_query($query) : '');
}

/** URL absoluta (para emails). */
function abs_url(string $route = '', array $params = []): string
{
    $site = rtrim((string)App::$config['site_url'], '/') . '/';
    return $site . substr(url($route, $params), strlen(base_path()));
}

function asset(string $path): string
{
    $file = AULA_DIR . '/' . $path;
    return base_path() . $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function serve_url(string $handler, string $path): string
{
    $parts = array_map('rawurlencode', explode('/', $path));
    return base_path() . 'serve.php/' . $handler . '/' . implode('/', $parts);
}

function redirect(string $route = '', array $params = []): void
{
    redirect_to(url($route, $params));
}

function redirect_to(string $url): void
{
    header('Location: ' . $url, true, 303);
    exit;
}

/** Vuelve a la página indicada en el formulario (_back) o a una ruta por defecto. */
function back(string $route = '', array $params = []): void
{
    $to = (string)($_POST['_back'] ?? '');
    if ($to !== '' && str_starts_with($to, base_path()) && !str_contains($to, "\n")) {
        redirect_to($to);
    }
    redirect($route, $params);
}

function current_url(): string
{
    return (string)($_SERVER['REQUEST_URI'] ?? url());
}

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

function abort(int $status, string $message = ''): void
{
    throw new HttpError($message, $status);
}

function icon(string $name, string $extra = ''): string
{
    $style = str_starts_with($name, 'fa-brands ') ? '' : 'fa-solid ';
    return '<i class="' . $style . 'fa-' . e($name) . ($extra ? ' ' . e($extra) : '') . '" aria-hidden="true"></i>';
}

// ─── Peticiones ───────────────────────────────────────────────────

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Valor de texto de $_POST, recortado. */
function p(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim(str_replace("\r\n", "\n", $v)) : $default;
}

/** Valor de texto de $_GET, recortado. */
function g(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function pint(string $key, int $default = 0): int
{
    return isset($_POST[$key]) && is_numeric($_POST[$key]) ? (int)$_POST[$key] : $default;
}

function gint(string $key, int $default = 0): int
{
    return isset($_GET[$key]) && is_numeric($_GET[$key]) ? (int)$_GET[$key] : $default;
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 64);
}

// ─── CSRF y mensajes flash ────────────────────────────────────────

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = (string)($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
        throw new HttpError('La sesión ha caducado. Vuelve atrás, recarga la página e inténtalo de nuevo.', 419);
    }
}

/** Campo oculto para volver a la página actual tras enviar un formulario. */
function back_field(?string $to = null): string
{
    return '<input type="hidden" name="_back" value="' . e($to ?? current_url()) . '">';
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = [$type, $message];
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

// ─── Ajustes ──────────────────────────────────────────────────────

class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Db::all('SELECT name, value FROM settings') as $row) {
                self::$cache[$row['name']] = $row['value'];
            }
        }
        return self::$cache;
    }

    public static function get(string $name, $default = null)
    {
        $all = self::all();
        return array_key_exists($name, $all) ? $all[$name] : $default;
    }

    public static function set(string $name, $value): void
    {
        $value = $value === null ? null : (string)$value;
        if (Db::val('SELECT COUNT(*) FROM settings WHERE name = ?', [$name])) {
            Db::q('UPDATE settings SET value = ? WHERE name = ?', [$value, $name]);
        } else {
            Db::q('INSERT INTO settings (name, value) VALUES (?, ?)', [$name, $value]);
        }
        if (self::$cache !== null) {
            self::$cache[$name] = $value;
        }
    }

    public static function reset(): void
    {
        self::$cache = null;
    }
}

function setting(string $name, $default = null)
{
    return Settings::get($name, $default);
}

// ─── Usuario actual ───────────────────────────────────────────────

function user(): ?array
{
    return Auth::$user;
}

function uid(): int
{
    return (int)(Auth::$user['id'] ?? 0);
}

function is_teacher(?array $u = null): bool
{
    $u = $u ?? Auth::$user;
    return $u !== null && in_array($u['role'], ['teacher', 'admin'], true);
}

function is_admin(?array $u = null): bool
{
    $u = $u ?? Auth::$user;
    return $u !== null && $u['role'] === 'admin';
}

function user_pref(array $u, string $key, $default = null)
{
    $prefs = json_decode((string)($u['prefs'] ?? ''), true);
    return is_array($prefs) && array_key_exists($key, $prefs) ? $prefs[$key] : $default;
}

// ─── Formato ──────────────────────────────────────────────────────

function fmt_date(?int $ts, string $style = 'datetime'): string
{
    if (!$ts) {
        return '—';
    }
    $day = date('Y-m-d', $ts);
    if ($style === 'relative') {
        if ($day === date('Y-m-d')) {
            return 'hoy, ' . date('H:i', $ts);
        }
        if ($day === date('Y-m-d', strtotime('-1 day'))) {
            return 'ayer, ' . date('H:i', $ts);
        }
    }
    $months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $s = date('j', $ts) . ' ' . $months[(int)date('n', $ts) - 1];
    if (date('Y', $ts) !== date('Y')) {
        $s .= ' ' . date('Y', $ts);
    }
    return $style === 'date' ? $s : $s . ', ' . date('H:i', $ts);
}

function fmt_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024) . ' KB';
    }
    if ($bytes < 1073741824) {
        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }
    return number_format($bytes / 1073741824, 1, ',', '.') . ' GB';
}

/** "1 alumno", "3 alumnos". */
function plural(int $n, string $one, string $many): string
{
    return $n . ' ' . ($n === 1 ? $one : $many);
}

function fmt_duration(int $seconds): string
{
    if ($seconds <= 0) {
        return '—';
    }
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    return $h ? sprintf('%d h %02d min', $h, $m) : sprintf('%d min', max(1, $m));
}

/** Texto plano → HTML seguro con saltos de línea y enlaces. */
function text_to_html(string $text): string
{
    $html = e($text);
    $html = preg_replace(
        '~(https?://[^\s<>"\']+[^\s<>"\'.,;:!?)\]])~i',
        '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
        $html
    );
    return nl2br($html, false);
}

function excerpt(string $text, int $len = 120): string
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
    return mb_strlen($text) > $len ? mb_substr($text, 0, $len - 1) . '…' : $text;
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $i = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $i .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $i ?: '?';
}

function random_code(int $length = 6): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $out;
}

/** Bytes a partir de valores de php.ini como "8M". */
function ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }
    $n = (int)$value;
    switch (strtolower(substr($value, -1))) {
        case 'g': $n *= 1024;
        // no break
        case 'm': $n *= 1024;
        // no break
        case 'k': $n *= 1024;
    }
    return $n;
}
