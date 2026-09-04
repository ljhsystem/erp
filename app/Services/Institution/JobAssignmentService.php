<?php

namespace App\Services\Institution;

use App\Models\Institution\EmployeeAssignmentAuditModel;
use App\Models\Institution\JobAssignmentModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use DateTimeImmutable;
use PDO;
use Psr\Log\LoggerInterface;

class JobAssignmentService
{
    private JobAssignmentModel $model;
    private EmployeeAssignmentAuditModel $audits;
    private LoggerInterface $logger;

    public function __construct(private readonly PDO $db)
    {
        $this->model = new JobAssignmentModel($db);
        $this->audits = new EmployeeAssignmentAuditModel($db);
        $this->logger = LoggerFactory::getLogger('service-institution-job-assignment');
    }

    public function list(array $query): array
    {
        $date = $this->date((string) ($query['as_of_date'] ?? $this->filterDate($query) ?? date('Y-m-d')));
        $page = $this->model->page($query, $date);
        return ['success' => true, 'data' => $page['rows'], 'draw' => (int) ($query['draw'] ?? 0), 'recordsTotal' => $page['total'], 'recordsFiltered' => $page['filtered'], 'as_of_date' => $date];
    }

    public function detail(string $employeeId): array
    {
        $this->uuid($employeeId, '직원을 선택해 주세요.');
        $row = $this->model->detail($employeeId);
        if (!$row) throw new \RuntimeException('직원 정보를 찾을 수 없습니다.');
        $directIds = $this->audits->createAssignmentIds($employeeId);
        foreach ($row['job_assignments'] as &$assignment) $assignment['direct_registration'] = $this->isDirect('JOB', $assignment, $directIds);
        unset($assignment);
        foreach ($row['project_assignments'] as &$assignment) $assignment['direct_registration'] = $this->isDirect('PROJECT', $assignment, $directIds);
        unset($assignment);
        return ['success' => true, 'data' => $row];
    }

    public function options(): array { return $this->model->options(); }

    public function saveHistory(array $input): array
    {
        return $this->transaction(function () use ($input): array {
            if ($existing = $this->idempotent($input, 'JOB', 'CREATE')) return $existing;
            $employeeId = $this->uuid((string) ($input['employee_id'] ?? ''), '직원을 선택해 주세요.');
            $jobId = $this->uuid((string) ($input['job_id'] ?? ''), '직무를 선택해 주세요.');
            $startDate = $this->date((string) ($input['start_date'] ?? ''));
            $endDate = $this->date((string) ($input['end_date'] ?? ''));
            if ($endDate < $startDate) throw new \InvalidArgumentException('종료일은 시작일보다 빠를 수 없습니다.');
            if ($endDate >= date('Y-m-d')) throw new \InvalidArgumentException('종료된 과거 직무 이력만 직접 등록할 수 있습니다.');
            $source = $this->source($input, ['INITIAL_MIGRATION', 'PRE_PERSONNEL_ACTION_HISTORY']);
            $reason = $this->reason($input);
            $requestKey = $this->requestKey($input);
            $employee = $this->employeePeriod($employeeId, $startDate, $endDate);
            if (!$this->model->job($jobId)) throw new \InvalidArgumentException('직무 정보를 찾을 수 없습니다.');
            if ($this->model->jobOverlaps($employeeId, $startDate, $endDate)) throw new \InvalidArgumentException('기존 직무 이력과 기간이 중복됩니다.');
            $officialDate = $this->model->earliestOfficialJobDate($employeeId);
            if ($officialDate !== null && $endDate >= $officialDate) throw new \InvalidArgumentException('인사발령 직무 이력 이후 기간은 직접 등록할 수 없습니다.');
            $actor = ActorHelper::user();
            $id = UuidHelper::generate();
            $row = ['id'=>$id,'employee_id'=>$employeeId,'job_id'=>$jobId,'start_date'=>$startDate,'end_date'=>$endDate,'status_code'=>'ENDED','assignment_personnel_action_target_id'=>null,'end_personnel_action_target_id'=>null,'created_by'=>$actor];
            $this->model->insertJob($row);
            $saved = $this->model->assignment('JOB', $id) ?? $row;
            $audit = $this->record('JOB', $id, $employee['id'], 'CREATE', $source, $reason, $requestKey, null, $saved, $actor);
            return $this->result($saved, $audit, false);
        });
    }

