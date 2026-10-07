<?php
/**
 * Extensión «Contenidos básicos»: tipos de recurso que vienen de serie.
 */
defined('AULA') || exit;

/**
 * Base para tipos que guardan un archivo subido (data.file_id).
 */
abstract class FileResourceType extends ResourceType
{
    /** Extensiones permitidas. */
    abstract protected function extensions(): array;

    protected function accept(): string
    {
        return implode(',', array_map(fn($e) => '.' . $e, $this->extensions()));
    }

    protected function file(array $res): ?array
    {
        return Files::find((int)($res['data']['file_id'] ?? 0));
    }

    protected function fileRequired(array $res): bool
    {
        return true;
    }

    public function form(array $res): string
    {
        $f = $this->file($res);
        $html = '';
        if ($f) {
            $html .= '<p class="current-file">' . icon('paperclip') . ' Archivo actual: <a href="' . e(Files::url($f)) . '" target="_blank">' . e($f['name']) . '</a> <small class="muted">(' . fmt_size((int)$f['size']) . ')</small></p>';
        }
        $required = !$f && $this->fileRequired($res);
        return $html . upload_field('file', $f ? 'Sustituir archivo (opcional)' : 'Archivo', $this->accept(), false, $required,
            'Formatos: ' . implode(', ', $this->extensions()) . '. Máximo ' . setting('max_upload_mb', 300) . ' MB.');
    }

    public function save(array $res, bool $isNew): array
    {
        $data = $res['data'];
        $in = Files::incoming('file');
        if ($in) {
            Files::checkExt($in[0], $this->extensions());
            $old = (int)($data['file_id'] ?? 0);
            $data['file_id'] = Files::store($in[0], 'resource', (int)$res['id']);
            if ($old) {
                Files::delete($old);
            }
        }
        if (empty($data['file_id']) && $this->fileRequired($res)) {
            throw new UserError('Falta el archivo.');
        }
        return $data;
    }

    public function meta(array $res): string
    {
        $f = $this->file($res);
        return $f ? strtoupper(Files::ext($f['name'])) . ' · ' . fmt_size((int)$f['size']) : '';
    }

    protected function downloadButton(array $f, string $label = 'Descargar'): string
    {
        return '<a class="btn btn-ghost" href="' . e(Files::url($f, true)) . '">' . icon('download') . ' ' . e($label) . '</a>';
    }
}

class PdfResource extends FileResourceType
{
    public function id(): string { return 'pdf'; }
    public function label(): string { return 'Documento PDF'; }
    public function icon(): string { return 'file-pdf'; }
    public function help(): string { return 'Fichas, actividades, apuntes… Se ven en la página y se pueden descargar.'; }
    protected function extensions(): array { return ['pdf']; }

    public function render(array $res, array $course): string
    {
        $f = $this->file($res);
        if (!$f) {
            return '<div class="alert alert-warn">Falta el archivo.</div>';
        }
        $url = Files::url($f);
        return '<div class="btn-row">'
            . '<a class="btn btn-primary" href="' . e($url) . '" target="_blank" rel="noopener">' . icon('up-right-from-square') . ' Abrir en pantalla completa</a>'
            . $this->downloadButton($f) . '</div>'
            . '<div class="pdf-frame"><iframe src="' . e($url) . '#view=FitH" title="' . e($res['title']) . '" loading="lazy"></iframe></div>'
            . '<p class="muted small pdf-hint">¿No ves el documento? Usa «Abrir en pantalla completa» o «Descargar».</p>';
    }
}

class AudioResource extends FileResourceType
{
    public function id(): string { return 'audio'; }
    public function label(): string { return 'Audio'; }
    public function icon(): string { return 'headphones'; }
    public function help(): string { return 'Comprensiones orales, dictados, pronunciación… (MP3, M4A, OGG, WAV).'; }
    protected function extensions(): array { return ['mp3', 'm4a', 'aac', 'ogg', 'oga', 'opus', 'wav', 'weba']; }

    public function form(array $res): string
    {
        $allow = !empty($res['data']['allow_download']) || !$res['id'];
        return parent::form($res)
            . '<label class="check"><input type="checkbox" name="allow_download" value="1" ' . ($allow ? 'checked' : '') . '> Permitir descargar el audio</label>'
            . editor_field('transcript', (string)($res['data']['transcript'] ?? ''), 'Transcripción (opcional)', 'Se muestra plegada bajo el reproductor.');
    }

    public function save(array $res, bool $isNew): array
    {
        $data = parent::save($res, $isNew);
        $data['allow_download'] = !empty($_POST['allow_download']);
        $data['transcript'] = Html::clean((string)($_POST['transcript'] ?? ''));
        return $data;
    }

