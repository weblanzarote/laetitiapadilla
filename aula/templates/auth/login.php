<?php defined('AULA') || exit; ?>
<h1 class="auth-title">Entrar al aula</h1>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= url('login') ?>" class="form">
    <?= csrf_field() ?>
    <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="email" autofocus>
    </div>
    <div class="field">
        <label for="password">Contraseña</label>
        <div class="password-wrap">
            <input type="password" id="password" name="password" required autocomplete="current-password">
            <button type="button" class="password-toggle" aria-label="Mostrar contraseña"><?= icon('eye') ?></button>
        </div>
    </div>
    <label class="check"><input type="checkbox" name="remember" value="1" checked> Recordarme en este dispositivo</label>
    <button type="submit" class="btn btn-primary btn-block">Entrar</button>
</form>
<p class="auth-links"><a href="<?= url('forgot') ?>">¿Has olvidado tu contraseña?</a></p>
<?php if ((int)setting('registration_enabled', 1) === 1): ?>
    <div class="auth-alt">
        <p>¿Primera vez? Tu profesora te habrá dado un <strong>código de curso</strong>.</p>
        <a class="btn btn-ghost btn-block" href="<?= url('register') ?>">Crear mi cuenta</a>
    </div>
<?php endif; ?>
