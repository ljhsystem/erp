<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

$mode = $argv[1] ?? 'verify';
if (!in_array($mode, ['verify', 'up'], true)) {
    throw new RuntimeException('사용법: php tools/apply_income_evidence_original_alignment.php [verify|up]');
}

$db = DbPdo::conn();
$scalar = static fn(string $sql): int => (int) $db->query($sql)->fetchColumn();
$signature = static fn(string $sql): string => (string) ($db->query($sql)->fetchColumn() ?: '');
$transactionSignatureSql = "SELECT SHA2(COALESCE(GROUP_CONCAT(CONCAT_WS('|',transaction_row.id,transaction_row.transaction_supply_amount,transaction_row.transaction_settlement_amount,transaction_row.transaction_final_amount,link.evidence_type,link.evidence_id) ORDER BY transaction_row.id,link.evidence_id SEPARATOR '#'),''),256) FROM ledger_evidence_links link JOIN ledger_transactions transaction_row ON transaction_row.id=link.target_id WHERE link.evidence_type IN ('PAYROLL_REPORT','DAILY_EMPLOYMENT_INCOME','BUSINESS_INCOME_REPORT') AND link.target_type='TRANSACTION' AND link.deleted_at IS NULL";
$before = [
    'salary_rows' => $scalar('SELECT COUNT(*) FROM ledger_evidence_salary_report'),
    'daily_rows' => $scalar('SELECT COUNT(*) FROM ledger_evidence_daily_employment_income'),
    'business_rows' => $scalar('SELECT COUNT(*) FROM ledger_evidence_business_income'),
    'transaction_links' => $scalar("SELECT COUNT(*) FROM ledger_evidence_links WHERE evidence_type IN ('PAYROLL_REPORT','DAILY_EMPLOYMENT_INCOME','BUSINESS_INCOME_REPORT') AND target_type='TRANSACTION' AND deleted_at IS NULL"),
    'triggers' => $scalar('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()'),
    'transaction_signature' => $signature($transactionSignatureSql),
];

if ($mode === 'up') {
    $path = PROJECT_ROOT . '/app/migrations/20260903_24_align_income_evidence_originals.up.sql';
    $delimiter = ';';
    $buffer = '';
    foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents($path)) ?: [] as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) {
            $delimiter = $match[1];
            continue;
        }
        $buffer .= $line . "\n";
        $trimmed = rtrim($buffer);
        if (!str_ends_with($trimmed, $delimiter)) continue;
        $statement = trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($statement !== '') $db->exec($statement);
        $buffer = '';
    }
    if (trim($buffer) !== '') throw new RuntimeException('Migration SQL 구분자가 닫히지 않았습니다.');
}

$hasSalaryLines = $scalar("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_salary_report_lines'") === 1;
$after = [
    'salary_rows' => $scalar('SELECT COUNT(*) FROM ledger_evidence_salary_report'),
    'daily_rows' => $scalar('SELECT COUNT(*) FROM ledger_evidence_daily_employment_income'),
    'business_rows' => $scalar('SELECT COUNT(*) FROM ledger_evidence_business_income'),
    'salary_lines' => $hasSalaryLines ? $scalar('SELECT COUNT(*) FROM ledger_evidence_salary_report_lines') : 0,
    'expected_salary_lines' => $scalar('SELECT COUNT(*) FROM ledger_evidence_salary_report evidence JOIN institution_regular_employment_income_line_items line ON line.regular_employment_income_item_id=evidence.regular_employment_income_item_id'),
    'salary_snapshot_missing' => $scalar('SELECT COUNT(*) FROM ledger_evidence_salary_report WHERE snapshot_json IS NULL OR source_hash IS NULL OR reconstruction_hash IS NULL'),
    'incomplete_income_evidence' => $scalar("SELECT (SELECT COUNT(*) FROM ledger_evidence_salary_report WHERE evidence_status<>'COMPLETED')+(SELECT COUNT(*) FROM ledger_evidence_daily_employment_income WHERE evidence_status<>'COMPLETED' OR evidence_status_code<>'COMPLETED')"),
    'legacy_business_columns' => $scalar("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_business_income' AND COLUMN_NAME IN ('income_date','provider_name','provider_reg_no','supply_amount','vat_amount','service_amount','total_amount')"),
    'transaction_links' => $scalar("SELECT COUNT(*) FROM ledger_evidence_links WHERE evidence_type IN ('PAYROLL_REPORT','DAILY_EMPLOYMENT_INCOME','BUSINESS_INCOME_REPORT') AND target_type='TRANSACTION' AND deleted_at IS NULL"),
    'triggers' => $scalar('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()'),
    'transaction_signature' => $signature($transactionSignatureSql),
];

$applied = $after['legacy_business_columns'] === 0 && $hasSalaryLines;
if ($mode === 'up' || $applied) {
    if ($before['salary_rows'] !== $after['salary_rows'] || $before['daily_rows'] !== $after['daily_rows']
        || $before['business_rows'] !== $after['business_rows'] || $before['transaction_links'] !== $after['transaction_links']
        || $before['triggers'] !== $after['triggers'] || $before['transaction_signature'] !== $after['transaction_signature']
        || $after['salary_lines'] !== $after['expected_salary_lines']
        || $after['salary_snapshot_missing'] !== 0 || $after['incomplete_income_evidence'] !== 0
        || $after['legacy_business_columns'] !== 0) {
        throw new RuntimeException('소득 신고 Evidence 원본 정비 검증에 실패했습니다.');
    }
}

echo json_encode(['success' => true, 'mode' => $mode, 'applied' => $applied, 'before' => $before, 'after' => $after], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
