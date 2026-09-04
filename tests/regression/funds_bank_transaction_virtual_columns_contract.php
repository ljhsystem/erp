<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$script = file_get_contents($root . '/public/assets/js/pages/funds/bank-transactions/table.js');

if ($script === false) {
    fwrite(STDERR, "계좌별 거래내역 테이블 파일을 읽을 수 없습니다.\n");
    exit(1);
}

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$expect(
    str_contains($script, "settingsVirtualType: 'calculated'")
        && str_contains($script, "__dtColumnKind: 'virtual'"),
    '계산 가상컬럼의 공용 TableSettings 분류 계약이 없습니다.'
);

foreach ([
    'payment_link_label',
    'internal_transfer_label',
    'internal_transfer_direction_label',
    'internal_transfer_counterpart_label',
    'internal_transfer_voucher_no',
    'internal_transfer_amount',
] as $key) {
    $expect(
        str_contains($script, "calculatedColumn('{$key}'"),
        "{$key} 컬럼이 공용 TableSettings 계산 가상컬럼으로 등록되지 않았습니다."
    );
}

echo "계좌별 거래내역 계산 가상컬럼 계약 검증 통과\n";
