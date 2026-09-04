<?php

namespace App\Services\Institution;

use App\Models\System\CodeModel;
use App\Models\User\ApprovalRequestModel;
use App\Repositories\Institution\PersonnelActionRepository;
use App\Services\Approval\ApprovalWorkflowService;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;

class PersonnelActionService
{
    public const DOCUMENT_TYPE = 'PERSONNEL_ACTION';
    private PersonnelActionRepository $repository;
    private ApprovalWorkflowService $workflow;
    private ApprovalRequestModel $requests;
    private CodeModel $codes;
    private PersonnelActionApplyService $applyService;
    private $logger;

    public function __construct(private readonly PDO $pdo)
    {
        $this->repository=new PersonnelActionRepository($pdo); $this->workflow=new ApprovalWorkflowService($pdo);
        $this->requests=new ApprovalRequestModel($pdo); $this->codes=new CodeModel($pdo); $this->applyService=new PersonnelActionApplyService($pdo); $this->logger=LoggerFactory::getLogger('service-institution.PersonnelActionService');
    }
    public function modalOptions(): array { return ['success'=>true,'data'=>$this->repository->modalOptions()+['change_policy'=>PersonnelActionChangePolicy::metadata()]]; }
    public function list(array $query): array { $p=$this->repository->page($query); return ['success'=>true,'data'=>$p['rows'],'draw'=>(int)($query['draw']??0),'recordsTotal'=>$p['total'],'recordsFiltered'=>$p['filtered']]; }
    public function trash(array $query): array { $p=$this->repository->page($query,true); return ['success'=>true,'data'=>$p['rows'],'recordsTotal'=>$p['total'],'recordsFiltered'=>$p['filtered']]; }
    public function detail(string $id): array { $action=$this->requireAction($id,true); $targets=$this->repository->targets($id); $changes=$this->repository->changes($id); foreach($changes as &$change)$change['change_type_name']=PersonnelActionChangePolicy::label((string)$change['change_type_code']);unset($change);foreach($targets as &$target)$target['changes']=array_values(array_filter($changes,fn($c)=>$c['personnel_action_target_id']===$target['id'])); unset($target); return ['success'=>true,'data'=>['action'=>$action,'targets'=>$targets,'approval_steps'=>$action['current_approval_request_id']?$this->repository->approvalSteps((string)$action['current_approval_request_id']):[]]]; }

    public function save(array $input): array
    {
        [, $actor]=$this->identity(); return $this->transaction(function()use($input,$actor){
            $id=trim((string)($input['id']??'')); $existing=$id!==''?$this->repository->find($id,false,true):null;
            if($id!==''&&(!$existing||$existing['business_status']!=='DRAFT'))throw new \RuntimeException('작성중 인사발령만 수정할 수 있습니다.');
            $issued=$this->date((string)($input['issued_date']??''),'발령일'); $effective=$this->date((string)($input['action_date']??''),'효력일');
            $type=$this->activeCode('PERSONNEL_ACTION_TYPE',$input['action_type_code']??'','발령유형'); PersonnelActionChangePolicy::assertSupportedActionType($type);
            $name=trim((string)($input['action_name']??'')); if($name==='')throw new \InvalidArgumentException('발령제목을 입력해 주세요.');
            $targets=is_array($input['targets']??null)?$input['targets']:[]; if($targets===[])throw new \InvalidArgumentException('대상 직원을 한 명 이상 등록해 주세요.');
            $data=['issued_date'=>$issued,'action_date'=>$effective,'action_type_code'=>$type,'action_name'=>$name,'action_reason'=>$this->nullable($input['action_reason']??null),'note'=>$this->nullable($input['note']??null),'updated_at'=>date('Y-m-d H:i:s'),'updated_by'=>$actor];
            if($id===''){ $id=UuidHelper::generate(); $this->repository->insertAction(['id'=>$id,'sort_no'=>$this->repository->nextSortNo(),'action_no'=>$this->actionNo(),'business_status'=>'DRAFT','created_by'=>$actor]+$data); }
            else { $this->repository->updateDraft($id,$data); $this->repository->replaceChildren($id); }
            $seen=[]; foreach($targets as $targetIndex=>$target){$employeeId=trim((string)($target['employee_id']??''));if($employeeId===''||isset($seen[$employeeId]))throw new \InvalidArgumentException('대상 직원이 없거나 중복되었습니다.');$seen[$employeeId]=true;$employee=$this->repository->employee($employeeId);if(!$employee)throw new \InvalidArgumentException('직원 정보를 찾을 수 없습니다.');$changes=is_array($target['changes']??null)?$target['changes']:[];if($changes===[])throw new \InvalidArgumentException($employee['employee_name'].' 직원의 변경항목을 등록해 주세요.');$normalizedChanges=[];foreach($changes as $change)$normalizedChanges[]=$this->normalizeChange($employee,$change,$effective,$type);PersonnelActionChangePolicy::assertCommandSet($type,$normalizedChanges);$targetId=UuidHelper::generate();$this->repository->insertTarget(['id'=>$targetId,'personnel_action_id'=>$id,'employee_id'=>$employeeId,'sort_no'=>$targetIndex+1,'individual_reason'=>$this->nullable($target['individual_reason']??null),'application_status'=>'PENDING','created_by'=>$actor]);foreach($normalizedChanges as $changeIndex=>$normalized)$this->repository->insertChange(['id'=>UuidHelper::generate(),'personnel_action_target_id'=>$targetId,'sort_no'=>$changeIndex+1,'created_by'=>$actor]+$normalized);}
            return ['success'=>true,'data'=>['id'=>$id],'message'=>'인사발령을 임시저장했습니다.'];
        });
    }

