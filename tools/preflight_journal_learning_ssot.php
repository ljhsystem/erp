<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;
$pdo = DbPdo::conn();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$scalar = static function (PDO $pdo, string $sql): mixed {
    return $pdo->query($sql)->fetchColumn();
};
$rows = static function (PDO $pdo, string $sql): array {
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
};
$tableExists = static function (PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table');
    $stmt->execute([':table' => $table]);
    return (int) $stmt->fetchColumn() > 0;
};

$report = [
    'database' => (string) $scalar($pdo, 'SELECT DATABASE()'),
    'version' => (string) $scalar($pdo, 'SELECT VERSION()'),
    'company_count' => (int) $scalar($pdo, 'SELECT COUNT(*) FROM system_company'),
    'companies' => $rows($pdo, 'SELECT id, company_name_ko FROM system_company ORDER BY id'),
    'journal_rule_count' => (int) $scalar($pdo, 'SELECT COUNT(*) FROM ledger_journal_rules'),
    'posted_or_closed_voucher_count' => (int) $scalar($pdo, "SELECT COUNT(*) FROM ledger_vouchers WHERE deleted_at IS NULL AND LOWER(status) IN ('posted','closed')"),
    'learning_event_count' => (int) $scalar($pdo, 'SELECT COUNT(*) FROM ledger_journal_learning_events'),
    'learning_event_types' => $rows($pdo, "SELECT COALESCE(event_type,'(NULL)') event_type, COUNT(*) row_count FROM ledger_journal_learning_events GROUP BY event_type ORDER BY event_type"),
    'legacy_learning_event_count' => (int) $scalar($pdo, 'SELECT COUNT(*) FROM ledger_journal_learning_events WHERE voucher_line_id IS NULL'),
    'journal_rule_account_fk_invalid' => (int) $scalar($pdo, 'SELECT COUNT(*) FROM ledger_journal_rules r LEFT JOIN ledger_accounts d ON d.id=r.debit_account_id LEFT JOIN ledger_accounts c ON c.id=r.credit_account_id LEFT JOIN ledger_accounts v ON v.id=r.vat_account_id WHERE (r.debit_account_id IS NOT NULL AND d.id IS NULL) OR (r.credit_account_id IS NOT NULL AND c.id IS NULL) OR (r.vat_account_id IS NOT NULL AND v.id IS NULL)'),
    'unusable_rule_accounts' => (int) $scalar($pdo, 'SELECT COUNT(*) FROM ledger_journal_rules r INNER JOIN ledger_accounts a ON a.id IN (r.debit_account_id,r.credit_account_id,r.vat_account_id) WHERE a.deleted_at IS NOT NULL OR a.is_active<>1 OR COALESCE(a.is_posting,1)<>1'),
    'personal_expense_accounts' => $rows($pdo, "SELECT id,account_code,account_name,is_active,COALESCE(is_posting,1) is_posting FROM ledger_accounts WHERE deleted_at IS NULL AND (account_name LIKE '%세금%' OR account_name LIKE '%수수료%' OR account_name LIKE '%소모품%' OR account_name LIKE '%교통%' OR account_name LIKE '%복리%' OR account_name LIKE '%미지급%') ORDER BY account_name,id"),
    'lee_jungho_employees' => $rows($pdo, "SELECT id,employee_name FROM user_employees WHERE employee_name='이정호'"),
    'payable_sub_accounts' => $rows($pdo, "SELECT s.* FROM ledger_accounts_sub s INNER JOIN ledger_accounts a ON a.id=s.account_id WHERE a.account_name='미지급금' ORDER BY s.sort_no,s.id"),
    'settings_scope' => 'GLOBAL_KEY_VALUE',
    'settings_baseline_exists' => (int) $scalar($pdo, "SELECT COUNT(*) FROM system_settings_config WHERE config_key='journal_learning_policy.default'"),
    'target_tables_exist' => [
        'ledger_journal_rule_revisions' => $tableExists($pdo, 'ledger_journal_rule_revisions'),
        'ledger_voucher_line_source_refs' => $tableExists($pdo, 'ledger_voucher_line_source_refs'),
    ],
];

$report['blocking_reasons'] = [];
if ($report['company_count'] !== 1) {
    $report['blocking_reasons'][] = 'system_company가 정확히 1건이 아니어서 기존 회계자료의 회사 범위를 자동 확정할 수 없습니다.';
}
if ($report['journal_rule_count'] !== 0) {
    $report['blocking_reasons'][] = '기존 복합 분개규칙은 단일 회계역할 규칙으로 자동 분해할 수 없습니다.';
}
if ($report['legacy_learning_event_count'] !== 5) {
    $report['blocking_reasons'][] = '승인된 Legacy Learning Event 5건과 실제 대상 건수가 다릅니다.';
}
if ($report['journal_rule_account_fk_invalid'] !== 0 || $report['unusable_rule_accounts'] !== 0) {
    $report['blocking_reasons'][] = '기존 분개규칙에 유효하지 않거나 전기 불가능한 계정과목 참조가 있습니다.';
}
if (!str_contains(strtolower($report['version']), 'mariadb') || version_compare(preg_replace('/[^0-9.].*$/', '', $report['version']), '10.11', '<')) {
    $report['blocking_reasons'][] = 'MariaDB 10.11 CHECK/FK 계약을 확인할 수 없습니다.';
}

$report['ready_for_migration'] = $report['blocking_reasons'] === [];
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($report['ready_for_migration'] ? 0 : 2);
