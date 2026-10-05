<?php
defined('AULA') || exit;

/**
 * Archivos subidos. Se guardan fuera de la web (carpeta de datos) y se
 * entregan siempre a través de PHP comprobando permisos.
 *
 * Cada archivo pertenece a un "contexto" (resource, message...) con un id.
 * Para dar acceso a un contexto propio, una extensión usa el filtro
 * 'file_access' ($allowed, $file, $user).
 */
class Files
{
    const EXT_MIME = [
        'pdf' => 'application/pdf',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
        'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'aac' => 'audio/aac', 'ogg' => 'audio/ogg', 'oga' => 'audio/ogg', 'wav' => 'audio/wav', 'weba' => 'audio/webm', 'opus' => 'audio/ogg',
        'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm', 'ogv' => 'video/ogg', 'mov' => 'video/quicktime',
        'txt' => 'text/plain', 'csv' => 'text/csv', 'html' => 'text/html', 'htm' => 'text/html', 'css' => 'text/css', 'js' => 'text/javascript', 'mjs' => 'text/javascript',
        'json' => 'application/json', 'xml' => 'application/xml', 'xsd' => 'application/xml', 'dtd' => 'application/xml-dtd', 'vtt' => 'text/vtt', 'srt' => 'text/plain',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'otf' => 'font/otf', 'eot' => 'application/vnd.ms-fontobject', 'ico' => 'image/x-icon',
        'swf' => 'application/x-shockwave-flash', 'wasm' => 'application/wasm',
        'zip' => 'application/zip', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'odt' => 'application/vnd.oasis.opendocument.text', 'ods' => 'application/vnd.oasis.opendocument.spreadsheet', 'odp' => 'application/vnd.oasis.opendocument.presentation',
    ];

    /** Tipos que se pueden mostrar dentro del navegador sin riesgo. */
    const INLINE = [
        'application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp',
        'audio/mpeg', 'audio/mp4', 'audio/aac', 'audio/ogg', 'audio/wav', 'audio/webm',
        'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'text/plain', 'text/vtt',
    ];

    public static function dir(string $sub = ''): string
    {
        $d = App::$dataDir . ($sub !== '' ? '/' . $sub : '');
        if (!is_dir($d)) {
            @mkdir($d, 0750, true);
        }
        return $d;
    }

    public static function maxBytes(): int
    {
        return max(1, (int)setting('max_upload_mb', 300)) * 1048576;
    }

    /** Tamaño de cada trozo en las subidas fragmentadas (según php.ini). */
    public static function chunkBytes(): int
    {
        $limits = array_filter([ini_bytes((string)ini_get('upload_max_filesize')), ini_bytes((string)ini_get('post_max_size'))]);
        $limit = $limits ? min($limits) : 2097152;
        return (int)max(262144, min(8388608, floor($limit * 0.8)));
    }

