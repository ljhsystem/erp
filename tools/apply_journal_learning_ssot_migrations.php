<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Backup\DatabaseBackupService;
use Core\DbPdo;
use Core\Helpers\ActorHelper;
$mode = strtolower(trim((string) ($argv[1] ?? '')));
if (!in_array($mode, ['backup', 'up', 'resume', 'verify'], true)) {
    fwrite(STDERR, "사용법: php tools/apply_journal_learning_ssot_migrations.php [backup|up|resume|verify]\n");
    exit(1);
}

$pdo = DbPdo::conn();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($mode === 'backup') {
    $result = (new DatabaseBackupService($pdo))->backupDatabase();
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(!empty($result['success']) ? 0 : 1);
}

$scalar = static fn(string $sql): mixed => $pdo->query($sql)->fetchColumn();
$rows = static fn(string $sql): array => $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
$tableExists = static function (string $table) use ($pdo): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table');
    $stmt->execute([':table' => $table]);
    return (int) $stmt->fetchColumn() > 0;
};
$columnExists = static function (string $table, string $column) use ($pdo): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND COLUMN_NAME=:column');
    $stmt->execute([':table' => $table, ':column' => $column]);
    return (int) $stmt->fetchColumn() > 0;
};
$removeStaleMigrationProcedure = static function () use ($pdo): void {
    $name = 'migrate_20260824_05_extend_journal_rule_learning_ssot';
    $tableStatement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:name');
    $tableStatement->execute([':name' => $name]);
    if ((int) $tableStatement->fetchColumn() !== 0) {
        throw new RuntimeException('잔존 Migration PROCEDURE 이름으로 TABLE 또는 VIEW가 존재합니다.');
    }
    $statement = $pdo->prepare('SELECT ROUTINE_TYPE,DEFINER,SHA2(ROUTINE_DEFINITION,256) body_sha256 FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME=:name');
    $statement->execute([':name' => $name]);
    $routine = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($routine === null) {
        return;
    }
    if (($routine['ROUTINE_TYPE'] ?? null) !== 'PROCEDURE'
        || ($routine['DEFINER'] ?? null) !== 'sukhyang@%'
        || ($routine['body_sha256'] ?? null) !== '21be8dfaffb4c55ceb356fcbb13ed3a39f1af8a49317ef68dcd93d04af21512f') {
        throw new RuntimeException('잔존 Migration PROCEDURE의 종류, DEFINER 또는 본문 해시가 기준선과 다릅니다.');
    }
    $pdo->exec("DROP PROCEDURE `{$name}`");
};

$financialSnapshot = static function () use ($scalar, $rows): array {
    $evidenceTables = $rows("SELECT DISTINCT source_table FROM ledger_evidence_metadata WHERE source_table IS NOT NULL AND source_table<>'' ORDER BY source_table");
    $evidenceCounts = [];
    foreach ($evidenceTables as $row) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($row['source_table'] ?? ''));
        if ($table === '') continue;
        $evidenceCounts[$table] = (int) $scalar("SELECT COUNT(*) FROM `{$table}`");
    }
    return [
        'vouchers' => [
            'count' => (int) $scalar('SELECT COUNT(*) FROM ledger_vouchers'),
            'status' => $rows('SELECT status,COUNT(*) row_count FROM ledger_vouchers GROUP BY status ORDER BY status'),
            'debit_total' => (string) $scalar('SELECT COALESCE(SUM(debit_total),0) FROM ledger_vouchers'),
            'credit_total' => (string) $scalar('SELECT COALESCE(SUM(credit_total),0) FROM ledger_vouchers'),
        ],
        'voucher_lines' => [
            'count' => (int) $scalar('SELECT COUNT(*) FROM ledger_voucher_lines'),
            'debit_total' => (string) $scalar('SELECT COALESCE(SUM(debit),0) FROM ledger_voucher_lines'),
            'credit_total' => (string) $scalar('SELECT COALESCE(SUM(credit),0) FROM ledger_voucher_lines'),
        ],
        'transactions' => (int) $scalar('SELECT COUNT(*) FROM ledger_transactions'),
        'transaction_items' => (int) $scalar('SELECT COUNT(*) FROM ledger_transaction_items'),
        'transaction_settlements' => (int) $scalar('SELECT COUNT(*) FROM ledger_transaction_settlements'),
        'evidences' => $evidenceCounts,
        'legacy_recent_patterns' => (int) $scalar('SELECT COUNT(*) FROM ledger_journal_recent_patterns'),
        'legacy_client_patterns' => (int) $scalar('SELECT COUNT(*) FROM ledger_journal_client_account_patterns'),
    ];
};

