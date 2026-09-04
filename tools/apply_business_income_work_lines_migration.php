<?php

declare(strict_types=1);

define('PROJECT_ROOT',dirname(__DIR__));
require PROJECT_ROOT.'/vendor/autoload.php';
require PROJECT_ROOT.'/core/DbPdo.php';

use Core\DbPdo;

if(($argv[1]??'')!=='--apply')throw new RuntimeException('운영 적용에는 --apply 인자가 필요합니다.');
$db=DbPdo::conn();
$execute=static function(PDO $connection,string $path):void{$delimiter=';';$buffer='';foreach(preg_split('/\r\n|\n|\r/',(string)file_get_contents($path))?:[] as $line){if(preg_match('/^DELIMITER\s+(.+)$/i',trim($line),$match)){$delimiter=$match[1];continue;}$buffer.=$line."\n";$trimmed=rtrim($buffer);if(!str_ends_with($trimmed,$delimiter))continue;$statement=trim(substr($trimmed,0,-strlen($delimiter)));if($statement!=='')$connection->exec($statement);$buffer='';}};
$before=['items'=>(int)$db->query('SELECT COUNT(*) FROM institution_business_income_items')->fetchColumn(),'evidence'=>(int)$db->query('SELECT COUNT(*) FROM ledger_evidence_business_income')->fetchColumn(),'triggers'=>(int)$db->query('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchColumn()];
$execute($db,PROJECT_ROOT.'/app/migrations/20260903_18_create_business_income_work_lines.up.sql');
$after=['items'=>(int)$db->query('SELECT COUNT(*) FROM institution_business_income_items')->fetchColumn(),'evidence'=>(int)$db->query('SELECT COUNT(*) FROM ledger_evidence_business_income')->fetchColumn(),'work_lines'=>(int)$db->query('SELECT COUNT(*) FROM institution_business_income_work_lines')->fetchColumn(),'evidence_work_lines'=>(int)$db->query('SELECT COUNT(*) FROM ledger_evidence_business_income_work_lines')->fetchColumn(),'triggers'=>(int)$db->query('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchColumn()];
if($before['items']!==$after['items']||$before['evidence']!==$after['evidence']||$before['triggers']!==$after['triggers'])throw new RuntimeException('사업소득 작업내역 운영 적용 검증에 실패했습니다.');
echo json_encode(['success'=>true,'before'=>$before,'after'=>$after],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
