<?php
/**
 * Extensión «SCORM»: paquetes SCORM 1.2 / 2004 con seguimiento.
 *
 * Los paquetes se descomprimen en <datos>/scorm/<carpeta>/ y se sirven por
 * serve.php/scorm/<recurso>/<carpeta>/<ruta>, comprobando el acceso.
 */
defined('AULA') || exit;

Db::migrate('plugin_scorm', [
    1 => [
        'CREATE TABLE scorm_packages (id {PK}, dir VARCHAR(64) NOT NULL, title VARCHAR(255) NOT NULL, version VARCHAR(10) NOT NULL,
            items {TEXT}, size BIGINT NOT NULL DEFAULT 0, created_at INT NOT NULL DEFAULT 0){TABLE_OPTS}',
        'CREATE TABLE scorm_tracks (id {PK}, resource_id INT NOT NULL, user_id INT NOT NULL, sco VARCHAR(190) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT \'incomplete\', score REAL NULL, total_seconds INT NOT NULL DEFAULT 0, data {TEXT},
            attempts INT NOT NULL DEFAULT 0, created_at INT NOT NULL DEFAULT 0, updated_at INT NOT NULL DEFAULT 0){TABLE_OPTS}',
        'CREATE UNIQUE INDEX scorm_tracks_key ON scorm_tracks (resource_id, user_id, sco)',
        'CREATE INDEX scorm_tracks_res ON scorm_tracks (resource_id)',
    ],
]);

class Scorm
{
    const LABELS = [
        'passed' => 'Superado', 'completed' => 'Completado', 'failed' => 'No superado',
        'incomplete' => 'En curso', 'browsed' => 'Visto', 'not attempted' => 'Sin empezar',
    ];
    const DONE = ['passed', 'completed'];

    public static function dir(string $sub = ''): string
    {
        return Files::dir('scorm' . ($sub !== '' ? '/' . $sub : ''));
    }

    public static function package(int $id): ?array
    {
        $p = $id ? Db::one('SELECT * FROM scorm_packages WHERE id = ?', [$id]) : null;
        if ($p) {
            $p['items'] = json_decode((string)$p['items'], true) ?: [];
        }
        return $p;
    }

    /** Elementos que se pueden abrir (con href). */
    public static function launchable(array $pkg): array
    {
        return array_values(array_filter($pkg['items'], fn($i) => !empty($i['href'])));
    }

    /** SCO con seguimiento (los "asset" no cuentan para el progreso). */
    public static function scos(array $pkg): array
    {
        return array_values(array_filter(self::launchable($pkg), fn($i) => ($i['type'] ?? 'sco') === 'sco'));
    }

    // ─── Importación ─────────────────────────────────────────────

