<?php
defined('AULA') || exit;

define('AULA_DIR', dirname(__DIR__));
define('AULA_VERSION', '1.0.0');

foreach (['helpers', 'Db', 'Hooks', 'Router', 'Auth', 'View', 'Files', 'Mailer', 'Html', 'Plugins', 'ResourceType', 'Courses', 'App'] as $f) {
    require AULA_DIR . "/core/$f.php";
}
