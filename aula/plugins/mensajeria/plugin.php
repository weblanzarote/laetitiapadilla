<?php
/**
 * Extensión «Mensajería».
 *
 * Tipos de conversación:
 *   direct        entre dos personas
 *   group         profesora + varios alumnos (todos ven las respuestas)
 *   announcement  aviso a todo un curso (el alumnado responde en privado)
 */
defined('AULA') || exit;

Db::migrate('plugin_mensajeria', [
    1 => [
        "CREATE TABLE msg_conversations (id {PK}, subject VARCHAR(200) NOT NULL, kind VARCHAR(20) NOT NULL DEFAULT 'direct',
            course_id INT NOT NULL DEFAULT 0, created_by INT NOT NULL DEFAULT 0, created_at INT NOT NULL DEFAULT 0,
            last_message_at INT NOT NULL DEFAULT 0, last_message_id INT NOT NULL DEFAULT 0){TABLE_OPTS}",
        'CREATE INDEX msg_conv_course ON msg_conversations (course_id)',
        'CREATE TABLE msg_participants (id {PK}, conversation_id INT NOT NULL, user_id INT NOT NULL,
            last_read_id INT NOT NULL DEFAULT 0, archived INT NOT NULL DEFAULT 0, joined_at INT NOT NULL DEFAULT 0){TABLE_OPTS}',
        'CREATE UNIQUE INDEX msg_part_conv_user ON msg_participants (conversation_id, user_id)',
        'CREATE INDEX msg_part_user ON msg_participants (user_id)',
        'CREATE TABLE msg_messages (id {PK}, conversation_id INT NOT NULL, user_id INT NOT NULL, body {TEXT},
            created_at INT NOT NULL DEFAULT 0){TABLE_OPTS}',
        'CREATE INDEX msg_msg_conv ON msg_messages (conversation_id, id)',
    ],
]);

class Messaging
{
    const DEFAULTS = [
        'msg_students_can_start' => 1,
        'msg_student_to_student' => 0,
        'msg_attachments' => 1,
        'msg_email_notify' => 1,
        'msg_email_excerpt' => 1,
        'msg_announce_email' => 1,
        'msg_poll_seconds' => 20,
    ];

    public static function opt(string $key)
    {
        return setting($key, self::DEFAULTS[$key] ?? null);
    }

    // ─── Consultas ───────────────────────────────────────────────

    public static function unreadCount(int $userId): int
    {
        return (int)Db::val(
            'SELECT COUNT(DISTINCT m.conversation_id) FROM msg_messages m
             JOIN msg_participants p ON p.conversation_id = m.conversation_id
             WHERE p.user_id = ? AND m.id > p.last_read_id AND m.user_id <> ?',
            [$userId, $userId]
        );
    }

    public static function conversation(int $id): ?array
    {
        return $id ? Db::one('SELECT * FROM msg_conversations WHERE id = ?', [$id]) : null;
    }

    public static function participant(int $convId, int $userId): ?array
    {
        return Db::one('SELECT * FROM msg_participants WHERE conversation_id = ? AND user_id = ?', [$convId, $userId]);
    }

    /** Bandeja: conversaciones con último mensaje, no leídos y participantes. */
    public static function inbox(int $userId, bool $archived, string $q = '', int $courseId = 0, int $limit = 100): array
    {
        $sql = 'SELECT c.*, p.last_read_id, p.archived,
                (SELECT COUNT(*) FROM msg_messages m WHERE m.conversation_id = c.id AND m.id > p.last_read_id AND m.user_id <> ?) AS unread
                FROM msg_conversations c JOIN msg_participants p ON p.conversation_id = c.id AND p.user_id = ?
                WHERE p.archived = ? AND c.last_message_id > 0';
        $params = [$userId, $userId, $archived ? 1 : 0];
        if ($q !== '') {
            $sql .= ' AND (c.subject LIKE ? OR c.id IN (SELECT conversation_id FROM msg_messages WHERE body LIKE ?))';
            $params[] = "%$q%";
            $params[] = "%$q%";
        }
        if ($courseId) {
            $sql .= ' AND (c.course_id = ? OR c.id IN (SELECT p2.conversation_id FROM msg_participants p2 JOIN enrolments e ON e.user_id = p2.user_id AND e.course_id = ?))';
            $params[] = $courseId;
            $params[] = $courseId;
        }
        $rows = Db::all($sql . ' ORDER BY c.last_message_at DESC LIMIT ' . (int)$limit, $params);
        if (!$rows) {
            return [];
        }
        $ids = array_map(fn($r) => (int)$r['id'], $rows);
        $last = [];
        foreach (Db::all('SELECT * FROM msg_messages WHERE id IN (' . Db::in($ids) . ')', array_map(fn($r) => (int)$r['last_message_id'], $rows)) as $m) {
            $last[(int)$m['conversation_id']] = $m;
        }
        $people = self::peopleFor($ids);
        foreach ($rows as &$r) {
            $r['last'] = $last[(int)$r['id']] ?? null;
            $r['people'] = $people[(int)$r['id']] ?? [];
        }
        return $rows;
    }

