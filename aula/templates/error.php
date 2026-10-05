<?php defined('AULA') || exit; ?>
<div class="empty-state">
    <div class="empty-icon"><?= icon($status === 404 ? 'map-signs' : ($status === 403 ? 'lock' : ($status === 419 ? 'clock-rotate-left' : 'triangle-exclamation'))) ?></div>
    <h1 class="page-title"><?= e($status === 404 ? 'No lo encontramos' : ($status === 403 ? 'Sin acceso' : 'Vaya…')) ?></h1>
    <p><?= e($message) ?></p>
    <p>
        <?php if ($status === 401): ?>
            <a class="btn btn-primary" href="<?= url('login') ?>">Entrar</a>
        <?php else: ?>
            <a class="btn btn-primary" href="<?= url() ?>">Ir al inicio</a>
        <?php endif; ?>
    </p>
</div>
