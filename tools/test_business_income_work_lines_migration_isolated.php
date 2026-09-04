<?php

declare(strict_types=1);

define('PROJECT_ROOT',dirname(__DIR__));
require PROJECT_ROOT.'/vendor/autoload.php';
require PROJECT_ROOT.'/core/DbPdo.php';

use Core\DbPdo;

$db=DbPdo::conn();$source=(string)$db->query('SELECT DATABASE()')->fetchColumn();$fixture='sukhyang_business_work_fixture_'.date('YmdHis').'_'.random_int(1000,9999);
$execute=static function(PDO $connection,string $path):void{$delimiter=';';$buffer='';foreach(preg_split('/\r\n|\n|\r/',(string)file_get_contents($path))?:[] as $line){if(preg_match('/^DELIMITER\s+(.+)$/i',trim($line),$match)){$delimiter=$match[1];continue;}$buffer.=$line."\n";$trimmed=rtrim($buffer);if(!str_ends_with($trimmed,$delimiter))continue;$statement=trim(substr($trimmed,0,-strlen($delimiter)));if($statement!=='')$connection->exec($statement);$buffer='';}};
try{$db->exec("CREATE DATABASE `$fixture` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");foreach(['institution_business_income_items','ledger_evidence_business_income'] as $table)$db->exec("CREATE TABLE `$fixture`.`$table` LIKE `$source`.`$table`");$db->exec("USE `$fixture`");$execute($db,PROJECT_ROOT.'/app/migrations/20260903_18_create_business_income_work_lines.up.sql');$tables=(int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('institution_business_income_work_lines','ledger_evidence_business_income_work_lines')")->fetchColumn();$columns=(int)$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='institution_business_income_items' AND COLUMN_NAME='other_deduction_reason') OR (TABLE_NAME='ledger_evidence_business_income' AND COLUMN_NAME='raw_other_deduction_reason'))")->fetchColumn();if($tables!==2||$columns!==2)throw new RuntimeException('사업소득 작업내역 Migration 격리검증에 실패했습니다.');echo json_encode(['success'=>true,'mariadb_version'=>$db->query('SELECT VERSION()')->fetchColumn(),'tables'=>$tables,'columns'=>$columns,'fixture_removed'=>true],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;}finally{try{$db->exec("USE `$source`");$db->exec("DROP DATABASE IF EXISTS `$fixture`");}catch(Throwable){}}
