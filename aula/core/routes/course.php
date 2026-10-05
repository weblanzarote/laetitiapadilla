<?php
defined('AULA') || exit;

function course_or_404(int $id): array
{
    $c = Courses::find($id);
    if (!$c) {
        throw new HttpError('Curso no encontrado.', 404);
    }
    return $c;
}

function resource_or_404(int $id): array
{
    $r = Courses::resource($id);
    if (!$r) {
        throw new HttpError('Contenido no encontrado.', 404);
    }
    return $r;
}

// ─── Ver curso ───────────────────────────────────────────────────

Router::add('course', function () {
    $course = course_or_404(gint('id'));
    if (!Courses::canView($course, user())) {
        throw new HttpError('No estás inscrito/a en este curso. Si tienes un código, úsalo en «Unirme a un curso».', 403);
    }
    $teacher = is_teacher();
    $byId = [];
    foreach (Courses::resources((int)$course['id'], $teacher) as $r) {
        $byId[(int)$r['section_id']][] = $r;
    }
    View::page('course/view', [
        'course' => $course,
        'teacher' => $teacher,
        'sections' => Courses::sections((int)$course['id'], $teacher),
        'resources' => $byId,
        'views' => $teacher ? [] : Courses::views((int)$course['id'], uid()),
        'progress' => $teacher ? null : Courses::progress((int)$course['id'], uid()),
        'students' => $teacher ? (int)Db::val('SELECT COUNT(*) FROM enrolments WHERE course_id = ?', [$course['id']]) : 0,
    ], $course['title'], ['crumbs' => [['Mis cursos', url()]]]);
});

// ─── Crear / editar curso ────────────────────────────────────────

Router::add('course/edit', function () {
    $course = gint('id') ? course_or_404(gint('id')) : null;
    $v = $course ?? ['id' => 0, 'title' => '', 'summary' => '', 'enrol_code' => '', 'enrol_open' => 1, 'visible' => 1];
    $error = null;
    if (is_post()) {
        $v = array_merge($v, [
            'title' => p('title'),
            'summary' => Html::clean((string)($_POST['summary'] ?? '')),
            'enrol_code' => p('enrol_code'),
            'enrol_open' => empty($_POST['enrol_open']) ? 0 : 1,
            'visible' => empty($_POST['visible']) ? 0 : 1,
        ]);
        try {
            if (mb_strlen($v['title']) < 2) {
                throw new UserError('Ponle un título al curso.');
            }
            $code = $v['enrol_code'] === '' ? Courses::newCode($v['title']) : Courses::normalizeCode($v['enrol_code']);
            if (Db::val('SELECT id FROM courses WHERE enrol_code = ? AND id <> ?', [$code, (int)$v['id']])) {
                throw new UserError('Ese código ya lo usa otro curso.');
            }
            $data = [
                'title' => mb_substr($v['title'], 0, 200), 'summary' => $v['summary'], 'enrol_code' => $code,
                'enrol_open' => $v['enrol_open'], 'visible' => $v['visible'], 'updated_at' => time(),
            ];
            if ($course) {
                Db::update('courses', $data, 'id = ?', [$course['id']]);
                $id = (int)$course['id'];
                flash('ok', 'Curso guardado.');
            } else {
                $data['created_at'] = time();
                $data['sort'] = (int)Db::val('SELECT COALESCE(MAX(sort), 0) FROM courses') + 10;
                $id = Db::insert('courses', $data);
                Db::insert('sections', ['course_id' => $id, 'title' => 'Presentación', 'summary' => '', 'sort' => 10, 'visible' => 1]);
                flash('ok', 'Curso creado. Ahora añade unidades y contenidos.');
            }
            do_action('course_saved', $id, !$course);
            redirect('course', ['id' => $id]);
        } catch (UserError $e) {
            $error = $e->getMessage();
        }
    }
    $crumbs = [['Mis cursos', url()]];
    if ($course) {
        $crumbs[] = [$course['title'], url('course', ['id' => $course['id']])];
    }
    View::page('course/form', ['v' => $v, 'error' => $error, 'course' => $course], $course ? 'Editar curso' : 'Nuevo curso', ['crumbs' => $crumbs]);
}, 'teacher');

Router::add('course/delete', function () {
    $course = course_or_404(pint('id'));
    if (p('confirm') !== 'BORRAR') {
        flash('error', 'Para borrar el curso escribe BORRAR en la casilla de confirmación.');
        redirect('course/edit', ['id' => $course['id']]);
    }
    Courses::delete((int)$course['id']);
    flash('ok', 'Curso «' . $course['title'] . '» borrado.');
    redirect();
}, 'teacher');

