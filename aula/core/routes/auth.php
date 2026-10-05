<?php
defined('AULA') || exit;

// ─── Instalación (solo mientras no exista ninguna cuenta) ────────

Router::add('setup', function () {
    if (Db::val('SELECT COUNT(*) FROM users')) {
        redirect();
    }
    $error = null;
    $v = ['name' => '', 'email' => '', 'site_name' => 'Aula · Laetitia Padilla'];
    if (is_post()) {
        $v = ['name' => p('name'), 'email' => p('email'), 'site_name' => p('site_name')];
        try {
            if (mb_strlen($v['name']) < 2) {
                throw new UserError('Escribe tu nombre.');
            }
            $email = Auth::validateEmail($v['email']);
            Auth::validatePassword(p('password'), p('password2'));
            Settings::set('site_name', $v['site_name'] !== '' ? $v['site_name'] : 'Aula virtual');
            $id = Auth::createUser($v['name'], $email, p('password'), 'admin');
            Auth::login(Db::one('SELECT * FROM users WHERE id = ?', [$id]));
            flash('ok', 'Aula instalada. Empieza creando tu primer curso.');
            redirect();
        } catch (UserError $e) {
            $error = $e->getMessage();
        }
    }
    View::page('auth/setup', ['v' => $v, 'error' => $error], 'Instalación', ['layout' => 'auth']);
}, 'public');

// ─── Entrar / salir ──────────────────────────────────────────────

Router::add('login', function () {
    $error = null;
    $email = g('email');
    if (is_post()) {
        $email = p('email');
        try {
            Auth::attempt($email, (string)($_POST['password'] ?? ''), !empty($_POST['remember']));
            $to = (string)($_SESSION['_intended'] ?? '');
            unset($_SESSION['_intended']);
            redirect_to($to !== '' && str_starts_with($to, base_path()) ? $to : url());
        } catch (UserError $e) {
            $error = $e->getMessage();
        }
    }
    View::page('auth/login', ['email' => $email, 'error' => $error], 'Entrar', ['layout' => 'auth']);
}, 'guest');

Router::add('logout', function () {
    if (is_post()) {
        Auth::logout();
        flash('ok', 'Has cerrado la sesión. ¡Hasta pronto!');
    }
    redirect('login');
}, 'public');

// ─── Registro con código de curso ────────────────────────────────

Router::add('register', function () {
    $open = (int)setting('registration_enabled', 1) === 1;
    $error = null;
    $v = ['name' => '', 'email' => '', 'code' => mb_strtoupper(g('code'))];
    if ($open && is_post()) {
        $v = ['name' => p('name'), 'email' => p('email'), 'code' => mb_strtoupper(p('code'))];
        try {
            if (p('website') !== '') {
                throw new UserError('No se pudo completar el registro.');
            }
            Auth::throttle('register', 6, 3600);
            if (mb_strlen($v['name']) < 3) {
                throw new UserError('Escribe tu nombre y apellidos.');
            }
            $course = Courses::findByCode($v['code']);
            if (!$course || (int)$course['enrol_open'] !== 1) {
                throw new UserError('El código de curso no es válido o la inscripción está cerrada. Pídeselo a tu profesora.');
            }
            $email = mb_strtolower(trim($v['email']));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) && Db::val('SELECT id FROM users WHERE email = ?', [$email])) {
                throw new UserError('Ya tienes una cuenta con ese email. Entra y usa «Unirme a un curso» con el código.');
            }
            $email = Auth::validateEmail($email);
            Auth::validatePassword((string)($_POST['password'] ?? ''), (string)($_POST['password2'] ?? ''));
            if (empty($_POST['privacy'])) {
                throw new UserError('Para registrarte tienes que aceptar la política de privacidad.');
            }
            $id = Auth::createUser($v['name'], $email, (string)$_POST['password'], 'student');
            $u = Db::one('SELECT * FROM users WHERE id = ?', [$id]);
            Auth::login($u);
            Courses::enrol($id, (int)$course['id']);
            do_action('user_registered', $u, $course);
            if ((int)setting('notify_new_student', 1) === 1) {
                Mailer::toTeachers(
                    'Nuevo alumno en ' . $course['title'],
                    "{$u['name']} ({$u['email']}) se ha registrado en el aula y se ha inscrito en «{$course['title']}».\n\nVer participantes: " . abs_url('course/participants', ['id' => $course['id']])
                );
            }
            flash('ok', 'Te damos la bienvenida. Ya estás en «' . $course['title'] . '».');
            redirect('course', ['id' => $course['id']]);
        } catch (UserError $e) {
            $error = $e->getMessage();
        }
    }
    View::page('auth/register', ['v' => $v, 'error' => $error, 'open' => $open], 'Crear cuenta', ['layout' => 'auth']);
}, 'guest');

