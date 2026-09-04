<?php
namespace App\Services\Auth;

use PDO;
use Core\Helpers\ConfigHelper;
use Core\LoggerFactory;

class ApprovalNotificationService
{
    private readonly PDO $pdo;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo    = $pdo;
        $this->logger = LoggerFactory::getLogger('service-auth.ApprovalNotificationService');
    }

    public function sendApprovalMail(string $adminEmail, array $user, string $token): void
    {
        $baseUrl = rtrim((string)ConfigHelper::get('App.BaseUrl', ''), '/');
        if ($baseUrl === '') {
            $this->logger->warning('계정승인 메일 발송이 차단되었습니다.', [
                'event_code' => 'ACCOUNT_APPROVAL_MAIL_BLOCKED',
                'result' => 'BLOCKED',
                'user_id' => $user['id'] ?? null,
                'reason_code' => 'BASE_URL_NOT_CONFIGURED',
            ]);
            throw new \RuntimeException('App.BaseUrl is not configured');
        }

        $url = $baseUrl
             . '/auth/approval/request?approve_token=' . urlencode($token);

        $payload = [
            'to'      => $adminEmail,
            'subject' => '[ERP] 신규 회원 승인 요청',
            'body'    =>
                "<b>신규 회원가입 승인 요청</b><br>
                직원명: {$user['employee_name']}<br>
                아이디: {$user['username']}<br><br>
                <a href='{$url}' target='_blank'>승인하러 가기</a>",
        ];

        // ✅ 공용 Secret (단일 진입점)
        $secret = ConfigHelper::secret();

        try {
            $ch = curl_init($baseUrl . '/public/api/smtp/mailer_api.php');

            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'X-Internal-Token: ' . $secret,
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
            ]);

            $response = curl_exec($ch);

            if ($response === false) {
                throw new \RuntimeException(curl_error($ch));
            }

            //curl_close($ch);

            $this->logger->info('승인 메일 발송 성공', [
                'event_code' => 'ACCOUNT_APPROVAL_MAIL_SENT',
                'result' => 'SUCCESS',
                'user_id' => $user['id'] ?? null,
                'recipient_domain' => str_contains($adminEmail, '@') ? substr(strrchr($adminEmail, '@'), 1) : '',
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('계정승인 메일 발송에 실패했습니다.', [
                'event_code' => 'ACCOUNT_APPROVAL_MAIL_FAILED',
                'result' => 'FAILED',
                'user_id' => $user['id'] ?? null,
                'recipient_domain' => str_contains($adminEmail, '@') ? substr(strrchr($adminEmail, '@'), 1) : '',
                'error_code' => get_class($e),
                'error' => $e,
            ]);
        }
    }

}