    /** conversation_id => [ [id, name, role], ... ] */
    public static function peopleFor(array $convIds): array
    {
        if (!$convIds) {
            return [];
        }
        $out = [];
        $rows = Db::all(
            'SELECT p.conversation_id, p.user_id, u.name, u.role FROM msg_participants p LEFT JOIN users u ON u.id = p.user_id
             WHERE p.conversation_id IN (' . Db::in($convIds) . ') ORDER BY u.name',
            $convIds
        );
        foreach ($rows as $row) {
            $out[(int)$row['conversation_id']][] = ['id' => (int)$row['user_id'], 'name' => $row['name'] ?? 'Cuenta eliminada', 'role' => $row['role'] ?? 'student'];
        }
        return $out;
    }

    /** Nombre visible de la conversación para un usuario. */
    public static function title(array $conv, array $people, int $forUser): string
    {
        if ($conv['kind'] === 'announcement') {
            $course = Courses::find((int)$conv['course_id']);
            return 'Aviso' . ($course ? ' · ' . $course['title'] : '');
        }
        $others = array_values(array_filter($people, fn($p) => $p['id'] !== $forUser));
        if (!$others) {
            return 'Solo tú';
        }
        $names = array_map(fn($p) => $p['name'], $others);
        if (count($names) > 3) {
            return implode(', ', array_slice($names, 0, 3)) . ' y ' . (count($names) - 3) . ' más';
        }
        return implode(', ', $names);
    }

    public static function messages(int $convId, int $afterId = 0): array
    {
        $rows = Db::all(
            'SELECT m.*, u.name AS author, u.role AS author_role FROM msg_messages m LEFT JOIN users u ON u.id = m.user_id
             WHERE m.conversation_id = ? AND m.id > ? ORDER BY m.id',
            [$convId, $afterId]
        );
        if (!$rows) {
            return [];
        }
        $files = [];
        $ids = array_map(fn($r) => (int)$r['id'], $rows);
        foreach (Db::all("SELECT * FROM files WHERE context = 'message' AND context_id IN (" . Db::in($ids) . ') ORDER BY id', $ids) as $f) {
            $files[(int)$f['context_id']][] = $f;
        }
        foreach ($rows as &$r) {
            $r['files'] = $files[(int)$r['id']] ?? [];
        }
        return $rows;
    }

    public static function markRead(array $conv, int $userId): void
    {
        Db::q('UPDATE msg_participants SET last_read_id = ? WHERE conversation_id = ? AND user_id = ? AND last_read_id < ?',
            [$conv['last_message_id'], $conv['id'], $userId, $conv['last_message_id']]);
    }

    // ─── Permisos ────────────────────────────────────────────────

    /** Personas a las que $u puede escribir, agrupadas: ['Grupo' => [users...]]. */
    public static function recipientsFor(array $u): array
    {
        $groups = [];
        if (is_teacher($u)) {
            foreach (Courses::all() as $c) {
                $list = array_values(array_filter(Courses::students((int)$c['id']), fn($s) => (int)$s['id'] !== (int)$u['id'] && $s['status'] === 'active'));
                if ($list) {
                    $groups[$c['title']] = $list;
                }
            }
            $loose = Db::all("SELECT * FROM users WHERE role = 'student' AND status = 'active' AND id NOT IN (SELECT user_id FROM enrolments) ORDER BY name");
            if ($loose) {
                $groups['Sin curso'] = $loose;
            }
            $teachers = Db::all("SELECT * FROM users WHERE role IN ('teacher', 'admin') AND status = 'active' AND id <> ? ORDER BY name", [$u['id']]);
            if ($teachers) {
                $groups['Profesorado'] = $teachers;
            }
            return $groups;
        }
        if ((int)self::opt('msg_students_can_start') === 1) {
            $teachers = Db::all("SELECT * FROM users WHERE role IN ('teacher', 'admin') AND status = 'active' ORDER BY role = 'admin' DESC, name");
            if ($teachers) {
                $groups['Profesorado'] = $teachers;
            }
        }
        if ((int)self::opt('msg_student_to_student') === 1) {
            foreach (Courses::forUser($u) as $c) {
                $list = array_values(array_filter(Courses::students((int)$c['id']), fn($s) => (int)$s['id'] !== (int)$u['id'] && $s['role'] === 'student' && $s['status'] === 'active'));
                if ($list) {
                    $groups['Compañeros · ' . $c['title']] = $list;
                }
            }
        }
        return $groups;
    }

