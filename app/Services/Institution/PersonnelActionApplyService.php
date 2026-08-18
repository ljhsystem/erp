<?php

namespace App\Services\Institution;

use App\Repositories\Institution\PersonnelActionRepository;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PDO;

class PersonnelActionApplyService
{
    private PersonnelActionRepository $repository;

    public function __construct(private readonly PDO $pdo)
    {
        $this->repository = new PersonnelActionRepository($pdo);
    }

    public function apply(string $actionId, ?string $actor = null): array
    {
        $actor ??= ActorHelper::user();
        $failedTargetId = null;
        try {
            return $this->transaction(function () use ($actionId, $actor, &$failedTargetId): array {
            $action = $this->repository->find($actionId, false, true);
            if (!$action) throw new \RuntimeException('인사발령을 찾을 수 없습니다.');
            PersonnelActionChangePolicy::assertSupportedActionType((string) $action['action_type_code']);
            if ((string) $action['business_status'] === 'APPLIED') return ['applied' => false, 'already_applied' => true];
            if ((string) $action['business_status'] !== 'APPROVED') throw new \RuntimeException('승인 완료된 인사발령만 적용할 수 있습니다.');
            if ((string) $action['action_date'] > date('Y-m-d')) throw new \RuntimeException('아직 효력일이 도래하지 않았습니다.');
            $targets = $this->repository->targets($actionId, true);
            if ($targets === []) throw new \RuntimeException('적용할 대상자가 없습니다.');
            foreach ($targets as $target) {
                if ((string) $target['application_status'] === 'APPLIED') continue;
                $failedTargetId = (string) $target['id'];
                try {
                    $this->applyTarget($action, $target, $actor);
                    $this->repository->updateTargetResult((string) $target['id'], 'APPLIED', null, $actor);
                } catch (\Throwable $exception) {
                    throw new \RuntimeException($target['employee_name'] . ': ' . $exception->getMessage(), 0, $exception);
                }
            }
            $this->repository->completeAction($actionId, $actor);
            return ['applied' => true, 'already_applied' => false];
            });
        } catch (\Throwable $exception) {
            if ($failedTargetId !== null && !$this->pdo->inTransaction()) {
                $this->repository->updateTargetResult($failedTargetId, 'FAILED', mb_substr($exception->getMessage(), 0, 1000), $actor);
            }
            throw $exception;
        }
    }

    private function applyTarget(array $action, array $target, string $actor): void
    {
        $employeeId = (string) $target['employee_id'];
        $targetId = (string) $target['id'];
        $employee = $this->repository->employee($employeeId, true);
        if (!$employee) throw new \RuntimeException('직원 정보를 찾을 수 없습니다.');
        $hireDate = $employee['real_hire_date'] ?: $employee['doc_hire_date'];
        if ($hireDate && (string) $action['action_date'] < (string) $hireDate) {
            throw new \RuntimeException('발령 적용일은 직원의 실입사일 또는 문서상 입사일보다 빠를 수 없습니다.');
        }
        $changes = $this->repository->targetChanges($targetId);
        if ($changes === []) throw new \RuntimeException('변경항목이 없습니다.');
        PersonnelActionChangePolicy::assertCommandSet((string) $action['action_type_code'], $changes);
        foreach ($changes as $change) $this->assertBefore($employee, $change);
        foreach ($changes as $change) {
            $this->applyChange($employee, $targetId, $change, $actor);
            $employee = $this->repository->employee($employeeId, true) ?? $employee;
        }
    }