    public function saveProject(array $input): array
    {
        return $this->transaction(function () use ($input): array {
            if ($existing = $this->idempotent($input, 'PROJECT', 'CREATE')) return $existing;
            if ((int) ($input['is_primary'] ?? 0) === 1) throw new \InvalidArgumentException('주 프로젝트 배치는 인사발령으로만 등록할 수 있습니다.');
            $employeeId = $this->uuid((string) ($input['employee_id'] ?? ''), '직원을 선택해 주세요.');
            $projectId = $this->uuid((string) ($input['project_id'] ?? ''), '프로젝트를 선택해 주세요.');
            $jobId = trim((string) ($input['job_id'] ?? ''));
            if ($jobId !== '') { $this->uuid($jobId, '배치 직무를 확인해 주세요.'); if (!$this->model->job($jobId)) throw new \InvalidArgumentException('배치 직무를 찾을 수 없습니다.'); }
            $startDate = $this->date((string) ($input['start_date'] ?? ''));
            $endDate = $this->nullableDate($input['end_date'] ?? null);
            if ($endDate !== null && $endDate < $startDate) throw new \InvalidArgumentException('종료일은 시작일보다 빠를 수 없습니다.');
            $source = $this->source($input, ['INITIAL_MIGRATION','DIRECT_PROJECT_ASSIGNMENT','TEMPORARY_PROJECT_ASSIGNMENT','CONCURRENT_PROJECT_ASSIGNMENT']);
            $reason = $this->reason($input);
            $requestKey = $this->requestKey($input);
            $employee = $this->employeePeriod($employeeId, $startDate, $endDate);
            $project = $this->model->project($projectId);
            if (!$project || (int) ($project['is_active'] ?? 0) !== 1) throw new \InvalidArgumentException('종료되거나 비활성인 프로젝트에는 배치할 수 없습니다.');
            $this->projectPeriod($project, $startDate, $endDate);
            if ($this->model->leaveOverlaps($employeeId, $startDate, $endDate)) throw new \InvalidArgumentException('휴직 기간에는 프로젝트 배치를 직접 등록할 수 없습니다.');
            if ($this->model->projectOverlaps($employeeId, $projectId, $startDate, $endDate)) throw new \InvalidArgumentException('동일 프로젝트 배치 기간이 중복됩니다.');
            $actor = ActorHelper::user();
            $status = EmployeeAssignmentResolver::effectiveStatus($startDate, $endDate, date('Y-m-d'));
            $id = UuidHelper::generate();
            $row = ['id'=>$id,'employee_id'=>$employeeId,'project_id'=>$projectId,'job_id'=>$jobId===''?null:$jobId,'assignment_role'=>$this->nullable($input['assignment_role']??null),'start_date'=>$startDate,'end_date'=>$endDate,'is_primary'=>0,'status_code'=>$status,'assignment_personnel_action_target_id'=>null,'end_personnel_action_target_id'=>null,'created_by'=>$actor];
            $this->model->insertProject($row);
            $saved = $this->model->assignment('PROJECT', $id) ?? $row;
            $audit = $this->record('PROJECT', $id, $employee['id'], 'CREATE', $source, $reason, $requestKey, null, $saved, $actor);
            return $this->result($saved, $audit, false);
        });
    }

    public function endProject(array $input): array
    {
        return $this->transaction(function () use ($input): array {
            if ($existing = $this->idempotent($input, 'PROJECT', 'END')) return $existing;
            $id = $this->uuid((string) ($input['assignment_id'] ?? ''), '종료할 프로젝트 배치를 선택해 주세요.');
            $endDate = $this->date((string) ($input['end_date'] ?? ''));
            if ($endDate > date('Y-m-d')) throw new \InvalidArgumentException('미래 종료일은 등록할 수 없습니다.');
            $before = $this->directAssignment('PROJECT', $id);
            if ((int) $before['is_primary'] === 1) throw new \RuntimeException('주 프로젝트 배치는 직접 종료할 수 없습니다.');
            if ($before['end_date'] !== null) throw new \RuntimeException('이미 종료일이 등록된 배치입니다.');
            if (!in_array($before['status_code'], ['PLANNED','ACTIVE'], true)) throw new \RuntimeException('이미 종료되었거나 취소된 배치입니다.');
            if ($endDate < $before['start_date']) throw new \InvalidArgumentException('종료일은 시작일보다 빠를 수 없습니다.');
            $source = $this->source($input, ['DIRECT_PROJECT_ASSIGNMENT','TEMPORARY_PROJECT_ASSIGNMENT','CONCURRENT_PROJECT_ASSIGNMENT','ADMIN_CORRECTION','DATA_CONSISTENCY_REPAIR']);
            $reason = $this->reason($input); $requestKey = $this->requestKey($input); $actor = ActorHelper::user();
            $endStatus=EmployeeAssignmentResolver::effectiveStatus((string)$before['start_date'],$endDate,date('Y-m-d'),(string)$before['status_code']);
            $this->model->updateAssignment('PROJECT', $id, ['end_date'=>$endDate,'status_code'=>$endStatus,'updated_at'=>date('Y-m-d H:i:s'),'updated_by'=>$actor]);
            $after = $this->model->assignment('PROJECT', $id) ?? [];
            $audit = $this->record('PROJECT', $id, (string)$before['employee_id'], 'END', $source, $reason, $requestKey, $before, $after, $actor);
            return $this->result($after, $audit, false);
        });
    }