    public static function canWriteTo(array $from, array $to): bool
    {
        if ((int)$from['id'] === (int)$to['id'] || $to['status'] !== 'active') {
            return false;
        }
        if (is_teacher($from)) {
            return true;
        }
        if (is_teacher($to)) {
            return true; // responder siempre está permitido; iniciar se controla en la interfaz
        }
        if ((int)self::opt('msg_student_to_student') !== 1) {
            return false;
        }
        return (bool)Db::val(
            'SELECT COUNT(*) FROM enrolments a JOIN enrolments b ON a.course_id = b.course_id WHERE a.user_id = ? AND b.user_id = ?',
            [$from['id'], $to['id']]
        );
    }

    /** ¿Puede $u escribir en esta conversación? */
    public static function canReply(array $conv, array $u): bool
    {
        if ($conv['kind'] === 'announcement') {
            return is_teacher($u);
        }
        foreach (self::peopleFor([(int)$conv['id']])[(int)$conv['id']] ?? [] as $p) {
            if ($p['id'] !== (int)$u['id']) {
                $other = Db::one('SELECT * FROM users WHERE id = ?', [$p['id']]);
                if ($other && $other['status'] === 'active') {
                    return true;
                }
            }
        }
        return false;
    }

    // ─── Escritura ───────────────────────────────────────────────

    public static function create(string $subject, string $kind, int $courseId, array $userIds, int $fromId): int
    {
        $now = time();
        $id = Db::insert('msg_conversations', [
            'subject' => mb_substr($subject !== '' ? $subject : '(sin asunto)', 0, 200),
            'kind' => $kind,
            'course_id' => $courseId,
            'created_by' => $fromId,
            'created_at' => $now,
        ]);
        foreach (array_unique(array_merge([$fromId], array_map('intval', $userIds))) as $uid) {
            Db::insert('msg_participants', ['conversation_id' => $id, 'user_id' => $uid, 'joined_at' => $now]);
        }
        return $id;
    }

    /**
     * Añade un mensaje. $files: archivos recibidos (Files::incoming) o ids existentes para copiar.
     * $notify: enviar avisos por email.
     */
    public static function post(int $convId, int $userId, string $body, array $incoming = [], array $copyFileIds = [], bool $notify = true): int
    {
        $conv = self::conversation($convId);
        $prevLast = (int)$conv['last_message_id'];
        $now = time();
        $msgId = Db::insert('msg_messages', ['conversation_id' => $convId, 'user_id' => $userId, 'body' => $body, 'created_at' => $now]);
        foreach ($incoming as $in) {
            Files::store($in, 'message', $msgId);
        }
        foreach ($copyFileIds as $fid) {
            Files::copy((int)$fid, 'message', $msgId);
        }
        Db::q('UPDATE msg_conversations SET last_message_id = ?, last_message_at = ? WHERE id = ?', [$msgId, $now, $convId]);
        Db::q('UPDATE msg_participants SET archived = 0 WHERE conversation_id = ?', [$convId]);
        Db::q('UPDATE msg_participants SET last_read_id = ? WHERE conversation_id = ? AND user_id = ?', [$msgId, $convId, $userId]);
        $conv['last_message_id'] = $msgId;
        do_action('message_posted', $conv, $msgId, $userId);
        if ($notify) {
            self::notify($conv, $userId, $body, $prevLast);
        }
        return $msgId;
    }