    public function render(array $res, array $course): string
    {
        $f = $this->file($res);
        if (!$f) {
            return '<div class="alert alert-warn">Falta el archivo.</div>';
        }
        $html = '<div class="audio-box card">'
            . '<audio controls preload="metadata" src="' . e(Files::url($f)) . '"' . (empty($res['data']['allow_download']) ? ' controlsList="nodownload"' : '') . '></audio>'
            . '<div class="audio-speed" data-audio-speed><span class="muted small">Velocidad</span>'
            . '<button type="button" data-rate="0.75">0,75×</button><button type="button" data-rate="1" class="active">1×</button><button type="button" data-rate="1.25">1,25×</button></div>';
        if (!empty($res['data']['allow_download'])) {
            $html .= $this->downloadButton($f, 'Descargar audio');
        }
        $html .= '</div>';
        if (!empty($res['data']['transcript'])) {
            $html .= '<details class="card transcript"><summary>' . icon('align-left') . ' Ver transcripción</summary><div class="prose">' . $res['data']['transcript'] . '</div></details>';
        }
        return $html;
    }
}

class VideoResource extends FileResourceType
{
    public function id(): string { return 'video'; }
    public function label(): string { return 'Vídeo'; }
    public function icon(): string { return 'circle-play'; }
    public function help(): string { return 'Un vídeo de YouTube o Vimeo (pegando el enlace) o un archivo MP4.'; }
    protected function extensions(): array { return ['mp4', 'm4v', 'webm', 'mov']; }
    protected function fileRequired(array $res): bool { return false; }

    public function form(array $res): string
    {
        return '<div class="field"><label for="video_url">Enlace de YouTube o Vimeo</label>'
            . '<input type="url" id="video_url" name="video_url" value="' . e($res['data']['url'] ?? '') . '" placeholder="https://www.youtube.com/watch?v=…">'
            . '<small class="hint">O, en lugar del enlace, sube un archivo de vídeo:</small></div>'
            . parent::form($res);
    }

    public static function embedUrl(string $url): ?string
    {
        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0';
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }
        return null;
    }

    public function save(array $res, bool $isNew): array
    {
        $data = parent::save($res, $isNew);
        $url = p('video_url');
        if ($url !== '' && !self::embedUrl($url)) {
            throw new UserError('No reconozco ese enlace. Pega la dirección de un vídeo de YouTube o Vimeo.');
        }
        $data['url'] = $url;
        if ($url === '' && empty($data['file_id'])) {
            throw new UserError('Pega un enlace de YouTube/Vimeo o sube un archivo de vídeo.');
        }
        return $data;
    }

    public function meta(array $res): string
    {
        if (!empty($res['data']['url'])) {
            return str_contains($res['data']['url'], 'vimeo') ? 'Vimeo' : 'YouTube';
        }
        return parent::meta($res);
    }

    public function render(array $res, array $course): string
    {
        $embed = self::embedUrl((string)($res['data']['url'] ?? ''));
        if ($embed) {
            return '<div class="video-frame"><iframe src="' . e($embed) . '" title="' . e($res['title']) . '" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen loading="lazy"></iframe></div>';
        }
        $f = $this->file($res);
        if (!$f) {
            return '<div class="alert alert-warn">Falta el vídeo.</div>';
        }
        return '<div class="video-frame"><video controls preload="metadata" playsinline src="' . e(Files::url($f)) . '"></video></div>';
    }
}

class PageResource extends ResourceType
{
    public function id(): string { return 'page'; }
    public function label(): string { return 'Página de texto'; }
    public function icon(): string { return 'file-lines'; }
    public function help(): string { return 'Explicaciones, vocabulario, gramática… escritas directamente aquí.'; }

    public function form(array $res): string
    {
        return editor_field('content', (string)($res['data']['content'] ?? ''), 'Contenido');
    }

    public function save(array $res, bool $isNew): array
    {
        $content = Html::clean((string)($_POST['content'] ?? ''));
        if ($content === '') {
            throw new UserError('Escribe el contenido de la página.');
        }
        return ['content' => $content];
    }

    public function render(array $res, array $course): string
    {
        return '<div class="card prose page-content">' . ($res['data']['content'] ?? '') . '</div>';
    }
}

class LinkResource extends ResourceType
{
    public function id(): string { return 'link'; }
    public function label(): string { return 'Enlace web'; }
    public function icon(): string { return 'link'; }
    public function help(): string { return 'Una web externa: un ejercicio en línea, un artículo, un Genially, una lista de Spotify…'; }

