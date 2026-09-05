<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT.'/core/Database.php';
require PROJECT_ROOT.'/core/DbPdo.php';

use Core\DbPdo;

$mode=strtolower((string)($argv[1]??'verify'));
if(!in_array($mode,['preflight','up','permissions','registry','verify'],true))throw new InvalidArgumentException('사용법: php tools/apply_vehicle_log_migration.php [preflight|up|permissions|registry|verify]');
$db=DbPdo::conn();
$tables=['ledger_vehicles','ledger_vehicle_trip_logs'];
$existing=static function()use($db,$tables):array{$found=[];foreach($tables as$table){$stmt=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$stmt->execute([$table]);if((int)$stmt->fetchColumn()===1)$found[]=$table;}return$found;};
if($mode==='preflight'){echo json_encode(['existing'=>$existing(),'ready'=>$existing()===[]],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;exit($existing()===[]?0:1);}
if($mode==='up'){$sql=(string)file_get_contents(PROJECT_ROOT.'/app/migrations/20260905_09_create_ledger_vehicle_trip_logs.up.sql');foreach(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[]))as$statement)$db->exec($statement);}
if($mode==='permissions'){$db->exec((string)file_get_contents(PROJECT_ROOT.'/app/migrations/20260905_10_backfill_vehicle_log_personal_permissions.up.sql'));}
if($mode==='registry'){$sql=(string)file_get_contents(PROJECT_ROOT.'/app/migrations/20260905_11_normalize_vehicle_log_registry_labels.up.sql');foreach(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[]))as$statement)$db->exec($statement);}
$missing=(int)$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('ledger_vehicles','ledger_vehicle_trip_logs') AND COALESCE(COLUMN_COMMENT,'')='' ")->fetchColumn();
$triggers=(int)$db->query("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE IN ('ledger_vehicles','ledger_vehicle_trip_logs')")->fetchColumn();
$ready=count($existing())===2&&$missing===0&&$triggers===0;
echo json_encode(['tables'=>$existing(),'comment_missing'=>$missing,'triggers'=>$triggers,'ready'=>$ready],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;exit($ready?0:1);
