<?php

declare(strict_types=1);

use App\Services\Institution\RegularEmploymentIncomeAccountingGenerationService;

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/core/Storage.php';
require PROJECT_ROOT . '/core/Bootstrap.php';

$service = (new ReflectionClass(RegularEmploymentIncomeAccountingGenerationService::class))->newInstanceWithoutConstructor();
$base = [
    'item_type_code'=>'EMPLOYER_BURDEN','item_code'=>'EMPLOYMENT_INSURANCE_VOCATIONAL',
    'item_name_snapshot'=>'고용안정·직업능력개발 부담','application_status_code'=>'APPLICABLE',
    'final_amount'=>2470,'calculation_basis_amount'=>988890,'calculation_rate'=>0.0025,
    'calculation_before_rounding'=>2472.225,'rounding_method_code'=>'TRUNCATE','rounding_unit'=>10,
    'statutory_standard_id'=>'standard','social_insurance_coverage_id'=>'coverage','workplace_size_period_id'=>'workplace',
    'calculated_amount'=>2470,'calculation_source_code'=>'CALCULATED','business_source_code'=>'CALCULATED',
    'processed_at'=>'2026-08-28 15:00:00','processed_by'=>'SYSTEM:REGRESSION',
];
$passes = static function(array $line)use($service):bool{try{$service->validateStoredLineSnapshot($line);return true;}catch(Throwable){return false;}};
$fails = static fn(array $line):bool=>!$passes($line);
$error = static function(array $line)use($service):string{try{$service->validateStoredLineSnapshot($line);return '';}catch(Throwable $e){return $e->getMessage();}};
$without = static function(array $line,string $key):array{$line[$key]=null;return$line;};
$excluded=$base;$excluded['item_code']='EMPLOYMENT_INSURANCE';$excluded['application_status_code']='EXCLUDED';$excluded['final_amount']=0;$excluded['calculated_amount']=null;$excluded['calculation_message']='근로계약상 적용 제외';foreach(['calculation_basis_amount','calculation_rate','calculation_before_rounding','rounding_method_code','rounding_unit','statutory_standard_id','social_insurance_coverage_id','workplace_size_period_id','business_source_code','processed_at','processed_by']as$key)$excluded[$key]=null;
$incomeTax=$base;$incomeTax['item_type_code']='DEDUCTION';$incomeTax['item_code']='EMPLOYMENT_INCOME_TAX';$incomeTax['final_amount']=0;$incomeTax['calculation_rate']=null;$incomeTax['calculation_before_rounding']=null;$incomeTax['rounding_method_code']=null;$incomeTax['rounding_unit']=null;$incomeTax['social_insurance_coverage_id']=null;$incomeTax['workplace_size_period_id']=null;
$localTax=$base;$localTax['item_type_code']='DEDUCTION';$localTax['item_code']='LOCAL_INCOME_TAX';$localTax['final_amount']=0;$localTax['calculation_basis_amount']=0;$localTax['calculation_rate']=0.1;$localTax['calculation_before_rounding']=0;$localTax['social_insurance_coverage_id']=null;$localTax['workplace_size_period_id']=null;
$pay=['item_type_code'=>'PAY','item_code'=>'BASE_SALARY','item_name_snapshot'=>'기본급','final_amount'=>1000,'application_status_code'=>null,'calculation_basis_amount'=>null,'calculation_rate'=>null,'calculation_before_rounding'=>null,'rounding_method_code'=>null,'rounding_unit'=>null,'statutory_standard_id'=>null,'social_insurance_coverage_id'=>null,'workplace_size_period_id'=>null];
$pension=$base;$pension['item_code']='NATIONAL_PENSION';$pension['workplace_size_period_id']=null;
$health=$base;$health['item_code']='HEALTH_INSURANCE';$health['workplace_size_period_id']=null;
$care=$base;$care['item_code']='LONG_TERM_CARE';$care['workplace_size_period_id']=null;
$employment=$base;$employment['item_code']='EMPLOYMENT_INSURANCE';$employment['workplace_size_period_id']=null;
$industrial=$base;$industrial['item_code']='INDUSTRIAL_ACCIDENT_INSURANCE';$industrial['social_insurance_coverage_id']=null;$industrial['workplace_size_period_id']=null;
$confirmation=$base;$confirmation['application_status_code']='CONFIRMATION_REQUIRED';$confirmation['final_amount']=0;
$excludedWithoutReason=$excluded;$excludedWithoutReason['calculation_message']=null;
$checks=[
    '정상 직업능력개발'=>$passes($base),
    '계산기초 NULL 차단'=>$fails($without($base,'calculation_basis_amount')),
    '법정기준 FK NULL 차단'=>$fails($without($base,'statutory_standard_id')),
    'Coverage FK NULL 차단'=>$fails($without($base,'social_insurance_coverage_id')),
    '회사규모 FK NULL 차단'=>$fails($without($base,'workplace_size_period_id')),
    'EXCLUDED 통과'=>$passes($excluded),
    '근로소득세 간이세액표 통과'=>$passes($incomeTax),
    '지방소득세 0원 기초 통과'=>$passes($localTax),
    '비법정 PAY NULL 통과'=>$passes($pay),
    '국민연금 계산기초 NULL 차단'=>$fails($without($pension,'calculation_basis_amount')),
    '건강보험 Coverage NULL 차단'=>$fails($without($health,'social_insurance_coverage_id')),
    '장기요양보험 Coverage NULL 차단'=>$fails($without($care,'social_insurance_coverage_id')),
    '고용보험 Coverage NULL 차단'=>$fails($without($employment,'social_insurance_coverage_id')),
    '직업능력개발 Coverage NULL 차단'=>$fails($without($base,'social_insurance_coverage_id')),
    '직업능력개발 회사규모 NULL 차단'=>$fails($without($base,'workplace_size_period_id')),
    '국민연금 회사규모 NULL 허용'=>$passes($pension),
    '건강보험 회사규모 NULL 허용'=>$passes($health),
    '고용보험 회사규모 NULL 허용'=>$passes($employment),
    '산재보험 회사규모 오류 아님'=>!str_contains($error($industrial),'회사규모'),
    '산재보험 공식 Scope 차단'=>str_contains($error($industrial),'공식 Scope'),
    '적용 제외 사유 없음 차단'=>$fails($excludedWithoutReason),
    '확인 필요 사용자부담 차단'=>$fails($confirmation),
    '자동계산액 NULL 차단'=>$fails($without($base,'calculated_amount')),
    '실제 적용원천 NULL 차단'=>$fails($without($base,'business_source_code')),
    '처리자 NULL 차단'=>$fails($without($base,'processed_by')),
    '처리시각 NULL 차단'=>$fails($without($base,'processed_at')),
];
$failed=array_keys(array_filter($checks,static fn(bool$value):bool=>!$value));
echo json_encode(['success'=>$failed===[],'checks'=>$checks,'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($failed===[]?0:1);
