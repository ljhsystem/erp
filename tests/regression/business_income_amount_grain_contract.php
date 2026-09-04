<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

use App\Services\Institution\BusinessIncomeEvidenceCanonicalPolicy;

$policy = new BusinessIncomeEvidenceCanonicalPolicy();
$fixture = static function (float $gross, float $incomeTax, float $localTax) use ($policy): array {
    $deduction = $incomeTax + $localTax;
    $net = $gross - $deduction;
    $evidence = [
        'source_type'=>'INTERNAL_APPROVAL','import_type'=>'BUSINESS_INCOME_REPORT','transaction_direction'=>'EXPENSE','operation_type'=>'BUSINESS_INCOME','employee_id'=>null,
        'raw_gross_payment_amount'=>$gross,'raw_total_deduction_amount'=>$deduction,'raw_net_payment_amount'=>$net,
    ];
    $policy->assert($evidence);
    return ['gross'=>$gross,'deduction'=>$deduction,'net'=>$net,'evidence_total'=>$evidence['raw_gross_payment_amount'],
        'settlement_total'=>$net+$incomeTax+$localTax];
};

$cases = [
    'A_원천징수없음'=>$fixture(1000000,0,0),
    'B_법정원천징수'=>$fixture(1000000,30000,3000),
];
$multi = [$fixture(1000000,30000,3000),$fixture(500000,15000,1500)];
$blocked = false;
try {
    $invalid = ['source_type'=>'INTERNAL_APPROVAL','import_type'=>'BUSINESS_INCOME_REPORT','transaction_direction'=>'EXPENSE','operation_type'=>'BUSINESS_INCOME','employee_id'=>null,
        'raw_gross_payment_amount'=>1000000,'raw_total_deduction_amount'=>33000,'raw_net_payment_amount'=>966999];
    $policy->assert($invalid);
} catch (DomainException $error) {
    $blocked = $error->getMessage() === 'BUSINESS_INCOME_EVIDENCE_AMOUNT_GRAIN_INVALID';
}
$success = array_reduce($cases,static fn(bool $ok,array $case):bool=>$ok&&$case['gross']===$case['evidence_total']&&$case['gross']===$case['settlement_total'],true)
    && array_sum(array_column($multi,'gross'))===array_sum(array_column($multi,'evidence_total'))
    && $blocked;
echo json_encode(['success'=>$success,'cases'=>$cases,'multiple_recipients'=>['item_count'=>count($multi),'header_gross'=>array_sum(array_column($multi,'gross')),'evidence_total'=>array_sum(array_column($multi,'evidence_total'))],'mismatch_blocked'=>$blocked],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($success?0:1);
