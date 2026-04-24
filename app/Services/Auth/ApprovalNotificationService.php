<?php
// 경로: PROJECT_ROOT . '/app/Services/Auth/ApprovalNotificationService.php'
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

    /* ---------------------------------------------------------
     * 승인 안내 메일 발송
     * --------------------------------------------------------- */
    public function sendApprovalMail(string $adminEmail, array $user, string $token): void
    {
        $this->logger->info('sendApprovalMail 시작', [
            'admin' => $adminEmail,
            'user'  => $user['id'] ?? null,
        ]);

        $baseUrl = rtrim((string)ConfigHelper::get('App.BaseUrl', ''), '/');
        if ($baseUrl === '') {
            throw new \RuntimeException('App.BaseUrl is not configured');
        }

        $url = $baseUrl
             . '/auth/approval/request?'
             . 'code=' . urlencode($user['code'])
             . '&approve_token=' . urlencode($token);

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
                'admin' => $adminEmail,
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('sendApprovalMail 실패', [
                'admin' => $adminEmail,
                'error' => $e->getMessage(),
            ]);
        }
    }

}
