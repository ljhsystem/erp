<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\Institution\RegularEmploymentIncomeDeductionLineService;
use App\Services\Institution\RegularEmploymentIncomeHistoricalService;
use App\Services\Institution\RegularEmploymentIncomePayLineService;
use App\Services\Institution\RegularEmploymentIncomeAccountingGenerationService;

$policy = new RegularEmploymentIncomeDeductionLineService();
$payPolicy = new RegularEmploymentIncomePayLineService();
$actor = 'SYSTEM:REGRESSION';
$settlement = static fn(string $parent, string $type, float $amount, string $period, string $reason): array => $policy->normalizeSettlement([
    'settlement_parent_code' => $parent,
    'settlement_type_code' => $type,
    'settlement_period' => $period,
    'final_amount' => $amount,
    'business_reason' => $reason,
], $actor);
$base = static fn(string $code, float $amount, float $adjustment = 0): array => [
    'id' => 'base-' . $code,
    'item_type_code' => 'DEDUCTION',
    'item_code' => $code,
    'item_name_snapshot' => $code,
    'calculated_amount' => $amount - $adjustment,
    'adjustment_amount' => $adjustment,
    'final_amount' => $amount,
    'application_status_code' => 'APPLICABLE',
];
$pay = ['id'=>'pay','item_type_code'=>'PAY','pay_effect_code'=>'CONTRACT_BASE','item_code'=>'BASE_SALARY','item_name_snapshot'=>'기본급','taxable_flag'=>1,'final_amount'=>1000000];

$healthLines = [$pay,$base('HEALTH_INSURANCE',29120),$settlement('HEALTH_INSURANCE','ADDITIONAL_COLLECTION',15000,'2025','정산 추징'),$settlement('HEALTH_INSURANCE','REFUND',2000,'2024','정산 환급')];
$taxAdditional = [$pay,$base('EMPLOYMENT_INCOME_TAX',20000),$settlement('EMPLOYMENT_INCOME_TAX','ADDITIONAL_COLLECTION',50000,'2025','연말정산 추가징수')];
$taxRefund = [$pay,$base('EMPLOYMENT_INCOME_TAX',20000),$settlement('EMPLOYMENT_INCOME_TAX','REFUND',30000,'2025','연말정산 환급')];
$local = $settlement('LOCAL_INCOME_TAX','ADDITIONAL_COLLECTION',5000,'2025','지방소득세 정산');

$reflection = new ReflectionClass(RegularEmploymentIncomeAccountingGenerationService::class);
$service = $reflection->newInstanceWithoutConstructor();
$projectionMethod = $reflection->getMethod('employeeTransactionPayload');
$header = ['id'=>'fixture-header','payment_date'=>'2026-02-25','income_year_month'=>'2026-02','title'=>'정산 Fixture'];
$item = ['id'=>'fixture-item','employee_id'=>'fixture-employee','employee_name_snapshot'=>'Fixture','employment_contract_id'=>'fixture-contract','gross_amount'=>1000000,'deduction_amount'=>42120,'net_payment_amount'=>957880];
$projectionPay=[
    ['id'=>'pay-1','item_type_code'=>'PAY','item_code'=>'BASE_SALARY','item_name_snapshot'=>'기본급','final_amount'=>700000],
    ['id'=>'pay-2','item_type_code'=>'PAY','item_code'=>'ALLOWANCE','item_name_snapshot'=>'수당','final_amount'=>200000],
    ['id'=>'pay-3','item_type_code'=>'PAY','item_code'=>'BONUS','item_name_snapshot'=>'상여','final_amount'=>50000],
    ['id'=>'pay-4','item_type_code'=>'PAY','item_code'=>'MEAL','item_name_snapshot'=>'식대','final_amount'=>50000],
];
$projectionLines = array_map(static function(array $line, int $index): array {$line['id']=$line['id']??'settlement-'.$index;$line['regular_employment_income_line_item_id']=$line['id'];$line['statutory_standard_revision_id']=$line['item_type_code']==='PAY'?null:'standard-'.$index;$line['calculation_basis_id']='basis-'.$index;return$line;}, array_merge($projectionPay,array_slice($healthLines,1)), array_keys(array_merge($projectionPay,array_slice($healthLines,1))));
$item['line_items']=$projectionLines;
$projection = $projectionMethod->invoke($service, $header, $item, 'fixture-evidence','2026-02-28');
$projectionAgain = $projectionMethod->invoke($service, $header, $item, 'fixture-evidence','2026-02-28');

$checks = [
    'health_29120_plus_15000_minus_2000_is_42120' => $payPolicy->totals($healthLines)['deduction_amount'] === 42120.0,
    'tax_20000_plus_50000_is_70000' => $payPolicy->totals($taxAdditional)['deduction_amount'] === 70000.0,
    'tax_20000_minus_30000_is_negative_10000' => $payPolicy->totals($taxRefund)['deduction_amount'] === -10000.0
        && $payPolicy->totals($taxRefund)['net_payment_amount'] === 1010000.0,
    'local_tax_is_independent' => $policy->parentCode($local) === 'LOCAL_INCOME_TAX',
    'adjustment_not_duplicated' => $payPolicy->totals([$pay,$base('HEALTH_INSURANCE',29000,-120),$settlement('HEALTH_INSURANCE','ADDITIONAL_COLLECTION',15000,'2025','추징')])['deduction_amount'] === 44000.0,
    'projection_current_minus' => count(array_filter($projection['settlements'], static fn(array $row): bool => $row['settlement_type']==='HEALTH_INSURANCE_CURRENT'&&$row['amount_sign']==='MINUS'&&(float)$row['amount']===29120.0)) === 1,
    'projection_collection_minus' => count(array_filter($projection['settlements'], static fn(array $row): bool => $row['settlement_type']==='HEALTH_INSURANCE_SETTLEMENT'&&$row['amount_sign']==='MINUS'&&(float)$row['amount']===15000.0)) === 1,
    'projection_refund_plus' => count(array_filter($projection['settlements'], static fn(array $row): bool => $row['settlement_type']==='HEALTH_INSURANCE_REFUND'&&$row['amount_sign']==='PLUS'&&(float)$row['amount']===2000.0)) === 1,
    'roundtrip_metadata' => $policy->parentCode($healthLines[2])==='HEALTH_INSURANCE'&&$policy->settlementType($healthLines[2])==='ADDITIONAL_COLLECTION'&&$policy->period($healthLines[2])==='2025',
    'historical_refund_direction' => (new RegularEmploymentIncomeHistoricalService())->totals($healthLines)['deduction_amount']===42120.0,
    'projection_idempotent' => $projection === $projectionAgain,
];
$failed = array_keys(array_filter($checks, static fn(bool $value): bool => !$value));
echo json_encode(['success'=>$failed===[],'checks'=>$checks,'failed'=>$failed], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;
exit($failed===[]?0:1);
