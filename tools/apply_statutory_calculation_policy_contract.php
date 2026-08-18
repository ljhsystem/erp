<?php

declare(strict_types=1);

use App\Models\System\StatutoryStandardModel;
use App\Models\System\StatutoryStandardSourceModel;
use App\Services\System\StatutoryStandardTemplateService;
use Core\DbPdo;
use Core\Helpers\ActorHelper;
use Core\Helpers\SequenceHelper;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$direction = $argv[1] ?? 'verify';
if (!in_array($direction, ['up', 'verify'], true)) {
    fwrite(STDERR, "사용법: php tools/apply_statutory_calculation_policy_contract.php [up|verify]\n");
    exit(1);
}

$db = DbPdo::conn();
$actor = ActorHelper::system('STATUTORY_POLICY_SYNC');
$model = new StatutoryStandardModel($db);
$sourceModel = new StatutoryStandardSourceModel($db);

$select = static fn(string $code, string $name, array $options, bool $required = true): array => [
    'code' => $code, 'name' => $name, 'type' => 'select', 'required' => $required,
    'options' => array_map(static fn(string $value, string $label): array => ['value' => $value, 'label' => $label], array_keys($options), $options),
];
$number = static fn(string $code, string $name, bool $required = true, ?string $unit = null): array => array_filter([
    'code' => $code, 'name' => $name, 'type' => 'number', 'required' => $required, 'min' => 0, 'unit_label' => $unit,
], static fn(mixed $value): bool => $value !== null);
$amount = static fn(string $code, string $name, bool $required = true): array => [
    'code' => $code, 'name' => $name, 'type' => 'amount', 'required' => $required,
];
$rounding = static fn(bool $required = true): array => [
    'code' => 'method', 'name' => '계산 처리방법', 'type' => 'rounding', 'required' => $required,
];
$stageOptions = [
    'ASSESSMENT_BASE' => '산정기초 확정 단계',
    'AFTER_TAX_CREDIT' => '세액공제 적용 후',
    'AFTER_TABLE_OR_EXCESS_CALCULATION' => '표 조회 또는 상한초과 계산 후',
    'AFTER_NATIONAL_WITHHOLDING_TAX' => '국세 원천징수액 확정 후',
    'AFTER_HEALTH_INSURANCE_PREMIUM' => '건강보험료 확정 후',
];
$aggregationOptions = [
    'INSURED_PERSON_MONTH' => '가입자별 월',
    'WITHHOLDING_AGENT_RECIPIENT_PAYMENT_MONTH' => '원천징수의무자·소득자·지급월',
    'WITHHOLDING_AGENT_RECIPIENT_WORKDAY_PAYMENT' => '원천징수의무자·소득자·근로일별 지급',
];
$comparisonOptions = ['LESS_THAN' => '미만'];
$workplaceOptions = ['EACH_WORKPLACE' => '사업장별 독립 판정'];
$baseOptions = [
    'REPORTED_MONTHLY_INCOME' => '신고 소득월액',
    'TABLE_LOOKUP_OR_EXCESS_RULE_RESULT' => '간이세액표 조회액 또는 상한초과 계산액',
    'DAILY_TAX_AFTER_CREDIT' => '근로소득세액공제 후 일용근로소득세',
    'HEALTH_INSURANCE_PREMIUM' => '확정 건강보험료',
    'NATIONAL_WITHHOLDING_TAX' => '확정 국세 원천징수액',
];

