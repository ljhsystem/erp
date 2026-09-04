<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

$pdo = DbPdo::conn();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$rows = static fn (string $sql): array => $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
$scalar = static fn (string $sql): mixed => $pdo->query($sql)->fetchColumn();
$createRow = $pdo->query('SHOW CREATE TABLE ledger_journal_rules')->fetch(PDO::FETCH_NUM);

$report = [
    'database' => (string) $scalar('SELECT DATABASE()'),
    'version' => (string) $scalar('SELECT VERSION()'),
    'journal_rule_count' => (int) $scalar('SELECT COUNT(*) FROM ledger_journal_rules'),
    'journal_rule_revision_count' => (int) $scalar('SELECT COUNT(*) FROM ledger_journal_rule_revisions'),
    'journal_rule_ddl' => (string) ($createRow[1] ?? ''),
    'connection_charset' => $rows('SELECT @@character_set_client client_charset,@@character_set_connection connection_charset,@@character_set_results result_charset,@@collation_connection connection_collation,@@character_set_database database_charset,@@collation_database database_collation'),
    'journal_rule_column_comments' => $rows("SELECT COLUMN_NAME,COLUMN_COMMENT,HEX(COLUMN_COMMENT) comment_hex,CHARACTER_SET_NAME,COLLATION_NAME,IS_NULLABLE,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_journal_rules' ORDER BY ORDINAL_POSITION"),
    'migration_history_tables' => $rows("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE '%migration%' ORDER BY TABLE_NAME"),
    'role_codes' => $rows("SELECT code,code_name,sort_no,is_active FROM system_codes WHERE code_group='JOURNAL_ACCOUNTING_ROLE' ORDER BY sort_no,code"),
    'personal_expense_categories' => $rows("SELECT code,code_name,note,memo,sort_no,is_active FROM system_codes WHERE code_group='PERSONAL_EXPENSE_CATEGORY' ORDER BY sort_no,code"),
    'target_accounts' => $rows("SELECT id,account_code,account_name,is_active,is_posting,deleted_at FROM ledger_accounts WHERE account_code IN ('551091','551200','551220','551040','551030','216100') ORDER BY account_code"),
    'source_ref_contracts' => $rows('SELECT source_type,evidence_type,accounting_role_code,debit_credit,COUNT(*) row_count FROM ledger_voucher_line_source_refs GROUP BY source_type,evidence_type,accounting_role_code,debit_credit ORDER BY source_type,evidence_type,accounting_role_code,debit_credit'),
];
$report['blocking_reasons'] = [];
if ($report['journal_rule_count'] !== 0) {
    $report['blocking_reasons'][] = '기존 Rule이 존재하여 자동 변환할 수 없습니다.';
}
if (!str_contains(strtolower($report['version']), 'mariadb') || version_compare(preg_replace('/[^0-9.].*$/', '', $report['version']), '10.11', '<')) {
    $report['blocking_reasons'][] = 'MariaDB 10.11 이상이 아닙니다.';
}
$report['ready_for_migration_14'] = $report['blocking_reasons'] === [];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($report['ready_for_migration_14'] ? 0 : 2);
