<?php
// 경로: PROJECT_ROOT . '/app/Services/Mail/AdminApprovalMail.php'
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
    private string $userCode = '';
    private string $userId = '';

    // 1. 로거 프로퍼티 추가
    private $logger;

    public function __construct(Mailer $mailer)
    {
        $this->mailer = $mailer;
        $this->logger = LoggerFactory::getLogger('service-mail.AdminApprovalMail');
    }

    /**
     * MailService 가 호출할 공용 엔트리
     * $data: ['username','employee_name','user_email','user_code','user_id']
     */
    public function send(array $data): array
    {
        $this->username     = trim((string)($data['username'] ?? $data['user_name'] ?? ''));
        $this->employeeName = trim((string)($data['employee_name'] ?? $data['employeeName'] ?? ''));
        $this->userEmail    = trim((string)($data['user_email'] ?? $data['email'] ?? ''));
        $this->userCode     = trim((string)($data['user_code'] ?? $data['userCode'] ?? ''));
        $this->userId       = trim((string)($data['user_id'] ?? $data['userId'] ?? ''));

        // 2. 메일 발송 시도 로그
        $this->logger->info('관리자 승인 메일 발송 시도', [
            'username'      => $this->username,
            'employee_name' => $this->employeeName,
            'user_email'    => $this->userEmail,
            'user_code'     => $this->userCode
        ]);

        $content = $this->build();

        $result = $this->mailer->sendToAdmin($content['subject'], $content['html'], $content['text']);

        // 3. 결과 로그
        $this->logger->info('관리자 승인 메일 발송 결과', [
            'username'  => $this->username,
            'sent'      => $result['sent'] ?? null,
            'status'    => $result['status'] ?? null
        ]);

        return $result;
    }

    // 기존 build() 로직을 재사용 (내부 필드 사용)
    public function build(): array
    {
        $baseUrl = rtrim((string)ConfigHelper::get('App.BaseUrl', ''), '/');
        if ($baseUrl === '') {
            throw new \RuntimeException('App.BaseUrl is not configured');
        }

        // 시크릿 로드 (APP_SECRET → AppSecret → InternalApiSecret)
        $secret = $this->loadAppSecret();
        // 디버그: 값 자체는 안 찍고 존재 여부만
        $this->logger->info('AdminApprovalMail: secret loaded', [
            'has_secret' => $secret !== ''
        ]);

        // 관리자 이메일 (설정 우선)
        $adminEmail = $this->mailer->getAdminEmail() ?: $this->userEmail;

        // 토큰 생성
        $token = '';
        if ($secret !== '') {
            try {
                $token = MailToken::create(
                    [
                        'admin'     => $adminEmail,
                        'user_code' => $this->userCode,
                        'issued_at' => time()
                    ],
                    $secret,
                    24 * 3600
                );
                // 토큰 앞부분만 로그
                $this->logger->info('AdminApprovalMail: token created', [
                    'short' => substr($token, 0, 16)
                ]);
            } catch (\Throwable $e) {
                // 4. 토큰 생성 실패 로그
                $this->log("MailToken::create 실패: " . $e->getMessage());
                $this->logger->error('관리자 승인 메일 토큰 생성 실패', [
                    'username'  => $this->username,
                    'user_code' => $this->userCode,
                    'error'     => $e->getMessage()
                ]);
                $token = '';
            }
        } else {
            // 5. 토큰 미생성 로그
            $this->log("시크릿 없음 - 토큰 미생성");
            $this->logger->warning('관리자 승인 메일 토큰 미생성 - 시크릿 없음', [
                'username'  => $this->username,
                'user_code' => $this->userCode
            ]);
        }

        $approveUrl = sprintf(
            '%s/approve_request?code=%s%s',
            $baseUrl,
            urlencode($this->userCode),
            $token ? '&approve_token=' . urlencode($token) : ''
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

    /**
     * APP_SECRET / AppSecret / InternalApiSecret 로드
     * - APP_SECRET 상수가 정의돼 있으면 그 값 우선 사용
     * - 없으면 config/appsetting.json 의 AppSecret → InternalApiSecret 순
     */
    private function loadAppSecret(): string
    {
        // 1. 상수 APP_SECRET 이 정의돼 있고, 비어있지 않다면 우선 사용
        if (\defined('APP_SECRET')) {
            $val = \constant('APP_SECRET');   // <- 여기서 직접 APP_SECRET 를 쓰지 않고 constant() 사용
            if (is_string($val) && $val !== '') {
                return $val;
            }
        }

        // 2. config/appsetting.json 에서 AppSecret / InternalApiSecret 조회
        $configFile = PROJECT_ROOT . '/config/appsetting.json';
        if (!file_exists($configFile)) {
            $this->log("appsetting.json 없음");
            $this->logger->warning('AdminApprovalMail: appsetting.json 없음');
            return '';
        }

        $raw = file_get_contents($configFile);

        // JSON 앞부분에 주석이 있을 경우 제거
        $raw = preg_replace('#^\s*//.*$#m', '', $raw);
        $raw = preg_replace('#/\*.*?\*/#s', '', $raw);

        $cfg = json_decode($raw, true);

        if (!is_array($cfg)) {
            $this->log("appsetting.json 파싱 실패");
            $this->logger->error('AdminApprovalMail: appsetting.json 파싱 실패');
            return '';
        }

        // 우선 AppSecret, 없으면 InternalApiSecret 사용
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
