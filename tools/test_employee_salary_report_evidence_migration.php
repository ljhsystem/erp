<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

$pdo = DbPdo::conn();
$source = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$test = 'tmp_employee_salary_evidence_' . bin2hex(random_bytes(5));
$execute = static function (PDO $db, string $file): void {
    $delimiter = ';';
    $buffer = '';
    foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents($file)) as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) {
            $delimiter = $match[1];
            continue;
        }
        $buffer .= $line . "\n";
        $trimmed = rtrim($buffer, "\r\n");
        if (!str_ends_with($trimmed, $delimiter)) continue;
        $sql = trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($sql !== '') $db->exec($sql);
        $buffer = '';
    }
    if (trim($buffer) !== '') throw new RuntimeException('Migration SQL 구분자가 닫히지 않았습니다.');
};
$expectFailure = static function (callable $callback): bool {
    try { $callback(); } catch (Throwable) { return true; }
    return false;
};

try {
    $tables = ['user_approval_requests', 'user_employees', 'institution_regular_employment_incomes',
        'institution_regular_employment_income_items', 'ledger_evidence_salary_report',
        'institution_regular_employment_income_accounting_links'];
    $creates = [];
    foreach ($tables as $table) {
        $row = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
        $creates[$table] = (string) ($row[1] ?? '');
    }
    $pdo->exec("CREATE DATABASE `{$test}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $pdo->exec("USE `{$test}`");
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($creates as $create) $pdo->exec($create);
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $up = PROJECT_ROOT . '/app/migrations/20260826_05_enable_employee_salary_report_evidence.up.sql';
    $down = PROJECT_ROOT . '/app/migrations/20260826_05_enable_employee_salary_report_evidence.down.sql';
    $execute($pdo, $up);
    $columns = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_salary_report' AND COLUMN_NAME IN ('approval_request_id','regular_employment_income_item_id','raw_employer_burden_amount','approved_at','approved_by')")->fetchColumn();
    $unique = (int) $pdo->query("SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_salary_report' AND INDEX_NAME IN ('uk_salary_report_source_item','uk_salary_report_approval_item') AND NON_UNIQUE=0")->fetchColumn();
    $foreignKeys = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME IN ('fk_salary_report_approval_request','fk_salary_report_income_item','fk_salary_report_employee')")->fetchColumn();
    $roleCheck = (string) $pdo->query("SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_regular_income_accounting_role'")->fetchColumn();
    $registryCorrect = str_contains($roleCheck, 'PAYROLL_REPORT_EVIDENCE') && str_contains($roleCheck, 'EMPLOYEE_PAYROLL') && !str_contains($roleCheck, 'INSTITUTION_LIABILITY');
    $upRepeatBlocked = $expectFailure(static fn() => $execute($pdo, $up));
    $pdo->exec('DROP PROCEDURE IF EXISTS migrate_20260826_05_employee_salary_report_evidence');
    $execute($pdo, $down);
    $restored = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_salary_report' AND INDEX_NAME='uk_salary_report_source_income' AND NON_UNIQUE=0")->fetchColumn() === 1;
    $success = $columns === 5 && $unique === 2 && $foreignKeys === 3 && $registryCorrect && $upRepeatBlocked && $restored;
    echo json_encode(compact('success', 'columns', 'unique', 'foreignKeys', 'registryCorrect', 'upRepeatBlocked', 'restored'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit($success ? 0 : 1);
} finally {
    $pdo->exec("USE `{$source}`");
    $pdo->exec("DROP DATABASE IF EXISTS `{$test}`");
}
