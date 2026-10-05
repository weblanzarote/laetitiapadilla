<?php defined('AULA') || exit; ?>
<h1 class="auth-title">Nueva contraseña</h1>
<?php if (!$valid): ?>
    <div class="alert alert-error">Este enlace no es válido o ha caducado. Pide uno nuevo.</div>
    <p class="auth-links"><a href="<?= url('forgot') ?>">Pedir otro enlace</a></p>
<?php else: ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= url('reset') ?>" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="t" value="<?= e($token) ?>">
        <div class="field">
            <label for="password">Contraseña nueva</label>
            <div class="password-wrap">
                <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password" autofocus>
                <button type="button" class="password-toggle" aria-label="Mostrar contraseña"><?= icon('eye') ?></button>
            </div>
            <small class="hint">Mínimo 8 caracteres.</small>
        </div>
        <div class="field">
            <label for="password2">Repítela</label>
            <input type="password" id="password2" name="password2" required minlength="8" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Guardar y entrar</button>
    </form>
<?php endif; ?>
