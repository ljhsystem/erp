<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$db = DbPdo::conn();
$tables = [
    'system_codes',
    'system_projects',
    'system_work_teams',
    'institution_social_insurance_workplaces',
    'institution_daily_employment_incomes',
    'institution_daily_employment_income_items',
];
$result = ['columns' => []];

foreach ($tables as $table) {
    $statement = $db->prepare(
        'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA'
        . ' FROM information_schema.COLUMNS'
        . ' WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name ORDER BY ORDINAL_POSITION'
    );
    $statement->execute([':table_name' => $table]);
    $result['columns'][$table] = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$result['business_units'] = $db->query(
    "SELECT code,code_name,sort_no,extra_data,is_active FROM system_codes"
    . " WHERE code_group='BUSINESS_UNIT' ORDER BY sort_no,code"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
$result['projects'] = $db->query(
    'SELECT id,project_name,business_type,start_date,completion_date,is_active'
    . ' FROM system_projects ORDER BY sort_no,id LIMIT 100'
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
$result['insurance_workplaces'] = $db->query(
    'SELECT * FROM institution_social_insurance_workplaces ORDER BY effective_from,id LIMIT 100'
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
$result['daily_items'] = $db->query(
    'SELECT business_unit,work_scope_code,project_id,work_team_id,COUNT(*) row_count'
    . ' FROM institution_daily_employment_income_items'
    . ' GROUP BY business_unit,work_scope_code,project_id,work_team_id'
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
$result['project_business_types'] = $db->query(
    "SELECT COALESCE(NULLIF(TRIM(business_type),''),'(없음)') business_type,COUNT(*) row_count"
    . ' FROM system_projects GROUP BY COALESCE(NULLIF(TRIM(business_type),\'\'),\'(없음)\')'
    . ' ORDER BY row_count DESC,business_type'
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
$result['preflight'] = [
    'daily_item_count'=>(int)$db->query('SELECT COUNT(*) FROM institution_daily_employment_income_items')->fetchColumn(),
    'daily_item_orphan_project'=>(int)$db->query('SELECT COUNT(*) FROM institution_daily_employment_income_items i LEFT JOIN system_projects p ON p.id=i.project_id WHERE i.project_id IS NOT NULL AND p.id IS NULL')->fetchColumn(),
    'daily_item_orphan_team'=>(int)$db->query('SELECT COUNT(*) FROM institution_daily_employment_income_items i LEFT JOIN system_work_teams t ON t.id=i.work_team_id WHERE i.work_team_id IS NOT NULL AND t.id IS NULL')->fetchColumn(),
    'daily_item_orphan_worker'=>(int)$db->query('SELECT COUNT(*) FROM institution_daily_employment_income_items i LEFT JOIN system_clients c ON c.id=i.worker_client_id WHERE c.id IS NULL')->fetchColumn(),
    'insurance_workplace_count'=>(int)$db->query('SELECT COUNT(*) FROM institution_social_insurance_workplaces')->fetchColumn(),
];
$result['indexes'] = [];
foreach (['institution_daily_employment_income_items', 'institution_social_insurance_workplaces'] as $table) {
    $result['indexes'][$table] = $db->query('SHOW INDEX FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
