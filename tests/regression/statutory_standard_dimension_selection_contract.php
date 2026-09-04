<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

$javascript = (string)file_get_contents($root . '/public/assets/js/pages/main/settings/statutory-standards/index.js');
$settingsView = (string)file_get_contents($root . '/app/views/main/settings.php');
$serviceSource = (string)file_get_contents($root . '/app/Services/System/StatutoryStandardService.php');
$service = new ReflectionClass(App\Services\System\StatutoryStandardService::class);
$instance = $service->newInstanceWithoutConstructor();
$validator = $service->getMethod('assertInsuranceDimensionCombination');

$allowed = [
    ['PREMIUM', 'ALL', 'ALL'],
    ['ELIGIBILITY', 'REGULAR', 'HEAD_OFFICE'],
    ['ELIGIBILITY', 'DAILY', 'HEAD_OFFICE'],
    ['ELIGIBILITY', 'DAILY', 'CONSTRUCTION_SITE'],
];
foreach ($allowed as $arguments) {
    $validator->invoke($instance, ...$arguments);
}

$blocked = [
    ['ELIGIBILITY', 'REGULAR', 'CONSTRUCTION_SITE'],
    ['ELIGIBILITY', 'ALL', 'ALL'],
    ['PREMIUM', 'DAILY', 'HEAD_OFFICE'],
];
foreach ($blocked as $arguments) {
    try {
        $validator->invoke($instance, ...$arguments);
        throw new RuntimeException('금지된 Dimension 조합이 허용됐습니다: ' . implode('/', $arguments));
    } catch (InvalidArgumentException $exception) {
        if (!str_contains($exception->getMessage(), '지원하지 않는')) throw $exception;
    }
}

$checks = [
    'system_codes options 재사용' => str_contains($javascript, 'optionGroups[valueKey]'),
    '가입자격 ALL 제외' => substr_count($javascript, "filter(value => value !== 'ALL')") >= 2,
    '가입자격 미선택 초기화' => str_contains($javascript, "employmentTypeSelect.value = resetDependents ? ''")
        && str_contains($javascript, "workScopeSelect.value = resetDependents ? ''"),
    '보험료 ALL 고정' => str_contains($javascript, "employmentTypeSelect.value = 'ALL'")
        && str_contains($javascript, "workScopeSelect.value = 'ALL'"),
    '보험료 Select 비활성' => str_contains($javascript, "['ALL'], 'employment_type_code', { disabled: true }")
        && str_contains($javascript, "['ALL'], 'work_scope_code', { disabled: true }"),
    '가입자격 필수 표시' => str_contains($javascript, "componentSelect?.value === 'ELIGIBILITY'")
        && str_contains($javascript, "['employment_type_code', 'work_scope_code'].includes(key)"),
    '서버 TableSettings 필수검증 대상' => str_contains($serviceSource, "'policy_component_code', 'employment_type_code', 'work_scope_code'"),
    'Select2 change 계약' => str_contains($javascript, 'window.jQuery(select).off(`change.${namespace}`).on(`change.${namespace}`, handler)')
        && str_contains($javascript, "bindDimensionChange(componentSelect, 'statutoryComponent'"),
    'Select2 disabled 동기화' => str_contains($javascript, ".prop('disabled', disabled)"),
    '브라우저 캐시 갱신' => str_contains($settingsView, 'index.js?v=20260831-dimension-2'),
];
foreach ($checks as $label => $passed) {
    if (!$passed) throw new RuntimeException($label . ' 계약이 누락됐습니다.');
}

echo json_encode([
    'success' => true,
    'allowed_count' => count($allowed),
    'blocked_count' => count($blocked),
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