    public static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F"<>:|?*]/u', '', $name);
        $name = trim($name, " .");
        if ($name === '') {
            $name = 'archivo';
        }
        return mb_substr($name, 0, 200);
    }

    public static function ext(string $name): string
    {
        return strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }

    public static function mimeFor(string $name): string
    {
        return self::EXT_MIME[self::ext($name)] ?? 'application/octet-stream';
    }

    /**
     * Archivos recibidos en un campo del formulario, tanto por subida normal
     * ($_FILES) como por subida fragmentada (aula.js).
     * Devuelve una lista de ['path', 'name', 'size', 'uploaded', 'meta'].
     */
    public static function incoming(string $field): array
    {
        $out = [];
        if (!empty($_FILES[$field])) {
            $f = $_FILES[$field];
            $names = (array)$f['name'];
            foreach ($names as $i => $name) {
                $err = (int)((array)$f['error'])[$i];
                if ($err === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ($err !== UPLOAD_ERR_OK) {
                    throw new UserError(self::uploadError($err, (string)$name));
                }
                $tmp = (string)((array)$f['tmp_name'])[$i];
                if (!is_uploaded_file($tmp)) {
                    continue;
                }
                $out[] = ['path' => $tmp, 'name' => self::cleanName((string)$name), 'size' => (int)((array)$f['size'])[$i], 'uploaded' => true, 'meta' => null];
            }
        }
        foreach ((array)($_POST['_uploads'][$field] ?? []) as $token) {
            $done = self::chunkedFile((string)$token);
            if ($done) {
                $out[] = $done;
            }
        }
        foreach ($out as $f) {
            if ($f['size'] > self::maxBytes()) {
                throw new UserError('«' . $f['name'] . '» supera el tamaño máximo permitido (' . setting('max_upload_mb', 300) . ' MB).');
            }
        }
        return $out;
    }

    public static function uploadError(int $code, string $name): string
    {
        if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
            return "«{$name}» es demasiado grande para el servidor.";
        }
        return "No se pudo subir «{$name}» (error $code). Inténtalo de nuevo.";
    }

    /** Recibe un trozo de una subida fragmentada. */
    public static function receiveChunk(): array
    {
        $id = preg_replace('/[^a-f0-9]/', '', strtolower((string)($_POST['upload_id'] ?? '')));
        if (strlen($id) < 16 || strlen($id) > 64) {
            throw new UserError('Subida no válida.');
        }
        $index = (int)($_POST['index'] ?? -1);
        $total = (int)($_POST['total'] ?? 0);
        $size = (int)($_POST['size'] ?? 0);
        if ($total < 1 || $index < 0 || $index >= $total) {
            throw new UserError('Subida no válida.');
        }
        if ($size > self::maxBytes()) {
            throw new UserError('El archivo supera el tamaño máximo permitido (' . setting('max_upload_mb', 300) . ' MB).');
        }
        $base = self::dir('tmp') . '/u' . uid() . '_' . $id;
        if ($index === 0) {
            self::cleanupTmp();
            @unlink("$base.part");
            $meta = ['name' => self::cleanName((string)($_POST['name'] ?? '')), 'size' => $size, 'total' => $total, 'next' => 0, 'done' => false];
        } else {
            $meta = is_file("$base.json") ? json_decode((string)file_get_contents("$base.json"), true) : null;
        }
        if (!is_array($meta) || $meta['next'] !== $index || $meta['total'] !== $total) {
            throw new UserError('Se perdió parte del archivo. Vuelve a intentarlo.');
        }
        $chunk = $_FILES['chunk'] ?? null;
        if (!$chunk || (int)$chunk['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($chunk['tmp_name'])) {
            throw new UserError(self::uploadError((int)($chunk['error'] ?? UPLOAD_ERR_NO_FILE), $meta['name']));
        }
        $in = fopen($chunk['tmp_name'], 'rb');
        $out = fopen("$base.part", 'ab');
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
        clearstatcache(true, "$base.part");
        $written = filesize("$base.part");
        if ($written > $meta['size']) {
            @unlink("$base.part");
            throw new UserError('El archivo recibido no coincide. Vuelve a intentarlo.');
        }
        $meta['next']++;
        if ($meta['next'] === $total) {
            if ($written !== $meta['size']) {
                @unlink("$base.part");
                throw new UserError('El archivo llegó incompleto. Vuelve a intentarlo.');
            }
            $meta['done'] = true;
        }
        file_put_contents("$base.json", json_encode($meta));
        return ['ok' => true, 'done' => $meta['done'], 'token' => $id];
    }

    private static function chunkedFile(string $token): ?array
    {
        $id = preg_replace('/[^a-f0-9]/', '', strtolower($token));
        if ($id === '' || !uid()) {
            return null;
        }
        $base = self::dir('tmp') . '/u' . uid() . '_' . $id;
        $meta = is_file("$base.json") ? json_decode((string)file_get_contents("$base.json"), true) : null;
        if (!is_array($meta) || empty($meta['done']) || !is_file("$base.part")) {
            return null;
        }
        return ['path' => "$base.part", 'name' => $meta['name'], 'size' => (int)filesize("$base.part"), 'uploaded' => false, 'meta' => "$base.json"];
    }

    /** Borra temporales de más de un día. */
    public static function cleanupTmp(): void
    {
        foreach (glob(self::dir('tmp') . '/*') ?: [] as $f) {
            if (is_file($f) && filemtime($f) < time() - 86400) {
                @unlink($f);
            }
        }
    }

    /** Elimina el temporal de un archivo recibido que no se va a guardar. */
    public static function discard(array $in): void
    {
        if (!$in['uploaded']) {
            @unlink($in['path']);
        }
        if (!empty($in['meta'])) {
            @unlink($in['meta']);
        }
    }

    public static function checkExt(array $in, array $allowed): void
    {
        $ext = self::ext($in['name']);
        if (!in_array($ext, $allowed, true)) {
            throw new UserError('«' . $in['name'] . '» no es un tipo de archivo válido aquí. Se aceptan: ' . implode(', ', $allowed) . '.');
        }
    }

    /**
     * Reduce una foto recibida si es más ancha que $maxWidth o pesa mucho
     * (las del móvil pueden ocupar varios MB). Necesita GD; si no está o
     * algo falla, devuelve el archivo tal cual.
     */
    public static function shrinkImage(array $in, int $maxWidth): array
    {
        if (!function_exists('imagecreatefromstring') || !in_array(self::ext($in['name']), ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return $in;
        }
        try {
            $info = @getimagesize($in['path']);
            if (!$info || ($info[0] <= $maxWidth && $in['size'] <= 1572864)) {
                return $in;
            }
            $src = @imagecreatefromstring((string)file_get_contents($in['path']));
            if (!$src) {
                return $in;
            }
            $w = min($maxWidth, $info[0]);
            $h = (int)round($info[1] * $w / $info[0]);
            $dst = imagecreatetruecolor($w, $h);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $info[0], $info[1]);
            if (!imagejpeg($dst, $in['path'], 85)) {
                return $in;
            }
            clearstatcache(true, $in['path']);
            $in['size'] = (int)filesize($in['path']);
            $in['name'] = pathinfo($in['name'], PATHINFO_FILENAME) . '.jpg';
        } catch (Throwable $e) {
            // Nos quedamos con el original.
        }
        return $in;
    }

    /** Guarda un archivo recibido y devuelve su id. */
    public static function store(array $in, string $context, int $contextId): int
    {
        $sub = 'files/' . date('Y/m');
        $rel = $sub . '/' . bin2hex(random_bytes(16));
        $dest = self::dir($sub) . '/' . basename($rel);
        $ok = $in['uploaded'] ? move_uploaded_file($in['path'], $dest) : rename($in['path'], $dest);
        if (!$ok) {
            throw new RuntimeException('No se pudo guardar el archivo en el servidor.');
        }
        if (!empty($in['meta'])) {
            @unlink($in['meta']);
        }
        return Db::insert('files', [
            'user_id' => uid(),
            'context' => $context,
            'context_id' => $contextId,
            'name' => $in['name'],
            'mime' => self::mimeFor($in['name']),
            'size' => (int)filesize($dest),
            'storage' => $rel,
            'created_at' => time(),
        ]);
    }

    public static function find(int $id): ?array
    {
        return $id ? Db::one('SELECT * FROM files WHERE id = ?', [$id]) : null;
    }

    public static function forContext(string $context, int $contextId): array
    {
        return Db::all('SELECT * FROM files WHERE context = ? AND context_id = ? ORDER BY id', [$context, $contextId]);
    }

    public static function path(array $file): string
    {
        return App::$dataDir . '/' . $file['storage'];
    }

    public static function url(array $file, bool $download = false): string
    {
        return serve_url('f', $file['id'] . '/' . $file['name']) . ($download ? '?dl=1' : '');
    }

    /** Copia lógica (comparte el archivo en disco). */
    public static function copy(int $id, string $context, int $contextId): int
    {
        $f = self::find($id);
        if (!$f) {
            return 0;
        }
        unset($f['id']);
        $f['context'] = $context;
        $f['context_id'] = $contextId;
        $f['created_at'] = time();
        return Db::insert('files', $f);
    }

    public static function delete(int $id): void
    {
        $f = self::find($id);
        if (!$f) {
            return;
        }
        Db::delete('files', 'id = ?', [$id]);
        if (!Db::val('SELECT COUNT(*) FROM files WHERE storage = ?', [$f['storage']])) {
            @unlink(self::path($f));
        }
    }

    public static function deleteContext(string $context, int $contextId): void
    {
        foreach (self::forContext($context, $contextId) as $f) {
            self::delete((int)$f['id']);
        }
    }

    public static function canAccess(array $file, ?array $u): bool
    {
        if (!$u) {
            return false;
        }
        if (is_teacher($u) || (int)$file['user_id'] === (int)$u['id']) {
            return true;
        }
        if ($file['context'] === 'resource') {
            $res = Courses::resource((int)$file['context_id']);
            return $res && Courses::canViewResource($res, $u);
        }
        if ($file['context'] === 'course') {
            $course = Courses::find((int)$file['context_id']);
            return $course && Courses::canView($course, $u);
        }
        return (bool)apply_filters('file_access', false, $file, $u);
    }

    /**
     * Envía un archivo al navegador con soporte de rangos (necesario para
     * poder avanzar/retroceder en audios y vídeos).
     * $disposition: 'auto' (inline si es seguro), 'inline' o 'attachment'.
     */
    public static function send(string $path, string $mime, string $name, string $disposition = 'auto', int $maxAge = 0): void
    {
        if (!is_file($path)) {
            throw new HttpError('Archivo no encontrado.', 404);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $size = (int)filesize($path);
        $mtime = (int)filemtime($path);
        $etag = '"' . md5($path . $mtime . $size) . '"';
        $inline = $disposition === 'inline' || ($disposition === 'auto' && in_array($mime, self::INLINE, true));

        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: ' . $mime . (str_starts_with($mime, 'text/') || $mime === 'application/json' || $mime === 'application/xml' ? '; charset=utf-8' : ''));
        header('X-Content-Type-Options: nosniff');
        header('Accept-Ranges: bytes');
        header('ETag: ' . $etag);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        header('Cache-Control: private, max-age=' . $maxAge);
        $ascii = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name);
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));

        if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            exit;
        }

        $start = 0;
        $end = $size - 1;
        if ($size > 0 && preg_match('/^bytes=(\d*)-(\d*)$/', trim($_SERVER['HTTP_RANGE'] ?? ''), $m)) {
            if ($m[1] === '' && $m[2] !== '') {
                $start = max(0, $size - (int)$m[2]);
            } else {
                $start = (int)$m[1];
                if ($m[2] !== '') {
                    $end = min((int)$m[2], $size - 1);
                }
            }
            if ($start > $end || $start >= $size) {
                http_response_code(416);
                header("Content-Range: bytes */$size");
                exit;
            }
            http_response_code(206);
            header("Content-Range: bytes $start-$end/$size");
        }
        $length = $size > 0 ? $end - $start + 1 : 0;
        header('Content-Length: ' . $length);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
            exit;
        }
        @set_time_limit(0);
        $fp = fopen($path, 'rb');
        fseek($fp, $start);
        while ($length > 0 && !feof($fp) && !connection_aborted()) {
            $buf = fread($fp, (int)min(1048576, $length));
            echo $buf;
            flush();
            $length -= strlen($buf);
        }
        fclose($fp);
        exit;
    }
}
