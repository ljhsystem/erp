<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$db = DbPdo::conn();
$count = static fn(PDO $db, string $sql): int => (int) $db->query($sql)->fetchColumn();
$result = [
    'daily_workers' => $count($db, "SELECT COUNT(*) FROM system_clients WHERE client_type='DAILY_WORKER' AND is_active=1 AND deleted_at IS NULL"),
    'groups' => $count($db, 'SELECT COUNT(*) FROM institution_daily_employment_income_groups'),
    'items' => $count($db, 'SELECT COUNT(*) FROM institution_daily_employment_income_items'),
    'workdays' => $count($db, 'SELECT COUNT(*) FROM institution_daily_employment_income_workdays'),
    'lines' => $count($db, 'SELECT COUNT(*) FROM institution_daily_employment_income_lines'),
    'unlinked_items' => $count($db, 'SELECT COUNT(*) FROM institution_daily_employment_income_items WHERE daily_employment_income_group_id IS NULL'),
    'duplicate_group_workers' => $count($db, 'SELECT COUNT(*) FROM (SELECT daily_employment_income_group_id,worker_client_id FROM institution_daily_employment_income_items GROUP BY daily_employment_income_group_id,worker_client_id HAVING COUNT(*)>1) duplicates'),
    'group_default_rate_column' => $count($db, "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_income_groups' AND COLUMN_NAME='default_daily_rate'"),
    'migration_10_schema_applied' => $count($db, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_income_groups'"),
    'migration_11_schema_applied' => 1 - $count($db, "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_income_groups' AND COLUMN_NAME='default_daily_rate'"),
    'group_insurance_policy' => $db->query(
        'SELECT id,business_unit,project_id,work_team_id,'
        . 'employment_insurance_application_status_code,employment_insurance_decision_reason,employment_insurance_decision_source_code_id,'
        . 'industrial_accident_application_status_code,industrial_accident_decision_reason,industrial_accident_decision_source_code_id,'
        . 'created_at,updated_at FROM institution_daily_employment_income_groups ORDER BY sort_no,id'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [],
    'amounts' => $db->query(
        'SELECT COALESCE(SUM(total_gross_amount),0) gross_amount,COALESCE(SUM(total_deduction_amount),0) deduction_amount,'
        . 'COALESCE(SUM(total_net_payment_amount),0) net_payment_amount FROM institution_daily_employment_income_items'
    )->fetch(PDO::FETCH_ASSOC) ?: [],
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
