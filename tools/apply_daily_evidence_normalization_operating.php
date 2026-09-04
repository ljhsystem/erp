<?php
declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

if (($argv[1] ?? '') !== 'apply') throw new RuntimeException('APPLY_MODE_REQUIRED');
$cutoff = new DateTimeImmutable('2026-09-02 15:00:00', new DateTimeZone('Asia/Seoul'));
$now = static fn(): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul'));
$assertTime = static function() use ($now,$cutoff): void { if($now() >= $cutoff) throw new RuntimeException('TIME_LIMIT_REACHED'); };
$db=DbPdo::conn();
if((string)$db->query('SELECT DATABASE()')->fetchColumn()!=='sukhyang')throw new RuntimeException('OPERATING_SCHEMA_MISMATCH');
$assertTime();
$backup=PROJECT_ROOT.'/storage/db_backup/sukhyang_2026-09-02_131015.sql';
if(!is_file($backup)||!is_readable($backup)||(int)filesize($backup)!==16451530)throw new RuntimeException('OPERATING_BACKUP_NOT_CONFIRMED');

$scalar=static function(string $sql,array $params=[])use($db):mixed{$s=$db->prepare($sql);$s->execute($params);return$s->fetchColumn();};
$rows=static function(string $sql,array $params=[])use($db):array{$s=$db->prepare($sql);$s->execute($params);return$s->fetchAll(PDO::FETCH_ASSOC)?:[];};
$digest=static fn(array $data):string=>hash('sha256',json_encode($data,JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION));
$executeFile=static function(string $file)use($db,$assertTime):void{
 $assertTime();$delimiter=';';$buffer='';$sql=(string)file_get_contents(PROJECT_ROOT.'/app/migrations/'.$file);
 foreach(preg_split('/\r\n|\n|\r/',$sql)?:[]as$line){
  if(preg_match('/^DELIMITER\s+(.+)$/i',trim($line),$m)){$delimiter=$m[1];continue;}
  $buffer.=$line."\n";$trimmed=rtrim($buffer);if(!str_ends_with($trimmed,$delimiter))continue;
  $statement=trim(substr($trimmed,0,-strlen($delimiter)));if($statement!==''){$assertTime();$db->exec($statement);}$buffer='';
 }
 if(trim($buffer)!=='')throw new RuntimeException('MIGRATION_SQL_NOT_CLOSED:'.$file);
};