    /** Descomprime y registra un paquete. Devuelve el id. */
    public static function import(array $in): int
    {
        if (!class_exists('ZipArchive')) {
            throw new UserError('El servidor no tiene la extensión ZIP de PHP: no se pueden instalar paquetes SCORM.');
        }
        Files::checkExt($in, ['zip']);
        $zip = new ZipArchive();
        if ($zip->open($in['path']) !== true) {
            throw new UserError('No se pudo abrir el ZIP. ¿Está completo?');
        }
        // Localiza imsmanifest.xml (en la raíz o dentro de una carpeta).
        $prefix = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string)$zip->getNameIndex($i));
            if (preg_match('~(^|/)imsmanifest\.xml$~i', $name) && !str_starts_with($name, '__MACOSX/')) {
                $p = substr($name, 0, -strlen('imsmanifest.xml'));
                if ($prefix === null || strlen($p) < strlen($prefix)) {
                    $prefix = $p;
                }
            }
        }
        if ($prefix === null) {
            $zip->close();
            throw new UserError('Este ZIP no es un paquete SCORM: no contiene imsmanifest.xml.');
        }
        $manifest = self::parseManifest((string)$zip->getFromName($prefix . 'imsmanifest.xml'));

        $dirName = date('Ymd') . '_' . bin2hex(random_bytes(8));
        $dest = self::dir($dirName);
        $total = 0;
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = str_replace('\\', '/', (string)$stat['name']);
                if ($prefix !== '' && !str_starts_with($name, $prefix)) {
                    continue;
                }
                $rel = substr($name, strlen($prefix));
                if ($rel === '' || str_starts_with($rel, '__MACOSX/')) {
                    continue;
                }
                if (str_starts_with($rel, '/') || preg_match('~(^|/)\.\.(/|$)~', $rel) || str_contains($rel, "\0") || preg_match('~^[A-Za-z]:~', $rel)) {
                    throw new UserError('El ZIP contiene rutas no válidas.');
                }
                $total += (int)$stat['size'];
                if ($total > 2147483648) {
                    throw new UserError('El paquete descomprimido supera 2 GB.');
                }
                $target = $dest . '/' . $rel;
                if (str_ends_with($rel, '/')) {
                    @mkdir($target, 0750, true);
                    continue;
                }
                @mkdir(dirname($target), 0750, true);
                $src = $zip->getStream($name);
                $out = fopen($target, 'wb');
                if (!$src || !$out) {
                    throw new RuntimeException('No se pudo descomprimir ' . $rel);
                }
                stream_copy_to_stream($src, $out);
                fclose($src);
                fclose($out);
            }
        } catch (Throwable $e) {
            $zip->close();
            self::rmdir($dest);
            throw $e;
        }
        $zip->close();
        Files::discard($in);

        return Db::insert('scorm_packages', [
            'dir' => $dirName,
            'title' => mb_substr($manifest['title'] ?: 'Paquete SCORM', 0, 255),
            'version' => $manifest['version'],
            'items' => json_encode($manifest['items'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'size' => $total,
            'created_at' => time(),
        ]);
    }

    public static function parseManifest(string $xml): array
    {
        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $xml !== '' && $doc->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) {
            throw new UserError('El imsmanifest.xml del paquete no se puede leer.');
        }
        $xp = new DOMXPath($doc);
        $schema = trim((string)$xp->evaluate("string(//*[local-name()='metadata']/*[local-name()='schemaversion'])"));
        $version = (str_contains($schema, '2004') || str_contains($schema, 'CAM 1.3') || str_contains($xml, 'adlcp_v1p3')) ? '2004' : '1.2';

        // Recursos
        $resources = [];
        foreach ($xp->query("//*[local-name()='resources']/*[local-name()='resource']") as $r) {
            /** @var DOMElement $r */
            $type = 'sco';
            $base = '';
            foreach ($r->attributes as $a) {
                if (strtolower($a->localName) === 'scormtype') {
                    $type = strtolower($a->value) === 'asset' ? 'asset' : 'sco';
                }
                if ($a->localName === 'base') {
                    $base = $a->value;
                }
            }
            $resources[$r->getAttribute('identifier')] = ['href' => $r->getAttribute('href'), 'type' => $type, 'base' => $base];
        }

        // Organización por defecto
        $orgs = $xp->query("//*[local-name()='organizations']");
        $org = null;
        $title = '';
        if ($orgs->length) {
            /** @var DOMElement $orgsEl */
            $orgsEl = $orgs->item(0);
            $default = $orgsEl->getAttribute('default');
            foreach ($xp->query("*[local-name()='organization']", $orgsEl) as $o) {
                if ($org === null || $o->getAttribute('identifier') === $default) {
                    $org = $o;
                }
            }
        }
        $items = [];
        if ($org) {
            $title = trim((string)$xp->evaluate("string(*[local-name()='title'])", $org));
            $walk = function (DOMElement $parent, int $level) use (&$walk, &$items, $xp, $resources) {
                foreach ($xp->query("*[local-name()='item']", $parent) as $it) {
                    /** @var DOMElement $it */
                    if (strtolower($it->getAttribute('isvisible')) === 'false') {
                        continue;
                    }
                    $ref = $it->getAttribute('identifierref');
                    $res = $ref !== '' ? ($resources[$ref] ?? null) : null;
                    $href = '';
                    if ($res && $res['href'] !== '') {
                        $href = ltrim($res['base'] . $res['href'], '/');
                        $params = $it->getAttribute('parameters');
                        if ($params !== '') {
                            $href .= (str_contains($href, '?') ? '&' : (str_starts_with($params, '?') || str_starts_with($params, '#') ? '' : '?')) . ltrim($params, '?&');
                        }
                    }
                    $items[] = [
                        'id' => $it->getAttribute('identifier') ?: 'item' . count($items),
                        'title' => trim((string)$xp->evaluate("string(*[local-name()='title'])", $it)) ?: 'Sin título',
                        'href' => $href,
                        'type' => $href === '' ? 'group' : ($res['type'] ?? 'sco'),
                        'level' => $level,
                        'data' => trim((string)$xp->evaluate("string(*[local-name()='dataFromLMS' or local-name()='datafromlms'])", $it)),
                        'mastery' => trim((string)$xp->evaluate("string(*[local-name()='masteryscore'])", $it)),
                    ];
                    $walk($it, $level + 1);
                }
            };
            $walk($org, 0);
        }
        if (!array_filter($items, fn($i) => $i['href'] !== '')) {
            foreach ($resources as $id => $r) {
                if ($r['href'] !== '') {
                    $items[] = ['id' => $id, 'title' => $title ?: 'Contenido', 'href' => ltrim($r['base'] . $r['href'], '/'), 'type' => $r['type'], 'level' => 0, 'data' => '', 'mastery' => ''];
                }
            }
        }
        if (!$items) {
            throw new UserError('El paquete no tiene ninguna página que abrir.');
        }
        return ['version' => $version, 'title' => $title, 'items' => $items];
    }

    public static function rmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    /** Borra el paquete si ya ningún recurso lo usa. */
    public static function releasePackage(int $packageId, int $exceptResource = 0): void
    {
        foreach (Db::all("SELECT id, data FROM resources WHERE type = 'scorm' AND id <> ?", [$exceptResource]) as $r) {
            $d = json_decode((string)$r['data'], true) ?: [];
            if ((int)($d['package_id'] ?? 0) === $packageId) {
                return;
            }
        }
        $pkg = self::package($packageId);
        if ($pkg) {
            self::rmdir(self::dir() . '/' . basename($pkg['dir']));
            Db::delete('scorm_packages', 'id = ?', [$packageId]);
        }
    }

    // ─── Seguimiento ─────────────────────────────────────────────

    public static function tracks(int $resourceId, int $userId): array
    {
        $out = [];
        foreach (Db::all('SELECT * FROM scorm_tracks WHERE resource_id = ? AND user_id = ?', [$resourceId, $userId]) as $t) {
            $t['data'] = json_decode((string)$t['data'], true) ?: [];
            $out[$t['sco']] = $t;
        }
        return $out;
    }

    public static function deriveStatus(array $d, string $version): string
    {
        if ($version === '2004') {
            $success = $d['cmi.success_status'] ?? 'unknown';
            $completion = $d['cmi.completion_status'] ?? 'unknown';
            if ($success === 'passed' || $success === 'failed') {
                return $success;
            }
            return $completion === 'completed' ? 'completed' : 'incomplete';
        }
        $s = $d['cmi.core.lesson_status'] ?? 'incomplete';
        return isset(self::LABELS[$s]) && $s !== 'not attempted' ? $s : 'incomplete';
    }

    public static function deriveScore(array $d, string $version): ?float
    {
        $raw = $version === '2004' ? ($d['cmi.score.raw'] ?? '') : ($d['cmi.core.score.raw'] ?? '');
        if (is_numeric($raw)) {
            $max = $version === '2004' ? ($d['cmi.score.max'] ?? '') : ($d['cmi.core.score.max'] ?? '');
            return is_numeric($max) && (float)$max > 0 && (float)$max !== 100.0 ? round((float)$raw * 100 / (float)$max, 1) : round((float)$raw, 1);
        }
        if ($version === '2004' && is_numeric($d['cmi.score.scaled'] ?? '')) {
            return round((float)$d['cmi.score.scaled'] * 100, 1);
        }
        return null;
    }

    /** "HHHH:MM:SS.SS" (1.2) o "PT1H2M3S" (2004) a segundos. */
    public static function parseTime(string $t): int
    {
        if (preg_match('/^(\d+):(\d{1,2}):(\d{1,2})(\.\d+)?$/', $t, $m)) {
            return (int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3];
        }
        if (preg_match('/^P(?:(\d+)Y)?(?:(\d+)M)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:([\d.]+)S)?)?$/', $t, $m)) {
            return (int)($m[3] ?? 0) * 86400 + (int)($m[4] ?? 0) * 3600 + (int)($m[5] ?? 0) * 60 + (int)floor((float)($m[6] ?? 0));
        }
        return 0;
    }

    public static function formatTime(int $s, string $version): string
    {
        if ($version === '2004') {
            return sprintf('PT%dH%dM%dS', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60);
        }
        return sprintf('%04d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60);
    }

    /** Resumen de un alumno en un recurso SCORM. */
    public static function summary(array $pkg, array $tracks): array
    {
        $scos = self::scos($pkg);
        $done = 0;
        $scores = [];
        $seconds = 0;
        $last = 0;
        $status = null;
        foreach ($scos as $s) {
            $t = $tracks[$s['id']] ?? null;
            if (!$t) {
                continue;
            }
            if (in_array($t['status'], self::DONE, true)) {
                $done++;
            }
            if ($t['score'] !== null && $t['score'] !== '') {
                $scores[] = (float)$t['score'];
            }
            $seconds += (int)$t['total_seconds'];
            $last = max($last, (int)$t['updated_at']);
            $status = $t['status'];
        }
        $n = count($scos);
        if ($n === 1) {
            $label = $status ? self::LABELS[$status] ?? $status : 'Sin empezar';
        } else {
            $label = $done . ' de ' . $n . ' completados';
        }
        return [
            'started' => $last > 0,
            'done' => $n > 0 && $done === $n,
            'failed' => $n === 1 && $status === 'failed',
            'label' => $label,
            'score' => $scores ? round(array_sum($scores) / count($scores), 1) : null,
            'seconds' => $seconds,
            'last' => $last,
        ];
    }
}

