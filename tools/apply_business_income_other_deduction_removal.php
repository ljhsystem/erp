<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

if (($argv[1] ?? '') !== '--apply') {
    throw new RuntimeException('운영 적용에는 --apply 인자가 필요합니다.');
}

$db = DbPdo::conn();
$execute = static function (PDO $connection, string $path): void {
    $delimiter = ';';
    $buffer = '';
    foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents($path)) ?: [] as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) {
            $delimiter = $match[1];
            continue;
        }
        $buffer .= $line . "\n";
        $trimmed = rtrim($buffer);
        if (!str_ends_with($trimmed, $delimiter)) {
            continue;
        }
        $statement = trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($statement !== '') {
            $connection->exec($statement);
        }
        $buffer = '';
    }
};

$before = [
    'headers' => (int) $db->query('SELECT COUNT(*) FROM institution_business_incomes')->fetchColumn(),
    'items' => (int) $db->query('SELECT COUNT(*) FROM institution_business_income_items')->fetchColumn(),
    'evidence' => (int) $db->query('SELECT COUNT(*) FROM ledger_evidence_business_income')->fetchColumn(),
    'other_lines' => (int) $db->query("SELECT COUNT(*) FROM institution_business_income_calculation_lines WHERE line_code='OTHER_DEDUCTION'")->fetchColumn(),
    'triggers' => (int) $db->query('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchColumn(),
];

$execute($db, PROJECT_ROOT . '/app/migrations/20260903_21_remove_business_income_other_deduction.up.sql');

$remaining = (int) $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='institution_business_incomes' AND COLUMN_NAME='total_other_deduction_amount') OR (TABLE_NAME='institution_business_income_items' AND COLUMN_NAME IN ('other_deduction_amount','other_deduction_reason')) OR (TABLE_NAME='ledger_evidence_business_income' AND COLUMN_NAME IN ('raw_other_deduction_amount','raw_other_deduction_reason')))")->fetchColumn();
$constraintClause = (string) $db->query("SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_business_income_calc_line_code'")->fetchColumn();
$afterTriggers = (int) $db->query('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchColumn();
if ($remaining !== 0 || str_contains($constraintClause, 'OTHER_DEDUCTION') || $before['triggers'] !== $afterTriggers) {
    throw new RuntimeException('사업소득 기타공제 제거 검증에 실패했습니다.');
}

echo json_encode([
    'success' => true,
    'before' => $before,
    'remaining_columns' => $remaining,
    'calculation_line_constraint' => $constraintClause,
    'triggers_after' => $afterTriggers,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
