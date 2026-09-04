<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/Services/Institution/InsuranceEligibilityConditionEvaluator.php';
require_once __DIR__ . '/../../app/Services/Institution/InsuranceEligibilityReasonProjectionService.php';
use App\Services\Institution\InsuranceEligibilityConditionEvaluator;
use App\Services\Institution\InsuranceEligibilityReasonProjectionService;
$projection = (new InsuranceEligibilityReasonProjectionService())->enrich([
    'status' => 'ELIGIBLE',
    'reason_code' => 'POLICY_CONDITIONS_MET',
    'evaluated_conditions' => [[
        'condition_code' => 'EMPLOYMENT_PERIOD',
        'state' => InsuranceEligibilityConditionEvaluator::TRUE,
    ]],
], [
    'insurance_type_code' => 'EMPLOYMENT_INSURANCE',
    'reason_codes' => [[
        'code' => 'POLICY_CONDITIONS_MET',
        'name' => '일용근로자 적용요건 충족',
        'detail' => '실제 근로자료와 법정 가입요건을 평가하여 적용대상으로 판정했습니다.',
    ]],
]);
if (($projection['decision_basis_code'] ?? null) !== 'POLICY_CONDITIONS_MET'
    || ($projection['decision_basis_name'] ?? null) !== '일용근로자 적용요건 충족'
    || count($projection['passed_conditions'] ?? []) !== 1) {
    throw new RuntimeException('성공 판정근거 Projection 계약이 일치하지 않습니다.');
}
echo "성공 판정근거 Projection PASS\n";