    private function assertBefore(array $employee, array $change): void
    {
        $type = (string) $change['change_type_code'];
        $map = [
            'EMPLOYMENT_STATUS' => ['employment_status','before_employment_status'],
            'DEPARTMENT' => ['department_id','before_department_id'],
            'POSITION' => ['position_id','before_position_id'],
            'JOB' => ['job_id','before_job_id'],
        ];
        if (isset($map[$type])) {
            [$employeeField,$beforeField] = $map[$type];
            if (($employee[$employeeField] ?? null) !== ($change[$beforeField] ?? null)) {
                throw new \RuntimeException($type . ' 변경 전 값과 현재 SSOT가 달라 적용이 중단되었습니다.');
            }
        }
        if ($type === 'HIRE_DATE' || $type === 'RETIRE_DATE') {
            $prefix = $change['date_kind'] === 'ACTUAL' ? 'real_' : 'doc_';
            $field = $prefix . ($type === 'HIRE_DATE' ? 'hire_date' : 'retire_date');
            if (($employee[$field] ?? null) !== ($change['before_date'] ?? null)) throw new \RuntimeException('입·퇴사일 변경 전 값이 현재 SSOT와 다릅니다.');
        }
    }

    private function applyChange(array $employee, string $targetId, array $change, string $actor): void
    {
        $employeeId = (string) $employee['id'];
        $date = (string) $change['effective_date'];
        $previousDate = date('Y-m-d', strtotime($date . ' -1 day'));
        switch ((string) $change['change_type_code']) {
            case 'EMPLOYMENT_STATUS':
                $current = $this->repository->currentEmploymentHistory($employeeId);
                if ($current) $this->repository->closeEmploymentHistory((string) $current['id'], max((string) $current['effective_date'], $previousDate), $actor);
                $this->repository->insertHistory('institution_job_assignments_employment_status_histories', [
                    'id'=>UuidHelper::generate(),'employee_id'=>$employeeId,'status_code'=>$change['after_employment_status'],
                    'effective_date'=>$date,'ended_date'=>null,'reason'=>$change['after_display_snapshot'],
                    'source_personnel_action_target_id'=>$targetId,'created_by'=>$actor,
                ]);
                $this->repository->updateEmployee($employeeId, ['employment_status'=>$change['after_employment_status']]);
                break;
            case 'DEPARTMENT':
                $this->replacePeriod('institution_job_assignments_department_histories','department_id',$employeeId,$change['after_department_id'],$date,$targetId,$actor);
                $this->repository->updateEmployee($employeeId, ['department_id'=>$change['after_department_id']]);
                break;
            case 'POSITION':
                $this->replacePeriod('institution_job_assignments_position_histories','position_id',$employeeId,$change['after_position_id'],$date,$targetId,$actor);
                $this->repository->updateEmployee($employeeId, ['position_id'=>$change['after_position_id']]);
                break;
            case 'JOB':
                $current=$this->repository->currentJob($employeeId); if($current)$this->repository->closeJob((string)$current['id'],max((string)$current['start_date'],$previousDate),$targetId,$actor);
                if ($change['after_job_id'] !== null) $this->repository->insertHistory('institution_job_assignments_job_histories',['id'=>UuidHelper::generate(),'employee_id'=>$employeeId,'job_id'=>$change['after_job_id'],'start_date'=>$date,'end_date'=>null,'status_code'=>'ACTIVE','assignment_personnel_action_target_id'=>$targetId,'end_personnel_action_target_id'=>null,'created_by'=>$actor]);
                $this->repository->updateEmployee($employeeId,['job_id'=>$change['after_job_id']]);
                break;
            case 'LEAVE':
                $this->repository->insertHistory('institution_job_assignments_leave_periods',['id'=>UuidHelper::generate(),'employee_id'=>$employeeId,'leave_type_code'=>$change['leave_type_code'],'start_date'=>$change['leave_start_date'],'planned_end_date'=>$change['leave_planned_end_date'],'actual_end_date'=>null,'status_code'=>'ACTIVE','reason'=>$change['after_display_snapshot'],'leave_personnel_action_target_id'=>$targetId,'return_personnel_action_target_id'=>null,'created_by'=>$actor]);
                break;
            case 'RETURN_FROM_LEAVE':
                $leave=$this->repository->activeLeave($employeeId); if(!$leave || (string)$leave['id']!==(string)$change['leave_period_id']) throw new \RuntimeException('복직 대상 휴직 이력이 유효하지 않습니다.');
                $this->repository->finishLeave((string)$leave['id'],(string)$change['leave_actual_end_date'],$targetId,$actor);
                break;
            case 'PROJECT_ASSIGNMENT':
                $projectStatus=EmployeeAssignmentResolver::effectiveStatus((string)$change['assignment_start_date'],$change['assignment_end_date']!==null?(string)$change['assignment_end_date']:null,date('Y-m-d'));
                $this->repository->insertHistory('institution_job_assignments_project_histories',['id'=>UuidHelper::generate(),'employee_id'=>$employeeId,'project_id'=>$change['project_id'],'job_id'=>$change['assignment_job_id'],'assignment_role'=>$change['assignment_role'],'start_date'=>$change['assignment_start_date'],'end_date'=>$change['assignment_end_date'],'is_primary'=>$change['is_primary_assignment'],'status_code'=>$projectStatus,'assignment_personnel_action_target_id'=>$targetId,'end_personnel_action_target_id'=>null,'created_by'=>$actor]);
                break;
            case 'PROJECT_RELEASE':
                $assignment=$this->repository->projectAssignment((string)$change['project_assignment_id'],$employeeId); if(!$assignment)throw new \RuntimeException('해제 대상 프로젝트 배치를 찾을 수 없습니다.');
                $releaseStatus=EmployeeAssignmentResolver::effectiveStatus((string)$assignment['start_date'],(string)$change['assignment_end_date'],date('Y-m-d'),(string)$assignment['status_code']);
                $this->repository->closeProject((string)$assignment['id'],(string)$change['assignment_end_date'],$targetId,$actor,$releaseStatus); break;
            case 'WORKPLACE':
                $current=$this->repository->currentWorkplace($employeeId); if($current)$this->repository->closeWorkplace((string)$current['id'],max((string)$current['start_date'],$previousDate),$targetId,$actor);
                $this->repository->insertHistory('institution_job_assignments_workplace_histories',['id'=>UuidHelper::generate(),'employee_id'=>$employeeId,'workplace_type_code'=>$change['workplace_type_code'],'project_id'=>$change['workplace_project_id'],'workplace_name_snapshot'=>$change['workplace_name_snapshot'],'workplace_address_snapshot'=>$change['workplace_address_snapshot'],'start_date'=>$date,'end_date'=>null,'status_code'=>'ACTIVE','assignment_personnel_action_target_id'=>$targetId,'end_personnel_action_target_id'=>null,'created_by'=>$actor]); break;
            case 'HIRE_DATE': case 'RETIRE_DATE':
                $prefix=$change['date_kind']==='ACTUAL'?'real_':'doc_'; $field=$prefix.($change['change_type_code']==='HIRE_DATE'?'hire_date':'retire_date');
                $this->repository->updateEmployee($employeeId,[$field=>$change['after_date']]); break;
            default:
                throw new \RuntimeException('지원하지 않는 인사발령 변경명령입니다.');
        }
    }

    private function replacePeriod(string $table,string $valueColumn,string $employeeId,?string $value,string $date,string $targetId,string $actor): void
    {
        $current=$this->repository->currentPeriod($table,'employee_id',$employeeId);
        if($current)$this->repository->closePeriod($table,(string)$current['id'],'effective_to',max((string)$current['effective_from'],date('Y-m-d',strtotime($date.' -1 day'))),'end_action_target_id',$targetId,$actor);
        if($value!==null)$this->repository->insertHistory($table,['id'=>UuidHelper::generate(),'employee_id'=>$employeeId,$valueColumn=>$value,'effective_from'=>$date,'effective_to'=>null,'start_action_target_id'=>$targetId,'end_action_target_id'=>null,'created_by'=>$actor]);
    }

    private function transaction(callable $callback): array
    {
        $owned=!$this->pdo->inTransaction(); if($owned)$this->pdo->beginTransaction();
        try{$result=$callback();if($owned)$this->pdo->commit();return $result;}catch(\Throwable $e){if($owned&&$this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
}
