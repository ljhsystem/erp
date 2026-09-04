<?php

declare(strict_types=1);

use App\Services\Institution\DailyEmploymentIncomeCalculationResultService;

require_once dirname(__DIR__, 2) . '/app/Services/Institution/DailyEmploymentIncomeCalculationResultService.php';
require_once dirname(__DIR__, 2) . '/app/Services/Institution/InsuranceEligibilityConditionEvaluator.php';
require_once dirname(__DIR__, 2) . '/app/Services/Institution/InsuranceEligibilityReasonProjectionService.php';

$reflection = new ReflectionClass(DailyEmploymentIncomeCalculationResultService::class);
$service = $reflection->newInstanceWithoutConstructor();
$method = $reflection->getMethod('appendEligibilityDisplayProjection');
$result = [
    'eligibility_status_code' => 'NOT_ELIGIBLE',
    'eligibility_reason_code' => 'CONTINUOUS_EMPLOYMENT_PERIOD_NOT_MET',
    'eligibility_snapshot' => [],
    'eligibility_policy_value_data' => json_encode([
        'reason_codes' => [[
            'code' => 'CONTINUOUS_EMPLOYMENT_PERIOD_NOT_MET',
            'name' => '계속고용기간 요건 미충족',
            'detail' => '법정 가입기간 요건을 충족하지 않았습니다.',
        ]],
    ], JSON_UNESCAPED_UNICODE),
];
$arguments = [&$result];
$method->invokeArgs($service, $arguments);

$checks = [
    'status_name' => $result['eligibility_status_name'] === '적용 제외',
    'reason_name' => $result['eligibility_reason_name'] === '계속고용기간 요건 미충족',
    'reason_detail' => $result['eligibility_reason_detail'] === '법정 가입기간 요건을 충족하지 않았습니다.',
    'policy_not_exposed' => !array_key_exists('eligibility_policy_value_data', $result),
];
foreach ($checks as $name => $passed) {
    if (!$passed) throw new RuntimeException('가입자격 표시 Projection 계약 실패: ' . $name);
}
echo json_encode(['success' => true, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
