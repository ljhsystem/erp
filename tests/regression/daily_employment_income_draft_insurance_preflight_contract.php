<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
$service = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeService.php');
$businessPolicy = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeBusinessUnitPolicyService.php');
$eligibility = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeInsuranceEligibilityService.php');
$resolver = (string) file_get_contents($root . '/app/Services/Institution/InsuranceEligibilityResolver.php');
$assertions = [
    'DRAFT 계산은 보험 미확정 상태를 명시한다' => str_contains($service, "'CONFIRMATION_REQUIRED'"),
    '보험 미확정은 계산결과의 preflight에 보존한다' => str_contains($service, "'insurance_preflight'"),
    '보험사업장 조회는 계산 Runtime에서 사용하지 않는다' => !str_contains($service, 'resolveDailyInsuranceContext('),
    '사업구분 정책은 보험사업장을 요구하지 않는다' => !str_contains($businessPolicy, "'insurance_workplace_required' => true"),
    '장기요양보험은 건강보험 가입자격 결과에 종속한다' => str_contains($eligibility, "'dependent_result' => \$results['HEALTH_INSURANCE']")
        && str_contains($resolver, "'reason_code'=>'DEPENDENT_INSURANCE_RESULT'"),
    '가입자격 확정 전에는 보험료 Revision을 조회하지 않는다' => str_contains($service, "if (\$status === 'ELIGIBLE')"),
];
$failed = array_keys(array_filter($assertions, static fn(bool $passed): bool => !$passed));
if ($failed !== []) { fwrite(STDERR, implode(PHP_EOL, $failed) . PHP_EOL); exit(1); }
echo "보험사업장과 회사부담·법정 가입자격 판정 책임이 분리되어 있습니다.\n";
