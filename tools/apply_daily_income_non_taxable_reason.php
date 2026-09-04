<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$db = DbPdo::conn();
$table = 'institution_daily_employment_income_workdays';
$migration = PROJECT_ROOT . '/app/migrations/20260828_02_add_daily_income_non_taxable_reason.up.sql';
$sql = trim((string) file_get_contents($migration));
$columns = $db->query(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS"
    . " WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}' ORDER BY ORDINAL_POSITION"
)->fetchAll(PDO::FETCH_COLUMN);
$similar = array_values(array_filter(
    $columns,
    static fn(string $column): bool => preg_match('/non_tax.*(reason|evidence|basis)|evidence.*non_tax/i', $column) === 1
));
$rowCount = (int) $db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
$nonZeroCount = (int) $db->query("SELECT COUNT(*) FROM {$table} WHERE non_taxable_amount<>0")->fetchColumn();
$indexesBefore = $db->query("SHOW INDEX FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);

if (in_array('non_taxable_reason', $columns, true) || $similar !== []) {
    throw new RuntimeException(json_encode(['existing_similar_columns' => $similar], JSON_UNESCAPED_UNICODE));
}
if ($nonZeroCount !== 0) {
    throw new RuntimeException('기존 비과세금액이 있어 적용사유 Backfill 승인 없이 Migration을 적용할 수 없습니다.');
}

$db->exec($sql);
$column = $db->query(
    "SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,ORDINAL_POSITION,COLUMN_COMMENT"
    . " FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}'"
    . " AND COLUMN_NAME='non_taxable_reason'"
)->fetch(PDO::FETCH_ASSOC);
$checks = $db->query(
    "SELECT CONSTRAINT_NAME,CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS"
    . " WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME LIKE 'ck_daily_workday_non_taxable_reason%'"
    . ' ORDER BY CONSTRAINT_NAME'
)->fetchAll(PDO::FETCH_ASSOC);
$indexesAfter = $db->query("SHOW INDEX FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'precheck' => ['row_count' => $rowCount, 'non_zero_non_taxable_count' => $nonZeroCount, 'similar_columns' => $similar],
    'column' => $column,
    'checks' => $checks,
    'row_count_after' => (int) $db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn(),
    'reason_value_count_after' => (int) $db->query("SELECT COUNT(*) FROM {$table} WHERE non_taxable_reason IS NOT NULL")->fetchColumn(),
    'indexes_unchanged' => $indexesBefore === $indexesAfter,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