    public function form(array $res): string
    {
        return '<div class="field"><label for="link_url">Dirección (URL)</label>'
            . '<input type="url" id="link_url" name="link_url" value="' . e($res['data']['url'] ?? '') . '" required placeholder="https://…">'
            . '<small class="hint">Spotify, Deezer, SoundCloud, YouTube, Vimeo, Google Docs/Slides/Forms/Drive y Canva se muestran con su propio reproductor o visor.</small></div>'
            . '<label class="check"><input type="checkbox" name="embed" value="1" ' . (!empty($res['data']['embed']) ? 'checked' : '') . '> Mostrar la web dentro del aula (solo si la web lo permite; si no, se abre en otra pestaña)</label>';
    }

    /**
     * Webs conocidas que tienen su propia versión para incrustar.
     * Devuelve [nombre, src, altura] (altura en px; 0 = formato 16:9; null = alto de página) o null.
     */
    public static function provider(string $url): ?array
    {
        if ($v = VideoResource::embedUrl($url)) {
            return [str_contains($url, 'vimeo') ? 'Vimeo' : 'YouTube', $v, 0];
        }
        if (preg_match('~open\.spotify\.com/(?:intl-[a-z-]+/)?(?:embed/)?(track|album|playlist|episode|show|artist)/([A-Za-z0-9]+)~i', $url, $m)) {
            $short = in_array($m[1], ['track', 'episode'], true);
            return ['Spotify', 'https://open.spotify.com/embed/' . $m[1] . '/' . $m[2], $short ? 152 : 452];
        }
        if (preg_match('~deezer\.com/(?:[a-z]{2}/)?(track|album|playlist|episode|show|artist)/(\d+)~i', $url, $m)) {
            return ['Deezer', 'https://widget.deezer.com/widget/auto/' . $m[1] . '/' . $m[2], $m[1] === 'track' ? 152 : 400];
        }
        if (preg_match('~^https?://(?:www\.|m\.)?soundcloud\.com/[^/?#]+/[^?#]+~i', $url, $m)) {
            return ['SoundCloud', 'https://w.soundcloud.com/player/?url=' . rawurlencode($m[0]), str_contains($m[0], '/sets/') ? 450 : 166];
        }
        if (preg_match('~docs\.google\.com/presentation/d/([\w-]+)~', $url, $m)) {
            return ['Google Slides', 'https://docs.google.com/presentation/d/' . $m[1] . '/embed', 0];
        }
        if (preg_match('~docs\.google\.com/(document|spreadsheets)/d/([\w-]+)~', $url, $m)) {
            return [$m[1] === 'document' ? 'Google Docs' : 'Google Sheets', 'https://docs.google.com/' . $m[1] . '/d/' . $m[2] . '/preview', null];
        }
        if (preg_match('~docs\.google\.com/forms/d/e/([\w-]+)~', $url, $m)) {
            return ['Google Forms', 'https://docs.google.com/forms/d/e/' . $m[1] . '/viewform?embedded=true', null];
        }
        if (preg_match('~drive\.google\.com/file/d/([\w-]+)~', $url, $m)) {
            return ['Google Drive', 'https://drive.google.com/file/d/' . $m[1] . '/preview', null];
        }
        if (preg_match('~canva\.com/design/([\w-]+)/([\w-]+)~', $url, $m)) {
            return ['Canva', 'https://www.canva.com/design/' . $m[1] . '/' . $m[2] . '/view?embed', 0];
        }
        return null;
    }

    /**
     * ¿La web prohíbe que se muestre dentro de otra (X-Frame-Options / frame-ancestors)?
     * Si no se puede comprobar, se da por buena.
     */
    private static function blocksFraming(string $url): bool
    {
        if (!function_exists('curl_init')) {
            return false;
        }
        $headers = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36',
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$headers) {
                if (str_starts_with($line, 'HTTP/')) {
                    $headers = []; // nueva respuesta (redirección): solo cuenta la última
                } elseif (($p = strpos($line, ':')) !== false) {
                    $headers[strtolower(trim(substr($line, 0, $p)))][] = trim(substr($line, $p + 1));
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => fn($ch, $data) => 0, // basta con las cabeceras: corta la descarga
        ]);
        curl_exec($ch);