// ─── Recuperar contraseña ────────────────────────────────────────

Router::add('forgot', function () {
    $sent = false;
    $error = null;
    if (is_post()) {
        try {
            Auth::throttle('forgot', 5, 3600);
            $u = Db::one("SELECT * FROM users WHERE email = ? AND status = 'active'", [mb_strtolower(p('email'))]);
            if ($u) {
                $link = Auth::resetLink((int)$u['id']);
                Mailer::send($u['email'], 'Cambiar tu contraseña del aula', "Hola, " . explode(' ', trim($u['name']))[0] . ":\n\nPara elegir una contraseña nueva, abre este enlace (caduca en 2 horas):\n\n$link\n\nSi no lo has pedido tú, ignora este mensaje.");
            }
            $sent = true;
        } catch (UserError $e) {
            $error = $e->getMessage();
        }
    }
    View::page('auth/forgot', ['sent' => $sent, 'error' => $error], 'Recuperar contraseña', ['layout' => 'auth']);
}, 'public');

Router::add('reset', function () {
    $token = is_post() ? p('t') : g('t');
    $reset = Auth::findReset($token);
    $error = null;
    if ($reset && is_post()) {
        try {
            Auth::validatePassword((string)($_POST['password'] ?? ''), (string)($_POST['password2'] ?? ''));
            Db::q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash((string)$_POST['password'], PASSWORD_DEFAULT), $reset['user_id']]);
            Db::q('UPDATE password_resets SET used = 1 WHERE user_id = ?', [$reset['user_id']]);
            $u = Db::one('SELECT * FROM users WHERE id = ?', [$reset['user_id']]);
            if ($u['status'] === 'active') {
                Auth::login($u);
            }
            flash('ok', 'Contraseña cambiada.');
            redirect();
        } catch (UserError $e) {
            $error = $e->getMessage();
        }
    }
    View::page('auth/reset', ['token' => $token, 'valid' => (bool)$reset, 'error' => $error], 'Nueva contraseña', ['layout' => 'auth']);
}, 'public');

// ─── Privacidad ──────────────────────────────────────────────────

Router::add('privacy', function () {
    View::page('privacy', ['text' => (string)setting('privacy_text', default_privacy_text())], 'Política de privacidad', ['layout' => user() ? 'layout' : 'auth']);
}, 'public');

function default_privacy_text(): string
{
    return '<h2>¿Quién trata tus datos?</h2>'
        . '<p>Laetitia Christelle Padilla, profesora de francés (contacto: <a href="mailto:contacto@laetitiapadilla.com">contacto@laetitiapadilla.com</a>).</p>'
        . '<h2>¿Para qué?</h2>'
        . '<p>Para darte acceso al aula virtual, a los materiales de tus cursos y a la mensajería con tu profesora, y para avisarte por email de mensajes nuevos.</p>'
        . '<h2>¿Qué datos?</h2>'
        . '<p>Tu nombre, tu email, tu contraseña (guardada cifrada), tus mensajes y tu progreso en las actividades.</p>'
        . '<h2>¿Durante cuánto tiempo?</h2>'
        . '<p>Mientras tengas cuenta en el aula. Puedes pedir que se borre en cualquier momento.</p>'
        . '<h2>¿Se comparten?</h2>'
        . '<p>No se ceden a terceros. Se guardan en el servidor de esta web.</p>'
        . '<h2>Tus derechos</h2>'
        . '<p>Puedes acceder, rectificar o suprimir tus datos escribiendo al email de contacto. También puedes reclamar ante la Agencia Española de Protección de Datos (aepd.es).</p>';
}
