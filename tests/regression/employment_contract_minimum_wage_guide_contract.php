<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$source = file_get_contents($root . '/public/assets/js/pages/institution/employment-contract/modal-runtime.js')
    . file_get_contents($root . '/public/assets/js/pages/institution/employment-contract/statutory-validation.js');
if ($source === false) {
    throw new RuntimeException('근로계약 Modal Runtime을 읽을 수 없습니다.');
}

$checks = [
    '기준단가 입력 참고' => '기준단가 입력 안내가 없습니다.',
    'minimum_wage_only=1&contract_date=' => '계약일 기준 최저임금 조회가 없습니다.',
    "Number(minimumWageGuide.hourly_wage).toLocaleString('ko-KR')" => '최저임금 표시 포맷이 없습니다.',
    '법정기준관리' => '법정기준관리 연결이 없습니다.',
    '계약 단가는 근로시간과 해당 급여항목의 최저임금 산입 여부를 함께 확인해 입력하세요.' => '입력 판단 주의문이 없습니다.',
];
foreach ($checks as $needle => $message) {
    if (!str_contains($source, $needle)) {
        throw new RuntimeException($message);
    }
}

echo "employment contract minimum wage guide UI contract: OK\n";