Router::add('course/duplicate', function () {
    $course = course_or_404(pint('id'));
    $id = Courses::duplicate((int)$course['id']);
    flash('ok', 'Curso duplicado. La copia está oculta y con la inscripción cerrada: revísala y cambia el título y el código.');
    redirect('course/edit', ['id' => $id]);
}, 'teacher');

// ─── Participantes ───────────────────────────────────────────────

Router::add('course/participants', function () {
    $course = course_or_404(gint('id'));
    $students = Courses::students((int)$course['id']);
    foreach ($students as &$s) {
        $s['progress'] = Courses::progress((int)$course['id'], (int)$s['id']);
    }
    unset($s);
    View::page('course/participants', ['course' => $course, 'students' => $students], 'Participantes', [
        'crumbs' => [['Mis cursos', url()], [$course['title'], url('course', ['id' => $course['id']])]],
    ]);
}, 'teacher');

Router::add('course/enrol', function () {
    $course = course_or_404(pint('id'));
    $u = Db::one('SELECT * FROM users WHERE email = ?', [mb_strtolower(p('email'))]);
    if (!$u) {
        flash('error', 'No hay ninguna cuenta con ese email. Puedes crearla en Gestión › Alumnado, o pasarle el código del curso para que se registre.');
    } elseif (Courses::enrol((int)$u['id'], (int)$course['id'])) {
        flash('ok', $u['name'] . ' inscrito/a en el curso.');
    } else {
        flash('info', $u['name'] . ' ya estaba en el curso.');
    }
    back('course/participants', ['id' => $course['id']]);
}, 'teacher');

Router::add('course/unenrol', function () {
    $course = course_or_404(pint('id'));
    Courses::unenrol(pint('user_id'), (int)$course['id']);
    flash('ok', 'Alumno/a dado/a de baja del curso.');
    back('course/participants', ['id' => $course['id']]);
}, 'teacher');

// ─── Unidades ────────────────────────────────────────────────────

Router::add('section/save', function () {
    $section = pint('id') ? Courses::section(pint('id')) : null;
    $course = course_or_404($section ? (int)$section['course_id'] : pint('course_id'));
    $title = p('title');
    if ($title === '') {
        flash('error', 'La unidad necesita un título.');
        redirect('course', ['id' => $course['id']]);
    }
    $data = ['title' => mb_substr($title, 0, 200), 'summary' => Html::clean((string)($_POST['summary'] ?? ''))];
    if ($section) {
        Db::update('sections', $data, 'id = ?', [$section['id']]);
        $id = (int)$section['id'];
    } else {
        $data += ['course_id' => $course['id'], 'sort' => Courses::nextSort('sections', 'course_id', (int)$course['id']), 'visible' => 1];
        $id = Db::insert('sections', $data);
    }
    redirect_to(url('course', ['id' => $course['id']]) . '#s' . $id);
}, 'teacher');

Router::add('section/delete', function () {
    $section = Courses::section(pint('id'));
    if ($section) {
        Courses::deleteSection((int)$section['id']);
        flash('ok', 'Unidad borrada.');
        redirect('course', ['id' => $section['course_id']]);
    }
    redirect();
}, 'teacher');

Router::add('section/move', function () {
    $section = Courses::section(pint('id'));
    if ($section) {
        Courses::move('sections', (int)$section['id'], 'course_id', pint('dir') < 0 ? -1 : 1);
        redirect_to(url('course', ['id' => $section['course_id']]) . '#s' . $section['id']);
    }
    redirect();
}, 'teacher');

Router::add('section/toggle', function () {
    $section = Courses::section(pint('id'));
    if ($section) {
        Db::q('UPDATE sections SET visible = ? WHERE id = ?', [(int)$section['visible'] ? 0 : 1, $section['id']]);
        redirect_to(url('course', ['id' => $section['course_id']]) . '#s' . $section['id']);
    }
    redirect();
}, 'teacher');

// ─── Recursos ────────────────────────────────────────────────────

Router::add('resource', function () {
    $res = resource_or_404(gint('id'));
    if (!Courses::canViewResource($res, user())) {
        throw new HttpError('No tienes acceso a este contenido.', 403);
    }
    $course = Courses::find((int)$res['course_id']);
    $type = Courses::type($res);
    if (!is_teacher()) {
        Courses::recordView($res, uid());
    }
    $redirect = $type->redirect($res);
    if ($redirect && !(is_teacher() && g('manage') === '1')) {
        redirect_to($redirect);
    }
    // Anterior / siguiente dentro del curso
    $list = Courses::resources((int)$course['id'], is_teacher());
    $prev = $next = null;
    foreach ($list as $i => $r) {
        if ((int)$r['id'] === (int)$res['id']) {
            $prev = $list[$i - 1] ?? null;
            $next = $list[$i + 1] ?? null;
        }
    }
    $section = Courses::section((int)$res['section_id']);
    View::page('course/resource', [
        'res' => $res, 'course' => $course, 'type' => $type, 'section' => $section, 'prev' => $prev, 'next' => $next,
    ], $res['title'], [
        'crumbs' => [['Mis cursos', url()], [$course['title'], url('course', ['id' => $course['id']])]],
        'wide' => $type->wide(),
    ]);
});

