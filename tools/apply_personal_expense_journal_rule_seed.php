<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;
use Core\Helpers\ActorHelper;

$mode = strtolower(trim((string) ($argv[1] ?? '')));
if (!in_array($mode, ['preflight', 'apply', 'verify'], true)) {
    throw new InvalidArgumentException('사용법: php tools/apply_personal_expense_journal_rule_seed.php [preflight|apply|verify]');
}

$pdo = DbPdo::conn();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$rules = ['PE_DEBIT_TAXES_AND_DUES','PE_DEBIT_FEES_AND_COMMISSIONS','PE_DEBIT_SUPPLIES','PE_DEBIT_TRANSPORTATION','PE_DEBIT_MEAL','PE_CREDIT_EMPLOYEE_ACCRUED'];
$quotedRules = implode(',', array_map([$pdo, 'quote'], $rules));
$snapshot = static function () use ($pdo, $quotedRules): array {
    $scalar = static fn(string $sql): mixed => $pdo->query($sql)->fetchColumn();
    return [
        'database' => (string) $scalar('SELECT DATABASE()'),
        'version' => (string) $scalar('SELECT VERSION()'),
        'companies' => (int) $scalar('SELECT COUNT(*) FROM system_company'),
        'rules_total' => (int) $scalar('SELECT COUNT(*) FROM ledger_journal_rules'),
        'revisions_total' => (int) $scalar('SELECT COUNT(*) FROM ledger_journal_rule_revisions'),
        'seed_rules' => (int) $scalar("SELECT COUNT(*) FROM ledger_journal_rules WHERE rule_code IN ({$quotedRules})"),
        'seed_revisions' => (int) $scalar("SELECT COUNT(*) FROM ledger_journal_rule_revisions rv JOIN ledger_journal_rules r ON r.id=rv.rule_id WHERE r.rule_code IN ({$quotedRules}) AND rv.action_code='CREATE'"),
        'roles' => (int) $scalar("SELECT COUNT(*) FROM system_codes WHERE code_group='JOURNAL_ACCOUNTING_ROLE' AND is_active=1"),
        'categories' => (int) $scalar("SELECT COUNT(*) FROM system_codes WHERE code_group='PERSONAL_EXPENSE_CATEGORY' AND code IN ('TAXES_AND_DUES','FEES_AND_COMMISSIONS','SUPPLIES','TRANSPORTATION','MEAL') AND is_active=1"),
        'accounts' => (int) $scalar("SELECT COUNT(*) FROM ledger_accounts WHERE account_code IN ('551091','551200','551220','551040','551030','216100') AND is_active=1 AND COALESCE(is_posting,1)=1 AND deleted_at IS NULL"),
        'evidence_links' => (int) $scalar('SELECT COUNT(*) FROM ledger_evidence_links'),
        'transactions' => (int) $scalar('SELECT COUNT(*) FROM ledger_transactions'),
        'vouchers' => (int) $scalar('SELECT COUNT(*) FROM ledger_vouchers'),
        'personal_expense_items' => (int) $scalar('SELECT COUNT(*) FROM approval_personal_expense_items'),
    ];
};
$before = $snapshot();
$ready = $before['database'] === 'sukhyang'
    && str_contains($before['version'], '10.11.11-MariaDB')
    && $before['companies'] === 1
    && (($before['rules_total'] === 0 && $before['revisions_total'] === 0) || ($before['seed_rules'] === 6 && $before['seed_revisions'] === 6))
    && $before['roles'] === 7 && $before['categories'] === 5 && $before['accounts'] === 6;
if ($mode === 'preflight') {
    echo json_encode(['success' => $ready, 'state' => $before], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($ready ? 0 : 2);
}
if ($mode === 'apply') {
    if (!$ready) throw new RuntimeException('운영 Seed Preflight가 승인 기준과 다릅니다.');
    $pdo->exec('SET @personal_expense_journal_rule_seed_actor=' . $pdo->quote(ActorHelper::system('PERSONAL_EXPENSE_ROLE_RULE_SEED')));
    $file = PROJECT_ROOT . '/app/migrations/20260825_02_seed_personal_expense_role_based_journal_rules.up.sql';
    $delimiter = ';';
    $buffer = '';
    foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents($file)) as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) { $delimiter = $match[1]; continue; }
        $buffer .= $line . "\n";
        $trimmed = rtrim($buffer, "\r\n");
        if (!str_ends_with($trimmed, $delimiter)) continue;
        $statement = trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($statement !== '') $pdo->exec($statement);
        $buffer = '';
    }
    if (trim($buffer) !== '') throw new RuntimeException('Migration SQL 구분자가 닫히지 않았습니다.');
}
$after = $snapshot();
$businessKeys = ['evidence_links','transactions','vouchers','personal_expense_items'];
$businessUnchanged = array_intersect_key($before, array_flip($businessKeys)) === array_intersect_key($after, array_flip($businessKeys));
$success = $after['seed_rules'] === 6 && $after['seed_revisions'] === 6 && $businessUnchanged;
echo json_encode(['success' => $success, 'before' => $before, 'after' => $after, 'business_data_unchanged' => $businessUnchanged], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($success ? 0 : 3);
