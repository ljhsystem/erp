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
    throw new InvalidArgumentException('사용법: php tools/apply_role_based_journal_rule_closure.php [preflight|apply|verify]');
}
$pdo = DbPdo::conn();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$scalar = static fn (string $sql): mixed => $pdo->query($sql)->fetchColumn();
$rows = static fn (string $sql): array => $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
$columnExists = static function (string $column) use ($pdo): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_journal_rules' AND COLUMN_NAME=:column");
    $stmt->execute([':column' => $column]);
    return (int) $stmt->fetchColumn() === 1;
};
$tableExists = static function (string $table) use ($pdo): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table');
    $stmt->execute([':table' => $table]);
    return (int) $stmt->fetchColumn() === 1;
};
$snapshot = static function () use ($scalar, $rows, $columnExists, $tableExists): array {
    return [
        'rules' => (int) $scalar('SELECT COUNT(*) FROM ledger_journal_rules'),
        'revisions' => (int) $scalar('SELECT COUNT(*) FROM ledger_journal_rule_revisions'),
        'roles' => $rows("SELECT code,code_name,sort_no,is_active FROM system_codes WHERE code_group='JOURNAL_ACCOUNTING_ROLE' ORDER BY sort_no,code"),
        'fees' => $rows("SELECT code,code_name,note,sort_no,is_active FROM system_codes WHERE code_group='PERSONAL_EXPENSE_CATEGORY' AND code='FEES_AND_COMMISSIONS'"),
        'context_policy_table' => $tableExists('ledger_account_context_ref_policies'),
        'context_policies' => $tableExists('ledger_account_context_ref_policies') ? (int) $scalar("SELECT COUNT(*) FROM ledger_account_context_ref_policies WHERE id IN ('10c31fd2-3dbc-4c45-80fa-202608240011','abbe6ee3-f1b2-487f-b476-202608240012')") : 0,
        'role_columns' => array_filter([
            'source_type' => $columnExists('source_type'),
            'source_line_type' => $columnExists('source_line_type'),
            'item_code' => $columnExists('item_code'),
        ]),
        'credit_nullable' => (string) $scalar("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_journal_rules' AND COLUMN_NAME='credit_account_id'"),
        'evidence_links' => (int) $scalar('SELECT COUNT(*) FROM ledger_evidence_links'),
        'transactions' => (int) $scalar('SELECT COUNT(*) FROM ledger_transactions'),
        'vouchers' => (int) $scalar('SELECT COUNT(*) FROM ledger_vouchers'),
        'personal_expense_items' => (int) $scalar('SELECT COUNT(*) FROM approval_personal_expense_items'),
    ];
};
$preflight = $snapshot();
$expectedPreflight = $preflight['rules'] === 0
    && $preflight['revisions'] === 0
    && $preflight['roles'] === []
    && $preflight['fees'] === []
    && $preflight['context_policy_table'] === false
    && $preflight['context_policies'] === 0
    && $preflight['role_columns'] === []
    && $preflight['credit_nullable'] === 'NO';
if ($mode === 'preflight') {
    echo json_encode(['success' => $expectedPreflight, 'preflight' => $preflight], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($expectedPreflight ? 0 : 2);
}
if ($mode === 'verify') {
    echo json_encode(['success' => true, 'state' => $preflight], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}
if (!$expectedPreflight) {
    throw new RuntimeException('운영 Preflight 상태가 승인된 적용 전 상태와 다릅니다.');
}

$execute = static function (string $file) use ($pdo): void {
    $delimiter = ';';
    $buffer = '';
    foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents(PROJECT_ROOT . '/app/migrations/' . $file)) as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) {
            $delimiter = $match[1];
            continue;
        }
        $buffer .= $line . "\n";
        $trimmedBuffer = rtrim($buffer, "\r\n");
        if (!str_ends_with($trimmedBuffer, $delimiter)) continue;
        $statement = trim(substr($trimmedBuffer, 0, -strlen($delimiter)));
        if ($statement !== '') $pdo->exec($statement);
        $buffer = '';
    }
    if (trim($buffer) !== '') throw new RuntimeException('SQL 구분자가 닫히지 않았습니다: ' . $file);
};

$beforeBusiness = array_intersect_key($preflight, array_flip(['evidence_links','transactions','vouchers','personal_expense_items']));
$completed = [];
$pdo->exec('SET @journal_context_actor=' . $pdo->quote(ActorHelper::system('PERSONAL_EXPENSE_CONTEXT_POLICY')));
$pdo->exec('SET @personal_expense_category_actor=' . $pdo->quote(ActorHelper::system('PERSONAL_EXPENSE_CATEGORY_CLOSURE')));
foreach ([
    '20260824_10_create_account_context_ref_policies.up.sql',
    '20260824_11_seed_personal_expense_context_policy.up.sql',
    '20260825_01_seed_personal_expense_fees_category.up.sql',
    '20260824_14_extend_role_based_journal_rule_conditions.up.sql',
] as $file) {
    $execute($file);
    $completed[] = $file;
}
$after = $snapshot();
$afterBusiness = array_intersect_key($after, array_flip(['evidence_links','transactions','vouchers','personal_expense_items']));
$success = count($after['roles']) === 7
    && count($after['fees']) === 1
    && $after['context_policies'] === 2
    && $after['context_policy_table'] === true
    && count($after['role_columns']) === 3
    && $after['credit_nullable'] === 'YES'
    && $beforeBusiness === $afterBusiness;
echo json_encode([
    'success' => $success,
    'completed' => $completed,
    'before' => $preflight,
    'after' => $after,
    'business_data_unchanged' => $beforeBusiness === $afterBusiness,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($success ? 0 : 3);
