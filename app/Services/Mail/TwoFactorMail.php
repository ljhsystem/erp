<?php
declare(strict_types=1);
namespace App\Services\Mail;

use Core\LoggerFactory;

class TwoFactorMail
{
    private Mailer $mailer;
    private array $user = [];

    private $logger;

    public function __construct(Mailer $mailer)
    {
        $this->mailer = $mailer;
        $this->logger = LoggerFactory::getLogger('service-mail.TwoFactorMail');
    }

    public function send(array $data): array
    {
        $this->user = $data['user'] ?? $data;

        $this->logger->info('2FA 메일 send 호출', [
            'user_id'  => $this->user['id'] ?? null,
            'username' => $this->user['username'] ?? null,
            'email'    => $this->user['email'] ?? null
        ]);

        $content = $this->build();

        if (empty($content['to'])) {
            $this->log("수신 이메일 없음");
            $this->logger->warning('2FA 메일 발송 실패 - 수신 이메일 없음', [
                'user' => $this->user
            ]);
            return ['sent' => 0, 'status' => 'missing_to'];
        }

        $this->logger->info('2FA 메일 발송 시도', [
            'to'       => $content['to'],
            'username' => $this->user['username'] ?? null
        ]);

        $result = $this->mailer->send(
            $content['to'],
            $content['subject'],
            $content['html'],
            $content['text']
        );

        $this->logger->info('2FA 메일 발송 결과', [
            'to'     => $content['to'],
            'sent'   => $result['sent'] ?? null,
            'status' => $result['status'] ?? null
        ]);

        return $result;
    }

    public function build(): array
    {
        $to = $this->user['email'] ?? '';

        $code = $this->user['two_factor_code'] ?? '';

        if ($code === '') {
            $this->log("2FA 코드 없음: user=" . json_encode($this->user));

            $this->logger->warning('2FA 메일 build - 코드 없음', [
                'user' => $this->user
            ]);
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

        $this->logger->info('2FA 메일 build 완료', [
            'to'    => $to,
            'has_code' => $code !== ''
        ]);

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

    private function log(string $msg): void
    {
        @file_put_contents(
            PROJECT_ROOT . '/storage/logs/mail_debug.log',
            date('c') . " | TwoFactorMail | " . $msg . PHP_EOL,
            FILE_APPEND
        );
    }
}
