<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$db = DbPdo::conn();
$apply = in_array('--apply', $argv, true);
$table = 'institution_daily_employment_income_lines';
$createdAt = '2026-08-28 12:43:29';
$updatedAt = '2026-08-28 12:47:06';
$logPath = PROJECT_ROOT . '/storage/logs/daily_income_line_scope_timestamp_recovery_20260828.json';

$fetchTargets = static function (bool $lock = false) use ($db, $table, $createdAt, $updatedAt): array {
    $sql = "SELECT id,daily_employment_income_item_id,daily_employment_income_workday_id,"
        . "workday_scope_key,line_type_code,line_code,final_amount,calculated_amount,adjustment_amount,"
        . "adjustment_reason,statutory_calculation_source_code_id,actual_application_source_code_id,"
        . "processed_at,processed_by,created_at,created_by,updated_at,updated_by"
        . " FROM {$table} WHERE created_at=:created_at AND updated_at=:updated_at ORDER BY id";
    if ($lock) $sql .= ' FOR UPDATE';
    $statement = $db->prepare($sql);
    $statement->execute([':created_at' => $createdAt, ':updated_at' => $updatedAt]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
};
$fetchAllLines = static function () use ($db, $table): array {
    return $db->query(
        "SELECT id,daily_employment_income_item_id,daily_employment_income_workday_id,workday_scope_key,"
        . "line_type_code,line_code,final_amount,calculated_amount,adjustment_amount,adjustment_reason,"
        . "statutory_calculation_source_code_id,actual_application_source_code_id,processed_at,processed_by,"
        . "created_at,created_by,updated_at,updated_by FROM {$table} ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC);
};
$withoutUpdatedAt = static function (array $rows): array {
    return array_map(static function (array $row): array {
        unset($row['updated_at']);
        return $row;
    }, $rows);
};
$hashRows = static fn(array $rows): string => hash(
    'sha256',
    json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)
);
$schemaSnapshot = static function () use ($db, $table): array {
    $indexes = $db->prepare(
        'SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) columns_list'
        . ' FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name'
        . " AND INDEX_NAME='uq_daily_income_line_scope' GROUP BY INDEX_NAME,NON_UNIQUE"
    );
    $indexes->execute([':table_name' => $table]);
    $checks = $db->prepare(
        'SELECT tc.CONSTRAINT_NAME,cc.CHECK_CLAUSE FROM information_schema.TABLE_CONSTRAINTS tc'
        . ' JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA'
        . ' AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME WHERE tc.CONSTRAINT_SCHEMA=DATABASE()'
        . ' AND tc.TABLE_NAME=:table_name AND tc.CONSTRAINT_TYPE=\'CHECK\''
        . " AND tc.CONSTRAINT_NAME LIKE 'ck_daily_income_line_%' ORDER BY tc.CONSTRAINT_NAME"
    );
    $checks->execute([':table_name' => $table]);
    $foreignKeys = $db->prepare(
        'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME'
        . ' FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name'
        . " AND CONSTRAINT_NAME LIKE 'fk_daily_income_line_%' ORDER BY CONSTRAINT_NAME,COLUMN_NAME"
    );
    $foreignKeys->execute([':table_name' => $table]);
    return [
        'unique_index' => $indexes->fetchAll(PDO::FETCH_ASSOC),
        'checks' => $checks->fetchAll(PDO::FETCH_ASSOC),
        'foreign_keys' => $foreignKeys->fetchAll(PDO::FETCH_ASSOC),
    ];
};
$draftSnapshot = static function () use ($db, $createdAt, $updatedAt): array {
    $statement = $db->prepare(
        'SELECT DISTINCT h.id,h.status_code,h.total_gross_amount,h.total_deduction_amount,h.total_net_payment_amount'
        . ' FROM institution_daily_employment_incomes h'
        . ' JOIN institution_daily_employment_income_groups g ON g.daily_employment_income_id=h.id'
        . ' JOIN institution_daily_employment_income_items i ON i.daily_employment_income_group_id=g.id'
        . ' JOIN institution_daily_employment_income_lines l ON l.daily_employment_income_item_id=i.id'
        . ' WHERE l.created_at=:created_at AND l.updated_at=:updated_at ORDER BY h.id'
    );
    $statement->execute([':created_at' => $createdAt, ':updated_at' => $updatedAt]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
};
$assertPreflight = static function (array $targets, array $allLines, array $schema, array $draft) use ($createdAt, $updatedAt): void {
    if (count($targets) !== 30) throw new RuntimeException('복구 조건 대상이 정확히 30건이 아닙니다.');
    if (count($allLines) !== 30) throw new RuntimeException('대상 조건 밖 Line이 존재합니다.');
    if (array_column($targets, 'id') !== array_column($allLines, 'id')) throw new RuntimeException('복구 대상과 전체 Scope Backfill Line ID가 일치하지 않습니다.');
    foreach ($targets as $row) {
        if ($row['created_at'] !== $createdAt || $row['updated_at'] !== $updatedAt) throw new RuntimeException('대상 timestamp가 승인 조건과 다릅니다.');
        $expectedScope = $row['daily_employment_income_workday_id'] ?: 'ITEM';
        if ($row['workday_scope_key'] !== $expectedScope) throw new RuntimeException('Scope Backfill 대상값이 Workday Scope 계약과 다릅니다.');
        if ($row['calculated_amount'] !== null || $row['adjustment_amount'] !== null || $row['adjustment_reason'] !== null
            || $row['statutory_calculation_source_code_id'] !== null || $row['actual_application_source_code_id'] !== null
            || $row['processed_at'] !== null || $row['processed_by'] !== null) {
            throw new RuntimeException('Scope Backfill 이후 계산·조정·원천 값이 변경된 Line이 있습니다.');
        }
        if ($row['created_by'] !== $row['updated_by']) throw new RuntimeException('생성자와 수정자가 달라 사용자 수정 가능성을 배제할 수 없습니다.');
    }
    $sum = array_sum(array_map(static fn(array $row): float => (float) $row['final_amount'], $targets));
    if (abs($sum - 452940.0) > 0.001) throw new RuntimeException('Line 금액 합계가 452,940원이 아닙니다.');
    if (count($schema['unique_index']) !== 1 || (int) $schema['unique_index'][0]['NON_UNIQUE'] !== 0
        || $schema['unique_index'][0]['columns_list'] !== 'daily_employment_income_item_id,workday_scope_key,line_type_code,line_code') {
        throw new RuntimeException('Line Scope UK가 승인 계약과 다릅니다.');
    }
    $checkNames = array_column($schema['checks'], 'CONSTRAINT_NAME');
    $fkNames = array_column($schema['foreign_keys'], 'CONSTRAINT_NAME');
    foreach (['ck_daily_income_line_scope', 'ck_daily_income_line_adjustment_reason', 'ck_daily_income_line_adjustment_reason_required', 'ck_daily_income_line_non_negative_actual'] as $name) {
        if (!in_array($name, $checkNames, true)) throw new RuntimeException("CHECK {$name}이 없습니다.");
    }
    foreach (['fk_daily_income_line_statutory_source_code', 'fk_daily_income_line_actual_source_code'] as $name) {
        if (!in_array($name, $fkNames, true)) throw new RuntimeException("FK {$name}이 없습니다.");
    }
    if (count($draft) !== 1 || $draft[0]['status_code'] !== 'DRAFT'
        || (float) $draft[0]['total_gross_amount'] !== 452940.0
        || (float) $draft[0]['total_deduction_amount'] !== 0.0
        || (float) $draft[0]['total_net_payment_amount'] !== 452940.0) {
        throw new RuntimeException('운영 DRAFT 합계가 승인된 불변값과 다릅니다.');
    }
};

$targetsBefore = $fetchTargets();
$allBefore = $fetchAllLines();
$schemaBefore = $schemaSnapshot();
$draftBefore = $draftSnapshot();
$assertPreflight($targetsBefore, $allBefore, $schemaBefore, $draftBefore);
$beforeHash = $hashRows($withoutUpdatedAt($allBefore));
$log = [
    'status' => $apply ? 'PRECHECK_PASSED' : 'DRY_RUN_PASSED',
    'approved_update' => "UPDATE {$table} SET updated_at = created_at WHERE created_at = '{$createdAt}' AND updated_at = '{$updatedAt}'",
    'pre_migration_evidence' => 'Migration 실행 직전 검사에서 30개 기존 Line의 updated_at이 created_at과 동일했고, Scope Backfill 직후 전 Line timestamp만 동일 시각으로 변경된 사실을 확인함.',
    'before' => ['target_count' => count($targetsBefore), 'line_count' => count($allBefore), 'line_hash_without_updated_at' => $beforeHash, 'targets' => $targetsBefore, 'draft' => $draftBefore, 'schema' => $schemaBefore],
];

if (!$apply) {
    file_put_contents($logPath, json_encode($log, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL);
    echo json_encode(['status' => $log['status'], 'target_count' => count($targetsBefore), 'target_ids' => array_column($targetsBefore, 'id'), 'log' => $logPath], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
    exit(0);
}

try {
    $db->beginTransaction();
    $lockedTargets = $fetchTargets(true);
    $lockedAll = $fetchAllLines();
    $assertPreflight($lockedTargets, $lockedAll, $schemaBefore, $draftBefore);
    if ($hashRows($withoutUpdatedAt($lockedAll)) !== $beforeHash) throw new RuntimeException('Transaction 진입 직전 Line 불변값이 달라졌습니다.');

    $update = $db->prepare(
        "UPDATE {$table} SET updated_at = created_at"
        . ' WHERE created_at = :created_at AND updated_at = :updated_at'
    );
    $update->execute([':created_at' => $createdAt, ':updated_at' => $updatedAt]);
    if ($update->rowCount() !== 30) throw new RuntimeException('실제 수정 행 수가 30건이 아닙니다.');

    $allAfter = $fetchAllLines();
    $schemaAfter = $schemaSnapshot();
    $draftAfter = $db->query(
        'SELECT id,status_code,total_gross_amount,total_deduction_amount,total_net_payment_amount'
        . ' FROM institution_daily_employment_incomes WHERE id=' . $db->quote((string) $draftBefore[0]['id'])
    )->fetchAll(PDO::FETCH_ASSOC);
    $restored = array_values(array_filter($allAfter, static fn(array $row): bool => $row['created_at'] === $createdAt && $row['updated_at'] === $createdAt));
    if (count($restored) !== 30) throw new RuntimeException('복구 후 updated_at=created_at인 대상이 30건이 아닙니다.');
    if (array_column($restored, 'id') !== array_column($targetsBefore, 'id')) throw new RuntimeException('복구 후 대상 ID 목록이 달라졌습니다.');
    if ($hashRows($withoutUpdatedAt($allAfter)) !== $beforeHash) throw new RuntimeException('timestamp 외 Line 값 변경이 감지되었습니다.');
    if ($schemaAfter !== $schemaBefore) throw new RuntimeException('UK·CHECK·FK 변경이 감지되었습니다.');
    if ($draftAfter !== $draftBefore) throw new RuntimeException('운영 DRAFT 합계 변경이 감지되었습니다.');

    $db->commit();
    $log['status'] = 'COMPLETED';
    $log['updated_rows'] = $update->rowCount();
    $log['after'] = ['line_count' => count($allAfter), 'line_hash_without_updated_at' => $hashRows($withoutUpdatedAt($allAfter)), 'targets' => $restored, 'draft' => $draftAfter, 'schema' => $schemaAfter];
    $log['outside_scope_changes'] = 0;
    file_put_contents($logPath, json_encode($log, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL);
    echo json_encode(['status' => 'COMPLETED', 'updated_rows' => $update->rowCount(), 'target_ids' => array_column($restored, 'id'), 'line_total' => array_sum(array_column($restored, 'final_amount')), 'draft' => $draftAfter, 'outside_scope_changes' => 0, 'log' => $logPath], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
} catch (Throwable $exception) {
    if ($db->inTransaction()) $db->rollBack();
    $log['status'] = 'ROLLED_BACK';
    $log['error'] = $exception->getMessage();
    file_put_contents($logPath, json_encode($log, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL);
    throw $exception;
}
