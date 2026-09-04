<?php

declare(strict_types=1);

define('PROJECT_ROOT',dirname(__DIR__));
require PROJECT_ROOT.'/vendor/autoload.php';
require PROJECT_ROOT.'/core/DbPdo.php';

use Core\DbPdo;

if(($argv[1]??'')!=='--apply')throw new RuntimeException('운영 적용에는 --apply 인자가 필요합니다.');
$db=DbPdo::conn();$before=(int)$db->query('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchColumn();
$sql=(string)file_get_contents(PROJECT_ROOT.'/app/migrations/20260903_19_complete_business_income_work_line_comments.up.sql');
foreach(array_filter(array_map('trim',explode(';',$sql))) as $statement)$db->exec($statement);
$missing=(int)$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('institution_business_income_work_lines','ledger_evidence_business_income_work_lines') AND TRIM(COLUMN_COMMENT)='' ")->fetchColumn();
$after=(int)$db->query('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchColumn();
if($missing!==0||$before!==$after)throw new RuntimeException('사업소득 작업내역 Comment 적용 검증에 실패했습니다.');
echo json_encode(['success'=>true,'missing_comments'=>$missing,'triggers'=>['before'=>$before,'after'=>$after]],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
