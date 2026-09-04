<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use App\Services\System\StatutoryStandardResolver;
use Core\DbPdo;

$db=DbPdo::conn();$source=(string)$db->query('SELECT DATABASE()')->fetchColumn();$fixture='sukhyang_business_income_fixture_'.date('YmdHis').'_'.random_int(1000,9999);
if(!preg_match('/^sukhyang_business_income_fixture_\d{14}_\d{4}$/',$fixture))throw new RuntimeException('격리 DB 이름 검증에 실패했습니다.');
$files=array_map(static fn(int $number):string=>(string)(glob(PROJECT_ROOT.'/app/migrations/20260903_'.str_pad((string)$number,2,'0',STR_PAD_LEFT).'_*.up.sql')[0]??''),range(1,9));
$execute=function(PDO $connection,string $path):void{$delimiter=';';$buffer='';foreach(preg_split('/\r\n|\n|\r/',(string)file_get_contents($path))?:[]as$line){if(preg_match('/^DELIMITER\s+(.+)$/i',trim($line),$match)){$delimiter=$match[1];continue;}$buffer.=$line."\n";$trimmed=rtrim($buffer);if(!str_ends_with($trimmed,$delimiter))continue;$statement=trim(substr($trimmed,0,-strlen($delimiter)));if($statement!=='')$connection->exec($statement);$buffer='';}};
try{
    $db->exec("CREATE DATABASE `$fixture` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $base=['system_codes','system_clients','system_projects','system_work_teams','system_page_registry','system_user_settings','auth_permissions','auth_roles','auth_role_permissions','user_approval_templates','user_approval_template_steps','user_approval_requests','ledger_evidence_metadata','ledger_transactions','ledger_transaction_items','ledger_transaction_settlements','ledger_evidence_links','system_statutory_standards','system_statutory_standard_sources','system_statutory_standard_supersessions'];
    $structureOnly=['user_approval_requests','ledger_transactions','ledger_transaction_items','ledger_transaction_settlements','ledger_evidence_links'];
    foreach($base as$table){$exists=(int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=".$db->quote($source)." AND TABLE_NAME=".$db->quote($table))->fetchColumn();if(!$exists)continue;$db->exec("CREATE TABLE `$fixture`.`$table` LIKE `$source`.`$table`");if(!in_array($table,$structureOnly,true))$db->exec("INSERT INTO `$fixture`.`$table` SELECT * FROM `$source`.`$table`");}
    $db->exec("USE `$fixture`");
    $resolver=new StatutoryStandardResolver($db);foreach(['2013-01-01','2024-06-30','2024-07-01',date('Y-m-d')]as$date)$resolver->resolve('BUSINESS_INCOME_WITHHOLDING',$date);
    $db->exec('SET @business_income_statutory_leaf_preflight_passed=1');$applied=[];foreach($files as$file){$execute($db,$file);$applied[]=basename($file);}
    $tables=(int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'institution_business_income%' OR TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN('system_client_tax_profiles','ledger_evidence_business_income','ledger_evidence_business_income_raw_lines')")->fetchColumn();
    $replayBlocked=false;try{$execute($db,$files[0]);}catch(Throwable){$replayBlocked=true;}
    echo json_encode(['success'=>true,'database'=>$fixture,'mariadb_version'=>$db->query('SELECT VERSION()')->fetchColumn(),'applied'=>$applied,'business_tables'=>$tables,'replay_blocked'=>$replayBlocked,'fixture_removed'=>true],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
}finally{
    try{$db->exec("USE `$source`");$db->exec("DROP DATABASE IF EXISTS `$fixture`");}catch(Throwable){}
}
