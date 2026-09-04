<?php

declare(strict_types=1);

$path = dirname(__DIR__, 2) . '/public/assets/js/pages/ledger/evidence-list/modal.js';
$source = file_get_contents($path);

if ($source === false) {
    fwrite(STDERR, "증빙원본 공통 모달 모듈을 읽을 수 없습니다.\n");
    exit(1);
}

$contracts = [
    "title: '\\uC5C5\\uBB34\\uBD84\\uB958\\uC815\\uBCF4'" => '업무분류정보 카드',
    "title: '\\uC6D0\\uBCF8\\uC815\\uBCF4'" => '원본정보 카드',
    "title: '\\uC2DC\\uC2A4\\uD15C \\uCC98\\uB9AC\\uC815\\uBCF4'" => '시스템 처리정보 카드',
    'renderDefaultModalFields(row = {}) {' => '기본 자료유형 모달 렌더러',
    'renderTaxInvoiceModalFields(row);' => '기본 자료유형의 공통 3개 카드 렌더링',
    'resolvePolicyDisplayName(' => 'TableSettings 사용컬럼명 적용',
    'resolvePolicyRequirementMode(' => 'TableSettings 필수구분 적용',
];

foreach ($contracts as $needle => $label) {
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, $label . " 계약이 누락되었습니다.\n");
        exit(1);
    }
}

echo "증빙원본 공통 모달 3개 카드·TableSettings 계약 검증 통과\n";
