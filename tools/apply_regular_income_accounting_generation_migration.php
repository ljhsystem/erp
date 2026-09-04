<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

$mode=strtolower((string)($argv[1]??'preflight'));
if(!in_array($mode,['preflight','up','verify'],true))throw new InvalidArgumentException('사용법: php tools/apply_regular_income_accounting_generation_migration.php [preflight|up|verify]');
$db=DbPdo::conn();$db->setAttribute(PDO::ATTR_EMULATE_PREPARES,true);
$migration='20260825_04_extend_regular_income_accounting_generation_identity';
$tables=['ledger_transaction_items','ledger_transaction_settlements','institution_regular_employment_income_accounting_links','institution_regular_income_accounting_schedules'];
$dataTables=['institution_regular_employment_income_accounting_links','ledger_transactions','ledger_transaction_items','ledger_transaction_settlements','ledger_evidence_salary_report','ledger_payment_schedules'];
$counts=static function()use($db,$dataTables):array{$result=[];foreach($dataTables as$table)$result[$table]=(int)$db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();return$result;};
$schema=static function()use($db,$tables):array{$result=[];foreach($tables as$table){$exists=(int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=".$db->quote($table))->fetchColumn();if(!$exists){$result[$table]=null;continue;}$row=$db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);$result[$table]=$row[1]??null;}return$result;};
$columns=(int)$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='ledger_transaction_items' AND COLUMN_NAME IN ('regular_employment_income_line_item_id','statutory_standard_revision_id','calculation_basis_id')) OR (TABLE_NAME='ledger_transaction_settlements' AND COLUMN_NAME IN ('regular_employment_income_line_item_id','statutory_standard_revision_id','calculation_basis_id')) OR (TABLE_NAME='institution_regular_employment_income_accounting_links' AND COLUMN_NAME IN ('generation_role','aggregation_key','approval_request_id','attribution_month','recognition_date','payload_hash')))")->fetchColumn();
$scheduleTable=(int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_income_accounting_schedules'")->fetchColumn();
$linkCount=(int)$db->query('SELECT COUNT(*) FROM institution_regular_employment_income_accounting_links')->fetchColumn();
$baselineUk=(int)$db->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_income_accounting_links' AND INDEX_NAME='uk_regular_income_accounting_detail'")->fetchColumn();
$beforeCounts=$counts();$beforeSchema=$schema();
$ready=$linkCount===0&&$columns===0&&$scheduleTable===0&&$baselineUk===1;
$snapshotPath=null;
if($mode==='up'){
    if(!$ready)throw new RuntimeException('운영 Preflight가 통과하지 않아 Migration을 실행하지 않았습니다.');
    $directory=PROJECT_ROOT.'/storage/db_backup';if(!is_dir($directory))throw new RuntimeException('스키마 Snapshot 저장경로를 찾을 수 없습니다.');
    $snapshotPath=$directory.'/'.$migration.'_schema_before_'.date('Ymd_His').'.json';
    $snapshot=['migration'=>$migration,'database'=>(string)$db->query('SELECT DATABASE()')->fetchColumn(),'captured_at'=>date(DATE_ATOM),'counts'=>$beforeCounts,'schema'=>$beforeSchema];
    if(file_put_contents($snapshotPath,json_encode($snapshot,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n")===false)throw new RuntimeException('Migration 전 스키마 Snapshot 저장에 실패했습니다.');
    $delimiter=';';$buffer='';$file=PROJECT_ROOT.'/app/migrations/'.$migration.'.up.sql';
    foreach(preg_split('/\r\n|\n|\r/',(string)file_get_contents($file))as$line){if(preg_match('/^DELIMITER\s+(.+)$/i',trim($line),$match)){$delimiter=$match[1];continue;}$buffer.=$line."\n";$trimmed=rtrim($buffer,"\r\n");if(!str_ends_with($trimmed,$delimiter))continue;$statement=trim(substr($trimmed,0,-strlen($delimiter)));if($statement!=='')$db->exec($statement);$buffer='';}
    if(trim($buffer)!=='')throw new RuntimeException('Migration SQL 구분자가 닫히지 않았습니다.');
}
$afterCounts=$counts();$afterSchema=$schema();
$actual=[
    'item_columns'=>(int)$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_transaction_items' AND COLUMN_NAME IN ('regular_employment_income_line_item_id','statutory_standard_revision_id','calculation_basis_id')")->fetchColumn(),
    'settlement_columns'=>(int)$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_transaction_settlements' AND COLUMN_NAME IN ('regular_employment_income_line_item_id','statutory_standard_revision_id','calculation_basis_id')")->fetchColumn(),
    'registry_columns'=>(int)$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_income_accounting_links' AND COLUMN_NAME IN ('generation_role','aggregation_key','approval_request_id','attribution_month','recognition_date','payload_hash')")->fetchColumn(),
    'schedule_table'=>(int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_income_accounting_schedules'")->fetchColumn(),
    'identity_uk'=>(int)$db->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_income_accounting_links' AND INDEX_NAME='uk_regular_income_accounting_identity'")->fetchColumn(),
    'foreign_keys'=>(int)$db->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND (CONSTRAINT_NAME LIKE 'fk_transaction_item_%' OR CONSTRAINT_NAME LIKE 'fk_transaction_settlement_%' OR CONSTRAINT_NAME LIKE 'fk_regular_income_accounting_%')")->fetchColumn(),
    'checks'=>(int)$db->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_TYPE='CHECK' AND CONSTRAINT_NAME IN ('chk_regular_income_accounting_role','chk_regular_income_accounting_month','chk_regular_income_accounting_payload_hash','chk_regular_income_accounting_role_fields','chk_regular_income_accounting_schedule_role')")->fetchColumn(),
];
$fkColumnCollations=$db->query("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,CHARACTER_SET_NAME,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='user_approval_requests' AND COLUMN_NAME='id') OR (TABLE_NAME='institution_regular_employment_income_accounting_links' AND COLUMN_NAME IN ('id','regular_employment_income_item_id','evidence_id','transaction_id','payment_schedule_id'))) ORDER BY TABLE_NAME,COLUMN_NAME")->fetchAll(PDO::FETCH_ASSOC)?:[];
$innodbStatus=(string)($db->query('SHOW ENGINE INNODB STATUS')->fetch(PDO::FETCH_ASSOC)['Status']??'');$foreignKeyError='';if(preg_match('/LATEST FOREIGN KEY ERROR\s*-+\s*(.*?)(?:\n-+\n|$)/s',$innodbStatus,$match))$foreignKeyError=trim($match[1]);
$applied=$actual===['item_columns'=>3,'settlement_columns'=>3,'registry_columns'=>6,'schedule_table'=>1,'identity_uk'=>1,'foreign_keys'=>9,'checks'=>5];
if(in_array($mode,['up','verify'],true)&&(!$applied||$beforeCounts!==$afterCounts))throw new RuntimeException('Migration 적용 후 스키마 또는 운영 데이터 건수 검증에 실패했습니다.');
echo json_encode(['success'=>true,'mode'=>$mode,'migration'=>$migration,'preflight'=>['accounting_link_count'=>$linkCount,'new_column_trace_count'=>$columns,'new_schedule_table_count'=>$scheduleTable,'baseline_uk_count'=>$baselineUk,'ready'=>$ready],'snapshot_path'=>$snapshotPath,'before_counts'=>$beforeCounts,'after_counts'=>$afterCounts,'data_counts_unchanged'=>$beforeCounts===$afterCounts,'actual_schema'=>$actual,'fk_column_collations'=>$fkColumnCollations,'latest_foreign_key_error'=>$foreignKeyError,'applied'=>$applied,'schema'=>$afterSchema],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
