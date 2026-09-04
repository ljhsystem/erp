<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\Institution\RegularEmploymentIncomeAccountingGenerationService;

$source = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Institution/RegularEmploymentIncomeAccountingGenerationService.php');
$checks = [
    'institution_liability_not_materialized' => !method_exists(RegularEmploymentIncomeAccountingGenerationService::class, 'institutionLiabilityGroups'),
    'employee_accounting_only' => !str_contains($source, 'institutionLiabilityGroups')
        && !str_contains($source, 'employerPayload')
        && str_contains($source, 'employeeTransactionPayload'),
    'employer_burden_excluded_from_employee_settlement' => str_contains($source, "elseif (\$line['item_type_code'] === 'DEDUCTION')"),
];
$failed = array_keys(array_filter($checks, static fn(bool $value): bool => !$value));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