    private static function notify(array $conv, int $senderId, string $body, int $prevLast): void
    {
        if ((int)self::opt('msg_email_notify') !== 1) {
            return;
        }
        if ($conv['kind'] === 'announcement' && (int)self::opt('msg_announce_email') !== 1) {
            return;
        }
        $sender = Db::one('SELECT * FROM users WHERE id = ?', [$senderId]);
        $rows = Db::all(
            "SELECT u.*, p.last_read_id FROM msg_participants p JOIN users u ON u.id = p.user_id
             WHERE p.conversation_id = ? AND p.user_id <> ? AND u.status = 'active'",
            [$conv['id'], $senderId]
        );
        $link = abs_url('messages/view', ['id' => $conv['id']]);
        foreach ($rows as $u) {
            if (!user_pref($u, 'msg_email', true)) {
                continue;
            }
            // Solo el primer mensaje sin leer genera email (evita avalanchas).
            if ((int)$u['last_read_id'] < $prevLast) {
                continue;
            }
            $what = $conv['kind'] === 'announcement' ? 'ha publicado un aviso' : 'te ha escrito';
            $first = explode(' ', trim($u['name']))[0];
            $text = "Hola, {$first}:\n\n{$sender['name']} $what en el aula.\n\nAsunto: {$conv['subject']}\n";
            if ((int)self::opt('msg_email_excerpt') === 1 && $body !== '') {
                $text .= "\n" . (mb_strlen($body) > 1500 ? mb_substr($body, 0, 1500) . '…' : $body) . "\n";
            }
            $text .= "\nLéelo y responde aquí:\n$link\n\nPuedes desactivar estos avisos en tu perfil del aula.";
            Mailer::send($u['email'], ($conv['kind'] === 'announcement' ? 'Aviso: ' : 'Mensaje nuevo: ') . $conv['subject'], $text);
        }
    }

    /** Añade a un alumno a los avisos ya publicados de un curso. */
    public static function addToAnnouncements(int $userId, int $courseId): void
    {
        foreach (Db::all("SELECT * FROM msg_conversations WHERE kind = 'announcement' AND course_id = ?", [$courseId]) as $c) {
            if (!self::participant((int)$c['id'], $userId)) {
                Db::insert('msg_participants', [
                    'conversation_id' => $c['id'], 'user_id' => $userId, 'last_read_id' => $c['last_message_id'], 'joined_at' => time(),
                ]);
            }
        }
    }

    public static function announcements(int $courseId, int $limit = 3): array
    {
        return Db::all(
            "SELECT c.*, m.body, m.created_at AS posted_at FROM msg_conversations c JOIN msg_messages m ON m.id = c.last_message_id
             WHERE c.kind = 'announcement' AND c.course_id = ? ORDER BY c.last_message_at DESC LIMIT " . (int)$limit,
            [$courseId]
        );
    }
}

// ─── Ganchos ─────────────────────────────────────────────────────

add_filter('nav_items', function (array $items) {
    $items[] = [
        'id' => 'messages', 'label' => 'Mensajes', 'url' => url('messages'), 'icon' => 'comments',
        'badge_id' => 'messages', 'badge' => Messaging::unreadCount(uid()),
    ];
    return $items;
});

add_action('footer', function () {
    if (!user()) {
        return;
    }
    echo '<script>window.AULA_BADGES = ' . json_encode(['messages' => ['url' => url('messages/unread'), 'every' => 60]]) . ';</script>';
});

add_filter('file_access', function (bool $allowed, array $file, array $u) {
    if ($allowed || $file['context'] !== 'message') {
        return $allowed;
    }
    $convId = (int)Db::val('SELECT conversation_id FROM msg_messages WHERE id = ?', [$file['context_id']]);
    return $convId && Messaging::participant($convId, (int)$u['id']) !== null;
});

