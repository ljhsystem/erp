<?php
declare(strict_types=1);
define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';
use Core\DbPdo;
$db = DbPdo::conn();
$employeeIds = ['ce50c61c-8b08-4f58-b8bc-e11f1dbafb84', '6e8fb7ef-ea70-4d37-9aed-74f33b355127'];
$placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
$employeeStatement = $db->prepare("SELECT id,employee_name,employment_status,COALESCE(real_hire_date,doc_hire_date) hire_date,COALESCE(real_retire_date,doc_retire_date) retire_date FROM user_employees WHERE id IN ({$placeholders}) ORDER BY employee_name");
$employeeStatement->execute($employeeIds);
$contractStatement = $db->prepare("SELECT id,employee_id,employee_name_snapshot,revision_no,contract_start_date,contract_end_date,contract_status,terminated_at,deleted_at FROM institution_employment_contracts WHERE employee_id IN ({$placeholders}) AND contract_start_date<='2013-08-31' AND COALESCE(contract_end_date,DATE(terminated_at),'9999-12-31')>='2013-08-01' ORDER BY employee_name_snapshot,revision_no");
$contractStatement->execute($employeeIds);
$documentStatement = $db->query("SELECT id,document_status,calculation_source_code,employee_count,gross_amount,deduction_amount,net_payment_amount,deleted_at FROM institution_regular_employment_incomes WHERE income_year_month='2013-08' ORDER BY created_at,id");
echo json_encode(['employees'=>$employeeStatement->fetchAll(PDO::FETCH_ASSOC),'contracts'=>$contractStatement->fetchAll(PDO::FETCH_ASSOC),'documents'=>$documentStatement->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
