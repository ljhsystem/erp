<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Institution\RegularEmploymentIncomeCalculationService;
use Core\DbPdo;

$db = DbPdo::conn();
$employees = $db->query(
    "SELECT id,employee_name FROM user_employees WHERE employee_name IN ('이정호','박한호') ORDER BY employee_name,id"
)->fetchAll(\PDO::FETCH_ASSOC);
$preview = (new RegularEmploymentIncomeCalculationService($db))->preview(
    '2013-08',
    '2013-09-11',
    array_map(static fn(array $employee): array => [
        'employee_id' => $employee['id'],
        'dependent_count_snapshot' => 1,
    ], $employees),
    'SYSTEM:INSURANCE_POLICY_AUDIT'
);
$names = array_column($employees, 'employee_name', 'id');
$result = [];
foreach ($preview['results'] as $item) {
    $lines = array_values(array_filter(
        $item['line_items'] ?? [],
        static fn(array $line): bool => in_array($line['item_code'] ?? '', [
            'EMPLOYMENT_INSURANCE',
            'EMPLOYMENT_INSURANCE_VOCATIONAL',
            'INDUSTRIAL_ACCIDENT_INSURANCE',
        ], true)
    ));
    $result[] = [
        'employee_name' => $names[$item['employee_id']] ?? $item['employee_id'],
        'calculation_status_code' => $item['calculation_status_code'] ?? null,
        'lines' => $lines,
    ];
}

$storedStatement = $db->query(
    "SELECT h.id AS document_id,h.document_status,h.income_year_month,h.payment_date,
            h.gross_amount AS document_gross_amount,h.deduction_amount AS document_deduction_amount,
            h.net_payment_amount AS document_net_payment_amount,h.updated_at AS document_updated_at,
            i.id AS item_id,i.employee_id,i.employee_name_snapshot,i.gross_amount,i.deduction_amount,
            i.net_payment_amount,i.employer_burden_amount,i.updated_at AS item_updated_at,
            l.id AS line_id,l.item_type_code,l.item_code,l.application_status_code,
            l.calculation_basis_amount,
            l.calculation_rate,l.calculation_before_rounding,l.rounding_method_code,l.rounding_unit,
            l.calculated_amount,l.final_amount,l.statutory_standard_id,l.updated_at AS line_updated_at
       FROM institution_regular_employment_incomes h
       JOIN institution_regular_employment_income_items i
         ON i.regular_employment_income_id=h.id AND i.deleted_at IS NULL
       JOIN institution_regular_employment_income_line_items l
         ON l.regular_employment_income_item_id=i.id
      WHERE h.deleted_at IS NULL
        AND i.employee_name_snapshot IN ('이정호','박한호')
        AND l.item_code IN (
            'EMPLOYMENT_INSURANCE',
            'EMPLOYMENT_INSURANCE_VOCATIONAL',
            'INDUSTRIAL_ACCIDENT_INSURANCE'
        )
      ORDER BY h.income_year_month,i.sort_no,l.sort_no,l.id"
);
$storedLines = $storedStatement->fetchAll(\PDO::FETCH_ASSOC) ?: [];

$documentIds = array_values(array_unique(array_column($storedLines, 'document_id')));
$accountingLinks = [];
$salaryEvidence = [];
if ($documentIds !== []) {
    $marks = implode(',', array_fill(0, count($documentIds), '?'));
    $linkStatement = $db->prepare(
        "SELECT regular_employment_income_id,generation_role,aggregation_key,transaction_id
           FROM institution_regular_employment_income_accounting_links
          WHERE regular_employment_income_id IN ({$marks})
          ORDER BY regular_employment_income_id,generation_role,aggregation_key,id"
    );
    $linkStatement->execute($documentIds);
    $accountingLinks = $linkStatement->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    $evidenceStatement = $db->prepare(
        "SELECT source_regular_employment_income_id,regular_employment_income_item_id,
                employee_id,raw_gross_amount,raw_deduction_amount,raw_net_payment_amount,
                raw_employer_burden_amount,evidence_status,approved_at
           FROM ledger_evidence_salary_report
          WHERE source_regular_employment_income_id IN ({$marks})
          ORDER BY source_regular_employment_income_id,regular_employment_income_item_id,id"
    );
    $evidenceStatement->execute($documentIds);
    $salaryEvidence = $evidenceStatement->fetchAll(\PDO::FETCH_ASSOC) ?: [];
}

echo json_encode([
    'success' => true,
    'preview_results' => $result,
    'stored_lines' => $storedLines,
    'accounting_links' => $accountingLinks,
    'salary_evidence' => $salaryEvidence,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
