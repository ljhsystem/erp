<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/Services/System/StatutoryStandardValueSummaryService.php';

use App\Services\System\StatutoryStandardValueSummaryService;

$service = new StatutoryStandardValueSummaryService();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$eligibility = static fn(string $employment, string $scope, array $values = []): array => $service->project(
    ['policy_component_code' => 'ELIGIBILITY', 'employment_type_code' => $employment, 'work_scope_code' => $scope],
    ['policy_version' => 1] + $values,
    ['code' => 'policy_version', 'type' => 'number']
);

$assert($eligibility('REGULAR', 'HEAD_OFFICE')['value_summary'] === '상용 · 본사 가입자격', '상용 가입자격 요약이 올바르지 않습니다.');
$assert($eligibility('DAILY', 'HEAD_OFFICE')['value_summary'] === '일반 일용 · 본사 가입자격', '일반 일용 가입자격 요약이 올바르지 않습니다.');
$assert($eligibility('DAILY', 'CONSTRUCTION_SITE')['value_summary'] === '건설 일용 · 현장 가입자격', '건설 일용 가입자격 요약이 올바르지 않습니다.');
$assert($eligibility('DAILY', 'HEAD_OFFICE', ['decision_code' => 'DEPENDENT_RESULT', 'dependent_insurance_type_code' => 'HEALTH_INSURANCE'])['value_summary'] === '건강보험 가입결과 종속', '종속 가입자격 요약이 올바르지 않습니다.');
$assert($eligibility('DAILY', 'HEAD_OFFICE')['value_summary'] !== '1', '정책 버전 숫자가 가입자격 기준값으로 노출됩니다.');

$amount = $service->project([], ['minimum_wage' => 10030], ['code' => 'minimum_wage', 'type' => 'amount']);
$rate = $service->project([], ['employee_rate' => 0.045], ['code' => 'employee_rate', 'type' => 'rate']);
$matrix = $service->project([], ['industry_rates' => [['industry_name' => '건설업', 'employer_rate' => 0.037]]], ['code' => 'industry_rates', 'name' => '업종별 요율', 'type' => 'matrix']);
$assert($amount === ['value_summary' => '10,030원', 'value_summary_formatter_code' => 'AMOUNT'], '금액 Summary 회귀가 발생했습니다.');
$assert($rate === ['value_summary' => '4.5%', 'value_summary_formatter_code' => 'RATE'], '요율 Summary 회귀가 발생했습니다.');
$assert($matrix === ['value_summary' => '업종별 요율 1건', 'value_summary_formatter_code' => 'MATRIX'], 'Matrix Summary 회귀가 발생했습니다.');

echo "statutory standard value summary contract: PASS\n";
