<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

use Core\DbPdo;
$db = DbPdo::conn();
$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
if ((string) $db->query('SELECT DATABASE()')->fetchColumn() !== 'sukhyang') {
    throw new RuntimeException('OPERATING_SCHEMA_MISMATCH');
}

$metadata = $db->query(
    "SELECT id,sort_no,import_type,evidence_type,source_table,process_role,transaction_cardinality,deleted_at "
    . "FROM ledger_evidence_metadata WHERE import_type IN "
    . "('DAILY_EMPLOYMENT_INCOME','DAILY_WORK_REPORT','PAYROLL_WITHHOLDING','PAYROLL','EMPLOYEE_EXPENSE_PERSONAL') ORDER BY sort_no,id"
)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
$codes = $db->query(
    "SELECT code,code_name,is_active FROM system_codes WHERE code_group='IMPORT_TYPE' "
    . "AND code IN ('DAILY_EMPLOYMENT_INCOME','DAILY_WORK_REPORT','PAYROLL_WITHHOLDING') ORDER BY sort_no,id"
)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
$pages = $db->query(
    "SELECT page_key,default_route_key,default_route_url,is_active FROM system_page_registry "
    . "WHERE default_route_url LIKE '%ledger/data%' OR page_key LIKE '%ledger%data%' ORDER BY page_key"
)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
$settings = $db->query(
    "SELECT page_key,setting_type,COUNT(*) row_count FROM system_user_settings "
    . "WHERE page_key IN ('evidence-daily-employment-income','evidence-payroll-withholding') "
    . "GROUP BY page_key,setting_type ORDER BY page_key,setting_type"
)->fetchAll(\PDO::FETCH_ASSOC) ?: [];

echo json_encode([
    'database' => 'sukhyang',
    'metadata' => $metadata,
    'import_type_codes' => $codes,
    'page_registry' => $pages,
    'table_settings' => $settings,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
