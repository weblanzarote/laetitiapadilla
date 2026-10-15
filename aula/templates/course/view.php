<?php defined('AULA') || exit;
// Los tipos de contenido, con la carpeta al final.
$types = ResourceTypes::all();
if (isset($types[FolderResourceType::ID])) {
    $folderType = $types[FolderResourceType::ID];
    unset($types[FolderResourceType::ID]);
    $types[FolderResourceType::ID] = $folderType;
}
$cid = (int)$course['id'];
$lists = compact('teacher', 'children', 'status', 'folders', 'locations', 'types');
?>
<header class="course-hero card">
    <div class="course-hero-cover">
        <?= Courses::cover($course) ?>
        <?php if ($teacher): ?>
            <a class="btn btn-sm cover-edit" href="<?= url('course/edit', ['id' => $cid]) ?>#portada"><?= icon('image') ?> Cambiar portada</a>
        <?php endif; ?>
    </div>
    <div class="course-hero-text">
        <p class="kicker"><?= $teacher ? 'Curso' : 'Mi curso' ?>
            <?php if ($teacher && !(int)$course['visible']): ?><span class="tag tag-muted"><?= icon('eye-slash') ?> Oculto para el alumnado</span><?php endif; ?>
        </p>
        <h1 class="page-title"><?= e($course['title']) ?></h1>
        <?php if ($course['summary']): ?><div class="prose"><?= $course['summary'] ?></div><?php endif; ?>
    </div>
    <?php if ($teacher): ?>
        <div class="course-hero-side">
            <small>Código de inscripción</small>
            <button type="button" class="code-chip" data-copy="<?= e($course['enrol_code']) ?>" title="Copiar"><?= e($course['enrol_code']) ?> <?= icon('copy') ?></button>
            <small class="muted"><?= (int)$course['enrol_open'] ? 'Inscripción abierta' : 'Inscripción cerrada' ?></small>
            <div class="btn-row">
                <a class="btn btn-sm" href="<?= url('course/participants', ['id' => $cid]) ?>"><?= icon('users') ?> Alumnos (<?= (int)$students ?>)</a>
                <a class="btn btn-sm btn-ghost" href="<?= url('course/edit', ['id' => $cid]) ?>"><?= icon('pen') ?> Ajustes</a>
                <a class="btn btn-sm btn-ghost" href="#nuevo-apartado" data-open="#nuevo-apartado"><?= icon('folder-plus') ?> Nuevo apartado</a>
            </div>
        </div>
    <?php elseif ($progress): [$done, $total] = $progress; $pct = $total ? (int)round($done * 100 / $total) : 0; ?>
        <div class="course-hero-side">
            <small>Tu avance</small>
            <strong class="big-number"><?= $pct ?>%</strong>
            <div class="progress"><span style="width: <?= $pct ?>%"></span></div>
            <small class="muted"><?= $done ?> de <?= $total ?> contenidos</small>
        </div>
    <?php endif; ?>
</header>

<?= Hooks::capture('course_top', $course) ?>

<?php if (!$sections): ?>
    <div class="empty-state card"><p>Este curso todavía no tiene contenidos.</p></div>
<?php endif; ?>

<?php foreach ($sections as $i => $s): $sid = (int)$s['id']; $items = $resources[$sid] ?? []; ?>
    <section class="unit card<?= (int)$s['visible'] ? '' : ' is-hidden' ?>" id="s<?= $sid ?>">
        <header class="unit-head">
            <h2 class="unit-title"><?= e($s['title']) ?></h2>
            <?php if ($teacher): ?>
                <div class="tools">
                    <?php if (!(int)$s['visible']): ?><span class="tag tag-muted">Oculta</span><?php endif; ?>
                    <?= post_button('section/move', ['id' => $sid, 'dir' => -1], icon('arrow-up'), 'icon-btn', 'Subir apartado') ?>
                    <?= post_button('section/move', ['id' => $sid, 'dir' => 1], icon('arrow-down'), 'icon-btn', 'Bajar apartado') ?>
                    <?= post_button('section/toggle', ['id' => $sid], icon((int)$s['visible'] ? 'eye' : 'eye-slash'), 'icon-btn', (int)$s['visible'] ? 'Ocultar apartado' : 'Mostrar apartado') ?>
                    <button type="button" class="icon-btn" data-toggle="#edit-s<?= $sid ?>" title="Editar apartado" aria-label="Editar apartado"><?= icon('pen') ?></button>
                    <?= post_button('section/duplicate', ['id' => $sid], icon('clone'), 'icon-btn', 'Duplicar apartado con su contenido') ?>
                    <?= post_button('section/delete', ['id' => $sid], icon('trash'), 'icon-btn icon-danger', 'Borrar apartado', '¿Borrar el apartado «' . $s['title'] . '» y todo su contenido?') ?>
                </div>
            <?php endif; ?>
        </header>

        <?php if ($teacher): ?>
            <form method="post" action="<?= url('section/save') ?>" class="form unit-edit" id="edit-s<?= $sid ?>" hidden>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $sid ?>">
                <div class="field"><label>Título del apartado</label><input type="text" name="title" value="<?= e($s['title']) ?>" required></div>
                <?= editor_field('summary', (string)$s['summary'], 'Introducción (opcional)', '', 'sum' . $sid) ?>
                <div class="btn-row"><button class="btn btn-primary btn-sm" type="submit">Guardar apartado</button>
                    <button class="btn btn-ghost btn-sm" type="button" data-toggle="#edit-s<?= $sid ?>">Cancelar</button></div>
            </form>
        <?php endif; ?>

        <?php if ($s['summary']): ?><div class="prose unit-summary"><?= $s['summary'] ?></div><?php endif; ?>

        <?php if ($items): ?>
            <?= partial('course/_items', ['items' => $items] + $lists) ?>
        <?php elseif (!$teacher): ?>
            <p class="muted unit-empty">Pronto habrá contenido aquí.</p>
        <?php endif; ?>

        <?php if ($teacher): ?>
            <?= partial('course/_add_menu', ['label' => 'Añadir contenido o carpeta', 'params' => ['section_id' => $sid], 'types' => $types]) ?>
        <?php endif; ?>
    </section>
<?php endforeach; ?>

<?php if ($teacher): ?>
    <details class="card add-unit" id="nuevo-apartado">
        <summary><?= icon('folder-plus') ?> Añadir apartado</summary>
        <form method="post" action="<?= url('section/save') ?>" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="course_id" value="<?= $cid ?>">
            <div class="field"><label for="new-unit">Título</label><input type="text" id="new-unit" name="title" placeholder="Gramática, Música, Vídeos… o Unité 1 · Se présenter" required></div>
            <?= editor_field('summary', '', 'Introducción (opcional)', '', 'new-unit-sum') ?>
            <button class="btn btn-primary" type="submit">Crear apartado</button>
        </form>
    </details>
<?php endif; ?>
