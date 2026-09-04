<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

use App\Services\Institution\BusinessIncomeCalculationService;

$income=['id'=>'income-revision','value_data'=>['rate_value'=>0.03,'calculation_policy'=>['method'=>'TRUNCATE','discard_below_unit'=>1,'stage'=>'WITHHOLDING','base_value_code'=>'GROSS_PAYMENT','aggregation_unit'=>'RECIPIENT_PAYMENT','application_order'=>1,'threshold'=>0,'threshold_comparison'=>'NONE']]];
$local=['id'=>'local-revision','value_data'=>['rate_value'=>0.1,'calculation_policy'=>['method'=>'TRUNCATE','discard_below_unit'=>1,'stage'=>'AFTER_NATIONAL_WITHHOLDING_TAX','base_value_code'=>'INCOME_TAX','aggregation_unit'=>'RECIPIENT_PAYMENT','application_order'=>2,'threshold'=>0,'threshold_comparison'=>'NONE']]];
$normal=BusinessIncomeCalculationService::calculateWithStandards(1000000,$income,$local);
$repeatA=BusinessIncomeCalculationService::calculateWithStandards(500000,$income,$local);
$repeatB=BusinessIncomeCalculationService::calculateWithStandards(700000,$income,$local);
$blocked=false;try{BusinessIncomeCalculationService::calculateWithStandards(1000000,['id'=>'rate-only','value_data'=>['rate_value'=>0.03]],$local);}catch(RuntimeException $exception){$blocked=$exception->getMessage()===BusinessIncomeCalculationService::POLICY_NOT_READY;}
$checks=[
    '정상 소득세'=>$normal['income_tax_amount']===30000.0,
    '지방소득세는 확정 소득세의 10%'=>$normal['local_income_tax_amount']===3000.0,
    '최종식 대사'=>$normal['net_payment_amount']===967000.0,
    '같은 소득자 복수 지급 비병합'=>$repeatA['net_payment_amount']+$repeatB['net_payment_amount']===1160400.0,
    '계산정책 미완성 차단'=>$blocked,
    'Calculation Line 전체'=>array_column($normal['lines'],'line_code')===['GROSS_PAYMENT','INCOME_TAX','LOCAL_INCOME_TAX'],
];
$failed=array_keys(array_filter($checks,static fn(bool $passed):bool=>!$passed));
echo json_encode(['success'=>$failed===[],'checks'=>$checks,'failed'=>$failed,'normal'=>$normal],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($failed===[]?0:1);
