<?php

require_once __DIR__ . '/../../app/Services/Institution/WorkplaceSizeRateResolver.php';

use App\Services\Institution\WorkplaceSizeRateResolver;

function assertContract(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$up = file_get_contents(__DIR__ . '/../../app/migrations/20260826_01_create_workplace_size_period_ssot.up.sql');
$down = file_get_contents(__DIR__ . '/../../app/migrations/20260826_01_create_workplace_size_period_ssot.down.sql');
$model = file_get_contents(__DIR__ . '/../../app/Models/Institution/WorkplaceSizePeriodModel.php');
foreach (['calculation_purpose_code','business_size_code','business_size_name_snapshot','regular_worker_count','evidence_type_code','confirmed_by','confirmed_at','previous_period_id','correction_reason','request_key'] as $column) assertContract(str_contains($up, '`'.$column.'`'), '회사규모 필수 컬럼 누락: '.$column);
foreach (['APPLICABLE','EXCLUDED','NOT_APPLICABLE'] as $status) assertContract(str_contains($up, $status), '적용판정 코드 누락: '.$status);
foreach (['calculation_basis_amount','calculation_rate','calculation_before_rounding','rounding_method_code','rounding_unit','statutory_standard_id','social_insurance_coverage_id','workplace_size_period_id'] as $column) assertContract(str_contains($up, '`'.$column.'`'), '계산결과 컬럼 누락: '.$column);
assertContract(str_contains($model, 'calculation_purpose_code = :purpose_code'), '기간중복 범위에 계산목적이 없습니다.');
assertContract(str_contains($model, 'next_revision.id IS NULL'), '현재 leaf Revision 판정이 없습니다.');
assertContract(str_contains($down, '회사규모 기간 데이터가 있어 Down할 수 없습니다.'), '데이터 존재 시 Down 차단이 없습니다.');

$resolver = new WorkplaceSizeRateResolver();
$matrix = ['additional_employer_rates' => [['business_size_code'=>'LESS_THAN_150','business_size_name'=>'예전 표시명','employer_rate'=>0.0025]]];
$period = ['business_size_code'=>'LESS_THAN_150','business_size_name_snapshot'=>'150명 미만'];
assertContract(abs($resolver->resolveAdditionalEmployerRate($period, $matrix)-0.0025)<0.0000001, '코드 Matrix 0.25% 선택 실패');
$period['business_size_name_snapshot'] = '표시명 변경';
assertContract(abs($resolver->resolveAdditionalEmployerRate($period, $matrix)-0.0025)<0.0000001, '표시명 변경이 Resolver에 영향을 줍니다.');
$basis=988000; $employeePremium=floor(($basis*0.0065)/10)*10; $vocationalPremium=floor(($basis*0.0025)/10)*10;
assertContract($employeePremium===6420.0, '박한호 고용보험 사용자부담 예상값 불일치');
assertContract($vocationalPremium===2470.0, '박한호 직업능력개발 부담 예상값 불일치');
assertContract(150960+$employeePremium+$vocationalPremium===159850.0, '사용자부담 예상합계 불일치');
assertContract(!str_contains($up, 'WORKERS_COMPENSATION')&&!str_contains($up, 'INDUSTRIAL_ACCIDENT'), '산재보험 급여 Line 계약이 포함되었습니다.');
echo "workplace_size_period_ssot_contract: OK\n";
