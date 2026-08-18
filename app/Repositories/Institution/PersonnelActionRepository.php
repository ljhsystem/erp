<?php

namespace App\Repositories\Institution;

use Core\Helpers\ActorHelper;
use PDO;

class PersonnelActionRepository
{
    public function __construct(private readonly PDO $db) {}

    public function page(array $query, bool $trash = false): array
    {
        $baseWhere = $trash ? 'a.deleted_at IS NOT NULL' : 'a.deleted_at IS NULL';
        $where = [$baseWhere];
        $params = [];
        $keyword = trim((string) ($query['search']['value'] ?? $query['keyword'] ?? ''));
        if ($keyword !== '') {
            $where[] = '(a.action_no LIKE :keyword_action_no
                OR a.action_name LIKE :keyword_action_name
                OR a.action_reason LIKE :keyword_action_reason
                OR EXISTS (
                    SELECT 1
                      FROM institution_personnel_actions_targets keyword_target
                      JOIN user_employees keyword_employee ON keyword_employee.id = keyword_target.employee_id
                     WHERE keyword_target.personnel_action_id = a.id
                       AND keyword_employee.employee_name LIKE :keyword_employee_name
                ))';
            foreach ([':keyword_action_no', ':keyword_action_name', ':keyword_action_reason', ':keyword_employee_name'] as $key) {
                $params[$key] = '%' . $keyword . '%';
            }
        }
        $filters = json_decode((string) ($query['filters'] ?? ''), true);
        foreach (is_array($filters) ? $filters : [] as $index => $filter) {
            $field = trim((string) ($filter['field'] ?? ''));
            $value = $filter['value'] ?? null;
            if ($field === 'keyword' && is_scalar($value) && trim((string) $value) !== '') {
                $keys = [
                    ':filter_action_no_' . $index,
                    ':filter_action_name_' . $index,
                    ':filter_action_reason_' . $index,
                    ':filter_employee_name_' . $index,
                ];
                $where[] = '(a.action_no LIKE ' . $keys[0]
                    . ' OR a.action_name LIKE ' . $keys[1]
                    . ' OR a.action_reason LIKE ' . $keys[2]
                    . ' OR EXISTS (SELECT 1 FROM institution_personnel_actions_targets filter_target'
                    . ' JOIN user_employees filter_employee ON filter_employee.id=filter_target.employee_id'
                    . ' WHERE filter_target.personnel_action_id=a.id AND filter_employee.employee_name LIKE ' . $keys[3] . '))';
                foreach ($keys as $key) {
                    $params[$key] = '%' . trim((string) $value) . '%';
                }
                continue;
            }
            if (in_array($field, ['issued_date', 'action_date'], true) && is_array($value)) {
                $from = trim((string) ($value['start'] ?? ''));
                $to = trim((string) ($value['end'] ?? ''));
                if ($from !== '' && $to !== '') {
                    $where[] = "a.{$field} BETWEEN :from_{$index} AND :to_{$index}";
                    $params[":from_{$index}"] = $from;
                    $params[":to_{$index}"] = $to;
                }
                continue;
            }
            if (!is_scalar($value) || trim((string) $value) === '') continue;
            $key = ':filter_' . $index;
            $params[$key] = trim((string) $value);
            if ($field === 'action_type_code') $where[] = "a.action_type_code = {$key}";
            elseif ($field === 'business_status') $where[] = "a.business_status = {$key}";
            elseif ($field === 'approval_status') {
                $where[] = "EXISTS (SELECT 1 FROM user_approval_requests filter_request WHERE filter_request.id=a.current_approval_request_id AND filter_request.status={$key})";
            } elseif ($field === 'employee_id') {
                $where[] = "EXISTS (SELECT 1 FROM institution_personnel_actions_targets filter_target WHERE filter_target.personnel_action_id=a.id AND filter_target.employee_id={$key})";
            } elseif (in_array($field, ['department_id', 'position_id', 'job_id'], true)) {
                unset($params[$key]);
                $afterKey = ':filter_after_' . $index;
                $currentKey = ':filter_current_' . $index;
                $params[$afterKey] = trim((string) $value);
                $params[$currentKey] = trim((string) $value);
                $currentColumn = $field;
                $afterColumn = match ($field) { 'department_id' => 'after_department_id', 'position_id' => 'after_position_id', default => 'after_job_id' };
                $changeType = match ($field) { 'department_id' => 'DEPARTMENT', 'position_id' => 'POSITION', default => 'JOB' };
                $where[] = "EXISTS (
                    SELECT 1 FROM institution_personnel_actions_targets filter_target
                    JOIN user_employees filter_employee ON filter_employee.id=filter_target.employee_id
                    WHERE filter_target.personnel_action_id=a.id AND (
                        EXISTS (SELECT 1 FROM institution_personnel_actions_changes filter_change
                            WHERE filter_change.personnel_action_target_id=filter_target.id
                              AND filter_change.change_type_code='{$changeType}'
                              AND filter_change.{$afterColumn}={$afterKey})
                        OR (filter_employee.{$currentColumn}={$currentKey} AND NOT EXISTS (
                            SELECT 1 FROM institution_personnel_actions_changes filter_change_current
                            WHERE filter_change_current.personnel_action_target_id=filter_target.id
                              AND filter_change_current.change_type_code='{$changeType}'
                        ))
                    ))";
            } else {
                unset($params[$key]);
            }
        }
        $whereSql = implode(' AND ', $where);
        $orderAllowed = [
            'sort_no' => 'a.sort_no', 'action_no' => 'a.action_no', 'issued_date' => 'a.issued_date',
            'action_date' => 'a.action_date', 'action_name' => 'a.action_name',
            'business_status' => 'a.business_status', 'created_at' => 'a.created_at',
        ];
        $orderIndex = (int) ($query['order'][0]['column'] ?? -1);
        $orderKey = $orderIndex >= 0 ? (string) ($query['columns'][$orderIndex]['data'] ?? '') : '';
        $order = $orderAllowed[$orderKey] ?? 'a.sort_no';
        $direction = strtolower((string) ($query['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $totalStmt = $this->db->prepare("SELECT COUNT(*) FROM institution_personnel_actions a WHERE {$baseWhere}");
        $totalStmt->execute();
        $total = (int) $totalStmt->fetchColumn();
        $filteredStmt = $this->db->prepare("SELECT COUNT(*) FROM institution_personnel_actions a WHERE {$whereSql}");
        $filteredStmt->execute($params);
        $filtered = (int) $filteredStmt->fetchColumn();
        $start = max(0, (int) ($query['start'] ?? 0));
        $length = max(1, min(200, (int) ($query['length'] ?? 50)));
        $sql = "SELECT a.id,a.sort_no,a.action_no,a.action_type_code,a.action_name,
                       a.issued_date,a.action_date,a.action_reason,a.business_status,
                       request.sort_no approval_request_no,original.action_no original_action_no,
                       a.correction_kind,a.approved_at,a.applied_at,a.cancelled_at,a.cancelled_reason,a.note,
                       a.created_by,a.created_at,a.updated_by,a.updated_at,a.deleted_by,a.deleted_at,
                       action_type.code_name action_type_name,
                       business_status.code_name business_status_name,
                       request.status approval_status,
                       target_summary.employee_names,target_summary.target_count,
                       change_summary.change_count,change_summary.change_summary
                  FROM institution_personnel_actions a
                  LEFT JOIN user_approval_requests request ON request.id=a.current_approval_request_id
                  LEFT JOIN institution_personnel_actions original ON original.id=a.original_action_id
                  LEFT JOIN system_codes action_type ON action_type.code_group='PERSONNEL_ACTION_TYPE' AND action_type.code=a.action_type_code AND action_type.is_active=1
                  LEFT JOIN system_codes business_status ON business_status.code_group='PERSONNEL_ACTION_STATUS' AND business_status.code=a.business_status AND business_status.is_active=1
                  LEFT JOIN (
                      SELECT target.personnel_action_id,
                             COUNT(*) target_count,
                             GROUP_CONCAT(employee.employee_name ORDER BY target.sort_no SEPARATOR ', ') employee_names
                        FROM institution_personnel_actions_targets target
                        JOIN user_employees employee ON employee.id=target.employee_id
                       GROUP BY target.personnel_action_id
                  ) target_summary ON target_summary.personnel_action_id=a.id
                  LEFT JOIN (
                      SELECT target.personnel_action_id,COUNT(*) change_count,
                             CONCAT(
                                 SUBSTRING_INDEX(GROUP_CONCAT(CONCAT(COALESCE(change_row.before_display_snapshot,'-'),' → ',COALESCE(change_row.after_display_snapshot,'-')) ORDER BY target.sort_no,change_row.sort_no SEPARATOR ' / '),' / ',2),
                                 IF(COUNT(*)>2,CONCAT(' 외 ',COUNT(*)-2,'건'),'')
                             ) change_summary
                        FROM institution_personnel_actions_targets target
                        JOIN institution_personnel_actions_changes change_row ON change_row.personnel_action_target_id=target.id
                       GROUP BY target.personnel_action_id
                  ) change_summary ON change_summary.personnel_action_id=a.id
                 WHERE {$whereSql}
                 ORDER BY {$order} {$direction}, a.id DESC LIMIT {$start},{$length}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $actorFields = ['created_by_name' => 'created_by', 'updated_by_name' => 'updated_by', 'deleted_by_name' => 'deleted_by'];
        $rows = ActorHelper::enrichActorNames($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], $actorFields);
        return ['rows' => $rows, 'total' => $total, 'filtered' => $filtered];
    }

    public function find(string $id, bool $includeDeleted = false, bool $forUpdate = false): ?array
    {
        $stmt = $this->db->prepare('SELECT a.*,r.status approval_status,r.sort_no approval_request_no,original.action_no original_action_no FROM institution_personnel_actions a LEFT JOIN user_approval_requests r ON r.id=a.current_approval_request_id LEFT JOIN institution_personnel_actions original ON original.id=a.original_action_id WHERE a.id=:id' . ($includeDeleted ? '' : ' AND a.deleted_at IS NULL') . ' LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        return $row ? ActorHelper::enrichActorNamesRow($row, ['created_by_name' => 'created_by', 'updated_by_name' => 'updated_by', 'deleted_by_name' => 'deleted_by']) : null;
    }

    public function targets(string $actionId, bool $forUpdate = false): array
    {
        $stmt = $this->db->prepare('SELECT t.*,e.employee_name,e.employment_status,e.department_id,e.position_id,e.job_id,
            e.doc_hire_date,e.real_hire_date,e.doc_retire_date,e.real_retire_date,
            department.dept_name department_name,position.position_name,job.job_name
            FROM institution_personnel_actions_targets t
            JOIN user_employees e ON e.id=t.employee_id
            LEFT JOIN user_departments department ON department.id=e.department_id
            LEFT JOIN user_positions position ON position.id=e.position_id
            LEFT JOIN institution_job_assignments_jobs job ON job.id=e.job_id
            WHERE t.personnel_action_id=:id ORDER BY t.sort_no' . ($forUpdate ? ' FOR UPDATE' : ''));
        $stmt->execute([':id' => $actionId]);
        return ActorHelper::enrichActorNames($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], ['created_by_name' => 'created_by', 'updated_by_name' => 'updated_by', 'applied_by_name' => 'applied_by']);
    }

    public function changes(string $actionId): array
    {
        $stmt = $this->db->prepare('SELECT c.*,project.project_name,workplace_project.project_name workplace_project_name
            FROM institution_personnel_actions_changes c
            JOIN institution_personnel_actions_targets t ON t.id=c.personnel_action_target_id
            LEFT JOIN system_projects project ON project.id=c.project_id
            LEFT JOIN system_projects workplace_project ON workplace_project.id=c.workplace_project_id
            WHERE t.personnel_action_id=:id ORDER BY t.sort_no,c.sort_no');
        $stmt->execute([':id' => $actionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function employee(string $id, bool $forUpdate = false): ?array
    {
        $stmt = $this->db->prepare('SELECT e.*,d.dept_name department_name,p.position_name,j.job_name FROM user_employees e LEFT JOIN user_departments d ON d.id=e.department_id LEFT JOIN user_positions p ON p.id=e.position_id LEFT JOIN institution_job_assignments_jobs j ON j.id=e.job_id WHERE e.id=:id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
        $stmt->execute([':id' => $id]); return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function referenceExists(string $kind, string $id): bool
    {
        $tables = [
            'department' => ['user_departments', 'is_active=1'],
            'position' => ['user_positions', 'is_active=1'],
            'job' => ['institution_job_assignments_jobs', 'is_active=1 AND deleted_at IS NULL'],
            'project' => ['system_projects', 'deleted_at IS NULL'],
        ];
        if ($id === '' || !isset($tables[$kind])) {
            return false;
        }
        [$table, $condition] = $tables[$kind];
        $stmt = $this->db->prepare("SELECT 1 FROM `{$table}` WHERE id=:id AND {$condition} LIMIT 1");
        $stmt->execute([':id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    public function ownedReferenceExists(string $kind, string $id, string $employeeId): bool
    {
        $tables = [
            'project_assignment' => 'institution_job_assignments_project_histories',
            'workplace_assignment' => 'institution_job_assignments_workplace_histories',
            'leave_period' => 'institution_job_assignments_leave_periods',
        ];
        if ($id === '' || $employeeId === '' || !isset($tables[$kind])) {
            return false;
        }
        $stmt = $this->db->prepare("SELECT 1 FROM `{$tables[$kind]}` WHERE id=:id AND employee_id=:employee LIMIT 1");
        $stmt->execute([':id' => $id, ':employee' => $employeeId]);
        return (bool) $stmt->fetchColumn();
    }

    public function modalOptions(): array
    {
        $options = [];
        $options['departments'] = $this->db->query('SELECT id,dept_name label FROM user_departments WHERE is_active=1 ORDER BY sort_no,dept_name')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $options['positions'] = $this->db->query('SELECT id,position_name label FROM user_positions WHERE is_active=1 ORDER BY sort_no,position_name')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $options['jobs'] = $this->db->query('SELECT id,job_name label FROM institution_job_assignments_jobs WHERE deleted_at IS NULL AND is_active=1 ORDER BY sort_no,job_name')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $options;
    }

    public function nextSortNo(): int { return (int) $this->db->query('SELECT COALESCE(MAX(sort_no),0)+1 FROM institution_personnel_actions')->fetchColumn(); }
    public function updateSortNo(string $id, int $sortNo): bool { $stmt=$this->db->prepare('UPDATE institution_personnel_actions SET sort_no=:sort_no WHERE id=:id AND deleted_at IS NULL');$stmt->execute([':id'=>$id,':sort_no'=>$sortNo]);return $stmt->rowCount()===1; }
    public function insertAction(array $data): void { $this->insert('institution_personnel_actions', $data); }
    public function insertTarget(array $data): void { $this->insert('institution_personnel_actions_targets', $data); }
    public function insertChange(array $data): void { $this->insert('institution_personnel_actions_changes', $data); }
    public function insertHistory(string $table, array $data): void { $this->insert($table, $data); }

    public function employeeHistoryExists(string $table, string $employeeId): bool
    {
        $allowed = [
            'institution_job_assignments_department_histories',
            'institution_job_assignments_position_histories',
            'institution_job_assignments_job_histories',
            'institution_job_assignments_employment_status_histories',
        ];
        if (!in_array($table, $allowed, true)) {
            throw new \InvalidArgumentException('허용되지 않은 직원 이력 테이블입니다.');
        }
        $stmt = $this->db->prepare("SELECT 1 FROM `{$table}` WHERE employee_id=:employee_id LIMIT 1");
        $stmt->execute([':employee_id' => $employeeId]);
        return (bool) $stmt->fetchColumn();
    }

    public function updateDraft(string $id, array $data): bool
    {
        $sets=[]; foreach(array_keys($data) as $key) $sets[]="`{$key}`=:{$key}"; $data['id']=$id;
        $stmt=$this->db->prepare('UPDATE institution_personnel_actions SET '.implode(',',$sets)." WHERE id=:id AND business_status='DRAFT' AND deleted_at IS NULL");
        $stmt->execute($data); return $stmt->rowCount()===1 || $this->find($id)!==null;
    }
    public function replaceChildren(string $id): void { $stmt=$this->db->prepare('DELETE FROM institution_personnel_actions_targets WHERE personnel_action_id=:id'); $stmt->execute([':id'=>$id]); }
    public function updateWorkflow(string $id,string $status,?string $requestId,string $actor): bool { $approved=$status==='APPROVED'?'approved_at=NOW(),':''; $stmt=$this->db->prepare("UPDATE institution_personnel_actions SET business_status=:status,current_approval_request_id=:request,{$approved}updated_at=NOW(),updated_by=:actor WHERE id=:id AND deleted_at IS NULL"); $stmt->execute([':status'=>$status,':request'=>$requestId,':actor'=>$actor,':id'=>$id]); return $stmt->rowCount()===1; }
    public function softDelete(string $id,string $actor): bool { $stmt=$this->db->prepare("UPDATE institution_personnel_actions SET deleted_at=NOW(),deleted_by=:deleted_actor,updated_at=NOW(),updated_by=:updated_actor WHERE id=:id AND business_status='DRAFT' AND current_approval_request_id IS NULL AND deleted_at IS NULL"); $stmt->execute([':id'=>$id,':deleted_actor'=>$actor,':updated_actor'=>$actor]); return $stmt->rowCount()===1; }
    public function restore(string $id,string $actor): bool { $stmt=$this->db->prepare("UPDATE institution_personnel_actions SET deleted_at=NULL,deleted_by=NULL,updated_at=NOW(),updated_by=:updated_actor WHERE id=:id AND business_status='DRAFT' AND current_approval_request_id IS NULL AND deleted_at IS NOT NULL"); $stmt->execute([':id'=>$id,':updated_actor'=>$actor]); return $stmt->rowCount()===1; }
    public function purge(string $id): bool { $stmt=$this->db->prepare("DELETE FROM institution_personnel_actions WHERE id=:id AND business_status='DRAFT' AND current_approval_request_id IS NULL AND deleted_at IS NOT NULL"); $stmt->execute([':id'=>$id]); return $stmt->rowCount()===1; }
    public function trashIds(): array { $stmt=$this->db->query("SELECT id FROM institution_personnel_actions WHERE business_status='DRAFT' AND current_approval_request_id IS NULL AND deleted_at IS NOT NULL ORDER BY deleted_at,id"); return array_values(array_filter(array_map('strval',$stmt->fetchAll(PDO::FETCH_COLUMN)?:[]))); }
    public function updateTargetResult(string $id,string $status,?string $error,string $actor): void { $stmt=$this->db->prepare("UPDATE institution_personnel_actions_targets SET application_status=:status,applied_at=CASE WHEN :status2='APPLIED' THEN NOW() ELSE NULL END,applied_by=CASE WHEN :status3='APPLIED' THEN :applied_actor ELSE NULL END,application_error=:error,updated_at=NOW(),updated_by=:updated_actor WHERE id=:id"); $stmt->execute([':status'=>$status,':status2'=>$status,':status3'=>$status,':applied_actor'=>$actor,':updated_actor'=>$actor,':error'=>$error,':id'=>$id]); }
    public function completeAction(string $id,string $actor): void { $stmt=$this->db->prepare("UPDATE institution_personnel_actions SET business_status='APPLIED',applied_at=NOW(),updated_at=NOW(),updated_by=:actor WHERE id=:id AND business_status='APPROVED'"); $stmt->execute([':id'=>$id,':actor'=>$actor]); }
    public function updateEmployee(string $id,array $data): void { $sets=[]; foreach(array_keys($data) as $key)$sets[]="`{$key}`=:{$key}"; $data['id']=$id; $stmt=$this->db->prepare('UPDATE user_employees SET '.implode(',',$sets).' WHERE id=:id'); $stmt->execute($data); }
    public function currentPeriod(string $table,string $employeeColumn,string $employeeId,bool $forUpdate=true): ?array { $stmt=$this->db->prepare("SELECT * FROM `{$table}` WHERE `{$employeeColumn}`=:employee AND effective_to IS NULL ORDER BY effective_from DESC LIMIT 1".($forUpdate?' FOR UPDATE':'')); $stmt->execute([':employee'=>$employeeId]); return $stmt->fetch(PDO::FETCH_ASSOC)?:null; }
    public function closePeriod(string $table,string $id,string $endColumn,string $endDate,string $targetColumn,string $targetId,string $actor): void { $stmt=$this->db->prepare("UPDATE `{$table}` SET `{$endColumn}`=:end_date,`{$targetColumn}`=:target,updated_at=NOW(),updated_by=:actor WHERE id=:id"); $stmt->execute([':end_date'=>$endDate,':target'=>$targetId,':actor'=>$actor,':id'=>$id]); }
    public function targetChanges(string $targetId): array { $stmt=$this->db->prepare('SELECT * FROM institution_personnel_actions_changes WHERE personnel_action_target_id=:id ORDER BY sort_no FOR UPDATE'); $stmt->execute([':id'=>$targetId]); return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    public function activeLeave(string $employeeId): ?array { $stmt=$this->db->prepare("SELECT * FROM institution_job_assignments_leave_periods WHERE employee_id=:id AND status_code='ACTIVE' AND actual_end_date IS NULL LIMIT 1 FOR UPDATE"); $stmt->execute([':id'=>$employeeId]); return $stmt->fetch(PDO::FETCH_ASSOC)?:null; }
    public function currentEmploymentHistory(string $employeeId): ?array { $stmt=$this->db->prepare('SELECT * FROM institution_job_assignments_employment_status_histories WHERE employee_id=:id AND ended_date IS NULL ORDER BY effective_date DESC LIMIT 1 FOR UPDATE'); $stmt->execute([':id'=>$employeeId]); return $stmt->fetch(PDO::FETCH_ASSOC)?:null; }
    public function closeEmploymentHistory(string $id,string $date,string $actor): void { $stmt=$this->db->prepare('UPDATE institution_job_assignments_employment_status_histories SET ended_date=:date,updated_at=NOW(),updated_by=:actor WHERE id=:id AND ended_date IS NULL'); $stmt->execute([':date'=>$date,':actor'=>$actor,':id'=>$id]); }
    public function currentJob(string $employeeId): ?array { $stmt=$this->db->prepare("SELECT * FROM institution_job_assignments_job_histories WHERE employee_id=:id AND status_code='ACTIVE' ORDER BY start_date DESC LIMIT 1 FOR UPDATE"); $stmt->execute([':id'=>$employeeId]); return $stmt->fetch(PDO::FETCH_ASSOC)?:null; }
    public function closeJob(string $id,string $date,string $targetId,string $actor): void { $stmt=$this->db->prepare("UPDATE institution_job_assignments_job_histories SET end_date=:date,status_code='ENDED',end_personnel_action_target_id=:target,updated_at=NOW(),updated_by=:actor WHERE id=:id"); $stmt->execute([':date'=>$date,':target'=>$targetId,':actor'=>$actor,':id'=>$id]); }
    public function projectAssignment(string $id,string $employeeId): ?array { $stmt=$this->db->prepare('SELECT * FROM institution_job_assignments_project_histories WHERE id=:id AND employee_id=:employee LIMIT 1 FOR UPDATE'); $stmt->execute([':id'=>$id,':employee'=>$employeeId]); return $stmt->fetch(PDO::FETCH_ASSOC)?:null; }
    public function closeProject(string $id,string $date,string $targetId,string $actor,string $status='ENDED'): void { $stmt=$this->db->prepare("UPDATE institution_job_assignments_project_histories SET end_date=:date,status_code=:status,end_personnel_action_target_id=:target,updated_at=NOW(),updated_by=:actor WHERE id=:id AND status_code IN ('PLANNED','ACTIVE')"); $stmt->execute([':date'=>$date,':status'=>$status,':target'=>$targetId,':actor'=>$actor,':id'=>$id]); }
    public function currentWorkplace(string $employeeId): ?array { $stmt=$this->db->prepare("SELECT * FROM institution_job_assignments_workplace_histories WHERE employee_id=:id AND status_code='ACTIVE' ORDER BY start_date DESC LIMIT 1 FOR UPDATE"); $stmt->execute([':id'=>$employeeId]); return $stmt->fetch(PDO::FETCH_ASSOC)?:null; }
    public function closeWorkplace(string $id,string $date,string $targetId,string $actor): void { $stmt=$this->db->prepare("UPDATE institution_job_assignments_workplace_histories SET end_date=:date,status_code='ENDED',end_personnel_action_target_id=:target,updated_at=NOW(),updated_by=:actor WHERE id=:id"); $stmt->execute([':date'=>$date,':target'=>$targetId,':actor'=>$actor,':id'=>$id]); }
    public function finishLeave(string $id,string $date,string $targetId,string $actor): void { $stmt=$this->db->prepare("UPDATE institution_job_assignments_leave_periods SET actual_end_date=:date,status_code='ENDED',return_personnel_action_target_id=:target,updated_at=NOW(),updated_by=:actor WHERE id=:id AND status_code='ACTIVE'"); $stmt->execute([':date'=>$date,':target'=>$targetId,':actor'=>$actor,':id'=>$id]); }
    public function conflicts(string $employeeId,string $actionId,string $date): bool { $stmt=$this->db->prepare("SELECT 1 FROM institution_personnel_actions a JOIN institution_personnel_actions_targets t ON t.personnel_action_id=a.id WHERE t.employee_id=:employee AND a.id<>:action AND a.deleted_at IS NULL AND a.business_status IN ('APPROVAL_PENDING','APPROVED') AND a.action_date=:date LIMIT 1"); $stmt->execute([':employee'=>$employeeId,':action'=>$actionId,':date'=>$date]); return (bool)$stmt->fetchColumn(); }
    public function approvalSteps(string $requestId): array { $stmt=$this->db->prepare('SELECT * FROM user_approval_request_steps WHERE request_id=:id ORDER BY sort_no'); $stmt->execute([':id'=>$requestId]); return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[]; }

    private function insert(string $table,array $data): void { $columns=array_keys($data); $stmt=$this->db->prepare("INSERT INTO `{$table}` (`".implode('`,`',$columns).'`) VALUES (:'.implode(',:',$columns).')'); $stmt->execute($data); }
}
