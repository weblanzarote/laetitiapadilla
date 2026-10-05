<?php defined('AULA') || exit; ?>
<h1 class="page-title">Ajustes</h1>
<form method="post" action="<?= url('admin/settings') ?>" class="form">
    <?= csrf_field() ?>
    <?php foreach ($sections as $section): ?>
        <section class="card settings-section" id="set-<?= e($section['id']) ?>">
            <h2 class="card-title"><?= e($section['title']) ?></h2>
            <?php if (!empty($section['description'])): ?><p class="muted"><?= e($section['description']) ?></p><?php endif; ?>
            <?php foreach ($section['fields'] as $f):
                $key = $f['key'];
                $val = setting($key, $f['default'] ?? '');
                $id = 'set_' . $key;
                ?>
                <?php if ($f['type'] === 'bool'): ?>
                    <label class="check"><input type="checkbox" name="s[<?= e($key) ?>]" value="1" <?= (int)$val ? 'checked' : '' ?>> <?= e($f['label']) ?></label>
                    <?php if (!empty($f['help'])): ?><small class="hint hint-check"><?= e($f['help']) ?></small><?php endif; ?>
                <?php elseif ($f['type'] === 'html'): ?>
                    <?= editor_field('s[' . $key . ']', (string)$val, $f['label'], $f['help'] ?? '', $id) ?>
                <?php else: ?>
                    <div class="field">
                        <label for="<?= e($id) ?>"><?= e($f['label']) ?></label>
                        <?php if ($f['type'] === 'textarea'): ?>
                            <textarea id="<?= e($id) ?>" name="s[<?= e($key) ?>]" rows="4"><?= e($val) ?></textarea>
                        <?php elseif ($f['type'] === 'select'): ?>
                            <select id="<?= e($id) ?>" name="s[<?= e($key) ?>]">
                                <?php foreach ($f['options'] as $ok => $ol): ?><option value="<?= e($ok) ?>" <?= (string)$val === (string)$ok ? 'selected' : '' ?>><?= e($ol) ?></option><?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="<?= $f['type'] === 'number' ? 'number' : 'text' ?>" id="<?= e($id) ?>" name="s[<?= e($key) ?>]" value="<?= e($val) ?>">
                        <?php endif; ?>
                        <?php if (!empty($f['help'])): ?><small class="hint"><?= e($f['help']) ?></small><?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
    <div class="sticky-actions"><button type="submit" class="btn btn-primary"><?= icon('check') ?> Guardar ajustes</button></div>
</form>
