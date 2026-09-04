<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

$pdo = DbPdo::conn();
$source = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$test = 'codex_regular_income_closure_' . bin2hex(random_bytes(5));
if (!preg_match('/^codex_regular_income_closure_[0-9a-f]{10}$/', $test)) throw new RuntimeException('허용된 격리 DB 이름이 아닙니다.');
$execute = static function (PDO $pdo, string $file): void {
    $delimiter = ';';
    $buffer = '';
    foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents(PROJECT_ROOT . '/app/migrations/' . $file)) as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) { $delimiter = $match[1]; continue; }
        $buffer .= $line . "\n";
        $trimmed = rtrim($buffer, "\r\n");
        if (!str_ends_with($trimmed, $delimiter)) continue;
        $statement = trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($statement !== '') $pdo->exec($statement);
        $buffer = '';
    }
};

$pdo->exec("CREATE DATABASE `{$test}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
try {
    foreach (['system_codes','institution_regular_employment_income_accounting_links','institution_regular_income_accounting_schedules','ledger_payment_schedules','ledger_evidence_salary_report'] as $table) {
        $pdo->exec("CREATE TABLE `{$test}`.`{$table}` LIKE `{$source}`.`{$table}`");
    }
    $pdo->exec("USE `{$test}`");
    $execute($pdo, '20260826_03_separate_regular_income_closure_from_payment.up.sql');
    $scheduleRemoved = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_income_accounting_schedules'")->fetchColumn() === 0;
    $statusSeeded = (int) $pdo->query("SELECT COUNT(*) FROM system_codes WHERE code_group='EVIDENCE_STATUS' AND code='CLASSIFICATION_PENDING'")->fetchColumn() === 1;
    $check = (string) $pdo->query("SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_regular_income_accounting_role_fields'")->fetchColumn();
    $closureNullAllowed = str_contains($check, "`payment_schedule_id` is null") || str_contains(strtolower($check), 'payment_schedule_id` is null');
    $execute($pdo, '20260826_03_separate_regular_income_closure_from_payment.down.sql');
    $scheduleRestored = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_income_accounting_schedules'")->fetchColumn() === 1;
    $execute($pdo, '20260826_03_separate_regular_income_closure_from_payment.up.sql');
    $pdo->exec("INSERT INTO ledger_evidence_salary_report SELECT * FROM `{$source}`.ledger_evidence_salary_report WHERE 1=0");
    $downGuardDeclared = str_contains((string) file_get_contents(PROJECT_ROOT . '/app/migrations/20260826_03_separate_regular_income_closure_from_payment.down.sql'), '신규 Closure 데이터가 있어');
    $result = compact('scheduleRemoved','statusSeeded','closureNullAllowed','scheduleRestored','downGuardDeclared');
    if (in_array(false, $result, true)) throw new RuntimeException('격리 Migration 검증 실패: ' . json_encode($result));
    echo json_encode(['success'=>true,'checks'=>$result], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} finally {
    $pdo->exec("USE `{$source}`");
    $pdo->exec("DROP DATABASE `{$test}`");
}
