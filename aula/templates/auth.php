<?php
defined('AULA') || exit;
$siteName = (string)setting('site_name', 'Aula virtual');
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> · <?= e($siteName) ?></title>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="icon" type="image/png" href="<?= e(base_path()) ?>../favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&family=Dancing+Script:wght@600&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('assets/aula.css') ?>">
</head>
<body class="auth-body">
<main id="main" class="auth-wrap">
    <a class="auth-brand" href="<?= e(base_path()) ?>../index.html">
        <img src="<?= e(base_path()) ?>../logo.jpg" alt="Laetitia Padilla">
    </a>
    <div class="auth-card">
        <p class="auth-kicker"><?= e($siteName) ?></p>
        <?php foreach (flashes() as [$type, $msg]): ?>
            <div class="alert alert-<?= e($type) ?>" role="status"><?= e($msg) ?></div>
        <?php endforeach; ?>
        <?= $content ?>
    </div>
    <p class="auth-foot">
        <a href="<?= e(base_path()) ?>../index.html"><?= icon('arrow-left') ?> Volver a la web</a>
        <span aria-hidden="true">·</span>
        <a href="<?= url('privacy') ?>">Privacidad</a>
    </p>
</main>
<script src="<?= asset('assets/aula.js') ?>"></script>
</body>
</html>
