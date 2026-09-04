<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

$pdo = DbPdo::conn();
$sourceDatabase = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$testDatabase = 'codex_personal_expense_classification_test';
if ($testDatabase !== 'codex_personal_expense_classification_test') {
    throw new RuntimeException('허용된 격리 테스트 DB 이름이 아닙니다.');
}
$execute = static function (PDO $pdo, string $file): void {
    $delimiter = ';';
    $buffer = '';
    foreach (preg_split('/\R/', (string) file_get_contents($file)) as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) {
            $delimiter = $match[1];
            continue;
        }
        $buffer .= $line . "\n";
        if (!str_ends_with(rtrim($buffer), $delimiter)) {
            continue;
        }
        $statement = trim(substr(rtrim($buffer), 0, -strlen($delimiter)));
        if ($statement !== '') {
            $pdo->exec($statement);
        }
        $buffer = '';
    }
};

$pdo->exec("DROP DATABASE IF EXISTS `{$testDatabase}`");
$pdo->exec("CREATE DATABASE `{$testDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
try {
    foreach (['system_company','approval_personal_expenses','approval_personal_expense_items','user_approval_requests','ledger_evidence_employee_personal_expense'] as $table) {
        $pdo->exec("CREATE TABLE `{$testDatabase}`.`{$table}` LIKE `{$sourceDatabase}`.`{$table}`");
    }
    $pdo->exec("USE `{$testDatabase}`");
    $up = PROJECT_ROOT . '/app/migrations/20260824_13_create_personal_expense_classification_corrections.up.sql';
    $down = PROJECT_ROOT . '/app/migrations/20260824_13_create_personal_expense_classification_corrections.down.sql';
    $execute($pdo, $up);
    $constraintCount = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='approval_personal_expense_item_classification_corrections'")->fetchColumn();
    if ($constraintCount < 13) {
        throw new RuntimeException('분류 정정 테이블의 무결성 제약이 부족합니다.');
    }
    $execute($pdo, $down);
    $existsAfterDown = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='approval_personal_expense_item_classification_corrections'")->fetchColumn();
    if ($existsAfterDown !== 0) {
        throw new RuntimeException('빈 테이블 Down 검증에 실패했습니다.');
    }
    echo "PASS: 개인경비 회계분류 정정 Migration Up/빈 데이터 Down\n";
} finally {
    $pdo->exec("USE `{$sourceDatabase}`");
    $pdo->exec("DROP DATABASE IF EXISTS `{$testDatabase}`");
}
