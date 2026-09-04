<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$db = DbPdo::conn();
$documentId = '4d9f970e-c6c5-4a7c-88ff-8a5cfdb47fb6';
$document = $db->prepare('SELECT id,income_year_month,payment_date,document_status,gross_amount,deduction_amount,net_payment_amount FROM institution_regular_employment_incomes WHERE id=:id');
$document->execute([':id' => $documentId]);
$items = $db->prepare("SELECT i.id,i.employee_id,i.employee_name_snapshot,i.position_name_snapshot,i.department_name_snapshot,i.gross_amount,i.taxable_amount,i.employment_insurance_basis_snapshot,i.employer_burden_amount,l.item_type_code,l.item_code,l.calculated_amount,l.adjustment_amount,l.final_amount,l.adjustment_reason,l.source_reference_id,l.source_key FROM institution_regular_employment_income_items i LEFT JOIN institution_regular_employment_income_line_items l ON l.regular_employment_income_item_id=i.id WHERE i.regular_employment_income_id=:id AND i.deleted_at IS NULL ORDER BY i.sort_no,l.sort_no,l.id");
$items->execute([':id' => $documentId]);
$standards = $db->query("SELECT * FROM system_statutory_standards WHERE standard_type_code IN ('EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT') AND effective_from<='2013-08-31' AND COALESCE(effective_to,'9999-12-31')>='2013-08-31' ORDER BY standard_type_code,effective_from DESC,id");
$coverages = $db->prepare("SELECT c.* FROM institution_social_insurance_coverages c JOIN institution_regular_employment_income_items i ON i.employee_id=c.employee_id WHERE i.regular_employment_income_id=:id AND c.effective_from<='2013-08-31' AND COALESCE(c.effective_to,'9999-12-31')>='2013-08-01' ORDER BY i.sort_no,c.insurance_type_code,c.effective_from");
$coverages->execute([':id' => $documentId]);
$company = $db->query('SELECT * FROM system_company ORDER BY created_at,id LIMIT 1');

echo json_encode([
    'document' => $document->fetch(PDO::FETCH_ASSOC) ?: null,
    'items_and_lines' => $items->fetchAll(PDO::FETCH_ASSOC) ?: [],
    'effective_standards' => $standards->fetchAll(PDO::FETCH_ASSOC) ?: [],
    'confirmed_coverages' => $coverages->fetchAll(PDO::FETCH_ASSOC) ?: [],
    'company' => $company->fetch(PDO::FETCH_ASSOC) ?: null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
