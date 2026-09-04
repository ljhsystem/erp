<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$db = DbPdo::conn();
$lineTable = 'institution_daily_employment_income_lines';
$groups = [
    'INCOME_STATUTORY_CALCULATION_SOURCE' => ['STATUTORY_RESOLVER', 'MANUAL_CALCULATION', 'UNRESOLVED'],
    'INCOME_ACTUAL_APPLICATION_SOURCE' => ['AUTO_APPLIED', 'MANUAL_OVERRIDE', 'HISTORICAL_ACTUAL', 'SETTLEMENT', 'UNCONFIRMED'],
    'INCOME_PAYMENT_CONFIRMATION_STATUS' => ['UNCONFIRMED', 'PARTIALLY_CONFIRMED', 'CONFIRMED'],
    'INCOME_STATUTORY_CALCULATION_STATUS' => ['UNRESOLVED', 'PARTIALLY_CALCULATED', 'CALCULATED', 'RECONCILIATION_REQUIRED', 'ERROR'],
];
$newColumns = [
    'workday_scope_key', 'calculated_amount', 'adjustment_amount', 'adjustment_reason',
    'statutory_calculation_source_code_id', 'actual_application_source_code_id', 'processed_at', 'processed_by',
];
$originalColumns = [
    'id','sort_no','daily_employment_income_item_id','daily_employment_income_workday_id','line_type_code','line_code',
    'line_name_snapshot','application_status_code','calculation_basis_amount','calculation_rate',
    'calculation_before_rounding','rounding_method_code','rounding_unit','statutory_standard_id','coverage_id',
    'social_insurance_workplace_id','final_amount','created_at','created_by','updated_at','updated_by',
];

