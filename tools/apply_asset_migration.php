<?php
declare(strict_types=1);
define('PROJECT_ROOT',dirname(__DIR__));
require PROJECT_ROOT.'/core/Database.php';
require PROJECT_ROOT.'/core/DbPdo.php';
use Core\DbPdo;
$mode=strtolower((string)($argv[1]??'verify'));
if(!in_array($mode,['preflight','up','verify'],true))throw new InvalidArgumentException('지원하지 않는 모드입니다.');
$db=DbPdo::conn();
$tables=['ledger_assets','ledger_asset_assignments','ledger_asset_depreciations','ledger_asset_disposals'];
$exists=static function(PDO$db,string$t):bool{$q=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t');$q->execute([':t'=>$t]);return(int)$q->fetchColumn()>0;};
$before=array_values(array_filter($tables,fn($t)=>$exists($db,$t)));
if($mode==='preflight'){$references=$db->query("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,CHARACTER_SET_NAME,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='system_company' AND COLUMN_NAME='id') OR (TABLE_NAME='system_projects' AND COLUMN_NAME='id') OR (TABLE_NAME='ledger_accounts' AND COLUMN_NAME='id') OR (TABLE_NAME='ledger_vouchers' AND COLUMN_NAME='id')) ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_ASSOC);echo json_encode(['mode'=>$mode,'existing'=>$before,'references'=>$references,'ready'=>$before===[]],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;exit($before===[]?0:1);}
if($mode==='up'){if($before!==[])throw new RuntimeException('자산관리 테이블이 이미 존재합니다.');$sql=file_get_contents(PROJECT_ROOT.'/app/migrations/20260905_04_create_ledger_assets.up.sql');foreach(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',(string)$sql)?:[]))as$s)$db->exec($s);}
$after=array_values(array_filter($tables,fn($t)=>$exists($db,$t)));
$missing=(int)$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('ledger_assets','ledger_asset_assignments','ledger_asset_depreciations','ledger_asset_disposals') AND COALESCE(COLUMN_COMMENT,'')=''")->fetchColumn();
$triggers=(int)$db->query("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE IN ('ledger_assets','ledger_asset_assignments','ledger_asset_depreciations','ledger_asset_disposals')")->fetchColumn();
$ok=count($after)===4&&$missing===0&&$triggers===0;
echo json_encode(['mode'=>$mode,'tables'=>$after,'comment_missing'=>$missing,'triggers'=>$triggers,'ready'=>$ok],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
exit($ok?0:1);
