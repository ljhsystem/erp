<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;
use Core\Helpers\ActorHelper;

$pdo = DbPdo::conn();
$source = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$test = 'codex_role_closure_' . bin2hex(random_bytes(6));
$created = false;
if (!preg_match('/^codex_role_closure_[0-9a-f]{12}$/', $test)) throw new RuntimeException('허용된 격리 DB 이름이 아닙니다.');
$exists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=:name');
$exists->execute([':name' => $test]);
if ((int) $exists->fetchColumn() !== 0) throw new RuntimeException('격리 DB 이름이 이미 사용 중입니다.');

$execute = static function (string $file) use ($pdo): void {
    $delimiter = ';'; $buffer = '';
    foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents(PROJECT_ROOT . '/app/migrations/' . $file)) as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) { $delimiter = $match[1]; continue; }
        $buffer .= $line . "\n";
        $trimmed = rtrim($buffer, "\r\n");
        if (!str_ends_with($trimmed, $delimiter)) continue;
        $statement = trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($statement !== '') $pdo->exec($statement);
        $buffer = '';
    }
    if (trim($buffer) !== '') throw new RuntimeException('SQL 구분자가 닫히지 않았습니다: ' . $file);
};

$pdo->exec("CREATE DATABASE `{$test}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"); $created = true;
try {
    foreach (['system_company','system_codes','ledger_accounts','ledger_accounts_sub','ledger_journal_rules','ledger_journal_rule_revisions'] as $table) {
        $pdo->exec("CREATE TABLE `{$test}`.`{$table}` LIKE `{$source}`.`{$table}`");
    }
    foreach (['system_company','system_codes','ledger_accounts','ledger_accounts_sub'] as $table) {
        $pdo->exec("INSERT INTO `{$test}`.`{$table}` SELECT * FROM `{$source}`.`{$table}`");
    }
    $pdo->exec("USE `{$test}`");
    $pdo->exec('ALTER TABLE ledger_journal_rules ADD CONSTRAINT fk_journal_rules_credit_account FOREIGN KEY (credit_account_id) REFERENCES ledger_accounts (id)');
    $pdo->exec('SET @journal_context_actor=' . $pdo->quote(ActorHelper::system('PERSONAL_EXPENSE_CONTEXT_POLICY')));
    $pdo->exec('SET @personal_expense_category_actor=' . $pdo->quote(ActorHelper::system('PERSONAL_EXPENSE_CATEGORY_CLOSURE')));
    $upFiles = [
        '20260824_10_create_account_context_ref_policies.up.sql',
        '20260824_11_seed_personal_expense_context_policy.up.sql',
        '20260825_01_seed_personal_expense_fees_category.up.sql',
        '20260824_14_extend_role_based_journal_rule_conditions.up.sql',
    ];
    foreach ($upFiles as $file) { $execute($file); echo $file . ": UP OK\n"; }
    $checks = [
        'context_policies' => (int) $pdo->query('SELECT COUNT(*) FROM ledger_account_context_ref_policies')->fetchColumn(),
        'roles' => (int) $pdo->query("SELECT COUNT(*) FROM system_codes WHERE code_group='JOURNAL_ACCOUNTING_ROLE' AND is_active=1")->fetchColumn(),
        'fees_order' => $pdo->query("SELECT GROUP_CONCAT(code ORDER BY sort_no SEPARATOR ',') FROM system_codes WHERE code_group='PERSONAL_EXPENSE_CATEGORY' AND code IN ('TAXES_AND_DUES','FEES_AND_COMMISSIONS','SUPPLIES')")->fetchColumn(),
        'role_columns' => (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_journal_rules' AND COLUMN_NAME IN ('source_type','source_line_type','item_code')")->fetchColumn(),
        'rules' => (int) $pdo->query('SELECT COUNT(*) FROM ledger_journal_rules')->fetchColumn(),
        'revisions' => (int) $pdo->query('SELECT COUNT(*) FROM ledger_journal_rule_revisions')->fetchColumn(),
    ];
    if ($checks !== ['context_policies'=>2,'roles'=>7,'fees_order'=>'TAXES_AND_DUES,FEES_AND_COMMISSIONS,SUPPLIES','role_columns'=>3,'rules'=>0,'revisions'=>0]) {
        throw new RuntimeException('연속 Migration 결과가 승인 계약과 다릅니다: ' . json_encode($checks, JSON_UNESCAPED_UNICODE));
    }
    $execute('20260824_14_extend_role_based_journal_rule_conditions.down.sql');
    $execute('20260824_11_seed_personal_expense_context_policy.down.sql');
    $execute('20260824_10_create_account_context_ref_policies.down.sql');
    echo json_encode(['success'=>true,'checks'=>$checks,'reverse_down'=>'14→11→10 OK','fees_down'=>'forward-only'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL;
} finally {
    $pdo->exec("USE `{$source}`");
    if ($created) $pdo->exec("DROP DATABASE `{$test}`");
}
