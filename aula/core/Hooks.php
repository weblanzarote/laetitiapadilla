<?php
defined('AULA') || exit;

/**
 * Sistema de ganchos (acciones y filtros) al estilo WordPress.
 * Las extensiones (plugins) se enganchan aquí para añadir funcionalidad
 * sin tocar el núcleo. Ver plugins/README.md para la lista de ganchos.
 */
class Hooks
{
    private static array $hooks = [];

    public static function add(string $name, callable $callback, int $priority = 10): void
    {
        self::$hooks[$name][$priority][] = $callback;
    }

    public static function has(string $name): bool
    {
        return !empty(self::$hooks[$name]);
    }

    private static function callbacks(string $name): array
    {
        if (empty(self::$hooks[$name])) {
            return [];
        }
        ksort(self::$hooks[$name]);
        return array_merge(...array_values(self::$hooks[$name]));
    }

    public static function action(string $name, ...$args): void
    {
        foreach (self::callbacks($name) as $cb) {
            $cb(...$args);
        }
    }

    public static function filter(string $name, $value, ...$args)
    {
        foreach (self::callbacks($name) as $cb) {
            $value = $cb($value, ...$args);
        }
        return $value;
    }

    /** Ejecuta una acción y devuelve lo que imprimen sus callbacks. */
    public static function capture(string $name, ...$args): string
    {
        ob_start();
        self::action($name, ...$args);
        return (string)ob_get_clean();
    }
}

function add_action(string $name, callable $cb, int $priority = 10): void
{
    Hooks::add($name, $cb, $priority);
}

function do_action(string $name, ...$args): void
{
    Hooks::action($name, ...$args);
}

function add_filter(string $name, callable $cb, int $priority = 10): void
{
    Hooks::add($name, $cb, $priority);
}

function apply_filters(string $name, $value, ...$args)
{
    return Hooks::filter($name, $value, ...$args);
}
