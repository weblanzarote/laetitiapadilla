<?php defined('AULA') || exit; ?>
<h1 class="page-title">Mi perfil</h1>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= url('profile') ?>" class="form card form-narrow">
    <?= csrf_field() ?>
    <div class="field">
        <label for="name">Nombre y apellidos</label>
        <input type="text" id="name" name="name" value="<?= e($u['name']) ?>" required>
    </div>
    <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($u['email']) ?>" required>
    </div>

    <?php if ($prefFields): ?>
        <fieldset class="fieldset">
            <legend>Avisos</legend>
            <?php foreach ($prefFields as $f): ?>
                <label class="check">
                    <input type="checkbox" name="pref[<?= e($f['key']) ?>]" value="1" <?= user_pref($u, $f['key'], $f['default'] ?? true) ? 'checked' : '' ?>>
                    <?= e($f['label']) ?>
                </label>
            <?php endforeach; ?>
        </fieldset>
    <?php endif; ?>

    <fieldset class="fieldset">
        <legend>Cambiar contraseña <span class="muted">(déjalo vacío para no cambiarla)</span></legend>
        <div class="field">
            <label for="current">Contraseña actual</label>
            <input type="password" id="current" name="current" autocomplete="current-password">
        </div>
        <div class="field-row">
            <div class="field">
                <label for="password">Nueva</label>
                <input type="password" id="password" name="password" minlength="8" autocomplete="new-password">
            </div>
            <div class="field">
                <label for="password2">Repítela</label>
                <input type="password" id="password2" name="password2" minlength="8" autocomplete="new-password">
            </div>
        </div>
    </fieldset>
    <button type="submit" class="btn btn-primary">Guardar</button>
</form>
