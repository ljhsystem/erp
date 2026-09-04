<?php

declare(strict_types=1);

$source = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/common/datatable/dataTableSettings.js');

$required = [
    'const parsedContractKeySet = new Set(parsedKeys);',
    "if (!hasPersistedColumnContract || !parsedContractKeySet.has(key))",
    'visibleSet.add(key);',
];

foreach ($required as $contract) {
    if (!str_contains($source, $contract)) {
        throw new RuntimeException('신규 기본노출 컬럼 병합 계약이 누락됐습니다: ' . $contract);
    }
}

$merge = static function (array $parsed, array $defaults): array {
    $hasPersisted = array_intersect(
        ['visibleColumns', 'columnOrder', 'columnDisplayName', 'columnRequirementPolicy'],
        array_keys($parsed)
    ) !== [];
    $parsedKeys = array_values(array_unique(array_merge(
        (array) ($parsed['columnOrder'] ?? []),
        (array) ($parsed['visibleColumns'] ?? []),
        array_keys((array) ($parsed['columnDisplayName'] ?? [])),
        array_keys((array) ($parsed['columnRequirementPolicy'] ?? []))
    )));
    $visible = array_fill_keys((array) ($parsed['visibleColumns'] ?? []), true);
    foreach ((array) ($defaults['visibleColumns'] ?? []) as $key) {
        if (!$hasPersisted || !in_array($key, $parsedKeys, true)) {
            $visible[$key] = true;
        }
    }
    return array_keys($visible);
};

$defaults = ['visibleColumns' => ['standard_type_code', 'value_summary', 'period_status']];
$legacy = ['visibleColumns' => ['standard_type_code'], 'columnOrder' => ['standard_type_code', 'period_status']];
$merged = $merge($legacy, $defaults);
if (!in_array('value_summary', $merged, true)) {
    throw new RuntimeException('기존 설정에 없던 기준값 컬럼이 기본노출로 병합되지 않았습니다.');
}
if (in_array('period_status', $merged, true)) {
    throw new RuntimeException('사용자가 숨긴 기존 컬럼이 다시 노출됐습니다.');
}

echo "DataTableSettings 신규 기본노출 컬럼 병합 계약: PASS\n";
