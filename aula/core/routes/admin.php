<?php
defined('AULA') || exit;

// ─── Alumnado y cuentas ──────────────────────────────────────────

Router::add('admin/users', function () {
    $q = g('q');
    $courseId = gint('course');
    $role = g('role');
    $sql = 'SELECT u.* FROM users u';
    $params = [];
    $where = [];
    if ($courseId) {
        $sql .= ' JOIN enrolments e ON e.user_id = u.id AND e.course_id = ?';
        $params[] = $courseId;
    }
    if ($q !== '') {
        $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    if (in_array($role, ['student', 'teacher', 'admin'], true)) {
        $where[] = 'u.role = ?';
        $params[] = $role;
    }
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $users = Db::all($sql . ' ORDER BY u.name LIMIT 500', $params);
    $enrol = [];
    foreach (Db::all('SELECT e.user_id, c.id, c.title FROM enrolments e JOIN courses c ON c.id = e.course_id ORDER BY c.title') as $row) {
        $enrol[(int)$row['user_id']][] = $row;
    }
    View::page('admin/users', [
        'users' => $users, 'enrol' => $enrol, 'courses' => Courses::all(), 'q' => $q, 'courseId' => $courseId, 'role' => $role,
    ], 'Alumnado y cuentas', ['crumbs' => [['Inicio', url()]]]);
}, 'teacher');

Router::add('admin/user', function () {
    $u = gint('id') ? Db::one('SELECT * FROM users WHERE id = ?', [gint('id')]) : null;
    if (gint('id') && !$u) {
        throw new HttpError('Cuenta no encontrada.', 404);
    }
    if ($u && $u['role'] === 'admin' && !is_admin()) {
        throw new HttpError('Solo la administración puede editar esta cuenta.', 403);
    }
    $error = null;
    $resetLink = null;
    $v = $u ?? ['id' => 0, 'name' => '', 'email' => '', 'role' => 'student', 'status' => 'active'];

    if (is_post()) {
        $action = p('action', 'save');
        try {
            if ($action === 'save') {
                $v['name'] = p('name');
                $v['email'] = p('email');
                if (mb_strlen($v['name']) < 2) {
                    throw new UserError('Escribe el nombre.');
                }
                $email = Auth::validateEmail($v['email'], (int)$v['id']);
                $data = ['name' => $v['name'], 'email' => $email];
                if (is_admin() && in_array(p('role'), ['student', 'teacher', 'admin'], true) && (int)$v['id'] !== uid()) {
                    $data['role'] = p('role');
                }
                if ((int)$v['id'] !== uid()) {
                    $data['status'] = p('status') === 'blocked' ? 'blocked' : 'active';
                }
                $pass = (string)($_POST['password'] ?? '');
                if ($pass !== '' || !$u) {
                    Auth::validatePassword($pass, $pass);
                    $data['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
                }
                if ($u) {
                    Db::update('users', $data, 'id = ?', [$u['id']]);
                    $id = (int)$u['id'];
                    if ($id === uid()) {
                        Auth::refresh();
                    }
                } else {
                    $id = Auth::createUser($data['name'], $email, $pass, $data['role'] ?? 'student');
                    if (pint('course_id') && Courses::find(pint('course_id'))) {
                        Courses::enrol($id, pint('course_id'));
                    }
                }
                flash('ok', 'Cuenta guardada.');
                redirect('admin/user', ['id' => $id]);
            }
            if (!$u) {
                throw new UserError('Primero guarda la cuenta.');
            }
            if ($action === 'enrol' && Courses::find(pint('course_id'))) {
                Courses::enrol((int)$u['id'], pint('course_id'));
                flash('ok', 'Inscrito/a en el curso.');
                redirect('admin/user', ['id' => $u['id']]);
            }
            if ($action === 'unenrol') {
                Courses::unenrol((int)$u['id'], pint('course_id'));
                flash('ok', 'Baja del curso hecha.');
                redirect('admin/user', ['id' => $u['id']]);
            }
            if ($action === 'reset') {
                $resetLink = Auth::resetLink((int)$u['id'], 72);
            }
            if ($action === 'delete' && is_admin() && (int)$u['id'] !== uid()) {
                do_action('user_deleting', $u);
                Db::delete('enrolments', 'user_id = ?', [$u['id']]);
                Db::delete('resource_views', 'user_id = ?', [$u['id']]);
                Db::delete('password_resets', 'user_id = ?', [$u['id']]);
                Db::delete('users', 'id = ?', [$u['id']]);
                do_action('user_deleted', (int)$u['id']);
                flash('ok', 'Cuenta de ' . $u['name'] . ' eliminada.');
                redirect('admin/users');
            }
        } catch (UserError $e) {
            $error = $e->getMessage();
        }
    }

    $enrolled = $u ? Db::all('SELECT c.* FROM courses c JOIN enrolments e ON e.course_id = c.id WHERE e.user_id = ? ORDER BY c.title', [$u['id']]) : [];
    View::page('admin/user', [
        'u' => $u, 'v' => $v, 'error' => $error, 'resetLink' => $resetLink, 'enrolled' => $enrolled, 'courses' => Courses::all(),
    ], $u ? $u['name'] : 'Nueva cuenta', ['crumbs' => [['Inicio', url()], ['Alumnado', url('admin/users')]]]);
}, 'teacher');

// ─── Ajustes ─────────────────────────────────────────────────────

add_filter('settings_sections', function (array $sections) {
    array_unshift($sections, [
        'id' => 'general',
        'title' => 'General',
        'fields' => [
            ['key' => 'site_name', 'label' => 'Nombre del aula', 'type' => 'text', 'default' => 'Aula virtual'],
            ['key' => 'registration_enabled', 'label' => 'Permitir que el alumnado se registre con código de curso', 'type' => 'bool', 'default' => 1],
            ['key' => 'notify_new_student', 'label' => 'Avisarme por email cuando se registre alguien', 'type' => 'bool', 'default' => 1],
            ['key' => 'max_upload_mb', 'label' => 'Tamaño máximo de cada archivo (MB)', 'type' => 'number', 'default' => 300],
            ['key' => 'welcome_text', 'label' => 'Mensaje de bienvenida en la portada del alumnado', 'type' => 'html', 'default' => ''],
            ['key' => 'privacy_text', 'label' => 'Política de privacidad', 'type' => 'html', 'default' => default_privacy_text()],
        ],
    ]);
    return $sections;
}, 1);

Router::add('admin/settings', function () {
    $sections = apply_filters('settings_sections', []);
    if (is_post()) {
        foreach ($sections as $section) {
            foreach ($section['fields'] as $f) {
                $key = $f['key'];
                switch ($f['type']) {
                    case 'bool':
                        $val = empty($_POST['s'][$key]) ? 0 : 1;
                        break;
                    case 'number':
                        $val = (int)($_POST['s'][$key] ?? $f['default']);
                        break;
                    case 'html':
                        $val = Html::clean((string)($_POST['s'][$key] ?? ''));
                        break;
                    case 'select':
                        $val = (string)($_POST['s'][$key] ?? '');
                        if (!array_key_exists($val, $f['options'])) {
                            $val = $f['default'];
                        }
                        break;
                    default:
                        $val = trim((string)($_POST['s'][$key] ?? ''));
                }
                Settings::set($key, $val);
            }
        }
        flash('ok', 'Ajustes guardados.');
        redirect('admin/settings');
    }
    View::page('admin/settings', ['sections' => $sections], 'Ajustes', ['crumbs' => [['Inicio', url()]]]);
}, 'admin');

// ─── Extensiones ─────────────────────────────────────────────────

Router::add('admin/plugins', function () {
    if (is_post()) {
        try {
            Plugins::setEnabled(p('id'), p('on') === '1');
            flash('ok', p('on') === '1' ? 'Extensión activada.' : 'Extensión desactivada (sus datos se conservan).');
        } catch (UserError $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/plugins');
    }
    View::page('admin/plugins', ['plugins' => Plugins::available(), 'enabled' => Plugins::enabledIds()], 'Extensiones', ['crumbs' => [['Inicio', url()]]]);
}, 'admin');

// ─── Sistema y copias de seguridad ───────────────────────────────

Router::add('admin/system', function () {
    $dir = App::$dataDir;
    $checks = [
        ['PHP', PHP_VERSION, version_compare(PHP_VERSION, '7.4.0', '>=')],
        ['Base de datos', Db::$driver === 'sqlite' ? 'SQLite' : 'MySQL', true],
        ['Carpeta de datos', $dir, is_writable($dir)],
        ['Carpeta de datos fuera de la web', App::dataDirIsPublic() ? 'NO: está dentro de la web pública' : 'Sí', !App::dataDirIsPublic()],
        ['Extensión ZIP (para SCORM)', class_exists('ZipArchive') ? 'Sí' : 'No', class_exists('ZipArchive')],
        ['Extensión fileinfo', function_exists('finfo_open') ? 'Sí' : 'No', true],
        ['Subida máxima de PHP', ini_get('upload_max_filesize') . ' (post: ' . ini_get('post_max_size') . ')', true],
        ['Trozos de subida del aula', fmt_size(Files::chunkBytes()) . ' (los archivos grandes se suben por partes)', true],
        ['Envío de correo', App::$config['mail_enabled'] ? 'Activado (mail())' : 'Desactivado: se guardan en mail.log', true],
        ['Espacio libre en disco', ($free = @disk_free_space($dir)) ? fmt_size((int)$free) : '¿?', $free === false || $free > 500 * 1048576],
    ];
    $counts = [
        'Cursos' => (int)Db::val('SELECT COUNT(*) FROM courses'),
        'Cuentas' => (int)Db::val('SELECT COUNT(*) FROM users'),
        'Contenidos' => (int)Db::val('SELECT COUNT(*) FROM resources'),
        'Archivos' => (int)Db::val('SELECT COUNT(*) FROM files'),
    ];
    $log = is_file("$dir/error.log") ? implode("\n", array_slice(file("$dir/error.log", FILE_IGNORE_NEW_LINES) ?: [], -30)) : '';
    View::page('admin/system', ['checks' => $checks, 'counts' => $counts, 'log' => $log], 'Sistema y copias', ['crumbs' => [['Inicio', url()]]]);
}, 'admin');

Router::add('admin/backup', function () {
    if (!is_post()) {
        redirect('admin/system');
    }
    if (!class_exists('ZipArchive')) {
        throw new UserError('El servidor no tiene la extensión ZIP.');
    }
    @set_time_limit(0);
    $dir = App::$dataDir;
    $tmp = Files::dir('tmp') . '/backup_' . bin2hex(random_bytes(6));
    $zip = new ZipArchive();
    $zip->open("$tmp.zip", ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if (Db::$driver === 'sqlite') {
        $snap = "$tmp.sqlite";
        try {
            Db::$pdo->exec('VACUUM INTO ' . Db::$pdo->quote($snap));
        } catch (Throwable $e) {
            Db::$pdo->exec('PRAGMA wal_checkpoint(FULL)');
            copy("$dir/aula.sqlite", $snap);
        }
        $zip->addFile($snap, 'aula.sqlite');
    } else {
        $tables = Db::col('SHOW TABLES');
        foreach ($tables as $t) {
            $zip->addFromString("db/$t.json", json_encode(Db::all("SELECT * FROM `$t`"), JSON_UNESCAPED_UNICODE));
        }
    }
    foreach (apply_filters('backup_dirs', ['files']) as $sub) {
        $base = "$dir/$sub";
        if (!is_dir($base)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $zip->addFile($file->getPathname(), $sub . '/' . str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1)));
        }
    }
    $zip->addFromString('LEEME.txt', "Copia del aula (" . date('Y-m-d H:i') . ").\nPara restaurar: copia el contenido en la carpeta de datos del aula ($dir).\n");
    $zip->close();
    @unlink("$tmp.sqlite");
    register_shutdown_function(fn() => @unlink("$tmp.zip"));
    Files::send("$tmp.zip", 'application/zip', 'copia-aula-' . date('Y-m-d') . '.zip', 'attachment');
}, 'admin');
