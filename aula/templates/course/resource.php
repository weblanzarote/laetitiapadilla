<?php defined('AULA') || exit; $teacher = is_teacher(); ?>
<article class="resource-page">
    <header class="page-head">
        <div>
            <p class="kicker">
                <span class="res-icon res-icon-<?= e($res['type']) ?> res-icon-sm"><?= icon($type->icon()) ?></span>
                <?= e($section['title'] ?? '') ?>
                <?php if ($teacher && !(int)$res['visible']): ?><span class="tag tag-muted">Oculto para el alumnado</span><?php endif; ?>
            </p>
            <h1 class="page-title"><?= e($res['title']) ?></h1>
        </div>
        <?php if ($teacher): ?>
            <a class="btn btn-ghost" href="<?= url('resource/edit', ['id' => $res['id']]) ?>"><?= icon('pen') ?> Editar</a>
        <?php endif; ?>
    </header>

    <?php if ($res['description']): ?>
        <div class="prose resource-desc"><?= $res['description'] ?></div>
    <?php endif; ?>

    <div class="resource-body"><?= $type->render($res, $course) ?></div>

    <?= Hooks::capture('resource_bottom', $res, $course) ?>

    <?php if ($teacher): ?>
        <?= $type->teacherPanel($res, $course) ?>
    <?php endif; ?>

    <nav class="pager">
        <?php if ($prev): ?>
            <a class="pager-link" href="<?= url('resource', ['id' => $prev['id']]) ?>"><?= icon('arrow-left') ?> <span><small>Anterior</small><?= e($prev['title']) ?></span></a>
        <?php else: ?><span></span><?php endif; ?>
        <a class="pager-up" href="<?= url('course', ['id' => $course['id']]) ?>#r<?= (int)$res['id'] ?>"><?= icon('list') ?> Índice del curso</a>
        <?php if ($next): ?>
            <a class="pager-link pager-next" href="<?= url('resource', ['id' => $next['id']]) ?>"><span><small>Siguiente</small><?= e($next['title']) ?></span> <?= icon('arrow-right') ?></a>
        <?php else: ?><span></span><?php endif; ?>
    </nav>
</article>