    public function correct(array $input): array
    {
        return $this->transaction(function () use ($input): array {
            $domain = strtoupper(trim((string) ($input['assignment_domain'] ?? '')));
            if (!in_array($domain, ['JOB','PROJECT'], true)) throw new \InvalidArgumentException('정정할 배치 도메인을 확인해 주세요.');
            if ($existing = $this->idempotent($input, $domain, 'CORRECT')) return $existing;
            $id = $this->uuid((string) ($input['assignment_id'] ?? ''), '정정할 이력을 선택해 주세요.');
            $before = $this->directAssignment($domain, $id);
            $source = $this->source($input, ['ADMIN_CORRECTION','DATA_CONSISTENCY_REPAIR']);
            $reason = $this->reason($input); $requestKey = $this->requestKey($input); $actor = ActorHelper::user();
            if ($domain === 'JOB') $updates = $this->correctJob($input, $before);
            else $updates = $this->correctProject($input, $before);
            $updates['updated_at'] = date('Y-m-d H:i:s'); $updates['updated_by'] = $actor;
            $this->model->updateAssignment($domain, $id, $updates);
            $after = $this->model->assignment($domain, $id) ?? [];
            if ($this->snapshot($before) === $this->snapshot($after)) throw new \InvalidArgumentException('정정할 변경사항이 없습니다.');
            $audit = $this->record($domain, $id, (string)$before['employee_id'], 'CORRECT', $source, $reason, $requestKey, $before, $after, $actor);
            return $this->result($after, $audit, false);
        });
    }

    private function correctJob(array $input, array $before): array
    {
        $jobId = $this->uuid((string) ($input['job_id'] ?? $before['job_id']), '직무를 선택해 주세요.');
        $start = $this->date((string) ($input['start_date'] ?? $before['start_date']));
        $end = $this->date((string) ($input['end_date'] ?? $before['end_date']));
        if ($end < $start || $end >= date('Y-m-d')) throw new \InvalidArgumentException('종료된 과거 직무 기간을 확인해 주세요.');
        $this->employeePeriod((string)$before['employee_id'], $start, $end);
        if (!$this->model->job($jobId)) throw new \InvalidArgumentException('직무 정보를 찾을 수 없습니다.');
        if ($this->model->jobOverlaps((string)$before['employee_id'], $start, $end, (string)$before['id'])) throw new \InvalidArgumentException('기존 직무 이력과 기간이 중복됩니다.');
        $officialDate = $this->model->earliestOfficialJobDate((string)$before['employee_id']);
        if ($officialDate !== null && $end >= $officialDate) throw new \InvalidArgumentException('인사발령 직무 이력 이후 기간은 정정할 수 없습니다.');
        return ['job_id'=>$jobId,'start_date'=>$start,'end_date'=>$end,'status_code'=>'ENDED'];
    }

