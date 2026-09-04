<?php

declare(strict_types=1);

namespace App\Services\Institution;

use App\Services\System\StatutoryStandardResolver;
use PDO;

final class BusinessIncomeCalculationService
{
    public const STANDARD_TYPE = 'BUSINESS_INCOME_WITHHOLDING';
    public const LOCAL_STANDARD_TYPE = 'LOCAL_INCOME_TAX_WITHHOLDING';
    public const POLICY_NOT_READY = 'CALCULATION_POLICY_NOT_READY';

    private StatutoryStandardResolver $resolver;

    public function __construct(PDO $db) { $this->resolver = new StatutoryStandardResolver($db); }

    public function calculate(string $statutoryReferenceDate, float $gross): array
    {
        if (!$this->isDate($statutoryReferenceDate) || $gross < 0) throw new \InvalidArgumentException('원천징수일과 지급금액을 확인해 주세요.');
        $incomeStandard = $this->resolver->resolve(self::STANDARD_TYPE, $statutoryReferenceDate);
        $localStandard = $this->resolver->resolve(self::LOCAL_STANDARD_TYPE, $statutoryReferenceDate);
        return self::calculateWithStandards($gross, $incomeStandard, $localStandard);
    }

    public static function calculateWithStandards(float $gross, array $incomeStandard, array $localStandard): array
    {
        $incomeValue=(array)($incomeStandard['value_data'] ?? []);
        $localValue=(array)($localStandard['value_data'] ?? []);
        $incomePolicy=(array)($incomeValue['calculation_policy'] ?? []);
        $localPolicy=(array)($localValue['calculation_policy'] ?? []);
        $incomeRate=$incomeValue['rate_value'] ?? $incomeValue['rate'] ?? null;
        $localRate=$localValue['rate_value'] ?? $localValue['rate'] ?? null;
        self::assertPolicy($incomeRate,$incomePolicy,'GROSS_PAYMENT');
        self::assertPolicy($localRate,$localPolicy,'INCOME_TAX');
        $incomeBefore=$gross*(float)$incomeRate;
        $incomeTax=self::applyThreshold(self::round($incomeBefore,$incomePolicy),$incomePolicy);
        $localBefore=$incomeTax*(float)$localRate;
        $localTax=self::applyThreshold(self::round($localBefore,$localPolicy),$localPolicy);
        $total=$incomeTax+$localTax;
        if($total>$gross) throw new \RuntimeException('총 공제액은 총지급액을 초과할 수 없습니다.');
        $lines=[
            self::line('PAY','GROSS_PAYMENT','총지급액',$gross,null,$gross,null,null,$gross,null,1),
            self::line('DEDUCTION','INCOME_TAX','사업소득세',$gross,(float)$incomeRate,$incomeBefore,$incomePolicy['method'],$incomePolicy['discard_below_unit'],$incomeTax,(string)$incomeStandard['id'],2),
            self::line('DEDUCTION','LOCAL_INCOME_TAX','개인지방소득세',$incomeTax,(float)$localRate,$localBefore,$localPolicy['method'],$localPolicy['discard_below_unit'],$localTax,(string)$localStandard['id'],3),
        ];
        return ['policy_status'=>'READY','gross_payment_amount'=>$gross,'income_tax_amount'=>$incomeTax,'local_income_tax_amount'=>$localTax,'total_deduction_amount'=>$total,'net_payment_amount'=>$gross-$total,'lines'=>$lines];
    }

    private static function assertPolicy(mixed $rate,array $policy,string $expectedBase):void
    {
        $required=['method','discard_below_unit','stage','base_value_code','aggregation_unit','application_order','threshold','threshold_comparison'];
        if(!is_numeric($rate)||(float)$rate<0||array_diff($required,array_keys($policy))!==[]||$policy['base_value_code']!==$expectedBase){
            throw new \RuntimeException(self::POLICY_NOT_READY);
        }
        if(!in_array($policy['method'],['TRUNCATE','ROUND','CEIL'],true)||(float)$policy['discard_below_unit']<=0
            ||!is_numeric($policy['application_order'])||!is_numeric($policy['threshold'])
            ||!in_array($policy['threshold_comparison'],['LT','LTE','NONE'],true)){
            throw new \RuntimeException(self::POLICY_NOT_READY);
        }
    }

    private static function round(float $amount,array $policy):float
    {
        $unit=(float)$policy['discard_below_unit'];
        return match($policy['method']){'TRUNCATE'=>floor($amount/$unit)*$unit,'ROUND'=>round($amount/$unit)*$unit,'CEIL'=>ceil($amount/$unit)*$unit};
    }

    private static function applyThreshold(float $amount,array $policy):float
    {
        $threshold=(float)$policy['threshold'];
        return match($policy['threshold_comparison']){'LT'=>$amount<$threshold?0.0:$amount,'LTE'=>$amount<=$threshold?0.0:$amount,default=>$amount};
    }

    private static function line(string $type,string $code,string $name,float $base,?float $rate,float $before,?string $method,mixed $unit,float $amount,?string $standardId,int $sortNo):array
    {
        return ['line_type'=>$type,'line_code'=>$code,'line_name'=>$name,'calculation_base_amount'=>$base,'applied_rate'=>$rate,'amount_before_rounding'=>$before,'rounding_method'=>$method,'rounding_unit'=>$unit,'calculated_amount'=>$amount,'statutory_standard_revision_id'=>$standardId,'applicability_status'=>'APPLICABLE','sort_no'=>$sortNo];
    }

    private function isDate(string $value):bool{$date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);return $date!==false&&$date->format('Y-m-d')===$value;}
}
