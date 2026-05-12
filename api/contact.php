<?php
declare(strict_types=1);

session_start();

// === Configuración ===
const CONTACT_TO_EMAIL = 'laetitiapadilla.fr@gmail.com';
const CONTACT_FROM_EMAIL = 'no-reply@laetitiapadilla.com'; // debe existir en tu dominio para mejor entregabilidad
const ENABLE_EMAIL_SEND = true; // si no tienes MTA/SMTP configurado aún, ponlo en false (igual se guardan los mensajes)

const STORAGE_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
const MAX_NAME_LEN = 100;
const MAX_EMAIL_LEN = 254;
const MAX_PHONE_LEN = 50;
const MAX_MESSAGE_LEN = 5000;
const MAX_UA_LEN = 300;

const RATE_LIMIT_PER_HOUR = 6;
const RATE_LIMIT_PER_DAY = 20;

// === Utilidades ===
function ensure_storage_dir(): void {
    if (!is_dir(STORAGE_DIR)) {
        @mkdir(STORAGE_DIR, 0700, true);
    }
    $htaccessPath = STORAGE_DIR . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($htaccessPath)) {
        @file_put_contents($htaccessPath, "Require all denied\nDeny from all\n", LOCK_EX);
    }
}

function client_ip(): string {
    // Preferimos REMOTE_ADDR para evitar spoofing. Si usas proxy/CDN, ajusta aquí.
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function is_ajax_request(): bool {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return stripos($accept, 'application/json') !== false || strtolower($xrw) === 'xmlhttprequest';
}

function json_response(int $status, array $payload): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function html_response(int $status, string $title, string $message): void {
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeMsg = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo "<!doctype html><html lang=\"es\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\"><title>{$safeTitle}</title></head><body style=\"font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:720px;margin:40px auto;padding:0 16px;\"><h1>{$safeTitle}</h1><p>{$safeMsg}</p><p><a href=\"../contacto.html\">Volver a contacto</a></p></body></html>";
    exit;
}

function normalize_str(?string $value): string {
    $v = trim((string)$value);
    $v = preg_replace("/\r\n|\r/", "\n", $v ?? '');
    return $v ?? '';
}

function clamp(string $value, int $maxLen): string {
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLen, 'UTF-8');
    }
    return substr($value, 0, $maxLen);
}

function get_post_value(string $key): string {
    return normalize_str($_POST[$key] ?? '');
}

function csrf_issue_token(): string {
    $token = bin2hex(random_bytes(32));
    $_SESSION['contact_csrf'] = [
        'token' => $token,
        'issued_at' => time(),
    ];
    return $token;
}

function csrf_validate(string $token): bool {
    if (!isset($_SESSION['contact_csrf']['token'])) return false;
    $expected = (string)($_SESSION['contact_csrf']['token'] ?? '');
    $issuedAt = (int)($_SESSION['contact_csrf']['issued_at'] ?? 0);
    if ($issuedAt <= 0) return false;
    if (time() - $issuedAt > 2 * 60 * 60) return false; // 2h
    return hash_equals($expected, $token);
}

function rate_limit_check_or_throw(string $ip): void {
    ensure_storage_dir();
    $key = hash('sha256', $ip);
    $path = STORAGE_DIR . DIRECTORY_SEPARATOR . "ratelimit_{$key}.json";
    $now = time();
    $hourAgo = $now - 3600;
    $dayAgo = $now - 86400;

    $data = ['hits' => []];
    if (is_file($path)) {
        $raw = @file_get_contents($path);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['hits']) && is_array($decoded['hits'])) {
                $data = $decoded;
            }
        }
    }

    $hits = array_values(array_filter($data['hits'], fn($t) => is_int($t) && $t > $dayAgo));
    $hitsLastHour = array_values(array_filter($hits, fn($t) => $t > $hourAgo));

    if (count($hitsLastHour) >= RATE_LIMIT_PER_HOUR || count($hits) >= RATE_LIMIT_PER_DAY) {
        throw new RuntimeException('Demasiados envíos. Prueba de nuevo en unos minutos.');
    }

    $hits[] = $now;
    $data['hits'] = $hits;
    @file_put_contents($path, json_encode($data), LOCK_EX);
}

