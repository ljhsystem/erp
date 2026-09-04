<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Storage.php';
require PROJECT_ROOT . '/core/Database.php';

$db = Core\Database::getInstance()->getConnection();
$tables = [
    'system_statutory_standards',
    'institution_daily_employment_income_calculation_revisions',
    'institution_daily_employment_income_calculation_results',
    'institution_daily_employment_income_allocations',
];
$columns = [];
$createSql = [];
foreach ($tables as $table) {
    $statement = $db->prepare("SELECT COLUMN_NAME column_name,COLUMN_TYPE column_type,IS_NULLABLE is_nullable,COLUMN_DEFAULT column_default,CHARACTER_SET_NAME character_set_name,COLLATION_NAME collation_name,EXTRA extra,COLUMN_COMMENT column_comment,ORDINAL_POSITION ordinal_position FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table ORDER BY ORDINAL_POSITION");
    $statement->execute(['table'=>$table]);
    $columns[$table] = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $create = $db->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_ASSOC) ?: [];
    $createSql[$table] = array_values($create)[1] ?? null;
}
$integrationColumns = ['policy_component_code','employment_type_code','work_scope_code','additional_dimension_data','additional_dimension_key'];
$targetColumns = array_values(array_filter($columns['system_statutory_standards'], static fn(array $column): bool => in_array($column['column_name'], $integrationColumns, true)));
$missing = [];
foreach ($columns as $table=>$tableColumns) {
    foreach ($tableColumns as $column) {
        $comment = trim((string)$column['column_comment']);
        if ($comment === '' || strcasecmp($comment, (string)$column['column_name']) === 0 || preg_match('/임시|temporary|todo/i', $comment)) {
            $missing[] = ['table'=>$table] + $column;
        }
    }
}
echo json_encode([
    'read_only'=>true,
    'database'=>$db->query('SELECT DATABASE()')->fetchColumn(),
    'version'=>$db->query('SELECT VERSION()')->fetchColumn(),
    'show_create_table'=>$createSql,
    'target_columns'=>$targetColumns,
    'all_columns'=>$columns,
    'missing_or_invalid_comments'=>$missing,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
