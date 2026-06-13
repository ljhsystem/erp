<?php
declare(strict_types=1);
namespace App\Services\Mail;

use Core\Helpers\ConfigHelper;
use Core\LoggerFactory;

class AdminApprovalMail
{
    private Mailer $mailer;
    private string $username = '';
    private string $employeeName = '';
    private string $userEmail = '';
    private string $userId = '';

    private $logger;

    public function __construct(Mailer $mailer)
    {
        $this->mailer = $mailer;
        $this->logger = LoggerFactory::getLogger('service-mail.AdminApprovalMail');
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

        $this->logger->info('관리자 승인 메일 발송 시도', [
            'username'      => $this->username,
            'employee_name' => $this->employeeName,
            'user_email'    => $this->userEmail,
            'user_id'       => $this->userId,
        ]);

        $content = $this->build();

        $result = $this->mailer->sendToAdmin($content['subject'], $content['html'], $content['text']);

        $this->logger->info('관리자 승인 메일 발송 결과', [
            'username'  => $this->username,
            'sent'      => $result['sent'] ?? null,
            'status'    => $result['status'] ?? null
        ]);

        return $result;
    }

    public function build(): array
    {
        $baseUrl = rtrim((string)ConfigHelper::get('App.BaseUrl', ''), '/');
        if ($baseUrl === '') {
            throw new \RuntimeException('App.BaseUrl is not configured');
        }

        $secret = $this->loadAppSecret();

        $this->logger->info('AdminApprovalMail: secret loaded', [
            'has_secret' => $secret !== ''
        ]);

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

                $this->logger->info('AdminApprovalMail: token created', [
                    'short' => substr($token, 0, 16)
                ]);
            } catch (\Throwable $e) {

                $this->log("MailToken::create 실패: " . $e->getMessage());
                $this->logger->error('관리자 승인 메일 토큰 생성 실패', [
                    'username'      => $this->username,
                    'user_id'       => $this->userId,
                    'error'         => $e->getMessage()
                ]);
                $token = '';
            }
        } else {

            $this->log("시크릿 없음 - 토큰 미생성");
            $this->logger->warning('관리자 승인 메일 토큰 미생성 - 시크릿 없음', [
                'username'      => $this->username,
                'user_id'       => $this->userId
            ]);
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

        if (\defined('APP_SECRET')) {
            $val = \constant('APP_SECRET');
            if (is_string($val) && $val !== '') {
                return $val;
            }
        }

        $configFile = PROJECT_ROOT . '/config/appsetting.json';
        if (!file_exists($configFile)) {
            $this->log("appsetting.json 없음");
            $this->logger->warning('AdminApprovalMail: appsetting.json 없음');
            return '';
        }

        $raw = file_get_contents($configFile);

        $raw = preg_replace('#^\s*//.*$#m', '', $raw);
        $raw = preg_replace('#/\*.*?\*/#s', '', $raw);

        $cfg = json_decode($raw, true);

        if (!is_array($cfg)) {
            $this->log("appsetting.json 파싱 실패");
            $this->logger->error('AdminApprovalMail: appsetting.json 파싱 실패');
            return '';
        }

        if (!empty($cfg['AppSecret'])) {
            return (string)$cfg['AppSecret'];
        }

        if (!empty($cfg['InternalApiSecret'])) {
            $this->log("AppSecret 없음 — InternalApiSecret 사용");
            $this->logger->warning('AdminApprovalMail: AppSecret 없음, InternalApiSecret 사용');
            return (string)$cfg['InternalApiSecret'];
        }

        $this->logger->warning('AdminApprovalMail: AppSecret/InternalApiSecret 모두 없음');
        return '';
    }

    private function log(string $msg): void
    {
        @file_put_contents(
            PROJECT_ROOT . '/storage/logs/mail_debug.log',
            date('c') . " | AdminApprovalMail | " . $msg . PHP_EOL,
            FILE_APPEND
        );
    }
}
