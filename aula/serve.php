<?php
/**
 * Aula virtual — entrega de archivos protegidos con rutas "bonitas":
 *   serve.php/<manejador>/<resto/de/la/ruta>
 * Se usa para archivos subidos (f) y contenido SCORM (scorm), de modo que
 * las rutas relativas dentro de un paquete funcionen igual que en disco.
 */
define('AULA', true);
require __DIR__ . '/core/bootstrap.php';
App::serve();
