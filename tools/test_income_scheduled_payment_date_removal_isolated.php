<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;
$db = DbPdo::conn();
$source = (string) $db->query('SELECT DATABASE()')->fetchColumn();
$fixture = 'sukhyang_income_date_fixture_' . date('YmdHis') . '_' . random_int(1000, 9999);
if (!preg_match('/^sukhyang_income_date_fixture_\d{14}_\d{4}$/', $fixture)) {
    throw new RuntimeException('격리 DB 이름 검증에 실패했습니다.');
}

$tables = [
    'institution_regular_employment_incomes',
    'institution_daily_employment_incomes',
    'institution_daily_employment_income_calculation_results',
    'institution_business_income_groups',
    'institution_business_income_items',
    'ledger_evidence_salary_report',
    'ledger_evidence_daily_employment_income',
    'ledger_evidence_business_income',
];
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

try {
    $db->exec("CREATE DATABASE `$fixture` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $before = [];
    foreach ($tables as $table) {
        $db->exec("CREATE TABLE `$fixture`.`$table` LIKE `$source`.`$table`");
        if ($table !== 'institution_business_income_items') {
            $db->exec("INSERT INTO `$fixture`.`$table` SELECT * FROM `$source`.`$table`");
        }
        $before[$table] = (int) $db->query("SELECT COUNT(*) FROM `$fixture`.`$table`")->fetchColumn();
    }
    $db->exec("USE `$fixture`");
    $execute($db, PROJECT_ROOT . '/app/migrations/20260903_16_remove_income_scheduled_payment_dates.up.sql');
    $after = [];
    foreach ($tables as $table) {
        $after[$table] = (int) $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    }
    $removedColumns = (int) $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME IN ('payment_date','raw_payment_date','nominal_payment_date','proposed_payment_date','payment_date_override_reason') AND TABLE_NAME IN ('institution_regular_employment_incomes','institution_daily_employment_incomes','institution_daily_employment_income_calculation_results','institution_business_income_groups','ledger_evidence_salary_report','ledger_evidence_daily_employment_income','ledger_evidence_business_income')")->fetchColumn();
    $transactionDateColumns = (int) $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_business_income_items' AND COLUMN_NAME='transaction_date' AND IS_NULLABLE='NO'")->fetchColumn();
    $resultIndexColumns = $db->query("SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_income_calculation_results' AND INDEX_NAME='uq_daily_calc_result_grain' ORDER BY SEQ_IN_INDEX")->fetchAll(PDO::FETCH_COLUMN);
    if ($before !== $after || $removedColumns !== 0 || $transactionDateColumns !== 1 || in_array('payment_date', $resultIndexColumns, true)) {
        throw new RuntimeException('격리 지급예정일 제거 검증에 실패했습니다.');
    }
    echo json_encode([
        'success' => true,
        'mariadb_version' => $db->query('SELECT VERSION()')->fetchColumn(),
        'row_counts_preserved' => $before,
        'removed_columns_remaining' => $removedColumns,
        'business_item_transaction_date_not_null' => $transactionDateColumns === 1,
        'fixture_removed' => true,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} finally {
    try {
        $db->exec("USE `$source`");
        $db->exec("DROP DATABASE IF EXISTS `$fixture`");
    } catch (Throwable) {
    }
}
