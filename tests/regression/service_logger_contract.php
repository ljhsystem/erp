<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Storage.php';

use Core\LoggerFactory;

assert(LoggerFactory::normalizeChannel('service-system.ClientService') === 'service-system-client');
assert(LoggerFactory::normalizeChannel('service-calendar.CalendarCrudService') === 'service-calendar-crud');
assert(LoggerFactory::normalizeChannel('core-Router') === 'system-core-router');
assert(LoggerFactory::normalizeChannel('cli-sync-runner') === 'job-sync-runner');
assert(LoggerFactory::normalizeChannel('security') === 'security-authentication');

$redacted = LoggerFactory::redactContext([
    'password' => 'plain-password',
    'token' => 'opaque-token-value',
    'token' => 'opaque-token-value',
    'nested' => [
        'rrn' => '900101-1234567',
        'contact_email' => 'person@example.com',
    ],
    'message' => '주민번호 900101-1234567, 담당자 person@example.com',
    'request_id' => 'request-safe',
]);

assert($redacted['password'] === '[REDACTED]');
assert($redacted['token'] === '[REDACTED]');
assert($redacted['token'] === '[REDACTED]');
assert($redacted['nested']['rrn'] === '[REDACTED]');
assert($redacted['nested']['contact_email'] === 'p***@example.com');
assert($redacted['message'] === '주민번호 900101-*******, 담당자 p***@example.com');
assert($redacted['request_id'] === 'request-safe');

$serviceFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PROJECT_ROOT . '/app/Services'));
foreach ($serviceFiles as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
    $path = str_replace('\\', '/', $file->getPathname());
    if (str_contains($path, '/Services/Calendar/')) continue;
    $code = file_get_contents($file->getPathname());
    assert($code !== false);
    assert(!preg_match('/\\berror_log\\s*\\(/', $code), $path . '에서 error_log()를 직접 사용할 수 없습니다.');
}

foreach (['app/Controllers', 'app/Models', 'app/Repositories', 'app/views', 'public/assets/js'] as $relativeRoot) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PROJECT_ROOT . '/' . $relativeRoot));
    foreach ($files as $file) {
        if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['php', 'js'], true)) continue;
        $code = file_get_contents($file->getPathname());
        assert($code !== false);
        assert(!preg_match('/\\berror_log\\s*\\(|LoggerFactory::getLogger\\s*\\(/', $code), $file->getPathname() . '에서 업무 로그를 직접 기록할 수 없습니다.');
    }
}

$rules = file_get_contents(PROJECT_ROOT . '/AGENTS.md');
assert($rules !== false);
assert(str_contains($rules, 'service-{module}-{domain}'));
assert(str_contains($rules, '성공·업무차단·실패'));
assert(str_contains($rules, '민감값'));
assert(str_contains($rules, '해당 도메인 Service 로그와 연관'));
assert(str_contains($rules, '로그를 확인하지 않은 추측성 수정은 금지'));

$operationTrait = file_get_contents(PROJECT_ROOT . '/app/Services/Concerns/LogsServiceOperations.php');
assert($operationTrait !== false);
assert(str_contains($operationTrait, "'SUCCESS'"));
assert(str_contains($operationTrait, "'BLOCKED'"));
assert(str_contains($operationTrait, "'FAILED'"));
assert(str_contains($operationTrait, 'catch (PDOException $exception)'));

$classification = require PROJECT_ROOT . '/config/service_logging_contract.php';
foreach ($classification as $file => $type) {
    assert(is_file(PROJECT_ROOT . '/' . $file), $file . '이 존재하지 않습니다.');
    assert(in_array($type, ['PURE','DELEGATED','INFRASTRUCTURE'], true), $file . '의 로그 책임 분류가 올바르지 않습니다.');
}

echo json_encode([
    'success' => true,
    'channel_normalization' => true,
    'sensitive_context_redaction' => true,
    'text_redaction' => true,
    'service_error_log_absent' => true,
    'development_rule_documented' => true,
    'service_operation_outcomes' => true,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
