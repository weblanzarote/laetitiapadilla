<?php
defined('AULA') || exit;

// ─── Inicio ──────────────────────────────────────────────────────

Router::add('home', function () {
    $u = user();
    $courses = Courses::forUser($u);
    if (is_teacher()) {
        $stats = [];
        foreach ($courses as $c) {
            $stats[(int)$c['id']] = [
                'students' => (int)Db::val('SELECT COUNT(*) FROM enrolments WHERE course_id = ?', [$c['id']]),
                'resources' => (int)Db::val('SELECT COUNT(*) FROM resources WHERE course_id = ?', [$c['id']]),
            ];
        }
        $recent = Db::all("SELECT * FROM users WHERE role = 'student' ORDER BY created_at DESC LIMIT 6");
        View::page('dashboard_teacher', ['courses' => $courses, 'stats' => $stats, 'recent' => $recent], 'Inicio');
        return;
    }
    $progress = [];
    foreach ($courses as $c) {
        $progress[(int)$c['id']] = Courses::progress((int)$c['id'], uid());
    }
    View::page('dashboard_student', ['courses' => $courses, 'progress' => $progress], 'Inicio');
});

Router::add('join', function () {
    if (is_post()) {
        $course = Courses::findByCode(p('code'));
        if (!$course || (int)$course['enrol_open'] !== 1) {
            flash('error', 'Ese código no es válido o la inscripción está cerrada.');
            redirect();
        }
        if (Courses::enrol(uid(), (int)$course['id'])) {
            flash('ok', 'Te has unido a «' . $course['title'] . '».');
        }
        redirect('course', ['id' => $course['id']]);
    }
    redirect();
});

// ─── Perfil ──────────────────────────────────────────────────────

Router::add('profile', function () {
    $u = user();
    $prefFields = apply_filters('profile_prefs', []);
    $error = null;
    if (is_post()) {
        try {
            $name = p('name');
            if (mb_strlen($name) < 2) {
                throw new UserError('Escribe tu nombre.');
            }
            $email = Auth::validateEmail(p('email'), (int)$u['id']);
            $data = ['name' => $name, 'email' => $email];
            $prefs = json_decode((string)$u['prefs'], true) ?: [];
            foreach ($prefFields as $f) {
                $prefs[$f['key']] = ($f['type'] ?? 'bool') === 'bool' ? !empty($_POST['pref'][$f['key']]) : p('pref_' . $f['key']);
            }
            $data['prefs'] = json_encode($prefs);
            $new = (string)($_POST['password'] ?? '');
            if ($new !== '') {
                if (!password_verify((string)($_POST['current'] ?? ''), $u['password_hash'])) {
                    throw new UserError('La contraseña actual no es correcta.');
                }
                Auth::validatePassword($new, (string)($_POST['password2'] ?? ''));
                $data['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
            }
            Db::update('users', $data, 'id = ?', [$u['id']]);
            Auth::refresh();
            flash('ok', 'Perfil guardado.');
            redirect('profile');
        } catch (UserError $e) {
            $error = $e->getMessage();
        }
    }
    View::page('profile', ['u' => $u, 'prefFields' => $prefFields, 'error' => $error], 'Mi perfil');
});

// ─── Archivos ────────────────────────────────────────────────────

Router::add('upload/chunk', function () {
    if (!is_post()) {
        abort(405);
    }
    try {
        json_out(Files::receiveChunk());
    } catch (UserError $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 400);
    }
});

// serve.php/f/<id>/<nombre>
Router::serve('f', function (string $rest) {
    $id = (int)explode('/', $rest)[0];
    $file = Files::find($id);
    if (!$file || !Files::canAccess($file, user())) {
        throw new HttpError('Archivo no encontrado.', 404);
    }
    Files::send(Files::path($file), $file['mime'], $file['name'], !empty($_GET['dl']) ? 'attachment' : 'auto', 3600);
});
