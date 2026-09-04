<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\Institution\IncomeInsurancePremiumCalculationService;

$calculator = new IncomeInsurancePremiumCalculationService();
$standard = ['value_data' => ['calculation_policy' => ['method' => 'TRUNCATE', 'discard_below_unit' => 10]]];
$regular = $calculator->calculate(988890, 0.0065, $standard);
$daily = $calculator->calculate(452940, 0.0065, $standard);
$root = dirname(__DIR__, 2);
$regularSource = (string) file_get_contents($root . '/app/Services/Institution/RegularEmploymentIncomeCalculationService.php');
$dailySource = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeService.php');

$checks = [
    '상용 공식산식 결과' => $regular['calculation_before_rounding'] === 6427.785 && $regular['calculated_amount'] === 6420.0,
    '일용 공식산식 결과' => $daily['calculation_before_rounding'] === 2944.11 && $daily['calculated_amount'] === 2940.0,
    '동일 끝수처리 계약' => $regular['rounding_method_code'] === 'TRUNCATE' && $daily['rounding_unit'] === 10,
    '상용 공용 보험료 계산 사용' => str_contains($regularSource, '$this->insurancePremium->finalize'),
    '일용 공용 보험료 계산 사용' => str_contains($dailySource, '$this->insurancePremium->calculate'),
    '일용 사용자부담 공식계산' => str_contains($dailySource, 'calculatedEmployerInsuranceLine')
        && str_contains($dailySource, 'WorkplaceSizeRateResolver')
        && str_contains($dailySource, "resolveOptional('INDUSTRIAL_ACCIDENT'"),
    'Preview 사유 전 계산·저장 사유 필수 분리' => str_contains($dailySource, 'bool $requireDecisionReason = false')
        && str_contains($dailySource, '$this->calculate($input, true)'),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($failed === [] ? 0 : 1);
