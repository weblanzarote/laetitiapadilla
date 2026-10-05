<?php defined('AULA') || exit;
$roles = ['student' => 'Alumno/a', 'teacher' => 'Profesorado', 'admin' => 'Administración'];
?>
<div class="page-head">
    <h1 class="page-title">Alumnado y cuentas</h1>
    <a class="btn btn-primary" href="<?= url('admin/user') ?>"><?= icon('user-plus') ?> Nueva cuenta</a>
</div>

<form method="get" action="<?= url() ?>" class="card filters">
    <input type="hidden" name="r" value="admin/users">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre o email" aria-label="Buscar">
    <select name="course" aria-label="Curso">
        <option value="0">Todos los cursos</option>
        <?php foreach ($courses as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $courseId ? 'selected' : '' ?>><?= e($c['title']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="role" aria-label="Rol">
        <option value="">Todos los roles</option>
        <?php foreach ($roles as $k => $label): ?>
            <option value="<?= $k ?>" <?= $role === $k ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= icon('magnifying-glass') ?> Filtrar</button>
</form>

<?php if (!$users): ?>
    <div class="empty-state card"><p>No hay cuentas que coincidan.</p></div>
<?php else: ?>
    <div class="card table-card">
        <table class="table">
            <thead><tr><th>Nombre</th><th>Cursos</th><th>Rol</th><th>Última conexión</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div class="person">
                            <span class="avatar avatar-sm"><?= e(initials($u['name'])) ?></span>
                            <span><a href="<?= url('admin/user', ['id' => $u['id']]) ?>"><?= e($u['name']) ?></a><small><?= e($u['email']) ?></small></span>
                            <?php if ($u['status'] !== 'active'): ?><span class="tag tag-danger">Bloqueado</span><?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <?php foreach ($enrol[(int)$u['id']] ?? [] as $c): ?>
                            <a class="tag" href="<?= url('course', ['id' => $c['id']]) ?>"><?= e($c['title']) ?></a>
                        <?php endforeach; ?>
                    </td>
                    <td><?= e($roles[$u['role']] ?? $u['role']) ?></td>
                    <td><?= fmt_date((int)$u['last_seen_at'], 'relative') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