$policies = [
    'NATIONAL_PENSION' => [
        $rounding(), $number('discard_below_unit', '버림 기준단위', true, '원'),
        $select('stage', '적용단계', $stageOptions), $select('base_value_code', '계산기초', $baseOptions),
        $select('aggregation_unit', '집계단위', $aggregationOptions), $number('application_order', '적용순서'),
    ],
    'DAILY_WORKER_INCOME_TAX' => [
        $rounding(), $number('discard_below_unit', '버림 기준단위', true, '원'),
        $select('stage', '적용단계', $stageOptions), $select('base_value_code', '계산기초', $baseOptions),
        $select('aggregation_unit', '집계단위', $aggregationOptions), $amount('threshold', '소액부징수 기준금액'),
        $select('threshold_comparison', '소액부징수 비교조건', $comparisonOptions),
        $select('workplace_scope', '사업장 판정범위', $workplaceOptions), $number('application_order', '적용순서'),
    ],
    'EMPLOYMENT_INCOME_TAX_TABLE' => [
        $select('stage', '적용단계', $stageOptions, false), $select('base_value_code', '판정 대상 세액', $baseOptions, false),
        $select('aggregation_unit', '집계단위', $aggregationOptions, false), $amount('threshold', '소액부징수 기준금액', false),
        $select('threshold_comparison', '소액부징수 비교조건', $comparisonOptions, false), $number('application_order', '적용순서', false),
    ],
    'LONG_TERM_CARE' => [
        $select('stage', '적용단계', $stageOptions), $select('base_value_code', '계산기초', $baseOptions),
        $select('aggregation_unit', '집계단위', $aggregationOptions), $number('application_order', '적용순서'),
    ],
    'LOCAL_INCOME_TAX_WITHHOLDING' => [
        $rounding(), $number('discard_below_unit', '버림 기준단위', true, '원'),
        $select('stage', '적용단계', $stageOptions), $select('base_value_code', '계산기초', $baseOptions),
        $select('aggregation_unit', '집계단위', $aggregationOptions), $number('application_order', '적용순서'),
    ],
];

$official = [
    'MINIMUM_WAGE' => ['최저임금위원회 연도별 최저임금 결정현황', '최저임금위원회', '최저임금법', 'https://www.minimumwage.go.kr/minWage/policy/decisionMain.do'],
    'NATIONAL_PENSION' => ['기준소득월액 상한액과 하한액', '국민연금공단', '국민연금법 시행령 제5조', 'https://www.nps.or.kr/pnsinfo/ntpsklg/getOHAF0038M0.do?menuId=MN24001113&tab=tab5'],
    'HEALTH_INSURANCE' => ['연도별 직장가입자 보험료율', '국민건강보험공단', '국민건강보험법 시행령', 'https://www.nhis.or.kr/english/wbheaa02500m01.do'],
    'LONG_TERM_CARE' => ['노인장기요양보험법 시행령 제4조', '국민건강보험공단', '노인장기요양보험법 시행령 제4조', 'https://www.nhis.or.kr/lm/lmxsrv/law/lawFullContent.do?SEQ=31'],
    'DAILY_WORKER_INCOME_TAX' => ['일용근로자 원천징수 세액 계산', '국세청', '소득세법 제47조·제59조의3·제86조', 'https://www.nts.go.kr/nts/cm/cntnts/cntntsView.do?cntntsId=7863&mi=6584'],
    'EMPLOYMENT_INCOME_TAX_TABLE' => ['근로소득 간이세액표', '국세청', '소득세법 시행령 제194조·소득세법 제86조', 'https://www.nts.go.kr/nts/cm/cntnts/cntntsView.do?cntntsId=7871&mi=6585'],
    'LOCAL_INCOME_TAX_WITHHOLDING' => ['지방소득세 특별징수', '국세청', '지방세법 제103조의13', 'https://www.nts.go.kr/nts/cm/cntnts/cntntsView.do?cntntsId=7701&mi=2413'],
    'CORPORATE_TAX' => ['법인세 세율', '국세청', '법인세법 제55조', 'https://in.nts.go.kr/nts/cm/cntnts/cntntsView.do?cntntsId=7746&mi=2372'],
    'CORPORATE_LOCAL_INCOME_TAX' => ['법인지방소득세 표준세율', '국가법령정보센터', '지방세법 제103조의20', 'https://www.law.go.kr/법령/지방세법/제103조의20'],
];

