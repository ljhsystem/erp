<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Services/Institution/InsuranceEligibilityConditionEvaluator.php';
require_once __DIR__ . '/../../app/Services/Institution/InsuranceEligibilityReasonProjectionService.php';

use App\Services\Institution\InsuranceEligibilityConditionEvaluator;
use App\Services\Institution\InsuranceEligibilityReasonProjectionService;

$service = new InsuranceEligibilityReasonProjectionService();
$result = $service->enrich([
    'reason_code' => 'CONTINUOUS_EMPLOYMENT_PERIOD_NOT_MET',
    'missing_inputs' => [],
    'evaluated_conditions' => [[
        'condition_code' => 'EMPLOYMENT_PERIOD',
        'state' => InsuranceEligibilityConditionEvaluator::FALSE,
        'minimum_continuous_months' => 1,
    ]],
], [
    'reason_codes' => [[
        'code' => 'CONTINUOUS_EMPLOYMENT_PERIOD_NOT_MET',
        'name' => '계속고용기간 요건 미충족',
        'detail' => '1개월 이상 계속근로 요건을 충족하지 않았습니다.',
    ]],
]);

$checks = [
    'revision metadata reason name' => $result['reason_name'] === '계속고용기간 요건 미충족',
    'revision metadata reason detail' => $result['reason_detail'] === '1개월 이상 계속근로 요건을 충족하지 않았습니다.',
    'failed condition projection' => count($result['failed_conditions']) === 1,
    'missing facts projection' => $result['missing_facts'] === [],
    'component projection' => $result['component_results'] === [],
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failed) . PHP_EOL);
    exit(1);
}

echo json_encode(['success' => true, 'checks' => $checks], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