Router::add('resource/edit', function () {
    $res = gint('id') ? resource_or_404(gint('id')) : null;
    if ($res) {
        $type = Courses::type($res);
        $section = Courses::section((int)$res['section_id']);
    } else {
        $section = Courses::section(gint('section_id'));
        $type = ResourceTypes::get(g('type'));
        if (!$section || !$type) {
            throw new HttpError('Elige una unidad y un tipo de contenido.', 404);
        }
        $res = ['id' => 0, 'type' => $type->id(), 'title' => '', 'description' => '', 'data' => [], 'visible' => 1,
            'section_id' => (int)$section['id'], 'course_id' => (int)$section['course_id']];
    }
    $course = course_or_404((int)$res['course_id']);
    $error = null;

    if (is_post()) {
        $isNew = !$res['id'];
        $res['title'] = mb_substr(p('title'), 0, 200);
        $res['description'] = Html::clean((string)($_POST['description'] ?? ''));
        $res['visible'] = empty($_POST['visible']) ? 0 : 1;
        $newSection = Courses::section(pint('section_id'));
        if ($newSection && (int)$newSection['course_id'] === (int)$course['id']) {
            $res['section_id'] = (int)$newSection['id'];
        }
        try {
            if ($res['title'] === '') {
                throw new UserError('Ponle un título.');
            }
            if ($isNew) {
                $res['id'] = Db::insert('resources', [
                    'course_id' => $course['id'], 'section_id' => $res['section_id'], 'type' => $type->id(), 'title' => $res['title'],
                    'description' => $res['description'], 'data' => '{}', 'visible' => $res['visible'],
                    'sort' => Courses::nextSort('resources', 'section_id', (int)$res['section_id']),
                    'created_at' => time(), 'updated_at' => time(),
                ]);
            }
            try {
                $data = $type->save($res, $isNew);
            } catch (Throwable $e) {
                if ($isNew) {
                    Files::deleteContext('resource', (int)$res['id']);
                    Db::delete('resources', 'id = ?', [$res['id']]);
                    $res['id'] = 0;
                }
                throw $e;
            }
            if (!$isNew && (int)$res['section_id'] !== (int)Db::val('SELECT section_id FROM resources WHERE id = ?', [$res['id']])) {
                Db::q('UPDATE resources SET sort = ? WHERE id = ?', [Courses::nextSort('resources', 'section_id', (int)$res['section_id']), $res['id']]);
            }
            Db::update('resources', [
                'title' => $res['title'], 'description' => $res['description'], 'visible' => $res['visible'],
                'section_id' => $res['section_id'], 'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => time(),
            ], 'id = ?', [$res['id']]);
            do_action('resource_saved', Courses::resource((int)$res['id']), $isNew);
            flash('ok', $isNew ? 'Contenido añadido.' : 'Contenido guardado.');
            redirect_to(url('course', ['id' => $course['id']]) . '#r' . $res['id']);
        } catch (UserError $e) {
            $error = $e->getMessage();
        }
    }
    View::page('course/resource_form', [
        'res' => $res, 'type' => $type, 'course' => $course, 'sections' => Courses::sections((int)$course['id'], true), 'error' => $error,
    ], ($res['id'] ? 'Editar: ' : 'Añadir: ') . $type->label(), [
        'crumbs' => [['Mis cursos', url()], [$course['title'], url('course', ['id' => $course['id']])]],
    ]);
}, 'teacher');

Router::add('resource/delete', function () {
    $res = resource_or_404(pint('id'));
    Courses::deleteResource($res);
    flash('ok', '«' . $res['title'] . '» borrado.');
    redirect_to(url('course', ['id' => $res['course_id']]) . '#s' . $res['section_id']);
}, 'teacher');

Router::add('resource/move', function () {
    $res = resource_or_404(pint('id'));
    Courses::move('resources', (int)$res['id'], 'section_id', pint('dir') < 0 ? -1 : 1);
    redirect_to(url('course', ['id' => $res['course_id']]) . '#r' . $res['id']);
}, 'teacher');

Router::add('resource/toggle', function () {
    $res = resource_or_404(pint('id'));
    Db::q('UPDATE resources SET visible = ? WHERE id = ?', [(int)$res['visible'] ? 0 : 1, $res['id']]);
    redirect_to(url('course', ['id' => $res['course_id']]) . '#r' . $res['id']);
}, 'teacher');
