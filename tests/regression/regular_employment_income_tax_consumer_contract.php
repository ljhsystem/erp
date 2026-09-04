<?php

declare(strict_types=1);

$service = file_get_contents(__DIR__ . '/../../app/Services/Institution/RegularEmploymentIncomeCalculationService.php');
$income = file_get_contents(__DIR__ . '/../../app/Services/Institution/RegularEmploymentIncomeService.php');
$taxTable = file_get_contents(__DIR__ . '/../../app/Services/Institution/EmploymentIncomeTaxTableService.php');
$ui = file_get_contents(__DIR__ . '/../../public/assets/js/pages/institution/regular-employment-income/index.js');
$migration = file_get_contents(__DIR__ . '/../../app/migrations/20260822_10_add_regular_income_dependent_count_snapshot.up.sql');

$checks = [
    'snapshot_migration' => str_contains($migration, 'dependent_count_snapshot SMALLINT UNSIGNED NULL'),
    'snapshot_save_validation' => str_contains($income, 'dependentCount($item)'),
    'employee_snapshot_calculation_input' => str_contains($service, '$dependents[$employeeId] = (int) $input[\'dependent_count_snapshot\']')
        && str_contains($service, '$dependentCounts[$employeeId] ?? null'),
    'tax_table_lookup' => str_contains($taxTable, "['tax_by_dependents']")
        && str_contains($taxTable, "['dependent_counts']"),
    'tax_threshold_policy' => str_contains($taxTable, "['threshold_comparison']"),
    'local_tax_revision_policy' => str_contains($service, '$this->round($incomeTax * (float) $rate, $policy)'),
    'family_input_ui' => !str_contains($ui, '변경사항 재계산')
        && str_contains($ui, "EMPLOYMENT_INCOME_TAX:['dependent_count_snapshot','공제대상 가족수']")
        && str_contains($ui, 'appendBasisControl(card,line,item)')
        && str_contains($ui, 'scheduleRecalculation(item)'),
    'social_insurance_decoupled' => !str_contains($ui, '/institution/social-insurance') && !str_contains($ui, '사회보험 이력 등록'),
    'calculation_payload_keeps_snapshot' => str_contains($ui, 'dependent_count_snapshot:dependentCountOverrides.has(String(item.employee_id))'),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($failed === [] ? 0 : 1);
