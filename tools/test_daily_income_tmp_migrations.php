<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Storage.php';
require PROJECT_ROOT . '/core/Database.php';

use App\Services\Institution\DailyEmploymentIncomeService;
use App\Services\Institution\DailyEmploymentIncomeCalculationResultService;
use App\Services\Institution\DailyEmploymentIncomeCalculationSourceService;
use Core\Security\Crypto;
use Core\Session;

$token = bin2hex(random_bytes(6));
$schema = 'tmp_daily_employment_income_' . $token;
$protected = ['sukhyang','SukHyang','Savewohyang','UserManagement','information_schema','mysql','performance_schema','sys'];
$created = false;
$exitCode = 1;
$cleanupDone = false;
$cleanupAttempts = 0;
$cleanupError = null;
$db = null;
$startedAt = date(DATE_ATOM);

$load = static function (string $path): ?array {
    if (!is_file($path)) return null;
    $value = require $path;
    return is_array($value) ? $value : null;
};
$topology = $load(PROJECT_ROOT . '/../secure-config/db_replication.php');
$legacy = $load(PROJECT_ROOT . '/../secure-config/db_config.php');
$target = strtolower((string)($topology['active_target'] ?? ''));
$node = is_array($topology[$target] ?? null) ? $topology[$target] : $legacy;
if (!is_array($node)) throw new RuntimeException('활성 MariaDB 연결설정을 찾을 수 없습니다.');
$config = [
    'host'=>(string)($node['host']??''), 'port'=>(int)($node['port']??3306),
    'user'=>(string)($node['user']??''), 'pass'=>(string)($node['pass']??''),
    'dbname'=>(string)($node['dbname']??$topology['dbname']??$legacy['dbname']??''),
];
if (in_array($config['dbname'], $protected, true) === false && str_starts_with(strtolower($config['dbname']), 'tmp_')) {
    throw new RuntimeException('활성 연결이 기존 tmp Schema를 가리켜 중단합니다.');
}
$server = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4',$config['host'],$config['port']),$config['user'],$config['pass'],[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,
]);
$version = (string)$server->query('SELECT VERSION()')->fetchColumn();
$schemasBefore = $server->query('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA ORDER BY SCHEMA_NAME')->fetchAll(PDO::FETCH_COLUMN);
$tmpBefore = array_values(array_filter($schemasBefore, static fn(string $name): bool => str_starts_with($name, 'tmp_')));
Session::start(30);
$_SESSION['user']=['id'=>'fixture-actor'];
$_SESSION['auth_state']=['user_id'=>'fixture-actor','status'=>'NORMAL'];
echo json_encode(['host'=>$config['host'],'port'=>$config['port'],'version'=>$version,'schema'=>$schema,
    'protected_schemas'=>$protected,'selected_database'=>$server->query('SELECT DATABASE()')->fetchColumn(),'test_mode'=>true,
    'started_at'=>$startedAt,'unique_token'=>$token,'schema_count_before'=>count($schemasBefore),
    'tmp_schema_count_before'=>count($tmpBefore),'schemas_before'=>$schemasBefore,'tmp_schemas_before'=>$tmpBefore],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),PHP_EOL;

