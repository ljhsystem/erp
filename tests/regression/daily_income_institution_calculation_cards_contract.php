<?php

$root = dirname(__DIR__, 2);
$javascript = (string) file_get_contents($root . '/public/assets/js/pages/institution/daily-employment-income/index.js');
$common = (string) file_get_contents($root . '/public/assets/js/common/income-calculation-cards.js');
$service = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeService.php');
$calculation = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeCalculationService.php');
$model = (string) file_get_contents($root . '/app/Models/Institution/DailyEmploymentIncomeModel.php');
$scopeKeys = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeScopeKeyService.php');

$checks = [
    'toggle_removed' => !str_contains($javascript, 'daily-income-institution-toggle')
        && !str_contains($javascript, 'institution_detail_expanded')
        && !str_contains($javascript, 'aria-expanded='),
    'worker_card_owned_host' => str_contains($javascript, 'renderInstitutionDetails: (item, host)')
        && str_contains($javascript, 'renderInstitutionDetails(item, host)'),
    'common_renderer' => str_contains($javascript, 'renderIncomeCalculationCards') && str_contains($common, 'export function renderIncomeCalculationCards'),
    'override_payload' => str_contains($javascript, 'institution_line_overrides: worker.institution_line_overrides'),
    'override_reason_validation' => str_contains($javascript, '자동계산액과 다른 적용금액에는 적용사유가 필요합니다.'),
    'employment_item_calculation' => str_contains($service, "resolveOptional('EMPLOYMENT_INSURANCE'")
        && str_contains($service, "'line_code' => 'EMPLOYMENT_INSURANCE'")
        && str_contains($service, "'STATUTORY_RESOLVER'"),
    'unresolved_partial_status' => str_contains($service, 'unresolvedInsuranceLine')
        && str_contains($service, "'HISTORICAL_ACTUAL'"),
    'item_scope_persistence' => str_contains($model, "'daily_employment_income_workday_id' => null")
        && str_contains($model, '$this->scopeKeys->lineKeys($itemId, null')
        && str_contains($scopeKeys, "'workday_scope_key' => \$workdayId ?? 'ITEM'"),
    'tax_standard_period_preview_projection' => substr_count($calculation, "'standard_effective_from'") >= 2
        && substr_count($calculation, "'standard_effective_to'") >= 2,
    'stored_standard_period_projection' => str_contains($model, 'LEFT JOIN system_statutory_standards standard_row')
        && str_contains($model, 'standard_row.effective_from AS standard_effective_from')
        && str_contains($model, 'standard_row.effective_to AS standard_effective_to'),
    'approval_lifecycle_connected' => str_contains($service, 'public function submit(')
        && str_contains($service, 'public function withdraw(')
        && str_contains($service, 'public function act(')
        && !str_contains($javascript, '결재 Lifecycle은 아직 연결되지 않았습니다.'),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode($checks, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
if ($failed !== []) {
    fwrite(STDERR, 'FAIL: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