class ScormResource extends ResourceType
{
    public function id(): string { return 'scorm'; }
    public function label(): string { return 'Paquete SCORM'; }
    public function icon(): string { return 'cube'; }
    public function help(): string { return 'Actividades interactivas en ZIP (SCORM 1.2 o 2004): guarda progreso, nota y tiempo de cada alumno.'; }
    public function wide(): bool { return true; }

    private function pkg(array $res): ?array
    {
        return Scorm::package((int)($res['data']['package_id'] ?? 0));
    }

    public function form(array $res): string
    {
        $pkg = $this->pkg($res);
        $html = '';
        if ($pkg) {
            $html .= '<p class="current-file">' . icon('cube') . ' Paquete actual: <strong>' . e($pkg['title']) . '</strong> <small class="muted">(SCORM ' . e($pkg['version']) . ', '
                . count(Scorm::launchable($pkg)) . ' apartados, ' . fmt_size((int)$pkg['size']) . ')</small></p>';
        }
        $html .= upload_field('package', $pkg ? 'Sustituir por otro ZIP (opcional)' : 'Paquete SCORM (.zip)', '.zip,application/zip', false, !$pkg,
            $pkg ? 'Si lo sustituyes, se conserva el progreso guardado de cada alumno en los apartados que se llamen igual.' : 'El ZIP tal cual sale del generador: debe contener imsmanifest.xml.');
        $toc = !isset($res['data']['toc']) || !empty($res['data']['toc']);
        $html .= '<label class="check"><input type="checkbox" name="toc" value="1" ' . ($toc ? 'checked' : '') . '> Mostrar el índice de apartados (si el paquete tiene varios)</label>';
        return $html;
    }

