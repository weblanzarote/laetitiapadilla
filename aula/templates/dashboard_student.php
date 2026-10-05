<?php defined('AULA') || exit; $welcome = (string)setting('welcome_text', ''); ?>
<div class="page-head">
    <div>
        <p class="kicker">Bonjour !</p>
        <h1 class="page-title">Hola, <?= e(explode(' ', user()['name'])[0]) ?></h1>
    </div>
</div>

<?php if ($welcome !== ''): ?>
    <div class="card welcome prose"><?= $welcome ?></div>
<?php endif; ?>

<?= Hooks::capture('dashboard_top') ?>

<div class="grid-main">
    <section>
        <h2 class="section-title">Mis cursos</h2>
        <?php if (!$courses): ?>
            <div class="empty-state card">
                <div class="empty-icon"><?= icon('book-open') ?></div>
                <p>Todavía no estás en ningún curso. Escribe abajo el código que te ha dado tu profesora.</p>
            </div>
        <?php endif; ?>
        <div class="course-cards">
            <?php foreach ($courses as $c): [$done, $total] = $progress[(int)$c['id']]; $pct = $total ? (int)round($done * 100 / $total) : 0; ?>
                <a class="card course-card" href="<?= url('course', ['id' => $c['id']]) ?>">
                    <span class="course-card-icon"><?= icon('book-open') ?></span>
                    <h3><?= e($c['title']) ?></h3>
                    <?php if ($c['summary']): ?><p class="muted"><?= e(excerpt($c['summary'], 110)) ?></p><?php endif; ?>
                    <div class="progress" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><span style="width: <?= $pct ?>%"></span></div>
                    <small class="muted"><?= $done ?> de <?= $total ?> contenidos vistos</small>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="post" action="<?= url('join') ?>" class="card join-form">
            <?= csrf_field() ?>
            <label for="join-code"><strong>Unirme a un curso</strong> <span class="muted">con el código que te dé tu profesora</span></label>
            <div class="inline-form">
                <input type="text" id="join-code" name="code" class="input-code" placeholder="CÓDIGO" required autocomplete="off">
                <button class="btn btn-primary" type="submit">Unirme</button>
            </div>
        </form>
    </section>
    <aside class="side">
        <?= Hooks::capture('dashboard_side') ?>
    </aside>
</div>
