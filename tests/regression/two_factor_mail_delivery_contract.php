<?php
declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

use App\Services\Auth\AuthService;
use App\Services\Auth\AuthSessionService;
use App\Services\Auth\LogService;
use App\Services\Auth\SecurityPolicyService;
use App\Services\Auth\TwoFactorService;
use App\Services\Mail\MailService;
use App\Services\Mail\Mailer;

final class TwoFactorMailServiceMock extends MailService
{
    public function __construct(private array $result, private bool $throw = false)
    {
    }

    public function sendTwoFactorMail(array $data): array
    {
        if ($this->throw) {
            throw new RuntimeException('mock mailer exception');
        }
        return $this->result;
    }
}

final class TwoFactorSessionMock extends AuthSessionService
{
    public bool $pending = false;
    public int $ttl = 0;
    public int $maxAttempts = 0;
    public int $clearCount = 0;

    public function __construct()
    {
    }

    public function clearPendingTwoFactor(): void
    {
        $this->pending = false;
        $this->clearCount++;
    }

    public function createPendingTwoFactorSession(
        array $user,
        array $reasons,
        string $codeHash,
        int $ttl,
        int $maxAttempts
    ): void {
        if ($codeHash === '' || preg_match('/^[a-f0-9]{64}$/', $codeHash) !== 1) {
            throw new RuntimeException('OTP Hash contract failed');
        }
        $this->pending = true;
        $this->ttl = $ttl;
        $this->maxAttempts = $maxAttempts;
    }
}

final class TwoFactorLogMock extends LogService
{
    public int $successCount = 0;
    public int $failureCount = 0;
    public ?string $errorCode = null;
    public array $failureContext = [];

    public function __construct()
    {
    }

    public function twoFactorSend(string $userId): void
    {
        $this->successCount++;
    }

    public function twoFactorSendFailed(string $userId, string $errorCode, array $context = []): void
    {
        $this->failureCount++;
        $this->errorCode = $errorCode;
        $this->failureContext = $context;
    }
}

final class TwoFactorPolicyMock extends SecurityPolicyService
{
    public function __construct()
    {
    }

    public function getTwoFactorReasons(array $user): array
    {
        return ['new_device_2fa' => true];
    }
}

function runDelivery(array $mailResult, bool $throw = false): array
{
    $session = new TwoFactorSessionMock();
    $log = new TwoFactorLogMock();
    $reflection = new ReflectionClass(AuthService::class);
    $service = $reflection->newInstanceWithoutConstructor();
    foreach ([
        'mailService' => new TwoFactorMailServiceMock($mailResult, $throw),
        'authSessionService' => $session,
        'twoFactorService' => new TwoFactorService(),
        'authLogService' => $log,
        'securityPolicyService' => new TwoFactorPolicyMock(),
    ] as $propertyName => $value) {
        $property = $reflection->getProperty($propertyName);
        $property->setValue($service, $value);
    }
    $method = new ReflectionMethod($service, 'beginTwoFactorDelivery');
    $result = $method->invoke($service, [
        'id' => 'user-test',
        'username' => 'admin-test',
        'email' => 'masked@example.com',
        'role_key' => 'admin',
    ]);

    return compact('result', 'session', 'log');
}

function assertContract(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$baseFailure = [
    'success' => false,
    'sent' => false,
    'provider' => 'GMAIL',
    'recipient_domain' => 'example.com',
    'message_id' => null,
    'retryable' => false,
    'occurred_at' => '2026-09-01T00:00:00+09:00',
];

$success = runDelivery([
    'success' => true,
    'sent' => true,
    'provider' => 'GMAIL',
    'recipient_domain' => 'example.com',
    'message_id' => '<mock-message@example.com>',
    'error_code' => null,
    'retryable' => false,
    'occurred_at' => '2026-09-01T00:00:00+09:00',
]);
assertContract($success['result']['success'] === true, 'SMTP 성공 응답 오류');
assertContract($success['session']->pending === true, 'SMTP 성공 시 Challenge 미생성');
assertContract($success['session']->ttl === 300, 'Challenge TTL 오류');
assertContract($success['session']->maxAttempts === 5, 'Challenge 최대 실패 횟수 오류');
assertContract($success['log']->successCount === 1 && $success['log']->failureCount === 0, '성공 로그 오류');

foreach ([
    'SMTP_AUTHENTICATION_FAILED',
    'SMTP_CONNECTION_FAILED',
    'RECIPIENT_REJECTED',
] as $errorCode) {
    $failure = runDelivery($baseFailure + ['error_code' => $errorCode]);
    assertContract($failure['result']['success'] === false, $errorCode . ' 실패 응답 오류');
    assertContract($failure['session']->pending === false, $errorCode . ' 실패 Challenge 잔존');
    assertContract($failure['log']->successCount === 0 && $failure['log']->failureCount === 1, $errorCode . ' 로그 오류');
    assertContract($failure['log']->errorCode === $errorCode, $errorCode . ' 분류 오류');
    assertContract(!str_contains((string) json_encode($failure['result']), '인증 코드를 발송했습니다'), $errorCode . ' 성공 문구 노출');
}

$exception = runDelivery([], true);
assertContract($exception['result']['success'] === false, 'Mailer 예외 성공 처리');
assertContract($exception['session']->pending === false, 'Mailer 예외 Challenge 잔존');
assertContract($exception['log']->errorCode === 'MAILER_EXCEPTION', 'Mailer 예외 분류 오류');

$mailer = (new ReflectionClass(Mailer::class))->newInstanceWithoutConstructor();
$classifier = new ReflectionMethod($mailer, 'classifyError');
foreach ([
    'SMTP Error: Could not authenticate.' => 'SMTP_AUTHENTICATION_FAILED',
    'SMTP connect() failed.' => 'SMTP_CONNECTION_FAILED',
    'Connection timed out' => 'SMTP_TIMEOUT',
    'Recipient address rejected' => 'RECIPIENT_REJECTED',
    'Sender address rejected' => 'SENDER_IDENTITY_REJECTED',
] as $providerMessage => $expectedCode) {
    assertContract($classifier->invoke($mailer, $providerMessage) === $expectedCode, $expectedCode . ' Mailer 분류 오류');
}

$failureResponse = new ReflectionMethod(AuthService::class, 'twoFactorMailFailureResponse');
$generalUserResponse = $failureResponse->invoke(
    (new ReflectionClass(AuthService::class))->newInstanceWithoutConstructor(),
    ['role_key' => 'staff'],
    'SMTP_AUTHENTICATION_FAILED'
);
assertContract(
    $generalUserResponse['mail_error']['detail'] === '메일 발송 설정에 문제가 있습니다. 관리자에게 문의해 주세요.',
    '일반 사용자 안전 문구 오류'
);
assertContract(empty($generalUserResponse['mail_error']['management_url']), '일반 사용자 Google 관리 주소 노출');
assertContract(!isset($generalUserResponse['mail_error']['error_code']), '사용자 응답 내부 오류코드 노출');

echo "two_factor_mail_delivery_contract: PASS\n";
