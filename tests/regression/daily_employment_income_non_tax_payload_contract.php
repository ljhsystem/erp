<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

use App\Services\Institution\DailyEmploymentIncomeNonTaxPayloadService;

$service = new DailyEmploymentIncomeNonTaxPayloadService();
$base = [
    'daily_employment_income_id' => 'header-1',
    'daily_employment_income_item_id' => 'item-1',
    'applied_amount' => '1000',
    'application_reason' => '식대 비과세',
    'legal_basis' => '법정기준',
    'calculation_details' => '1,000원',
    'statutory_standard_id' => 'standard-1',
    'attachment_ids' => ['attachment-b', 'attachment-a'],
    'display_name' => '해시 제외 표시명',
];
$same = $base;
$same['attachment_ids'] = ['attachment-a', 'attachment-b'];
$same['display_name'] = '변경된 표시명';
$different = $base;
$different['applied_amount'] = '1001';

$firstHash = $service->hash('NON_TAX_CREATE', $base);
$sameHash = $service->hash('NON_TAX_CREATE', $same);
$differentHash = $service->hash('NON_TAX_CREATE', $different);

$assertions = [
    '동일 공식 Payload는 같은 hash' => hash_equals($firstHash, $sameHash),
    '금액이 다르면 다른 hash' => !hash_equals($firstHash, $differentHash),
    'SHA-256 소문자 형식' => preg_match('/^[0-9a-f]{64}$/', $firstHash) === 1,
];

foreach ($assertions as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}

echo "OK: 비과세 Command Payload canonicalization 계약\n";
