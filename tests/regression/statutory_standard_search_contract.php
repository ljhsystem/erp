<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$model = (string)file_get_contents($root . '/app/Models/System/StatutoryStandardModel.php');

$checks = [
    '종류명 검색 고유 placeholder' => str_contains($model, ':search_type_name'),
    '종류코드 검색 고유 placeholder' => str_contains($model, ':search_type_code'),
    '비고 검색 고유 placeholder' => str_contains($model, ':search_note'),
    '적용상태 검색 고유 placeholder' => str_contains($model, ':search_period_status'),
    '전역검색 placeholder 재사용 금지' => !str_contains($model, 'LIKE :query'),
    '정렬 whitelist 유지' => str_contains($model, "'standard_type_code' => 's.standard_type_code'"),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        throw new RuntimeException($label . ' 계약이 누락됐습니다.');
    }
}

echo json_encode(['success' => true, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
