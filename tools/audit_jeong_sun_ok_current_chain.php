<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Storage.php';
require PROJECT_ROOT . '/core/Database.php';

$db = Core\Database::getInstance()->getConnection();
$documentId = 'e8650425-ef60-4bbb-bd5e-88deeeff7f48';
$statement = $db->prepare("SELECT header_row.id document_id,header_row.status_code,group_row.id group_id,item_row.id item_id,item_row.worker_client_id,item_row.total_work_days,item_row.total_gross_amount,item_row.total_deduction_amount,item_row.total_net_payment_amount,COUNT(DISTINCT workday_row.id) workday_count,COUNT(DISTINCT line_row.id) line_count FROM institution_daily_employment_incomes header_row JOIN institution_daily_employment_income_groups group_row ON group_row.daily_employment_income_id=header_row.id JOIN institution_daily_employment_income_items item_row ON item_row.daily_employment_income_group_id=group_row.id LEFT JOIN institution_daily_employment_income_workdays workday_row ON workday_row.daily_employment_income_item_id=item_row.id LEFT JOIN institution_daily_employment_income_lines line_row ON line_row.daily_employment_income_item_id=item_row.id WHERE header_row.id=:id GROUP BY header_row.id,header_row.status_code,group_row.id,item_row.id,item_row.worker_client_id,item_row.total_work_days,item_row.total_gross_amount,item_row.total_deduction_amount,item_row.total_net_payment_amount");
$statement->execute(['id'=>$documentId]);
$chain = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
$results = $db->query("SELECT id,calculation_revision_id,daily_employment_income_item_id,result_type_code,eligibility_revision_id,status_code,confirmed_employee_amount,confirmed_employer_amount FROM institution_daily_employment_income_calculation_results ORDER BY calculation_revision_id,result_type_code,id")->fetchAll(PDO::FETCH_ASSOC) ?: [];
echo json_encode(['read_only'=>true,'chain'=>$chain,'results'=>$results], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
