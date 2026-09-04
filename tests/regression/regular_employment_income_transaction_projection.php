<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

$reflection = new ReflectionClass(App\Services\Institution\RegularEmploymentIncomeAccountingGenerationService::class);
$service = $reflection->newInstanceWithoutConstructor();
$projection = $reflection->getMethod('employeeTransactionPayload');

$header = ['id'=>'header-1','payment_date' => '2013-09-11', 'income_year_month' => '2013-08', 'title' => '2013년 08월 급여'];
$employee = [
    'id' => 'income-item-1',
    'employee_id' => 'employee-1',
    'employee_name_snapshot' => '이정호',
    'employment_contract_id'=>'contract-1',
    'gross_amount' => 1088890,
    'deduction_amount' => 75480,
    'net_payment_amount' => 1013410,
];
$lines = [
    ['id' => 'pay-1', 'item_type_code' => 'PAY', 'item_code' => 'BASE_SALARY', 'item_name_snapshot' => '기본급', 'calculated_amount' => 1, 'adjustment_amount' => 2, 'final_amount' => 653011],
    ['id' => 'pay-2', 'item_type_code' => 'PAY', 'item_code' => 'OVERTIME_ALLOWANCE', 'item_name_snapshot' => '연장근로수당', 'final_amount' => 304634],
    ['id' => 'pay-3', 'item_type_code' => 'PAY', 'item_code' => 'ANNUAL_LEAVE_ALLOWANCE', 'item_name_snapshot' => '연차수당', 'final_amount' => 31245],
    ['id' => 'pay-4', 'item_type_code' => 'PAY', 'item_code' => 'MEAL_ALLOWANCE', 'item_name_snapshot' => '식대', 'final_amount' => 100000],
    ['id' => 'deduction-1', 'item_type_code' => 'DEDUCTION', 'item_code' => 'NATIONAL_PENSION', 'item_name_snapshot' => '국민연금', 'final_amount' => 45000],
    ['id' => 'deduction-2', 'item_type_code' => 'DEDUCTION', 'item_code' => 'HEALTH_INSURANCE', 'item_name_snapshot' => '건강보험', 'final_amount' => 25000],
    ['id' => 'deduction-3', 'item_type_code' => 'DEDUCTION', 'item_code' => 'OTHER_DEDUCTION', 'item_name_snapshot' => '기타공제', 'final_amount' => 5480],
    ['id' => 'burden-1', 'item_type_code' => 'EMPLOYER_BURDEN', 'item_code' => 'HEALTH_INSURANCE', 'item_name_snapshot' => '건강보험 회사부담', 'final_amount' => 25000],
];
$decorate=static function(array$row):array{return$row+[
    'application_status_code'=>$row['item_type_code']==='PAY'?null:'APPLICABLE',
    'statutory_standard_id'=>$row['item_type_code']==='PAY'?null:'standard-1',
];};
$lines=array_map($decorate,$lines);

$employee['line_items']=$lines;
$payload = $projection->invoke($service, $header, $employee, 'evidence-1','2013-08-31');
$itemTotal = array_sum(array_column($payload['items'], 'item_supply_amount'));
$settlementTotal = array_sum(array_column($payload['settlements'], 'amount'));
$checks = [
    'employee_transaction' => $payload['employee_id'] === 'employee-1',
    'pay_items_n' => count($payload['items']) === 4,
    'deduction_settlements_n' => count($payload['settlements']) === 3,
    'item_total' => abs($itemTotal - 1088890) < 0.01,
    'settlement_total' => abs($settlementTotal - 75480) < 0.01,
    'net_total' => abs(($itemTotal - $settlementTotal) - 1013410) < 0.01,
    'minus_settlements' => count(array_filter($payload['settlements'], static fn(array $row): bool => $row['amount_sign'] !== 'MINUS')) === 0,
    'no_vat' => count(array_filter($payload['settlements'], static fn(array $row): bool => str_contains($row['settlement_type'], 'VAT'))) === 0,
    'no_employer_burden' => count(array_filter($payload['settlements'], static fn(array $row): bool => $row['settlement_description'] === '건강보험 회사부담')) === 0,
    'one_evidence_link' => count($payload['linked_evidences']) === 1,
    'approved_final_amount_only' => abs($payload['items'][0]['item_supply_amount'] - 653011) < 0.01,
];

$second = $employee;
$second['id'] = 'income-item-2';
$second['employee_id'] = 'employee-2';
$second['employee_name_snapshot'] = '박한호';
$second['line_items']=$lines;
$secondPayload = $projection->invoke($service, $header, $second, 'evidence-1','2013-08-31');
$checks['employees_not_mixed'] = $payload['employee_id'] !== $secondPayload['employee_id']
    && str_contains($payload['items'][0]['item_description'], '이정호')
    && str_contains($secondPayload['items'][0]['item_description'], '박한호');
$serviceSource = file_get_contents(PROJECT_ROOT . '/app/Services/Institution/RegularEmploymentIncomeAccountingGenerationService.php');
$checks['accounting_idempotency_guard'] = is_string($serviceSource)
    && str_contains($serviceSource, 'findByRequestKey')
    && str_contains($serviceSource, 'hash_equals');
$checks['legacy_document_transaction_removed'] = is_string($serviceSource)
    && !str_contains((string)file_get_contents(PROJECT_ROOT . '/app/Services/Institution/RegularEmploymentIncomeService.php'), 'private function payrollProjection');
$checks['recognition_date'] = $payload['transaction_date']==='2013-08-31' && $payload['items'][0]['item_date']==='2013-08-31';
$checks['explicit_source_fk'] = $payload['items'][0]['regular_employment_income_line_item_id']==='pay-1' && $payload['settlements'][0]['statutory_standard_revision_id']==='standard-1';

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode(
    ['success' => $failed === [], 'checks' => $checks, 'failed' => $failed],
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
) . PHP_EOL;
exit($failed === [] ? 0 : 1);
