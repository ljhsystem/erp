<?php

declare(strict_types=1);

use Core\DbPdo;
use App\Services\Institution\RegularEmploymentIncomeCalculationService;

define('PROJECT_ROOT', dirname(__DIR__));
require dirname(__DIR__) . '/vendor/autoload.php';

$db = DbPdo::conn();
$types = [
    'NATIONAL_PENSION', 'HEALTH_INSURANCE', 'LONG_TERM_CARE', 'EMPLOYMENT_INSURANCE',
    'EMPLOYMENT_INCOME_TAX_TABLE', 'LOCAL_INCOME_TAX_WITHHOLDING', 'INDUSTRIAL_ACCIDENT',
];
$marks = implode(',', array_fill(0, count($types), '?'));
$statement = $db->prepare("SELECT standard_type_code,effective_from,effective_to,value_data
    FROM system_statutory_standards
    WHERE standard_type_code IN ($marks) AND effective_from<=? AND(effective_to IS NULL OR effective_to>=?)
    ORDER BY standard_type_code,effective_from,id");
$statement->execute([...$types, '2026-08-31', '2026-08-31']);
$standards = [];
foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
    $value = json_decode((string) $row['value_data'], true, 512, JSON_THROW_ON_ERROR);
    $standards[] = [
        'type' => $row['standard_type_code'], 'from' => $row['effective_from'], 'to' => $row['effective_to'],
        'rate' => $value['rate_value'] ?? null, 'employee_rate' => $value['employee_rate'] ?? null,
        'employer_rate' => $value['employer_rate'] ?? null, 'policy' => $value['calculation_policy'] ?? null,
        'industry_rates_count' => count($value['industry_rates'] ?? []),
    ];
}
$historyStatement = $db->prepare("SELECT standard_type_code,effective_from,effective_to,value_data
    FROM system_statutory_standards WHERE standard_type_code IN ('EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT')
    ORDER BY standard_type_code,effective_from,id");
$historyStatement->execute();
$histories = [];
foreach ($historyStatement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
    $value = json_decode((string) $row['value_data'], true, 512, JSON_THROW_ON_ERROR);
    $histories[] = [
        'type' => $row['standard_type_code'], 'from' => $row['effective_from'], 'to' => $row['effective_to'],
        'employee_rate' => $value['employee_rate'] ?? null, 'employer_rate' => $value['employer_rate'] ?? null,
        'additional_employer_rates' => $value['additional_employer_rates'] ?? null,
        'industry_rates' => $value['industry_rates'] ?? null, 'policy' => $value['calculation_policy'] ?? null,
    ];
}
$coverages = $db->query("SELECT insurance_type_code,coverage_status_code,COUNT(*) count
    FROM institution_social_insurance_coverages
    GROUP BY insurance_type_code,coverage_status_code ORDER BY insurance_type_code,coverage_status_code")
    ->fetchAll(PDO::FETCH_ASSOC) ?: [];
$coverageDetails = $db->query("SELECT employee_id,insurance_type_code,coverage_status_code,effective_from,effective_to,
        exclusion_reason_code,exclusion_reason,confirmed_at,source_type_code
    FROM institution_social_insurance_coverages ORDER BY employee_id,insurance_type_code,effective_from")
    ->fetchAll(PDO::FETCH_ASSOC) ?: [];
$workplace = $db->query("SELECT calculation_purpose_code,business_size_code,effective_from,effective_to,
        confirmation_status_code,COUNT(*) count FROM institution_workplace_size_periods
    GROUP BY calculation_purpose_code,business_size_code,effective_from,effective_to,confirmation_status_code
    ORDER BY effective_from,business_size_code")
    ->fetchAll(PDO::FETCH_ASSOC) ?: [];
$insuranceWorkplaces = $db->query("SELECT id,company_id,business_unit,work_scope_code,project_id,workplace_name,
        confirmation_status_code,effective_from,effective_to FROM institution_social_insurance_workplaces
    ORDER BY company_id,business_unit,effective_from,id")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$companyColumns = $db->query("SHOW COLUMNS FROM system_company")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$industryColumnNames = array_values(array_filter(array_column($companyColumns, 'Field'), static fn(string $name): bool =>
    str_contains(strtolower($name), 'industry') || str_contains(strtolower($name), 'business_type')
    || str_contains(strtolower($name), 'category') || str_contains(strtolower($name), 'insurance')));
$companyIndustry = [];
if ($industryColumnNames !== []) {
    $quoted = implode(',', array_map(static fn(string $name): string => '`' . str_replace('`', '``', $name) . '`', $industryColumnNames));
    $companyIndustry = $db->query("SELECT $quoted FROM system_company ORDER BY created_at,id LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
}
$employees = $db->query("SELECT DISTINCT employee_id FROM institution_employment_contracts
    WHERE contract_status='APPROVED' AND deleted_at IS NULL
      AND contract_start_date<='2026-08-31' AND(contract_end_date IS NULL OR contract_end_date>='2026-08-01')
    ORDER BY employee_id")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$preview = [];
if ($employees !== []) {
    $result = (new RegularEmploymentIncomeCalculationService($db))->preview(
        '2026-08', '2026-08-31', array_map(static fn(string $id): array => ['employee_id' => $id], $employees), 'SYSTEM:AUDIT'
    );
    foreach ($result['results'] as $item) {
        $lines = [];
        foreach ($item['line_items'] as $line) {
            if (!in_array($line['item_type_code'], ['DEDUCTION', 'EMPLOYER_BURDEN'], true)) continue;
            $lines[] = array_intersect_key($line, array_flip([
                'item_type_code', 'item_code', 'calculation_status_code', 'calculation_message',
                'calculation_basis_amount', 'calculation_rate', 'calculation_before_rounding',
                'rounding_method_code', 'rounding_unit', 'calculated_amount', 'final_amount',
            ]));
        }
        $preview[] = ['employee_id' => $item['employee_id'], 'status' => $item['calculation_status_code'],
            'message' => $item['calculation_message'], 'lines' => $lines];
    }
}
echo json_encode(['standards' => $standards, 'employment_industrial_histories' => $histories,
    'coverages' => $coverages, 'coverage_details' => $coverageDetails,
    'workplace_size_periods' => $workplace, 'insurance_workplaces' => $insuranceWorkplaces,
    'company_industry_columns' => $industryColumnNames,
    'company_industry_values' => $companyIndustry, 'preview' => $preview],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
