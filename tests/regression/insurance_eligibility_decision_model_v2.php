<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Services/Institution/InsuranceEligibilityConditionEvaluator.php';
require_once __DIR__ . '/../../app/Services/Institution/InsuranceEligibilityDecisionModelEvaluator.php';
require_once __DIR__ . '/../../app/Services/Institution/InsuranceEligibilityPolicyValidator.php';

use App\Services\Institution\InsuranceEligibilityConditionEvaluator;
use App\Services\Institution\InsuranceEligibilityDecisionModelEvaluator;
use App\Services\Institution\InsuranceEligibilityPolicyValidator;

function eligibilityV2Assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$reason = static fn(string $code, string $name): array => ['code' => $code, 'name' => $name, 'detail' => $name];
$component = static fn(string $code, string $fact, string $required = 'NOT_REQUIRED'): array => [
    'component_code' => $code,
    'component_name' => $code === 'UNEMPLOYMENT_BENEFIT' ? '실업급여' : '고용안정·직업능력개발',
    'combination_code' => 'ALL',
    'required_application_code' => $required,
    'employee_contribution_applicable' => $code === 'UNEMPLOYMENT_BENEFIT',
    'employer_contribution_applicable' => true,
    'rules' => [['fact_code' => $fact, 'operator' => 'TRUE', 'expected_value' => true]],
    'applicable_reason' => $reason('LEGAL_REQUIREMENT_MET', '법정 가입요건 충족'),
    'excluded_reason' => $reason('LEGAL_REQUIREMENT_NOT_MET', '법정 가입요건 미충족'),
    'confirmation_reason' => $reason('REQUIRED_FACT_MISSING', '가입자격 판정 사실 확인 필요'),
];
$base = [
    'policy_version' => 2,
    'insurance_type_code' => 'EMPLOYMENT_INSURANCE',
    'employment_type_code' => 'ALL',
    'work_scope_code' => 'ALL',
    'decision_model_code' => 'COMPONENT_ELIGIBILITY',
    'required_facts' => [
        ['fact_code' => 'unemployment_applicable', 'fact_name' => '실업급여 적용요건 충족 여부'],
        ['fact_code' => 'vocational_applicable', 'fact_name' => '고용안정·직업능력개발 적용요건 충족 여부'],
    ],
    'overall_aggregation_code' => 'COMPONENT_STATUS_AGGREGATION',
    'reason_codes' => [
        $reason('LEGAL_REQUIREMENT_MET', '법정 가입요건 충족'),
        $reason('LEGAL_REQUIREMENT_NOT_MET', '법정 가입요건 미충족'),
        $reason('REQUIRED_FACT_MISSING', '가입자격 판정 사실 확인 필요'),
    ],
    '_schema' => ['version' => 2, 'condition_language' => 'STRUCTURED_NO_EXPRESSION'],
    'components' => [
        $component('UNEMPLOYMENT_BENEFIT', 'unemployment_applicable'),
        $component('EMPLOYMENT_STABILITY_VOCATIONAL', 'vocational_applicable'),
    ],
];

$validator = new InsuranceEligibilityPolicyValidator();
$validator->validate($base);
$evaluator = new InsuranceEligibilityDecisionModelEvaluator(new InsuranceEligibilityConditionEvaluator());

$eligible = $evaluator->evaluate($base, ['unemployment_applicable' => true, 'vocational_applicable' => true]);
eligibilityV2Assert($eligible['status'] === 'ELIGIBLE', '모든 구성요소 적용 판정이 실패했습니다.');
$partial = $evaluator->evaluate($base, ['unemployment_applicable' => false, 'vocational_applicable' => true]);
eligibilityV2Assert($partial['status'] === 'PARTIALLY_ELIGIBLE', '부분 적용 판정이 실패했습니다.');
$excluded = $evaluator->evaluate($base, ['unemployment_applicable' => false, 'vocational_applicable' => false]);
eligibilityV2Assert($excluded['status'] === 'NOT_ELIGIBLE', '전체 제외 판정이 실패했습니다.');
$unknown = $evaluator->evaluate($base, ['vocational_applicable' => true]);
eligibilityV2Assert($unknown['status'] === 'CONFIRMATION_REQUIRED' && $unknown['component_results'][0]['employee_amount'] === null, '미확정 null 계약이 실패했습니다.');

$nested = $base;
$nested['components'][0]['condition'] = [
    'combination_code' => 'ANY',
    'conditions' => [
        ['fact_code' => 'unemployment_applicable', 'operator' => 'TRUE', 'expected_value' => true],
        [
            'combination_code' => 'ALL',
            'conditions' => [
                ['fact_code' => 'vocational_applicable', 'operator' => 'TRUE', 'expected_value' => true],
                ['fact_code' => 'unemployment_applicable', 'operator' => 'FALSE', 'expected_value' => false],
            ],
        ],
    ],
];
unset($nested['components'][0]['rules']);
$validator->validate($nested);
eligibilityV2Assert(
    $evaluator->evaluate($nested, ['unemployment_applicable' => false, 'vocational_applicable' => true])['component_results'][0]['status_code'] === 'APPLICABLE',
    '중첩 ANY/ALL 조건 판정이 실패했습니다.'
);