$fetchColumns = static function () use ($db, $lineTable): array {
    return $db->query(
        "SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,GENERATION_EXPRESSION"
        . " FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$lineTable}'"
        . ' ORDER BY ORDINAL_POSITION'
    )->fetchAll(PDO::FETCH_ASSOC);
};
$indexDefinition = static function (string $name) use ($db, $lineTable): ?string {
    $statement = $db->prepare(
        'SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) FROM information_schema.STATISTICS'
        . ' WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name AND INDEX_NAME=:index_name GROUP BY INDEX_NAME'
    );
    $statement->execute([':table_name' => $lineTable, ':index_name' => $name]);
    $value = $statement->fetchColumn();
    return is_string($value) ? $value : null;
};
$duplicates = static function (string $scopeExpression) use ($db, $lineTable): array {
    return $db->query(
        "SELECT daily_employment_income_item_id,{$scopeExpression} scope_key,line_type_code,line_code,COUNT(*) row_count"
        . " FROM {$lineTable} GROUP BY daily_employment_income_item_id,scope_key,line_type_code,line_code HAVING COUNT(*)>1"
    )->fetchAll(PDO::FETCH_ASSOC);
};
$lineSnapshot = static function () use ($db, $lineTable, $originalColumns): array {
    return $db->query(
        'SELECT ' . implode(',', $originalColumns) . " FROM {$lineTable} ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC);
};

$columnsBefore = $fetchColumns();
$columnNamesBefore = array_column($columnsBefore, 'COLUMN_NAME');
$presentNewColumns = array_values(array_intersect($newColumns, $columnNamesBefore));
$oldIndex = $indexDefinition('uq_daily_income_line');
$newIndex = $indexDefinition('uq_daily_income_line_scope');
$fullyApplied = count($presentNewColumns) === count($newColumns)
    && $newIndex === 'daily_employment_income_item_id,workday_scope_key,line_type_code,line_code'
    && $oldIndex === null;
if ($presentNewColumns !== [] && !$fullyApplied) {
    throw new RuntimeException(json_encode([
        'status' => 'PARTIAL_APPLICATION',
        'present_new_columns' => $presentNewColumns,
        'old_index' => $oldIndex,
        'new_index' => $newIndex,
        'required_action' => '현재 단계의 정확한 DDL 완료 여부를 확인한 뒤 별도 재개 승인을 받아야 합니다.',
    ], JSON_UNESCAPED_UNICODE));
}

$targetRows = $db->query(
    "SELECT code_group,code FROM system_codes WHERE code_group IN ('"
    . implode("','", array_keys($groups)) . "') ORDER BY code_group,sort_no,code"
)->fetchAll(PDO::FETCH_ASSOC);
$expectedPairs = [];
foreach ($groups as $group => $codes) foreach ($codes as $code) $expectedPairs[] = $group . '|' . $code;
$actualPairs = array_map(static fn(array $row): string => $row['code_group'] . '|' . $row['code'], $targetRows);
sort($expectedPairs);
sort($actualPairs);
if ($actualPairs !== [] && $actualPairs !== $expectedPairs) {
    throw new RuntimeException(json_encode([
        'status' => 'CODE_SSOT_PARTIAL_OR_DIFFERENT', 'actual' => $actualPairs, 'expected' => $expectedPairs,
    ], JSON_UNESCAPED_UNICODE));
}

$countBefore = (int) $db->query("SELECT COUNT(*) FROM {$lineTable}")->fetchColumn();
$workdayBefore = (int) $db->query("SELECT COUNT(*) FROM {$lineTable} WHERE daily_employment_income_workday_id IS NOT NULL")->fetchColumn();
$itemBefore = (int) $db->query("SELECT COUNT(*) FROM {$lineTable} WHERE daily_employment_income_workday_id IS NULL")->fetchColumn();
$duplicateBefore = $duplicates("COALESCE(daily_employment_income_workday_id,'ITEM')");
$amountBefore = $db->query("SELECT SUM(final_amount) line_total FROM {$lineTable}")->fetch(PDO::FETCH_ASSOC);
$draftBefore = $db->query(
    "SELECT id,total_gross_amount,total_deduction_amount,total_net_payment_amount"
    . " FROM institution_daily_employment_incomes WHERE status_code='DRAFT' AND deleted_at IS NULL"
    . ' ORDER BY updated_at DESC,id DESC LIMIT 1'
)->fetch(PDO::FETCH_ASSOC);
$snapshotBefore = $lineSnapshot();
$hashBefore = hash('sha256', json_encode($snapshotBefore, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
if (!$fullyApplied && ($countBefore !== 30 || $workdayBefore !== 30 || $itemBefore !== 0 || $duplicateBefore !== []
    || $oldIndex !== 'daily_employment_income_item_id,daily_employment_income_workday_id,line_type_code,line_code')) {
    throw new RuntimeException(json_encode([
        'status' => 'PRECHECK_MISMATCH', 'line_count' => $countBefore, 'workday_count' => $workdayBefore,
        'item_count' => $itemBefore, 'duplicates' => $duplicateBefore, 'old_index' => $oldIndex,
    ], JSON_UNESCAPED_UNICODE));
}

$stages = [];
if ($actualPairs === []) {
    $db->exec((string) file_get_contents(PROJECT_ROOT . '/app/migrations/20260828_03_seed_income_calculation_code_ssot.up.sql'));
    $stages[] = 'CODE_SEED';
}
if (!$fullyApplied) {
    $migration = (string) file_get_contents(PROJECT_ROOT . '/app/migrations/20260828_04_add_daily_income_line_adjustment_contract.up.sql');
    $statements = array_values(array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $migration) ?: [])));
    if (count($statements) !== 5) throw new RuntimeException('Line Migration 단계 수가 승인 계약과 다릅니다.');
    $db->exec($statements[0]);
    $stages[] = 'ADD_NULLABLE_COLUMNS';
    $db->exec($statements[1]);
    $stages[] = 'BACKFILL_SCOPE';
    $scopeNull = (int) $db->query("SELECT COUNT(*) FROM {$lineTable} WHERE workday_scope_key IS NULL")->fetchColumn();
    $scopeMismatch = (int) $db->query(
        "SELECT COUNT(*) FROM {$lineTable} WHERE (daily_employment_income_workday_id IS NULL AND workday_scope_key<>'ITEM')"
        . ' OR (daily_employment_income_workday_id IS NOT NULL AND workday_scope_key<>daily_employment_income_workday_id)'
    )->fetchColumn();
    $duplicateAfterBackfill = $duplicates('workday_scope_key');
    if ($scopeNull !== 0 || $scopeMismatch !== 0 || $duplicateAfterBackfill !== []) {
        throw new RuntimeException(json_encode([
            'status' => 'BACKFILL_VALIDATION_FAILED', 'scope_null' => $scopeNull,
            'scope_mismatch' => $scopeMismatch, 'duplicates' => $duplicateAfterBackfill,
            'required_action' => '기존 UK를 유지한 상태에서 Backfill 값을 조사해야 합니다.',
        ], JSON_UNESCAPED_UNICODE));
    }
    $db->exec($statements[2]);
    $stages[] = 'SCOPE_NOT_NULL';
    if ($indexDefinition('uq_daily_income_line') !== 'daily_employment_income_item_id,daily_employment_income_workday_id,line_type_code,line_code') {
        throw new RuntimeException('기존 UK 정의가 승인 계약과 달라 UK 전환을 중단했습니다.');
    }
    if ($duplicates('workday_scope_key') !== []) throw new RuntimeException('UK 생성 직전 중복이 발견되었습니다.');
    $db->exec($statements[3]);
    $stages[] = 'SWITCH_SCOPE_UK';
    $db->exec($statements[4]);
    $stages[] = 'ADD_CHECKS_AND_FKS';
}

