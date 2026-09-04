<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$db = DbPdo::conn();
$tables = ['system_work_teams', 'system_work_team_assignments', 'institution_daily_employment_income_items'];
$result = [
    'business_units' => $db->query(
        "SELECT code, code_name, sort_no, is_active FROM system_codes"
        . " WHERE code_group='BUSINESS_UNIT' ORDER BY sort_no, code"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [],
    'work_teams' => $db->query(
        'SELECT id, team_name, business_unit, sort_no, is_active, deleted_at FROM system_work_teams ORDER BY sort_no, team_name, id'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [],
    'projects_by_unit' => $db->query(
        'SELECT business_unit,is_active,deleted_at IS NULL AS not_deleted,COUNT(*) AS row_count'
        . ' FROM system_projects GROUP BY business_unit,is_active,deleted_at IS NULL ORDER BY business_unit,is_active DESC'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [],
    'assignments' => $db->query(
        'SELECT work_scope_code, COUNT(*) AS row_count FROM system_work_team_assignments GROUP BY work_scope_code ORDER BY work_scope_code'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [],
    'clients_by_type' => $db->query(
        'SELECT client_type, is_active, deleted_at IS NULL AS not_deleted, COUNT(*) AS row_count'
        . ' FROM system_clients GROUP BY client_type, is_active, deleted_at IS NULL ORDER BY client_type, is_active DESC'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [],
    'work_types' => $db->query(
        "SELECT code,code_name,sort_no,is_active FROM system_codes WHERE code_group='WORK_TYPE' ORDER BY sort_no,code"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [],
    'active_clients' => $db->query(
        'SELECT id, client_name, client_type, sort_no FROM system_clients'
        . ' WHERE is_active=1 AND deleted_at IS NULL ORDER BY sort_no, client_name, id LIMIT 100'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [],
    'tables' => [],
];

foreach ($tables as $table) {
    $result['tables'][$table] = $db->query(
        'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT'
        . ' FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=' . $db->quote($table)
        . ' ORDER BY ORDINAL_POSITION'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