$minimumWages = [2013=>4860,2014=>5210,2015=>5580,2016=>6030,2017=>6470,2018=>7530,2019=>8350,2020=>8590,2021=>8720,2022=>9160,2023=>9620,2024=>9860,2025=>10030,2026=>10320];
$pensionLimits = [
    ['2013-01-01','2013-06-30',240000,3890000],['2013-07-01','2014-06-30',250000,3980000],
    ['2014-07-01','2015-06-30',260000,4080000],['2015-07-01','2016-06-30',270000,4210000],
    ['2016-07-01','2017-06-30',280000,4340000],['2017-07-01','2018-06-30',290000,4490000],
    ['2018-07-01','2019-06-30',300000,4680000],['2019-07-01','2020-06-30',310000,4860000],
    ['2020-07-01','2021-06-30',320000,5030000],['2021-07-01','2022-06-30',330000,5240000],
    ['2022-07-01','2023-06-30',350000,5530000],['2023-07-01','2024-06-30',370000,5900000],
    ['2024-07-01','2025-06-30',390000,6170000],['2025-07-01','2025-12-31',400000,6370000],
    ['2026-01-01','2026-06-30',400000,6370000],['2026-07-01','2027-06-30',410000,6590000],
];
$healthRates = [2013=>.0589,2014=>.0599,2015=>.0607,2016=>.0612,2017=>.0612,2018=>.0624,2019=>.0646,2020=>.0667,2021=>.0686,2022=>.0699,2023=>.0709,2024=>.0709,2025=>.0709,2026=>.0719];
$careRates = [2010=>.0655,2018=>.0738,2019=>.0851,2020=>.1025,2021=>.1152,2022=>.1227,2023=>.1281,2024=>.1295,2025=>.1295,2026=>.1314];

$brackets = static fn(array $rates): array => array_map(static fn(array $row): array => [
    'tax_base_from'=>$row[0],'tax_base_to'=>$row[1],'tax_rate'=>$row[2],'progressive_deduction'=>$row[3],
], $rates);
$corporate = [
    ['2013-01-01','2017-12-31',[[0,200000000,.10,0],[200000000,20000000000,.20,20000000],[20000000000,null,.22,420000000]]],
    ['2018-01-01','2022-12-31',[[0,200000000,.10,0],[200000000,20000000000,.20,20000000],[20000000000,300000000000,.22,420000000],[300000000000,null,.25,9420000000]]],
    ['2023-01-01','2025-12-31',[[0,200000000,.09,0],[200000000,20000000000,.19,20000000],[20000000000,300000000000,.21,420000000],[300000000000,null,.24,9420000000]]],
    ['2026-01-01',null,[[0,200000000,.10,0],[200000000,20000000000,.20,20000000],[20000000000,300000000000,.22,420000000],[300000000000,null,.25,9420000000]]],
];
$corporateLocal = [
    ['2014-01-01','2017-12-31',[[0,200000000,.01,0],[200000000,20000000000,.02,2000000],[20000000000,null,.022,42000000]]],
    ['2018-01-01','2022-12-31',[[0,200000000,.01,0],[200000000,20000000000,.02,2000000],[20000000000,300000000000,.022,42000000],[300000000000,null,.025,942000000]]],
    ['2023-01-01','2025-12-31',[[0,200000000,.009,0],[200000000,20000000000,.019,2000000],[20000000000,300000000000,.021,42000000],[300000000000,null,.024,942000000]]],
    ['2026-01-01',null,[[0,200000000,.01,0],[200000000,20000000000,.02,2000000],[20000000000,300000000000,.022,42000000],[300000000000,null,.025,942000000]]],
];

$source = static function (string $type) use ($official): array {
    [$name,$organization,$law,$url] = $official[$type];
    return [['source_name'=>$name,'organization_name'=>$organization,'law_name'=>$law,'source_url'=>$url,
        'note'=>'공식 계산계약 및 적용기간 확인']];
};
$schema = static function (array $template): array {
    return ['version'=>2,'fields'=>$template['fields'] ?? [],'calculation_policy'=>['fields'=>$template['calculation_policy']['fields'] ?? []]];
};
$upsert = static function (string $type, string $from, ?string $to, array $value, string $note, array $sources) use ($db,$model,$sourceModel,$actor): string {
    $stmt=$db->prepare('SELECT id FROM system_statutory_standards WHERE standard_type_code=:type AND effective_from=:from ORDER BY id LIMIT 1 FOR UPDATE');
    $stmt->execute([':type'=>$type,':from'=>$from]);
    $id=(string)($stmt->fetchColumn() ?: '');
    $data=['standard_type_code'=>$type,'effective_from'=>$from,'effective_to'=>$to,
        'value_data'=>json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'note'=>$note,
        'updated_at'=>date('Y-m-d H:i:s'),'updated_by'=>$actor];
    if ($id==='') {
        $id=$model->create($data+['sort_no'=>SequenceHelper::next('system_statutory_standards','sort_no'),
            'created_at'=>date('Y-m-d H:i:s'),'created_by'=>$actor]);
    } else $model->update($id,$data);
    $sourceModel->replace($id,$sources,$actor);
    return $id;
};

