<?php defined('AULA') || exit; ?>
<div class="page-head">
    <h1 class="page-title">Mensajes</h1>
    <div class="btn-row">
        <?php if (is_teacher()): ?>
            <a class="btn btn-ghost" href="<?= url('messages/new', ['course' => $courses[0]['id'] ?? 0]) ?>"><?= icon('bullhorn') ?> Aviso a un curso</a>
        <?php endif; ?>
        <?php if ($canStart): ?>
            <a class="btn btn-primary" href="<?= url('messages/new') ?>"><?= icon('pen-to-square') ?> Nuevo mensaje</a>
        <?php endif; ?>
    </div>
</div>

<div class="msg-toolbar">
    <nav class="tabs">
        <a href="<?= url('messages') ?>" class="<?= $box === 'inbox' ? 'active' : '' ?>"><?= icon('inbox') ?> Bandeja</a>
        <a href="<?= url('messages', ['box' => 'archived']) ?>" class="<?= $box === 'archived' ? 'active' : '' ?>"><?= icon('box-archive') ?> Archivados</a>
    </nav>
    <form method="get" action="<?= url() ?>" class="inline-form">
        <input type="hidden" name="r" value="messages">
        <?php if ($box === 'archived'): ?><input type="hidden" name="box" value="archived"><?php endif; ?>
        <?php if ($courses): ?>
            <select name="course" aria-label="Curso" onchange="this.form.submit()">
                <option value="0">Todos los cursos</option>
                <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $courseId ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?>
            </select>
        <?php endif; ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar" aria-label="Buscar en mensajes">
    </form>
</div>

<?php if (!$convs): ?>
    <div class="empty-state card">
        <div class="empty-icon"><?= icon($box === 'archived' ? 'box-archive' : 'comments') ?></div>
        <p><?= $q !== '' ? 'No hay mensajes que coincidan con la búsqueda.' : ($box === 'archived' ? 'No tienes conversaciones archivadas.' : 'No tienes mensajes todavía.') ?></p>
        <?php if ($canStart && $box === 'inbox' && $q === ''): ?><a class="btn btn-primary" href="<?= url('messages/new') ?>">Escribir un mensaje</a><?php endif; ?>
    </div>
<?php else: ?>
    <ul class="conv-list card">
        <?php foreach ($convs as $c):
            $title = Messaging::title($c, $c['people'], uid());
            $others = array_values(array_filter($c['people'], fn($p) => $p['id'] !== uid()));
            $last = $c['last'];
            $mine = $last && (int)$last['user_id'] === uid();
            ?>
            <li class="conv<?= (int)$c['unread'] ? ' is-unread' : '' ?>">
                <a href="<?= url('messages/view', ['id' => $c['id']]) ?>">
                    <span class="avatar<?= $c['kind'] === 'announcement' ? ' avatar-announce' : '' ?>">
                        <?= $c['kind'] === 'announcement' ? icon('bullhorn') : ($c['kind'] === 'group' ? icon('users') : e(initials($others[0]['name'] ?? '?'))) ?>
                    </span>
                    <span class="conv-main">
                        <span class="conv-top">
                            <strong class="conv-who"><?= e($title) ?></strong>
                            <time><?= fmt_date((int)$c['last_message_at'], 'relative') ?></time>
                        </span>
                        <span class="conv-subject"><?= e($c['subject']) ?></span>
                        <span class="conv-excerpt"><?= $mine ? 'Tú: ' : '' ?><?= e($last ? ($last['body'] === '[deleted]' ? 'Mensaje borrado' : excerpt((string)$last['body'], 110)) : '') ?></span>
                    </span>
                    <?php if ((int)$c['unread']): ?><span class="badge badge-count"><?= (int)$c['unread'] ?></span><?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
