<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

$mode=strtolower((string)($argv[1]??'verify'));
if(!in_array($mode,['up','verify'],true))throw new InvalidArgumentException('사용법: php tools/apply_statutory_standard_immutability.php [up|verify]');
$db=DbPdo::conn();$db->setAttribute(PDO::ATTR_EMULATE_PREPARES,true);
$execute=function(PDO $connection,string $path):void{$delimiter=';';$buffer='';foreach(preg_split('/\r\n|\n|\r/',(string)file_get_contents($path))?:[]as$line){if(preg_match('/^DELIMITER\s+(.+)$/i',trim($line),$match)){$delimiter=$match[1];continue;}$buffer.=$line."\n";$trimmed=rtrim($buffer);if(!str_ends_with($trimmed,$delimiter))continue;$statement=trim(substr($trimmed,0,-strlen($delimiter)));if($statement!=='')$connection->exec($statement);$buffer='';}if(trim($buffer)!=='')throw new RuntimeException('Migration SQL 구분자가 닫히지 않았습니다.');};
$names=['trg_statutory_standard_bu','trg_statutory_standard_bd','trg_statutory_standard_source_bu','trg_statutory_standard_source_bd'];$quoted=implode(',',array_map([$db,'quote'],$names));
if($mode==='up'){if((int)$db->query("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME IN($quoted)")->fetchColumn()!==0)throw new RuntimeException('법정기준 불변성 Trigger가 이미 존재합니다.');$execute($db,PROJECT_ROOT.'/app/migrations/20260903_12_enforce_statutory_revision_immutability.up.sql');}
$triggers=$db->query("SELECT TRIGGER_NAME,ACTION_TIMING,EVENT_MANIPULATION,EVENT_OBJECT_TABLE FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME IN($quoted) ORDER BY TRIGGER_NAME")->fetchAll(PDO::FETCH_ASSOC)?:[];
if(count($triggers)!==4)throw new RuntimeException('법정기준 불변성 Trigger 4개가 완전하지 않습니다.');
$target='021889f4-3c43-466d-8e33-80f9f39455bc';$source='da54715a-c51d-4820-9adc-fbbc77f12284';$checks=[];
foreach(['revision_update'=>['UPDATE system_statutory_standards SET note=note WHERE id=:id',$target],'revision_delete'=>['DELETE FROM system_statutory_standards WHERE id=:id',$target],'source_update'=>['UPDATE system_statutory_standard_sources SET note=note WHERE id=:id',$source],'source_delete'=>['DELETE FROM system_statutory_standard_sources WHERE id=:id',$source]]as$name=>$case){$checks[$name]=false;try{$statement=$db->prepare($case[0]);$statement->execute([':id'=>$case[1]]);}catch(Throwable){$checks[$name]=true;}if(!$checks[$name])throw new RuntimeException($name.' 불변성 차단 검증에 실패했습니다.');}
echo json_encode(['success'=>true,'mode'=>$mode,'triggers'=>$triggers,'blocked'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
