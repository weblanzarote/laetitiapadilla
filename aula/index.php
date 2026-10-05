<?php
/**
 * Aula virtual — punto de entrada.
 * Todas las páginas pasan por aquí: index.php?r=<ruta>
 */
define('AULA', true);
require __DIR__ . '/core/bootstrap.php';
App::run();
