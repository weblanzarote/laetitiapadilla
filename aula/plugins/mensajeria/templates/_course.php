<?php defined('AULA') || exit; ?>
<section class="card announcements">
    <header class="unit-head">
        <h2 class="card-title"><?= icon('bullhorn') ?> Avisos</h2>
        <?php if (is_teacher()): ?>
            <a class="btn btn-sm" href="<?= url('messages/new', ['course' => $course['id']]) ?>"><?= icon('plus') ?> Nuevo aviso</a>
        <?php endif; ?>
    </header>
    <?php if (!$list): ?>
        <p class="muted">Aún no hay avisos. Los avisos llegan a todo el alumnado del curso (y por email si está activado).</p>
    <?php else: ?>
        <ul class="announce-list">
            <?php foreach ($list as $a): ?>
                <li>
                    <a href="<?= url('messages/view', ['id' => $a['id']]) ?>">
                        <strong><?= e($a['subject']) ?></strong>
                        <span class="muted"><?= e(excerpt((string)$a['body'], 140)) ?></span>
                    </a>
                    <time class="muted small"><?= fmt_date((int)$a['posted_at'], 'relative') ?></time>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
