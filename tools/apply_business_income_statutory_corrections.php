<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use App\Services\System\StatutoryStandardResolver;
use App\Models\System\StatutoryStandardSupersessionModel;
use Core\DbPdo;
use Core\Helpers\ActorHelper;

$mode=strtolower((string)($argv[1]??'verify'));
if(!in_array($mode,['test','up','verify'],true)) throw new InvalidArgumentException('사용법: php tools/apply_business_income_statutory_corrections.php [test|up|verify]');
$db=DbPdo::conn();$db->setAttribute(PDO::ATTR_EMULATE_PREPARES,true);
$execute=function(PDO $connection,string $path):void{$delimiter=';';$buffer='';foreach(preg_split('/\r\n|\n|\r/',(string)file_get_contents($path))?:[] as $line){if(preg_match('/^DELIMITER\s+(.+)$/i',trim($line),$match)){$delimiter=$match[1];continue;}$buffer.=$line."\n";$trimmed=rtrim($buffer);if(!str_ends_with($trimmed,$delimiter))continue;$statement=trim(substr($trimmed,0,-strlen($delimiter)));if($statement!=='')$connection->exec($statement);$buffer='';}if(trim($buffer)!=='')throw new RuntimeException('Migration SQL 구분자가 닫히지 않았습니다.');};
$originalIds=['021889f4-3c43-466d-8e33-80f9f39455bc','7255f865-08d0-4fd9-b9d6-85361f93fe0a','7af041bd-74b5-4d85-a489-f8dc703c5a06'];
$hash=function(PDO $connection,array $ids):string{$quoted=implode(',',array_map([$connection,'quote'],$ids));return hash('sha256',json_encode($connection->query("SELECT * FROM system_statutory_standards WHERE id IN($quoted) ORDER BY id")->fetchAll(PDO::FETCH_ASSOC)?:[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION));};
$beforeHash=$hash($db,$originalIds);$beforeCounts=['revision'=>(int)$db->query('SELECT COUNT(*) FROM system_statutory_standards')->fetchColumn(),'source'=>(int)$db->query('SELECT COUNT(*) FROM system_statutory_standard_sources')->fetchColumn(),'relation'=>(int)$db->query('SELECT COUNT(*) FROM system_statutory_standard_supersessions')->fetchColumn()];
if(in_array($mode,['test','up'],true)){$db->beginTransaction();try{$db->exec('SET @statutory_revision_actor='.$db->quote(ActorHelper::system('STATUTORY_POLICY_CORRECTION')));$execute($db,PROJECT_ROOT.'/app/migrations/20260903_11_add_business_income_statutory_correction_revisions.up.sql');if($mode==='test'){$db->rollBack();}else{$db->commit();}}catch(Throwable $exception){if($db->inTransaction())$db->rollBack();throw$exception;}}
if($mode==='test'){if($beforeHash!==$hash($db,$originalIds))throw new RuntimeException('Test Rollback 후 원 Revision이 변경됐습니다.');echo json_encode(['success'=>true,'mode'=>$mode,'rollback'=>true,'counts'=>$beforeCounts],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;exit;}
$resolver=new StatutoryStandardResolver($db);$dates=['2013-06-30','2024-06-30','2024-07-01',date('Y-m-d')];$resolved=[];
foreach($dates as$date){$income=$resolver->resolve('BUSINESS_INCOME_WITHHOLDING',$date);$local=$resolver->resolve('LOCAL_INCOME_TAX_WITHHOLDING',$date);$resolved[$date]=['income'=>$income['id'],'local'=>$local['id']];foreach([$income,$local]as$row){$policy=(array)($row['value_data']['calculation_policy']??[]);$required=['method','discard_below_unit','stage','base_value_code','aggregation_unit','application_order','threshold','threshold_comparison'];if(array_diff($required,array_keys($policy))!==[])throw new RuntimeException('완전한 계산정책이 아닌 leaf Revision이 선택됐습니다.');}}
if($beforeHash!==$hash($db,$originalIds))throw new RuntimeException('기존 Revision 불변성 검증에 실패했습니다.');
$afterCounts=['revision'=>(int)$db->query('SELECT COUNT(*) FROM system_statutory_standards')->fetchColumn(),'source'=>(int)$db->query('SELECT COUNT(*) FROM system_statutory_standard_sources')->fetchColumn(),'relation'=>(int)$db->query('SELECT COUNT(*) FROM system_statutory_standard_supersessions')->fetchColumn()];
$chainCount=count((new StatutoryStandardSupersessionModel($db))->chain('b15c0001-2026-0903-0000-000000000002'));
echo json_encode(['success'=>true,'mode'=>$mode,'original_revision_unchanged'=>true,'before_counts'=>$beforeCounts,'after_counts'=>$afterCounts,'business_income_chain_count'=>$chainCount,'resolved'=>$resolved],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