$guard = static function (PDO $db, string $expected) use ($protected): void {
    $current=$db->query('SELECT DATABASE()')->fetchColumn();
    if (!is_string($current) || $current !== $expected || !str_starts_with($current,'tmp_daily_employment_income_') || in_array($current,$protected,true)) {
        throw new RuntimeException('쓰기 Guard가 현재 Schema 불일치를 차단했습니다.');
    }
};
$executeSql = static function (PDO $db, string $path): void {
    $sql=(string)file_get_contents($path);$delimiter=';';$buffer='';
    foreach(preg_split('/\r\n|\n|\r/',$sql)?:[] as $line){
        if(preg_match('/^DELIMITER\s+(.+)$/i',trim($line),$match)){$delimiter=$match[1];continue;}
        $buffer.=$line."\n";$trimmed=rtrim($buffer);
        if(!str_ends_with($trimmed,$delimiter))continue;
        $statement=trim(substr($trimmed,0,-strlen($delimiter)));if($statement!=='')$db->exec($statement);$buffer='';
    }
    if(trim($buffer)!=='')throw new RuntimeException('종결되지 않은 Migration SQL입니다: '.basename($path));
};
$dropFixtureTables = static function (PDO $db, string $expected) use ($guard): void {
    $guard($db, $expected);
    $tables=$db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME<>'_test_schema_ownership'")->fetchAll(PDO::FETCH_COLUMN);
    $db->exec('SET FOREIGN_KEY_CHECKS=0');
    try { foreach($tables as $table){$db->exec('DROP TABLE `'.str_replace('`','``',(string)$table).'`');} }
    finally { $db->exec('SET FOREIGN_KEY_CHECKS=1'); }
};
$schemaInventory = static function (PDO $db): array {
    $inventory = [];
    $queries = [
        'tables' => "SELECT TABLE_NAME,TABLE_TYPE,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME<>'_test_schema_ownership' ORDER BY TABLE_NAME",
        'columns' => "SELECT TABLE_NAME,COLUMN_NAME,ORDINAL_POSITION,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME<>'_test_schema_ownership' ORDER BY TABLE_NAME,ORDINAL_POSITION",
        'indexes' => "SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME,INDEX_TYPE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME<>'_test_schema_ownership' ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX",
        'routines' => "SELECT ROUTINE_NAME,ROUTINE_TYPE FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() ORDER BY ROUTINE_NAME,ROUTINE_TYPE",
        'views' => "SELECT TABLE_NAME,VIEW_DEFINITION FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME",
    ];
    foreach ($queries as $key => $sql) $inventory[$key] = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return $inventory;
};
$insuranceProjection = static function (array $item): array {
    $projection = [];
    foreach ((array)($item['lines'] ?? []) as $line) {
        $code = (string)($line['line_code'] ?? '');
        if (!in_array($code, ['NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE'], true)) continue;
        $type = (string)($line['line_type_code'] ?? '');
        $projection[$code][$type] = [
            'eligibility' => $line['eligibility_result']['status'] ?? null,
            'application' => $line['application_status_code'] ?? null,
            'calculated' => $line['calculated_amount'] ?? null,
            'final' => $line['final_amount'] ?? null,
            'eligibility_revision_id' => $line['eligibility_result']['eligibility_revision_id'] ?? null,
        ];
    }
    return ['insurance'=>$projection,'summary'=>$item['summary'] ?? []];
};
$assertExpectedProjection = static function (array $projection, string $path): void {
    foreach (['NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE'] as $code) {
        foreach (['DEDUCTION','EMPLOYER_BURDEN'] as $type) {
            $line = $projection['insurance'][$code][$type] ?? null;
            if (!is_array($line) || ($line['eligibility'] ?? null) !== 'NOT_ELIGIBLE'
                || ($line['application'] ?? null) !== 'EXCLUDED' || (float)($line['final'] ?? -1) !== 0.0
                || trim((string)($line['eligibility_revision_id'] ?? '')) === '') {
                throw new RuntimeException($path . ' ' . $code . ' ' . $type . ' result mismatch');
            }
        }
    }
    $employment = $projection['insurance']['EMPLOYMENT_INSURANCE']['DEDUCTION'] ?? null;
    $summary = $projection['summary'];
    if (!is_array($employment) || (float)($employment['final'] ?? -1) !== 2940.0
        || (float)($summary['total_gross_amount'] ?? -1) !== 452940.0
        || (float)($summary['total_deduction_amount'] ?? -1) !== 2940.0
        || (float)($summary['total_net_payment_amount'] ?? -1) !== 450000.0) {
        throw new RuntimeException($path . ' totals mismatch');
    }
};
$seedReconcileLines = static function (PDO $db, string $expected) use ($guard): array {
    $guard($db, $expected);
    $db->exec("CREATE TABLE institution_daily_employment_incomes(id VARCHAR(36) NOT NULL PRIMARY KEY) ENGINE=InnoDB");
    $db->exec("CREATE TABLE institution_daily_employment_income_items(id VARCHAR(36) NOT NULL PRIMARY KEY) ENGINE=InnoDB");
    $db->exec("CREATE TABLE institution_daily_employment_income_workdays(id VARCHAR(36) NOT NULL PRIMARY KEY) ENGINE=InnoDB");
    $db->exec("CREATE TABLE institution_daily_employment_income_lines(
        id VARCHAR(36) NOT NULL PRIMARY KEY,daily_employment_income_item_id VARCHAR(36) NOT NULL,
        daily_employment_income_workday_id VARCHAR(36) NULL,workday_scope_key VARCHAR(36) NOT NULL,
        line_type_code VARCHAR(30) NOT NULL,line_code VARCHAR(100) NOT NULL,statutory_standard_id CHAR(36) NULL,
        application_status_code VARCHAR(30) NULL,calculation_basis_amount DECIMAL(18,2) NULL,
        calculation_rate DECIMAL(18,8) NULL,calculation_before_rounding DECIMAL(18,2) NULL,
        calculated_amount DECIMAL(18,2) NULL,final_amount DECIMAL(18,2) NULL,updated_at DATETIME NULL,
        UNIQUE KEY uq_daily_income_line_scope(daily_employment_income_item_id,workday_scope_key,line_type_code,line_code)
    ) ENGINE=InnoDB");
    $codes=[['PAY','BASE_PAY'],['PAY','TAXABLE_ADDITIONAL_PAY'],['PAY','NON_TAXABLE_ADDITIONAL_PAY'],['PAY','PAY_ADJUSTMENT'],['DEDUCTION','DAILY_WORKER_INCOME_TAX'],['DEDUCTION','LOCAL_INCOME_TAX'],['DEDUCTION','NATIONAL_PENSION'],['DEDUCTION','HEALTH_INSURANCE'],['DEDUCTION','LONG_TERM_CARE'],['DEDUCTION','EMPLOYMENT_INSURANCE'],['EMPLOYER_BURDEN','EMPLOYMENT_INSURANCE'],['EMPLOYER_BURDEN','EMPLOYMENT_INSURANCE_VOCATIONAL'],['EMPLOYER_BURDEN','INDUSTRIAL_ACCIDENT_INSURANCE']];
    $insert=$db->prepare('INSERT INTO institution_daily_employment_income_lines(id,daily_employment_income_item_id,daily_employment_income_workday_id,workday_scope_key,line_type_code,line_code,application_status_code,calculation_basis_amount,calculation_rate,calculation_before_rounding,calculated_amount,final_amount,updated_at) VALUES(:id,:item,NULL,:scope,:type,:code,:status,0,0,0,0,:final,NOW())');
    for($index=0;$index<37;$index++){
        [$type,$code]=$codes[$index%count($codes)];
        $status=in_array($index,[6,7,8],true)?null:'APPLICABLE';
        $final=$code==='NON_TAXABLE_ADDITIONAL_PAY'?0:($index+1)*100;
        $insert->execute(['id'=>sprintf('fixture-line-%023d',$index+1),'item'=>sprintf('fixture-item-%023d',$index+1),'scope'=>'ITEM','type'=>$type,'code'=>$code,'status'=>$status,'final'=>$final]);
    }
    $rows=$db->query("SELECT id,daily_employment_income_item_id,daily_employment_income_workday_id,workday_scope_key,line_type_code,line_code,application_status_code,calculation_basis_amount,calculation_rate,calculation_before_rounding,calculated_amount,final_amount FROM institution_daily_employment_income_lines ORDER BY id")->fetchAll();
    return ['count'=>count($rows),'hash'=>hash('sha256',json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION))];
};

