<?php
namespace App\Controllers\Home;

use App\Services\Mail\MailService;

class ContactController
{
    public function apiSend()
    {
        $name    = trim($_POST['FullName']  ?? '');
        $email   = trim($_POST['EmailId']   ?? '');
        $subject = trim($_POST['Subject']   ?? '');
        $message = trim($_POST['Message']   ?? '');

        if (!$name || !$email || !$subject || !$message) {
            return $this->fail("모든 항목을 입력해주세요.");
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

            if (empty($result['success']) || empty($result['sent'])) {
                $errorCode = (string) ($result['error_code'] ?? 'CONTACT_MAIL_FAILED');
                $requestId = (string) ($result['request_id'] ?? '');
                return $this->fail($this->mailFailureMessage($errorCode), 503);
            }

            // 성공 화면 로드
            include PROJECT_ROOT . '/app/views/home/contact_email_confirmation.php';
            exit;
        } catch (\Throwable $e) {
            return $this->fail("메일 전송 중 오류가 발생했습니다.");
        }
    }

    private function mailFailureMessage(string $errorCode): string
    {
        return match ($errorCode) {
            'SMTP_SECRET_NOT_CONFIGURED', 'SMTP_CONFIGURATION_MISSING' =>
                '현재 메일 발송 설정이 완료되지 않아 문의를 전달하지 못했습니다.',
            'SMTP_AUTHENTICATION_FAILED' =>
                '메일 발송 인증정보 오류로 문의를 전달하지 못했습니다.',
            'SENDER_IDENTITY_REJECTED' =>
                '등록된 발신주소를 사용할 수 없어 문의를 전달하지 못했습니다.',
            'SMTP_CONNECTION_FAILED', 'SMTP_TIMEOUT' =>
                '메일서버에 연결할 수 없어 문의를 전달하지 못했습니다.',
            default => '문의 전달 중 오류가 발생했습니다.',
        };
    }

    private function fail(string $message, int $httpStatus = 400)
    {
        http_response_code($httpStatus);
        echo "<script>alert('{$message}'); history.back();</script>";
        exit;
    }
}
