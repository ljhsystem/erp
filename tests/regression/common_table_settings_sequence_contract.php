<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$settings = file_get_contents($root . '/public/assets/js/common/datatable/dataTableColumnSettings.js');
$commonTable = file_get_contents($root . '/public/assets/js/common/table/data-table.js');
$dictionary = file_get_contents($root . '/docs/architecture/CommonDictionary.md');

if ($settings === false || $commonTable === false || $dictionary === false) {
    fwrite(STDERR, "공용 TableSettings 순번 계약 파일을 읽을 수 없습니다.\n");
    exit(1);
}

$checks = [
    '물리컬럼 표시순번은 현재 entries 순서로 계산' => str_contains($settings, 'let physicalDisplayOrder = 0;')
        && str_contains($settings, '? ++physicalDisplayOrder'),
    'DB ordinalPosition을 화면 순번으로 사용하지 않음' => !str_contains(
        $settings,
        "sourceOrder: entry.columnType === 'physical' ? entry.ordinalPosition : ''"
    ),
    '가상컬럼은 숫자 대신 기존 유형 아이콘 유지' => str_contains($settings, "virtualType === 'system'")
        && str_contains($settings, 'bi-gear-fill'),
    'DB 원본순번은 원본컬럼명 정보에 유지' => str_contains($settings, 'entry.sourceOrdinalPosition'),
    '목록 본문 순번을 별도로 변경하지 않음' => !str_contains($commonTable, 'updateDisplayedSequenceCells'),
    '공용 사전 등록' => str_contains($dictionary, '| TableSettings displayed sequence |'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}

fwrite(STDOUT, "PASS: 공용 TableSettings 순번은 현재 컬럼 정렬순서를 따릅니다.\n");
