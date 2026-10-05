<?php defined('AULA') || exit; ?>
<h1 class="auth-title">Crear mi cuenta</h1>
<?php if (!$open): ?>
    <div class="alert alert-info">El registro está cerrado ahora mismo. Escribe a tu profesora para que te dé acceso.</div>
    <p class="auth-links"><a href="<?= url('login') ?>">Ya tengo cuenta: entrar</a></p>
<?php else: ?>
    <p class="muted">Solo necesitas el código de curso que te ha dado tu profesora.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= url('register') ?>" class="form">
        <?= csrf_field() ?>
        <div class="field">
            <label for="code">Código del curso</label>
            <input type="text" id="code" name="code" value="<?= e($v['code']) ?>" required autocomplete="off" class="input-code" placeholder="p. ej. FRB1-2026">
        </div>
        <div class="field">
            <label for="name">Nombre y apellidos</label>
            <input type="text" id="name" name="name" value="<?= e($v['name']) ?>" required autocomplete="name">
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e($v['email']) ?>" required autocomplete="email">
        </div>
        <div class="field-row">
            <div class="field">
                <label for="password">Contraseña</label>
                <div class="password-wrap">
                    <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                    <button type="button" class="password-toggle" aria-label="Mostrar contraseña"><?= icon('eye') ?></button>
                </div>
                <small class="hint">Mínimo 8 caracteres.</small>
            </div>
            <div class="field">
                <label for="password2">Repítela</label>
                <input type="password" id="password2" name="password2" required minlength="8" autocomplete="new-password">
            </div>
        </div>
        <div class="hp-field" aria-hidden="true"><label>Web <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <label class="check"><input type="checkbox" name="privacy" value="1" required> <span>He leído y acepto la <a href="<?= url('privacy') ?>" target="_blank">política de privacidad</a></span></label>
        <button type="submit" class="btn btn-primary btn-block">Crear cuenta y entrar</button>
    </form>
    <p class="auth-links"><a href="<?= url('login') ?>">Ya tengo cuenta: entrar</a></p>
<?php endif; ?>
