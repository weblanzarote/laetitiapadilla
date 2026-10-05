<?php defined('AULA') || exit;
$launch = Scorm::launchable($pkg);
?>
<div class="scorm-player<?= $showToc ? ' has-toc' : '' ?>" data-scorm="<?= e(json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
    <?php if ($showToc): ?>
        <aside class="scorm-toc" aria-label="Apartados">
            <p class="scorm-toc-title"><?= e($pkg['title']) ?></p>
            <ol>
                <?php foreach ($pkg['items'] as $it): $t = $tracks[$it['id']] ?? null; ?>
                    <?php if (empty($it['href'])): ?>
                        <li class="toc-group" style="--lvl: <?= (int)$it['level'] ?>"><?= e($it['title']) ?></li>
                    <?php else: ?>
                        <li style="--lvl: <?= (int)$it['level'] ?>">
                            <button type="button" class="toc-item" data-sco="<?= e($it['id']) ?>">
                                <span class="toc-st toc-st-<?= ($it['type'] ?? 'sco') === 'asset' ? 'asset' : e($t ? str_replace(' ', '-', $t['status']) : 'none') ?>" aria-hidden="true"></span>
                                <span><?= e($it['title']) ?></span>
                            </button>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ol>
        </aside>
    <?php endif; ?>
    <div class="scorm-stage">
        <div class="scorm-bar">
            <?php if ($showToc): ?>
                <button type="button" class="icon-btn" data-scorm-toggle title="Mostrar u ocultar el índice" aria-label="Índice"><?= icon('list') ?></button>
            <?php endif; ?>
            <strong class="scorm-current" data-scorm-title></strong>
            <span class="scorm-state" data-scorm-state></span>
            <?php if (count($launch) > 1): ?>
                <button type="button" class="icon-btn" data-scorm-prev title="Apartado anterior" aria-label="Apartado anterior"><?= icon('chevron-left') ?></button>
                <button type="button" class="icon-btn" data-scorm-next title="Apartado siguiente" aria-label="Apartado siguiente"><?= icon('chevron-right') ?></button>
            <?php endif; ?>
            <button type="button" class="icon-btn" data-scorm-full title="Pantalla completa" aria-label="Pantalla completa"><?= icon('expand') ?></button>
        </div>
        <iframe class="scorm-frame" title="<?= e($res['title']) ?>" allow="fullscreen; autoplay; clipboard-write; microphone" allowfullscreen></iframe>
    </div>
</div>
<p class="muted small scorm-note"><?= icon('floppy-disk') ?> Tu progreso se guarda automáticamente: puedes salir y continuar más tarde.</p>
<script src="<?= e(Plugins::asset('scorm', 'player.js')) ?>"></script>
