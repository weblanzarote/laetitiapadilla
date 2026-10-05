<?php
/**
 * Configuración por defecto del aula.
 *
 * No edites este archivo en el servidor: crea aula/config.local.php
 * (ver config.local.example.php) y sobrescribe solo lo que necesites.
 */
defined('AULA') || exit;

$config = [
    // Carpeta de datos (base de datos, archivos subidos, SCORM, sesiones).
    // Por defecto queda FUERA de public_html: /home/<dominio>/aula_data
    'data_dir' => getenv('AULA_DATA_DIR') ?: dirname(__DIR__, 2) . '/aula_data',

    // URL pública del aula (se usa en los emails).
    'site_url' => getenv('AULA_SITE_URL') ?: 'https://laetitiapadilla.com/aula/',

    // Base de datos: 'sqlite' (por defecto, sin configurar nada) o 'mysql'.
    'db' => [
        'driver' => 'sqlite',
        'host'   => 'localhost',
        'port'   => 3306,
        'name'   => '',
        'user'   => '',
        'pass'   => '',
    ],

    'timezone' => 'Atlantic/Canary',

    // Correo saliente (usa mail() del servidor, como el formulario de contacto).
    'mail_from'    => 'no-reply@laetitiapadilla.com',
    'mail_enabled' => getenv('AULA_MAIL') !== '0',

    'debug' => getenv('AULA_DEBUG') === '1',
];

if (is_file(__DIR__ . '/config.local.php')) {
    $local = require __DIR__ . '/config.local.php';
    if (is_array($local)) {
        $config = array_replace_recursive($config, $local);
    }
}

return $config;
