<?php defined('AULA') || exit; ?>
<h1 class="page-title">Sistema y copias de seguridad</h1>
<div class="grid-main">
    <section>
        <div class="card table-card">
            <table class="table">
                <tbody>
                <?php foreach ($checks as [$label, $value, $ok]): ?>
                    <tr>
                        <th><?= e($label) ?></th>
                        <td><?= $ok ? icon('circle-check', 'ok') : icon('triangle-exclamation', 'warn') ?> <?= e($value) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr><th>Versión del aula</th><td><?= e(AULA_VERSION) ?></td></tr>
                </tbody>
            </table>
        </div>
        <?php if ($log !== ''): ?>
            <details class="card">
                <summary>Últimos errores registrados</summary>
                <pre class="log"><?= e($log) ?></pre>
            </details>
        <?php endif; ?>
    </section>
    <aside class="side">
        <div class="card">
            <h2 class="card-title"><?= icon('chart-simple') ?> Resumen</h2>
            <ul class="mini-list">
                <?php foreach ($counts as $label => $n): ?><li><span><?= e($label) ?></span> <strong><?= $n ?></strong></li><?php endforeach; ?>
            </ul>
        </div>
        <div class="card">
            <h2 class="card-title"><?= icon('download') ?> Copia de seguridad</h2>
            <p class="muted">Descarga un ZIP con la base de datos y todos los archivos subidos. Guárdalo en tu ordenador o en tu nube de vez en cuando.</p>
            <form method="post" action="<?= url('admin/backup') ?>" data-keep-enabled><?= csrf_field() ?>
                <button class="btn btn-primary" type="submit"><?= icon('download') ?> Descargar copia</button></form>
        </div>
    </aside>
</div>