    public function reorder(array $changes): array
    {
        if ($changes === []) return ['success'=>true,'message'=>'변경할 순서가 없습니다.'];
        $normalized=[];
        foreach($changes as $change){
            $id=trim((string)($change['id']??''));
            $sortNo=filter_var($change['newSortNo']??$change['sort_no']??null,FILTER_VALIDATE_INT);
            if($id===''||$sortNo===false||$sortNo<1)throw new \InvalidArgumentException('순서 변경 데이터가 올바르지 않습니다.');
            $normalized[]=['id'=>$id,'sort_no'=>$sortNo];
        }
        if(count(array_unique(array_column($normalized,'id')))!==count($normalized)||count(array_unique(array_column($normalized,'sort_no')))!==count($normalized))throw new \InvalidArgumentException('순서 변경 대상 또는 순번이 중복되었습니다.');
        return $this->transaction(function()use($normalized){
            foreach($normalized as $index=>$change)$this->repository->updateSortNo($change['id'],1000000+$index+1);
            foreach($normalized as $change)if(!$this->repository->updateSortNo($change['id'],$change['sort_no']))throw new \RuntimeException('순서를 변경할 인사발령을 찾을 수 없습니다.');
            return ['success'=>true,'message'=>'순서가 저장되었습니다.'];
        });
    }

