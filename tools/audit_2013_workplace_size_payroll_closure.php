<?php

declare(strict_types=1);

define('PROJECT_ROOT',dirname(__DIR__));
require PROJECT_ROOT.'/vendor/autoload.php';
require_once PROJECT_ROOT.'/core/Storage.php';

$pdo=Core\DbPdo::conn();
$lines=$pdo->query("SELECT i.employee_name_snapshot,l.item_type_code,l.item_code,l.application_status_code,l.calculation_basis_amount,l.calculation_rate,l.calculation_before_rounding,l.rounding_method_code,l.rounding_unit,l.calculated_amount,l.final_amount,l.statutory_standard_id,l.social_insurance_coverage_id,l.workplace_size_period_id FROM institution_regular_employment_income_line_items l JOIN institution_regular_employment_income_items i ON i.id=l.regular_employment_income_item_id JOIN institution_regular_employment_incomes h ON h.id=i.regular_employment_income_id WHERE h.income_year_month='2013-08' AND (l.item_code IN('EMPLOYMENT_INSURANCE','EMPLOYMENT_INSURANCE_VOCATIONAL') OR l.item_code LIKE '%INDUSTRIAL%') ORDER BY i.sort_no,l.item_type_code,l.item_code")->fetchAll(PDO::FETCH_ASSOC);
$traceLines=$pdo->query("SELECT i.employee_name_snapshot,l.item_type_code,l.item_code,l.application_status_code,l.calculation_basis_amount,l.calculation_rate,l.calculation_before_rounding,l.rounding_method_code,l.rounding_unit,l.calculated_amount,l.final_amount,l.statutory_standard_id,l.social_insurance_coverage_id,l.workplace_size_period_id FROM institution_regular_employment_income_line_items l JOIN institution_regular_employment_income_items i ON i.id=l.regular_employment_income_item_id JOIN institution_regular_employment_incomes h ON h.id=i.regular_employment_income_id WHERE h.income_year_month='2013-08' AND l.item_type_code IN('DEDUCTION','EMPLOYER_BURDEN') ORDER BY i.sort_no,l.sort_no,l.id")->fetchAll(PDO::FETCH_ASSOC);
$employerTotal=(float)$pdo->query("SELECT SUM(l.final_amount) FROM institution_regular_employment_income_line_items l JOIN institution_regular_employment_income_items i ON i.id=l.regular_employment_income_item_id JOIN institution_regular_employment_incomes h ON h.id=i.regular_employment_income_id WHERE h.income_year_month='2013-08' AND l.item_type_code='EMPLOYER_BURDEN'")->fetchColumn();
$nonStatutoryInvalid=(int)$pdo->query("SELECT COUNT(*) FROM institution_regular_employment_income_line_items l JOIN institution_regular_employment_income_items i ON i.id=l.regular_employment_income_item_id JOIN institution_regular_employment_incomes h ON h.id=i.regular_employment_income_id WHERE h.income_year_month='2013-08' AND l.item_type_code='PAY' AND (l.application_status_code IS NOT NULL OR l.statutory_standard_id IS NOT NULL OR l.social_insurance_coverage_id IS NOT NULL OR l.workplace_size_period_id IS NOT NULL)")->fetchColumn();
$header=$pdo->query("SELECT id,document_status,current_approval_request_id,gross_amount,deduction_amount,net_payment_amount FROM institution_regular_employment_incomes WHERE income_year_month='2013-08' AND deleted_at IS NULL")->fetch(PDO::FETCH_ASSOC);
$request=$pdo->query("SELECT id,status,current_step FROM user_approval_requests WHERE id=".$pdo->quote((string)$header['current_approval_request_id']))->fetch(PDO::FETCH_ASSOC);
$requests=$pdo->query("SELECT id,status,current_step,is_active,requester_id,created_at FROM user_approval_requests WHERE document_type='REGULAR_EMPLOYMENT_INCOME' AND document_id=".$pdo->quote((string)$header['id'])." ORDER BY created_at,id")->fetchAll(PDO::FETCH_ASSOC);
$counts=['evidence'=>(int)$pdo->query("SELECT COUNT(DISTINCT evidence_id) FROM institution_regular_employment_income_accounting_links WHERE regular_employment_income_id=".$pdo->quote((string)$header['id'])." AND generation_role='PAYROLL_REPORT_EVIDENCE'")->fetchColumn(),'accounting_links'=>(int)$pdo->query("SELECT COUNT(*) FROM institution_regular_employment_income_accounting_links WHERE regular_employment_income_id=".$pdo->quote((string)$header['id']))->fetchColumn()];
$documentId=$pdo->quote((string)$header['id']);
$documentCounts=[
    'headers'=>(int)$pdo->query("SELECT COUNT(*) FROM institution_regular_employment_incomes WHERE id={$documentId}")->fetchColumn(),
    'items'=>(int)$pdo->query("SELECT COUNT(*) FROM institution_regular_employment_income_items WHERE regular_employment_income_id={$documentId} AND deleted_at IS NULL")->fetchColumn(),
    'lines'=>(int)$pdo->query("SELECT COUNT(*) FROM institution_regular_employment_income_line_items l JOIN institution_regular_employment_income_items i ON i.id=l.regular_employment_income_item_id WHERE i.regular_employment_income_id={$documentId} AND i.deleted_at IS NULL")->fetchColumn(),
];
$referenceCounts=[
    'workplace_size_periods'=>(int)$pdo->query('SELECT COUNT(*) FROM institution_workplace_size_periods')->fetchColumn(),
    'coverages'=>(int)$pdo->query('SELECT COUNT(*) FROM institution_social_insurance_coverages')->fetchColumn(),
    'statutory_revisions'=>(int)$pdo->query('SELECT COUNT(*) FROM system_statutory_standards')->fetchColumn(),
    'approval_requests'=>(int)$pdo->query("SELECT COUNT(*) FROM user_approval_requests WHERE document_type='REGULAR_EMPLOYMENT_INCOME' AND document_id={$documentId}")->fetchColumn(),
    'salary_evidence'=>(int)$pdo->query("SELECT COUNT(*) FROM ledger_evidence_salary_report WHERE source_regular_employment_income_id={$documentId} AND deleted_at IS NULL")->fetchColumn(),
];
echo json_encode(['header'=>$header,'document_counts'=>$documentCounts,'reference_counts'=>$referenceCounts,'request'=>$request,'approval_requests'=>$requests,'employment_lines'=>$lines,'statutory_lines'=>$traceLines,'employer_burden_total'=>$employerTotal,'non_statutory_trace_violation'=>$nonStatutoryInvalid,'post_processing'=>$counts],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
