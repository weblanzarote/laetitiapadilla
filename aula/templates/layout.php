<?php
defined('AULA') || exit;
$u = user();
$route = is_string($_GET['r'] ?? null) && $_GET['r'] !== '' ? $_GET['r'] : 'home';
$navItems = [['id' => 'home', 'label' => 'Inicio', 'url' => url(), 'icon' => 'house', 'match' => ['home', 'course', 'resource']]];
$navItems = $u ? apply_filters('nav_items', $navItems) : [];
$manage = [];
if (is_teacher()) {
    $manage[] = ['label' => 'Alumnado y cuentas', 'url' => url('admin/users'), 'icon' => 'users'];
    $manage[] = ['label' => 'Nuevo curso', 'url' => url('course/edit'), 'icon' => 'plus'];
}
if (is_admin()) {
    $manage[] = ['label' => 'Ajustes', 'url' => url('admin/settings'), 'icon' => 'sliders'];
    $manage[] = ['label' => 'Extensiones', 'url' => url('admin/plugins'), 'icon' => 'puzzle-piece'];
    $manage[] = ['label' => 'Sistema y copias', 'url' => url('admin/system'), 'icon' => 'server'];
}
$manage = $u ? apply_filters('manage_menu', $manage) : [];
$siteName = (string)setting('site_name', 'Aula virtual');
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> · <?= e($siteName) ?></title>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="aula-base" content="<?= e(base_path()) ?>">
    <link rel="icon" type="image/png" href="<?= e(base_path()) ?>../favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&family=Dancing+Script:wght@600&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('assets/aula.css') ?>">
    <?= Hooks::capture('head') ?>
</head>
<body class="<?= e($body_class) ?>">
<a class="skip-link" href="#main">Saltar al contenido</a>
<header class="topbar">
    <a class="brand" href="<?= url() ?>">
        <img src="<?= e(base_path()) ?>../logo.jpg" alt="">
        <span class="brand-text">Aula <em>virtual</em></span>
    </a>
    <?php if ($u): ?>
    <button class="nav-toggle" type="button" aria-label="Menú" aria-expanded="false" aria-controls="mainnav"><?= icon('bars') ?></button>
    <nav class="mainnav" id="mainnav">
        <ul class="nav-links">
            <?php foreach ($navItems as $item):
                $active = in_array(explode('/', $route)[0], $item['match'] ?? [$item['id']], true); ?>
                <li>
                    <a href="<?= e($item['url']) ?>" class="<?= $active ? 'active' : '' ?>" <?= !empty($item['badge_id']) ? 'data-badge-host' : '' ?>>
                        <?= icon($item['icon'] ?? 'circle') ?> <span><?= e($item['label']) ?></span>
                        <?php if (!empty($item['badge_id'])): ?>
                            <span class="badge badge-count" data-badge="<?= e($item['badge_id']) ?>" <?= empty($item['badge']) ? 'hidden' : '' ?>><?= (int)($item['badge'] ?? 0) ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php if ($manage): ?>
                <li>
                    <details class="dropdown">
                        <summary class="<?= str_starts_with($route, 'admin/') ? 'active' : '' ?>"><?= icon('gear') ?> <span>Gestión</span></summary>
                        <div class="dropdown-menu">
                            <?php foreach ($manage as $m): ?>
                                <a href="<?= e($m['url']) ?>"><?= icon($m['icon']) ?> <?= e($m['label']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </details>
                </li>
            <?php endif; ?>
        </ul>
        <details class="dropdown user-menu">
            <summary><span class="avatar"><?= e(initials($u['name'])) ?></span> <span class="user-name"><?= e(explode(' ', $u['name'])[0]) ?></span></summary>
            <div class="dropdown-menu dropdown-right">
                <div class="dropdown-head"><strong><?= e($u['name']) ?></strong><small><?= e($u['email']) ?></small></div>
                <a href="<?= url('profile') ?>"><?= icon('user') ?> Mi perfil</a>
                <a href="<?= e(base_path()) ?>../index.html"><?= icon('globe') ?> Ir a la web</a>
                <form method="post" action="<?= url('logout') ?>"><?= csrf_field() ?>
                    <button type="submit"><?= icon('right-from-bracket') ?> Salir</button>
                </form>
            </div>
        </details>
    </nav>
    <?php endif; ?>
</header>

<main id="main" class="container<?= $wide ? ' container-wide' : '' ?>">
    <?php if ($crumbs): ?>
        <nav class="crumbs" aria-label="Ruta">
            <?php foreach ($crumbs as [$label, $href]): ?>
                <a href="<?= e($href) ?>"><?= e($label) ?></a> <span aria-hidden="true">›</span>
            <?php endforeach; ?>
            <span><?= e($title) ?></span>
        </nav>
    <?php endif; ?>
    <?php foreach (flashes() as [$type, $msg]): ?>
        <div class="alert alert-<?= e($type) ?>" role="status"><?= e($msg) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>

<footer class="foot">
    <a href="<?= e(base_path()) ?>../index.html">laetitiapadilla.com</a>
    <span aria-hidden="true">·</span>
    <a href="<?= url('privacy') ?>">Privacidad</a>
</footer>
<script src="<?= asset('assets/aula.js') ?>"></script>
<?= Hooks::capture('footer') ?>
</body>
</html>
