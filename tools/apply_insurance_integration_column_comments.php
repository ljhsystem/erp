<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Storage.php';
require PROJECT_ROOT . '/core/Database.php';

$db=Core\Database::getInstance()->getConnection();
if ((string)$db->query('SELECT DATABASE()')->fetchColumn()!=='sukhyang') throw new RuntimeException('운영 DB가 아닙니다.');
$executeSql=static function(PDO $connection,string $path):void{$delimiter=';';$buffer='';foreach(preg_split('/\r\n|\n|\r/',(string)file_get_contents($path))?:[] as $line){if(preg_match('/^DELIMITER\s+(.+)$/i',trim($line),$match)){$delimiter=$match[1];continue;}$buffer.=$line."\n";$trimmed=rtrim($buffer);if(!str_ends_with($trimmed,$delimiter))continue;$statement=trim(substr($trimmed,0,-strlen($delimiter)));if($statement!=='')$connection->exec($statement);$buffer='';}if(trim($buffer)!=='')throw new RuntimeException('Migration SQL 구분자가 닫히지 않았습니다.');};
$hash=static function(PDO $connection,string $sql):string{return hash('sha256',json_encode($connection->query($sql)->fetchAll(PDO::FETCH_ASSOC)?:[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION));};
$schemaFingerprint=static function(PDO $connection,string $table):string{$statement=$connection->prepare("SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,CHARACTER_SET_NAME,COLLATION_NAME,EXTRA,ORDINAL_POSITION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table ORDER BY ORDINAL_POSITION");$statement->execute(['table'=>$table]);return hash('sha256',json_encode($statement->fetchAll(PDO::FETCH_ASSOC)?:[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));};
$queries=[
 'standards'=>'SELECT * FROM system_statutory_standards ORDER BY id',
 'sources'=>'SELECT * FROM system_statutory_standard_sources ORDER BY id',
 'calculation_revisions'=>'SELECT * FROM institution_daily_employment_income_calculation_revisions ORDER BY id',
 'calculation_results'=>'SELECT * FROM institution_daily_employment_income_calculation_results ORDER BY id',
 'daily_headers'=>'SELECT * FROM institution_daily_employment_incomes ORDER BY id',
 'daily_groups'=>'SELECT * FROM institution_daily_employment_income_groups ORDER BY id',
 'daily_items'=>'SELECT * FROM institution_daily_employment_income_items ORDER BY id',
 'daily_workdays'=>'SELECT * FROM institution_daily_employment_income_workdays ORDER BY id',
 'daily_lines'=>'SELECT * FROM institution_daily_employment_income_lines ORDER BY id',
];
$capture=static function()use($db,$hash,$queries,$schemaFingerprint):array{$rows=[];foreach($queries as $key=>$sql)$rows[$key]=$hash($db,$sql);return['schema'=>['standards'=>$schemaFingerprint($db,'system_statutory_standards'),'results'=>$schemaFingerprint($db,'institution_daily_employment_income_calculation_results')],'rows'=>$rows];};
$before=$capture();
$executeSql($db,PROJECT_ROOT.'/app/migrations/20260831_05_add_insurance_integration_column_comments.up.sql');
$after=$capture();
if($before!==$after)throw new RuntimeException('COMMENT 외 Schema 또는 업무자료가 변경됐습니다.');
$comments=$db->query("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_COMMENT,ORDINAL_POSITION,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='system_statutory_standards' AND COLUMN_NAME IN('policy_component_code','employment_type_code','work_scope_code','additional_dimension_data','additional_dimension_key')) OR (TABLE_NAME='institution_daily_employment_income_calculation_results' AND COLUMN_NAME IN('daily_employment_income_item_id','eligibility_status_code','eligibility_reason_code','missing_inputs','snapshot_schema_version'))) ORDER BY TABLE_NAME,ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC)?:[];
if(count($comments)!==10||count(array_filter($comments,static fn(array $row):bool=>trim((string)$row['COLUMN_COMMENT'])===''))>0)throw new RuntimeException('운영 COMMENT 적용 결과가 다릅니다.');
echo json_encode(['success'=>true,'migration'=>'20260831_05_add_insurance_integration_column_comments.up.sql','before'=>$before,'after'=>$after,'comments'=>$comments,'business_dml_count'=>0],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
