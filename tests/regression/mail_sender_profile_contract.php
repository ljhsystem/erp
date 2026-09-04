<?php
declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

use App\Services\Auth\RegisterService;
use App\Services\Mail\Mailer;
use App\Services\Mail\MailService;
use Monolog\Handler\NullHandler;
use Monolog\Logger;

function assertMailContract(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$config = json_decode((string) file_get_contents(PROJECT_ROOT . '/config/appsetting.json'), true, 512, JSON_THROW_ON_ERROR);
$runtimeSmtp = $config['SmtpSettings'] ?? [];
assertMailContract(
    ($runtimeSmtp['SenderProfiles'][Mailer::SENDER_SUKHYANG_APP_ADMIN]['TransportCode'] ?? null) === 'DAUM_SMTP_MAIN',
    '운영 앱 관리자 Transport 연결 오류'
);
assertMailContract(
    ($runtimeSmtp['Transports']['DAUM_SMTP_MAIN']['Host'] ?? null) === 'smtp.daum.net',
    '운영 Daum SMTP Host 오류'
);
assertMailContract(
    ($runtimeSmtp['SenderProfiles'][Mailer::SENDER_SUKHYANG_REPRESENTATIVE]['TransportCode'] ?? null) === 'GOOGLE_SMTP_MAIN',
    '운영 대표자 Transport 연결 오류'
);
assertMailContract(
    ($runtimeSmtp['Transports']['GOOGLE_SMTP_MAIN']['Host'] ?? null) === 'smtp.gmail.com',
    '운영 Google SMTP Host 오류'
);

$mailerReflection = new ReflectionClass(Mailer::class);
$mailer = $mailerReflection->newInstanceWithoutConstructor();
$smtpProperty = $mailerReflection->getProperty('smtp');
$smtpProperty->setValue($mailer, [
    'AdminEmail' => 'admin@example.com',
    'Transports' => [
        'DAUM_SMTP_MAIN' => [
            'Host' => 'smtp.daum.net',
            'Port' => 465,
            'SMTPSecure' => 'ssl',
            'UserName' => 'suk-hyang',
            'CredentialCode' => 'DAUM_SMTP_MAIN',
        ],
        'GOOGLE_SMTP_MAIN' => [
            'Host' => 'smtp.gmail.com',
            'Port' => 587,
            'SMTPSecure' => 'tls',
            'UserName' => 'smtp-auth@example.com',
            'CredentialCode' => 'GOOGLE_SMTP_MAIN',
        ],
    ],
    'SenderProfiles' => [
        Mailer::SENDER_SUKHYANG_APP_ADMIN => [
            'TransportCode' => 'DAUM_SMTP_MAIN',
            'SenderName' => '석향앱관리자',
            'SenderEmail' => 'suk-hyang@daum.net',
            'ReplyToEmail' => 'suk-hyang@daum.net',
        ],
        Mailer::SENDER_SUKHYANG_REPRESENTATIVE => [
            'TransportCode' => 'GOOGLE_SMTP_MAIN',
            'SenderName' => '(주)석향 대표 이정호',
            'SenderEmail' => 'ljhsystem66@gmail.com',
            'ReplyToEmail' => 'ljhsystem66@gmail.com',
        ],
    ],
]);
$loggerProperty = $mailerReflection->getProperty('logger');
$nullLogger = new Logger('mail-profile-test');
$nullLogger->pushHandler(new NullHandler());
$loggerProperty->setValue($mailer, $nullLogger);

$envelopeMethod = new ReflectionMethod($mailer, 'resolveSenderEnvelope');
$appEnvelope = $envelopeMethod->invoke($mailer, Mailer::SENDER_SUKHYANG_APP_ADMIN);
assertMailContract($appEnvelope['success'] === true, '앱 관리자 프로필 해석 실패');
assertMailContract($appEnvelope['envelope']['sender_name'] === '석향앱관리자', '앱 관리자 표시명 오류');
assertMailContract($appEnvelope['envelope']['sender_email'] === 'suk-hyang@daum.net', '앱 관리자 From 오류');
assertMailContract($appEnvelope['envelope']['reply_to_email'] === 'suk-hyang@daum.net', '앱 관리자 Reply-To 오류');
assertMailContract($appEnvelope['envelope']['transport_code'] === 'DAUM_SMTP_MAIN', '앱 관리자 Transport 오류');

$representativeEnvelope = $envelopeMethod->invoke($mailer, Mailer::SENDER_SUKHYANG_REPRESENTATIVE);
assertMailContract($representativeEnvelope['success'] === true, '대표자 프로필 해석 실패');
assertMailContract($representativeEnvelope['envelope']['sender_name'] === '(주)석향 대표 이정호', '대표자 표시명 오류');
assertMailContract($representativeEnvelope['envelope']['sender_email'] === 'ljhsystem66@gmail.com', '대표자 From 오류');
assertMailContract($representativeEnvelope['envelope']['reply_to_email'] === 'ljhsystem66@gmail.com', '대표자 Reply-To 오류');
assertMailContract($representativeEnvelope['envelope']['transport_code'] === 'GOOGLE_SMTP_MAIN', '대표자 Transport 오류');

$transportMethod = new ReflectionMethod($mailer, 'resolveTransport');
$appTransport = $transportMethod->invoke($mailer, $appEnvelope['envelope']['transport_code']);
assertMailContract($appTransport['success'] === true, 'Daum Transport 해석 실패');
assertMailContract($appTransport['transport']['host'] === 'smtp.daum.net', '앱 관리자 Gmail fallback 발생');
assertMailContract($appTransport['transport']['port'] === 465, 'Daum SMTP Port 오류');
assertMailContract($appTransport['transport']['smtp_secure'] === 'ssl', 'Daum SMTP 암호화 오류');
assertMailContract($appTransport['transport']['credential_code'] === 'DAUM_SMTP_MAIN', 'Daum Credential 연결 오류');

$representativeTransport = $transportMethod->invoke($mailer, $representativeEnvelope['envelope']['transport_code']);
assertMailContract($representativeTransport['success'] === true, 'Google Transport 해석 실패');
assertMailContract($representativeTransport['transport']['host'] === 'smtp.gmail.com', '대표자 Transport 오류');
assertMailContract($representativeTransport['transport']['credential_code'] === 'GOOGLE_SMTP_MAIN', 'Google Credential 연결 오류');

$contactEnvelope = $envelopeMethod->invoke(
    $mailer,
    Mailer::SENDER_SUKHYANG_APP_ADMIN,
    'customer@example.net',
    '문의자'
);
assertMailContract($contactEnvelope['envelope']['sender_email'] === 'suk-hyang@daum.net', '문의 From 변경 오류');
assertMailContract($contactEnvelope['envelope']['reply_to_email'] === 'customer@example.net', '문의 Reply-To Override 오류');

$missingProfile = $mailer->send('to@example.net', 'subject', 'body', 'text', '');
assertMailContract($missingProfile['success'] === false, '프로필 누락 발송 허용');
assertMailContract($missingProfile['error_code'] === 'SENDER_PROFILE_REQUIRED', '프로필 누락 오류코드 오류');
$invalidProfile = $mailer->send('to@example.net', 'subject', 'body', 'text', 'UNKNOWN_PROFILE');
assertMailContract($invalidProfile['success'] === false, '잘못된 프로필 발송 허용');
assertMailContract($invalidProfile['error_code'] === 'SENDER_PROFILE_INVALID', '잘못된 프로필 오류코드 오류');

$invalidTransport = $transportMethod->invoke($mailer, 'UNKNOWN_TRANSPORT');
assertMailContract($invalidTransport['success'] === false, '잘못된 Transport 허용');
assertMailContract($invalidTransport['error_code'] === 'SMTP_TRANSPORT_INVALID', '잘못된 Transport 오류코드 오류');

$mismatchedSmtp = $smtpProperty->getValue($mailer);
$mismatchedSmtp['Transports']['DAUM_SMTP_MAIN']['CredentialCode'] = 'GOOGLE_SMTP_MAIN';
$smtpProperty->setValue($mailer, $mismatchedSmtp);
$mismatchedTransport = $transportMethod->invoke($mailer, 'DAUM_SMTP_MAIN');
assertMailContract($mismatchedTransport['success'] === false, 'Transport Credential 불일치 허용');
assertMailContract(
    $mismatchedTransport['error_code'] === 'SMTP_TRANSPORT_CREDENTIAL_MISMATCH',
    'Transport Credential 불일치 오류코드 오류'
);
$mismatchedSmtp['Transports']['DAUM_SMTP_MAIN']['CredentialCode'] = 'DAUM_SMTP_MAIN';
$smtpProperty->setValue($mailer, $mismatchedSmtp);

$classifier = new ReflectionMethod($mailer, 'classifyError');
assertMailContract(
    $classifier->invoke($mailer, 'Sender address rejected') === 'SENDER_IDENTITY_REJECTED',
    '발신주소 거부 분류 오류'
);

$mailService = new class extends MailService {
    public function __construct()
    {
    }

    public function sendAdminApprovalMail(array $data): array
    {
        return ['success' => false, 'sent' => false, 'error_code' => 'SMTP_AUTHENTICATION_FAILED'];
    }
};
$registerReflection = new ReflectionClass(RegisterService::class);
$registerService = $registerReflection->newInstanceWithoutConstructor();
$registerReflection->getProperty('mailService')->setValue($registerService, $mailService);
$registerLogger = new Logger('register-mail-test');
$registerLogger->pushHandler(new NullHandler());
$registerReflection->getProperty('logger')->setValue($registerService, $registerLogger);
$sendApproval = new ReflectionMethod($registerService, 'sendAdminApprovalMail');
$approvalFailure = $sendApproval->invoke($registerService, 'user', 'name', 'user@example.net', 'user-id');
assertMailContract($approvalFailure['success'] === false, '관리자 승인메일 실패 미전파');
assertMailContract($approvalFailure['error_code'] === 'SMTP_AUTHENTICATION_FAILED', '관리자 승인메일 오류코드 손실');

foreach (['TwoFactorMail.php', 'AdminApprovalMail.php', 'ContactMail.php'] as $mailPath) {
    $mailSource = file_get_contents(PROJECT_ROOT . '/app/Services/Mail/' . $mailPath);
    assertMailContract(is_string($mailSource), $mailPath . ' 소스 조회 실패');
    assertMailContract(
        str_contains($mailSource, 'Mailer::SENDER_SUKHYANG_APP_ADMIN'),
        $mailPath . ' 앱 관리자 프로필 누락'
    );
    assertMailContract(!str_contains($mailSource, 'SUKHYANG_REPRESENTATIVE'), $mailPath . ' 대표자 프로필 오용');
}

$registerSource = file_get_contents(PROJECT_ROOT . '/app/Services/Auth/RegisterService.php');
assertMailContract(is_string($registerSource), 'RegisterService 소스 조회 실패');
assertMailContract(str_contains($registerSource, "'mail_sent' => false"), '회원가입 메일 실패 상태 누락');
assertMailContract(str_contains($registerSource, '회원가입 요청은 저장되었으나 관리자 승인 메일을 발송하지 못했습니다.'), '회원가입 부분성공 안내 누락');
assertMailContract(str_contains($registerSource, "'redirect' => '/waiting_approval'"), '회원가입 메일 실패 이동경로 누락');

echo "mail_sender_profile_contract: PASS\n";
