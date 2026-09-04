<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$mode = strtolower((string) ($argv[1] ?? 'verify'));
if (!in_array($mode, ['baseline', 'feature', 'verify'], true)) {
    throw new InvalidArgumentException('사용법: php tools/apply_regular_employment_income_migrations.php [baseline|feature|verify]');
}

$db = Core\DbPdo::conn();
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

$counts = static function (PDO $db): array {
    $result = [];
    foreach (['institution_regular_employment_incomes', 'institution_regular_employment_income_items', 'ledger_evidence_salary_report'] as $table) {
        $result[$table] = (int) $db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }
    return $result;
};

$before = $counts($db);
if ($mode === 'baseline') {
    $sql = (string) file_get_contents(PROJECT_ROOT . '/app/migrations/20260822_01_adopt_regular_employment_income_baseline.up.sql');
    if (!preg_match('/DELIMITER \$\$(.*?)\$\$\s*DELIMITER ;/s', $sql, $matches)) {
        throw new RuntimeException('Baseline Migration 구문을 해석할 수 없습니다.');
    }
    foreach (preg_split('/\$\$\s*/', trim($matches[1])) ?: [] as $statement) {
        $statement = trim($statement);
        if ($statement !== '') $db->exec($statement);
    }
} elseif ($mode === 'feature') {
    $sql = (string) file_get_contents(PROJECT_ROOT . '/app/migrations/20260822_02_strengthen_regular_employment_income_ssot.up.sql');
    $db->exec($sql);
}

$after = $counts($db);
if ($before !== $after) {
    throw new RuntimeException('Migration 전후 기존 운영 데이터 건수가 달라졌습니다.');
}

$tables = $db->query("SELECT TABLE_NAME,COUNT(*) column_count FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('institution_regular_employment_incomes','institution_regular_employment_income_items','ledger_evidence_salary_report','institution_regular_employment_income_line_items','institution_regular_employment_income_calculation_bases','institution_regular_employment_income_audits','institution_regular_employment_income_accounting_links') GROUP BY TABLE_NAME ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$linkGuard = $db->query("SELECT INDEX_NAME,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) columns_in_order FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_links' AND INDEX_NAME='uk_evl_active_transaction_evidence' GROUP BY INDEX_NAME")->fetch(PDO::FETCH_ASSOC) ?: null;
echo json_encode(['success' => true, 'mode' => $mode, 'before' => $before, 'after' => $after, 'tables' => $tables, 'evidence_transaction_link_guard' => $linkGuard], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
