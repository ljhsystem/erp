<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

use Core\DbPdo;

$pdo = DbPdo::conn();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$groupId = (string) $pdo->query('SELECT id FROM institution_daily_employment_income_groups ORDER BY id LIMIT 1')->fetchColumn();
$sourceId = '20260828-0701-4000-8000-000000000001';
$contractId = (string) $pdo->query('SELECT id FROM institution_employment_contracts ORDER BY id LIMIT 1')->fetchColumn();
if ($groupId === '') throw new RuntimeException('검증할 기존 Group이 없습니다.');

$cases = [];
$run = static function (string $name, bool $allowed, callable $operation) use ($pdo, &$cases): void {
    $pdo->exec('SAVEPOINT insurance_case');
    $passed = false;
    try {
        $operation();
        $passed = $allowed;
    } catch (PDOException) {
        $passed = !$allowed;
    } finally {
        $pdo->exec('ROLLBACK TO SAVEPOINT insurance_case');
    }
    $cases[$name] = $passed;
    if (!$passed) throw new RuntimeException($name . ' 검증에 실패했습니다.');
};
$update = static function (string $prefix, ?string $status, ?string $reason, ?string $source) use ($pdo, $groupId): void {
    $sql = "UPDATE institution_daily_employment_income_groups SET {$prefix}_application_status_code=:status,"
        . "{$prefix}_decision_reason=:reason,{$prefix}_decision_source_code_id=:source WHERE id=:id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':status'=>$status,':reason'=>$reason,':source'=>$source,':id'=>$groupId]);
};
$updateContract = static function (string $prefix, ?string $status, ?string $reason) use ($pdo, $contractId): void {
    $stmt = $pdo->prepare("UPDATE institution_employment_contracts SET {$prefix}_application_status_code=:status,{$prefix}_exclusion_reason=:reason WHERE id=:id");
    $stmt->execute([':status'=>$status,':reason'=>$reason,':id'=>$contractId]);
};

$pdo->beginTransaction();
try {
    foreach (['employment_insurance', 'industrial_accident'] as $prefix) {
        $run('contract_' . $prefix . '_null', true, fn() => $updateContract($prefix, null, null));
        $run('contract_' . $prefix . '_applicable', true, fn() => $updateContract($prefix, 'APPLICABLE', null));
        $run('contract_' . $prefix . '_applicable_reason', false, fn() => $updateContract($prefix, 'APPLICABLE', '사유'));
        $run('contract_' . $prefix . '_excluded', true, fn() => $updateContract($prefix, 'EXCLUDED', '법정 적용제외'));
        foreach ([null, '', '   ', ' 앞공백', '뒤공백 ', ' 앞뒤공백 '] as $index=>$invalidReason) {
            $run('contract_' . $prefix . '_invalid_reason_' . $index, false, fn() => $updateContract($prefix, 'EXCLUDED', $invalidReason));
        }
        $run('contract_' . $prefix . '_reason_500', true, fn() => $updateContract($prefix, 'EXCLUDED', str_repeat('가', 500)));
        $run('contract_' . $prefix . '_reason_501', false, fn() => $updateContract($prefix, 'EXCLUDED', str_repeat('가', 501)));
        $run('contract_' . $prefix . '_invalid_status', false, fn() => $updateContract($prefix, 'CONFIRMATION_REQUIRED', null));
        $run($prefix . '_null', true, fn() => $update($prefix, null, null, null));
        $run($prefix . '_applicable', true, fn() => $update($prefix, 'APPLICABLE', null, $sourceId));
        $run($prefix . '_applicable_without_source', false, fn() => $update($prefix, 'APPLICABLE', null, null));
        $run($prefix . '_excluded', true, fn() => $update($prefix, 'EXCLUDED', '법정 적용제외', $sourceId));
        $run($prefix . '_excluded_without_reason', false, fn() => $update($prefix, 'EXCLUDED', null, $sourceId));
        $run($prefix . '_confirmation_manual', true, fn() => $update($prefix, 'CONFIRMATION_REQUIRED', '공식 근거 확인 필요', $sourceId));
        $run($prefix . '_confirmation_legacy', true, fn() => $update($prefix, 'CONFIRMATION_REQUIRED', '기존 문서 적용 여부 확인 필요', null));
        $run($prefix . '_blank_reason', false, fn() => $update($prefix, 'EXCLUDED', '   ', $sourceId));
        $run($prefix . '_leading_space', false, fn() => $update($prefix, 'EXCLUDED', ' 앞공백', $sourceId));
        $run($prefix . '_trailing_space', false, fn() => $update($prefix, 'EXCLUDED', '뒤공백 ', $sourceId));
        $run($prefix . '_both_space', false, fn() => $update($prefix, 'EXCLUDED', ' 앞뒤공백 ', $sourceId));
        $run($prefix . '_reason_500', true, fn() => $update($prefix, 'EXCLUDED', str_repeat('가', 500), $sourceId));
        $run($prefix . '_reason_501', false, fn() => $update($prefix, 'EXCLUDED', str_repeat('가', 501), $sourceId));
        $run($prefix . '_missing_source', false, fn() => $update($prefix, 'APPLICABLE', null, '00000000-0000-0000-0000-000000000000'));
    }
    $update('employment_insurance', 'APPLICABLE', null, $sourceId);
    $run('source_id_update_restrict', false, fn() => $pdo->exec(
        "UPDATE system_codes SET id='20260828-0701-4000-8000-000000000099' WHERE id='{$sourceId}'"
    ));
    $run('source_delete_restrict', false, fn() => $pdo->exec("DELETE FROM system_codes WHERE id='{$sourceId}'"));
} finally {
    $pdo->rollBack();
}

$remaining = (int) $pdo->query(
    "SELECT COUNT(*) FROM institution_daily_employment_income_groups WHERE "
    . "employment_insurance_application_status_code IS NOT NULL OR employment_insurance_decision_reason IS NOT NULL "
    . "OR employment_insurance_decision_source_code_id IS NOT NULL OR industrial_accident_application_status_code IS NOT NULL "
    . "OR industrial_accident_decision_reason IS NOT NULL OR industrial_accident_decision_source_code_id IS NOT NULL"
)->fetchColumn();
if ($remaining !== 0) throw new RuntimeException('Rollback Fixture 값이 잔존합니다.');
echo json_encode(['success'=>true,'cases'=>$cases,'remaining'=>$remaining], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), PHP_EOL;