$verify = static function () use ($scalar, $rows, $tableExists, $columnExists, $financialSnapshot): array {
    $constraints = $rows("SELECT TABLE_NAME,CONSTRAINT_NAME,CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME IN ('ledger_journal_rules','ledger_journal_learning_events','ledger_journal_rule_revisions','ledger_voucher_line_source_refs') ORDER BY TABLE_NAME,CONSTRAINT_TYPE,CONSTRAINT_NAME");
    $indexes = $rows("SELECT TABLE_NAME,INDEX_NAME,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) columns_list,MIN(NON_UNIQUE) non_unique FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('ledger_journal_rules','ledger_journal_learning_events','ledger_journal_rule_revisions','ledger_voucher_line_source_refs') GROUP BY TABLE_NAME,INDEX_NAME ORDER BY TABLE_NAME,INDEX_NAME");
    return [
        'tables' => [
            'ledger_journal_rule_revisions' => $tableExists('ledger_journal_rule_revisions'),
            'ledger_voucher_line_source_refs' => $tableExists('ledger_voucher_line_source_refs'),
        ],
        'required_columns' => [
            'rule_condition_hash' => $columnExists('ledger_journal_rules', 'condition_hash'),
            'rule_status' => $columnExists('ledger_journal_rules', 'rule_status'),
            'learning_event_key' => $columnExists('ledger_journal_learning_events', 'event_key'),
            'learning_status' => $columnExists('ledger_journal_learning_events', 'learning_status'),
        ],
        'counts' => [
            'rules' => (int) $scalar('SELECT COUNT(*) FROM ledger_journal_rules'),
            'revisions' => $tableExists('ledger_journal_rule_revisions') ? (int) $scalar('SELECT COUNT(*) FROM ledger_journal_rule_revisions') : null,
            'source_refs' => $tableExists('ledger_voucher_line_source_refs') ? (int) $scalar('SELECT COUNT(*) FROM ledger_voucher_line_source_refs') : null,
            'learning_events' => (int) $scalar('SELECT COUNT(*) FROM ledger_journal_learning_events'),
            'learning_ignored' => $columnExists('ledger_journal_learning_events', 'learning_status') ? (int) $scalar("SELECT COUNT(*) FROM ledger_journal_learning_events WHERE learning_status='IGNORED'") : null,
            'legacy_excluded' => $columnExists('ledger_journal_learning_events', 'decision_code') ? (int) $scalar("SELECT COUNT(*) FROM ledger_journal_learning_events WHERE decision_code='LEGACY_EVENT_EXCLUDED'") : null,
        ],
        'policy' => $rows("SELECT config_key,config_value,is_editable,created_by,updated_by FROM system_settings_config WHERE config_key='journal_learning_policy.default' OR config_key LIKE 'journal_learning_policy.%' ORDER BY config_key"),
        'constraints' => $constraints,
        'indexes' => $indexes,
        'financial_snapshot' => $financialSnapshot(),
    ];
};

