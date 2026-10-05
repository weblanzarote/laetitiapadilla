<?php
defined('AULA') || exit;

/**
 * Envío de correo en texto plano.
 * Una extensión puede encargarse del envío (p. ej. por SMTP) usando el
 * filtro 'mail_send': si devuelve true/false, se usa ese resultado.
 */
class Mailer
{
    public static function send(string $to, string $subject, string $body, ?string $replyTo = null): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $subject = str_replace(["\r", "\n"], ' ', $subject);
        $siteName = (string)setting('site_name', 'Aula virtual');
        $body = rtrim($body) . "\n\n-- \n" . $siteName . "\n" . App::$config['site_url'] . "\n";

        $handled = apply_filters('mail_send', null, $to, $subject, $body, $replyTo);
        if (is_bool($handled)) {
            return $handled;
        }

        if (!App::$config['mail_enabled']) {
            $log = "==== " . date('c') . "\nPara: $to\nAsunto: $subject\n\n$body\n";
            file_put_contents(App::$dataDir . '/mail.log', $log, FILE_APPEND | LOCK_EX);
            return true;
        }

        $from = (string)App::$config['mail_from'];
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'From: ' . mb_encode_mimeheader($siteName, 'UTF-8', 'B') . ' <' . $from . '>',
        ];
        if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        return @mail($to, mb_encode_mimeheader($subject, 'UTF-8', 'B'), $body, implode("\r\n", $headers), '-f' . $from);
    }

    /** Correo a todas las cuentas de administración/profesorado. */
    public static function toTeachers(string $subject, string $body): void
    {
        foreach (Db::col("SELECT email FROM users WHERE role IN ('admin', 'teacher') AND status = 'active'") as $email) {
            self::send($email, $subject, $body);
        }
    }
}
