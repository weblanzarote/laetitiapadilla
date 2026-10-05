<?php defined('AULA') || exit; ?>
<h1 class="auth-title">Recuperar contraseña</h1>
<?php if ($sent): ?>
    <div class="alert alert-ok">Si ese email tiene cuenta en el aula, te hemos enviado un enlace para elegir una contraseña nueva. Revisa también la carpeta de spam.</div>
<?php else: ?>
    <p class="muted">Escribe tu email y te enviaremos un enlace para cambiarla.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= url('forgot') ?>" class="form">
        <?= csrf_field() ?>
        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autocomplete="email" autofocus>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Enviarme el enlace</button>
    </form>
<?php endif; ?>
<p class="auth-links"><a href="<?= url('login') ?>">Volver a entrar</a></p>