$optional = $base;
$optional['components'][0] = $component('UNEMPLOYMENT_BENEFIT', 'unemployment_applicable', 'OPTIONAL');
$validator->validate($optional);
$optionalUnknown = $evaluator->evaluate($optional, ['unemployment_applicable' => true, 'vocational_applicable' => true]);
eligibilityV2Assert($optionalUnknown['status'] === 'CONFIRMATION_REQUIRED', '임의가입 신청사실 누락 판정이 실패했습니다.');
$optionalApplied = $evaluator->evaluate($optional, [
    'unemployment_applicable' => true,
    'vocational_applicable' => true,
    'application_facts' => ['UNEMPLOYMENT_BENEFIT' => 'APPLIED'],
]);
eligibilityV2Assert($optionalApplied['status'] === 'ELIGIBLE', '임의가입 신청 확인 판정이 실패했습니다.');

$dynamicOptional = $base;
$dynamicOptional['components'][0]['optional_application_condition'] = [
    'fact_code' => 'unemployment_applicable', 'operator' => 'FALSE', 'expected_value' => false,
];
$validator->validate($dynamicOptional);
$dynamicOptionalResult = $evaluator->evaluate($dynamicOptional, [
    'unemployment_applicable' => true,
    'vocational_applicable' => true,
]);
eligibilityV2Assert($dynamicOptionalResult['status'] === 'ELIGIBLE', '조건부 임의가입 비대상 판정이 실패했습니다.');

$industrial = [
    'policy_version' => 2,
    'insurance_type_code' => 'INDUSTRIAL_ACCIDENT',
    'employment_type_code' => 'ALL',
    'work_scope_code' => 'ALL',
    'decision_model_code' => 'BUSINESS_AND_WORKER_ELIGIBILITY',
    'required_facts' => [
        ['fact_code' => 'business_applicable', 'fact_name' => '사업장 적용 여부'],
        ['fact_code' => 'worker_status_confirmed', 'fact_name' => '근로자성 확인 여부'],
        ['fact_code' => 'actual_work_confirmed', 'fact_name' => '실제 근로 확인 여부'],
    ],
    'overall_aggregation_code' => 'ALL_STAGES_REQUIRED',
    'reason_codes' => [
        $reason('STAGE_REQUIREMENT_MET', '단계 요건 충족'),
        $reason('STAGE_REQUIREMENT_NOT_MET', '단계 요건 미충족'),
        $reason('REQUIRED_FACT_MISSING', '단계 판정 사실 확인 필요'),
    ],
    '_schema' => ['version' => 2, 'condition_language' => 'STRUCTURED_NO_EXPRESSION'],
    'stages' => array_map(static fn(array $row): array => [
        'stage_code' => $row[0], 'stage_name' => $row[1], 'combination_code' => 'ALL',
        'rules' => [['fact_code' => $row[2], 'operator' => 'TRUE', 'expected_value' => true]],
        'applicable_reason' => $reason('STAGE_REQUIREMENT_MET', '단계 요건 충족'),
        'excluded_reason' => $reason('STAGE_REQUIREMENT_NOT_MET', '단계 요건 미충족'),
        'confirmation_reason' => $reason('REQUIRED_FACT_MISSING', '단계 판정 사실 확인 필요'),
    ], [
        ['BUSINESS_APPLICABILITY', '사업장 적용성', 'business_applicable'],
        ['WORKER_STATUS', '근로자성', 'worker_status_confirmed'],
        ['ACTUAL_WORK_ENGAGEMENT', '실제 근로', 'actual_work_confirmed'],
    ]),
];
$validator->validate($industrial);
eligibilityV2Assert($evaluator->evaluate($industrial, ['business_applicable' => true, 'worker_status_confirmed' => true, 'actual_work_confirmed' => true])['status'] === 'ELIGIBLE', '산재보험 전체 단계 적용 판정이 실패했습니다.');
eligibilityV2Assert($evaluator->evaluate($industrial, ['business_applicable' => false])['status'] === 'NOT_ELIGIBLE', '산재보험 명백한 제외 우선 판정이 실패했습니다.');
eligibilityV2Assert($evaluator->evaluate($industrial, ['business_applicable' => true, 'worker_status_confirmed' => true])['status'] === 'CONFIRMATION_REQUIRED', '산재보험 사실 누락 판정이 실패했습니다.');

echo json_encode(['success' => true, 'component_statuses' => [$eligible['status'], $partial['status'], $excluded['status'], $unknown['status']], 'optional_statuses' => [$optionalUnknown['status'], $optionalApplied['status']]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
