<?php
namespace App\Services\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Core\LoggerFactory;

class Mailer
{
    private array $smtp;
    private $logger;

    public function __construct()
    {
        $configFile = PROJECT_ROOT . '/config/appsetting.json';
        $this->smtp = [];

        if (file_exists($configFile)) {
            $raw = file_get_contents($configFile);

            $raw = preg_replace('#^\s*//.*$#m', '', $raw);
            $raw = preg_replace('#/\*.*?\*/#s', '', $raw);

            $decoded = json_decode($raw, true);
            $this->smtp = $decoded['SmtpSettings'] ?? [];
        }
        $this->logger = LoggerFactory::getLogger('service-mail.Mailer');
    }

    public function getAdminEmail(): string
    {
        return $this->smtp['AdminEmail'] ?? $this->smtp['UserName'] ?? '';
    }

    private function createMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = $this->smtp['Host'] ?? '';
        $mail->SMTPAuth   = !empty($this->smtp['UserName']) && !empty($this->smtp['Password']);
        $mail->Username   = $this->smtp['UserName'] ?? ($this->smtp['FromEmail'] ?? '');
        $mail->Password   = $this->smtp['Password'] ?? '';
        $mail->SMTPSecure = $this->smtp['SMTPSecure'] ?? 'tls';
        $mail->Port       = $this->smtp['Port'] ?? 587;

        $mail->CharSet  = 'UTF-8';
        $mail->Encoding = 'base64';

        $mailFromEmail = $this->smtp['FromEmail'] ?? $this->smtp['UserName'] ?? '';
        $mailFromName  = $this->smtp['FromName'] ?? $this->smtp['SenderName'] ?? 'SUHKYANG ERP';

        if ($mailFromEmail !== '') {
            $mail->setFrom($mailFromEmail, $mailFromName);
        }

        return $mail;
    }


    public function send(string $to, string $subject, string $html, string $text = '', string $fromName = '', string $fromEmail = ''): array
    {
        try {
            $mail = $this->createMailer();

            $configuredFromEmail = $this->smtp['FromEmail'] ?? $this->smtp['UserName'] ?? '';
            $configuredFromName  = $this->smtp['FromName'] ?? $this->smtp['SenderName'] ?? 'SUHKYANG ERP';

            if (!empty($fromEmail)) {
                if (filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                    $mail->addReplyTo($fromEmail, $fromName ?: $configuredFromName);
                }
            }

            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = $text ?: strip_tags($html);
            $mail->isHTML(true);

            $mail->send();
            return ['sent' => 1, 'status' => 'sent', 'method' => 'phpmailer'];
        } catch (Exception $e) {
            @file_put_contents(PROJECT_ROOT . '/storage/logs/mail_debug.log',
                date('c') . " | Mailer::send phpmailer_failed: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            return ['sent' => 0, 'status' => $e->getMessage()];
        }
    }

    public function sendToAdmin(string $subject, string $html, string $text = '', string $fromName = '', string $fromEmail = ''): array
    {
        $admin = $this->getAdminEmail();
        if (empty($admin)) {
            return ['sent' => 0, 'status' => 'missing_admin'];
        }
        return $this->send($admin, $subject, $html, $text, $fromName, $fromEmail);
    }
}
