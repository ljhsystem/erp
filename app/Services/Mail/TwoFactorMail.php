<?php
declare(strict_types=1);
namespace App\Services\Mail;


class TwoFactorMail
{
    private Mailer $mailer;
    private array $user = [];

    public function __construct(Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    public function send(array $data): array
    {
        $this->user = $data['user'] ?? $data;

        $content = $this->build();

        if (empty($content['to'])) {
            return [
                'success' => false,
                'sent' => false,
                'delivery_status' => 'FAILED',
                'provider' => 'SMTP',
                'recipient_domain' => '',
                'message_id' => null,
                'error_code' => 'SMTP_RECIPIENT_MISSING',
                'retryable' => false,
                'occurred_at' => date(DATE_ATOM),
            ];
        }

        $result = $this->mailer->send(
            $content['to'],
            $content['subject'],
            $content['html'],
            $content['text'],
            Mailer::SENDER_SUKHYANG_APP_ADMIN
        );

        return $result;
    }

    public function build(): array
    {
        $to = $this->user['email'] ?? '';

        $code = $this->user['two_factor_code'] ?? '';

        if ($code === '') {
        }

        $verifyUrl = "https://erp.sukhyang.com/auth/2fa?code=" . urlencode($code);

        $subject = '[ERP] 2단계 인증 안내';

        $html = "
        <meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>

        <h3>2단계 인증</h3>
        <p>아래 인증 코드를 입력하여 로그인 절차를 완료하세요.</p>

        <div style='font-size:26px;font-weight:bold;letter-spacing:6px;
                    padding:14px 20px;
                    border:1px solid #ddd;
                    background:#f8f9fa;
                    user-select: all; -webkit-user-select: all;
                    display:inline-block;'>
            " . htmlspecialchars($code, ENT_QUOTES) . "
        </div>

        <p style='font-size:12px;color:#666;margin-top:10px;'>
            * 코드 영역은 클릭하면 전체 선택됩니다.
        </p>
        ";

        $text = "2단계 인증 코드: {$code}\n\n"
               ."자동 인증 링크: {$verifyUrl}";

        return [
            'to'      => $to,
            'subject' => $subject,
            'html'    => $html,
            'text'    => $text,
        ];
    }

    public static function dispatch(array $user): array
    {
        $mailer = new Mailer();
        return (new self($mailer))->send(['user' => $user]);
    }

    private function recipientDomain(string $recipient): string
    {
        $position = strrpos($recipient, '@');
        return $position === false ? '' : strtolower(substr($recipient, $position + 1));
    }
}
