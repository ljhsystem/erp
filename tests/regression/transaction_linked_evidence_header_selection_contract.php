<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$transaction = file_get_contents($root . '/public/assets/js/pages/ledger/transaction/modal.js');
$voucher = file_get_contents($root . '/public/assets/js/pages/ledger/voucher/evidence-links.js');
$htmlGrid = file_get_contents($root . '/public/assets/js/common/html-grid/index.js');
$css = file_get_contents($root . '/public/assets/css/pages/ledger/transaction/grid.css');

$selectionColumn = "{ key: 'selection', label: '', type: 'selection', headerSelection: true, width: 56 }";
$assertions = [
    '거래입력 증빙연결 선택 헤더 체크박스' => str_contains($transaction, $selectionColumn),
    '선택 헤더 문구 제거' => !str_contains($transaction, "{ key: 'selection', label: '선택', type: 'selection'"),
    '전표입력과 동일한 선택열 계약' => str_contains($voucher, $selectionColumn),
    '공용 전체선택 처리' => str_contains($htmlGrid, "checkbox.classList.contains('html-grid-header-selection')")
        && str_contains($htmlGrid, 'selectedAll: checkbox.checked'),
    '공용 전체선택 상태 동기화' => str_contains($htmlGrid, 'checkbox.indeterminate = selectedSelectableCount > 0')
        && str_contains($htmlGrid, 'checkbox.disabled = selectableRows.length === 0'),
    '거래입력 선택열 상하좌우 중앙정렬' => str_contains($css, '#transactionLinkedEvidencesGrid .html-grid-header-cell-selection {')
        && str_contains($css, '#transactionLinkedEvidencesGrid .html-grid-header-cell-selection .html-grid-header-cell-content {')
        && str_contains($css, '#transactionLinkedEvidencesGrid .html-grid-header-cell-selection .html-grid-header-selection {')
        && str_contains($css, 'align-items: center;')
        && str_contains($css, 'justify-content: center;')
        && str_contains($css, 'float: none;'),
];

$failed = array_keys(array_filter($assertions, static fn(bool $passed): bool => !$passed));
if ($failed !== []) {
    fwrite(STDERR, 'FAIL: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'PASS: transaction linked evidence header selection contract' . PHP_EOL;
