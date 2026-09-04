<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

if (($argv[1] ?? '') !== '--apply') throw new RuntimeException('운영 적용에는 --apply 인자가 필요합니다.');

$db = DbPdo::conn();
$database = (string) $db->query('SELECT DATABASE()')->fetchColumn();
if ($database === '' || str_contains(strtolower($database), 'fixture')) throw new RuntimeException('운영 데이터베이스 이름을 확인해 주세요.');

$tables = [
    'institution_regular_employment_incomes',
    'institution_daily_employment_incomes',
    'institution_business_incomes',
    'ledger_evidence_salary_report',
    'ledger_evidence_daily_employment_income',
    'ledger_evidence_business_income',
];
$rowCounts = [];
foreach ($tables as $table) $rowCounts[$table] = (int) $db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
$triggerBefore = (int) $db->query('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchColumn();

$sql = (string) file_get_contents(PROJECT_ROOT . '/app/migrations/20260903_22_add_income_withholding_dates.up.sql');
foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) $db->exec($statement);

$expected = [
    'institution_regular_employment_incomes' => 'withholding_date',
    'institution_daily_employment_incomes' => 'withholding_date',
    'institution_business_incomes' => 'withholding_date',
    'ledger_evidence_salary_report' => 'raw_withholding_date',
    'ledger_evidence_daily_employment_income' => 'raw_withholding_date',
    'ledger_evidence_business_income' => 'raw_withholding_date',
];
foreach ($expected as $table => $column) {
    $check = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND COLUMN_NAME=:column');
    $check->execute([':table' => $table, ':column' => $column]);
    if ((int) $check->fetchColumn() !== 1) throw new RuntimeException("{$table}.{$column} 적용을 확인할 수 없습니다.");
    if ((int) $db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn() !== $rowCounts[$table]) throw new RuntimeException("{$table} 행 수가 변경되었습니다.");
}
$triggerAfter = (int) $db->query('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchColumn();
if ($triggerBefore !== $triggerAfter) throw new RuntimeException('Trigger 수가 변경되었습니다.');

$nullCounts = [];
foreach (array_slice($expected, 0, 3, true) as $table => $column) $nullCounts[$table] = (int) $db->query("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` IS NULL")->fetchColumn();
echo json_encode(['success' => true, 'database' => $database, 'row_counts' => $rowCounts, 'null_withholding_dates' => $nullCounts, 'triggers' => ['before' => $triggerBefore, 'after' => $triggerAfter]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
