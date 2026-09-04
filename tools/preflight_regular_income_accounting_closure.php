<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Institution\RegularEmploymentIncomeAccountingException;
use App\Services\Institution\RegularEmploymentIncomeAccountingGenerationService;
use Core\DbPdo;

$db=DbPdo::conn();
$document=$db->query("SELECT id,current_approval_request_id FROM institution_regular_employment_incomes WHERE document_status='PENDING' AND deleted_at IS NULL ORDER BY created_at,id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if(!$document)throw new RuntimeException('결재 대기 중인 상용근로소득 문서가 없습니다.');
$counts=static function()use($db):array{$scheduleLinkTable=(int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_income_accounting_schedules'")->fetchColumn();return['evidence'=>(int)$db->query('SELECT COUNT(*) FROM ledger_evidence_salary_report')->fetchColumn(),'transactions'=>(int)$db->query('SELECT COUNT(*) FROM ledger_transactions')->fetchColumn(),'items'=>(int)$db->query('SELECT COUNT(*) FROM ledger_transaction_items')->fetchColumn(),'settlements'=>(int)$db->query('SELECT COUNT(*) FROM ledger_transaction_settlements')->fetchColumn(),'schedules'=>(int)$db->query('SELECT COUNT(*) FROM ledger_payment_schedules')->fetchColumn(),'registries'=>(int)$db->query('SELECT COUNT(*) FROM institution_regular_employment_income_accounting_links')->fetchColumn(),'schedule_links'=>$scheduleLinkTable===1?(int)$db->query('SELECT COUNT(*) FROM institution_regular_income_accounting_schedules')->fetchColumn():0];};
$before=$counts();
try{$plan=(new RegularEmploymentIncomeAccountingGenerationService($db))->preflight((string)$document['id'],(string)$document['current_approval_request_id'],false);$result=['success'=>true,'plan'=>['attribution_month'=>$plan['attribution_month'],'recognition_date'=>$plan['recognition_date'],'payment_date'=>$plan['payment_date'],'employee_count'=>count($plan['items']),'evidence_count'=>count($plan['items']),'evidence_initial_status'=>'CORRECTION_REQUIRED','employee_transaction_count'=>count($plan['items']),'institution_transaction_count'=>0,'evidence_link_count'=>count($plan['items']),'payment_schedule_count'=>0,'voucher_count'=>0,'totals'=>$plan['totals']]];}catch(RegularEmploymentIncomeAccountingException$e){$result=['success'=>false,'error_code'=>$e->errorCode(),'message'=>$e->getMessage()];}
$after=$counts();$result['document_id']=$document['id'];$result['before']=$before;$result['after']=$after;$result['read_only_unchanged']=$before===$after;echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
