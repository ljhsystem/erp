<?php
declare(strict_types=1);
define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';
use Core\DbPdo;

$db=DbPdo::conn();
$count=static function(PDO $db,string $sql,array $params=[]):int{$s=$db->prepare($sql);$s->execute($params);return(int)$s->fetchColumn();};
$list=static function(PDO $db,string $sql):array{$s=$db->query($sql);return array_map('strval',$s->fetchAll(PDO::FETCH_COLUMN)?:[]);};
$exists=static fn(string $table):bool=>$count($db,'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table',[':table'=>$table])===1;
$database=(string)$db->query('SELECT DATABASE()')->fetchColumn();
if($database!=='sukhyang')throw new RuntimeException('OPERATING_SCHEMA_MISMATCH');

$columns=['business_unit','transaction_direction','operation_type','raw_income_year_month','raw_payment_date','raw_work_day_count','raw_gross_payment_amount','raw_worker_deduction_amount','raw_net_payment_amount','raw_employer_burden_amount','evidence_status'];
$checks=['ck_daily_evidence_raw_non_negative','ck_daily_evidence_raw_amounts','ck_daily_evidence_raw_period','ck_daily_evidence_business_classification','ck_daily_evidence_review_status'];
$presentColumns=$list($db,"SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_daily_employment_income' AND COLUMN_NAME IN ('".implode("','",$columns)."')");
$presentChecks=$list($db,"SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_daily_employment_income' AND CONSTRAINT_NAME IN ('".implode("','",$checks)."')");
$columnState=count($presentColumns)===0?'NOT_APPLIED':(count($presentColumns)===11?'APPLIED':'PARTIAL');
$checkState=count($presentChecks)===0?'NOT_APPLIED':(count($presentChecks)===5?'APPLIED':'PARTIAL');
$rawTable=$exists('ledger_evidence_daily_employment_income_lines');
$evidence=$count($db,'SELECT COUNT(*) FROM ledger_evidence_daily_employment_income');
$legacyInvalid=$count($db,"SELECT COUNT(*) FROM ledger_evidence_daily_employment_income e LEFT JOIN institution_daily_employment_income_groups g ON g.id=e.daily_employment_income_group_id LEFT JOIN system_codes c ON c.code_group='BUSINESS_UNIT' AND c.code=g.business_unit AND c.is_active=1 WHERE g.id IS NULL OR c.id IS NULL OR e.income_year_month NOT REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$' OR e.payment_date IS NULL OR e.total_work_days<0 OR e.total_gross_amount<0 OR e.total_deduction_amount<0 OR e.total_net_payment_amount<0 OR e.total_employer_burden_amount<0 OR ROUND(e.total_gross_amount-e.total_deduction_amount,2)<>ROUND(e.total_net_payment_amount,2) OR e.evidence_status_code NOT IN ('CORRECTION_REQUIRED','COMPLETED')");
$amountMismatch=$count($db,'SELECT COUNT(*) FROM ledger_evidence_daily_employment_income WHERE ROUND(total_gross_amount-total_deduction_amount,2)<>ROUND(total_net_payment_amount,2)');
$groupOrphans=$count($db,'SELECT COUNT(*) FROM ledger_evidence_daily_employment_income e LEFT JOIN institution_daily_employment_income_groups g ON g.id=e.daily_employment_income_group_id WHERE g.id IS NULL');
$revisionOrphans=$count($db,'SELECT COUNT(*) FROM ledger_evidence_daily_employment_income e LEFT JOIN institution_daily_employment_income_calculation_revisions r ON r.id=e.calculation_revision_id WHERE r.id IS NULL');
$expectedLines=$count($db,'SELECT COUNT(*) FROM ledger_evidence_daily_employment_income e JOIN institution_daily_employment_income_lines l ON l.daily_employment_income_item_id=e.daily_employment_income_item_id');
$sourceDuplicates=$count($db,'SELECT COUNT(*) FROM (SELECT e.id evidence_id,l.id source_line_id FROM ledger_evidence_daily_employment_income e JOIN institution_daily_employment_income_lines l ON l.daily_employment_income_item_id=e.daily_employment_income_item_id GROUP BY e.id,l.id HAVING COUNT(*)>1) x');
$rawExisting=0;$rawMissing=$expectedLines;$rawDuplicates=0;$rawOrphans=0;
if($rawTable){
 $rawExisting=$count($db,'SELECT COUNT(*) FROM ledger_evidence_daily_employment_income_lines');
 $rawMissing=$count($db,'SELECT COUNT(*) FROM ledger_evidence_daily_employment_income e JOIN institution_daily_employment_income_lines l ON l.daily_employment_income_item_id=e.daily_employment_income_item_id LEFT JOIN ledger_evidence_daily_employment_income_lines r ON r.evidence_id=e.id AND r.source_calculation_line_id=l.id WHERE r.id IS NULL');
 $rawDuplicates=$count($db,'SELECT COUNT(*) FROM (SELECT evidence_id,source_calculation_line_id FROM ledger_evidence_daily_employment_income_lines GROUP BY evidence_id,source_calculation_line_id HAVING COUNT(*)>1) x');
 $rawOrphans=$count($db,'SELECT COUNT(*) FROM ledger_evidence_daily_employment_income_lines r LEFT JOIN ledger_evidence_daily_employment_income e ON e.id=r.evidence_id LEFT JOIN institution_daily_employment_income_lines l ON l.id=r.source_calculation_line_id LEFT JOIN institution_daily_employment_income_calculation_revisions v ON v.id=r.calculation_revision_id WHERE e.id IS NULL OR l.id IS NULL OR v.id IS NULL');
}
$settings=$count($db,"SELECT COUNT(*) FROM system_user_settings WHERE setting_type='TABLE' AND page_key='evidence-daily-employment-income' AND JSON_VALID(settings_json)=1 AND (settings_json LIKE '%total_work_days%' OR settings_json LIKE '%total_gross_amount%' OR settings_json LIKE '%total_deduction_amount%' OR settings_json LIKE '%total_net_payment_amount%' OR settings_json LIKE '%total_employer_burden_amount%' OR settings_json LIKE '%evidence_status_code%')");
$pending=$count($db,"SELECT COUNT(*) FROM user_approval_request_steps s JOIN user_approval_requests r ON r.id=s.request_id WHERE r.document_type='DAILY_EMPLOYMENT_INCOME' AND r.is_active=1 AND r.status IN ('pending','in_progress') AND s.is_active=1 AND s.step_type='FINAL_APPROVAL' AND s.status IN ('waiting','pending')");
$repairId='2d315f38-bfa7-4ca6-8d6d-fb9bbaa50b7c';
$repair=['transaction'=>$count($db,'SELECT COUNT(*) FROM ledger_transactions WHERE id=:id',[':id'=>$repairId]),'items'=>$count($db,'SELECT COUNT(*) FROM ledger_transaction_items WHERE transaction_id=:id',[':id'=>$repairId]),'settlements'=>$count($db,'SELECT COUNT(*) FROM ledger_transaction_settlements WHERE transaction_id=:id',[':id'=>$repairId]),'links'=>$count($db,"SELECT COUNT(*) FROM ledger_evidence_links WHERE target_type='TRANSACTION' AND target_id=:id AND deleted_at IS NULL",[':id'=>$repairId])];
$rawTotals=[];
if($rawTable){$s=$db->query("SELECT line_type_code,COALESCE(application_status_code,'NULL') application_status_code,COUNT(*) row_count,ROUND(COALESCE(SUM(raw_final_amount),0),2) amount_total FROM ledger_evidence_daily_employment_income_lines GROUP BY line_type_code,application_status_code ORDER BY line_type_code,application_status_code");$rawTotals=$s->fetchAll(PDO::FETCH_ASSOC)?:[];}
$blockers=[];
if($columnState==='PARTIAL')$blockers[]='HEADER_COLUMNS_PARTIAL';if($checkState==='PARTIAL')$blockers[]='CHECKS_PARTIAL';if($legacyInvalid)$blockers[]='HEADER_SOURCE_INVALID';if($sourceDuplicates)$blockers[]='SOURCE_LINE_DUPLICATE';if($rawTable&&$rawExisting&&($rawMissing||$rawDuplicates||$rawOrphans))$blockers[]='RAW_LINE_PARTIAL';if($pending)$blockers[]='FINAL_APPROVAL_IN_PROGRESS';
echo json_encode(['read_only'=>true,'database'=>$database,'version'=>(string)$db->query('SELECT VERSION()')->fetchColumn(),'migration'=>['_01'=>$columnState,'_02'=>$columnState==='NOT_APPLIED'?'NOT_APPLIED':($legacyInvalid?'BLOCKED':'READY_OR_APPLIED'),'_03'=>$checkState,'_04_expected_updates'=>$settings,'_05'=>$rawTable?'APPLIED':'NOT_APPLIED','_06'=>$rawTable&&$rawMissing===0&&$rawDuplicates===0&&$rawOrphans===0?'APPLIED':'NOT_APPLIED_OR_PARTIAL'],'counts'=>['evidence'=>$evidence,'header_backfill_expected'=>$columnState==='NOT_APPLIED'?$evidence:null,'raw_line_backfill_expected'=>$expectedLines,'raw_line_existing'=>$rawExisting,'raw_line_missing'=>$rawMissing,'pending_final_approvals'=>$pending],'integrity'=>['legacy_invalid'=>$legacyInvalid,'amount_mismatch'=>$amountMismatch,'group_orphans'=>$groupOrphans,'revision_orphans'=>$revisionOrphans,'source_duplicates'=>$sourceDuplicates,'raw_duplicates'=>$rawDuplicates,'raw_orphans'=>$rawOrphans],'raw_line_totals'=>$rawTotals,'constraint_install_ready'=>$legacyInvalid===0&&$columnState!=='PARTIAL'&&$checkState!=='PARTIAL','repair_preconditions'=>$repair,'blockers'=>$blockers,'operating_ddl'=>0,'operating_dml'=>0],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),PHP_EOL;
