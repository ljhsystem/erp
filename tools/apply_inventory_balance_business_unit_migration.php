<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT.'/core/Database.php';
require PROJECT_ROOT.'/core/DbPdo.php';
use Core\DbPdo;

$mode=strtolower((string)($argv[1]??'verify'));if(!in_array($mode,['preflight','up','verify'],true))throw new InvalidArgumentException('지원하지 않는 실행 모드입니다.');
$db=DbPdo::conn();
$column=static function(PDO $db):array{$stmt=$db->query("SELECT COLUMN_TYPE,CHARACTER_SET_NAME,COLLATION_NAME,COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_inventory_balance_items' AND COLUMN_NAME='business_unit'");return $stmt->fetch(PDO::FETCH_ASSOC)?:[];};
$before=$column($db);$target=($before['COLUMN_TYPE']??'')==='varchar(50)'&&($before['COLLATION_NAME']??'')==='utf8mb4_general_ci';
if($mode==='preflight'){echo json_encode(['mode'=>$mode,'before'=>$before,'needs_migration'=>!$target],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;exit(0);}
if($mode==='up'){if((int)$db->query('SELECT COUNT(*) FROM ledger_inventory_balance_items')->fetchColumn()!==0)throw new RuntimeException('재고행 데이터가 있어 타입 정합성 Migration을 자동 적용할 수 없습니다.');if(!$target)$db->exec((string)file_get_contents(PROJECT_ROOT.'/app/migrations/20260905_03_align_inventory_business_unit_ssot.up.sql'));}
$after=$column($db);$passed=($after['COLUMN_TYPE']??'')==='varchar(50)'&&($after['COLLATION_NAME']??'')==='utf8mb4_general_ci'&&trim((string)($after['COLUMN_COMMENT']??''))!=='';echo json_encode(['mode'=>$mode,'after'=>$after,'passed'=>$passed],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;exit($passed?0:1);