    public function save(array $res, bool $isNew): array
    {
        $data = $res['data'];
        $in = Files::incoming('package');
        if ($in) {
            $old = (int)($data['package_id'] ?? 0);
            try {
                $data['package_id'] = Scorm::import($in[0]);
            } catch (Throwable $e) {
                Files::discard($in[0]);
                throw $e;
            }
            if ($old) {
                Scorm::releasePackage($old, (int)$res['id']);
            }
        }
        if (empty($data['package_id'])) {
            throw new UserError('Sube el paquete SCORM (.zip).');
        }
        $data['toc'] = !empty($_POST['toc']);
        return $data;
    }

    public function meta(array $res): string
    {
        $pkg = $this->pkg($res);
        if (!$pkg) {
            return '';
        }
        $n = count(Scorm::launchable($pkg));
        return $n > 1 ? $n . ' apartados' : 'Interactivo';
    }

    public function status(array $res, int $userId, ?array $view): ?array
    {
        $pkg = $this->pkg($res);
        if (!$pkg) {
            return null;
        }
        if (!Scorm::scos($pkg)) {
            return parent::status($res, $userId, $view);
        }
        $s = Scorm::summary($pkg, Scorm::tracks((int)$res['id'], $userId));
        if (!$s['started']) {
            return null;
        }
        return ['done' => $s['done'], 'label' => $s['label'] . ($s['score'] !== null ? ' · ' . str_replace('.', ',', (string)$s['score']) . ' puntos' : '')];
    }