add_filter('settings_sections', function (array $sections) {
    $sections[] = [
        'id' => 'mensajeria',
        'title' => 'Mensajería',
        'fields' => [
            ['key' => 'msg_students_can_start', 'label' => 'El alumnado puede iniciar conversaciones con la profesora', 'type' => 'bool', 'default' => 1,
                'help' => 'Si lo desactivas, solo podrán responder a lo que les escribas.'],
            ['key' => 'msg_student_to_student', 'label' => 'El alumnado puede escribirse entre sí (solo compañeros de curso)', 'type' => 'bool', 'default' => 0],
            ['key' => 'msg_attachments', 'label' => 'Permitir adjuntar archivos en los mensajes', 'type' => 'bool', 'default' => 1],
            ['key' => 'msg_email_notify', 'label' => 'Avisar por email de los mensajes nuevos', 'type' => 'bool', 'default' => 1,
                'help' => 'Cada persona puede desactivarlo en su perfil.'],
            ['key' => 'msg_email_excerpt', 'label' => 'Incluir el texto del mensaje en el email', 'type' => 'bool', 'default' => 1],
            ['key' => 'msg_announce_email', 'label' => 'Enviar también por email los avisos a todo el curso', 'type' => 'bool', 'default' => 1],
            ['key' => 'msg_poll_seconds', 'label' => 'Cada cuántos segundos se buscan mensajes nuevos en una conversación abierta', 'type' => 'number', 'default' => 20],
        ],
    ];
    return $sections;
});

add_filter('profile_prefs', function (array $fields) {
    $fields[] = ['key' => 'msg_email', 'label' => 'Recibir un email cuando me llegue un mensaje o un aviso', 'type' => 'bool', 'default' => true];
    return $fields;
});

add_action('user_enrolled', function (int $userId, int $courseId) {
    Messaging::addToAnnouncements($userId, $courseId);
});

add_action('user_unenrolled', function (int $userId, int $courseId) {
    Db::q("DELETE FROM msg_participants WHERE user_id = ? AND conversation_id IN (SELECT id FROM msg_conversations WHERE kind = 'announcement' AND course_id = ?)", [$userId, $courseId]);
});

add_action('user_deleted', function (int $userId) {
    Db::delete('msg_participants', 'user_id = ?', [$userId]);
});

add_action('course_deleted', function (int $courseId) {
    Db::q('UPDATE msg_conversations SET course_id = 0 WHERE course_id = ?', [$courseId]);
});

add_action('dashboard_side', function () {
    echo View::fetch('mensajeria:_side', [
        'convs' => Messaging::inbox(uid(), false, '', 0, 5),
        'unread' => Messaging::unreadCount(uid()),
    ]);
}, 5);

add_action('course_top', function (array $course) {
    $list = Messaging::announcements((int)$course['id'], 3);
    if (!$list && !is_teacher()) {
        return;
    }
    echo View::fetch('mensajeria:_course', ['course' => $course, 'list' => $list]);
});

add_action('participant_actions', function (array $student) {
    echo '<a class="icon-btn" href="' . e(url('messages/new', ['to' => $student['id']])) . '" title="Enviar mensaje" aria-label="Enviar mensaje">' . icon('envelope') . '</a>';
});

add_action('participants_actions', function (array $course) {
    echo '<a class="btn" href="' . e(url('messages/new', ['course' => $course['id']])) . '">' . icon('bullhorn') . ' Aviso al curso</a>';
});

add_action('user_actions', function (array $u) {
    if ((int)$u['id'] !== uid()) {
        echo '<a class="btn" href="' . e(url('messages/new', ['to' => $u['id']])) . '">' . icon('envelope') . ' Enviar mensaje</a>';
    }
});

// Ficha del alumno/a: últimas conversaciones en las que estamos los dos (sin avisos)
add_action('user_side', function (array $u) {
    if ((int)$u['id'] === uid()) {
        return;
    }
    $convs = Db::all(
        "SELECT c.* FROM msg_conversations c
         JOIN msg_participants me ON me.conversation_id = c.id AND me.user_id = ?
         JOIN msg_participants p ON p.conversation_id = c.id AND p.user_id = ?
         WHERE c.kind <> 'announcement' AND c.last_message_id > 0
         ORDER BY c.last_message_at DESC LIMIT 6",
        [uid(), $u['id']]
    );
    echo '<div class="card"><h2 class="card-title">' . icon('comments') . ' Conversaciones</h2>';
    if (!$convs) {
        echo '<p class="muted">Todavía no hay mensajes con esta persona.</p>';
    } else {
        echo '<ul class="mini-conv">';
        foreach ($convs as $c) {
            echo '<li><a href="' . e(url('messages/view', ['id' => $c['id']])) . '"><strong>' . e($c['subject']) . '</strong>'
                . '<small class="muted">' . fmt_date((int)$c['last_message_at'], 'relative') . '</small></a></li>';
        }
        echo '</ul>';
    }
    echo '</div>';
});

