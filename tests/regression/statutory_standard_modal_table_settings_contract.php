<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$page = (string)file_get_contents($root . '/public/assets/js/pages/main/settings/statutory-standards/index.js');
$adapter = (string)file_get_contents($root . '/public/assets/js/common/datatable/dataTableFormSettings.js');

$checks = [
    '공용 FormSettings import' => str_contains($page, "import { createDataTableFormSettings }"),
    '법정기준 metaDomain 연결' => str_contains($page, "metaDomain: 'statutory-standard'"),
    'TABLE 저장키 단일 사용' => substr_count($page, 'TABLE_SETTINGS_STORAGE_KEY') >= 3,
    '공용 apply 사용' => str_contains($page, 'formSettings.apply();'),
    '동적 Header도 공용 FormSettings 대상' => str_contains($page, 'name="${name}"')
        && !str_contains($page, 'data-dimension-label'),
    '가입자격 업무필수 합성' => str_contains($page, "componentSelect?.value === 'ELIGIBILITY'")
        && str_contains($page, "['employment_type_code', 'work_scope_code'].includes(key)"),
    '현재 DataTable 설정 우선' => str_contains($page, 'stateProvider: () => table?.__dtTableSettings?.getTableState?.() || null')
        && str_contains($adapter, "typeof stateProvider === 'function'"),
    'storageKey 설정변경 즉시 반영' => str_contains($page, 'eventStorageKey === TABLE_SETTINGS_STORAGE_KEY'),
    '모달 표시 시 설정 재적용' => str_contains($page, 'applyPhysicalFieldSettings();'),
    '화면 전용 meta label 반복 제거' => !str_contains($page, "document.querySelectorAll('[data-meta-label]').forEach"),
    '사용컬럼명 적용' => str_contains($adapter, 'resolveDataTableColumnDisplayName'),
    '필수구분 적용' => str_contains($adapter, 'resolveDataTableColumnRequirementPolicy'),
    'DB 필수 기본값의 저장정책 덮어쓰기 금지' => !str_contains($adapter, 'const required = Boolean(column.required)'),
    '필수 빨강별표' => str_contains($adapter, "policy === POLICY.REQUIRED ? 'text-danger' : 'text-primary'"),
    '선택 파랑별표' => str_contains($adapter, 'POLICY.OPTIONAL'),
    '선택없음 별표 미생성' => str_contains($adapter, "if (![POLICY.REQUIRED, POLICY.OPTIONAL].includes(policy)) return;"),
];

foreach ($checks as $label => $passed) {
    if (!$passed) throw new RuntimeException("{$label} 계약이 누락됐습니다.");
}

echo json_encode(['success'=>true,'checks'=>$checks], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), PHP_EOL;
