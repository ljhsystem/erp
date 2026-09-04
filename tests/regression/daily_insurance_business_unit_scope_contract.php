<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\Institution\DailyEmploymentIncomeBusinessUnitPolicyService;

$service = new DailyEmploymentIncomeBusinessUnitPolicyService();
$count = 0;
$assert = static function (bool $condition, string $message) use (&$count): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $count++;
};
$policy = static fn(string $code, bool $project): array => [
    'id' => $code,
    'uses_project' => $project,
    'requires_project' => $project,
    'uses_work_team' => $project,
    'requires_work_team' => $project,
];

foreach ([
    ['HQ', null, '본사 시설 설치공사', 'HEAD_OFFICE', 'HQ_NON_CONSTRUCTION_POLICY'],
    ['ECOMMERCE', null, '쇼핑몰 포장 시공', 'HEAD_OFFICE', 'ECOMMERCE_NON_CONSTRUCTION_POLICY'],
    ['CONSTRUCTION', 'project-1', '전문건설 현장', 'CONSTRUCTION_SITE', 'CONSTRUCTION_SITE_POLICY'],
] as [$businessUnit, $projectId, $description, $expectedScope, $expectedReason]) {
    $result = $service->eligibilityScope($policy($businessUnit, $projectId !== null), $projectId);
    $assert($result['employment_type_code'] === 'DAILY', $description . ': 고용형태는 DAILY여야 합니다.');
    $assert($result['business_unit_code'] === $businessUnit, $description . ': 사업구분이 보존되어야 합니다.');
    $assert($result['eligibility_work_scope_code'] === $expectedScope, $description . ': 작업명과 무관한 Scope여야 합니다.');
    $assert($result['derivation_reason_code'] === $expectedReason, $description . ': 사업구분별 파생근거가 보존되어야 합니다.');
}

$missingProject = $service->eligibilityScope($policy('CONSTRUCTION', true), null);
$assert($missingProject['eligibility_work_scope_code'] === null, '전문건설업 프로젝트 누락은 비건설 Scope로 fallback하면 안 됩니다.');
$assert(($missingProject['missing_inputs'][0]['field'] ?? null) === 'project_id', '누락 필드는 project_id여야 합니다.');

$sameWorkerHeadOffice = $service->eligibilityScope($policy('HQ', false), null);
$sameWorkerConstruction = $service->eligibilityScope($policy('CONSTRUCTION', true), 'project-2');
$assert($sameWorkerHeadOffice['employment_type_code'] === $sameWorkerConstruction['employment_type_code'], '동일 근로자의 고용형태는 Group별로 바뀌면 안 됩니다.');
$assert($sameWorkerHeadOffice['eligibility_work_scope_code'] !== $sameWorkerConstruction['eligibility_work_scope_code'], '동일 근로자의 Scope는 Group별 사업구분에서 파생되어야 합니다.');

echo 'PASS business-unit scope contract: ' . $count . PHP_EOL;
