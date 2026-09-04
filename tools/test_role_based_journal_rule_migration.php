<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

$pdo = DbPdo::conn();
$sourceDatabase = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$testDatabase = 'codex_role_journal_rule_' . bin2hex(random_bytes(6));
$createdTestDatabase = false;
if (!preg_match('/^codex_role_journal_rule_[0-9a-f]{12}$/', $testDatabase)) {
    throw new RuntimeException('허용된 격리 DB 이름이 아닙니다.');
}

$executeSqlFile = static function (PDO $pdo, string $file): void {
    $delimiter = ';';
    $buffer = '';
    foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents($file)) as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) {
            $delimiter = $match[1];
            continue;
        }
        $buffer .= $line . "\n";
        $trimmedBuffer = rtrim($buffer, "\r\n");
        if (!str_ends_with($trimmedBuffer, $delimiter)) {
            continue;
        }
        $statement = trim(substr($trimmedBuffer, 0, -strlen($delimiter)));
        if ($statement !== '') {
            $pdo->exec($statement);
        }
        $buffer = '';
    }
};

$up = PROJECT_ROOT . '/app/migrations/20260824_14_extend_role_based_journal_rule_conditions.up.sql';
$down = PROJECT_ROOT . '/app/migrations/20260824_14_extend_role_based_journal_rule_conditions.down.sql';
$existsStmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=:schema_name');
$existsStmt->execute([':schema_name' => $testDatabase]);
if ((int) $existsStmt->fetchColumn() !== 0) {
    throw new RuntimeException('격리 DB 이름이 이미 사용 중입니다.');
}
$pdo->exec("CREATE DATABASE `{$testDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$createdTestDatabase = true;
try {
    $pdo->exec("CREATE TABLE `{$testDatabase}`.`system_company` LIKE `{$sourceDatabase}`.`system_company`");
    $pdo->exec("CREATE TABLE `{$testDatabase}`.`ledger_accounts` LIKE `{$sourceDatabase}`.`ledger_accounts`");
    $pdo->exec("CREATE TABLE `{$testDatabase}`.`ledger_journal_rules` LIKE `{$sourceDatabase}`.`ledger_journal_rules`");
    $pdo->exec("CREATE TABLE `{$testDatabase}`.`ledger_journal_rule_revisions` LIKE `{$sourceDatabase}`.`ledger_journal_rule_revisions`");
    $pdo->exec("USE `{$testDatabase}`");
    $pdo->exec('ALTER TABLE ledger_journal_rules ADD CONSTRAINT fk_journal_rules_credit_account FOREIGN KEY (credit_account_id) REFERENCES ledger_accounts (id)');

    $executeSqlFile($pdo, $up);
    $nullable = (string) $pdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_journal_rules' AND COLUMN_NAME='credit_account_id'")->fetchColumn();
    $newColumns = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_journal_rules' AND COLUMN_NAME IN ('source_type','source_line_type','item_code') AND IS_NULLABLE='YES'")->fetchColumn();
    if ($nullable !== 'YES' || $newColumns !== 3) {
        throw new RuntimeException('Migration 14 Up 컬럼 검증에 실패했습니다.');
    }
    echo "Migration 14 UP: OK\n";

    $executeSqlFile($pdo, $down);
    echo "Migration 14 EMPTY DOWN: OK\n";
    $executeSqlFile($pdo, $up);

    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $pdo->exec("INSERT INTO ledger_journal_rules
        (id,company_id,sort_no,rule_code,rule_name,import_type,source_type,source_line_type,item_code,condition_hash,origin_code,rule_status,accounting_role_code,debit_credit,account_id,amount_policy_code,is_locked,auto_apply_enabled,priority_no,revision_no,business_unit,transaction_direction,operation_type,debit_account_id,credit_account_id,vat_account_id,is_active)
        VALUES ('00000000-0000-4000-8000-000000000014','company-test',1,'TEST_ROLE_RULE','격리 역할형 규칙','EMPLOYEE_EXPENSE_PERSONAL','PERSONAL_EXPENSE_ITEM','ITEM','MEAL',REPEAT('a',64),'USER','ACTIVE','EXPENSE','DEBIT','account-test','SOURCE_AMOUNT',1,0,100,1,'CONSTRUCTION','OUT','PERSONAL_EXPENSE',NULL,NULL,NULL,1)");
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    try {
        $executeSqlFile($pdo, $down);
        throw new RuntimeException('역할형 데이터가 있는데 Down이 허용되었습니다.');
    } catch (PDOException $exception) {
        if (!str_contains($exception->getMessage(), 'Down Migration')) {
            throw $exception;
        }
    }
    echo "Migration 14 DATA DOWN BLOCK: OK\n";
    echo "PASS: role-based journal rule migration contract\n";
} finally {
    $pdo->exec("USE `{$sourceDatabase}`");
    if ($createdTestDatabase) {
        $pdo->exec("DROP DATABASE `{$testDatabase}`");
    }
}
