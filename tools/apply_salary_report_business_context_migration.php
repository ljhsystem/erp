<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

$mode=strtolower((string)($argv[1]??'preflight'));if(!in_array($mode,['preflight','up','verify'],true))throw new InvalidArgumentException('사용법: php tools/apply_salary_report_business_context_migration.php [preflight|up|verify]');
$db=DbPdo::conn();$db->setAttribute(PDO::ATTR_EMULATE_PREPARES,true);$migration='20260825_06_add_salary_report_business_context';$file=PROJECT_ROOT.'/app/migrations/'.$migration.'.up.sql';
$state=static function()use($db):array{return['row_count'=>(int)$db->query('SELECT COUNT(*) FROM ledger_evidence_salary_report')->fetchColumn(),'context_columns'=>(int)$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_salary_report' AND COLUMN_NAME IN ('client_id','employee_id','project_id','bank_account_id','card_id','team_id')")->fetchColumn(),'context_values'=>(int)$db->query("SELECT COUNT(*) FROM ledger_evidence_salary_report WHERE ".((int)$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_salary_report' AND COLUMN_NAME='client_id'")->fetchColumn()===1?"client_id IS NOT NULL OR employee_id IS NOT NULL OR project_id IS NOT NULL OR bank_account_id IS NOT NULL OR card_id IS NOT NULL OR team_id IS NOT NULL":"1=0"))->fetchColumn()];};
$schema=static function()use($db):string{$row=$db->query('SHOW CREATE TABLE ledger_evidence_salary_report')->fetch(PDO::FETCH_NUM);return(string)($row[1]??'');};
$execute=static function()use($db,$file):void{$delimiter=';';$buffer='';foreach(preg_split('/\r\n|\n|\r/',(string)file_get_contents($file))as$line){if(preg_match('/^DELIMITER\s+(.+)$/i',trim($line),$m)){$delimiter=$m[1];continue;}$buffer.=$line."\n";$trimmed=rtrim($buffer,"\r\n");if(!str_ends_with($trimmed,$delimiter))continue;$statement=trim(substr($trimmed,0,-strlen($delimiter)));if($statement!=='')$db->exec($statement);$buffer='';}if(trim($buffer)!=='')throw new RuntimeException('Migration SQL 구분자가 닫히지 않았습니다.');};
$before=$state();$beforeSchema=$schema();$snapshot=null;$history=null;
if($mode==='up'){if($before['context_columns']!==0)throw new RuntimeException('급여(신고) Context Migration Preflight가 통과하지 않았습니다.');$dir=PROJECT_ROOT.'/storage/db_backup';$stamp=date('Ymd_His');$snapshot=$dir.'/'.$migration.'_schema_before_'.$stamp.'.json';file_put_contents($snapshot,json_encode(['migration'=>$migration,'captured_at'=>date(DATE_ATOM),'sql_sha256'=>hash_file('sha256',$file),'state'=>$before,'schema'=>$beforeSchema],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");$execute();$history=$dir.'/'.$migration.'_application_'.$stamp.'.json';}
$after=$state();$afterSchema=$schema();$applied=$after['context_columns']===6;if(in_array($mode,['up','verify'],true)&&(!$applied||$before['row_count']!==$after['row_count']))throw new RuntimeException('급여(신고) Context Migration 최종 검증에 실패했습니다.');
$report=['success'=>true,'mode'=>$mode,'migration'=>$migration,'sql_sha256'=>hash_file('sha256',$file),'applied'=>$applied,'snapshot_path'=>$snapshot,'application_history_path'=>$history,'before'=>$before,'after'=>$after,'row_count_unchanged'=>$before['row_count']===$after['row_count'],'schema'=>$afterSchema];if($history!==null)file_put_contents($history,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
