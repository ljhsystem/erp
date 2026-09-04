<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));

$validator = (string) file_get_contents(PROJECT_ROOT . '/app/Services/Ledger/TransactionReferenceValidatorService.php');
$crud = (string) file_get_contents(PROJECT_ROOT . '/app/Services/Ledger/TransactionCrudService.php');
$income = (string) file_get_contents(PROJECT_ROOT . '/app/Services/Institution/RegularEmploymentIncomeAccountingGenerationService.php');

$checks = [
    'default_active_policy_preserved' => str_contains($validator, "'employee_id' => ['user_employees', '직원', \"employment_status = 'ACTIVE'\"]"),
    'explicit_historical_policy' => str_contains($validator, "REGULAR_EMPLOYMENT_INCOME_EFFECTIVE_SNAPSHOT"),
    'source_item_verified' => str_contains($validator, 'institution_regular_employment_income_items item'),
    'approved_contract_verified' => str_contains($validator, 'contract.approved_at IS NOT NULL'),
    'contract_period_verified' => str_contains($validator, 'contract.contract_start_date <= :contract_period_to')
        && str_contains($validator, 'contract.contract_end_date >= :contract_period_from'),
    'employee_master_exists' => str_contains($validator, 'employee.id = item.employee_id'),
    'crud_passes_context' => substr_count($crud, "data['reference_validation_context']") >= 2,
    'projection_supplies_source_identity' => str_contains($income, "'source_document_id' => \$header['id']")
        && str_contains($income, "'source_item_id' => \$item['id']")
        && str_contains($income, "'employment_contract_id' => \$item['employment_contract_id']"),
    'no_skip_validation' => !str_contains($validator, 'skipValidation') && !str_contains($income, 'skipValidation'),
];

$failed = array_keys(array_filter($checks, static fn (bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($failed === [] ? 0 : 1);
