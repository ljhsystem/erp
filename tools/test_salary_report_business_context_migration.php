<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

$db=DbPdo::conn();$db->setAttribute(PDO::ATTR_EMULATE_PREPARES,true);
$source=(string)$db->query('SELECT DATABASE()')->fetchColumn();
$test='codex_salary_context_'.bin2hex(random_bytes(4));
$execute=static function(PDO$db,string$file):void{$delimiter=';';$buffer='';foreach(preg_split('/\r\n|\n|\r/',(string)file_get_contents($file))as$line){if(preg_match('/^DELIMITER\s+(.+)$/i',trim($line),$m)){$delimiter=$m[1];continue;}$buffer.=$line."\n";$trimmed=rtrim($buffer,"\r\n");if(!str_ends_with($trimmed,$delimiter))continue;$statement=trim(substr($trimmed,0,-strlen($delimiter)));if($statement!=='')$db->exec($statement);$buffer='';}if(trim($buffer)!=='')throw new RuntimeException('Migration SQL 구분자가 닫히지 않았습니다.');};
$up=PROJECT_ROOT.'/app/migrations/20260825_06_add_salary_report_business_context.up.sql';
$down=PROJECT_ROOT.'/app/migrations/20260825_06_add_salary_report_business_context.down.sql';
$before=(int)$db->query('SELECT COUNT(*) FROM ledger_evidence_salary_report')->fetchColumn();
$db->exec("CREATE DATABASE `{$test}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
try{$db->exec('SET FOREIGN_KEY_CHECKS=0');$row=$db->query('SHOW CREATE TABLE ledger_evidence_salary_report')->fetch(PDO::FETCH_NUM);$db->exec("USE `{$test}`");$db->exec((string)($row[1]??''));$db->exec('SET FOREIGN_KEY_CHECKS=1');
    $execute($db,$up);$columns=$db->query("SELECT COLUMN_NAME,IS_NULLABLE,ORDINAL_POSITION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_salary_report' AND COLUMN_NAME IN ('client_id','employee_id','project_id','bank_account_id','card_id','team_id') ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC)?:[];
    $names=array_column($columns,'COLUMN_NAME');if($names!==['client_id','employee_id','project_id','bank_account_id','card_id','team_id']||array_unique(array_column($columns,'IS_NULLABLE'))!==['YES'])throw new RuntimeException('급여 업무 Context 컬럼 구조가 승인 계약과 다릅니다.');
    $duplicateBlocked=false;try{$execute($db,$up);}catch(PDOException$e){$duplicateBlocked=$e->getCode()==='45000';}if(!$duplicateBlocked)throw new RuntimeException('Migration 중복 적용 차단에 실패했습니다.');
    $execute($db,$down);$remaining=(int)$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_salary_report' AND COLUMN_NAME IN ('client_id','employee_id','project_id','bank_account_id','card_id','team_id')")->fetchColumn();if($remaining!==0)throw new RuntimeException('Down 후 Context 컬럼이 남았습니다.');$execute($db,$up);
    $db->exec("USE `{$source}`");$after=(int)$db->query('SELECT COUNT(*) FROM ledger_evidence_salary_report')->fetchColumn();if($before!==$after)throw new RuntimeException('격리 검증 중 운영 급여 증빙 건수가 변경됐습니다.');
    echo json_encode(['success'=>true,'columns'=>$names,'all_nullable'=>true,'duplicate_up_blocked'=>true,'empty_down_and_reup'=>'PASS','operating_count_unchanged'=>true],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
}finally{$db->exec("USE `{$source}`");$db->exec('SET FOREIGN_KEY_CHECKS=0');$db->exec("DROP DATABASE IF EXISTS `{$test}`");$db->exec('SET FOREIGN_KEY_CHECKS=1');}