    private function correctProject(array $input, array $before): array
    {
        if ((int)($input['is_primary']??0)===1 || (int)$before['is_primary']===1) throw new \RuntimeException('주 프로젝트 배치는 직접 정정할 수 없습니다.');
        $projectId = $this->uuid((string) ($input['project_id'] ?? $before['project_id']), '프로젝트를 선택해 주세요.');
        $start = $this->date((string) ($input['start_date'] ?? $before['start_date']));
        $end = $this->nullableDate($input['end_date'] ?? $before['end_date']);
        if ($end !== null && $end < $start) throw new \InvalidArgumentException('프로젝트 배치 기간을 확인해 주세요.');
        $this->employeePeriod((string)$before['employee_id'], $start, $end);
        $project = $this->model->project($projectId);
        if (!$project || (int)($project['is_active']??0)!==1) throw new \InvalidArgumentException('활성 프로젝트를 선택해 주세요.');
        $this->projectPeriod($project, $start, $end);
        if ($this->model->leaveOverlaps((string)$before['employee_id'], $start, $end)) throw new \InvalidArgumentException('휴직 기간과 배치 기간이 중복됩니다.');
        if ($this->model->projectOverlaps((string)$before['employee_id'], $projectId, $start, $end, (string)$before['id'])) throw new \InvalidArgumentException('동일 프로젝트 배치 기간이 중복됩니다.');
        $jobId = trim((string)($input['job_id']??$before['job_id']??''));
        if ($jobId!=='' && !$this->model->job($this->uuid($jobId,'배치 직무를 확인해 주세요.'))) throw new \InvalidArgumentException('배치 직무를 찾을 수 없습니다.');
        $status=EmployeeAssignmentResolver::effectiveStatus($start,$end,date('Y-m-d'),(string)($before['status_code']??''));
        return ['project_id'=>$projectId,'job_id'=>$jobId===''?null:$jobId,'assignment_role'=>$this->nullable($input['assignment_role']??$before['assignment_role']??null),'start_date'=>$start,'end_date'=>$end,'is_primary'=>0,'status_code'=>$status];
    }

    private function employeePeriod(string $employeeId, string $start, ?string $end): array
    {
        $employee = $this->model->employee($employeeId, true);
        if (!$employee) throw new \InvalidArgumentException('직원 정보를 찾을 수 없습니다.');
        $hire = $employee['real_hire_date'] ?: $employee['doc_hire_date'];
        $retire = $employee['real_retire_date'] ?: $employee['doc_retire_date'];
        if ($hire && $start < $hire) throw new \InvalidArgumentException('입사일 이전에는 배치할 수 없습니다.');
        if ($retire && ($end ?? $start) > $retire) throw new \InvalidArgumentException('퇴사일 이후에는 배치할 수 없습니다.');
        return $employee;
    }

    private function projectPeriod(array $project, string $start, ?string $end): void
    {
        if (!empty($project['start_date']) && $start < $project['start_date']) throw new \InvalidArgumentException('프로젝트 시작일 이전에는 배치할 수 없습니다.');
        if (!empty($project['completion_date']) && ($end === null || $end > $project['completion_date'])) throw new \InvalidArgumentException('프로젝트 완료일 이후에는 배치할 수 없습니다.');
    }

    private function directAssignment(string $domain, string $id): array
    {
        $row = $this->model->assignment($domain, $id, true);
        if (!$row) throw new \RuntimeException('배치 이력을 찾을 수 없습니다.');
        if (!empty($row['assignment_personnel_action_target_id']) || !empty($row['end_personnel_action_target_id']) || !$this->audits->hasCreateAudit($domain, $id)) {
            throw new \RuntimeException('인사발령 또는 기존 이력은 직접 종료·정정할 수 없습니다.');
        }
        return $row;
    }

    private function isDirect(string $domain, array $row, array $directIds): bool
    {
        return empty($row['assignment_personnel_action_target_id']) && empty($row['end_personnel_action_target_id']) && !empty($directIds[$domain][(string)$row['id']]);
    }

    private function idempotent(array $input, string $domain, string $action): ?array
    {
        $requestKey = $this->requestKey($input);
        $audit = $this->audits->findByRequestKey($requestKey);
        if (!$audit) return null;
        if ($audit['assignment_domain'] !== $domain || $audit['action_type'] !== $action) throw new \RuntimeException('이미 다른 작업에 사용된 요청 식별값입니다.');
        if ((string) $audit['processed_by'] !== ActorHelper::user()) throw new \RuntimeException('다른 사용자가 처리한 요청 식별값입니다.');
        $column = $domain === 'JOB' ? 'job_assignment_id' : 'project_assignment_id';
        if (!empty($input['assignment_id']) && (string) $input['assignment_id'] !== (string) $audit[$column]) throw new \RuntimeException('다른 배치에 사용된 요청 식별값입니다.');
        if (!empty($input['employee_id']) && (string) $input['employee_id'] !== (string) $audit['employee_id']) throw new \RuntimeException('다른 직원에게 사용된 요청 식별값입니다.');
        $assignment = $this->model->assignment($domain, (string)$audit[$column]);
        if (!$assignment) throw new \RuntimeException('기존 요청의 저장 결과를 찾을 수 없습니다.');
        foreach (['job_id','project_id','start_date','end_date','assignment_role'] as $field) {
            if (!array_key_exists($field, $input) || trim((string) $input[$field]) === '') continue;
            if ((string) $input[$field] !== (string) ($assignment[$field] ?? '')) throw new \RuntimeException('기존 요청과 입력값이 일치하지 않습니다.');
        }
        return $this->result($assignment, $audit, true);
    }