        foreach ($headers['content-security-policy'] ?? [] as $csp) {
            if (preg_match('~frame-ancestors([^;]*)~i', $csp, $m)) {
                $host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
                foreach (preg_split('~\s+~', trim($m[1])) as $src) {
                    $src = strtolower(trim($src, "'"));
                    if (in_array($src, ['*', 'https:', 'http:'], true)) {
                        return false;
                    }
                    $h = (string)(parse_url(str_contains($src, '://') ? $src : 'https://' . $src, PHP_URL_HOST) ?: '');
                    if ($h !== '' && $host !== '' && ($h === $host || (str_starts_with($h, '*.') && str_ends_with($host, substr($h, 1))))) {
                        return false;
                    }
                }
                return true;
            }
        }
        foreach ($headers['x-frame-options'] ?? [] as $xfo) {
            if (preg_match('~deny|sameorigin~i', $xfo)) {
                return true;
            }
        }
        return false;
    }

    public function save(array $res, bool $isNew): array
    {
        $url = p('link_url');
        if (!preg_match('~^https?://~i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new UserError('Escribe una dirección completa que empiece por https://');
        }
        $embed = !empty($_POST['embed']);
        if ($embed && !self::provider($url) && self::blocksFraming($url)) {
            $embed = false;
            flash('info', 'La web ' . parse_url($url, PHP_URL_HOST) . ' no permite mostrarse dentro de otra página, así que se abrirá en otra pestaña.');
        }
        return ['url' => $url, 'embed' => $embed];
    }

    public function redirect(array $res): ?string
    {
        return empty($res['data']['embed']) ? ($res['data']['url'] ?? null) : null;
    }

    public function opensNewTab(array $res): bool
    {
        return empty($res['data']['embed']);
    }

    public function meta(array $res): string
    {
        $url = (string)($res['data']['url'] ?? '');
        return self::provider($url)[0] ?? (string)(parse_url($url, PHP_URL_HOST) ?: '');
    }

    public function render(array $res, array $course): string
    {
        $url = (string)($res['data']['url'] ?? '');
        $open = '<p><a class="btn btn-ghost" href="' . e($url) . '" target="_blank" rel="noopener">' . icon('up-right-from-square') . ' Abrir en otra pestaña</a></p>';
        if (empty($res['data']['embed'])) {
            return $open;
        }
        $title = e($res['title']);
        if ($p = self::provider($url)) {
            [, $src, $height] = $p;
            $attrs = ' src="' . e($src) . '" title="' . $title . '" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"';
            if ($height === 0) {
                return $open . '<div class="video-frame"><iframe' . $attrs . '></iframe></div>';
            }
            if ($height) {
                return $open . '<div class="embed-player"><iframe' . $attrs . ' style="height:' . (int)$height . 'px"></iframe></div>';
            }
            return $open . '<div class="embed-frame"><iframe' . $attrs . '></iframe></div>';
        }
        return $open
            . '<div class="embed-frame"><iframe src="' . e($url) . '" title="' . $title . '" loading="lazy" referrerpolicy="no-referrer"></iframe></div>'
            . '<p class="muted small pdf-hint">¿No se ve la web? Usa «Abrir en otra pestaña».</p>';
    }
}

class FileDownloadResource extends FileResourceType
{
    public function id(): string { return 'file'; }
    public function label(): string { return 'Archivo descargable'; }
    public function icon(): string { return 'file-arrow-down'; }
    public function help(): string { return 'Imágenes y fichas (se ven en grande), Word, PowerPoint, Excel, ZIP… para descargar.'; }

    protected function extensions(): array
    {
        return ['doc', 'docx', 'odt', 'rtf', 'txt', 'ppt', 'pptx', 'odp', 'xls', 'xlsx', 'ods', 'csv', 'zip', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'mp3', 'mp4', 'epub'];
    }

    public function render(array $res, array $course): string
    {
        $f = $this->file($res);
        if (!$f) {
            return '<div class="alert alert-warn">Falta el archivo.</div>';
        }
        if (str_starts_with($f['mime'], 'image/')) {
            $url = Files::url($f);
            return '<div class="btn-row">'
                . '<a class="btn btn-primary" href="' . e($url) . '" data-lightbox>' . icon('magnifying-glass-plus') . ' Ver en grande</a>'
                . $this->downloadButton($f) . '</div>'
                . '<figure class="image-view"><a href="' . e($url) . '" data-lightbox title="Ver en grande">'
                . '<img src="' . e($url) . '" alt="' . e($res['title']) . '"></a></figure>'
                . '<p class="muted small image-hint">' . icon('circle-info') . ' Pulsa la imagen para verla a pantalla completa; pulsa otra vez para ampliarla.</p>';
        }
        return '<div class="card file-box">'
            . '<p><span class="res-icon res-icon-file">' . icon('file-arrow-down') . '</span> <strong>' . e($f['name']) . '</strong> <span class="muted">' . fmt_size((int)$f['size']) . '</span></p>'
            . '<a class="btn btn-primary" href="' . e(Files::url($f, true)) . '">' . icon('download') . ' Descargar</a></div>';
    }
}

ResourceTypes::register(new PdfResource());
ResourceTypes::register(new AudioResource());
ResourceTypes::register(new VideoResource());
ResourceTypes::register(new PageResource());
ResourceTypes::register(new LinkResource());
ResourceTypes::register(new FileDownloadResource());
