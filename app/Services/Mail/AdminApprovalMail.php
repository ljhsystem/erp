<?php
declare(strict_types=1);
namespace App\Services\Mail;

use Core\Helpers\ConfigHelper;

class AdminApprovalMail
{
    private Mailer $mailer;
    private string $username = '';
    private string $employeeName = '';
    private string $userEmail = '';
    private string $userId = '';

    public function __construct(Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    public function send(array $data): array
    {
        $this->username     = trim((string)($data['username'] ?? $data['user_name'] ?? ''));
        $this->employeeName = trim((string)($data['employee_name'] ?? $data['employeeName'] ?? ''));
        $this->userEmail    = trim((string)($data['user_email'] ?? $data['email'] ?? ''));
        $this->userId       = trim((string)($data['user_id'] ?? $data['userId'] ?? ''));

        if ($this->userId === '') {
            throw new \InvalidArgumentException('AdminApprovalMail requires user_id.');
        }

        $content = $this->build();

        $result = $this->mailer->sendToAdmin(
            $content['subject'],
            $content['html'],
            $content['text'],
            Mailer::SENDER_SUKHYANG_APP_ADMIN
        );

        return $result;
    }

    public function build(): array
    {
        $baseUrl = rtrim((string)ConfigHelper::get('App.BaseUrl', ''), '/');
        if ($baseUrl === '') {
            throw new \RuntimeException('App.BaseUrl is not configured');
        }

        $secret = $this->loadAppSecret();

        $adminEmail = $this->mailer->getAdminEmail() ?: $this->userEmail;

        $token = '';
        if ($secret !== '') {
            try {
                $tokenPayload = [
                    'admin'     => $adminEmail,
                    'issued_at' => time(),
                    'user_id'   => $this->userId,
                ];

                $token = MailToken::create(
                    $tokenPayload,
                    $secret,
                    24 * 3600
                );

            } catch (\Throwable $e) {
                $token = '';
            }
        } else {
        }

        $approveUrl = sprintf(
            '%s/auth/approval/request?approve_token=%s',
            $baseUrl,
            urlencode($token)
        );

        $subject = '[ERP] 신규 회원가입 승인 요청';
        $html = "<meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>"
              . "<h3>신규 회원가입 요청</h3>"
              . "<p><b>아이디:</b> " . htmlspecialchars($this->username, ENT_QUOTES) . "</p>"
              . "<p><b>이름:</b> " . htmlspecialchars($this->employeeName, ENT_QUOTES) . "</p>"
              . "<p><a href='" . htmlspecialchars($approveUrl, ENT_QUOTES) . "' "
              . "style='display:inline-block;padding:10px 16px;background:#0d6efd;color:#fff;"
              . "text-decoration:none;border-radius:6px;'>승인하러 가기</a></p>";

        $text = "신규 회원가입 요청\n"
              . "아이디: {$this->username}\n"
              . "이름: {$this->employeeName}\n"
              . "승인 링크: {$approveUrl}";

        return ['subject' => $subject, 'html' => $html, 'text' => $text];
    }

    private function loadAppSecret(): string
    {
        try {
            return (new \Core\Security\SecretResolver())->resolve('ERP_APP_MAIN', 'secret');
        } catch (\Core\Security\SecretResolutionException) {
            return '';
        }
    }
}
