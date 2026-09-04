<?php
namespace App\Services\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use Core\Security\SecretResolutionException;
use Core\Security\SecretResolver;

class Mailer
{
    public const SENDER_SUKHYANG_APP_ADMIN = 'SUKHYANG_APP_ADMIN';
    public const SENDER_SUKHYANG_REPRESENTATIVE = 'SUKHYANG_REPRESENTATIVE';

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
        return trim((string) ($this->smtp['AdminEmail'] ?? ''));
    }

    private function createMailer(
        array $transport,
        array $senderProfile,
        string $password,
        ?string $replyToEmail = null,
        ?string $replyToName = null
    ): PHPMailer
    {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = $transport['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $transport['username'];
        $mail->Password   = $password;
        $mail->SMTPSecure = $transport['smtp_secure'];
        $mail->Port       = $transport['port'];

        $mail->CharSet  = 'UTF-8';
        $mail->Encoding = 'base64';

        $mail->setFrom($senderProfile['sender_email'], $senderProfile['sender_name']);
        $mail->addReplyTo(
            $replyToEmail ?? $senderProfile['reply_to_email'],
            $replyToName ?? $senderProfile['sender_name']
        );

        return $mail;
    }


    public function send(
        string $to,
        string $subject,
        string $html,
        string $text,
        string $senderProfileCode,
        ?string $replyToEmail = null,
        ?string $replyToName = null
    ): array
    {
        $occurredAt = date(DATE_ATOM);
        $recipientDomain = $this->recipientDomain($to);
        $provider = 'SMTP';
        $requestId = UuidHelper::generate();

        $envelopeResult = $this->resolveSenderEnvelope(
            $senderProfileCode,
            $replyToEmail,
            $replyToName
        );
        if (empty($envelopeResult['success'])) {
            return $this->loggedResult($this->failureResult(
                (string) ($envelopeResult['error_code'] ?? 'SENDER_PROFILE_INVALID'),
                false,
                $provider,
                $recipientDomain,
                $occurredAt,
                $requestId,
                $senderProfileCode
            ));
        }
        $senderProfile = $envelopeResult['envelope'];
        $transportCode = $senderProfile['transport_code'];
        $transportResult = $this->resolveTransport($transportCode);
        if (empty($transportResult['success'])) {
            return $this->loggedResult($this->failureResult(
                (string) ($transportResult['error_code'] ?? 'SMTP_TRANSPORT_INVALID'),
                false,
                $provider,
                $recipientDomain,
                $occurredAt,
                $requestId,
                $senderProfileCode,
                $transportCode
            ));
        }
        $transport = $transportResult['transport'];
        $provider = $this->provider($transport['host']);

        try {
            $password = (new SecretResolver())->resolve($transport['credential_code'], 'password');
        } catch (SecretResolutionException) {
            $password = '';
        }
        if ($password === '') {
            return $this->loggedResult($this->failureResult(
                'SMTP_SECRET_NOT_CONFIGURED',
                false,
                $provider,
                $recipientDomain,
                $occurredAt,
                $requestId,
                $senderProfileCode,
                $transportCode
            ));
        }

        try {
            $mail = $this->createMailer($transport, $senderProfile, $password, $replyToEmail, $replyToName);

            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = $text ?: strip_tags($html);
            $mail->isHTML(true);

            $mail->send();
            return $this->loggedResult([
                'success' => true,
                'sent' => true,
                'delivery_status' => 'ACCEPTED',
                'provider' => $provider,
                'recipient_domain' => $recipientDomain,
                'message_id' => (string) $mail->getLastMessageID(),
                'error_code' => null,
                'retryable' => false,
                'occurred_at' => $occurredAt,
                'request_id' => $requestId,
                'sender_profile_code' => $senderProfileCode,
                'transport_code' => $transportCode,
            ]);
        } catch (Exception $e) {
            $errorCode = $this->classifyError($e->getMessage());
            $result = $this->failureResult(
                $errorCode,
                in_array($errorCode, ['SMTP_CONNECTION_FAILED', 'SMTP_TIMEOUT'], true),
                $provider,
                $recipientDomain,
                $occurredAt,
                $requestId,
                $senderProfileCode,
                $transportCode
            );
            return $this->loggedResult($result);
        }
    }

    private function failureResult(
        string $errorCode,
        bool $retryable,
        string $provider,
        string $recipientDomain,
        string $occurredAt,
        string $requestId,
        string $senderProfileCode,
        ?string $transportCode = null
    ): array {
        return [
            'success' => false,
            'sent' => false,
            'delivery_status' => 'FAILED',
            'provider' => $provider,
            'recipient_domain' => $recipientDomain,
            'message_id' => null,
            'error_code' => $errorCode,
            'retryable' => $retryable,
            'occurred_at' => $occurredAt,
            'request_id' => $requestId,
            'sender_profile_code' => $senderProfileCode,
            'transport_code' => $transportCode,
        ];
    }

    private function loggedResult(array $result): array
    {
        $success = !empty($result['success']);
        $retryable = !empty($result['retryable']);
        $outcome = $success ? 'SUCCESS' : ($retryable ? 'FAILED' : 'BLOCKED');
        $level = $success ? 'info' : ($retryable ? 'error' : 'warning');
        $this->logger->{$level}($success ? '메일 발송을 완료했습니다.' : ($retryable ? '메일 발송에 실패했습니다.' : '메일 발송이 차단되었습니다.'), [
            'event_code' => 'MAIL_SEND_' . $outcome,
            'result' => $outcome,
            'service' => self::class,
            'action' => 'send',
            'request_id' => $result['request_id'] ?? null,
            'error_code' => $result['error_code'] ?? null,
            'provider' => $result['provider'] ?? null,
            'recipient_domain' => $result['recipient_domain'] ?? null,
            'sender_profile_code' => $result['sender_profile_code'] ?? null,
            'transport_code' => $result['transport_code'] ?? null,
        ]);
        return $result;
    }

    private function classifyError(string $message): string
    {
        $normalized = strtolower($message);
        if (str_contains($normalized, 'authenticate')) {
            return 'SMTP_AUTHENTICATION_FAILED';
        }
        if (str_contains($normalized, 'sender address rejected')
            || str_contains($normalized, 'from address rejected')
            || str_contains($normalized, 'sender identity')) {
            return 'SENDER_IDENTITY_REJECTED';
        }
        if (str_contains($normalized, 'recipient') || str_contains($normalized, 'address rejected')) {
            return 'RECIPIENT_REJECTED';
        }
        if (str_contains($normalized, 'timed out') || str_contains($normalized, 'timeout')) {
            return 'SMTP_TIMEOUT';
        }
        if (str_contains($normalized, 'connect') || str_contains($normalized, 'connection')) {
            return 'SMTP_CONNECTION_FAILED';
        }
        return 'SMTP_SEND_FAILED';
    }

    private function provider(string $host): string
    {
        $normalized = strtolower(trim($host));
        if (str_contains($normalized, 'gmail')) {
            return 'GMAIL';
        }
        return str_contains($normalized, 'daum') ? 'DAUM' : 'SMTP';
    }

    private function recipientDomain(string $recipient): string
    {
        $position = strrpos($recipient, '@');
        return $position === false ? '' : strtolower(substr($recipient, $position + 1));
    }

    private function resolveSenderProfile(string $senderProfileCode): array
    {
        $code = trim($senderProfileCode);
        if ($code === '') {
            return ['success' => false, 'error_code' => 'SENDER_PROFILE_REQUIRED'];
        }

        $profiles = $this->smtp['SenderProfiles'] ?? null;
        if (!is_array($profiles) || !isset($profiles[$code]) || !is_array($profiles[$code])) {
            return ['success' => false, 'error_code' => 'SENDER_PROFILE_INVALID'];
        }

        $profile = $profiles[$code];
        $transportCode = trim((string) ($profile['TransportCode'] ?? ''));
        $senderName = trim((string) ($profile['SenderName'] ?? ''));
        $senderEmail = trim((string) ($profile['SenderEmail'] ?? ''));
        $replyToEmail = trim((string) ($profile['ReplyToEmail'] ?? ''));
        if ($transportCode === ''
            || $senderName === ''
            || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)
            || !filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error_code' => 'SENDER_PROFILE_INCOMPLETE'];
        }

        return [
            'success' => true,
            'profile' => [
                'sender_name' => $senderName,
                'sender_email' => $senderEmail,
                'reply_to_email' => $replyToEmail,
                'transport_code' => $transportCode,
            ],
        ];
    }

    private function resolveTransport(string $transportCode): array
    {
        $code = trim($transportCode);
        if ($code === '') {
            return ['success' => false, 'error_code' => 'SMTP_TRANSPORT_REQUIRED'];
        }

        $transports = $this->smtp['Transports'] ?? null;
        if (!is_array($transports) || !isset($transports[$code]) || !is_array($transports[$code])) {
            return ['success' => false, 'error_code' => 'SMTP_TRANSPORT_INVALID'];
        }

        $transport = $transports[$code];
        $host = trim((string) ($transport['Host'] ?? ''));
        $port = (int) ($transport['Port'] ?? 0);
        $smtpSecure = strtolower(trim((string) ($transport['SMTPSecure'] ?? '')));
        $username = trim((string) ($transport['UserName'] ?? ''));
        $credentialCode = trim((string) ($transport['CredentialCode'] ?? ''));
        if ($host === '' || $port < 1 || !in_array($smtpSecure, ['ssl', 'tls'], true)
            || $username === '' || $credentialCode === '') {
            return ['success' => false, 'error_code' => 'SMTP_TRANSPORT_INCOMPLETE'];
        }
        if ($credentialCode !== $code) {
            return ['success' => false, 'error_code' => 'SMTP_TRANSPORT_CREDENTIAL_MISMATCH'];
        }

        return [
            'success' => true,
            'transport' => [
                'code' => $code,
                'host' => $host,
                'port' => $port,
                'smtp_secure' => $smtpSecure,
                'username' => $username,
                'credential_code' => $credentialCode,
            ],
        ];
    }

    private function resolveSenderEnvelope(
        string $senderProfileCode,
        ?string $replyToEmail = null,
        ?string $replyToName = null
    ): array {
        $profileResult = $this->resolveSenderProfile($senderProfileCode);
        if (empty($profileResult['success'])) {
            return $profileResult;
        }
        if ($replyToEmail !== null && !filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error_code' => 'REPLY_TO_INVALID'];
        }

        $profile = $profileResult['profile'];
        $profile['reply_to_email'] = $replyToEmail ?? $profile['reply_to_email'];
        $profile['reply_to_name'] = $replyToName ?? $profile['sender_name'];

        return ['success' => true, 'envelope' => $profile];
    }

    public function sendToAdmin(
        string $subject,
        string $html,
        string $text,
        string $senderProfileCode,
        ?string $replyToEmail = null,
        ?string $replyToName = null
    ): array
    {
        $admin = $this->getAdminEmail();
        if (empty($admin)) {
            return $this->loggedResult($this->failureResult(
                'ADMIN_RECIPIENT_MISSING',
                false,
                'SMTP',
                '',
                date(DATE_ATOM),
                UuidHelper::generate(),
                $senderProfileCode
            ));
        }
        return $this->send(
            $admin,
            $subject,
            $html,
            $text,
            $senderProfileCode,
            $replyToEmail,
            $replyToName
        );
    }
}
