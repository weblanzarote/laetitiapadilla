<?php
defined('AULA') || exit;

class App
{
    public static array $config = [];
    public static string $dataDir = '';

    public static function boot(): void
    {
        self::$config = require AULA_DIR . '/config.php';
        $c = self::$config;
        error_reporting(E_ALL);
        ini_set('display_errors', $c['debug'] ? '1' : '0');
        date_default_timezone_set($c['timezone']);
        mb_internal_encoding('UTF-8');
        set_exception_handler([self::class, 'handleException']);

        self::$dataDir = rtrim(str_replace('\\', '/', (string)$c['data_dir']), '/');
        self::prepareDataDir();

        try {
            Db::connect($c['db'], self::$dataDir);
        } catch (Throwable $e) {
            self::fatal('No se puede abrir la base de datos', '<p>' . e($e->getMessage()) . '</p>');
        }
        Db::migrate('core', require AULA_DIR . '/core/schema.php');

        Auth::startSession();
        foreach (glob(AULA_DIR . '/core/routes/*.php') ?: [] as $file) {
            require $file;
        }
        Plugins::loadEnabled();
        do_action('init');

        if (random_int(1, 100) === 1) {
            Db::delete('login_attempts', 'created_at < ?', [time() - 86400]);
            Db::delete('password_resets', 'expires_at < ?', [time() - 86400]);
        }
    }

    public static function run(): void
    {
        self::boot();
        $route = is_string($_GET['r'] ?? null) ? $_GET['r'] : '';
        if (!Db::val('SELECT COUNT(*) FROM users') && $route !== 'setup') {
            redirect('setup');
        }
        Router::dispatch($route);
    }

    public static function serve(): void
    {
        self::boot();
        Router::dispatchServe(self::pathInfo());
    }

    /** Parte de la URL tras serve.php, sin decodificar. */
    private static function pathInfo(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $uri = explode('?', $uri, 2)[0];
        $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        if ($script !== '' && str_starts_with($uri, $script)) {
            return substr($uri, strlen($script));
        }
        return implode('/', array_map('rawurlencode', explode('/', (string)($_SERVER['PATH_INFO'] ?? ''))));
    }

    private static function prepareDataDir(): void
    {
        $dir = self::$dataDir;
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            self::fatal(
                'Falta la carpeta de datos del aula',
                '<p>El aula necesita una carpeta con permiso de escritura para guardar la base de datos y los archivos:</p>'
                . '<pre>' . e($dir) . '</pre>'
                . '<p>Créala en el servidor (fuera de <code>public_html</code>) o indica otra en <code>aula/config.local.php</code> con la opción <code>data_dir</code>.</p>'
            );
        }
        // Por si alguien la pone dentro de la web: que el servidor no la sirva.
        if (!is_file("$dir/.htaccess")) {
            @file_put_contents("$dir/.htaccess", "Require all denied\nDeny from all\n");
            @file_put_contents("$dir/index.html", '');
        }
    }

    /** ¿La carpeta de datos queda dentro de la web pública? */
    public static function dataDirIsPublic(): bool
    {
        $root = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $data = realpath(self::$dataDir);
        return $root && $data && str_starts_with(str_replace('\\', '/', $data) . '/', str_replace('\\', '/', $root) . '/');
    }

    public static function fatal(string $title, string $html): void
    {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . e($title) . '</title></head>'
            . '<body style="font-family:system-ui,sans-serif;max-width:720px;margin:48px auto;padding:0 16px;color:#0c2b5e;line-height:1.6">'
            . '<h1 style="font-size:1.4rem">' . e($title) . '</h1>' . $html . '</body></html>';
        exit;
    }

    public static function log(string $message): void
    {
        if (self::$dataDir !== '' && is_dir(self::$dataDir)) {
            @file_put_contents(self::$dataDir . '/error.log', '[' . date('c') . '] ' . $message . "\n", FILE_APPEND | LOCK_EX);
        }
    }

    public static function handleException(Throwable $e): void
    {
        $status = 500;
        $message = 'Algo ha fallado. Inténtalo de nuevo en un momento.';
        if ($e instanceof HttpError) {
            $status = $e->getCode() ?: 500;
            $message = $e->getMessage() ?: 'Error';
        } elseif ($e instanceof UserError) {
            $status = 400;
            $message = $e->getMessage();
        } else {
            self::log(get_class($e) . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
            if (!empty(self::$config['debug'])) {
                $message = get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
            }
        }
        if (!headers_sent()) {
            http_response_code($status);
        }
        if (wants_json()) {
            json_out(['ok' => false, 'error' => $message], $status);
        }
        try {
            $titles = [401 => 'Inicia sesión', 403 => 'Sin permiso', 404 => 'No encontrado', 419 => 'Sesión caducada'];
            View::page('error', ['status' => $status, 'message' => $message], $titles[$status] ?? 'Error');
        } catch (Throwable $inner) {
            self::fatal('Error', '<p>' . e($message) . '</p>');
        }
    }
}
