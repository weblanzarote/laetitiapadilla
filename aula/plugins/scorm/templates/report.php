<?php defined('AULA') || exit; ?>
<section class="card table-card scorm-report">
    <h2 class="card-title"><?= icon('chart-column') ?> Seguimiento del alumnado</h2>
    <?php if (!$rows): ?>
        <p class="muted">Todavía no hay alumnado inscrito en el curso.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Alumno/a</th><th>Estado</th><th>Nota</th><th>Tiempo</th><th>Última vez</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $s = $r['sum']; $u = $r['user']; ?>
                <tr>
                    <td><a href="<?= url('admin/user', ['id' => $u['id']]) ?>"><?= e($u['name']) ?></a></td>
                    <td>
                        <?php if (!$s['started']): ?>
                            <span class="tag tag-muted">Sin empezar</span>
                        <?php elseif ($s['done']): ?>
                            <span class="tag tag-ok"><?= icon('circle-check') ?> <?= e($s['label']) ?></span>
                        <?php elseif ($s['failed']): ?>
                            <span class="tag tag-danger"><?= e($s['label']) ?></span>
                        <?php else: ?>
                            <span class="tag tag-warn"><?= e($s['label']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= $s['score'] !== null ? e(str_replace('.', ',', (string)$s['score'])) : '—' ?></td>
                    <td><?= fmt_duration((int)$s['seconds']) ?></td>
                    <td><?= $s['last'] ? fmt_date((int)$s['last'], 'relative') : '—' ?></td>
                    <td class="t-right">
                        <?php if ($s['started']): ?>
                            <?= post_button('scorm/reset', ['resource' => $res['id'], 'user' => $u['id']], icon('rotate-left'), 'icon-btn', 'Reiniciar su progreso', '¿Borrar el progreso de ' . $u['name'] . ' en esta actividad? Empezará de cero.') ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="muted small">La nota es la que guarda el propio paquete (sobre 100). Si tiene varios apartados, se muestra la media.</p>
    <?php endif; ?>
</section>