$snapshotAfter = $lineSnapshot();
$hashAfter = hash('sha256', json_encode($snapshotAfter, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
$final = $db->query(
    "SELECT COUNT(*) line_count,SUM(daily_employment_income_workday_id IS NOT NULL) workday_count,"
    . " SUM(daily_employment_income_workday_id IS NULL) item_count,SUM(workday_scope_key IS NULL) scope_null_count,"
    . " SUM(calculated_amount IS NOT NULL) calculated_value_count,SUM(adjustment_amount IS NOT NULL) adjustment_value_count,"
    . " SUM(statutory_calculation_source_code_id IS NOT NULL) statutory_source_count,"
    . " SUM(actual_application_source_code_id IS NOT NULL) actual_source_count,SUM(final_amount) line_total"
    . " FROM {$lineTable}"
)->fetch(PDO::FETCH_ASSOC);
$draftAfter = $db->query(
    "SELECT id,total_gross_amount,total_deduction_amount,total_net_payment_amount"
    . " FROM institution_daily_employment_incomes WHERE status_code='DRAFT' AND deleted_at IS NULL"
    . ' ORDER BY updated_at DESC,id DESC LIMIT 1'
)->fetch(PDO::FETCH_ASSOC);
$checks = $db->query(
    "SELECT CONSTRAINT_NAME,CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS"
    . " WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME LIKE 'ck_daily_income_line_%' ORDER BY CONSTRAINT_NAME"
)->fetchAll(PDO::FETCH_ASSOC);
$foreignKeys = $db->query(
    "SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE"
    . " WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$lineTable}'"
    . " AND CONSTRAINT_NAME LIKE 'fk_daily_income_line_%' ORDER BY CONSTRAINT_NAME"
)->fetchAll(PDO::FETCH_ASSOC);
if ($hashBefore !== $hashAfter || $draftBefore !== $draftAfter || (string) $amountBefore['line_total'] !== (string) $final['line_total']) {
    throw new RuntimeException('승인 범위 밖 기존 Line 또는 DRAFT 값 변경이 감지되었습니다.');
}

echo json_encode([
    'migration_ids' => ['20260828_03_seed_income_calculation_code_ssot', '20260828_04_add_daily_income_line_adjustment_contract'],
    'stages' => $stages,
    'code_count' => count($expectedPairs),
    'before' => ['line_count' => $countBefore, 'workday_count' => $workdayBefore, 'item_count' => $itemBefore, 'line_total' => $amountBefore['line_total'], 'line_hash' => $hashBefore, 'draft' => $draftBefore],
    'after' => $final + ['line_hash' => $hashAfter, 'draft' => $draftAfter],
    'new_index' => $indexDefinition('uq_daily_income_line_scope'),
    'old_index' => $indexDefinition('uq_daily_income_line'),
    'checks' => $checks,
    'foreign_keys' => $foreignKeys,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