function store_message(array $record): void {
    ensure_storage_dir();
    $ym = gmdate('Y-m');
    $path = STORAGE_DIR . DIRECTORY_SEPARATOR . "messages-{$ym}.jsonl";
    $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
}

function send_email(array $record): ?string {
    if (!ENABLE_EMAIL_SEND) return null;

    $name = $record['name'] ?? '';
    $email = $record['email'] ?? '';
    $phone = $record['phone'] ?? '';
    $interest = $record['interest'] ?? 'General';
    $message = $record['message'] ?? '';

    $subject = "[Web] {$interest} — {$name}";
    $subject = str_replace(["\r", "\n"], ' ', $subject);
    $subject = clamp($subject, 180);

    $body = "Nuevo mensaje desde la web\n\n"
        . "Nombre: {$name}\n"
        . "Email: {$email}\n"
        . "Teléfono: " . ($phone !== '' ? $phone : "—") . "\n"
        . "Interés: {$interest}\n"
        . "Fecha (UTC): " . ($record['ts_utc'] ?? '') . "\n"
        . "IP: " . ($record['ip'] ?? '') . "\n"
        . "\nMensaje:\n{$message}\n";

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'From: ' . CONTACT_FROM_EMAIL;
    $headers[] = 'Sender: ' . CONTACT_FROM_EMAIL;
    $headers[] = 'Reply-To: ' . $email;

    $ok = @mail(CONTACT_TO_EMAIL, $subject, $body, implode("\r\n", $headers), '-f' . CONTACT_FROM_EMAIL);
    if ($ok) return null;
    return 'No se pudo enviar el email desde el servidor (pero el mensaje quedó guardado).';
}

// === Router simple ===
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action === 'token') {
        $token = csrf_issue_token();
        json_response(200, ['ok' => true, 'token' => $token]);
    }
    html_response(404, 'No encontrado', 'Recurso no disponible.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    html_response(405, 'Método no permitido', 'Este endpoint solo acepta POST.');
}

try {
    // Honeypot (campo que deben dejar vacío los humanos)
    $company = get_post_value('company');
    if ($company !== '') {
        // Fingimos éxito para bots.
        if (is_ajax_request()) json_response(200, ['ok' => true]);
        html_response(200, 'Mensaje enviado', 'Gracias. He recibido tu mensaje.');
    }

    $csrf = get_post_value('csrf_token');
    if (!csrf_validate($csrf)) {
        throw new RuntimeException('Sesión caducada. Recarga la página y vuelve a enviar.');
    }

    $ip = client_ip();
    rate_limit_check_or_throw($ip);

    $name = clamp(get_post_value('nombre'), MAX_NAME_LEN);
    $email = clamp(get_post_value('email'), MAX_EMAIL_LEN);
    $phone = clamp(get_post_value('telefono'), MAX_PHONE_LEN);
    $interest = clamp(get_post_value('asunto'), 80);
    $message = clamp(get_post_value('mensaje'), MAX_MESSAGE_LEN);

    if ($name === '' || $email === '' || $message === '') {
        throw new RuntimeException('Faltan campos obligatorios.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('El email no parece válido.');
    }

    $allowedInterests = [
        'General',
        'Información general',
        'Clases de Francés',
        'Intérprete',
        'Acompañamiento cultural'
    ];
    if (!in_array($interest, $allowedInterests, true)) {
        $interest = 'General';
    }

    $ua = clamp((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), MAX_UA_LEN);

    $record = [
        'ts_utc' => gmdate('c'),
        'ip' => $ip,
        'ua' => $ua,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'interest' => $interest,
        'message' => $message,
    ];

    store_message($record);
    $emailWarning = send_email($record);

    // Rotamos token tras envío para evitar replays sencillos
    unset($_SESSION['contact_csrf']);

    if (is_ajax_request()) {
        json_response(200, ['ok' => true, 'warning' => $emailWarning]);
    }
    html_response(200, 'Mensaje enviado', 'Gracias. He recibido tu mensaje y te responderé lo antes posible.');
} catch (Throwable $e) {
    $msg = $e->getMessage();
    if (is_ajax_request()) {
        json_response(400, ['ok' => false, 'error' => $msg]);
    }
    html_response(400, 'No se pudo enviar', $msg);
}
