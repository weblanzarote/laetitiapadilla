<?php defined('AULA') || exit; ?>
<h1 class="page-title"><?= $course ? 'Ajustes del curso' : 'Nuevo curso' ?></h1>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<form method="post" action="<?= url('course/edit', $course ? ['id' => $course['id']] : []) ?>" class="form card form-wide">
    <?= csrf_field() ?>
    <div class="field">
        <label for="title">Título del curso</label>
        <input type="text" id="title" name="title" value="<?= e($v['title']) ?>" required maxlength="200" placeholder="Francés B1 · 2026-2027">
    </div>
    <?= editor_field('summary', (string)$v['summary'], 'Presentación (opcional)', 'Se ve en la portada del curso.') ?>
    <div class="field-row">
        <div class="field">
            <label for="enrol_code">Código de inscripción</label>
            <input type="text" id="enrol_code" name="enrol_code" value="<?= e($v['enrol_code']) ?>" class="input-code" maxlength="40" placeholder="Se genera solo si lo dejas vacío">
            <small class="hint">Es lo que tus alumnos escriben al registrarse. Letras, números y guiones.</small>
        </div>
        <div class="field field-check">
            <label class="check"><input type="checkbox" name="enrol_open" value="1" <?= (int)$v['enrol_open'] ? 'checked' : '' ?>> Inscripción abierta (el código funciona)</label>
            <label class="check"><input type="checkbox" name="visible" value="1" <?= (int)$v['visible'] ? 'checked' : '' ?>> Curso visible para el alumnado</label>
        </div>
    </div>
    <div class="btn-row">
        <button type="submit" class="btn btn-primary"><?= icon('check') ?> <?= $course ? 'Guardar' : 'Crear curso' ?></button>
        <a class="btn btn-ghost" href="<?= $course ? url('course', ['id' => $course['id']]) : url() ?>">Cancelar</a>
    </div>
</form>

<?php if ($course): ?>
    <div class="card">
        <h2 class="card-title"><?= icon('clone') ?> Duplicar para otra edición</h2>
        <p class="muted">Crea una copia con todas las unidades y contenidos, pero sin alumnos. Ideal para reutilizar el curso el año que viene.</p>
        <?= post_button('course/duplicate', ['id' => $course['id']], icon('clone') . ' Duplicar curso', 'btn', '', '¿Duplicar «' . $course['title'] . '»?') ?>
    </div>
    <div class="card danger-zone">
        <h2 class="card-title"><?= icon('triangle-exclamation') ?> Borrar el curso</h2>
        <p class="muted">Se borran sus unidades, contenidos y archivos, y se da de baja a todo el alumnado. No se puede deshacer.</p>
        <form method="post" action="<?= url('course/delete') ?>" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$course['id'] ?>">
            <input type="text" name="confirm" placeholder="Escribe BORRAR" autocomplete="off" required>
            <button type="submit" class="btn btn-danger">Borrar curso</button>
        </form>
    </div>
<?php endif; ?>
