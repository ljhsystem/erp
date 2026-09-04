<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

if (($argv[1] ?? '') !== '--apply') {
    throw new RuntimeException('운영 적용에는 --apply 인자가 필요합니다.');
}

$db = DbPdo::conn();
$database = (string) $db->query('SELECT DATABASE()')->fetchColumn();
if ($database === '' || str_contains(strtolower($database), 'fixture')) {
    throw new RuntimeException('운영 데이터베이스 이름을 확인해 주세요.');
}

$columns = [
    'institution_regular_employment_incomes' => 'withholding_date',
    'institution_daily_employment_incomes' => 'withholding_date',
    'institution_business_incomes' => 'withholding_date',
    'ledger_evidence_salary_report' => 'raw_withholding_date',
    'ledger_evidence_daily_employment_income' => 'raw_withholding_date',
    'ledger_evidence_business_income' => 'raw_withholding_date',
];
$rowCounts = [];
foreach ($columns as $table => $column) {
    $rowCounts[$table] = (int) $db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
}
$triggerBefore = (int) $db->query('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchColumn();

$sql = (string) file_get_contents(PROJECT_ROOT . '/app/migrations/20260903_23_correct_income_withholding_date_comments.up.sql');
foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
    $db->exec($statement);
}

$comments = [];
foreach ($columns as $table => $column) {
    if ((int) $db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn() !== $rowCounts[$table]) {
        throw new RuntimeException("{$table} 행 수가 변경되었습니다.");
    }
    $query = $db->prepare('SELECT COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND COLUMN_NAME=:column');
    $query->execute([':table' => $table, ':column' => $column]);
    $comment = (string) $query->fetchColumn();
    if (!str_contains($comment, '기관 신고 및 법정기준 적용일')) {
        throw new RuntimeException("{$table}.{$column} Comment 정비를 확인할 수 없습니다.");
    }
    $comments["{$table}.{$column}"] = $comment;
}
$triggerAfter = (int) $db->query('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchColumn();
if ($triggerBefore !== $triggerAfter) {
    throw new RuntimeException('Trigger 수가 변경되었습니다.');
}

echo json_encode([
    'success' => true,
    'database' => $database,
    'row_counts' => $rowCounts,
    'comments' => $comments,
    'triggers' => ['before' => $triggerBefore, 'after' => $triggerAfter],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
