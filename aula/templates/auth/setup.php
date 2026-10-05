<?php defined('AULA') || exit; ?>
<h1 class="auth-title">Instalar el aula</h1>
<p class="muted">Crea la cuenta de administración (la de la profesora). Solo se pide una vez.</p>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= url('setup') ?>" class="form">
    <?= csrf_field() ?>
    <div class="field">
        <label for="site_name">Nombre del aula</label>
        <input type="text" id="site_name" name="site_name" value="<?= e($v['site_name']) ?>" required>
    </div>
    <div class="field">
        <label for="name">Tu nombre</label>
        <input type="text" id="name" name="name" value="<?= e($v['name']) ?>" required autocomplete="name">
    </div>
    <div class="field">
        <label for="email">Tu email</label>
        <input type="email" id="email" name="email" value="<?= e($v['email']) ?>" required autocomplete="email">
    </div>
    <div class="field-row">
        <div class="field">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
        </div>
        <div class="field">
            <label for="password2">Repítela</label>
            <input type="password" id="password2" name="password2" required minlength="8" autocomplete="new-password">
        </div>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Instalar</button>
</form>
