<?php defined('AULA') || exit;
$action = $res['id'] ? url('resource/edit', ['id' => $res['id']]) : url('resource/edit', ['section_id' => $res['section_id'], 'type' => $type->id()]);
?>
<div class="page-head">
    <div>
        <p class="kicker"><span class="res-icon res-icon-<?= e($type->id()) ?> res-icon-sm"><?= icon($type->icon()) ?></span> <?= e($type->label()) ?></p>
        <h1 class="page-title"><?= $res['id'] ? 'Editar contenido' : 'Añadir contenido' ?></h1>
        <?php if ($type->help()): ?><p class="muted"><?= e($type->help()) ?></p><?php endif; ?>
    </div>
</div>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="form card form-wide">
    <?= csrf_field() ?>
    <div class="field">
        <label for="title">Título</label>
        <input type="text" id="title" name="title" value="<?= e($res['title']) ?>" required maxlength="200" data-title-target>
    </div>

    <?= $type->form($res) ?>

    <?= editor_field('description', (string)$res['description'], 'Instrucciones o texto de presentación (opcional)', 'Aparece encima del contenido. Útil para explicar qué hay que hacer.') ?>

    <div class="field-row">
        <div class="field">
            <label for="section_id">Apartado</label>
            <select id="section_id" name="section_id">
                <?php foreach ($sections as $s): ?>
                    <option value="<?= (int)$s['id'] ?>" <?= (int)$s['id'] === (int)$res['section_id'] ? 'selected' : '' ?>><?= e($s['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field field-check">
            <label class="check"><input type="checkbox" name="visible" value="1" <?= (int)$res['visible'] ? 'checked' : '' ?>> Visible para el alumnado</label>
        </div>
    </div>

    <div class="btn-row">
        <button type="submit" class="btn btn-primary"><?= icon('check') ?> Guardar</button>
        <a class="btn btn-ghost" href="<?= url('course', ['id' => $course['id']]) ?><?= $res['id'] ? '#r' . (int)$res['id'] : '#s' . (int)$res['section_id'] ?>">Cancelar</a>
    </div>
</form>