// ─── Rutas ───────────────────────────────────────────────────────

function msg_conv_or_404(int $id): array
{
    $conv = Messaging::conversation($id);
    if (!$conv || !Messaging::participant((int)$conv['id'], uid())) {
        throw new HttpError('Conversación no encontrada.', 404);
    }
    return $conv;
}

function msg_incoming_files(): array
{
    if ((int)Messaging::opt('msg_attachments') !== 1) {
        return [];
    }
    $in = Files::incoming('attachments');
    if (count($in) > 10) {
        throw new UserError('Como máximo 10 archivos por mensaje.');
    }
    return $in;
}

Router::add('messages', function () {
    $box = g('box') === 'archived' ? 'archived' : 'inbox';
    $q = g('q');
    $courseId = is_teacher() ? gint('course') : 0;
    View::page('mensajeria:inbox', [
        'convs' => Messaging::inbox(uid(), $box === 'archived', $q, $courseId),
        'box' => $box, 'q' => $q, 'courseId' => $courseId,
        'courses' => is_teacher() ? Courses::all() : [],
        'canStart' => (bool)Messaging::recipientsFor(user()),
    ], 'Mensajes');
});

Router::add('messages/view', function () {
    $conv = msg_conv_or_404(gint('id'));
    $messages = Messaging::messages((int)$conv['id']);
    Messaging::markRead($conv, uid());
    $people = Messaging::peopleFor([(int)$conv['id']])[(int)$conv['id']] ?? [];
    $me = Messaging::participant((int)$conv['id'], uid());
    View::page('mensajeria:view', [
        'conv' => $conv, 'messages' => $messages, 'people' => $people, 'archived' => (int)$me['archived'] === 1,
        'title' => Messaging::title($conv, $people, uid()),
        'canReply' => Messaging::canReply($conv, user()),
        'course' => $conv['course_id'] ? Courses::find((int)$conv['course_id']) : null,
    ], $conv['subject'], ['crumbs' => [['Mensajes', url('messages')]]]);
});

Router::add('messages/reply', function () {
    $conv = msg_conv_or_404(pint('id'));
    try {
        if (!Messaging::canReply($conv, user())) {
            throw new UserError('No se puede responder en esta conversación.');
        }
        $body = mb_substr(p('body'), 0, 20000);
        $files = msg_incoming_files();
        if ($body === '' && !$files) {
            throw new UserError('Escribe algo antes de enviar.');
        }
        Messaging::post((int)$conv['id'], uid(), $body, $files);
    } catch (UserError $e) {
        flash('error', $e->getMessage());
    }
    redirect_to(url('messages/view', ['id' => $conv['id']]) . '#bottom');
});

