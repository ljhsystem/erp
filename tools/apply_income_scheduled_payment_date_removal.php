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
$database = (string) $db->query('SELECT DATABASE()')->fetchColumn();
if ($database === '' || str_contains(strtolower($database), 'fixture')) {
    throw new RuntimeException('운영 데이터베이스 이름을 확인해 주세요.');
}

$removedByTable = [
    'institution_regular_employment_incomes' => ['nominal_payment_date', 'proposed_payment_date', 'payment_date_override_reason', 'payment_date'],
    'institution_daily_employment_incomes' => ['payment_date'],
    'institution_daily_employment_income_calculation_results' => ['payment_date'],
    'institution_business_income_groups' => ['payment_date'],
    'ledger_evidence_salary_report' => ['raw_payment_date'],
    'ledger_evidence_daily_employment_income' => ['raw_payment_date', 'payment_date'],
    'ledger_evidence_business_income' => ['raw_payment_date'],
];

$columns = static function (PDO $db, string $table, array $excluded): array {
    $statement = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table ORDER BY ORDINAL_POSITION');
    $statement->execute([':table' => $table]);
    return array_values(array_diff($statement->fetchAll(PDO::FETCH_COLUMN) ?: [], $excluded));
};
$digest = static function (PDO $db, string $table, array $selectedColumns): array {
    $quoted = implode(',', array_map(static fn(string $column): string => '`' . str_replace('`', '``', $column) . '`', $selectedColumns));
    $rows = $db->query("SELECT {$quoted} FROM `{$table}` ORDER BY `id`")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return ['count' => count($rows), 'sha256' => hash('sha256', (string) json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION))];
};
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
        if (!str_ends_with($trimmed, $delimiter)) continue;
        $statement = trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($statement !== '') $connection->exec($statement);
        $buffer = '';
    }
};

$before = [];
$selected = [];
foreach ($removedByTable as $table => $removed) {
    $selected[$table] = $columns($db, $table, $removed);
    $before[$table] = $digest($db, $table, $selected[$table]);
}
$triggerBefore = (int) $db->query('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchColumn();

$execute($db, PROJECT_ROOT . '/app/migrations/20260903_16_remove_income_scheduled_payment_dates.up.sql');

$after = [];
foreach ($removedByTable as $table => $removed) {
    $after[$table] = $digest($db, $table, $selected[$table]);
}
$remaining = (int) $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME IN ('payment_date','raw_payment_date','nominal_payment_date','proposed_payment_date','payment_date_override_reason') AND TABLE_NAME IN ('institution_regular_employment_incomes','institution_daily_employment_incomes','institution_daily_employment_income_calculation_results','institution_business_income_groups','ledger_evidence_salary_report','ledger_evidence_daily_employment_income','ledger_evidence_business_income')")->fetchColumn();
$transactionDateReady = (int) $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_business_income_items' AND COLUMN_NAME='transaction_date' AND IS_NULLABLE='NO'")->fetchColumn() === 1;
$triggerAfter = (int) $db->query('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchColumn();
if ($before !== $after || $remaining !== 0 || !$transactionDateReady || $triggerBefore !== $triggerAfter) {
    throw new RuntimeException('운영 지급예정일 제거 후 불변식 검증에 실패했습니다.');
}

echo json_encode([
    'success' => true,
    'database' => $database,
    'logical_data' => $after,
    'removed_columns_remaining' => $remaining,
    'business_item_transaction_date_not_null' => $transactionDateReady,
    'triggers' => ['before' => $triggerBefore, 'after' => $triggerAfter],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
