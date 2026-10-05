<?php
defined('AULA') || exit;

/**
 * Extensiones. Cada una vive en aula/plugins/<id>/ con:
 *   plugin.json  título, descripción, versión, "default" (activa al instalar), "required"
 *   plugin.php   código que se carga si está activa (registra rutas, tipos, ganchos...)
 */
class Plugins
{
    private static ?array $available = null;
    private static array $loaded = [];

    public static function available(): array
    {
        if (self::$available === null) {
            self::$available = [];
            foreach (glob(AULA_DIR . '/plugins/*/plugin.json') ?: [] as $file) {
                $id = basename(dirname($file));
                if (!preg_match('/^[a-z0-9_-]+$/', $id)) {
                    continue;
                }
                $m = json_decode((string)file_get_contents($file), true);
                if (!is_array($m)) {
                    continue;
                }
                self::$available[$id] = $m + [
                    'id' => $id,
                    'title' => $id,
                    'description' => '',
                    'version' => '1.0.0',
                    'author' => '',
                    'default' => false,
                    'required' => false,
                ];
                self::$available[$id]['id'] = $id;
            }
            uasort(self::$available, fn($a, $b) => [empty($a['required']), $a['title']] <=> [empty($b['required']), $b['title']]);
        }
        return self::$available;
    }

    public static function enabledIds(): array
    {
        $raw = setting('plugins_enabled');
        if ($raw === null) {
            $ids = array_keys(array_filter(self::available(), fn($p) => !empty($p['default'])));
            Settings::set('plugins_enabled', json_encode(array_values($ids)));
        } else {
            $ids = json_decode((string)$raw, true) ?: [];
        }
        foreach (self::available() as $id => $p) {
            if (!empty($p['required']) && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }
        return array_values(array_intersect(array_keys(self::available()), $ids));
    }

    public static function loadEnabled(): void
    {
        foreach (self::enabledIds() as $id) {
            $file = AULA_DIR . "/plugins/$id/plugin.php";
            if (is_file($file)) {
                require_once $file;
                self::$loaded[$id] = true;
            }
        }
        do_action('plugins_loaded');
    }

    public static function isActive(string $id): bool
    {
        return isset(self::$loaded[$id]);
    }

    public static function setEnabled(string $id, bool $on): void
    {
        $p = self::available()[$id] ?? null;
        if (!$p) {
            return;
        }
        if (!$on && !empty($p['required'])) {
            throw new UserError('Esta extensión es imprescindible y no se puede desactivar.');
        }
        $ids = self::enabledIds();
        $ids = $on ? array_values(array_unique(array_merge($ids, [$id]))) : array_values(array_diff($ids, [$id]));
        Settings::set('plugins_enabled', json_encode($ids));
    }

    /** Ruta web a un recurso estático de una extensión (js, css, imágenes). */
    public static function asset(string $id, string $path): string
    {
        return asset('plugins/' . $id . '/' . $path);
    }
}