    private function record(string $domain,string $id,string $employeeId,string $action,string $source,string $reason,string $requestKey,?array $before,array $after,string $actor): array
    {
        if (!$this->model->activeCode('EMPLOYEE_ASSIGNMENT_AUDIT_ACTION',$action)) throw new \RuntimeException('배치 감사 작업코드가 등록되지 않았습니다.');
        return $this->audits->record(['assignment_domain'=>$domain,'assignment_id'=>$id,'employee_id'=>$employeeId,'action_type'=>$action,'source_type'=>$source,'reason'=>$reason,'request_key'=>$requestKey,'before_data'=>$before===null?null:$this->snapshot($before),'after_data'=>$this->snapshot($after),'processed_by'=>$actor]);
    }

    private function result(array $assignment,array $audit,bool $idempotent): array { return ['success'=>true,'data'=>['assignment'=>$assignment,'audit'=>$audit,'idempotent'=>$idempotent],'message'=>$idempotent?'기존 처리 결과를 반환했습니다.':'처리되었습니다.']; }
    private function source(array $input,array $allowed): string { $source=strtoupper(trim((string)($input['source_type']??''))); if(!in_array($source,$allowed,true)||!$this->model->activeCode('EMPLOYEE_ASSIGNMENT_SOURCE',$source))throw new \InvalidArgumentException('등록 출처를 확인해 주세요.'); return $source; }
    private function reason(array $input): string { $reason=trim((string)($input['reason']??'')); if($reason===''||mb_strlen($reason)>1000)throw new \InvalidArgumentException('처리 사유를 1,000자 이내로 입력해 주세요.'); return $reason; }
    private function requestKey(array $input): string { $key=trim((string)($input['request_key']??'')); if(!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',$key))throw new \InvalidArgumentException('요청 식별값을 확인해 주세요.'); return strtolower($key); }
    private function uuid(string $value,string $message): string { if(!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',$value))throw new \InvalidArgumentException($message); return strtolower($value); }
    private function date(string $value): string { $value=trim($value);$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$parsed||$parsed->format('Y-m-d')!==$value)throw new \InvalidArgumentException('날짜를 YYYY-MM-DD 형식으로 입력해 주세요.');return $value; }
    private function nullableDate(mixed $value): ?string { $value=trim((string)$value);return $value===''?null:$this->date($value); }
    private function nullable(mixed $value): ?string { $value=trim((string)$value);return $value===''?null:$value; }
    private function snapshot(array $row): array { return array_diff_key($row,array_flip(['created_at','created_by','updated_at','updated_by'])); }
    private function filterDate(array $query): ?string { $filters=json_decode((string)($query['filters']??''),true);foreach(is_array($filters)?$filters:[] as $filter)if(($filter['field']??'')==='as_of_date')return(string)($filter['value']??'');return null; }
    private function transaction(callable $callback): array { $owned=!$this->db->inTransaction();if($owned)$this->db->beginTransaction();try{$result=$callback();if($owned){$this->db->commit();$this->logger->info('직원 배치정보 변경이 완료되었습니다.',['event_code'=>'JOB_ASSIGNMENT_CHANGED','result'=>'SUCCESS','service'=>self::class,'action'=>'change','actor'=>ActorHelper::user()]);}return$result;}catch(\InvalidArgumentException|\DomainException$e){if($owned&&$this->db->inTransaction())$this->db->rollBack();if($owned)$this->logger->warning('직원 배치정보 변경이 차단되었습니다.',['event_code'=>'JOB_ASSIGNMENT_CHANGE_BLOCKED','result'=>'BLOCKED','service'=>self::class,'action'=>'change','actor'=>ActorHelper::user(),'error_code'=>get_class($e),'error'=>$e]);throw$e;}catch(\Throwable $e){if($owned&&$this->db->inTransaction())$this->db->rollBack();if($owned)$this->logger->error('직원 배치정보 변경에 실패했습니다.',['event_code'=>'JOB_ASSIGNMENT_CHANGE_FAILED','result'=>'FAILED','service'=>self::class,'action'=>'change','actor'=>ActorHelper::user(),'error_code'=>get_class($e),'error'=>$e]);throw$e;} }
}