$evidence=$rows('SELECT id,source_daily_employment_income_id,daily_employment_income_item_id,daily_employment_income_group_id,approval_request_id,calculation_revision_id,source_hash,snapshot_json,total_work_days,total_gross_amount,total_deduction_amount,total_net_payment_amount,total_employer_burden_amount,evidence_status_code FROM ledger_evidence_daily_employment_income ORDER BY id');
if(count($evidence)!==1)throw new RuntimeException('EVIDENCE_COUNT_MISMATCH');
$documentId=(string)$evidence[0]['source_daily_employment_income_id'];$itemId=(string)$evidence[0]['daily_employment_income_item_id'];
$immutableSql=[
 'header'=>'SELECT * FROM institution_daily_employment_incomes WHERE id=:id',
 'groups'=>'SELECT * FROM institution_daily_employment_income_groups WHERE daily_employment_income_id=:id ORDER BY id',
 'items'=>'SELECT i.* FROM institution_daily_employment_income_items i JOIN institution_daily_employment_income_groups g ON g.id=i.daily_employment_income_group_id WHERE g.daily_employment_income_id=:id ORDER BY i.id',
 'workdays'=>'SELECT w.* FROM institution_daily_employment_income_workdays w JOIN institution_daily_employment_income_items i ON i.id=w.daily_employment_income_item_id JOIN institution_daily_employment_income_groups g ON g.id=i.daily_employment_income_group_id WHERE g.daily_employment_income_id=:id ORDER BY w.id',
 'lines'=>'SELECT l.* FROM institution_daily_employment_income_lines l JOIN institution_daily_employment_income_items i ON i.id=l.daily_employment_income_item_id JOIN institution_daily_employment_income_groups g ON g.id=i.daily_employment_income_group_id WHERE g.daily_employment_income_id=:id ORDER BY l.id',
 'revisions'=>'SELECT * FROM institution_daily_employment_income_calculation_revisions WHERE daily_employment_income_id=:id ORDER BY id',
 'results'=>'SELECT r.* FROM institution_daily_employment_income_calculation_results r JOIN institution_daily_employment_income_calculation_revisions v ON v.id=r.calculation_revision_id WHERE v.daily_employment_income_id=:id ORDER BY r.id',
 'requests'=>'SELECT * FROM user_approval_requests WHERE document_type=\'DAILY_EMPLOYMENT_INCOME\' AND document_id=:id ORDER BY id',
 'steps'=>'SELECT s.* FROM user_approval_request_steps s JOIN user_approval_requests r ON r.id=s.request_id WHERE r.document_type=\'DAILY_EMPLOYMENT_INCOME\' AND r.document_id=:id ORDER BY s.id',
];
$before=[];foreach($immutableSql as$key=>$sql)$before[$key]=$digest($rows($sql,[':id'=>$documentId]));
$before['evidence']=$digest($evidence);
$before['transaction']=$digest($rows("SELECT t.* FROM ledger_transactions t JOIN ledger_evidence_links l ON l.target_type='TRANSACTION' AND l.target_id=t.id AND l.deleted_at IS NULL WHERE l.evidence_type='DAILY_EMPLOYMENT_INCOME' ORDER BY t.id"));
$before['items']=$digest($rows("SELECT i.* FROM ledger_transaction_items i JOIN ledger_evidence_links l ON l.target_type='TRANSACTION' AND l.target_id=i.transaction_id AND l.deleted_at IS NULL WHERE l.evidence_type='DAILY_EMPLOYMENT_INCOME' ORDER BY i.id"));
$before['settlements']=$digest($rows("SELECT s.* FROM ledger_transaction_settlements s JOIN ledger_evidence_links l ON l.target_type='TRANSACTION' AND l.target_id=s.transaction_id AND l.deleted_at IS NULL WHERE l.evidence_type='DAILY_EMPLOYMENT_INCOME' ORDER BY s.id"));
$before['links']=$digest($rows("SELECT * FROM ledger_evidence_links WHERE evidence_type='DAILY_EMPLOYMENT_INCOME' ORDER BY id"));
$before['registry']=$digest($rows('SELECT * FROM institution_daily_employment_income_accounting_links WHERE daily_employment_income_id=:id ORDER BY id',[':id'=>$documentId]));
$before['closure']=$digest($rows('SELECT * FROM institution_daily_employment_income_closures WHERE daily_employment_income_id=:id ORDER BY id',[':id'=>$documentId]));
$settingsBefore=(int)$scalar("SELECT COUNT(*) FROM system_user_settings WHERE setting_type='TABLE' AND page_key='evidence-daily-employment-income' AND JSON_VALID(settings_json)=1 AND settings_json LIKE '%total_gross_amount%'");
$settingsApplied=(int)$scalar("SELECT COUNT(*) FROM system_user_settings WHERE setting_type='TABLE' AND page_key='evidence-daily-employment-income' AND JSON_VALID(settings_json)=1 AND settings_json LIKE '%raw_gross_payment_amount%'");
if(($settingsBefore===1&&$settingsApplied!==0)||($settingsBefore===0&&$settingsApplied!==1))throw new RuntimeException('TABLE_SETTINGS_BASELINE_MISMATCH');
$pending=(int)$scalar("SELECT COUNT(*) FROM user_approval_request_steps s JOIN user_approval_requests r ON r.id=s.request_id WHERE r.document_type='DAILY_EMPLOYMENT_INCOME' AND r.is_active=1 AND r.status IN ('pending','in_progress') AND s.is_active=1 AND s.step_type='FINAL_APPROVAL' AND s.status IN ('waiting','pending')");
if($pending!==0)throw new RuntimeException('FINAL_APPROVAL_IN_PROGRESS');

