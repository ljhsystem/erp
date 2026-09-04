<?php

declare(strict_types=1);

define('PROJECT_ROOT',dirname(__DIR__,2));
require PROJECT_ROOT.'/vendor/autoload.php';
require PROJECT_ROOT.'/core/DbPdo.php';
require PROJECT_ROOT.'/core/Storage.php';

use App\Services\Auth\AuthSessionService;
use App\Services\Institution\BusinessIncomeService;
use App\Services\Approval\ApprovalDocumentSummaryResolver;
use App\Services\Approval\ApprovalInboxService;
use Core\DbPdo;
use Core\Helpers\UuidHelper;

$db=DbPdo::conn();
$user=$db->query("SELECT id,username,role_id FROM auth_users WHERE approved=1 AND is_active=1 ORDER BY created_at,id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$client=$db->query("SELECT id,client_name,client_type FROM system_clients WHERE is_active=1 AND deleted_at IS NULL ORDER BY sort_no,id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$businessUnit=(string)$db->query("SELECT code FROM system_codes WHERE code_group='BUSINESS_UNIT' AND is_active=1 ORDER BY sort_no,code LIMIT 1")->fetchColumn();
if(!$user||!$client||$businessUnit==='')throw new RuntimeException('사업소득 저장 Runtime Fixture 기준정보가 없습니다.');
(new AuthSessionService())->createLoginSession($user+['role_key'=>null,'role_name'=>null]);
$actor='USER:'.$user['id'];$profileId=UuidHelper::generate();$paymentDate=date('Y-m-d');
$insert=static function(PDO$db,string$table,array$row):void{$columns=array_keys($row);$statement=$db->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',$columns).'`) VALUES (:'.implode(',:',$columns).')');$statement->execute(array_combine(array_map(static fn(string$key):string=>':'.$key,$columns),array_values($row)));};
$before=(int)$db->query('SELECT COUNT(*) FROM institution_business_incomes')->fetchColumn();
$db->beginTransaction();
try{
    $db->prepare("UPDATE system_clients SET client_type='FREELANCER' WHERE id=:id")->execute([':id'=>$client['id']]);
    $insert($db,'system_client_tax_profiles',['id'=>$profileId,'client_id'=>$client['id'],'effective_from'=>$paymentDate,'taxpayer_entity_type'=>'INDIVIDUAL','residency_status'=>'RESIDENT','income_recipient_type'=>'BUSINESS_INCOME','withholding_policy_code'=>'BUSINESS_INCOME_WITHHOLDING','verification_status'=>'VERIFIED','verified_at'=>date('Y-m-d H:i:s'),'verified_by'=>$actor,'created_by'=>$actor,'updated_by'=>$actor]);
    $service=new BusinessIncomeService($db);
    $payload=['income_year_month'=>substr($paymentDate,0,7),'withholding_date'=>$paymentDate,'title'=>'사업소득 저장·재조회 Runtime 검증','description'=>'Rollback fixture','memo'=>'사업소득 문서 메모 저장 검증','groups'=>[[
        'business_unit'=>$businessUnit,'project_id'=>null,'work_team_id'=>null,'group_description'=>'검증 지급그룹','items'=>[[
            'transaction_date'=>$paymentDate,'client_id'=>$client['id'],'service_type_code'=>'GENERAL_SERVICE','service_description'=>'사업소득 용역',
            'work_lines'=>[
                ['item_name'=>'설계 용역','item_specification'=>'기본설계','item_unit_name'=>'식','item_quantity'=>1,'item_unit_price'=>700000,'adjustment_amount'=>0],
                ['item_name'=>'현장 자문','item_specification'=>'기술 검토','item_unit_name'=>'시간','item_quantity'=>10,'item_unit_price'=>30000,'adjustment_amount'=>0],
            ],
        ]],
    ]]];
    $preview=$service->calculate($payload);$saved=$service->save($payload);$id=(string)$saved['data']['id'];$detail=$service->detail($id);$preflight=$service->submissionPreflight($id);$submitted=$service->submit($id);
    $requestId=(string)$submitted['data']['request_id'];$requestCountStatement=$db->prepare("SELECT COUNT(*) FROM user_approval_requests WHERE id=:id AND document_type='BUSINESS_INCOME' AND document_id=:document_id AND status='pending'");$requestCountStatement->execute([':id'=>$requestId,':document_id'=>$id]);
    $summary=(new ApprovalDocumentSummaryResolver($db))->resolve([['document_type'=>'BUSINESS_INCOME','document_id'=>$id]])[0];
    $approvalStates=[];$lastStepId='';$lastApprover=null;
    while(true){
        $request=$db->prepare('SELECT status,current_step FROM user_approval_requests WHERE id=:id');$request->execute([':id'=>$requestId]);$requestRow=$request->fetch(PDO::FETCH_ASSOC);if(($requestRow['status']??'')==='approved')break;
        $step=$db->prepare("SELECT * FROM user_approval_request_steps WHERE request_id=:id AND sort_no=:sort_no AND status='pending' FOR UPDATE");$step->execute([':id'=>$requestId,':sort_no'=>$requestRow['current_step']]);$stepRow=$step->fetch(PDO::FETCH_ASSOC);if(!$stepRow)throw new RuntimeException('처리할 사업소득 결재단계를 찾을 수 없습니다.');
        $approverId=trim((string)($stepRow['approver_id']??''));if($approverId===''){$eligible=$db->prepare("SELECT u.id,u.username,u.role_id FROM auth_users u JOIN user_employees e ON e.user_id=u.id JOIN auth_roles r ON r.id=u.role_id WHERE u.role_id=:role_id AND u.approved=1 AND u.is_active=1 AND r.is_active=1 AND (e.doc_retire_date IS NULL OR e.doc_retire_date>CURRENT_DATE()) AND (e.real_retire_date IS NULL OR e.real_retire_date>CURRENT_DATE()) ORDER BY u.created_at,u.id LIMIT 1");$eligible->execute([':role_id'=>$stepRow['role_id']]);$approver=$eligible->fetch(PDO::FETCH_ASSOC);}else{$eligible=$db->prepare('SELECT id,username,role_id FROM auth_users WHERE id=:id');$eligible->execute([':id'=>$approverId]);$approver=$eligible->fetch(PDO::FETCH_ASSOC);}
        if(!$approver)throw new RuntimeException('사업소득 결재단계의 적격 승인자가 없습니다.');(new AuthSessionService())->createLoginSession($approver+['role_key'=>null,'role_name'=>null]);
        $inboxDetail=(new ApprovalInboxService($db))->detail($requestId);$lastStepId=(string)$stepRow['id'];$lastApprover=$approver;$acted=(new BusinessIncomeService($db))->act($lastStepId,'approved',null);$approvalStates[]=['can_act'=>$inboxDetail['data']['actions']['can_act'],'state'=>$acted['data']['state']];
    }
    $artifacts=$db->prepare("SELECT e.raw_gross_payment_amount,t.transaction_supply_amount,t.transaction_final_amount,(SELECT COUNT(*) FROM ledger_evidence_business_income_raw_lines r WHERE r.evidence_id=e.id) raw_line_count,(SELECT COUNT(*) FROM ledger_evidence_business_income_work_lines w WHERE w.evidence_id=e.id) work_line_count,(SELECT COUNT(*) FROM ledger_transaction_items i WHERE i.transaction_id=t.id) transaction_item_count,(SELECT COUNT(*) FROM ledger_transaction_settlements s WHERE s.transaction_id=t.id) settlement_count,(SELECT COUNT(*) FROM ledger_evidence_links l WHERE l.evidence_type='BUSINESS_INCOME_REPORT' AND l.evidence_id=e.id AND l.target_type='TRANSACTION' AND l.target_id=t.id AND l.deleted_at IS NULL) evidence_link_count FROM institution_business_income_artifact_links a JOIN ledger_evidence_business_income e ON e.id=a.evidence_id JOIN ledger_transactions t ON t.id=a.transaction_id WHERE a.business_income_id=:id");$artifacts->execute([':id'=>$id]);$artifact=$artifacts->fetch(PDO::FETCH_ASSOC)?:[];
    (new AuthSessionService())->createLoginSession($lastApprover+['role_key'=>null,'role_name'=>null]);$replay=(new BusinessIncomeService($db))->act($lastStepId,'approved',null);
    $lineCountStatement=$db->prepare('SELECT COUNT(*) FROM institution_business_income_calculation_lines WHERE calculation_revision_id=:id');$lineCountStatement->execute([':id'=>$saved['data']['calculation_revision_id']]);
    $result=['success'=>$preview['data']['totals']['net_payment_amount']===967000.0&&($detail['data']['withholding_date']??'')===$paymentDate&&($detail['data']['memo']??'')==='사업소득 문서 메모 저장 검증'&&$detail['data']['groups'][0]['items'][0]['net_payment_amount']==967000&&$preflight['data']['ready']===true&&(int)$requestCountStatement->fetchColumn()===1&&(int)$lineCountStatement->fetchColumn()===3
        &&$summary['summary_status_code']==='RESOLVED'&&array_reduce($approvalStates,static fn(bool$ok,array$row):bool=>$ok&&$row['can_act'],true)&&$artifact['raw_gross_payment_amount']=='1000000.00'&&$artifact['transaction_final_amount']=='967000.00'&&(int)$artifact['raw_line_count']===3&&(int)$artifact['work_line_count']===2&&(int)$artifact['transaction_item_count']===2&&(int)$artifact['settlement_count']===2&&(int)$artifact['evidence_link_count']===1&&$replay['data']['closure']['status']==='ALREADY_PROCESSED',
        'preview_totals'=>$preview['data']['totals'],'saved'=>$saved['data'],'reloaded_item'=>$detail['data']['groups'][0]['items'][0],'preflight'=>$preflight['data'],'approval_request_id'=>$requestId,'approval_summary'=>$summary,'approval_states'=>$approvalStates,'artifact'=>$artifact,'replay'=>$replay['data']['closure']];
    $db->rollBack();
}catch(Throwable$error){if($db->inTransaction())$db->rollBack();throw$error;}
$after=(int)$db->query('SELECT COUNT(*) FROM institution_business_incomes')->fetchColumn();$result['rollback_unchanged']=$before===$after;$result['success']=$result['success']&&$result['rollback_unchanged'];
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($result['success']?0:1);
