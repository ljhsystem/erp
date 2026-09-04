<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;
use Core\Helpers\ActorHelper;

$pdo = DbPdo::conn();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$source = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$test = 'codex_personal_rule_seed_' . bin2hex(random_bytes(5));
if (!preg_match('/^codex_personal_rule_seed_[0-9a-f]{10}$/', $test)) throw new RuntimeException('격리 DB 이름이 안전하지 않습니다.');

$execute = static function (PDO $pdo, string $file): void {
    $delimiter = ';'; $buffer = '';
    foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents($file)) as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) { $delimiter = $match[1]; continue; }
        $buffer .= $line . "\n";
        $trimmed = rtrim($buffer, "\r\n");
        if (!str_ends_with($trimmed, $delimiter)) continue;
        $statement = trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($statement !== '') $pdo->exec($statement);
        $buffer = '';
    }
    if (trim($buffer) !== '') throw new RuntimeException('SQL 구분자가 닫히지 않았습니다.');
};
$up = PROJECT_ROOT . '/app/migrations/20260825_02_seed_personal_expense_role_based_journal_rules.up.sql';
$down = PROJECT_ROOT . '/app/migrations/20260825_02_seed_personal_expense_role_based_journal_rules.down.sql';
$pdo->exec("CREATE DATABASE `{$test}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
try {
    foreach (['system_company','system_codes','ledger_accounts','ledger_journal_rules','ledger_journal_rule_revisions'] as $table) {
        $pdo->exec("CREATE TABLE `{$test}`.`{$table}` LIKE `{$source}`.`{$table}`");
    }
    foreach (['system_company','system_codes','ledger_accounts'] as $table) {
        $pdo->exec("INSERT INTO `{$test}`.`{$table}` SELECT * FROM `{$source}`.`{$table}`");
    }
    $pdo->exec("USE `{$test}`");
    $pdo->exec('SET @personal_expense_journal_rule_seed_actor=' . $pdo->quote(ActorHelper::system('PERSONAL_EXPENSE_ROLE_RULE_SEED_TEST')));
    $execute($pdo, $up);
    $first = [
        'rules' => (int) $pdo->query('SELECT COUNT(*) FROM ledger_journal_rules')->fetchColumn(),
        'revisions' => (int) $pdo->query('SELECT COUNT(*) FROM ledger_journal_rule_revisions')->fetchColumn(),
        'legacy_null' => (int) $pdo->query('SELECT COUNT(*) FROM ledger_journal_rules WHERE debit_account_id IS NULL AND credit_account_id IS NULL AND vat_account_id IS NULL')->fetchColumn(),
        'debit_items' => (int) $pdo->query("SELECT COUNT(*) FROM ledger_journal_rules WHERE debit_credit='DEBIT' AND item_code IS NOT NULL")->fetchColumn(),
        'credit_item_null' => (int) $pdo->query("SELECT COUNT(*) FROM ledger_journal_rules WHERE debit_credit='CREDIT' AND item_code IS NULL")->fetchColumn(),
    ];
    if ($first !== ['rules'=>6,'revisions'=>6,'legacy_null'=>6,'debit_items'=>5,'credit_item_null'=>1]) throw new RuntimeException('최초 Up 결과가 다릅니다.');

    $companyId = (string) $pdo->query('SELECT id FROM system_company')->fetchColumn();
    $rows = $pdo->query('SELECT rule_code,item_code,condition_hash FROM ledger_journal_rules')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $condition = ['company_id'=>$companyId,'business_unit'=>'CONSTRUCTION','operation_type'=>'PERSONAL_EXPENSE','transaction_direction'=>'OUT','client_type'=>'','import_type'=>'EMPLOYEE_EXPENSE_PERSONAL','source_type'=>'PERSONAL_EXPENSE_ITEM','source_line_type'=>'ITEM','item_code'=>(string)($row['item_code'] ?? '')];
        ksort($condition);
        $serviceHash = hash('sha256', json_encode($condition, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if (!hash_equals($serviceHash, (string) $row['condition_hash'])) throw new RuntimeException('Service condition_hash 계약이 다릅니다: ' . $row['rule_code']);
    }
    $execute($pdo, $up);
    if ((int)$pdo->query('SELECT COUNT(*) FROM ledger_journal_rules')->fetchColumn() !== 6 || (int)$pdo->query('SELECT COUNT(*) FROM ledger_journal_rule_revisions')->fetchColumn() !== 6) throw new RuntimeException('동일 Payload 재실행 멱등성이 실패했습니다.');

    $downBlocked = false;
    try { $execute($pdo, $down); } catch (PDOException) { $downBlocked = true; }
    if (!$downBlocked) throw new RuntimeException('forward-only Down이 차단되지 않았습니다.');

    $pdo->exec("UPDATE ledger_journal_rules SET rule_name='충돌' WHERE rule_code='PE_DEBIT_MEAL'");
    $payloadConflictBlocked = false;
    try { $execute($pdo, $up); } catch (PDOException) { $payloadConflictBlocked = true; }
    if (!$payloadConflictBlocked) throw new RuntimeException('동일 rule_code 다른 Payload 충돌이 차단되지 않았습니다.');

    echo json_encode(['success'=>true,'database'=>$test,'first_up'=>$first,'same_payload_idempotent'=>true,'service_hash_match'=>true,'payload_conflict_blocked'=>true,'down_forward_only_blocked'=>true], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    $pdo->exec("USE `{$source}`");
    $pdo->exec("DROP DATABASE IF EXISTS `{$test}`");
}
