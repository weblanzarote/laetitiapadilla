<?php defined('AULA') || exit;
/** Desplegable «Añadir contenido» con los tipos. Variables: $label, $params (section_id o parent_id), $types. */
?>
<details class="add-menu">
    <summary><?= icon('plus') ?> <?= e($label) ?></summary>
    <div class="type-grid">
        <?php foreach ($types as $t): ?>
            <a class="type-card" href="<?= url('resource/edit', $params + ['type' => $t->id()]) ?>">
                <span class="res-icon res-icon-<?= e($t->id()) ?>"><?= icon($t->icon()) ?></span>
                <strong><?= e($t->label()) ?></strong>
                <small><?= e($t->help()) ?></small>
            </a>
        <?php endforeach; ?>
    </div>
</details>
