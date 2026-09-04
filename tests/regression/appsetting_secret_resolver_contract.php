<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

use Core\Security\SecretResolutionException;
use Core\Security\SecretResolver;

function assertSecretContract(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$fixtureRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'erp-secret-contract-' . bin2hex(random_bytes(8));
if (!mkdir($fixtureRoot, 0700, true) && !is_dir($fixtureRoot)) {
    throw new RuntimeException('SecretResolver Fixture 디렉터리 생성 실패');
}

$fixture = <<<'PHP'
<?php
return [
    'SECURITY_RRN_KEY' => ['secret' => 'fixture-rrn-key'],
    'DAUM_SMTP_MAIN' => ['password' => null],
    'GOOGLE_SMTP_MAIN' => ['password' => null],
    'BUSINESS_STATUS_API' => ['service_key' => 'fixture-business-key'],
    'INTERNAL_API_MAIN' => ['secret' => null],
];
PHP;
file_put_contents($fixtureRoot . DIRECTORY_SEPARATOR . 'appsetting_secrets.php', $fixture, LOCK_EX);

try {
    $resolver = new SecretResolver($fixtureRoot);
    assertSecretContract($resolver->resolve('SECURITY_RRN_KEY', 'secret') !== '', 'RRN Credential 조회 실패');
    assertSecretContract($resolver->resolve('BUSINESS_STATUS_API', 'service_key') !== '', 'Business API Credential 조회 실패');

    foreach ([
        ['GOOGLE_SMTP_MAIN', 'password', 'SECRET_NOT_CONFIGURED'],
        ['DAUM_SMTP_MAIN', 'password', 'SECRET_NOT_CONFIGURED'],
        ['INTERNAL_API_MAIN', 'secret', 'SECRET_NOT_CONFIGURED'],
        ['UNKNOWN', 'secret', 'UNKNOWN_CREDENTIAL'],
        ['SECURITY_RRN_KEY', 'password', 'UNKNOWN_CREDENTIAL'],
    ] as [$code, $field, $expected]) {
        try {
            $resolver->resolve($code, $field);
            throw new RuntimeException($code . ' 누락/오류 Credential 허용');
        } catch (SecretResolutionException $e) {
            assertSecretContract($e->errorCode() === $expected, $code . ' 오류코드 불일치');
        }
    }
} finally {
    @unlink($fixtureRoot . DIRECTORY_SEPARATOR . 'appsetting_secrets.php');
    @rmdir($fixtureRoot);
}

echo "appsetting_secret_resolver_contract: PASS\n";
