<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$mode = strtolower((string)($argv[1] ?? 'verify'));
if (!in_array($mode, ['verify','up'], true)) throw new InvalidArgumentException('사용법: php tools/apply_regular_income_closure_separation.php [verify|up]');
$pdo=DbPdo::conn();
$migration='20260826_03_separate_regular_income_closure_from_payment';
$file=PROJECT_ROOT.'/app/migrations/'.$migration.'.up.sql';
$documentId='4d9f970e-c6c5-4a7c-88ff-8a5cfdb47fb6';
$requestId='e7f37bc9-82d7-4113-bb64-c5b01cf9e0f1';
$tables=['institution_regular_employment_incomes','institution_regular_employment_income_items','institution_regular_employment_income_line_items','user_approval_requests','user_approval_request_steps','ledger_evidence_salary_report','ledger_transactions','ledger_transaction_items','ledger_transaction_settlements','ledger_evidence_links','institution_regular_employment_income_accounting_links','ledger_payment_schedules','ledger_payment_schedule_histories','ledger_vouchers','ledger_voucher_lines'];
$counts=static function(PDO $pdo,array $tables):array{$result=[];foreach($tables as$table)$result[$table]=(int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();return$result;};
$before=$counts($pdo,$tables);
$scalar=static function(PDO $pdo,string$sql,array$params=[]):mixed{$statement=$pdo->prepare($sql);$statement->execute($params);return$statement->fetchColumn();};
$request=$pdo->prepare("SELECT r.status request_status,r.current_step,s.status step_status,s.acted_by,s.action_at,d.document_status FROM user_approval_requests r JOIN user_approval_request_steps s ON s.request_id=r.id AND s.sort_no=r.current_step JOIN institution_regular_employment_incomes d ON d.id=r.document_id WHERE r.id=:request_id AND d.id=:document_id");
$request->execute([':request_id'=>$requestId,':document_id'=>$documentId]);$requestState=$request->fetch(PDO::FETCH_ASSOC)?:[];
$guard=[
 'registry_rows'=>(int)$scalar($pdo,'SELECT COUNT(*) FROM institution_regular_employment_income_accounting_links'),
 'registry_schedule_rows'=>(int)$scalar($pdo,'SELECT COUNT(*) FROM institution_regular_employment_income_accounting_links WHERE payment_schedule_id IS NOT NULL'),
 'schedule_link_table'=>(int)$scalar($pdo,"SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_income_accounting_schedules'"),
 'schedule_link_rows'=>(int)$scalar($pdo,'SELECT COUNT(*) FROM institution_regular_income_accounting_schedules'),
 'payroll_schedules'=>(int)$scalar($pdo,"SELECT COUNT(*) FROM ledger_payment_schedules WHERE source_type='PAYROLL_REPORT'"),
 'document_evidence'=>(int)$scalar($pdo,'SELECT COUNT(*) FROM ledger_evidence_salary_report WHERE source_regular_employment_income_id=:id',[':id'=>$documentId]),
 'document_links'=>(int)$scalar($pdo,"SELECT COUNT(*) FROM ledger_evidence_links WHERE evidence_type='PAYROLL_REPORT' AND evidence_id IN (SELECT id FROM ledger_evidence_salary_report WHERE source_regular_employment_income_id=:id)",[':id'=>$documentId]),
];
$ready=$guard===['registry_rows'=>0,'registry_schedule_rows'=>0,'schedule_link_table'=>1,'schedule_link_rows'=>0,'payroll_schedules'=>0,'document_evidence'=>0,'document_links'=>0]
 && ($requestState['request_status']??null)==='pending'&&($requestState['step_status']??null)==='pending'&&($requestState['acted_by']??null)===null&&($requestState['action_at']??null)===null&&($requestState['document_status']??null)==='PENDING';
if(!$ready)throw new RuntimeException('운영 적용 Guard가 예상 기준선과 다릅니다: '.json_encode(['guard'=>$guard,'request'=>$requestState],JSON_UNESCAPED_UNICODE));
if($mode==='up'){
 $delimiter=';';$buffer='';foreach(preg_split('/\r\n|\n|\r/',(string)file_get_contents($file))as$line){if(preg_match('/^DELIMITER\s+(.+)$/i',trim($line),$match)){$delimiter=$match[1];continue;}$buffer.=$line."\n";$trimmed=rtrim($buffer,"\r\n");if(!str_ends_with($trimmed,$delimiter))continue;$sql=trim(substr($trimmed,0,-strlen($delimiter)));if($sql!=='')$pdo->exec($sql);$buffer='';}
}
$after=$counts($pdo,$tables);
$schema=[
 'schedule_link_table'=>(int)$scalar($pdo,"SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_income_accounting_schedules'"),
 'pending_code'=>(int)$scalar($pdo,"SELECT COUNT(*) FROM system_codes WHERE code_group='EVIDENCE_STATUS' AND code='CLASSIFICATION_PENDING' AND is_active=1"),
 'role_check'=>(string)$scalar($pdo,"SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_regular_income_accounting_role'"),
 'field_check'=>(string)$scalar($pdo,"SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_regular_income_accounting_role_fields'"),
];
if($mode==='up'&&($before!==$after||$schema['schedule_link_table']!==0||$schema['pending_code']!==1||!str_contains($schema['role_check'],'INSTITUTION_LIABILITY')||!str_contains(strtolower($schema['field_check']),'payment_schedule_id` is null')))throw new RuntimeException('운영 적용 후 스키마 또는 업무자료 무결성 검증에 실패했습니다.');
echo json_encode(['success'=>true,'mode'=>$mode,'migration'=>$migration,'database'=>(string)$pdo->query('SELECT DATABASE()')->fetchColumn(),'mariadb_version'=>(string)$pdo->query('SELECT VERSION()')->fetchColumn(),'central_migration_history'=>false,'guard'=>$guard,'request'=>$requestState,'before'=>$before,'after'=>$after,'business_counts_unchanged'=>$before===$after,'schema'=>$schema,'sql_sha256'=>hash_file('sha256',$file)],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
