<?php defined('AULA') || exit;
$roles = ['student' => 'Alumno/a', 'teacher' => 'Profesorado', 'admin' => 'Administración'];
$self = $u && (int)$u['id'] === uid();
$action = url('admin/user', $u ? ['id' => $u['id']] : []);
?>
<div class="page-head">
    <div>
        <?php if ($u): ?><p class="kicker">Alta: <?= fmt_date((int)$u['created_at'], 'date') ?> · Última conexión: <?= fmt_date((int)$u['last_seen_at'], 'relative') ?></p><?php endif; ?>
        <h1 class="page-title"><?= $u ? e($u['name']) : 'Nueva cuenta' ?></h1>
    </div>
    <?php if ($u): ?><div class="btn-row"><?= Hooks::capture('user_actions', $u) ?></div><?php endif; ?>
</div>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<?php if ($resetLink): ?>
    <div class="alert alert-ok">
        <p><strong>Enlace para que elija contraseña</strong> (válido 3 días). Envíaselo por el medio que prefieras:</p>
        <div class="inline-form"><input type="text" readonly value="<?= e($resetLink) ?>" onclick="this.select()"><button type="button" class="btn" data-copy="<?= e($resetLink) ?>"><?= icon('copy') ?> Copiar</button></div>
    </div>
<?php endif; ?>

<div class="grid-main">
    <form method="post" action="<?= e($action) ?>" class="form card">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <div class="field"><label for="name">Nombre y apellidos</label><input type="text" id="name" name="name" value="<?= e($v['name']) ?>" required></div>
        <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" value="<?= e($v['email']) ?>" required></div>
        <div class="field-row">
            <?php if (is_admin() && !$self): ?>
                <div class="field"><label for="role">Rol</label>
                    <select id="role" name="role">
                        <?php foreach ($roles as $k => $label): ?><option value="<?= $k ?>" <?= $v['role'] === $k ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <?php if (!$self): ?>
                <div class="field"><label for="status">Estado</label>
                    <select id="status" name="status">
                        <option value="active" <?= $v['status'] === 'active' ? 'selected' : '' ?>>Activa</option>
                        <option value="blocked" <?= $v['status'] === 'blocked' ? 'selected' : '' ?>>Bloqueada (no puede entrar)</option>
                    </select>
                </div>
            <?php endif; ?>
        </div>
        <div class="field">
            <label for="password"><?= $u ? 'Poner contraseña nueva (opcional)' : 'Contraseña' ?></label>
            <input type="text" id="password" name="password" minlength="8" autocomplete="off" <?= $u ? '' : 'required' ?>>
            <small class="hint"><?= $u ? 'Déjalo vacío para no cambiarla. Mejor usa «Enlace para elegir contraseña».' : 'Mínimo 8 caracteres. Comunícasela a la persona.' ?></small>
        </div>
        <?php if (!$u): ?>
            <div class="field"><label for="course_id">Inscribir en</label>
                <select id="course_id" name="course_id"><option value="0">— Ningún curso —</option>
                    <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['title']) ?></option><?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary"><?= icon('check') ?> Guardar</button>
    </form>

    <?php if ($u): ?>
        <aside class="side">
            <div class="card">
                <h2 class="card-title"><?= icon('book-open') ?> Cursos</h2>
                <?php if (!$enrolled): ?><p class="muted">No está en ningún curso.</p><?php endif; ?>
                <ul class="mini-list">
                    <?php foreach ($enrolled as $c): ?>
                        <li>
                            <a href="<?= url('course', ['id' => $c['id']]) ?>"><?= e($c['title']) ?></a>
                            <form method="post" action="<?= e($action) ?>" class="inline-post" data-confirm="¿Dar de baja de este curso?">
                                <?= csrf_field() ?><input type="hidden" name="action" value="unenrol"><input type="hidden" name="course_id" value="<?= (int)$c['id'] ?>">
                                <button class="icon-btn icon-danger" title="Dar de baja" aria-label="Dar de baja"><?= icon('xmark') ?></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <form method="post" action="<?= e($action) ?>" class="inline-form">
                    <?= csrf_field() ?><input type="hidden" name="action" value="enrol">
                    <select name="course_id" aria-label="Curso">
                        <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['title']) ?></option><?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm" type="submit">Inscribir</button>
                </form>
            </div>
            <div class="card">
                <h2 class="card-title"><?= icon('key') ?> Acceso</h2>
                <p class="muted">Si no recibe el email de recuperación, genera un enlace y pásaselo tú.</p>
                <form method="post" action="<?= e($action) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="reset">
                    <button class="btn btn-sm" type="submit"><?= icon('link') ?> Enlace para elegir contraseña</button></form>
            </div>
            <?php if (is_admin() && !$self): ?>
                <div class="card danger-zone">
                    <h2 class="card-title"><?= icon('trash') ?> Eliminar cuenta</h2>
                    <p class="muted">Borra la cuenta y su progreso. Sus mensajes se conservan como «cuenta eliminada».</p>
                    <form method="post" action="<?= e($action) ?>" data-confirm="¿Eliminar definitivamente la cuenta de <?= e($u['name']) ?>?">
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete">
                        <button class="btn btn-danger btn-sm" type="submit">Eliminar cuenta</button>
                    </form>
                </div>
            <?php endif; ?>
        </aside>
    <?php endif; ?>
</div>