$cleanup = null;
$cleanup = static function () use (&$cleanup,&$cleanupDone,&$cleanupAttempts,&$cleanupError,&$created,&$db,$server,$schema,$token,$protected,$schemasBefore): bool {
    if ($cleanupDone || !$created) return true;
    $cleanupAttempts++;
    try {
        if (!preg_match('/^tmp_daily_employment_income_'.preg_quote($token,'/').'$/',$schema)
            || in_array($schema,$protected,true) || in_array($schema,$schemasBefore,true)) {
            throw new RuntimeException('Cleanup Schema 소유권 Guard 실패');
        }
        if ($db instanceof PDO) {
            if ($db->inTransaction()) $db->rollBack();
            $marker=$db->query("SELECT test_tool_code,unique_token,cleanup_required,source_script FROM _test_schema_ownership LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if (($marker['test_tool_code']??'')!=='DAILY_EMPLOYMENT_INCOME_RUNTIME'
                || ($marker['unique_token']??'')!==$token || (int)($marker['cleanup_required']??0)!==1
                || ($marker['source_script']??'')!==basename(__FILE__)) {
                throw new RuntimeException('Cleanup Marker 소유권이 일치하지 않습니다.');
            }
        }
        $db=null;
        $server->exec('DROP DATABASE `'.$schema.'`');
        $check=$server->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=:schema');
        $check->execute(['schema'=>$schema]);
        if((int)$check->fetchColumn()!==0)throw new RuntimeException('DROP 후 tmp Schema가 남아 있습니다.');
        $cleanupDone=true;
        return true;
    } catch (Throwable $exception) {
        $cleanupError=['class'=>$exception::class,'sqlstate'=>$exception instanceof PDOException?$exception->getCode():null,'message'=>$exception->getMessage()];
        if($cleanupAttempts<2){$db=null;usleep(250000);return $cleanup();}
        return false;
    }
};
register_shutdown_function(static function () use (&$cleanup,$schema,&$cleanupDone): void {
    if(!$cleanupDone){$ok=$cleanup();if(!$ok)error_log('[DAILY_TMP_CLEANUP_FAILED] '.$schema);}
});

