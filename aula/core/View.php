<?php
defined('AULA') || exit;

/**
 * Plantillas PHP. Nombres:
 *   "course/view"          → aula/templates/course/view.php
 *   "mensajeria:inbox"     → aula/plugins/mensajeria/templates/inbox.php
 */
class View
{
    public static function resolve(string $name): string
    {
        if (str_contains($name, ':')) {
            [$plugin, $tpl] = explode(':', $name, 2);
            $file = AULA_DIR . '/plugins/' . basename($plugin) . '/templates/' . $tpl . '.php';
        } else {
            $file = AULA_DIR . '/templates/' . $name . '.php';
        }
        if (!is_file($file)) {
            throw new RuntimeException("Plantilla no encontrada: $name");
        }
        return $file;
    }

    public static function fetch(string $__name, array $__vars = []): string
    {
        $__file = self::resolve($__name);
        extract($__vars, EXTR_SKIP);
        ob_start();
        include $__file;
        return (string)ob_get_clean();
    }

    /**
     * Pinta una página completa.
     * $opts: crumbs (lista [texto, url]), layout ('layout' | 'auth' | 'bare'), body_class, wide (bool)
     */
    public static function page(string $name, array $vars, string $title, array $opts = []): void
    {
        $content = self::fetch($name, $vars);
        self::layout($content, $title, $opts);
    }

    public static function layout(string $content, string $title, array $opts = []): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('X-Frame-Options: SAMEORIGIN');
        echo self::fetch($opts['layout'] ?? 'layout', [
            'title' => $title,
            'content' => $content,
            'crumbs' => $opts['crumbs'] ?? [],
            'body_class' => $opts['body_class'] ?? '',
            'wide' => $opts['wide'] ?? false,
        ]);
    }
}

/** Atajo para usar en plantillas: incluye una plantilla parcial. */
function partial(string $name, array $vars = []): string
{
    return View::fetch($name, $vars);
}

/** Botón que envía un POST (con CSRF) a una ruta. */
function post_button(string $route, array $fields, string $html, string $class = 'icon-btn', string $title = '', string $confirm = ''): string
{
    $out = '<form method="post" action="' . e(url($route)) . '" class="inline-post"' . ($confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field();
    foreach ($fields as $k => $v) {
        $out .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    $label = $title !== '' ? ' title="' . e($title) . '" aria-label="' . e($title) . '"' : '';
    return $out . '<button type="submit" class="' . e($class) . '"' . $label . '>' . $html . '</button></form>';
}

/** Campo de texto enriquecido (el editor se activa con aula.js). */
function editor_field(string $name, string $value, string $label, string $hint = '', string $id = ''): string
{
    $id = $id !== '' ? $id : 'ed_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    return '<div class="field"><label for="' . e($id) . '">' . e($label) . '</label>'
        . '<textarea id="' . e($id) . '" name="' . e($name) . '" rows="6" data-editor>' . e($value) . '</textarea>'
        . ($hint !== '' ? '<small class="hint">' . e($hint) . '</small>' : '') . '</div>';
}

/** Campo para subir archivos (con subida por partes y barra de progreso). */
function upload_field(string $name, string $label, string $accept = '', bool $multiple = false, bool $required = false, string $hint = ''): string
{
    $id = 'up_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    return '<div class="field"><label for="' . e($id) . '">' . e($label) . '</label>'
        . '<input type="file" id="' . e($id) . '" name="' . e($name) . ($multiple ? '[]' : '') . '" data-chunked="' . e($name) . '"'
        . ' data-chunk-size="' . Files::chunkBytes() . '" data-max-size="' . Files::maxBytes() . '"'
        . ($accept !== '' ? ' accept="' . e($accept) . '"' : '') . ($multiple ? ' multiple' : '') . ($required ? ' required' : '') . '>'
        . ($hint !== '' ? '<small class="hint">' . e($hint) . '</small>' : '')
        . '</div>';
}
