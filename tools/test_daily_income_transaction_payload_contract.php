<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

use App\Services\Institution\DailyEmploymentIncomeAccountingGenerationService;

$reflection = new ReflectionClass(DailyEmploymentIncomeAccountingGenerationService::class);
$service = $reflection->newInstanceWithoutConstructor();
$method = $reflection->getMethod('workerPaymentPayload');
$basePlan = [
    'header' => [
        'id' => 'document-fixture', 'income_year_month' => '2013-08',
        'payment_date' => '2013-09-11', 'document_title' => '2013년 8월귀속 일용근로',
    ],
    'revision' => ['id' => 'revision-fixture', 'source_hash' => str_repeat('a', 64)],
    'approval_request_id' => 'approval-fixture',
];
$group = ['id' => 'group-fixture', 'business_unit' => 'HQ', 'project_id' => null, 'work_team_id' => null, 'work_description' => 'Fixture 작업'];
$case = static function (float $gross, float $deduction, array $lines) use ($basePlan, $group, $method, $service): array {
    $item = [
        'id' => 'item-fixture', 'worker_client_id' => 'worker-fixture', 'worker_name_snapshot' => 'Fixture 근로자',
        'total_gross_amount' => $gross, 'total_deduction_amount' => $deduction,
        'total_net_payment_amount' => $gross - $deduction, 'lines' => $lines,
    ];
    $payload = $method->invoke($service, $basePlan, $group, $item);
    $settlementTotal = array_sum(array_map(static fn(array $row): float => $row['amount_sign'] === 'MINUS' ? -(float) $row['amount'] : (float) $row['amount'], $payload['settlements']));
    if ((float) $payload['items'][0]['item_supply_amount'] !== $gross
        || round($settlementTotal, 2) !== round(-$deduction, 2)
        || round($gross + $settlementTotal, 2) !== round((float) $payload['transaction_final_amount'], 2)) {
        throw new RuntimeException('일용 지급 Payload 합계 계약이 일치하지 않습니다.');
    }
    return ['item_total' => $gross, 'settlement_count' => count($payload['settlements']), 'settlement_total' => $settlementTotal, 'final_total' => $payload['transaction_final_amount']];
};
$line = static fn(string $id, string $code, float $amount): array => [
    'id' => $id, 'line_type_code' => 'DEDUCTION', 'line_code' => $code,
    'line_name_snapshot' => $code, 'application_status_code' => 'APPLICABLE',
    'final_amount' => $amount, 'statutory_standard_id' => 'standard-' . $id,
];

$results = [
    'zero_deduction' => $case(100000, 0, [$line('zero', 'EMPLOYMENT_INCOME_TAX', 0)]),
    'employment_insurance_only' => $case(452940, 2940, [$line('employment', 'EMPLOYMENT_INSURANCE', 2940)]),
    'multiple_deductions' => $case(100000, 1500, [
        $line('tax', 'DAILY_WORKER_INCOME_TAX', 1000),
        $line('insurance', 'EMPLOYMENT_INSURANCE', 500),
    ]),
];

echo json_encode(['success' => true, 'cases' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
