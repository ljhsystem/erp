<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$page = (string)file_get_contents($root . '/public/assets/js/pages/main/settings/statutory-standards/index.js');
$service = (string)file_get_contents($root . '/app/Services/System/StatutoryStandardService.php');

$checks = [
    '셀 Projection 직접 바인딩' => str_contains($page, "data: 'value_summary'")
        && str_contains($page, "render: data => escapeHtml(data ?? '-')"),
    '계산 가상컬럼 선언' => str_contains($page, "settingsVirtualType: 'calculated'"),
    '가상컬럼 kind 선언' => str_contains($page, "__dtColumnKind: 'virtual'"),
    '정렬 비활성' => str_contains($page, "name: 'value_summary'")
        && str_contains($page, 'orderable: false'),
    'Service Summary Projection' => str_contains($service, '$this->valueSummary->project('),
    '목록 원본 JSON 제거 후 Summary 유지' => str_contains($service, "unset(\$row['value_data']);"),
];

foreach ($checks as $label=>$passed) {
    if (!$passed) throw new RuntimeException("{$label} 계약이 누락됐습니다.");
}

echo json_encode(['success'=>true,'checks'=>$checks], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), PHP_EOL;
