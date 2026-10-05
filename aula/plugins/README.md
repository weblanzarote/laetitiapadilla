# Extensiones del aula

Cada extensión es una carpeta `plugins/<id>/` (id en minúsculas, sin espacios) con:

- `plugin.json`: título, descripción, versión, icono (Font Awesome), `default` (activa al
  instalar) y `required` (no se puede desactivar).
- `plugin.php`: se carga en cada petición **solo si la extensión está activa**
  (Gestión › Extensiones). Empieza siempre con `defined('AULA') || exit;`.
- `templates/` (opcional): plantillas, que se usan como `View::fetch('<id>:nombre', [...])`.
- Archivos estáticos (js, css, imágenes) con `Plugins::asset('<id>', 'archivo.js')`.

Al desactivar una extensión sus tablas y datos se conservan.

## Qué puede hacer una extensión

```php
<?php
defined('AULA') || exit;

// 1. Tablas propias (migraciones numeradas; nunca cambies una ya publicada)
Db::migrate('plugin_tareas', [
    1 => ['CREATE TABLE tareas_entregas (id {PK}, resource_id INT NOT NULL, user_id INT NOT NULL,
           nota REAL NULL, created_at INT NOT NULL DEFAULT 0){TABLE_OPTS}'],
]);

// 2. Un tipo de contenido nuevo para los cursos
class TareaResource extends ResourceType {
    public function id(): string { return 'tarea'; }
    public function label(): string { return 'Tarea para entregar'; }
    public function icon(): string { return 'inbox'; }
    public function form(array $res): string { /* campos extra */ return ''; }
    public function save(array $res, bool $isNew): array { return $res['data']; }
    public function render(array $res, array $course): string { return '<p>…</p>'; }
}
ResourceTypes::register(new TareaResource());

// 3. Páginas nuevas: index.php?r=tareas/lista
Router::add('tareas/lista', function () {
    View::page('tareas:lista', ['items' => []], 'Tareas');
}, 'teacher'); // public | guest | user | teacher | admin

// 4. Engancharse a lo que ya existe
add_filter('nav_items', function (array $items) {
    $items[] = ['id' => 'tareas', 'label' => 'Tareas', 'url' => url('tareas/lista'), 'icon' => 'inbox'];
    return $items;
});
add_action('user_enrolled', function (int $userId, int $courseId) { /* … */ });
```

En la base de datos se usan `{PK}`, `{TEXT}` y `{TABLE_OPTS}` para que el mismo SQL funcione
en SQLite y en MySQL. Tiempos: enteros Unix (`time()`).

## Ganchos disponibles

### Acciones (`add_action`)

| Nombre | Argumentos | Cuándo |
|---|---|---|
| `init` | — | Tras cargar todo, antes de atender la petición |
| `plugins_loaded` | — | Tras cargar las extensiones |
| `user_login` | `$user` | Al iniciar sesión |
| `user_created` | `$userId` | Cuenta nueva (registro o creada por la profesora) |
| `user_registered` | `$user, $course` | Alumno registrado con código |
| `user_enrolled` / `user_unenrolled` | `$userId, $courseId` | Alta / baja en un curso |
| `user_deleting` / `user_deleted` | `$user` / `$userId` | Al eliminar una cuenta |
| `course_saved` | `$courseId, $isNew` | Curso creado o editado |
| `course_duplicated` | `$oldId, $newId` | Curso duplicado |
| `course_deleted` | `$courseId` | Curso borrado |
| `resource_saved` | `$resource, $isNew` | Contenido creado o editado |
| `resource_deleted` | `$resource` | Contenido borrado |
| `message_posted` | `$conv, $msgId, $userId` | (Mensajería) mensaje enviado |
| `scorm_tracked` | `$resource, $userId, $sco, $row` | (SCORM) progreso guardado |

Acciones para **pintar HTML** en un sitio concreto (lo que se imprima aparece allí):
`head`, `footer`, `dashboard_top`, `dashboard_side`, `course_top($course)`,
`resource_bottom($resource, $course)`, `participants_actions($course)`,
`participant_actions($student, $course)`, `user_actions($user)`.

### Filtros (`add_filter`)

| Nombre | Valor / argumentos | Para |
|---|---|---|
| `nav_items` | lista de enlaces | Menú principal (`badge_id` + `badge` para contadores) |
| `manage_menu` | lista de enlaces | Menú «Gestión» |
| `settings_sections` | lista de secciones | Ajustes propios (`bool`, `text`, `number`, `textarea`, `html`, `select`) |
| `profile_prefs` | lista de casillas | Preferencias en «Mi perfil» (se leen con `user_pref()`) |
| `resource_types` | tipos registrados | Ocultar o reordenar tipos de contenido |
| `file_access` | `$allowed, $file, $user` | Dar acceso a archivos de un contexto propio |
| `mail_send` | `null, $to, $subject, $body, $replyTo` | Enviar el correo de otra forma (p. ej. SMTP); devolver `true`/`false` |
| `backup_dirs` | carpetas de datos | Incluir carpetas propias en la copia de seguridad |

## Utilidades del núcleo

- Base de datos: `Db::all/one/val/col/insert/update/delete/q`.
- Usuario actual: `user()`, `uid()`, `is_teacher()`, `is_admin()`.
- Cursos: `Courses::find`, `Courses::forUser`, `Courses::students`, `Courses::canView`…
- Archivos: `Files::incoming('campo')` (admite subidas por partes), `Files::store`,
  `Files::url`, `Files::send`. En formularios: `upload_field()`.
- Formularios: `csrf_field()` (obligatorio en todo POST), `post_button()`, `editor_field()`.
- HTML del editor: `Html::clean()` siempre antes de guardarlo.
- Ajustes: `setting('clave', $porDefecto)`, `Settings::set()`.
- Correo: `Mailer::send()`, `Mailer::toTeachers()`.
