<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Institution\PayComponentService;
use App\Services\Institution\RegularEmploymentIncomeCalculationService;
use App\Services\Institution\RegularEmploymentIncomeService;
use Core\DbPdo;

$db = DbPdo::conn();
$user = $db->query("SELECT id, username FROM auth_users WHERE is_active = 1 ORDER BY created_at, id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$user) throw new RuntimeException('회귀검사 Actor를 찾을 수 없습니다.');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$_SESSION['user'] = ['id' => $user['id'], 'username' => $user['username']];
$_SESSION['auth_state'] = ['user_id' => $user['id'], 'status' => 'NORMAL'];

$employeeId = 'ce50c61c-8b08-4f58-b8bc-e11f1dbafb84';
$options = (new PayComponentService($db))->optionsForDate('2013-09-11');
$other = current(array_filter($options, static fn(array $option): bool => ($option['meta']['component_code'] ?? '') === 'OTHER_PAY'));
if (!$other) throw new RuntimeException('기타 급여항목 마스터를 찾을 수 없습니다.');

$input = [
    'item_type_code' => 'PAY',
    'pay_effect_code' => 'INCREASE',
    'final_amount' => 12345,
    'business_source_code' => 'MANUAL',
    'source_reference_id' => $other['value'],
    'source_key' => 'CLIENT|OTHER_PAY|INCREASE|99999999-9999-4999-8999-999999999999',
    'business_reason' => '기타 지급 회귀검증',
];

$calculation = new RegularEmploymentIncomeCalculationService($db);
$service = new RegularEmploymentIncomeService($db);
$db->beginTransaction();
try {
    $baseline = $calculation->preview('2013-09', '2013-10-11', [[
        'employee_id' => $employeeId,
        'dependent_count_snapshot' => 1,
    ]], 'SYSTEM:REGRESSION')['results'][0];
    $preview = $calculation->preview('2013-09', '2013-10-11', [[
        'employee_id' => $employeeId,
        'dependent_count_snapshot' => 1,
        'pay_line_items' => [$input],
    ]], 'SYSTEM:REGRESSION');
    $save = $service->savePayEffect([
        'income_year_month' => '2013-09',
        'payment_date' => '2013-10-11',
        'title' => '기타 지급 저장 회귀검증',
        'items' => $preview['results'],
    ]);
    $documentId = (string) $save['data']['id'];
    $detail = $service->detail((string) $save['data']['id'])['data'];
    $stored = array_values(array_filter(
        $detail['items'][0]['line_items'],
        static fn(array $line): bool => str_starts_with((string) ($line['source_key'] ?? ''), 'PAY_COMPONENT|OTHER_PAY|INCREASE|')
    ));
    $resave = $service->savePayEffect([
        'id' => $documentId,
        'income_year_month' => '2013-09',
        'payment_date' => '2013-10-11',
        'title' => '기타 지급 저장 회귀검증',
        'items' => $detail['items'],
    ]);
    $reopened = $service->detail((string) $resave['data']['id'])['data'];
    $reopenedLines = array_values(array_filter(
        $reopened['items'][0]['line_items'],
        static fn(array $line): bool => str_starts_with((string) ($line['source_key'] ?? ''), 'PAY_COMPONENT|OTHER_PAY|INCREASE|')
    ));
    $line = $stored[0] ?? [];
    $checks = [
        'saved_snapshot' => count($stored) === 1
            && ($line['source_reference_id'] ?? '') === $other['value']
            && ($line['item_name_snapshot'] ?? '') === '기타'
            && (int) ($line['taxable_flag'] ?? 0) === 1
            && ($line['business_reason'] ?? '') === '기타 지급 회귀검증',
        'saved_totals' => (float) $detail['items'][0]['gross_amount'] === (float) $baseline['gross_amount'] + 12345.0
            && (float) $detail['items'][0]['taxable_amount'] === (float) $baseline['taxable_amount'] + 12345.0,
        'resave_requery' => count($reopenedLines) === 1
            && (float) $reopened['items'][0]['gross_amount'] === (float) $detail['items'][0]['gross_amount']
            && ($reopenedLines[0]['business_reason'] ?? '') === '기타 지급 회귀검증',
    ];
    $failed = array_keys(array_filter($checks, static fn(bool $value): bool => !$value));
    echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
    if ($failed !== []) exit(1);
} finally {
    if ($db->inTransaction()) $db->rollBack();
}
