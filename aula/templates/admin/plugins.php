<?php defined('AULA') || exit; ?>
<h1 class="page-title">Extensiones</h1>
<p class="muted">Las extensiones añaden funciones al aula. Al desactivar una, sus datos se conservan y vuelven si la activas otra vez.
    Para instalar una nueva, se copia su carpeta en <code>aula/plugins/</code>.</p>
<div class="plugin-list">
    <?php foreach ($plugins as $id => $p): $on = in_array($id, $enabled, true); ?>
        <article class="card plugin<?= $on ? ' is-on' : '' ?>">
            <span class="plugin-icon"><?= icon($p['icon'] ?? 'puzzle-piece') ?></span>
            <div class="plugin-text">
                <h2><?= e($p['title']) ?> <small class="muted">v<?= e($p['version']) ?></small></h2>
                <p><?= e($p['description']) ?></p>
            </div>
            <div class="plugin-action">
                <?php if (!empty($p['required'])): ?>
                    <span class="tag tag-ok">Imprescindible</span>
                <?php elseif ($on): ?>
                    <?= post_button('admin/plugins', ['id' => $id, 'on' => 0], 'Desactivar', 'btn btn-sm btn-ghost') ?>
                <?php else: ?>
                    <?= post_button('admin/plugins', ['id' => $id, 'on' => 1], 'Activar', 'btn btn-sm btn-primary') ?>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>
