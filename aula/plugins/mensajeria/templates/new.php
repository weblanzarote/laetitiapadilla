<?php defined('AULA') || exit;
$selected = array_map('intval', $v['to']);
$single = !$teacher && count($groups) === 1 && count(reset($groups)) === 1 ? reset($groups)[0] : null;
?>
<h1 class="page-title"><?= $v['mode'] === 'announcement' ? 'Nuevo aviso' : 'Nuevo mensaje' ?></h1>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<form method="post" action="<?= url('messages/new') ?>" enctype="multipart/form-data" class="form card form-wide" data-msg-form>
    <?= csrf_field() ?>

    <?php if ($teacher): ?>
        <div class="segmented" role="radiogroup" aria-label="Tipo de envío">
            <label><input type="radio" name="mode" value="people" <?= $v['mode'] === 'people' ? 'checked' : '' ?>> <?= icon('user') ?> A personas concretas</label>
            <label><input type="radio" name="mode" value="announcement" <?= $v['mode'] === 'announcement' ? 'checked' : '' ?>> <?= icon('bullhorn') ?> Aviso a todo un curso</label>
        </div>

        <div class="field" data-mode="announcement">
            <label for="course_id">Curso</label>
            <select id="course_id" name="course_id">
                <?php foreach ($courses as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)$v['course_id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="hint">Lo recibe todo el alumnado inscrito (y quien se inscriba más tarde). Responden en privado.</small>
        </div>
    <?php endif; ?>

    <div class="field" data-mode="people">
        <?php if ($single): ?>
            <label>Para</label>
            <p class="to-fixed"><span class="avatar avatar-sm"><?= e(initials($single['name'])) ?></span> <?= e($single['name']) ?></p>
            <input type="hidden" name="to[]" value="<?= (int)$single['id'] ?>">
        <?php elseif (!$teacher): ?>
            <label for="to">Para</label>
            <select id="to" name="to[]" required>
                <option value="">— Elige —</option>
                <?php foreach ($groups as $gname => $list): ?>
                    <optgroup label="<?= e($gname) ?>">
                        <?php foreach ($list as $p): ?><option value="<?= (int)$p['id'] ?>" <?= in_array((int)$p['id'], $selected, true) ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <label>Para <span class="muted" data-picked-count></span></label>
            <div class="picker" data-picker>
                <input type="search" placeholder="Buscar alumno/a…" data-picker-search aria-label="Buscar destinatarios">
                <?php if (!$groups): ?><p class="muted">Todavía no hay alumnado.</p><?php endif; ?>
                <?php foreach ($groups as $gname => $list): ?>
                    <fieldset class="picker-group">
                        <legend>
                            <span><?= e($gname) ?></span>
                            <button type="button" class="link-btn" data-pick-all>Seleccionar todos</button>
                        </legend>
                        <?php foreach ($list as $p): ?>
                            <label class="check picker-item" data-name="<?= e(mb_strtolower($p['name'] . ' ' . $p['email'])) ?>">
                                <input type="checkbox" name="to[]" value="<?= (int)$p['id'] ?>" <?= in_array((int)$p['id'], $selected, true) ? 'checked' : '' ?>>
                                <?= e($p['name']) ?> <small class="muted"><?= e($p['email']) ?></small>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endforeach; ?>
            </div>
            <label class="check"><input type="checkbox" name="group" value="1" <?= $v['group'] ? 'checked' : '' ?>> <span>Si eliges a varias personas: crear <strong>una conversación de grupo</strong> (todos ven las respuestas). Si no, cada una recibe el mensaje por separado.</span></label>
        <?php endif; ?>
    </div>

    <div class="field">
        <label for="subject">Asunto</label>
        <input type="text" id="subject" name="subject" value="<?= e($v['subject']) ?>" required maxlength="200">
    </div>
    <div class="field">
        <label for="body">Mensaje</label>
        <textarea id="body" name="body" rows="8" required data-autosize><?= e($v['body']) ?></textarea>
    </div>
    <?php if ($attachments): ?>
        <?= upload_field('attachments', 'Adjuntos (opcional)', '', true, false, 'Hasta 10 archivos.') ?>
    <?php endif; ?>
    <div class="btn-row">
        <button type="submit" class="btn btn-primary"><?= icon('paper-plane') ?> Enviar</button>
        <a class="btn btn-ghost" href="<?= url('messages') ?>">Cancelar</a>
    </div>
</form>