if ($mode === 'verify') {
    echo json_encode($verify(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

$preflight = [
    'company_count' => (int) $scalar('SELECT COUNT(*) FROM system_company'),
    'rule_count' => (int) $scalar('SELECT COUNT(*) FROM ledger_journal_rules'),
    'posted_or_closed_vouchers' => (int) $scalar("SELECT COUNT(*) FROM ledger_vouchers WHERE deleted_at IS NULL AND LOWER(status) IN ('posted','closed')"),
    'learning_event_count' => (int) $scalar('SELECT COUNT(*) FROM ledger_journal_learning_events'),
    'legacy_event_count' => (int) $scalar('SELECT COUNT(*) FROM ledger_journal_learning_events WHERE voucher_line_id IS NULL'),
    'baseline_count' => (int) $scalar("SELECT COUNT(*) FROM system_settings_config WHERE config_key='journal_learning_policy.default'"),
    'revision_table_exists' => $tableExists('ledger_journal_rule_revisions'),
    'source_ref_table_exists' => $tableExists('ledger_voucher_line_source_refs'),
    'rule_extension_exists' => $columnExists('ledger_journal_rules', 'condition_hash'),
];
$expected = [
    'company_count' => 1,
    'rule_count' => 0,
    'posted_or_closed_vouchers' => 0,
    'learning_event_count' => 5,
    'legacy_event_count' => 5,
    'baseline_count' => 0,
    'revision_table_exists' => false,
    'source_ref_table_exists' => false,
    'rule_extension_exists' => false,
];
if ($mode === 'up' && $preflight !== $expected) {
    fwrite(STDERR, json_encode(['success' => false, 'reason' => 'PREFLIGHT_CHANGED', 'expected' => $expected, 'actual' => $preflight], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
    exit(2);
}

$before = $financialSnapshot();
$migrationFiles = [
    '20260824_05_extend_journal_rule_learning_ssot.up.sql',
    '20260824_06_create_journal_rule_revisions.up.sql',
    '20260824_07_create_voucher_line_source_refs.up.sql',
    '20260824_08_exclude_legacy_journal_learning_events.up.sql',
    '20260824_09_seed_journal_learning_policy_baseline.up.sql',
];
$executeSqlFile = static function (string $file) use ($pdo): void {
    $sql = (string) file_get_contents(PROJECT_ROOT . '/app/migrations/' . $file);
    $delimiter = ';';
    $buffer = '';
    foreach (preg_split('/\R/', $sql) as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) {
            $delimiter = $match[1];
            continue;
        }
        $buffer .= $line . "\n";
        if (!str_ends_with(rtrim($buffer), $delimiter)) continue;
        $statement = trim(substr(rtrim($buffer), 0, -strlen($delimiter)));
        if ($statement !== '') $pdo->exec($statement);
        $buffer = '';
    }
    if (trim($buffer) !== '') throw new RuntimeException('SQL 구분자가 닫히지 않았습니다: ' . $file);
};

$pdo->exec('SET @journal_learning_actor=' . $pdo->quote(ActorHelper::system('JOURNAL_LEARNING_POLICY_BASELINE')));
$completed = [];
try {
    if ($mode === 'resume') {
        $partialExpected = $preflight['company_count'] === 1
            && $preflight['rule_count'] === 0
            && $preflight['posted_or_closed_vouchers'] === 0
            && $preflight['learning_event_count'] === 5
            && $preflight['legacy_event_count'] === 5
            && $preflight['baseline_count'] === 0
            && $preflight['revision_table_exists'] === false
            && $preflight['source_ref_table_exists'] === false
            && $preflight['rule_extension_exists'] === true
            && $columnExists('ledger_journal_learning_events', 'event_key')
            && (int) $scalar("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_journal_learning_events' AND INDEX_NAME='uk_ljle_voucher_line_event'") > 0;
        if (!$partialExpected) {
            throw new RuntimeException('승인된 20260824_05 부분반영 상태와 실제 운영 스키마가 다릅니다.');
        }
        if ((int) $scalar("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_journal_learning_events' AND INDEX_NAME='idx_ljle_voucher_line_fk'") === 0) {
            $pdo->exec('ALTER TABLE ledger_journal_learning_events ADD KEY idx_ljle_voucher_line_fk (voucher_line_id)');
        }
        $pdo->exec('ALTER TABLE ledger_journal_learning_events DROP INDEX uk_ljle_voucher_line_event');
        $pdo->exec("ALTER TABLE ledger_journal_learning_events
            ADD UNIQUE KEY uk_journal_learning_event_key (event_key),
            ADD KEY idx_journal_learning_scope (company_id,learning_status,event_type,created_at),
            ADD CONSTRAINT fk_journal_learning_company FOREIGN KEY (company_id) REFERENCES system_company (id),
            ADD CONSTRAINT chk_journal_learning_status CHECK (learning_status IN ('PENDING','PROCESSED','IGNORED','CONFLICT','FAILED')),
            ADD CONSTRAINT chk_journal_learning_trace CHECK (event_type <> 'POSTED_CONFIRMATION' OR (voucher_line_source_ref_id IS NOT NULL AND event_key IS NOT NULL))");
        $completed[] = '20260824_05_extend_journal_rule_learning_ssot.up.sql (FORWARD_FIX_COMPLETE)';
        $migrationFiles = array_slice($migrationFiles, 1);
    }
    foreach ($migrationFiles as $file) {
        $executeSqlFile($file);
        $completed[] = $file;
    }
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode(['success' => false, 'completed' => $completed, 'failed' => $migrationFiles[count($completed)] ?? null, 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
    exit(1);
}

$after = $financialSnapshot();
$verification = $verify();
$unchanged = $before === $after;
$resumeStructureComplete = $verification['required_columns']['rule_condition_hash'] === true
    && $verification['required_columns']['rule_status'] === true
    && $verification['required_columns']['learning_event_key'] === true
    && $verification['required_columns']['learning_status'] === true;
if ($mode === 'resume' && $unchanged && $resumeStructureComplete) {
    $removeStaleMigrationProcedure();
}
echo json_encode([
    'success' => $unchanged,
    'completed' => $completed,
    'preflight' => $preflight,
    'financial_data_unchanged' => $unchanged,
    'before' => $before,
    'after' => $after,
    'verification' => $verification,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
exit($unchanged ? 0 : 3);
