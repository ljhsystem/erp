<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeService.php');
$eligibility = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeInsuranceEligibilityService.php');
$manualPolicy = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeGroupInsurancePolicyService.php');
$resultService = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeCalculationResultService.php');
$sourceService = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeCalculationSourceService.php');

$assertions = [
    '고용보험과 산재보험은 현재 공식 가입자격 Resolver 대상이 아니다' =>
        !str_contains($eligibility, "['EMPLOYMENT_INSURANCE', 'INDUSTRIAL_ACCIDENT']"),
    '보험사업장 누락만으로 차단하지 않는다' =>
        !str_contains($eligibility, "'보험사업장 미지정'")
        && !str_contains($eligibility, "'SOCIAL_INSURANCE_WORKPLACE_REQUIRED'"),
    '고용보험 구성요소별 판정을 소비한다' =>
        str_contains($service, "'UNEMPLOYMENT_BENEFIT'")
        && str_contains($service, "'EMPLOYMENT_STABILITY_VOCATIONAL'"),
    '고용·산재 계산은 회사부담 정책 결과를 사용한다' =>
        str_contains($service, "companyBurdenResult(\$item, 'employment_insurance')")
        && str_contains($service, "companyBurdenResult(\$item, 'industrial_accident')")
        && str_contains($manualPolicy, 'DAILY_GROUP_MANUAL_SETTING')
        && str_contains($manualPolicy, 'BUSINESS_DIVISION_POLICY'),
    '회사부담 설정은 공식 가입자격 Revision을 위조하지 않는다' =>
        str_contains($manualPolicy, "'eligibility_revision_id' => null")
        && str_contains($manualPolicy, "'company_burden_status_code' => \$status"),
    '계산 Result는 보험 5종과 회사부담 Snapshot을 보존한다' =>
        str_contains($resultService, "'EMPLOYMENT_INSURANCE', 'INDUSTRIAL_ACCIDENT_INSURANCE'")
        && str_contains($resultService, "'decision_source_code'")
        && str_contains($resultService, "\$manualSetting ? null : \$status"),
    '회사부담 설정은 계산 source hash에 포함된다' =>
        str_contains($sourceService, "'EMPLOYMENT_INSURANCE_VOCATIONAL', 'INDUSTRIAL_ACCIDENT_INSURANCE'")
        && str_contains($sourceService, "'company_burden_status_code'")
        && str_contains($sourceService, "'burden_source_code'"),
    '미확정 근로자부담은 상태와 NULL 금액을 보존한다' =>
        str_contains($service, "'application_status_code' => 'CONFIRMATION_REQUIRED', 'calculated_amount' => null, 'final_amount' => null"),
    '미확정 사용자부담은 NULL 금액을 보존한다' =>
        str_contains($service, "'calculated_amount' => null, 'final_amount' => null, 'adjustment_reason' => null"),
];

$failed = array_keys(array_filter($assertions, static fn(bool $passed): bool => !$passed));
if ($failed !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failed) . PHP_EOL);
    exit(1);
}

echo "고용·산재 회사부담은 사업구분 기본정책 또는 건설 Group 수동설정과 PREMIUM Resolver를 사용합니다.\n";
