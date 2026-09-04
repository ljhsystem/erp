<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Institution\RegularEmploymentIncomeCalculationService;
use App\Services\Institution\RegularEmploymentIncomeService;
use Core\DbPdo;

function recalculateAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$month = $argv[1] ?? '';
$apply = ($argv[2] ?? '') === '--apply';
recalculateAssert((bool) preg_match('/^\d{4}-\d{2}$/', $month), '귀속연월을 YYYY-MM 형식으로 입력해 주세요.');

$db = DbPdo::conn();
$statement = $db->prepare(
    "SELECT id,document_status,income_year_month,payment_date,title,description
       FROM institution_regular_employment_incomes
      WHERE income_year_month=:month AND deleted_at IS NULL
      ORDER BY created_at,id"
);
$statement->execute([':month' => $month]);
$documents = $statement->fetchAll(PDO::FETCH_ASSOC);
recalculateAssert(count($documents) === 1, '해당 귀속연월의 활성 문서가 정확히 1건이어야 합니다.');
$document = $documents[0];
if ($apply) recalculateAssert(in_array($document['document_status'], ['DRAFT','REJECTED','WITHDRAWN'], true), '공식 재계산이 허용된 문서상태가 아닙니다.');

$service = new RegularEmploymentIncomeService($db);
$detail = $service->detail((string) $document['id'])['data'];
$inputs = [];
foreach ($detail['items'] as $item) {
    $inputs[] = [
        'employee_id' => $item['employee_id'],
        'dependent_count_snapshot' => $item['dependent_count_snapshot'],
        'national_pension_basis_snapshot' => $item['national_pension_basis_snapshot'],
        'health_insurance_basis_snapshot' => $item['health_insurance_basis_snapshot'],
        'employment_insurance_basis_snapshot' => $item['employment_insurance_basis_snapshot'],
        'pay_line_items' => array_values(array_filter(
            $item['line_items'],
            static fn(array $line): bool => $line['item_type_code'] === 'PAY'
                && in_array($line['pay_effect_code'] ?? null, ['INCREASE','DECREASE'], true)
        )),
        'deduction_line_items' => array_values(array_filter(
            $item['line_items'],
            static fn(array $line): bool => $line['item_type_code'] === 'DEDUCTION'
                && str_starts_with((string) ($line['source_key'] ?? ''), 'SETTLEMENT|')
        )),
        'insurance_override_line_items' => array_values(array_filter(
            $item['line_items'],
            static fn(array $line): bool => $line['item_type_code'] === 'DEDUCTION'
                && in_array($line['item_code'] ?? '', ['NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE'], true)
                && (abs((float) ($line['adjustment_amount'] ?? 0)) >= 0.01 || str_starts_with((string) ($line['source_key'] ?? ''), 'INSURANCE_OVERRIDE'))
        )),
    ];
}

$actor = 'SYSTEM:REGULAR_INCOME_RECALCULATION';

$calculation = (new RegularEmploymentIncomeCalculationService($db))->preview(
    $month,
    (string) $document['payment_date'],
    $inputs,
    $actor
);
recalculateAssert($calculation['readiness'] === 'READY', '최신 법정기준 재계산 결과가 확정 상태가 아닙니다.');
$totals = ['gross_amount'=>0.0,'deduction_amount'=>0.0,'net_payment_amount'=>0.0];
foreach ($calculation['results'] as $result) {
    foreach (array_keys($totals) as $key) $totals[$key] += (float) $result[$key];
}

if ($apply) {
    $service->recalculatePayEffectAsSystem([
        'id' => $document['id'],
        'income_year_month' => $month,
        'payment_date' => $document['payment_date'],
        'title' => $document['title'],
        'description' => $document['description'],
        'items' => $calculation['results'],
    ]);
}

echo json_encode([
    'success' => true,
    'mode' => $apply ? 'APPLIED' : 'DRY_RUN',
    'document_id' => $document['id'],
    'actor' => $actor,
    'employees' => $calculation['results'],
    'totals' => array_map(static fn(float $value): float => round($value, 2), $totals),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
