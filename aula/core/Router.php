<?php
defined('AULA') || exit;

/**
 * Rutas del aula. Niveles de acceso:
 *   public  cualquiera
 *   guest   solo sin sesión (login, registro...)
 *   user    cualquier usuario identificado
 *   teacher profesorado y administración
 *   admin   solo administración
 */
class Router
{
    private static array $routes = [];
    private static array $serve = [];

    public static function add(string $route, callable $handler, string $access = 'user'): void
    {
        self::$routes[$route] = ['handler' => $handler, 'access' => $access];
    }

    /** Manejador para serve.php/<name>/... (recibe el resto de la ruta). */
    public static function serve(string $name, callable $handler, string $access = 'user'): void
    {
        self::$serve[$name] = ['handler' => $handler, 'access' => $access];
    }

    public static function exists(string $route): bool
    {
        return isset(self::$routes[$route]);
    }

    public static function dispatch(string $route): void
    {
        $route = trim($route, '/');
        if ($route === '') {
            $route = 'home';
        }
        $def = self::$routes[$route] ?? null;
        if (!$def) {
            throw new HttpError('Página no encontrada.', 404);
        }
        self::checkAccess($def['access']);
        if (is_post()) {
            csrf_verify();
        }
        ($def['handler'])();
    }

    public static function dispatchServe(string $path): void
    {
        $path = ltrim($path, '/');
        $slash = strpos($path, '/');
        $name = $slash === false ? $path : substr($path, 0, $slash);
        $rest = $slash === false ? '' : substr($path, $slash + 1);
        $def = self::$serve[$name] ?? null;
        if (!$def) {
            throw new HttpError('No encontrado.', 404);
        }
        if ($def['access'] !== 'public' && !user()) {
            throw new HttpError('Inicia sesión para ver este contenido.', 401);
        }
        ($def['handler'])(rawurldecode($rest));
    }

    public static function checkAccess(string $access): void
    {
        if ($access === 'public') {
            return;
        }
        if ($access === 'guest') {
            if (user()) {
                redirect();
            }
            return;
        }
        if (!user()) {
            if (wants_json()) {
                json_out(['ok' => false, 'error' => 'Sesión caducada. Vuelve a entrar.'], 401);
            }
            if (!is_post()) {
                $_SESSION['_intended'] = current_url();
            }
            redirect('login');
        }
        if ($access === 'teacher' && !is_teacher()) {
            throw new HttpError('No tienes permiso para ver esta página.', 403);
        }
        if ($access === 'admin' && !is_admin()) {
            throw new HttpError('Solo la administración puede ver esta página.', 403);
        }
    }
}
