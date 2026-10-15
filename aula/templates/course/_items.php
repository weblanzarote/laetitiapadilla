<?php defined('AULA') || exit;
/**
 * Lista de contenidos de un apartado o de una carpeta. Las carpetas se pintan
 * llamando otra vez a esta plantilla con su contenido.
 * Variables: $items, $teacher, $children, $status, $folders, $locations, $types.
 */
?>
<ul class="res-list">
    <?php foreach ($items as $r):
        $type = Courses::type($r);
        $rid = (int)$r['id'];
        $isFolder = Courses::isFolder($r);
        if ($isFolder) {
            [$done, $total] = $folders[$rid] ?? [0, 0];
            $st = !$teacher && $done ? ['done' => $done === $total, 'label' => $done === $total ? 'Todo hecho' : "$done de $total hechos"] : null;
        } else {
            $st = $status[$rid] ?? null;
        }
        $newTab = !$isFolder && $type->opensNewTab($r);
        $meta = $isFolder ? plural($total, 'contenido', 'contenidos') : $type->meta($r);
        ?>
        <li class="res-item<?= $isFolder ? ' res-folder' : '' ?><?= (int)$r['visible'] ? '' : ' is-hidden' ?><?= $st && !empty($st['done']) ? ' is-done' : '' ?>" id="r<?= $rid ?>">
            <?php if ($isFolder): ?>
                <button type="button" class="res-link folder-toggle" aria-expanded="false" aria-controls="f<?= $rid ?>">
                    <span class="res-icon res-icon-folder"><?= icon('folder') ?><?= icon('folder-open') ?></span>
                    <span class="res-text">
                        <span class="res-title"><?= e($r['title']) ?></span>
                        <span class="res-meta"><?= e($type->label()) ?> · <?= e($meta) ?></span>
                    </span>
                    <?= icon('chevron-down', 'folder-caret') ?>
                </button>
            <?php else: ?>
                <a class="res-link" href="<?= url('resource', ['id' => $rid]) ?>"<?= $newTab ? ' target="_blank" rel="noopener"' : '' ?>>
                    <span class="res-icon res-icon-<?= e($r['type']) ?>"><?= icon($type->icon()) ?></span>
                    <span class="res-text">
                        <span class="res-title"><?= e($r['title']) ?><?= $newTab ? ' ' . icon('arrow-up-right-from-square', 'res-ext') : '' ?></span>
                        <span class="res-meta"><?= e($type->label()) ?><?= $meta !== '' ? ' · ' . e($meta) : '' ?></span>
                    </span>
                </a>
            <?php endif; ?>
            <?php if ($st): ?>
                <span class="res-status<?= !empty($st['done']) ? ' done' : '' ?>" title="<?= e($st['label']) ?>">
                    <?= icon(!empty($st['done']) ? 'circle-check' : 'circle-half-stroke') ?><span class="sr-only"><?= e($st['label']) ?></span>
                </span>
            <?php endif; ?>
            <?php if ($teacher):
                $what = $isFolder ? 'carpeta' : 'contenido';
                ?>
                <div class="tools">
                    <?php if (!(int)$r['visible']): ?><span class="tag tag-muted"><?= $isFolder ? 'Oculta' : 'Oculto' ?></span><?php endif; ?>
                    <?= post_button('resource/move', ['id' => $rid, 'dir' => -1], icon('arrow-up'), 'icon-btn', 'Subir') ?>
                    <?= post_button('resource/move', ['id' => $rid, 'dir' => 1], icon('arrow-down'), 'icon-btn', 'Bajar') ?>
                    <?= post_button('resource/toggle', ['id' => $rid], icon((int)$r['visible'] ? 'eye' : 'eye-slash'), 'icon-btn', (int)$r['visible'] ? 'Ocultar' : 'Mostrar') ?>
                    <?php if (count($locations) > 1): ?>
                        <details class="dropdown move-menu">
                            <summary class="icon-btn" title="Mover a otro apartado o carpeta" aria-label="Mover a otro apartado o carpeta"><?= icon('folder-tree') ?></summary>
                            <div class="dropdown-menu dropdown-right">
                                <div class="dropdown-head"><small>Mover a…</small></div>
                                <?php foreach ($locations as $loc):
                                    if (in_array($rid, $loc['path'], true)) continue; // la propia carpeta y lo que tiene dentro
                                    $label = '<span class="move-dest" style="--depth: ' . (int)$loc['depth'] . '">'
                                        . icon($loc['parent_id'] ? 'folder' : 'layer-group') . ' ' . e($loc['title']) . '</span>';
                                    $here = $loc['section_id'] === (int)$r['section_id'] && $loc['parent_id'] === (int)$r['parent_id'];
                                    ?>
                                    <?php if ($here): ?>
                                        <span class="move-here" title="Está aquí"><?= $label ?> <small>(aquí)</small></span>
                                    <?php else: ?>
                                        <?= post_button('resource/section', ['id' => $rid, 'section_id' => $loc['section_id'], 'parent_id' => $loc['parent_id']], $label, '') ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php endif; ?>
                    <a class="icon-btn" href="<?= url('resource/edit', ['id' => $rid]) ?>" title="Editar" aria-label="Editar"><?= icon('pen') ?></a>
                    <?= post_button('resource/duplicate', ['id' => $rid], icon('clone'), 'icon-btn', $isFolder ? 'Duplicar carpeta con su contenido' : 'Duplicar') ?>
                    <?= post_button('resource/delete', ['id' => $rid], icon('trash'), 'icon-btn icon-danger', 'Borrar',
                        $isFolder ? '¿Borrar la carpeta «' . $r['title'] . '» y todo lo que tiene dentro?' : '¿Borrar «' . $r['title'] . '»?') ?>
                </div>
            <?php endif; ?>
            <?php if ($isFolder): $kids = $children[$rid] ?? []; ?>
                <div class="folder-body" id="f<?= $rid ?>" hidden>
                    <?php if ($r['description']): ?><div class="prose folder-desc"><?= $r['description'] ?></div><?php endif; ?>
                    <?php if ($kids): ?>
                        <?= partial('course/_items', ['items' => $kids] + compact('teacher', 'children', 'status', 'folders', 'locations', 'types')) ?>
                    <?php else: ?>
                        <p class="muted folder-empty"><?= $teacher ? 'Carpeta vacía.' : 'Pronto habrá contenido aquí.' ?></p>
                    <?php endif; ?>
                    <?php if ($teacher): ?>
                        <?= partial('course/_add_menu', ['label' => 'Añadir a esta carpeta', 'params' => ['parent_id' => $rid], 'types' => $types]) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
