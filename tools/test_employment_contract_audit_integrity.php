<?php

declare(strict_types=1);

use App\Models\Institution\EmploymentContractModel;
use App\Services\Institution\EmploymentContractAuditService;
use App\Services\Institution\EmploymentContractValidityService;
use Core\DbPdo;
use Core\Helpers\UuidHelper;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$pdo=DbPdo::conn();$contracts=new EmploymentContractModel($pdo);$validity=new EmploymentContractValidityService($contracts);$audit=new EmploymentContractAuditService($pdo);
$base=$pdo->query("SELECT * FROM institution_employment_contracts WHERE contract_status='APPROVED' AND deleted_at IS NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if(!$base)throw new RuntimeException('Fixture 기준 승인 계약이 없습니다.');
$fixtureEmployee=$pdo->query("SELECT e.id FROM user_employees e LEFT JOIN institution_employment_contracts c ON c.employee_id=e.id AND c.deleted_at IS NULL WHERE c.id IS NULL LIMIT 1")->fetchColumn();
if(!$fixtureEmployee)throw new RuntimeException('Fixture용 무계약 직원이 없습니다.');
$result=[];$pdo->beginTransaction();
try{
    $ids=[];
    $make=function(string$status,string$start,?string$end,?string$previous=null,int$revision=1)use(&$ids,$base,$contracts,$fixtureEmployee):array{$row=$base;$row['employee_id']=$fixtureEmployee;$row['id']=UuidHelper::generate();$row['sort_no']=$contracts->nextSortNo();$row['contract_no']='FIX-'.strtoupper(substr(str_replace('-','',$row['id']),0,16));$row['contract_status']=$status;$row['contract_start_date']=$start;$row['contract_end_date']=$end;$row['contract_period_type']=$end===null?'INDEFINITE':'FIXED_TERM';$row['fixed_term_reason_code']=$end===null?null:'GENERAL';$row['fixed_term_reason_detail']=null;$row['previous_contract_id']=$previous;$row['revision_no']=$revision;$row['revision_reason']=$revision>1?'Fixture 개정':null;$row['current_approval_request_id']=null;$row['approved_at']=$status==='APPROVED'?date('Y-m-d H:i:s'):null;$row['terminated_at']=null;$row['termination_reason']=null;$row['deleted_at']=null;$row['deleted_by']=null;$row['created_at']=date('Y-m-d H:i:s');$row['updated_at']=null;$row['created_by']='SYSTEM:EMPLOYMENT_CONTRACT_FIXTURE';$row['updated_by']='SYSTEM:EMPLOYMENT_CONTRACT_FIXTURE';unset($row['employee_name'],$row['project_name'],$row['fixed_term_reason_name'],$row['created_by_name'],$row['updated_by_name'],$row['deleted_by_name']);$contracts->create($row);$ids[]=$row['id'];return$row;};
    $clear=function()use(&$ids,$pdo):void{for($i=count($ids)-1;$i>=0;$i--)$pdo->prepare('DELETE FROM institution_employment_contracts WHERE id=:id')->execute([':id'=>$ids[$i]]);$ids=[];};
    $a=$make('APPROVED','2026-01-01','2026-06-30');$b=$make('DRAFT','2026-07-01','2026-12-31');$validity->assertNoOverlap($b['id']);$result['A_non_overlap']='PASS';$clear();
    $a=$make('APPROVED','2026-01-01','2026-06-30');$b=$make('DRAFT','2026-06-30','2026-12-31');try{$validity->assertNoOverlap($b['id']);$result['B_boundary_overlap']='FAIL';}catch(RuntimeException){$result['B_boundary_overlap']='PASS';}$clear();
    $a=$make('APPROVED','2026-01-01',null);$b=$make('DRAFT','2026-08-01',null);try{$validity->assertNoOverlap($b['id']);$result['C_open_end_overlap']='FAIL';}catch(RuntimeException){$result['C_open_end_overlap']='PASS';}$clear();
    $r1=$make('APPROVED','2026-01-01','2026-12-31');$r2=$make('APPROVED','2026-01-01','2026-12-31',$r1['id'],2);$effective=$validity->effectiveContracts((string)$fixtureEmployee,'2026-08-01');$fixture=array_values(array_filter($effective,fn(array$row):bool=>in_array($row['id'],[$r1['id'],$r2['id']],true)));$result['D_revision_latest']=count($fixture)===1&&$fixture[0]['id']===$r2['id']?'PASS':'FAIL';$clear();
    $a=$make('APPROVED','2026-01-01','2026-12-31');$b=$make('DRAFT','2026-04-01','2026-09-30');try{$validity->assertNoOverlap($b['id']);$result['E_other_lineage_overlap']='FAIL';}catch(RuntimeException){$result['E_other_lineage_overlap']='PASS';}$clear();
    $fixture=$make('DRAFT','2027-01-01','2027-12-31');$snapshot=$audit->snapshot((string)$base['id']);$complete=isset($snapshot['header'],$snapshot['weekly_schedules'],$snapshot['components'])&&count($snapshot['weekly_schedules'])>0&&count($snapshot['components'])>0&&array_key_exists('work_schedule_policy',$snapshot);$actions=['CREATE','UPDATE_DRAFT','SUBMIT','WITHDRAW','APPROVE','REJECT','CREATE_REVISION','APPROVE_REVISION','TERMINATE','CANCEL','DELETE','RESTORE','PURGE'];foreach($actions as$action)$audit->record($fixture['id'],$action,$snapshot,$snapshot,'Fixture '.$action,'SYSTEM:EMPLOYMENT_CONTRACT_FIXTURE','fixture-'.$action);$before=count($audit->histories($fixture['id']));$audit->record($fixture['id'],'APPROVE',$snapshot,$snapshot,'Fixture APPROVE','SYSTEM:EMPLOYMENT_CONTRACT_FIXTURE','fixture-APPROVE');$after=count($audit->histories($fixture['id']));$result['F_audit_actions']=$before===count($actions)?'PASS':'FAIL';$result['G_audit_idempotency']=$before===$after?'PASS':'FAIL';$result['snapshot_complete']=$complete?'PASS':'FAIL';
    $incomeId=UuidHelper::generate();$pdo->prepare('INSERT INTO institution_regular_employment_incomes (id,sort_no,income_year_month,payment_date,title,created_by) VALUES (:id,999999999,:month,:date,:title,:actor)')->execute([':id'=>$incomeId,':month'=>'2099-12',':date'=>'2099-12-31',':title'=>'FK Fixture',':actor'=>'SYSTEM:EMPLOYMENT_CONTRACT_FIXTURE']);$pdo->prepare('INSERT INTO institution_regular_employment_income_items (id,sort_no,regular_employment_income_id,employee_id,employee_name_snapshot,employment_contract_id,created_by) VALUES (:id,1,:header,:employee,:name,:contract,:actor)')->execute([':id'=>UuidHelper::generate(),':header'=>$incomeId,':employee'=>$base['employee_id'],':name'=>$base['employee_name_snapshot'],':contract'=>$fixture['id'],':actor'=>'SYSTEM:EMPLOYMENT_CONTRACT_FIXTURE']);try{$pdo->prepare('DELETE FROM institution_employment_contracts WHERE id=:id')->execute([':id'=>$fixture['id']]);$result['H_fk_restrict']='FAIL';}catch(PDOException){$result['H_fk_restrict']='PASS';}
    $fixture['deleted_at']=date('Y-m-d H:i:s');$pdo->prepare('UPDATE institution_employment_contracts SET deleted_at=:deleted_at,deleted_by=:actor WHERE id=:id')->execute([':deleted_at'=>$fixture['deleted_at'],':actor'=>'SYSTEM:EMPLOYMENT_CONTRACT_FIXTURE',':id'=>$fixture['id']]);$result['I_approved_direct_update']=$contracts->updateEditable((string)$base['id'],['note'=>'금지 검증'])?'FAIL':'PASS';$pdo->prepare('DELETE FROM institution_regular_employment_incomes WHERE id=:id')->execute([':id'=>$incomeId]);$result['J_draft_purge']=$contracts->purge($fixture['id'])?'PASS':'FAIL';
    $pdo->rollBack();
}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
echo json_encode(['rolled_back'=>true,'results'=>$result],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),"\n";