    public function render(array $res, array $course): string
    {
        $pkg = $this->pkg($res);
        if (!$pkg) {
            return '<div class="alert alert-warn">Falta el paquete SCORM.</div>';
        }
        $u = user();
        $tracks = Scorm::tracks((int)$res['id'], (int)$u['id']);
        $items = $pkg['items'];
        $launch = Scorm::launchable($pkg);
        // Empieza en el primer apartado sin completar.
        $start = $launch[0]['id'];
        foreach ($launch as $it) {
            $t = $tracks[$it['id']] ?? null;
            if (($it['type'] ?? 'sco') === 'sco' && (!$t || !in_array($t['status'], Scorm::DONE, true))) {
                $start = $it['id'];
                break;
            }
        }
        $cfg = [
            'resource' => (int)$res['id'],
            'version' => $pkg['version'],
            'base' => serve_url('scorm', $res['id'] . '/' . $pkg['dir']) . '/',
            'commit' => url('scorm/commit'),
            'learner' => ['id' => (string)$u['id'], 'name' => $u['name']],
            'items' => $items,
            'start' => $start,
            'tracks' => array_map(fn($t) => ['data' => $t['data'], 'status' => $t['status']], $tracks),
            'labels' => Scorm::LABELS,
        ];
        $showToc = count($launch) > 1 && (!isset($res['data']['toc']) || !empty($res['data']['toc']));
        return View::fetch('scorm:player', ['cfg' => $cfg, 'pkg' => $pkg, 'showToc' => $showToc, 'tracks' => $tracks, 'res' => $res]);
    }

    public function teacherPanel(array $res, array $course): string
    {
        $pkg = $this->pkg($res);
        if (!$pkg) {
            return '';
        }
        $rows = [];
        foreach (Courses::students((int)$course['id']) as $s) {
            if (is_teacher($s)) {
                continue;
            }
            $rows[] = ['user' => $s, 'sum' => Scorm::summary($pkg, Scorm::tracks((int)$res['id'], (int)$s['id']))];
        }
        return View::fetch('scorm:report', ['rows' => $rows, 'res' => $res, 'pkg' => $pkg]);
    }

    public function delete(array $res): void
    {
        Db::delete('scorm_tracks', 'resource_id = ?', [$res['id']]);
        if (!empty($res['data']['package_id'])) {
            Scorm::releasePackage((int)$res['data']['package_id'], (int)$res['id']);
        }
    }
}

ResourceTypes::register(new ScormResource());

add_filter('backup_dirs', function (array $dirs) {
    $dirs[] = 'scorm';
    return $dirs;
});

add_action('user_deleted', function (int $userId) {
    Db::delete('scorm_tracks', 'user_id = ?', [$userId]);
});

add_action('head', function () {
    if (($_GET['r'] ?? '') === 'resource') {
        echo '<link rel="stylesheet" href="' . e(Plugins::asset('scorm', 'player.css')) . '">';
    }
});

