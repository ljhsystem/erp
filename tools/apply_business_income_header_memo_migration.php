<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

if (($argv[1] ?? '') !== '--apply') throw new RuntimeException('운영 적용에는 --apply 인자가 필요합니다.');
$db=DbPdo::conn();
$before=(int)$db->query('SELECT COUNT(*) FROM institution_business_incomes')->fetchColumn();
$delimiter=';';$buffer='';
foreach(preg_split('/\r\n|\n|\r/',(string)file_get_contents(PROJECT_ROOT.'/app/migrations/20260903_17_add_business_income_header_memo.up.sql'))?:[] as $line){
    if(preg_match('/^DELIMITER\s+(.+)$/i',trim($line),$match)){$delimiter=$match[1];continue;}
    $buffer.=$line."\n";$trimmed=rtrim($buffer);if(!str_ends_with($trimmed,$delimiter))continue;
    $statement=trim(substr($trimmed,0,-strlen($delimiter)));if($statement!=='')$db->exec($statement);$buffer='';
}
$after=(int)$db->query('SELECT COUNT(*) FROM institution_business_incomes')->fetchColumn();
$column=$db->query("SELECT DATA_TYPE,IS_NULLABLE,COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_business_incomes' AND COLUMN_NAME='memo'")->fetch(PDO::FETCH_ASSOC);
$triggers=(int)$db->query('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchColumn();
if($before!==$after||($column['DATA_TYPE']??'')!=='text'||($column['IS_NULLABLE']??'')!=='YES'||($column['COLUMN_COMMENT']??'')!=='메모')throw new RuntimeException('사업소득 메모 운영 적용 검증에 실패했습니다.');
echo json_encode(['success'=>true,'rows'=>['before'=>$before,'after'=>$after],'column'=>$column,'triggers'=>$triggers],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
