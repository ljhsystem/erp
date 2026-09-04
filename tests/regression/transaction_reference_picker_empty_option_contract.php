<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$view = (string) file_get_contents($root . '/app/views/ledger/transaction/partials/transaction_modal.php');
$selects = (string) file_get_contents($root . '/public/assets/js/pages/ledger/transaction/selects.js');
$index = (string) file_get_contents($root . '/public/assets/js/pages/ledger/transaction/index.js');

$legacyLabels = [
    '거래처 검색', '프로젝트 검색', '계좌 검색', '카드 검색', '팀 검색', '직원 검색',
    '계좌선택', '카드선택', '팀선택', '직원선택',
];

foreach ($legacyLabels as $label) {
    if (str_contains($view, $label) || str_contains($selects, $label)) {
        fwrite(STDERR, "FAIL: 거래입력 참조 Picker에 화면별 빈값 문구가 남아 있습니다: {$label}\n");
        exit(1);
    }
}

if (substr_count($view, '<option value="">선택(없음)</option>') < 6
    || !str_contains($selects, "setStaticSelectValue(ctx.bankAccountSelectEl, value, text, '선택(없음)');")
    || !str_contains($index, "import('./selects.js?v=20260826-common-empty-option-1')")) {
    fwrite(STDERR, "FAIL: 공용 Select2 선택(없음) 계약이 거래입력 모달에 적용되지 않았습니다.\n");
    exit(1);
}

fwrite(STDOUT, "PASS: 거래입력 참조 Picker가 공용 선택(없음) 계약을 사용합니다.\n");
