<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

$mode = strtolower((string) ($argv[1] ?? 'verify'));
if (!in_array($mode, ['preflight','up','verify'], true)) throw new InvalidArgumentException('지원하지 않는 실행 모드입니다.');
$db = DbPdo::conn();
$tables = ['ledger_inventory_balances','ledger_inventory_balance_items'];
$exists = static function (PDO $db, string $table): bool {
    $stmt=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table');
    $stmt->execute([':table'=>$table]);return (int)$stmt->fetchColumn()>0;
};
$before=array_filter($tables,static fn(string $table):bool=>$exists($db,$table));
if($mode==='preflight'){
    $columns=$db->query("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,CHARACTER_SET_NAME,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='system_projects' AND COLUMN_NAME='id') OR (TABLE_NAME='system_codes' AND COLUMN_NAME='code') OR (TABLE_NAME='ledger_inventory_balances' AND COLUMN_NAME='id') OR (TABLE_NAME='ledger_inventory_balance_items' AND COLUMN_NAME IN ('project_id','business_unit'))) ORDER BY TABLE_NAME,COLUMN_NAME")->fetchAll(PDO::FETCH_ASSOC)?:[];
    echo json_encode(['mode'=>$mode,'existing_tables'=>array_values($before),'reference_columns'=>$columns,'ready'=>$before===[]],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;exit(0);
}
if($mode==='up'){
    if($before!==[] && $before!==['ledger_inventory_balances'])throw new RuntimeException('재고관리 Migration 대상 테이블이 이미 존재합니다.');
    if($before===['ledger_inventory_balances'] && (int)$db->query('SELECT COUNT(*) FROM ledger_inventory_balances')->fetchColumn()!==0)throw new RuntimeException('부분 생성된 재고관리 헤더에 데이터가 있어 자동 정정할 수 없습니다.');
    $sql=(string)file_get_contents(PROJECT_ROOT.'/app/migrations/20260905_02_create_ledger_inventory_balances.up.sql');
    foreach(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[])) as $statement){
        if(str_contains($statement,'CREATE TABLE `ledger_inventory_balances`')&&$exists($db,'ledger_inventory_balances'))continue;
        if(str_contains($statement,'CREATE TABLE `ledger_inventory_balance_items`')&&$exists($db,'ledger_inventory_balance_items'))continue;
        $db->exec($statement);
    }
}
$after=array_filter($tables,static fn(string $table):bool=>$exists($db,$table));
$comments=$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('ledger_inventory_balances','ledger_inventory_balance_items') AND COALESCE(COLUMN_COMMENT,'')='' ")->fetchColumn();
$triggers=$db->query("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE IN ('ledger_inventory_balances','ledger_inventory_balance_items')")->fetchColumn();
$result=['mode'=>$mode,'tables'=>array_values($after),'comment_missing'=>(int)$comments,'triggers'=>(int)$triggers,'ready'=>count($after)===2&&(int)$comments===0&&(int)$triggers===0];
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
exit($result['ready']?0:1);
