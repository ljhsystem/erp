<?php
// 경로: PROJECT_ROOT . '/app/Controllers/Home/ContactController.php'
namespace App\Controllers\Home;

use App\Services\Mail\MailService;

class ContactController
{
    private array $smtp = [];
    private array $config = [];

    public function __construct()
    {
        $configFile = PROJECT_ROOT . '/config/appsetting.json';

        if (!file_exists($configFile)) {
            exit("❌ 설정 파일(appsetting.json)을 찾을 수 없습니다.");
        }

        // JSON 주석 제거 후 파싱
        $raw = file_get_contents($configFile);
        $raw = preg_replace('#^\s*//.*$#m', '', $raw);
        $raw = preg_replace('#/\*.*?\*/#s', '', $raw);

        $cfg = json_decode($raw, true) ?: [];

        $this->config = $cfg;
        $this->smtp   = $cfg['SmtpSettings'] ?? ($cfg['Smtp'] ?? []);
    }

    //// ============================================================
    // WEB: 문의 메일 전송 처리
    // URL: POST /contact/send
    // permission: 없음
    // controller: ContactController@apiSend
    //// ============================================================
    public function apiSend()
    {
        $name    = trim($_POST['FullName']  ?? '');
        $email   = trim($_POST['EmailId']   ?? '');
        $subject = trim($_POST['Subject']   ?? '');
        $message = trim($_POST['Message']   ?? '');

        if (!$name || !$email || !$subject || !$message) {
            return $this->fail("모든 항목을 입력해주세요.");
        }

        // 관리자 이메일 결정
        $adminEmail = $this->smtp['AdminEmail']
            ?? $this->smtp['SenderEmail']
            ?? $this->smtp['UserName']
            ?? null;

        if (!$adminEmail) {
            error_log('[Contact] 관리자 이메일 미설정');
            return $this->fail("서버 설정 오류가 발생했습니다.");
        }

        // 메일 발송
        try {
            $mailer = new MailService();

            $payload = [
                'fromName'  => $name,
                'fromEmail' => $email,
                'subject'   => $subject,
                'message'   => $message,
            ];

            $result = $mailer->sendContactMail($payload);

            if (empty($result['sent'])) {
                $status = $result['status'] ?? 'unknown-error';
                error_log("[Contact] 메일 전송 실패: {$status}");
                return $this->fail("메일 전송에 실패했습니다. 다시 시도해주세요.");
            }

            // 성공 화면 로드
            include PROJECT_ROOT . '/app/views/home/contact_email_confirmation.php';
            exit;
        } catch (\Throwable $e) {
            error_log('[Contact] 예외 발생: ' . $e->getMessage());
            return $this->fail("메일 전송 중 오류가 발생했습니다.");
        }
    }

    //// ============================================================
    // HELPER: 실패 처리
    //// ============================================================
    private function fail(string $message)
    {
        echo "<script>alert('{$message}'); history.back();</script>";
        exit;
    }
}
