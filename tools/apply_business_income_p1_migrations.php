<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use App\Services\System\StatutoryStandardResolver;
use Core\DbPdo;

$mode=strtolower((string)($argv[1]??'verify'));
if(!in_array($mode,['preflight','up','verify'],true))throw new InvalidArgumentException('사용법: php tools/apply_business_income_p1_migrations.php [preflight|up|verify]');
$db=DbPdo::conn();$db->setAttribute(PDO::ATTR_EMULATE_PREPARES,true);
$files=array_map(static fn(int $number):string=>(string)(glob(PROJECT_ROOT.'/app/migrations/20260903_'.str_pad((string)$number,2,'0',STR_PAD_LEFT).'_*.up.sql')[0]??''),range(1,9));
if(in_array('', $files,true))throw new RuntimeException('사업소득 01~09 Migration 파일이 완전하지 않습니다.');
$execute=function(PDO $connection,string $path):void{$delimiter=';';$buffer='';foreach(preg_split('/\r\n|\n|\r/',(string)file_get_contents($path))?:[]as$line){if(preg_match('/^DELIMITER\s+(.+)$/i',trim($line),$match)){$delimiter=$match[1];continue;}$buffer.=$line."\n";$trimmed=rtrim($buffer);if(!str_ends_with($trimmed,$delimiter))continue;$statement=trim(substr($trimmed,0,-strlen($delimiter)));if($statement!=='')$connection->exec($statement);$buffer='';}if(trim($buffer)!=='')throw new RuntimeException('Migration SQL 구분자가 닫히지 않았습니다: '.basename($path));};
$hash=function(PDO $connection,string $table):string{return hash('sha256',json_encode($connection->query("SELECT * FROM `$table` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC)?:[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION));};
$immutableBefore=['revision'=>$hash($db,'system_statutory_standards'),'source'=>$hash($db,'system_statutory_standard_sources'),'supersession'=>$hash($db,'system_statutory_standard_supersessions')];
$leafPreflight=function(PDO $connection):array{$resolver=new StatutoryStandardResolver($connection);$required=['method','discard_below_unit','stage','base_value_code','aggregation_unit','application_order','threshold','threshold_comparison'];$dates=['BUSINESS_INCOME_WITHHOLDING'=>['2013-01-01','2024-06-30','2024-07-01',date('Y-m-d')],'LOCAL_INCOME_TAX_WITHHOLDING'=>['2013-01-01','2013-12-31','2014-01-01','2024-06-30','2024-07-01',date('Y-m-d')]];$result=[];foreach($dates as$type=>$typeDates)foreach($typeDates as$date){$revision=$resolver->resolve($type,$date);if((string)$revision['standard_type_code']!==$type||$date<(string)$revision['effective_from']||($revision['effective_to']!==null&&$date>(string)$revision['effective_to']))throw new RuntimeException('법정기준 leaf Type·기간 검증에 실패했습니다.');$policy=(array)($revision['value_data']['calculation_policy']??[]);if(!isset($revision['value_data']['rate_value'])||array_diff($required,array_keys($policy))!==[])throw new RuntimeException('법정기준 leaf 필수 정책이 누락됐습니다.');$source=$connection->prepare('SELECT COUNT(*) FROM system_statutory_standard_sources WHERE standard_id=:id');$source->execute([':id'=>$revision['id']]);if((int)$source->fetchColumn()<1)throw new RuntimeException('법정기준 leaf Source가 없습니다.');$result[]=['type'=>$type,'date'=>$date,'revision_id'=>$revision['id']];}return$result;};
$leaf=$leafPreflight($db);
$targetTables=['system_client_tax_profiles','institution_business_incomes','institution_business_income_groups','institution_business_income_items','institution_business_income_calculation_revisions','institution_business_income_calculation_lines','institution_business_income_commands','institution_business_income_artifact_links','institution_business_income_closures','ledger_evidence_business_income','ledger_evidence_business_income_raw_lines'];$quoted=implode(',',array_map([$db,'quote'],$targetTables));$beforeTables=$db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN($quoted) ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN)?:[];
if($mode==='up'){if($beforeTables!==[])throw new RuntimeException('사업소득 Migration 대상 객체가 이미 일부 존재합니다.');$db->exec('SET @business_income_statutory_leaf_preflight_passed=1');foreach($files as$file)$execute($db,$file);}
$afterTables=$db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN($quoted) ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN)?:[];$immutableAfter=['revision'=>$hash($db,'system_statutory_standards'),'source'=>$hash($db,'system_statutory_standard_sources'),'supersession'=>$hash($db,'system_statutory_standard_supersessions')];if($immutableBefore!==$immutableAfter)throw new RuntimeException('사업소득 Migration이 법정기준 불변 데이터를 변경했습니다.');if($mode!=='preflight'&&count($afterTables)!==count($targetTables))throw new RuntimeException('사업소득 P1 테이블이 완전하지 않습니다.');
echo json_encode(['success'=>true,'mode'=>$mode,'migration_files'=>array_map('basename',$files),'leaf_checks'=>$leaf,'before_tables'=>$beforeTables,'after_tables'=>$afterTables,'immutable_hash_unchanged'=>true],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
