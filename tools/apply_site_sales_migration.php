<?php
declare(strict_types=1);
define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT.'/core/Database.php';
require PROJECT_ROOT.'/core/DbPdo.php';
use Core\DbPdo;
$mode=strtolower((string)($argv[1]??'verify'));
if(!in_array($mode,['preflight','up','sync-permissions','verify'],true))throw new InvalidArgumentException('사용법: php tools/apply_site_sales_migration.php [preflight|up|sync-permissions|verify]');
$db=DbPdo::conn();
$tables=['site_sales_organizations','site_sales_people','site_sales_affiliations','site_sales_business_cards','site_sales_opportunities','site_sales_activities','site_sales_followups'];
$existing=static function()use($db,$tables):array{$found=[];foreach($tables as$table){$stmt=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$stmt->execute([$table]);if((int)$stmt->fetchColumn()===1)$found[]=$table;}return$found;};
if($mode==='preflight'){echo json_encode(['existing'=>$existing(),'ready'=>$existing()===[]],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;exit($existing()===[]?0:1);}
if($mode==='up'){$sql=(string)file_get_contents(PROJECT_ROOT.'/app/migrations/20260905_12_create_site_sales_management.up.sql');foreach(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[]))as$statement)$db->exec($statement);}
if($mode==='sync-permissions'){require PROJECT_ROOT.'/vendor/autoload.php';require_once PROJECT_ROOT.'/core/Storage.php';$router=new \Core\Router();ob_start();require PROJECT_ROOT.'/routes/web.php';require PROJECT_ROOT.'/routes/api.php';ob_end_clean();\Core\PermissionRegistry::syncToDatabase($db);}
$missing=(int)$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'site_sales_%' AND COALESCE(COLUMN_COMMENT,'')='' ")->fetchColumn();
$triggers=(int)$db->query("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE LIKE 'site_sales_%'")->fetchColumn();
$ready=count($existing())===count($tables)&&$missing===0&&$triggers===0;
echo json_encode(['tables'=>$existing(),'comment_missing'=>$missing,'triggers'=>$triggers,'ready'=>$ready],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;exit($ready?0:1);
