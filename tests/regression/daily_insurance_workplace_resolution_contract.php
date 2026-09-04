<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$model = file_get_contents($root . '/app/Models/Institution/DailyEmploymentIncomeModel.php');
$service = file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeService.php');
$ui = file_get_contents($root . '/public/assets/js/pages/institution/daily-employment-income/index.js');

if (!is_string($model) || !is_string($service) || !is_string($ui)) {
    throw new RuntimeException('보험사업장 연결 계약 파일을 읽을 수 없습니다.');
}

$checks = [
    'coverage_missing_projects_pending_report' => str_contains($model, "'PENDING_REPORT'"),
    'coverage_missing_not_blocking_code' => !str_contains($model, "'INSURANCE_COVERAGE_NOT_FOUND'"),
    'coverage_duplicate_blocks' => str_contains($model, "'INSURANCE_COVERAGE_AMBIGUOUS'"),
    'explicit_workplace_is_scope_validated' => str_contains($model, 'id=:id AND company_id=:company_id AND business_unit=:business_unit')
        && str_contains($model, 'scope_project_key=:scope_key'),
    'group_payload_does_not_require_workplace' => !str_contains($ui, 'social_insurance_workplace_id: group.social_insurance_workplace_id || null'),
    'group_ui_does_not_show_workplace' => !str_contains($ui, "addField('보험사업장', workplace)"),
    'service_allows_null_workplace_for_manual_group_insurance' => str_contains($service, '$manualEmploymentEligibility'),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed !== []) {
    throw new RuntimeException('보험사업장 연결 계약 실패: ' . implode(', ', $failed));
}

echo json_encode(['success' => true, 'checks' => $checks], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