$trigger='trg_daily_evidence_migration_final_approval_block';
$triggerExists=(int)$scalar('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME=:name',[':name'=>$trigger])===1;
if(!$triggerExists)$db->exec("CREATE TRIGGER {$trigger} BEFORE UPDATE ON user_approval_request_steps FOR EACH ROW BEGIN IF NEW.status='approved' AND OLD.status<>'approved' AND NEW.step_type='FINAL_APPROVAL' AND EXISTS(SELECT 1 FROM user_approval_requests r WHERE r.id=NEW.request_id AND r.document_type='DAILY_EMPLOYMENT_INCOME') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='DAILY_EVIDENCE_MIGRATION_IN_PROGRESS'; END IF; END");
$report=['runtime'=>'DIRECT_RUNTIME_PATH','backup'=>true,'approval_blocked'=>true,'stages'=>[]];
try{
 $files=[
  '_01'=>'20260901_01_normalize_daily_employment_income_evidence.up.sql','_02'=>'20260901_02_backfill_daily_employment_income_evidence_raw.up.sql','_03'=>'20260901_03_add_daily_employment_income_evidence_raw_checks.up.sql','_04'=>'20260901_04_migrate_daily_evidence_table_settings_keys.up.sql','_05'=>'20260901_05_create_daily_employment_income_evidence_raw_lines.up.sql','_06'=>'20260901_06_backfill_daily_employment_income_evidence_raw_lines.up.sql'];
 foreach($files as$stage=>$file){
  $executeFile($file);
  $report['stages'][$stage]='APPLIED';
  if($stage==='_01'&&(int)$scalar("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_daily_employment_income' AND COLUMN_NAME IN ('business_unit','transaction_direction','operation_type','raw_income_year_month','raw_payment_date','raw_work_day_count','raw_gross_payment_amount','raw_worker_deduction_amount','raw_net_payment_amount','raw_employer_burden_amount','evidence_status')")!==11)throw new RuntimeException('_01_RECONCILIATION_FAILED');
  if($stage==='_02'&&(int)$scalar("SELECT COUNT(*) FROM ledger_evidence_daily_employment_income WHERE business_unit IS NULL OR raw_gross_payment_amount<>452940 OR raw_worker_deduction_amount<>2940 OR raw_net_payment_amount<>450000 OR raw_employer_burden_amount<>20820 OR ROUND(raw_gross_payment_amount-raw_worker_deduction_amount,2)<>raw_net_payment_amount")!==0)throw new RuntimeException('_02_RECONCILIATION_FAILED');
  if($stage==='_03'&&(int)$scalar("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_daily_employment_income' AND CONSTRAINT_NAME IN ('ck_daily_evidence_raw_non_negative','ck_daily_evidence_raw_amounts','ck_daily_evidence_raw_period','ck_daily_evidence_business_classification','ck_daily_evidence_review_status')")!==5)throw new RuntimeException('_03_RECONCILIATION_FAILED');
  if($stage==='_04'&&(int)$scalar("SELECT COUNT(*) FROM system_user_settings WHERE setting_type='TABLE' AND page_key='evidence-daily-employment-income' AND JSON_VALID(settings_json)=1 AND (settings_json LIKE '%total_gross_amount%' OR settings_json LIKE '%evidence_status_code%')")!==0)throw new RuntimeException('_04_RECONCILIATION_FAILED');
  if($stage==='_05'&&(!in_array((int)$scalar('SELECT COUNT(*) FROM ledger_evidence_daily_employment_income_lines'),[0,35],true)||(int)$scalar("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_daily_employment_income_lines' AND CONSTRAINT_TYPE='FOREIGN KEY'")!==6))throw new RuntimeException('_05_RECONCILIATION_FAILED');
  if($stage==='_06'&&(int)$scalar('SELECT COUNT(*) FROM ledger_evidence_daily_employment_income_lines')!==35)throw new RuntimeException('_06_RECONCILIATION_FAILED');
 }
 $totals=$rows("SELECT line_type_code,ROUND(SUM(raw_final_amount),2) total FROM ledger_evidence_daily_employment_income_lines WHERE line_type_code='PAY' OR application_status_code='APPLICABLE' GROUP BY line_type_code");$map=[];foreach($totals as$row)$map[$row['line_type_code']]=(float)$row['total'];
 if(($map['PAY']??0)!==452940.0||($map['DEDUCTION']??0)!==2940.0||($map['EMPLOYER_BURDEN']??0)!==20820.0)throw new RuntimeException('RAW_LINE_TOTAL_MISMATCH');
 $after=[];foreach($immutableSql as$key=>$sql)$after[$key]=$digest($rows($sql,[':id'=>$documentId]));
 $afterEvidence=$rows('SELECT id,source_daily_employment_income_id,daily_employment_income_item_id,daily_employment_income_group_id,approval_request_id,calculation_revision_id,source_hash,snapshot_json,total_work_days,total_gross_amount,total_deduction_amount,total_net_payment_amount,total_employer_burden_amount,evidence_status_code FROM ledger_evidence_daily_employment_income ORDER BY id');$after['evidence']=$digest($afterEvidence);
 $after['transaction']=$digest($rows("SELECT t.* FROM ledger_transactions t JOIN ledger_evidence_links l ON l.target_type='TRANSACTION' AND l.target_id=t.id AND l.deleted_at IS NULL WHERE l.evidence_type='DAILY_EMPLOYMENT_INCOME' ORDER BY t.id"));
 $after['items']=$digest($rows("SELECT i.* FROM ledger_transaction_items i JOIN ledger_evidence_links l ON l.target_type='TRANSACTION' AND l.target_id=i.transaction_id AND l.deleted_at IS NULL WHERE l.evidence_type='DAILY_EMPLOYMENT_INCOME' ORDER BY i.id"));
 $after['settlements']=$digest($rows("SELECT s.* FROM ledger_transaction_settlements s JOIN ledger_evidence_links l ON l.target_type='TRANSACTION' AND l.target_id=s.transaction_id AND l.deleted_at IS NULL WHERE l.evidence_type='DAILY_EMPLOYMENT_INCOME' ORDER BY s.id"));
 $after['links']=$digest($rows("SELECT * FROM ledger_evidence_links WHERE evidence_type='DAILY_EMPLOYMENT_INCOME' ORDER BY id"));$after['registry']=$digest($rows('SELECT * FROM institution_daily_employment_income_accounting_links WHERE daily_employment_income_id=:id ORDER BY id',[':id'=>$documentId]));$after['closure']=$digest($rows('SELECT * FROM institution_daily_employment_income_closures WHERE daily_employment_income_id=:id ORDER BY id',[':id'=>$documentId]));
 if($before!==$after)throw new RuntimeException('IMMUTABLE_DATA_CHANGED');
 $db->exec("DROP TRIGGER {$trigger}");$report+=['success'=>true,'approval_resumed'=>true,'evidence_count'=>(int)$scalar('SELECT COUNT(*) FROM ledger_evidence_daily_employment_income'),'raw_line_count'=>(int)$scalar('SELECT COUNT(*) FROM ledger_evidence_daily_employment_income_lines'),'checks'=>(int)$scalar("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_daily_employment_income' AND CONSTRAINT_NAME IN ('ck_daily_evidence_raw_non_negative','ck_daily_evidence_raw_amounts','ck_daily_evidence_raw_period','ck_daily_evidence_business_classification','ck_daily_evidence_review_status')"),'foreign_keys'=>(int)$scalar("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_daily_employment_income_lines' AND CONSTRAINT_TYPE='FOREIGN KEY'"),'table_settings_changed'=>1];
}catch(Throwable$e){$report+=['success'=>false,'error_code'=>$e->getMessage(),'approval_resumed'=>false];}
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),PHP_EOL;
exit(!empty($report['success'])?0:1);