if ($direction === 'up') {
    $db->beginTransaction();
    try {
        foreach ($policies as $type=>$fields) {
            $stmt=$db->prepare("SELECT extra_data FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE' AND code=:code FOR UPDATE");
            $stmt->execute([':code'=>$type]);
            $extra=json_decode((string)$stmt->fetchColumn(),true,512,JSON_THROW_ON_ERROR);
            $extra['calculation_policy']=['fields'=>$fields];
            $extra['preserve_schema_in_value']=true;
            $stmt=$db->prepare("UPDATE system_codes SET extra_data=:extra,updated_at=NOW(),updated_by=:actor WHERE code_group='STATUTORY_STANDARD_TYPE' AND code=:code");
            $stmt->execute([':extra'=>json_encode($extra,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),':actor'=>$actor,':code'=>$type]);
        }
        $templates=array_column((new StatutoryStandardTemplateService($db))->all(),null,'code');
        foreach ($minimumWages as $year=>$wage) $upsert('MINIMUM_WAGE',"$year-01-01","$year-12-31",['hourly_wage'=>$wage],"{$year}년 적용 최저임금",$source('MINIMUM_WAGE'));
        foreach ($pensionLimits as [$from,$to,$min,$max]) {
            $rate=$from>='2026-01-01'?.0475:.045;
            $value=['employee_rate'=>$rate,'employer_rate'=>$rate,'minimum_base_amount'=>$min,'maximum_base_amount'=>$max,
                'calculation_policy'=>['method'=>'TRUNCATE','discard_below_unit'=>1000,'stage'=>'ASSESSMENT_BASE','base_value_code'=>'REPORTED_MONTHLY_INCOME','aggregation_unit'=>'INSURED_PERSON_MONTH','application_order'=>1]];
            $value['_schema']=$schema($templates['NATIONAL_PENSION']);
            $upsert('NATIONAL_PENSION',$from,$to,$value,'국민연금 보험료율 및 기준소득월액 상·하한',$source('NATIONAL_PENSION'));
        }
        foreach ($healthRates as $year=>$rate) $upsert('HEALTH_INSURANCE',"$year-01-01","$year-12-31",['employee_rate'=>$rate/2,'employer_rate'=>$rate/2,'minimum_base_amount'=>'','maximum_base_amount'=>''],"{$year}년 직장가입자 건강보험료율",$source('HEALTH_INSURANCE'));
        foreach ($careRates as $year=>$rate) {
            $to=$year===2010?'2017-12-31':"$year-12-31";
            $value=['rate_value'=>$rate,'calculation_policy'=>['stage'=>'AFTER_HEALTH_INSURANCE_PREMIUM','base_value_code'=>'HEALTH_INSURANCE_PREMIUM','aggregation_unit'=>'INSURED_PERSON_MONTH','application_order'=>1]];
            $value['_schema']=$schema($templates['LONG_TERM_CARE']);
            $upsert('LONG_TERM_CARE',"$year-01-01",$to,$value,'건강보험료에 연동하는 장기요양보험료율',$source('LONG_TERM_CARE'));
        }
        foreach ([['2013-01-01','2018-12-31',100000],['2019-01-01',null,150000]] as [$from,$to,$deduction]) {
            $value=['daily_income_deduction'=>$deduction,'daily_income_tax_rate'=>.06,'daily_income_tax_credit_rate'=>.55,
                'calculation_policy'=>['method'=>'TRUNCATE','discard_below_unit'=>1,'stage'=>'AFTER_TAX_CREDIT','base_value_code'=>'DAILY_TAX_AFTER_CREDIT','aggregation_unit'=>'WITHHOLDING_AGENT_RECIPIENT_WORKDAY_PAYMENT','threshold'=>1000,'threshold_comparison'=>'LESS_THAN','workplace_scope'=>'EACH_WORKPLACE','application_order'=>1]];
            $value['_schema']=$schema($templates['DAILY_WORKER_INCOME_TAX']);
            $upsert('DAILY_WORKER_INCOME_TAX',$from,$to,$value,'일용근로소득 원천징수 공식 계산계약',$source('DAILY_WORKER_INCOME_TAX'));
        }
        $stmt=$db->query("SELECT id,value_data FROM system_statutory_standards WHERE standard_type_code='EMPLOYMENT_INCOME_TAX_TABLE' FOR UPDATE");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $value=json_decode((string)$row['value_data'],true,512,JSON_THROW_ON_ERROR);
            $value['calculation_policy']=['stage'=>'AFTER_TABLE_OR_EXCESS_CALCULATION','base_value_code'=>'TABLE_LOOKUP_OR_EXCESS_RULE_RESULT','aggregation_unit'=>'WITHHOLDING_AGENT_RECIPIENT_PAYMENT_MONTH','threshold'=>1000,'threshold_comparison'=>'LESS_THAN','application_order'=>1];
            $value['_schema']=$schema($templates['EMPLOYMENT_INCOME_TAX_TABLE']);
            $model->update((string)$row['id'],['value_data'=>json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'updated_at'=>date('Y-m-d H:i:s'),'updated_by'=>$actor]);
            $sourceModel->replace((string)$row['id'],$source('EMPLOYMENT_INCOME_TAX_TABLE'),$actor);
        }
        foreach ([['2013-01-01','2013-12-31','지방소득세 소득분 특별징수'],['2014-01-01',null,'개인지방소득세 특별징수']] as [$from,$to,$note]) {
            $value=['rate_value'=>.1,'calculation_policy'=>['method'=>'TRUNCATE','discard_below_unit'=>10,'stage'=>'AFTER_NATIONAL_WITHHOLDING_TAX','base_value_code'=>'NATIONAL_WITHHOLDING_TAX','aggregation_unit'=>'WITHHOLDING_AGENT_RECIPIENT_PAYMENT_MONTH','application_order'=>1]];
            $value['_schema']=$schema($templates['LOCAL_INCOME_TAX_WITHHOLDING']);
            $upsert('LOCAL_INCOME_TAX_WITHHOLDING',$from,$to,$value,$note,$source('LOCAL_INCOME_TAX_WITHHOLDING'));
        }
        foreach ($corporate as [$from,$to,$rows]) { $value=['tax_brackets'=>$brackets($rows)]; $value['_schema']=$schema($templates['CORPORATE_TAX']); $upsert('CORPORATE_TAX',$from,$to,$value,'내국법인 각 사업연도 소득 표준세율',$source('CORPORATE_TAX')); }
        $db->exec("DELETE FROM system_statutory_standards WHERE standard_type_code='CORPORATE_LOCAL_INCOME_TAX' AND effective_from<'2014-01-01'");
        foreach ($corporateLocal as [$from,$to,$rows]) { $value=['tax_brackets'=>$brackets($rows)]; $value['_schema']=$schema($templates['CORPORATE_LOCAL_INCOME_TAX']); $upsert('CORPORATE_LOCAL_INCOME_TAX',$from,$to,$value,'법인지방소득세 독립세 표준세율',$source('CORPORATE_LOCAL_INCOME_TAX')); }
        $cleanSources=$db->prepare("UPDATE system_statutory_standard_sources SET source_url=REPLACE(REPLACE(REPLACE(source_url,'?utm_source=chatgpt.com',''),'&utm_source=chatgpt.com',''),'utm_source=chatgpt.com&',''),updated_at=NOW(),updated_by=:actor WHERE source_url LIKE '%utm_source=chatgpt.com%'");
        $cleanSources->execute([':actor'=>$actor]);
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) $db->rollBack();
        throw $exception;
    }
}

$counts=$db->query('SELECT standard_type_code,COUNT(*) AS rows_count,MIN(effective_from) AS first_date,MAX(COALESCE(effective_to,\'9999-12-31\')) AS last_date FROM system_statutory_standards GROUP BY standard_type_code ORDER BY standard_type_code')->fetchAll(PDO::FETCH_ASSOC) ?: [];
$policyTypes=[];
foreach ((new StatutoryStandardTemplateService($db))->all() as $template) if (($template['calculation_policy']['fields'] ?? []) !== []) $policyTypes[]=$template['code'];
echo json_encode(['direction'=>$direction,'policy_types'=>$policyTypes,'periods'=>$counts],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),PHP_EOL;
