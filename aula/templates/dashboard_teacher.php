<?php defined('AULA') || exit; ?>
<div class="page-head">
    <div>
        <p class="kicker">Panel de la profesora</p>
        <h1 class="page-title">Hola, <?= e(explode(' ', user()['name'])[0]) ?></h1>
    </div>
    <a class="btn btn-primary" href="<?= url('course/edit') ?>"><?= icon('plus') ?> Nuevo curso</a>
</div>

<?= Hooks::capture('dashboard_top') ?>

<div class="grid-main">
    <section>
        <h2 class="section-title">Cursos</h2>
        <?php if (!$courses): ?>
            <div class="empty-state card">
                <div class="empty-icon"><?= icon('book-open') ?></div>
                <p>Todavía no hay cursos. Crea el primero (por ejemplo, «Francés B1») y pásale el código a tus alumnos.</p>
                <a class="btn btn-primary" href="<?= url('course/edit') ?>">Crear curso</a>
            </div>
        <?php endif; ?>
        <div class="course-list">
            <?php foreach ($courses as $c): $s = $stats[(int)$c['id']]; ?>
                <article class="card course-row">
                    <a class="course-row-cover" href="<?= url('course', ['id' => $c['id']]) ?>" tabindex="-1" aria-hidden="true"><?= Courses::cover($c) ?></a>
                    <div class="course-row-main">
                        <h3><a href="<?= url('course', ['id' => $c['id']]) ?>"><?= e($c['title']) ?></a></h3>
                        <div class="tags">
                            <?php if (!(int)$c['visible']): ?><span class="tag tag-muted"><?= icon('eye-slash') ?> Oculto</span><?php endif; ?>
                            <?php if ((int)$c['enrol_open']): ?>
                                <span class="tag tag-ok"><?= icon('door-open') ?> Inscripción abierta</span>
                            <?php else: ?>
                                <span class="tag tag-muted"><?= icon('door-closed') ?> Inscripción cerrada</span>
                            <?php endif; ?>
                            <span class="tag"><?= icon('user-graduate') ?> <?= plural($s['students'], 'alumno', 'alumnos') ?></span>
                            <span class="tag"><?= icon('layer-group') ?> <?= plural($s['resources'], 'contenido', 'contenidos') ?></span>
                        </div>
                    </div>
                    <div class="course-row-code">
                        <small>Código</small>
                        <button type="button" class="code-chip" data-copy="<?= e($c['enrol_code']) ?>" title="Copiar código"><?= e($c['enrol_code']) ?> <?= icon('copy') ?></button>
                        <button type="button" class="link-btn" data-copy="<?= e(abs_url('register', ['code' => $c['enrol_code']])) ?>"><?= icon('link') ?> Copiar enlace de registro</button>
                    </div>
                    <div class="course-row-actions">
                        <a class="btn btn-sm" href="<?= url('course', ['id' => $c['id']]) ?>">Abrir</a>
                        <a class="btn btn-sm btn-ghost" href="<?= url('course/participants', ['id' => $c['id']]) ?>"><?= icon('users') ?> Alumnos</a>
                        <a class="btn btn-sm btn-ghost" href="<?= url('course/edit', ['id' => $c['id']]) ?>"><?= icon('pen') ?> Ajustes</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <aside class="side">
        <?= Hooks::capture('dashboard_side') ?>
        <div class="card">
            <h2 class="card-title"><?= icon('user-plus') ?> Últimos registros</h2>
            <?php if (!$recent): ?>
                <p class="muted">Aún no se ha registrado nadie.</p>
            <?php else: ?>
                <ul class="mini-list">
                    <?php foreach ($recent as $r): ?>
                        <li>
                            <span class="avatar avatar-sm"><?= e(initials($r['name'])) ?></span>
                            <a href="<?= url('admin/user', ['id' => $r['id']]) ?>"><?= e($r['name']) ?></a>
                            <small class="muted"><?= fmt_date((int)$r['created_at'], 'relative') ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <a class="link-more" href="<?= url('admin/users') ?>">Ver todo el alumnado</a>
            <?php endif; ?>
        </div>
    </aside>
</div>
