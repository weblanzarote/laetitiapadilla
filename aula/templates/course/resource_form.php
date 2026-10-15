<?php defined('AULA') || exit;
$action = $res['id'] ? url('resource/edit', ['id' => $res['id']]) : url('resource/edit', ['section_id' => $res['section_id'], 'parent_id' => $res['parent_id'], 'type' => $type->id()]);
?>
<div class="page-head">
    <div>
        <p class="kicker"><span class="res-icon res-icon-<?= e($type->id()) ?> res-icon-sm"><?= icon($type->icon()) ?></span> <?= e($type->label()) ?></p>
        <h1 class="page-title"><?= Courses::isFolder($res) ? ($res['id'] ? 'Editar carpeta' : 'Nueva carpeta') : ($res['id'] ? 'Editar contenido' : 'Añadir contenido') ?></h1>
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
            <label for="location">Dónde va</label>
            <select id="location" name="location">
                <?php foreach ($locations as $loc):
                    if ($res['id'] && in_array((int)$res['id'], $loc['path'], true)) continue; // una carpeta no puede ir dentro de sí misma
                    $here = $loc['section_id'] === (int)$res['section_id'] && $loc['parent_id'] === (int)($res['parent_id'] ?? 0);
                    ?>
                    <option value="<?= $loc['section_id'] ?>:<?= $loc['parent_id'] ?>" <?= $here ? 'selected' : '' ?>><?= str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $loc['depth']) ?><?= $loc['parent_id'] ? '📁 ' : '' ?><?= e($loc['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field field-check">
            <label class="check"><input type="checkbox" name="visible" value="1" <?= (int)$res['visible'] ? 'checked' : '' ?>> Visible para el alumnado</label>
        </div>
    </div>

    <div class="btn-row">
        <button type="submit" class="btn btn-primary"><?= icon('check') ?> Guardar</button>
        <a class="btn btn-ghost" href="<?= url('course', ['id' => $course['id']]) ?><?= $res['id'] ? '#r' . (int)$res['id'] : ($res['parent_id'] ? '#r' . (int)$res['parent_id'] : '#s' . (int)$res['section_id']) ?>">Cancelar</a>
    </div>
</form>