// serve.php/scorm/<recurso>/<carpeta>/<ruta del archivo>
Router::serve('scorm', function (string $rest) {
    $parts = explode('/', $rest, 3);
    if (count($parts) < 3) {
        throw new HttpError('No encontrado.', 404);
    }
    [$resId, $dir, $path] = $parts;
    $res = Courses::resource((int)$resId);
    if (!$res || $res['type'] !== 'scorm' || !Courses::canViewResource($res, user())) {
        throw new HttpError('No encontrado.', 404);
    }
    $pkg = Scorm::package((int)($res['data']['package_id'] ?? 0));
    if (!$pkg || $pkg['dir'] !== $dir || $path === '' || preg_match('~(^|/)\.\.(/|$)~', $path) || str_contains($path, "\0")) {
        throw new HttpError('No encontrado.', 404);
    }
    $base = realpath(Scorm::dir() . '/' . basename($pkg['dir']));
    $file = realpath($base . '/' . $path);
    if (!$base || !$file || !str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', $base) . '/') || !is_file($file)) {
        throw new HttpError('No encontrado.', 404);
    }
    Files::send($file, Files::mimeFor($file), basename($file), 'inline', 3600);
});

// Guardado del progreso (lo llama player.js)
Router::add('scorm/commit', function () {
    if (!is_post()) {
        abort(405);
    }
    $in = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($in)) {
        json_out(['ok' => false, 'error' => 'Datos no válidos.'], 400);
    }
    $res = Courses::resource((int)($in['resource'] ?? 0));
    if (!$res || $res['type'] !== 'scorm' || !Courses::canViewResource($res, user())) {
        json_out(['ok' => false, 'error' => 'No encontrado.'], 404);
    }
    $pkg = Scorm::package((int)($res['data']['package_id'] ?? 0));
    $sco = (string)($in['sco'] ?? '');
    if (!$pkg || !in_array($sco, array_column(Scorm::launchable($pkg), 'id'), true)) {
        json_out(['ok' => false, 'error' => 'Apartado desconocido.'], 400);
    }
    $clean = [];
    foreach ((array)($in['data'] ?? []) as $k => $v) {
        if (is_string($k) && preg_match('/^(cmi|adl)\.[A-Za-z0-9_.]{1,180}$/', $k) && (is_scalar($v) || $v === null)) {
            $clean[$k] = mb_substr((string)$v, 0, 65536);
        }
    }
    $track = Db::one('SELECT * FROM scorm_tracks WHERE resource_id = ? AND user_id = ? AND sco = ?', [$res['id'], uid(), $sco]);
    $data = array_merge($track ? (json_decode((string)$track['data'], true) ?: []) : [], $clean);
    if (strlen(json_encode($data)) > 2097152) {
        json_out(['ok' => false, 'error' => 'Demasiados datos.'], 413);
    }
    $v = $pkg['version'];
    $total = $track ? (int)$track['total_seconds'] : 0;
    if (!empty($in['finish'])) {
        $sessionKey = $v === '2004' ? 'cmi.session_time' : 'cmi.core.session_time';
        $secs = Scorm::parseTime((string)($data[$sessionKey] ?? ''));
        if ($secs <= 0) {
            $secs = max(0, min(86400, (int)($in['elapsed'] ?? 0)));
        }
        $total += $secs;
        unset($data[$sessionKey]);
        $data[$v === '2004' ? 'cmi.total_time' : 'cmi.core.total_time'] = Scorm::formatTime($total, $v);
    }
    $row = [
        'status' => Scorm::deriveStatus($data, $v),
        'score' => Scorm::deriveScore($data, $v),
        'total_seconds' => $total,
        'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'updated_at' => time(),
    ];
    if ($track) {
        if (!empty($in['finish'])) {
            $row['attempts'] = (int)$track['attempts'] + 1;
        }
        Db::update('scorm_tracks', $row, 'id = ?', [$track['id']]);
    } else {
        Db::insert('scorm_tracks', $row + [
            'resource_id' => $res['id'], 'user_id' => uid(), 'sco' => $sco, 'attempts' => !empty($in['finish']) ? 1 : 0, 'created_at' => time(),
        ]);
    }
    do_action('scorm_tracked', $res, uid(), $sco, $row);
    json_out(['ok' => true, 'status' => $row['status'], 'label' => Scorm::LABELS[$row['status']] ?? $row['status']]);
});

Router::add('scorm/reset', function () {
    $res = Courses::resource(pint('resource'));
    if ($res) {
        Db::delete('scorm_tracks', 'resource_id = ? AND user_id = ?', [$res['id'], pint('user')]);
        flash('ok', 'Progreso reiniciado: la próxima vez empezará desde el principio.');
    }
    redirect('resource', ['id' => pint('resource')]);
}, 'teacher');
