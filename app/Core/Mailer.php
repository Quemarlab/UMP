<?php
declare(strict_types=1);

namespace UMP\Core;

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

require_once UMP_ROOT . '/app/Vendor/phpmailer/src/Exception.php';
require_once UMP_ROOT . '/app/Vendor/phpmailer/src/PHPMailer.php';
require_once UMP_ROOT . '/app/Vendor/phpmailer/src/SMTP.php';

/** SMTP mailer driven by .env. Failures are logged, never shown to visitors. */
final class Mailer
{
    public static function configured(): bool
    {
        return Env::get('MAIL_USER') !== null && Env::get('MAIL_PASS') !== null;
    }

    /** Reused SMTP connection for bulk sending (see the 'reuse' option). */
    private static ?PHPMailer $shared = null;

    private static function transport(bool $keepAlive): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = Env::get('MAIL_HOST', 'smtp.gmail.com');
        $mail->SMTPAuth = true;
        $mail->Username = Env::get('MAIL_USER');
        $mail->Password = Env::get('MAIL_PASS');
        $enc = Env::get('MAIL_ENCRYPTION', 'ssl');
        if ($enc === 'none') {
            $mail->SMTPSecure = ''; // unencrypted: only for a local relay / test mail catcher
            $mail->SMTPAutoTLS = false;
        } else {
            $mail->SMTPSecure = $enc === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        }
        $mail->Port = (int) Env::get('MAIL_PORT', '465');
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 10;
        $mail->SMTPKeepAlive = $keepAlive;
        $mail->setFrom(Env::get('MAIL_FROM', Env::get('MAIL_USER')), Env::get('MAIL_FROM_NAME', setting('company_name')));
        return $mail;
    }

    /**
     * @param array{headers?:array<string,string>,reuse?:bool,heading?:?string,footer?:string} $opt
     *   headers: extra message headers (e.g. List-Unsubscribe); reuse: keep one SMTP connection open across calls
     *   (call Mailer::close() when done); heading: title inside the email (defaults to the subject); footer: extra HTML under the content.
     */
    public static function send(string $to, string $subject, string $htmlBody, ?string $replyTo = null, ?string $toName = null, array $opt = []): bool
    {
        if (!self::configured()) {
            error_log('Mailer: MAIL_USER / MAIL_PASS not set, skipping "' . $subject . '"');
            return false;
        }
        try {
            $reuse = !empty($opt['reuse']);
            if ($reuse && self::$shared !== null) {
                $mail = self::$shared;
                $mail->clearAllRecipients();
                $mail->clearReplyTos();
                $mail->clearCustomHeaders();
            } else {
                $mail = self::transport($reuse);
                if ($reuse) {
                    self::$shared = $mail;
                }
            }
            $mail->addAddress($to, $toName ?? '');
            if ($replyTo) {
                $mail->addReplyTo($replyTo);
            }
            foreach ($opt['headers'] ?? [] as $name => $value) {
                $mail->addCustomHeader($name, $value);
            }
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = self::layout($opt['heading'] ?? $subject, $htmlBody, $opt['footer'] ?? '');
            $mail->AltBody = trim(strip_tags(str_replace(['<br>', '</p>'], "\n", $htmlBody)));
            return $mail->send();
        } catch (MailException $e) {
            error_log('Mailer error: ' . $e->getMessage());
            if (self::$shared !== null) {
                self::close(); // drop a connection that may be in a bad state; the next send reconnects
            }
            return false;
        }
    }

    public static function close(): void
    {
        if (self::$shared !== null) {
            try {
                self::$shared->smtpClose();
            } catch (\Throwable) {
            }
            self::$shared = null;
        }
    }
    /** Branded wrapper shared by every transactional email. */
    private static function layout(string $title, string $content, string $footer = ''): string
    {
        $company = e(setting('company_name'));
        return '<!doctype html><html><body style="margin:0;background:#f3f4f1;font-family:Segoe UI,Arial,sans-serif;color:#24282b">'
            . '<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 12px">'
            . '<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:12px;overflow:hidden">'
            . '<tr><td style="background:#1f2326;padding:22px 28px;border-bottom:4px solid #d4a017;color:#fff;font-size:18px;font-weight:600">' . $company . '</td></tr>'
            . '<tr><td style="padding:28px;font-size:15px;line-height:1.65"><h2 style="margin:0 0 16px;font-size:20px">' . e($title) . '</h2>' . $content . $footer . '</td></tr>'
            . '<tr><td style="padding:18px 28px;background:#f8f8f6;color:#777;font-size:12px">&copy; ' . date('Y') . ' ' . $company . '. All rights reserved.</td></tr>'
            . '</table></td></tr></table></body></html>';
    }
}
