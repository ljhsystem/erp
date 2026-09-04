<?php
declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

use App\Controllers\Home\ContactController;

function assertContactContract(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$controller = new ContactController();
$messageMethod = new ReflectionMethod($controller, 'mailFailureMessage');

$expectedMessages = [
    'SMTP_SECRET_NOT_CONFIGURED' => '현재 메일 발송 설정이 완료되지 않아 문의를 전달하지 못했습니다.',
    'SMTP_AUTHENTICATION_FAILED' => '메일 발송 인증정보 오류로 문의를 전달하지 못했습니다.',
    'SENDER_IDENTITY_REJECTED' => '등록된 발신주소를 사용할 수 없어 문의를 전달하지 못했습니다.',
    'SMTP_CONNECTION_FAILED' => '메일서버에 연결할 수 없어 문의를 전달하지 못했습니다.',
];

foreach ($expectedMessages as $errorCode => $expectedMessage) {
    assertContactContract(
        $messageMethod->invoke($controller, $errorCode) === $expectedMessage,
        $errorCode . ' 사용자 문구 오류'
    );
}

$source = file_get_contents(PROJECT_ROOT . '/app/Controllers/Home/ContactController.php');
assertContactContract(is_string($source), 'ContactController 소스 조회 실패');
assertContactContract(str_contains($source, 'return $this->fail($this->mailFailureMessage($errorCode), 503);'), '메일 실패 HTTP 상태 누락');
assertContactContract(!str_contains($source, 'SMTP 응답'), '사용자 응답에 SMTP 상세 노출');

echo "contact_mail_failure_contract: PASS\n";
