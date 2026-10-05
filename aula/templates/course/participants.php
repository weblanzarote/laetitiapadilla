<?php defined('AULA') || exit; $cid = (int)$course['id']; ?>
<div class="page-head">
    <div>
        <p class="kicker"><?= e($course['title']) ?></p>
        <h1 class="page-title">Participantes <span class="muted">(<?= count($students) ?>)</span></h1>
    </div>
    <div class="btn-row">
        <button type="button" class="btn btn-ghost" data-copy="<?= e(abs_url('register', ['code' => $course['enrol_code']])) ?>"><?= icon('link') ?> Copiar enlace de registro</button>
        <?= Hooks::capture('participants_actions', $course) ?>
    </div>
</div>

<div class="card">
    <p class="muted">Para que alguien se una: pásale el código <button type="button" class="code-chip" data-copy="<?= e($course['enrol_code']) ?>"><?= e($course['enrol_code']) ?> <?= icon('copy') ?></button>
        <?php if (!(int)$course['enrol_open']): ?><strong>(ahora la inscripción está cerrada)</strong><?php endif; ?>
        o inscribe a una cuenta que ya exista:</p>
    <form method="post" action="<?= url('course/enrol') ?>" class="inline-form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $cid ?>">
        <input type="email" name="email" placeholder="email@ejemplo.com" required>
        <button class="btn" type="submit"><?= icon('user-plus') ?> Inscribir</button>
    </form>
</div>

<?php if (!$students): ?>
    <div class="empty-state card"><p>Todavía no hay nadie inscrito.</p></div>
<?php else: ?>
    <div class="card table-card">
        <table class="table">
            <thead>
            <tr><th>Alumno/a</th><th>Avance</th><th>Última conexión</th><th>Inscrito</th><th class="t-right">Acciones</th></tr>
            </thead>
            <tbody>
            <?php foreach ($students as $s): [$done, $total] = $s['progress']; $pct = $total ? (int)round($done * 100 / $total) : 0; ?>
                <tr>
                    <td>
                        <div class="person">
                            <span class="avatar avatar-sm"><?= e(initials($s['name'])) ?></span>
                            <span><a href="<?= url('admin/user', ['id' => $s['id']]) ?>"><?= e($s['name']) ?></a><small><?= e($s['email']) ?></small></span>
                            <?php if ($s['status'] !== 'active'): ?><span class="tag tag-danger">Bloqueado</span><?php endif; ?>
                            <?php if (is_teacher($s)): ?><span class="tag">Profesorado</span><?php endif; ?>
                        </div>
                    </td>
                    <td class="t-progress"><div class="progress progress-sm"><span style="width: <?= $pct ?>%"></span></div><small><?= $done ?>/<?= $total ?></small></td>
                    <td><?= fmt_date((int)$s['last_seen_at'], 'relative') ?></td>
                    <td><?= fmt_date((int)$s['enrolled_at'], 'date') ?></td>
                    <td class="t-right">
                        <div class="tools">
                            <?= Hooks::capture('participant_actions', $s, $course) ?>
                            <?= post_button('course/unenrol', ['id' => $cid, 'user_id' => $s['id']], icon('user-minus'), 'icon-btn icon-danger', 'Dar de baja del curso', '¿Dar de baja a ' . $s['name'] . ' de este curso?') ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
