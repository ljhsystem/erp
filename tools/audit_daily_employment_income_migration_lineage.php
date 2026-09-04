<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$db = DbPdo::conn();
$tables = [
    'institution_daily_employment_income_commands',
    'institution_daily_employment_income_closures',
    'institution_daily_employment_income_accounting_links',
];
$result = [
    'database' => (string) $db->query('SELECT DATABASE()')->fetchColumn(),
    'version' => (string) $db->query('SELECT VERSION()')->fetchColumn(),
    'migration_history_tables' => $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE '%migration%' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN) ?: [],
    'tables' => [],
];
foreach ($tables as $table) {
    $exists = (int) $db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=" . $db->quote($table))->fetchColumn() === 1;
    $entry = ['exists' => $exists];
    if ($exists) {
        $entry['row_count'] = (int) $db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        $entry['columns'] = $db->query("SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=" . $db->quote($table) . ' ORDER BY ORDINAL_POSITION')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $entry['indexes'] = $db->query("SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns_in_order FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=" . $db->quote($table) . ' GROUP BY INDEX_NAME,NON_UNIQUE ORDER BY INDEX_NAME')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $entry['checks'] = $db->query("SELECT CONSTRAINT_NAME,CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME IN (SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=" . $db->quote($table) . ") ORDER BY CONSTRAINT_NAME")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $entry['foreign_keys'] = $db->query("SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=" . $db->quote($table) . ' AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $create = $db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC) ?: [];
        $entry['create_sql'] = array_values($create)[1] ?? null;
    }
    $result['tables'][$table] = $entry;
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
