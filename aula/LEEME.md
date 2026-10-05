# Aula virtual

Plataforma de cursos de laetitiapadilla.com: `https://laetitiapadilla.com/aula/`

- PHP 8 sin dependencias ni Composer. Base de datos SQLite (o MySQL, opcional).
- Todo lo que suben la profesora y el alumnado se guarda **fuera de la web**, en
  `/home/laetitiapadilla.com/aula_data/`, y se entrega solo a quien tiene permiso.
- Es modular: cada función grande es una extensión en `plugins/` (ver `plugins/README.md`).

## Primera puesta en marcha

1. Desplegar como siempre con `manage.ps1` (opción **1a**). La carpeta `aula/` sube con el resto.
2. Abrir **enseguida** `https://laetitiapadilla.com/aula/` y crear la cuenta de administración
   (la de Leti). El instalador solo funciona mientras no exista ninguna cuenta.
3. Entrar en **Gestión › Sistema y copias** y comprobar que todo sale en verde.

Si en el paso 2 aparece «Falta la carpeta de datos del aula», crear la carpeta en el servidor
(mismo usuario que la web) y recargar:

```bash
mkdir -p /home/laetitiapadilla.com/aula_data && chmod 750 /home/laetitiapadilla.com/aula_data
```

Si aparece «El servidor no tiene la extensión pdo_sqlite»: activar SQLite para PHP en
CyberPanel o crear una base de datos MySQL y configurarla en `aula/config.local.php`
(copiar `config.local.example.php`; ese archivo no se sube a Git, se crea a mano en el servidor).

## Uso diario (resumen para Leti)

- **Crear un curso**: Gestión › Nuevo curso. Cada curso tiene un **código de inscripción**
  (p. ej. `FRB1-2026`). Se pasa el código o el «enlace de registro» al alumnado.
- **Apartados y contenidos**: dentro del curso, «Nuevo apartado» (Gramática, Música, Unité 1…)
  y, en cada apartado, «Añadir contenido»: PDF, audio, vídeo, página de texto, enlace, archivo
  o paquete SCORM. Todo se puede ocultar, ordenar con las flechas y editar.
- **Duplicar un apartado** con todo su contenido: botón de copiar en la cabecera del apartado.
- **Mover un contenido a otro apartado**: botón de carpeta junto al contenido.
- **Portada del curso**: Ajustes del curso › Portada (o «Cambiar portada» en la cabecera).
  Se sube una foto horizontal o se elige uno de los diseños de serie.
- **Imágenes y fichas** (archivo JPG/PNG): se ven a todo el ancho; al pulsarlas se abren a
  pantalla completa y, con otro toque, se amplían.
- **Reutilizar un curso** el año siguiente: Ajustes del curso › Duplicar curso.
- **Mensajes**: privados con cada alumno, a varios a la vez o **avisos a todo el curso**.
  Llegan también por email (configurable en Gestión › Ajustes).
- **Seguimiento**: en «Alumnos» del curso se ve el avance de cada uno; en cada SCORM, el estado,
  la nota y el tiempo.
- **Copias de seguridad**: Gestión › Sistema y copias › Descargar copia (de vez en cuando).

## Probar en local

Con PHP 8 instalado (en Windows, SQLite se activa con `-d extension=pdo_sqlite`):

```bash
php -d extension=pdo_sqlite -S 127.0.0.1:8765
```

Variables útiles: `AULA_DATA_DIR` (carpeta de datos de pruebas), `AULA_MAIL=0` (los emails se
guardan en `mail.log` en vez de enviarse), `AULA_DEBUG=1`, `AULA_SITE_URL`.

## Estructura

```
aula/
  index.php          entrada de todas las páginas (?r=ruta)
  serve.php          entrega de archivos protegidos y contenido SCORM
  config.php         configuración por defecto (no tocar: usar config.local.php)
  core/              núcleo: base de datos, rutas, usuarios, cursos, archivos, ganchos
  templates/         plantillas de las páginas
  assets/            CSS y JS del aula
  plugins/           extensiones: recursos (contenidos básicos), mensajeria, scorm
```
