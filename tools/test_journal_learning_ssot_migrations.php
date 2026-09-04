<?php
declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;
use Core\Helpers\ActorHelper;

$pdo = DbPdo::conn();
$sourceDatabase = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$testDatabase = 'codex_journal_learning_ssot_test';
if (!str_starts_with($testDatabase, 'codex_journal_ssot_') && !str_starts_with($testDatabase, 'codex_journal_learning_ssot_test')) {
    throw new RuntimeException('허용된 테스트 DB 이름이 아닙니다.');
}

$executeSqlFile = static function (PDO $pdo, string $file): void {
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
    if (trim($buffer) !== '') {
        throw new RuntimeException('SQL 문장 구분자가 닫히지 않았습니다: ' . basename($file));
    }
};

$upFiles = [
    '20260824_05_extend_journal_rule_learning_ssot.up.sql',
    '20260824_06_create_journal_rule_revisions.up.sql',
    '20260824_07_create_voucher_line_source_refs.up.sql',
    '20260824_08_exclude_legacy_journal_learning_events.up.sql',
    '20260824_09_seed_journal_learning_policy_baseline.up.sql',
];

$pdo->exec("DROP DATABASE IF EXISTS `{$testDatabase}`");
$pdo->exec("CREATE DATABASE `{$testDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
try {
    foreach (['system_company', 'system_settings_config', 'ledger_accounts', 'ledger_vouchers', 'ledger_voucher_lines', 'ledger_journal_rules', 'ledger_journal_learning_events'] as $table) {
        $pdo->exec("CREATE TABLE `{$testDatabase}`.`{$table}` LIKE `{$sourceDatabase}`.`{$table}`");
    }
    $pdo->exec("INSERT INTO `{$testDatabase}`.`system_company` SELECT * FROM `{$sourceDatabase}`.`system_company`");
    $pdo->exec("INSERT INTO `{$testDatabase}`.`ledger_journal_learning_events` SELECT * FROM `{$sourceDatabase}`.`ledger_journal_learning_events`");
    $pdo->exec("USE `{$testDatabase}`");
    $pdo->exec('SET @journal_learning_actor=' . $pdo->quote(ActorHelper::system('JOURNAL_LEARNING_POLICY_BASELINE')));

    $hasExpandedRule = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_journal_rules' AND COLUMN_NAME='condition_hash'")->fetchColumn() > 0;
    $hasLegacyUnique = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_journal_learning_events' AND INDEX_NAME='uk_ljle_voucher_line_event'")->fetchColumn() > 0;
    if ($hasExpandedRule && $hasLegacyUnique) {
        $hasReplacement = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_journal_learning_events' AND INDEX_NAME='idx_ljle_voucher_line_fk'")->fetchColumn() > 0;
        if (!$hasReplacement) {
            $pdo->exec('ALTER TABLE ledger_journal_learning_events ADD KEY idx_ljle_voucher_line_fk (voucher_line_id)');
        }
        $pdo->exec('ALTER TABLE ledger_journal_learning_events DROP INDEX uk_ljle_voucher_line_event');
        $pdo->exec("ALTER TABLE ledger_journal_learning_events ADD UNIQUE KEY uk_journal_learning_event_key (event_key), ADD KEY idx_journal_learning_scope (company_id,learning_status,event_type,created_at), ADD CONSTRAINT fk_journal_learning_company FOREIGN KEY (company_id) REFERENCES system_company (id), ADD CONSTRAINT chk_journal_learning_status CHECK (learning_status IN ('PENDING','PROCESSED','IGNORED','CONFLICT','FAILED')), ADD CONSTRAINT chk_journal_learning_trace CHECK (event_type <> 'POSTED_CONFIRMATION' OR (voucher_line_source_ref_id IS NOT NULL AND event_key IS NOT NULL))");
        array_shift($upFiles);
        echo '20260824_05 partial Forward Fix: UP OK' . PHP_EOL;
    } elseif ($hasExpandedRule) {
        array_shift($upFiles);
        echo '20260824_05 already applied clone: SKIP' . PHP_EOL;
    }

    foreach ($upFiles as $file) {
        $executeSqlFile($pdo, PROJECT_ROOT . '/app/migrations/' . $file);
        echo $file . ': UP OK' . PHP_EOL;
    }
    $legacy = (int) $pdo->query("SELECT COUNT(*) FROM ledger_journal_learning_events WHERE learning_status='IGNORED' AND decision_code='LEGACY_EVENT_EXCLUDED'")->fetchColumn();
    $baseline = (int) $pdo->query("SELECT COUNT(*) FROM system_settings_config WHERE config_key='journal_learning_policy.default'")->fetchColumn();
    if ($legacy !== 5 || $baseline !== 1) {
        throw new RuntimeException('Legacy 제외 또는 학습정책 Baseline 검증에 실패했습니다.');
    }

    foreach (['20260824_07_create_voucher_line_source_refs.down.sql', '20260824_06_create_journal_rule_revisions.down.sql'] as $file) {
        $executeSqlFile($pdo, PROJECT_ROOT . '/app/migrations/' . $file);
        echo $file . ': EMPTY DOWN OK' . PHP_EOL;
    }
    echo 'PASS: journal learning SSOT migration up/empty-down contract' . PHP_EOL;
} finally {
    $pdo->exec("USE `{$sourceDatabase}`");
    $pdo->exec("DROP DATABASE IF EXISTS `{$testDatabase}`");
}