Router::add('messages/new', function () {
    $u = user();
    $groups = Messaging::recipientsFor($u);
    $teacher = is_teacher();
    $error = null;
    $v = [
        'mode' => gint('course') ? 'announcement' : 'people',
        'to' => array_filter([gint('to')]),
        'course_id' => gint('course'),
        'subject' => g('subject'),
        'body' => '',
        'group' => false,
    ];
    if (is_post()) {
        $v = [
            'mode' => $teacher && p('mode') === 'announcement' ? 'announcement' : 'people',
            'to' => array_values(array_unique(array_map('intval', (array)($_POST['to'] ?? [])))),
            'course_id' => pint('course_id'),
            'subject' => p('subject'),
            'body' => mb_substr(p('body'), 0, 20000),
            'group' => !empty($_POST['group']),
        ];
        try {
            if ($v['body'] === '') {
                throw new UserError('Escribe el mensaje.');
            }
            if ($v['subject'] === '') {
                throw new UserError('Ponle un asunto.');
            }
            $files = msg_incoming_files();
            if ($v['mode'] === 'announcement') {
                $course = Courses::find($v['course_id']);
                if (!$course) {
                    throw new UserError('Elige el curso.');
                }
                $ids = array_map(fn($s) => (int)$s['id'], array_filter(Courses::students((int)$course['id']), fn($s) => $s['status'] === 'active'));
                $convId = Messaging::create($v['subject'], 'announcement', (int)$course['id'], $ids, uid());
                Messaging::post($convId, uid(), $v['body'], $files);
                flash('ok', 'Aviso enviado a ' . plural(count($ids), 'alumno', 'alumnos') . ' de «' . $course['title'] . '».');
                redirect('messages/view', ['id' => $convId]);
            }
            $targets = [];
            foreach ($v['to'] as $id) {
                $t = Db::one('SELECT * FROM users WHERE id = ?', [$id]);
                if ($t && Messaging::canWriteTo($u, $t)) {
                    $targets[] = $t;
                }
            }
            if (!$targets) {
                throw new UserError('Elige a quién va el mensaje.');
            }
            if (!$teacher && count($targets) > 1) {
                throw new UserError('Elige una sola persona.');
            }
            if (count($targets) === 1 || $v['group']) {
                $kind = count($targets) === 1 ? 'direct' : 'group';
                $convId = Messaging::create($v['subject'], $kind, 0, array_map(fn($t) => (int)$t['id'], $targets), uid());
                Messaging::post($convId, uid(), $v['body'], $files);
                flash('ok', 'Mensaje enviado.');
                redirect('messages/view', ['id' => $convId]);
            }
            // Varios destinatarios por separado: una conversación privada con cada uno.
            $firstMsg = 0;
            foreach ($targets as $i => $t) {
                $convId = Messaging::create($v['subject'], 'direct', 0, [(int)$t['id']], uid());
                if ($i === 0) {
                    $firstMsg = Messaging::post($convId, uid(), $v['body'], $files);
                } else {
                    $copy = array_map(fn($f) => (int)$f['id'], Files::forContext('message', $firstMsg));
                    Messaging::post($convId, uid(), $v['body'], [], $copy);
                }
            }
            flash('ok', 'Mensaje enviado por separado a ' . count($targets) . ' personas.');
            redirect('messages');
        } catch (UserError $e) {
            $error = $e->getMessage();
        }
    }
    if (!$groups && !$teacher) {
        throw new HttpError('Ahora mismo no puedes iniciar conversaciones. Podrás responder a los mensajes que recibas.', 403);
    }
    View::page('mensajeria:new', [
        'v' => $v, 'groups' => $groups, 'teacher' => $teacher, 'error' => $error,
        'courses' => $teacher ? Courses::all() : [],
        'attachments' => (int)Messaging::opt('msg_attachments') === 1,
    ], $v['mode'] === 'announcement' ? 'Nuevo aviso' : 'Nuevo mensaje', ['crumbs' => [['Mensajes', url('messages')]]]);
});

Router::add('messages/archive', function () {
    $conv = msg_conv_or_404(pint('id'));
    $on = pint('on') === 1;
    Db::q('UPDATE msg_participants SET archived = ? WHERE conversation_id = ? AND user_id = ?', [$on ? 1 : 0, $conv['id'], uid()]);
    flash('ok', $on ? 'Conversación archivada. Volverá a la bandeja si llega un mensaje nuevo.' : 'Conversación devuelta a la bandeja.');
    redirect('messages', $on ? [] : ['box' => 'archived']);
});

Router::add('messages/delete', function () {
    $msg = Db::one('SELECT * FROM msg_messages WHERE id = ?', [pint('id')]);
    if (!$msg) {
        abort(404);
    }
    $conv = msg_conv_or_404((int)$msg['conversation_id']);
    $own = (int)$msg['user_id'] === uid() && time() - (int)$msg['created_at'] < 900;
    if (!is_teacher() && !$own) {
        throw new HttpError('Solo puedes borrar tus mensajes durante los primeros 15 minutos.', 403);
    }
    Files::deleteContext('message', (int)$msg['id']);
    Db::q("UPDATE msg_messages SET body = '[deleted]' WHERE id = ?", [$msg['id']]);
    redirect_to(url('messages/view', ['id' => $conv['id']]) . '#m' . $msg['id']);
});

Router::add('messages/unread', function () {
    json_out(['ok' => true, 'count' => Messaging::unreadCount(uid())]);
});

Router::add('messages/poll', function () {
    $conv = msg_conv_or_404(gint('id'));
    $messages = Messaging::messages((int)$conv['id'], gint('after'));
    $html = '';
    foreach ($messages as $m) {
        $html .= View::fetch('mensajeria:_message', ['m' => $m, 'conv' => $conv]);
    }
    if ($messages) {
        Messaging::markRead($conv, uid());
    }
    json_out(['ok' => true, 'html' => $html, 'last' => $messages ? (int)end($messages)['id'] : gint('after')]);
});