    public function submit(string $id): array { [$user,$actor]=$this->identity(); return $this->transaction(function()use($id,$user,$actor){$action=$this->requireAction($id,true);if($action['business_status']!=='DRAFT')throw new \RuntimeException('작성중 인사발령만 결재요청할 수 있습니다.');$this->validateStored($action);$result=$this->workflow->submit(self::DOCUMENT_TYPE,$id,$user,$actor);$this->repository->updateWorkflow($id,'APPROVAL_PENDING',$result['request_id'],$actor);return ['success'=>true,'data'=>$result,'message'=>'결재를 요청했습니다.'];}); }
    public function withdraw(string $requestId): array { [$user,$actor]=$this->identity();return $this->transaction(function()use($requestId,$user,$actor){$request=$this->workflow->withdraw($requestId,self::DOCUMENT_TYPE,$user,$actor);$this->repository->updateWorkflow((string)$request['document_id'],'DRAFT',$requestId,$actor);return ['success'=>true,'message'=>'기안을 회수했습니다.'];}); }
    public function act(string $stepId,string $decision,?string $comment): array { [$user,$actor]=$this->identity();$projection=$this->transaction(function()use($stepId,$decision,$comment,$user,$actor){$result=$this->workflow->act($stepId,self::DOCUMENT_TYPE,$decision,$comment,$user,$actor);$state=$result['state'];$status=$state==='APPROVED'?'APPROVED':($state==='REJECTED'?'DRAFT':'APPROVAL_PENDING');$id=(string)$result['request']['document_id'];$this->repository->updateWorkflow($id,$status,(string)$result['request']['id'],$actor);return ['state'=>$state,'status'=>$status,'id'=>$id];});if($projection['status']==='APPROVED'){ $action=$this->repository->find($projection['id']);if($action&&$action['action_date']<=date('Y-m-d'))$this->applyService->apply($projection['id'],$actor); }return ['success'=>true,'message'=>$projection['state']==='APPROVED'?'최종 승인했습니다.':($projection['state']==='REJECTED'?'반려했습니다.':'승인했습니다.')]; }
    public function apply(string $id): array { return $this->loggedDirect('apply', function()use($id){$result=$this->applyService->apply($id);return ['success'=>true,'data'=>$result,'message'=>$result['already_applied']?'이미 적용된 발령입니다.':'인사발령을 적용했습니다.'];}); }
    public function delete(string $id): array { return $this->loggedDirect('delete', function()use($id){[, $actor]=$this->identity();if(!$this->repository->softDelete($id,$actor))throw new \RuntimeException('결재 이력이 없는 작성중 발령만 삭제할 수 있습니다.');return ['success'=>true,'message'=>'휴지통으로 이동했습니다.'];}); }
    public function restore(string $id): array { return $this->loggedDirect('restore', function()use($id){[, $actor]=$this->identity();if(!$this->repository->restore($id,$actor))throw new \RuntimeException('복원할 수 없는 발령입니다.');return ['success'=>true,'message'=>'복원했습니다.'];}); }
    public function purge(string $id): array { $result=$this->purgeMany([$id]);if(($result['data']['deleted_count']??0)!==1)throw new \RuntimeException('완전삭제할 수 없는 발령입니다.');$result['message']='완전삭제했습니다.';return$result; }
    public function purgeMany(array $ids): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            fn($id) => trim((string) $id),
            $ids
        ))));
        if ($normalized === []) throw new \InvalidArgumentException('완전삭제할 인사발령을 선택해 주세요.');
        return $this->transaction(function () use ($normalized) {
            $deleted = 0;
            $skipped = 0;
            foreach ($normalized as $id) {
                if ($this->repository->purge($id)) $deleted++;
                else $skipped++;
            }
            return [
                'success' => true,
                'data' => ['deleted_count' => $deleted, 'skipped_count' => $skipped],
                'message' => $deleted > 0 ? '인사발령을 완전삭제했습니다.' : '완전삭제된 인사발령이 없습니다.',
            ];
        });
    }
    public function purgeAll(): array { $ids=$this->repository->trashIds();if($ids===[])return['success'=>true,'data'=>['deleted_count'=>0,'skipped_count'=>0],'message'=>'완전삭제할 인사발령이 없습니다.'];return$this->purgeMany($ids); }

    private function normalizeChange(array $employee,array $change,string $effective,string $actionType): array
    {
        $type=strtoupper(trim((string)($change['change_type_code']??'')));PersonnelActionChangePolicy::assertAllowed($actionType,$type);
        $hireDate=$this->nullable($employee['real_hire_date']??null)??$this->nullable($employee['doc_hire_date']??null);
        if($hireDate!==null&&$effective<$hireDate)throw new \InvalidArgumentException('발령 적용일은 직원의 실입사일 또는 문서상 입사일보다 빠를 수 없습니다.');
        $data=array_fill_keys(['before_employment_status','after_employment_status','before_department_id','after_department_id','before_position_id','after_position_id','before_job_id','after_job_id','project_id','project_assignment_id','assignment_job_id','assignment_role','assignment_start_date','assignment_end_date','is_primary_assignment','workplace_assignment_id','workplace_type_code','workplace_project_id','workplace_name_snapshot','workplace_address_snapshot','leave_period_id','leave_type_code','leave_start_date','leave_planned_end_date','leave_actual_end_date','date_kind','before_date','after_date'],null);
        $data+=['change_type_code'=>$type,'effective_date'=>$effective,'before_display_snapshot'=>null,'after_display_snapshot'=>null];
        if($type==='EMPLOYMENT_STATUS'){$data['before_employment_status']=$employee['employment_status'];$data['after_employment_status']=$this->activeCode('EMPLOYMENT_STATUS',$change['after_employment_status']??'','변경 후 재직상태');$this->different($data['before_employment_status'],$data['after_employment_status'],'현재 재직상태와 다른 상태를 선택해 주세요.');}
        elseif($type==='DEPARTMENT'){$data['before_department_id']=$employee['department_id'];$data['after_department_id']=$this->requiredReference('department',$change['after_department_id']??null,'변경 후 부서');$this->different($data['before_department_id'],$data['after_department_id'],'현재 부서와 다른 부서를 선택해 주세요.');}
        elseif($type==='POSITION'){$data['before_position_id']=$employee['position_id'];$data['after_position_id']=$this->requiredReference('position',$change['after_position_id']??null,'변경 후 직위');$this->different($data['before_position_id'],$data['after_position_id'],'현재 직위와 다른 직위를 선택해 주세요.');}
        elseif($type==='JOB'){$data['before_job_id']=$employee['job_id'];$data['after_job_id']=$this->requiredReference('job',$change['after_job_id']??null,'변경 후 직무');$this->different($data['before_job_id'],$data['after_job_id'],'현재 직무와 다른 직무를 선택해 주세요.');}
        elseif($type==='PROJECT_ASSIGNMENT'){$data['project_id']=$this->requiredReference('project',$change['project_id']??null,'프로젝트');foreach(['assignment_role','assignment_start_date','assignment_end_date'] as $f)$data[$f]=$this->nullable($change[$f]??null);$job=$this->nullable($change['assignment_job_id']??null);if($job!==null&&!$this->repository->referenceExists('job',$job))throw new \InvalidArgumentException('프로젝트 배치 직무를 확인해 주세요.');$data['assignment_job_id']=$job;if($data['assignment_start_date']===null)throw new \InvalidArgumentException('프로젝트 배치 시작일을 입력해 주세요.');$this->date($data['assignment_start_date'],'프로젝트 배치 시작일');if($data['assignment_end_date']!==null)$this->date($data['assignment_end_date'],'프로젝트 배치 종료일');$data['is_primary_assignment']=!empty($change['is_primary_assignment'])?1:0;}
        elseif($type==='PROJECT_RELEASE'){$data['project_assignment_id']=$this->requiredOwnedReference('project_assignment',$change['project_assignment_id']??null,(string)$employee['id'],'해제 대상 프로젝트 배치');$data['assignment_end_date']=$this->nullable($change['assignment_end_date']??null);if($data['assignment_end_date']===null)throw new \InvalidArgumentException('프로젝트 배치 종료일을 입력해 주세요.');$this->date($data['assignment_end_date'],'프로젝트 배치 종료일');}
        elseif($type==='WORKPLACE'){$assignment=$this->nullable($change['workplace_assignment_id']??null);if($assignment!==null&&!$this->repository->ownedReferenceExists('workplace_assignment',$assignment,(string)$employee['id']))throw new \InvalidArgumentException('변경 전 근무지 배치를 확인해 주세요.');$data['workplace_assignment_id']=$assignment;foreach(['workplace_name_snapshot','workplace_address_snapshot'] as $f)$data[$f]=$this->nullable($change[$f]??null);$data['workplace_type_code']=$this->activeCode('EMPLOYEE_WORKPLACE_TYPE',$change['workplace_type_code']??'','근무지유형');$data['workplace_project_id']=$data['workplace_type_code']==='PROJECT'?$this->requiredReference('project',$change['workplace_project_id']??null,'현장 프로젝트'):null;}
        elseif($type==='LEAVE'){$data['leave_start_date']=$this->nullable($change['leave_start_date']??null);$data['leave_planned_end_date']=$this->nullable($change['leave_planned_end_date']??null);if($data['leave_start_date']===null)throw new \InvalidArgumentException('휴직 시작일을 입력해 주세요.');$this->date($data['leave_start_date'],'휴직 시작일');if($data['leave_planned_end_date']!==null){$this->date($data['leave_planned_end_date'],'휴직 예정종료일');if($data['leave_planned_end_date']<$data['leave_start_date'])throw new \InvalidArgumentException('휴직 예정종료일은 시작일 이후여야 합니다.');}$data['leave_type_code']=$this->activeCode('EMPLOYEE_LEAVE_TYPE',$change['leave_type_code']??'','휴직유형');}
        elseif($type==='RETURN_FROM_LEAVE'){$data['leave_period_id']=$this->requiredOwnedReference('leave_period',$change['leave_period_id']??null,(string)$employee['id'],'복직 대상 휴직기간');$data['leave_actual_end_date']=$this->nullable($change['leave_actual_end_date']??null);if($data['leave_actual_end_date']===null)throw new \InvalidArgumentException('복직일을 입력해 주세요.');$this->date($data['leave_actual_end_date'],'복직일');}
        else{$kind=strtoupper(trim((string)($change['date_kind']??'ACTUAL')));if(!in_array($kind,['DOCUMENT','ACTUAL'],true))throw new \InvalidArgumentException('입·퇴사일 구분을 확인해 주세요.');$prefix=$kind==='ACTUAL'?'real_':'doc_';$field=$prefix.($type==='HIRE_DATE'?'hire_date':'retire_date');$data['date_kind']=$kind;$data['before_date']=$employee[$field];$data['after_date']=$this->nullable($change['after_date']??null);if($data['after_date']===null)throw new \InvalidArgumentException('변경 후 날짜를 입력해 주세요.');$this->date($data['after_date'],'변경 후 날짜');$this->different($data['before_date'],$data['after_date'],'현재 날짜와 다른 날짜를 입력해 주세요.');}
        $data['before_display_snapshot']=$this->beforeLabel($employee,$type);
        $data['after_display_snapshot']=$this->nullable($change['after_display_snapshot']??null)??$this->nullable($change['after_label']??null);
        if($hireDate!==null){foreach(['assignment_start_date','assignment_end_date','leave_start_date','leave_planned_end_date','leave_actual_end_date'] as $dateField){if($data[$dateField]!==null&&$data[$dateField]<$hireDate)throw new \InvalidArgumentException('인사발령의 변경일은 직원의 입사일보다 빠를 수 없습니다.');}if($type==='RETIRE_DATE'&&$data['after_date']<$hireDate)throw new \InvalidArgumentException('퇴사일은 직원의 입사일보다 빠를 수 없습니다.');}
        return $data;
    }
    private function validateStored(array $action): void { $type=(string)$action['action_type_code'];PersonnelActionChangePolicy::assertSupportedActionType($type);$targets=$this->repository->targets((string)$action['id']);if($targets===[])throw new \RuntimeException('대상자가 없습니다.');foreach($targets as $target){$employee=$this->repository->employee((string)$target['employee_id']);$changes=$this->repository->targetChanges((string)$target['id']);if(!$employee||$changes===[])throw new \RuntimeException('대상자 또는 변경항목을 확인해 주세요.');$normalized=[];foreach($changes as $change)$normalized[]=$this->normalizeChange($employee,$change,(string)$action['action_date'],$type);PersonnelActionChangePolicy::assertCommandSet($type,$normalized);if($this->repository->conflicts((string)$target['employee_id'],(string)$action['id'],(string)$action['action_date']))throw new \RuntimeException('동일 직원의 충돌하는 결재·승인 발령이 있습니다.');if($type==='HIRE'&&$employee['employment_status']!=='PENDING_HIRE')throw new \RuntimeException('입사발령 대상자는 입사예정 상태여야 합니다.');if($type==='LEAVE_OF_ABSENCE'&&$employee['employment_status']!=='ACTIVE')throw new \RuntimeException('휴직 대상자는 재직 상태여야 합니다.');if($type==='REINSTATEMENT'&&!$this->repository->activeLeave((string)$employee['id']))throw new \RuntimeException('유효한 휴직 이력이 없습니다.');if($type==='RETIREMENT'&&$employee['employment_status']==='RETIRED')throw new \RuntimeException('이미 퇴직한 직원입니다.');}}
    private function requireAction(string $id,bool $includeDeleted=false): array { $row=$id!==''?$this->repository->find($id,$includeDeleted):null;if(!$row)throw new \RuntimeException('인사발령을 찾을 수 없습니다.');return $row; }
    private function identity(): array { $actor=ActorHelper::user();$parsed=ActorHelper::parse($actor);$id=trim((string)($parsed['id']??''));if($id==='')throw new \RuntimeException('로그인 사용자 정보를 확인할 수 없습니다.');return[$id,$actor]; }
    private function activeCode(string $group,mixed $value,string $label): string { $code=trim((string)$value);$resolved=$code===''?null:$this->codes->resolveActiveCode($group,$code,'');if($resolved===null)throw new \InvalidArgumentException($label.'의 활성 코드값을 확인해 주세요.');return $resolved; }
    private function date(string $value,string $label): string { $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$d||$d->format('Y-m-d')!==$value)throw new \InvalidArgumentException($label.'을 확인해 주세요.');return$value; }
    private function nullable(mixed $value): ?string { $value=trim((string)$value);return$value===''?null:$value; }
    private function requiredReference(string $kind,mixed $value,string $label): string { $id=$this->nullable($value);if($id===null||!$this->repository->referenceExists($kind,$id))throw new \InvalidArgumentException($label.'을 확인해 주세요.');return$id; }
    private function requiredOwnedReference(string $kind,mixed $value,string $employeeId,string $label): string { $id=$this->nullable($value);if($id===null||!$this->repository->ownedReferenceExists($kind,$id,$employeeId))throw new \InvalidArgumentException($label.'을 확인해 주세요.');return$id; }
    private function different(?string $before,?string $after,string $message): void { if($before===$after)throw new \InvalidArgumentException($message); }
    private function actionNo(): string { return 'PA-'.date('YmdHis').'-'.strtoupper(substr(str_replace('-','',UuidHelper::generate()),0,6)); }
    private function beforeLabel(array $employee,string $type): ?string { return match($type){PersonnelActionChangePolicy::EMPLOYMENT_STATUS=>$employee['employment_status'],PersonnelActionChangePolicy::DEPARTMENT=>$employee['department_name'],PersonnelActionChangePolicy::POSITION=>$employee['position_name'],PersonnelActionChangePolicy::JOB=>$employee['job_name'],default=>null}; }
    private function transaction(callable $callback): array { $owned=!$this->pdo->inTransaction();$savepoint='personnel_action_save';if($owned)$this->pdo->beginTransaction();else$this->pdo->exec("SAVEPOINT {$savepoint}");try{$r=$callback();if($owned)$this->pdo->commit();else$this->pdo->exec("RELEASE SAVEPOINT {$savepoint}");$this->logger->info('인사발령 업무 처리를 완료했습니다.',['event_code'=>'PERSONNEL_ACTION_COMPLETED','result'=>'SUCCESS']);return$r;}catch(\Throwable$e){if($owned&&$this->pdo->inTransaction())$this->pdo->rollBack();elseif(!$owned&&$this->pdo->inTransaction()){$this->pdo->exec("ROLLBACK TO SAVEPOINT {$savepoint}");$this->pdo->exec("RELEASE SAVEPOINT {$savepoint}");}$this->logOperationException($e);throw$e;} }
    private function loggedDirect(string $action, callable $callback): array { try{$result=$callback();$this->logger->info('인사발령 업무 처리를 완료했습니다.',['event_code'=>'PERSONNEL_ACTION_'.strtoupper($action),'result'=>'SUCCESS','action'=>$action]);return$result;}catch(\Throwable$e){$this->logOperationException($e);throw$e;} }
    private function logOperationException(\Throwable $exception): void { $failed=$exception instanceof \PDOException;$level=$failed?'error':'warning';$this->logger->{$level}($failed?'인사발령 업무 처리에 실패했습니다.':'인사발령 업무 처리가 차단되었습니다.',['event_code'=>$failed?'PERSONNEL_ACTION_FAILED':'PERSONNEL_ACTION_BLOCKED','result'=>$failed?'FAILED':'BLOCKED','error_code'=>get_class($exception),'error'=>$exception]); }
}