try {
    if (!preg_match('/^tmp_daily_employment_income_[a-f0-9]{12}$/',$schema)) throw new RuntimeException('테스트 Schema 이름 Guard 실패');
    $server->exec('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');$created=true;
    $db=new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',$config['host'],$config['port'],$schema),$config['user'],$config['pass'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    $guard($db,$schema);
    $db->exec("CREATE TABLE _test_schema_ownership(test_tool_code VARCHAR(100) NOT NULL,unique_token VARCHAR(64) NOT NULL,created_at DATETIME NOT NULL,process_id INT NOT NULL,purpose VARCHAR(200) NOT NULL,cleanup_required TINYINT(1) NOT NULL,source_script VARCHAR(255) NOT NULL) ENGINE=InnoDB");
    $marker=$db->prepare('INSERT INTO _test_schema_ownership VALUES(:tool,:token,NOW(),:pid,:purpose,1,:script)');
    $marker->execute(['tool'=>'DAILY_EMPLOYMENT_INCOME_RUNTIME','token'=>$token,'pid'=>getmypid(),'purpose'=>'일용근로소득 Migration 및 Runtime 검증','script'=>basename(__FILE__)]);

    $executeSql($db,PROJECT_ROOT.'/tests/fixtures/daily_insurance_tmp_bootstrap.sql');
    $operatingBefore=$seedReconcileLines($db,$schema);
    if($operatingBefore['count']!==37)throw new RuntimeException('운영 Baseline Line 37행 Fixture 계약 불일치');
    $executeSql($db,PROJECT_ROOT.'/app/migrations/20260829_00_reconcile_daily_income_non_taxable_line_schema.up.sql');
    $operatingAfterRows=$db->query("SELECT id,daily_employment_income_item_id,daily_employment_income_workday_id,workday_scope_key,line_type_code,line_code,application_status_code,calculation_basis_amount,calculation_rate,calculation_before_rounding,calculated_amount,final_amount FROM institution_daily_employment_income_lines ORDER BY id")->fetchAll();
    $operatingAfter=['count'=>count($operatingAfterRows),'hash'=>hash('sha256',json_encode($operatingAfterRows,JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION))];
    $auditCount=(int)$db->query("SELECT COUNT(*) FROM institution_daily_employment_income_line_backfill_audits WHERE verification_status_code='VERIFIED'")->fetchColumn();
    $duplicateCount=(int)$db->query('SELECT COUNT(*) FROM (SELECT 1 FROM institution_daily_employment_income_lines GROUP BY daily_employment_income_item_id,workday_scope_key,line_type_code,line_code,revision_scope_key,period_scope_key HAVING COUNT(*)>1) duplicate_rows')->fetchColumn();
    if($operatingAfter!==$operatingBefore||$auditCount!==37||$duplicateCount!==0)throw new RuntimeException('운영 37행 호환 Migration 불변조건 실패');
    $executeSql($db,PROJECT_ROOT.'/app/migrations/20260829_00_reconcile_daily_income_non_taxable_line_schema.down.sql');
    echo json_encode(['operating_reconcile'=>'PASS','line_count'=>37,'invariant_hash'=>$operatingBefore['hash'],'audit_verified'=>$auditCount,'duplicate_count'=>$duplicateCount,'down'=>'PASS'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),PHP_EOL;
    $dropFixtureTables($db,$schema);

    $executeSql($db,PROJECT_ROOT.'/tests/fixtures/daily_insurance_tmp_bootstrap.sql');
    $cleanBaselineInventory = $schemaInventory($db);
    $migrations=[
        '20260827_02_enable_daily_employment_income_with_company_ssot','20260827_03_add_daily_employment_income_sort_no',
        '20260827_04_create_daily_employment_income_commands','20260827_05_add_daily_employment_income_trash',
        '20260827_06_add_daily_employment_income_counts','20260827_08_add_daily_income_business_unit_chain',
        '20260827_09_align_daily_income_group_ssot','20260827_10_create_daily_employment_income_groups',
        '20260827_11_remove_daily_employment_income_group_default_rate','20260827_12_move_daily_income_work_description_to_item',
        '20260827_14_restore_daily_income_group_description','20260827_15_create_daily_income_calculation_results_allocations',
        '20260827_18_add_daily_income_actual_work_minutes','20260827_22_add_daily_income_header_description_memo',
        '20260827_23_create_daily_income_mariadb_compatible_baseline','20260827_21_add_daily_income_non_tax_command_audit',
        '20260828_01_add_daily_income_calculation_note','20260828_02_add_daily_income_non_taxable_reason',
        '20260828_03_seed_income_calculation_code_ssot','20260828_04_add_daily_income_line_adjustment_contract',
        '20260828_07_add_income_insurance_applicability_policy',
        '20260829_03_add_insurance_eligibility_revisions','20260829_04_close_daily_income_insurance_eligibility',
        '20260829_05_close_daily_income_eligibility_result_contract','20260829_08_remove_daily_workday_adjustment_amount',
        '20260831_01_integrate_insurance_eligibility_into_insurance_types',
        '20260831_03_remove_insurance_eligibility_standard_type',
        '20260831_04_add_insurance_component_input_templates',
        '20260831_05_add_insurance_integration_column_comments',
        '20260831_06_unify_statutory_standard_select_codes',
        '20260831_07_seed_employment_industrial_eligibility',
        '20260831_08_add_insurance_eligibility_reason_codes',
        '20260831_10_widen_daily_income_line_application_status',
    ];
    foreach($migrations as $migration){$guard($db,$schema);$executeSql($db,PROJECT_ROOT.'/app/migrations/'.$migration.'.up.sql');}
    $revisions=(int)$db->query("SELECT COUNT(*) FROM system_statutory_standards WHERE policy_component_code='ELIGIBILITY'")->fetchColumn();
    $sources=(int)$db->query("SELECT COUNT(*) FROM system_statutory_standard_sources s JOIN system_statutory_standards r ON r.id=s.standard_id WHERE r.policy_component_code='ELIGIBILITY'")->fetchColumn();
    $deleted=(int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('institution_daily_worker_employment_periods','institution_social_insurance_construction_transition_facts','institution_social_insurance_transition_fact_attachments','institution_social_insurance_workplace_groups','institution_social_insurance_workplace_group_members')")->fetchColumn();
    if($revisions!==37||$sources!==37||$deleted!==0)throw new RuntimeException('가입자격 Migration 건수 검증 실패');
    $standardInsert=$db->prepare('INSERT INTO system_statutory_standards(id,sort_no,standard_type_code,effective_from,effective_to,value_data,note,created_at,created_by) VALUES(:id,:sort,:type,:from,:to,:value,:note,NOW(),\'SYSTEM:FIXTURE\')');
    $fixtureStandards=[
        ['f1000000-0000-4000-8000-000000000001',901,'DAILY_WORKER_INCOME_TAX','2013-01-01','2018-12-31',['daily_income_deduction'=>100000,'daily_income_tax_rate'=>.06,'daily_income_tax_credit_rate'=>.55,'calculation_policy'=>['method'=>'TRUNCATE','discard_below_unit'=>1,'stage'=>'AFTER_TAX_CREDIT','base_value_code'=>'DAILY_TAX_AFTER_CREDIT','aggregation_unit'=>'WITHHOLDING_AGENT_RECIPIENT_WORKDAY_PAYMENT','threshold'=>1000,'threshold_comparison'=>'LESS_THAN','workplace_scope'=>'EACH_WORKPLACE']]],
        ['f1000000-0000-4000-8000-000000000002',902,'LOCAL_INCOME_TAX_WITHHOLDING','2013-01-01','2013-12-31',['rate_value'=>.1,'calculation_policy'=>['method'=>'TRUNCATE','discard_below_unit'=>10]]],
        ['f1000000-0000-4000-8000-000000000003',903,'EMPLOYMENT_INSURANCE','2013-07-01','2019-09-30',['employee_rate'=>.0065,'employer_rate'=>.0065,'additional_employer_rates'=>[['business_size_code'=>'UNDER_150','employer_rate'=>.0025]],'calculation_policy'=>['method'=>'TRUNCATE','discard_below_unit'=>10,'stage'=>'PREMIUM_CALCULATION']]],
    ];
    foreach($fixtureStandards as [$id,$sort,$type,$from,$to,$value]){$standardInsert->execute(['id'=>$id,'sort'=>$sort,'type'=>$type,'from'=>$from,'to'=>$to,'value'=>json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'note'=>'비식별 Runtime Fixture']);}
    $db->exec("UPDATE system_statutory_standards SET policy_component_code='PREMIUM',employment_type_code='ALL',work_scope_code='ALL',additional_dimension_data=JSON_OBJECT(),additional_dimension_key=SHA2('{}',256) WHERE id='f1000000-0000-4000-8000-000000000003'");
    $operatingSchema = str_replace('`', '``', $config['dbname']);
    $db->exec("INSERT INTO system_statutory_standards SELECT operating_row.* FROM `{$operatingSchema}`.system_statutory_standards operating_row WHERE operating_row.standard_type_code='INDUSTRIAL_ACCIDENT' AND operating_row.policy_component_code='PREMIUM' AND operating_row.effective_from<='2013-08-31' AND (operating_row.effective_to IS NULL OR operating_row.effective_to>='2013-08-01')");
    $rrn=(new Crypto())->encryptResidentNumber('600101-1234567');
    $workerUpdate=$db->prepare('UPDATE system_clients SET rrn=:rrn WHERE id=:id');$workerUpdate->execute(['rrn'=>$rrn,'id'=>'fixture-worker']);
    $db->exec("INSERT INTO institution_workplace_size_periods(id,company_id,calculation_purpose_code,business_size_code,confirmation_status_code,effective_from,effective_to,revision_no,previous_period_id) VALUES('fixture-size-period','fixture-company','EMPLOYMENT_INSURANCE_VOCATIONAL','UNDER_150','CONFIRMED','2013-01-01','2013-12-31',1,NULL)");
    $workdays=[];foreach(['2013-08-06','2013-08-07','2013-08-08','2013-08-09','2013-08-10'] as $date){$workdays[]=['work_date'=>$date,'actual_work_minutes'=>480,'daily_rate_amount'=>90000,'taxable_additional_amount'=>0,'non_taxable_additional_amount'=>0];}
    $workdays[4]['taxable_additional_amount']=2940;
    $payload=['request_key'=>'daily-closure-'.$token,'income_year_month'=>'2013-08','payment_date'=>'2013-09-11','document_title'=>'2013-08 비식별 일용근로소득 Closure','description'=>'공식 Service 경로 검증','memo'=>null,'groups'=>[['business_unit'=>'HQ','project_id'=>null,'work_team_id'=>null,'work_description'=>'비식별 본사 일용업무','employment_insurance_application_status_code'=>'APPLICABLE','employment_insurance_decision_source_code'=>'MANUAL_INTERIM_GROUP','industrial_accident_application_status_code'=>'EXCLUDED','industrial_accident_decision_reason'=>'비식별 Fixture 산재보험 미적용','industrial_accident_decision_source_code'=>'MANUAL_INTERIM_GROUP','items'=>[['worker_client_id'=>'fixture-worker','work_type_code'=>'GENERAL','work_description'=>'비식별 지원업무','workdays'=>$workdays]]]]];
    $service=new DailyEmploymentIncomeService($db);
    $preview=$service->calculate($payload)['data'];
    $previewItem=$preview['groups'][0]['items'][0];$eligibility=[];$lineStatuses=[];
    foreach($previewItem['lines'] as $line){if(($line['line_type_code']??'')==='DEDUCTION'&&in_array(($line['line_code']??''),['NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE'],true)){$eligibility[$line['line_code']]=$line['eligibility_result']['status']??null;$lineStatuses[$line['line_code']]=$line['application_status_code']??null;}}
    echo json_encode(['calculate_observed'=>['resolver_eligibility'=>$eligibility,'line_application_status'=>$lineStatuses,'summary'=>$previewItem['summary']]],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),PHP_EOL;
    if($eligibility!==['NATIONAL_PENSION'=>'NOT_ELIGIBLE','HEALTH_INSURANCE'=>'NOT_ELIGIBLE','LONG_TERM_CARE'=>'NOT_ELIGIBLE']
        ||(float)$previewItem['summary']['total_gross_amount']!==452940.0||(float)$previewItem['summary']['total_deduction_amount']!==2940.0||(float)$previewItem['summary']['total_net_payment_amount']!==450000.0){throw new RuntimeException('정순옥 비식별 공식 calculate 기대결과 불일치');}
    $pathResults=['preview'=>$insuranceProjection($previewItem)];
    $assertExpectedProjection($pathResults['preview'],'Preview');

    fwrite(STDERR, "[daily-closure-test] official-save start\n");
    $saved=$service->save($payload)['data'];
    fwrite(STDERR, "[daily-closure-test] official-save complete\n");
    $documentId=(string)$saved['id'];
    $detail=$service->detail($documentId)['data'];
    $detailItem=$detail['groups'][0]['items'][0];
    $storedProjection=['insurance'=>[],'summary'=>[
        'total_gross_amount'=>$detailItem['total_gross_amount'],
        'total_deduction_amount'=>$detailItem['total_deduction_amount'],
        'total_net_payment_amount'=>$detailItem['total_net_payment_amount'],
    ]];
    foreach(($detail['calculation_revision']['results']??[]) as $result){
        $code=($result['result_type_code']??'')==='LONG_TERM_CARE_INSURANCE'?'LONG_TERM_CARE':(string)$result['result_type_code'];
        $base=['eligibility'=>$result['eligibility_status_code']??null,'application'=>$result['status_code']??null,'eligibility_revision_id'=>$result['eligibility_revision_id']??null];
        $storedProjection['insurance'][$code]['DEDUCTION']=$base+['calculated'=>$result['automatic_employee_amount']??null,'final'=>$result['confirmed_employee_amount']??null];
        $storedProjection['insurance'][$code]['EMPLOYER_BURDEN']=$base+['calculated'=>$result['automatic_employer_amount']??null,'final'=>$result['confirmed_employer_amount']??null];
    }
    $employmentStored=current(array_filter($detailItem['lines']??[],static fn(array $line):bool=>($line['line_code']??'')==='EMPLOYMENT_INSURANCE'&&($line['line_type_code']??'')==='DEDUCTION'))?:[];
    $storedProjection['insurance']['EMPLOYMENT_INSURANCE']['DEDUCTION']=['eligibility'=>null,'application'=>$employmentStored['application_status_code']??null,'calculated'=>$employmentStored['calculated_amount']??null,'final'=>$employmentStored['final_amount']??null,'eligibility_revision_id'=>null];
    $pathResults['detail']=$storedProjection;
    $assertExpectedProjection($pathResults['detail'],'저장 후 Detail');

    $recalculated=$service->calculate($payload+['id'=>$documentId])['data'];
    $pathResults['recalculate']=$insuranceProjection($recalculated['groups'][0]['items'][0]);
    $assertExpectedProjection($pathResults['recalculate'],'재계산');
    fwrite(STDERR, "[daily-closure-test] submission-preflight start\n");
    $preflight=$service->submissionPreflight($documentId)['data'];
    fwrite(STDERR, "[daily-closure-test] submission-preflight complete\n");
    if (($preflight['can_submit'] ?? false) !== true || ($preflight['insurance_status'] ?? '') !== 'CALCULATED') {
        throw new RuntimeException('결재요청 Preflight 불일치: ' . json_encode($preflight, JSON_UNESCAPED_UNICODE));
    }
    $approvalPreflight=$service->submissionPreflight($documentId)['data'];
    $left=$preflight;$right=$approvalPreflight;unset($left['checked_at'],$right['checked_at']);
    if($left!==$right)throw new RuntimeException('승인 직전 검증 결과 불일치');

    $countSql=['documents'=>'institution_daily_employment_incomes','commands'=>'institution_daily_employment_income_commands','revisions'=>'institution_daily_employment_income_calculation_revisions','results'=>'institution_daily_employment_income_calculation_results'];
    $countsBeforeIdempotent=[];foreach($countSql as $key=>$table)$countsBeforeIdempotent[$key]=(int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
    $idempotent=$service->save($payload)['data'];
    $countsAfterIdempotent=[];foreach($countSql as $key=>$table)$countsAfterIdempotent[$key]=(int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
    if((string)$idempotent['id']!==$documentId||$countsBeforeIdempotent!==$countsAfterIdempotent)throw new RuntimeException('동일 request_key 멱등성 불일치');

    $revisionId=(string)$detail['calculation_revision']['id'];
    $resultStatement=$db->prepare('SELECT * FROM institution_daily_employment_income_calculation_results WHERE calculation_revision_id=:id ORDER BY id');
    $resultStatement->execute(['id'=>$revisionId]);$resultSnapshotBefore=$resultStatement->fetchAll(PDO::FETCH_ASSOC);
    $resultHashBefore=hash('sha256',json_encode($resultSnapshotBefore,JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION));
    $resolverTypes=['NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE_INSURANCE'];
    $premiumOnlyTypes=['EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT_INSURANCE'];
    foreach($resultSnapshotBefore as $result){
        $type=(string)$result['result_type_code'];
        $snapshot=json_decode((string)$result['eligibility_snapshot'],true);
        if(!is_array($snapshot)||!is_array($snapshot['scope_derivation_snapshot']??null))throw new RuntimeException('보험 정책 Snapshot 보존 불일치');
        if(in_array($type,$resolverTypes,true)&&(
            empty($result['eligibility_revision_id'])||($snapshot['eligibility_revision_id']??null)!==$result['eligibility_revision_id']
        ))throw new RuntimeException('공식 가입자격 Revision Snapshot 보존 불일치: '.$type);
        if(in_array($type,$premiumOnlyTypes,true)&&(
            $result['eligibility_revision_id']!==null||($snapshot['eligibility_revision_id']??null)!==null||empty($snapshot['premium_revision_id'])
        ))throw new RuntimeException('회사부담 PREMIUM 전용 Snapshot 계약 불일치: '.$type);
    }
    $revisionSourceHash=(string)$detail['calculation_revision']['source_hash'];
    $preflightSourceHash=(string)($preflight['source_hash']??'');
    $sourceHasher=new DailyEmploymentIncomeCalculationSourceService();
    $sourcePayload=static fn(array $groups):array=>[
        'daily_employment_income_id'=>$documentId,'income_year_month'=>'2013-08','payment_date'=>'2013-09-11',
        'calculation_policy_version'=>DailyEmploymentIncomeCalculationResultService::SNAPSHOT_SCHEMA_VERSION,'groups'=>$groups,
    ];
    $changedPreview=$service->calculate($changedPayload??($payload+['id'=>$documentId]))['data'];
    $changedGroups=$changedPreview['groups'];
    $changedGroups[0]['items'][0]['workdays'][0]['actual_work_minutes']=479;
    $changedSourceHash=$sourceHasher->hash($sourcePayload($changedGroups));
    $resetSourceHash=$sourceHasher->hash($sourcePayload($preview['groups']));
    if(!hash_equals($revisionSourceHash,$preflightSourceHash)||hash_equals($revisionSourceHash,$changedSourceHash)||!hash_equals($revisionSourceHash,$resetSourceHash)){
        throw new RuntimeException('Source Hash 입력 변경·복원 계약 불일치');
    }
    $db->prepare("UPDATE institution_daily_employment_income_calculation_revisions SET status_code='CONFIRMED',confirmed_by='SYSTEM:FIXTURE_APPROVAL',confirmed_at=NOW(),updated_by='SYSTEM:FIXTURE_APPROVAL' WHERE id=:id")->execute(['id'=>$revisionId]);
    $changedPayload=$payload;$changedPayload['id']=$documentId;$changedPayload['request_key']='daily-closure-change-'.$token;$changedPayload['groups'][0]['items'][0]['workdays'][0]['actual_work_minutes']=479;
    try{$service->save($changedPayload);throw new RuntimeException('확정 Result UPDATE가 차단되지 않았습니다.');}catch(RuntimeException $exception){if(!str_contains($exception->getMessage(),'Revision'))throw $exception;}
    $resultStatement->execute(['id'=>$revisionId]);$resultSnapshotAfter=$resultStatement->fetchAll(PDO::FETCH_ASSOC);
    $resultHashAfter=hash('sha256',json_encode($resultSnapshotAfter,JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION));
    if($resultHashBefore!==$resultHashAfter)throw new RuntimeException('원본 변경 시 확정 Snapshot이 변경됐습니다.');

    $executeSql($db,PROJECT_ROOT.'/app/migrations/20260829_08_remove_daily_workday_adjustment_amount.down.sql');
    try{$executeSql($db,PROJECT_ROOT.'/app/migrations/20260829_05_close_daily_income_eligibility_result_contract.down.sql');throw new RuntimeException('참조자료 존재 상태 Down이 차단되지 않았습니다.');}catch(PDOException $exception){if((string)$exception->getCode()!=='45000')throw $exception;}
    foreach(['institution_daily_employment_income_calculation_results','institution_daily_employment_income_calculation_revisions','institution_daily_employment_income_lines','institution_daily_employment_income_workdays','institution_daily_employment_income_items','institution_daily_employment_income_groups','institution_daily_employment_income_commands','institution_daily_employment_incomes'] as $table)$db->exec('DELETE FROM '.$table);
    $reverseMigrations=array_values(array_filter(
        array_reverse($migrations),
        static fn(string $migration): bool => $migration !== '20260829_08_remove_daily_workday_adjustment_amount'
    ));
    foreach($reverseMigrations as $migration){
        $guard($db,$schema);
        try{$executeSql($db,PROJECT_ROOT.'/app/migrations/'.$migration.'.down.sql');}
        catch(Throwable $exception){$stage=$db->query('SELECT @daily_income_group_down_stage')->fetchColumn();throw new RuntimeException('Down 실패: '.$migration.' / stage='.(string)$stage.' / '.$exception->getMessage(),0,$exception);}
    }
    $afterDownInventory=$schemaInventory($db);
    if($afterDownInventory!==$cleanBaselineInventory){$counts=static fn(array $inventory):array=>array_map('count',$inventory);throw new RuntimeException('전체 역순 Down 잔존 불일치: '.json_encode(['before'=>$counts($cleanBaselineInventory),'after'=>$counts($afterDownInventory)],JSON_UNESCAPED_UNICODE));}
    echo json_encode(['official_service_paths'=>'PASS','paths'=>array_keys($pathResults),'preflight'=>'PASS','approval_preflight'=>'PASS','source_hashes'=>['revision'=>$revisionSourceHash,'preflight'=>$preflightSourceHash,'matches'=>true,'changed_input'=>$changedSourceHash,'reset'=>$resetSourceHash],'idempotency'=>'PASS','confirmed_result_update_guard'=>'PASS','confirmed_snapshot_hash'=>$resultHashBefore,'down_reference_guard'=>'PASS','reverse_down'=>'PASS','schema_inventory_residue'=>0],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),PHP_EOL;
    echo json_encode(['success'=>true,'migrations'=>$migrations,'revision_count'=>$revisions,'source_count'=>$sources,'deleted_table_count'=>$deleted],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),PHP_EOL;
    echo json_encode(['official_calculate'=>'PASS','eligibility'=>$eligibility,'employee_insurance_amounts'=>['NATIONAL_PENSION'=>0,'HEALTH_INSURANCE'=>0,'LONG_TERM_CARE'=>0],'employer_insurance_amounts'=>['NATIONAL_PENSION'=>0,'HEALTH_INSURANCE'=>0,'LONG_TERM_CARE'=>0],'employment_insurance_employee_amount'=>2940,'gross_amount'=>452940,'net_payment_amount'=>450000],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),PHP_EOL;
    $exitCode=0;
} finally {
    $cleanupOk=$cleanup();
    $schemasAfter=$server->query('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA ORDER BY SCHEMA_NAME')->fetchAll(PDO::FETCH_COLUMN);
    $newResidue=array_values(array_diff($schemasAfter,$schemasBefore));
    $ownedResidue=array_values(array_filter($newResidue,static fn(string $name):bool=>$name===$schema));
    echo json_encode(['cleanup'=>$cleanupOk?'DROPPED':'FAILED','schema'=>$schema,'cleanup_attempts'=>$cleanupAttempts,
        'cleanup_error'=>$cleanupError,'schema_count_after'=>count($schemasAfter),'concurrent_external_schema_additions'=>$newResidue,
        'owned_residual_schemas'=>$ownedResidue,'owned_residual_schema_count'=>count($ownedResidue),'exit_code'=>$exitCode],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),PHP_EOL;
    if(!$cleanupOk||$ownedResidue!==[])$exitCode=1;
}
exit($exitCode);
