<?php

namespace App\Models\Institution;

use App\Services\Institution\EmployeeAssignmentResolver;
use Core\Helpers\ActorHelper;
use PDO;

class JobAssignmentModel
{
    public function __construct(private readonly PDO $db) {}

    public function page(array $query, string $asOfDate): array
    {
        $date = $this->db->quote($asOfDate);
        $employmentRange = EmployeeAssignmentResolver::containsSql('esh.effective_date', 'esh.ended_date', $date);
        $departmentRange = EmployeeAssignmentResolver::containsSql('da.effective_from', 'da.effective_to', $date);
        $positionRange = EmployeeAssignmentResolver::containsSql('pa.effective_from', 'pa.effective_to', $date);
        $jobRange = EmployeeAssignmentResolver::containsSql('ja.start_date', 'ja.end_date', $date);
        $projectStatus = EmployeeAssignmentResolver::effectiveStatusSql('pra.start_date', 'pra.end_date', 'pra.status_code', $date);
        $workplaceRange = EmployeeAssignmentResolver::containsSql('wa.start_date', 'wa.end_date', $date);
        $where = ['1=1'];
        $params = [];
        $filters = json_decode((string) ($query['filters'] ?? ''), true);
        $filters = is_array($filters) ? $filters : [];
        $keyword = trim((string) ($query['search']['value'] ?? $query['keyword'] ?? ''));
        if ($keyword !== '') {
            $where[] = "(e.employee_name LIKE :keyword_employee ESCAPE '=' OR u.username LIKE :keyword_username ESCAPE '=')";
            $params[':keyword_employee'] = $this->likePattern($keyword);
            $params[':keyword_username'] = $this->likePattern($keyword);
        }
        foreach ($filters as $index => $filter) {
            $field = (string) ($filter['field'] ?? '');
            $value = $filter['value'] ?? null;
            if ($field === 'as_of_date' && is_scalar($value) && trim((string) $value) !== '') continue;
            if ($field === 'keyword' && is_scalar($value) && trim((string) $value) !== '') {
                $employeeKey = ':keyword_employee_' . $index;
                $usernameKey = ':keyword_username_' . $index;
                $where[] = "(e.employee_name LIKE {$employeeKey} ESCAPE '=' OR u.username LIKE {$usernameKey} ESCAPE '=')";
                $params[$employeeKey] = $this->likePattern(trim((string) $value));
                $params[$usernameKey] = $this->likePattern(trim((string) $value));
                continue;
            }
            if (!is_scalar($value) || trim((string) $value) === '') continue;
            $key = ':filter_' . $index;
            $params[$key] = trim((string) $value);
            if ($field === 'employee_id') $where[] = "e.id={$key}";
            elseif ($field === 'employee_name') { $where[] = "e.employee_name LIKE {$key} ESCAPE '='"; $params[$key] = $this->likePattern((string) $params[$key]); }
            elseif ($field === 'username') { $where[] = "u.username LIKE {$key} ESCAPE '='"; $params[$key] = $this->likePattern((string) $params[$key]); }
            elseif ($field === 'employment_status') $where[] = "EXISTS(SELECT 1 FROM institution_job_assignments_employment_status_histories es WHERE es.employee_id=e.id AND ".EmployeeAssignmentResolver::containsSql('es.effective_date','es.ended_date',$date)." AND es.status_code={$key})";
            elseif ($field === 'department_id') $where[] = "EXISTS(SELECT 1 FROM institution_job_assignments_department_histories dx WHERE dx.employee_id=e.id AND ".EmployeeAssignmentResolver::containsSql('dx.effective_from','dx.effective_to',$date)." AND dx.department_id={$key})";
            elseif ($field === 'position_id') $where[] = "EXISTS(SELECT 1 FROM institution_job_assignments_position_histories px WHERE px.employee_id=e.id AND ".EmployeeAssignmentResolver::containsSql('px.effective_from','px.effective_to',$date)." AND px.position_id={$key})";
            elseif ($field === 'job_id') $where[] = "EXISTS(SELECT 1 FROM institution_job_assignments_job_histories jx WHERE jx.employee_id=e.id AND jx.status_code<>'CANCELLED' AND ".EmployeeAssignmentResolver::containsSql('jx.start_date','jx.end_date',$date)." AND jx.job_id={$key})";
            elseif ($field === 'project_id') $where[] = "EXISTS(SELECT 1 FROM institution_job_assignments_project_histories prx WHERE prx.employee_id=e.id AND prx.status_code<>'CANCELLED' AND ".EmployeeAssignmentResolver::containsSql('prx.start_date','prx.end_date',$date)." AND prx.project_id={$key})";
            elseif ($field === 'workplace_type_code') $where[] = "EXISTS(SELECT 1 FROM institution_job_assignments_workplace_histories wx WHERE wx.employee_id=e.id AND wx.status_code<>'CANCELLED' AND ".EmployeeAssignmentResolver::containsSql('wx.start_date','wx.end_date',$date)." AND wx.workplace_type_code={$key})";
            elseif ($field === 'assignment_status') $where[] = "EXISTS(SELECT 1 FROM institution_job_assignments_project_histories sx WHERE sx.employee_id=e.id AND ".EmployeeAssignmentResolver::effectiveStatusSql('sx.start_date','sx.end_date','sx.status_code',$date)."={$key})";
            else unset($params[$key]);
        }
        $currentOnly = $this->filterFlag($filters, 'current_only', (string) ($query['current_only'] ?? '0'));
        $includeEnded = $this->filterFlag($filters, 'include_ended', (string) ($query['include_ended'] ?? '1'));
        $baseWhere = ['1=1'];
        if ($currentOnly === '1') {
            $where[] = "EXISTS(SELECT 1 FROM institution_job_assignments_employment_status_histories ec WHERE ec.employee_id=e.id AND ".EmployeeAssignmentResolver::containsSql('ec.effective_date','ec.ended_date',$date)." AND ec.status_code IN ('ACTIVE','ON_LEAVE'))";
        }
        if ($includeEnded !== '1') $where[] = "(EXISTS(SELECT 1 FROM institution_job_assignments_department_histories dc WHERE dc.employee_id=e.id AND ".EmployeeAssignmentResolver::containsSql('dc.effective_from','dc.effective_to',$date).") OR EXISTS(SELECT 1 FROM institution_job_assignments_position_histories pc WHERE pc.employee_id=e.id AND ".EmployeeAssignmentResolver::containsSql('pc.effective_from','pc.effective_to',$date).") OR EXISTS(SELECT 1 FROM institution_job_assignments_job_histories jc WHERE jc.employee_id=e.id AND jc.status_code<>'CANCELLED' AND ".EmployeeAssignmentResolver::containsSql('jc.start_date','jc.end_date',$date).") OR EXISTS(SELECT 1 FROM institution_job_assignments_project_histories rc WHERE rc.employee_id=e.id AND ".EmployeeAssignmentResolver::effectiveStatusSql('rc.start_date','rc.end_date','rc.status_code',$date)."='ACTIVE') OR EXISTS(SELECT 1 FROM institution_job_assignments_workplace_histories wc WHERE wc.employee_id=e.id AND wc.status_code<>'CANCELLED' AND ".EmployeeAssignmentResolver::containsSql('wc.start_date','wc.end_date',$date)."))";

        $joins = "
            FROM user_employees e
            JOIN auth_users u ON u.id=e.user_id
            LEFT JOIN institution_job_assignments_employment_status_histories esh ON esh.employee_id=e.id AND {$employmentRange}
            LEFT JOIN system_codes employment_code ON employment_code.code_group='EMPLOYMENT_STATUS' AND employment_code.code=COALESCE(esh.status_code,e.employment_status)
            LEFT JOIN institution_job_assignments_department_histories da ON da.employee_id=e.id AND {$departmentRange}
            LEFT JOIN user_departments d ON d.id=da.department_id
            LEFT JOIN user_departments master_department ON master_department.id=e.department_id
            LEFT JOIN institution_job_assignments_position_histories pa ON pa.employee_id=e.id AND {$positionRange}
            LEFT JOIN user_positions pos ON pos.id=pa.position_id
            LEFT JOIN user_positions master_position ON master_position.id=e.position_id
            LEFT JOIN (
                SELECT ja.employee_id,COUNT(DISTINCT ja.id) job_count,MAX(j.job_name) job_name,
                       MAX(ja.start_date) start_date,MAX(ja.end_date) end_date,
                       MAX(COALESCE(ja.updated_at,ja.created_at)) updated_at
                  FROM institution_job_assignments_job_histories ja
                  JOIN institution_job_assignments_jobs j ON j.id=ja.job_id
                 WHERE ja.status_code<>'CANCELLED' AND {$jobRange}
                 GROUP BY ja.employee_id
            ) jp ON jp.employee_id=e.id
            LEFT JOIN (
                SELECT pra.employee_id,
                       GROUP_CONCAT(DISTINCT CASE WHEN {$projectStatus}='ACTIVE' THEN pr.project_name END ORDER BY pra.is_primary DESC,pr.project_name SEPARATOR ', ') project_names,
                       MAX(CASE WHEN {$projectStatus}='ACTIVE' AND pra.is_primary=1 THEN pr.project_name END) primary_project_name,
                       COUNT(DISTINCT CASE WHEN {$projectStatus}='ACTIVE' AND pra.is_primary=0 THEN pra.id END) other_project_count,
                       SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT CASE WHEN {$projectStatus}='ACTIVE' AND pra.is_primary=0 THEN pr.project_name END ORDER BY pr.project_name SEPARATOR ', '),', ',1) other_project_first_name,
                       CASE WHEN SUM({$projectStatus}='ACTIVE')>0 THEN 'ACTIVE' WHEN SUM({$projectStatus}='PLANNED')>0 THEN 'PLANNED' ELSE MAX({$projectStatus}) END assignment_status,
                       MAX(COALESCE(pra.updated_at,pra.created_at)) updated_at
                  FROM institution_job_assignments_project_histories pra
                  JOIN system_projects pr ON pr.id=pra.project_id
                 GROUP BY pra.employee_id
            ) pp ON pp.employee_id=e.id
            LEFT JOIN system_codes assignment_code ON assignment_code.code_group='EMPLOYEE_ASSIGNMENT_STATUS' AND assignment_code.code=pp.assignment_status
            LEFT JOIN (
                SELECT wa.employee_id,COUNT(DISTINCT wa.id) workplace_count,
                       MAX(COALESCE(wa.workplace_name_snapshot,wp.project_name)) workplace_name,
                       MAX(wa.workplace_type_code) workplace_type_code,
                       MAX(COALESCE(wa.updated_at,wa.created_at)) updated_at
                  FROM institution_job_assignments_workplace_histories wa
                  LEFT JOIN system_projects wp ON wp.id=wa.project_id
                 WHERE wa.status_code<>'CANCELLED' AND {$workplaceRange}
                 GROUP BY wa.employee_id
            ) wproj ON wproj.employee_id=e.id";
        $whereSql = implode(' AND ', $where);
        $totalSql = 'SELECT COUNT(*) FROM user_employees e JOIN auth_users u ON u.id=e.user_id WHERE '.implode(' AND ',$baseWhere);
        $total = (int)$this->db->query($totalSql)->fetchColumn();
        $count = $this->db->prepare("SELECT COUNT(DISTINCT e.id) {$joins} WHERE {$whereSql}");$count->execute($params);$filtered=(int)$count->fetchColumn();
        $start = max(0, (int) ($query['start'] ?? 0));
        $length = max(1, min(200, (int) ($query['length'] ?? 50)));
        $orderSql = $this->listOrderSql($query);
        $sql = "SELECT e.id employee_id,e.sort_no,u.username,e.employee_name,
                       COALESCE(esh.status_code,e.employment_status) employment_status,employment_code.code_name employment_status_name,
                       COALESCE(d.dept_name,master_department.dept_name) department_name,
                       COALESCE(pos.position_name,master_position.position_name) position_name,
                       CASE WHEN jp.job_count>1 THEN '[직무 중복]' ELSE jp.job_name END job_name,
                       pp.project_names,pp.primary_project_name,COALESCE(pp.other_project_count,0) other_project_count,
                       pp.other_project_first_name,
                       CASE WHEN wproj.workplace_count>1 THEN '[근무지 중복]' ELSE wproj.workplace_name END workplace_name,
                       wproj.workplace_type_code,jp.start_date assignment_start_date,jp.end_date assignment_end_date,
                       pp.assignment_status,assignment_code.code_name assignment_status_name,
                       NULLIF(GREATEST(COALESCE(jp.updated_at,'1000-01-01'),COALESCE(pp.updated_at,'1000-01-01'),COALESCE(wproj.updated_at,'1000-01-01')),'1000-01-01') updated_at
                {$joins} WHERE {$whereSql}
                ORDER BY {$orderSql} LIMIT {$start},{$length}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
        foreach($rows as &$row){$count=(int)($row['other_project_count']??0);$first=(string)($row['other_project_first_name']??'');$row['other_project_summary']=$count===0?null:($count===1?$first:$first.' 외 '.($count-1).'건');}unset($row);
        return ['rows' => $rows, 'total' => $total, 'filtered' => $filtered, 'as_of_date' => $asOfDate];
    }

    private function listOrderSql(array $query): string
    {
        $order = is_array($query['order'][0] ?? null) ? $query['order'][0] : [];
        $columnIndex = filter_var($order['column'] ?? null, FILTER_VALIDATE_INT);
        $columns = is_array($query['columns'] ?? null) ? $query['columns'] : [];
        $column = $columnIndex !== false && is_array($columns[$columnIndex] ?? null)
            ? $columns[$columnIndex]
            : [];
        $key = trim((string) ($column['data'] ?? ''));
        $direction = strtolower((string) ($order['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $expressions = [
            'sort_no' => 'e.sort_no',
            'employee_name' => 'e.employee_name',
            'employment_status' => 'COALESCE(esh.status_code,e.employment_status)',
            'department_name' => 'COALESCE(d.dept_name,master_department.dept_name)',
            'position_name' => 'COALESCE(pos.position_name,master_position.position_name)',
            'job_name' => 'jp.job_name',
            'primary_project_name' => 'pp.primary_project_name',
            'workplace_name' => 'wproj.workplace_name',
            'assignment_start_date' => 'jp.start_date',
            'assignment_end_date' => 'jp.end_date',
            'assignment_status' => 'pp.assignment_status',
        ];
        $expression = $expressions[$key] ?? 'e.sort_no';

        return "{$expression} {$direction}, e.sort_no ASC";
    }

    public function detail(string $employeeId): ?array
    {
        $stmt = $this->db->prepare('SELECT e.id,e.sort_no,u.username,e.employee_name,e.employment_status,d.dept_name department_name,p.position_name,j.job_name,e.doc_hire_date,e.real_hire_date,e.doc_retire_date,e.real_retire_date FROM user_employees e JOIN auth_users u ON u.id=e.user_id LEFT JOIN user_departments d ON d.id=e.department_id LEFT JOIN user_positions p ON p.id=e.position_id LEFT JOIN institution_job_assignments_jobs j ON j.id=e.job_id WHERE e.id=:id LIMIT 1');
        $stmt->execute([':id' => $employeeId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$employee) return null;
        $employee['employment_status_histories'] = $this->history('SELECT h.*,a.action_no,a.action_name FROM institution_job_assignments_employment_status_histories h LEFT JOIN institution_personnel_actions_targets t ON t.id=h.source_personnel_action_target_id LEFT JOIN institution_personnel_actions a ON a.id=t.personnel_action_id WHERE h.employee_id=:id ORDER BY h.effective_date DESC', $employeeId);
        $employee['department_assignments'] = $this->history('SELECT h.*,d.dept_name,a.action_no,a.action_name FROM institution_job_assignments_department_histories h JOIN user_departments d ON d.id=h.department_id LEFT JOIN institution_personnel_actions_targets t ON t.id=h.start_action_target_id LEFT JOIN institution_personnel_actions a ON a.id=t.personnel_action_id WHERE h.employee_id=:id ORDER BY h.effective_from DESC', $employeeId);
        $employee['position_assignments'] = $this->history('SELECT h.*,p.position_name,a.action_no,a.action_name FROM institution_job_assignments_position_histories h JOIN user_positions p ON p.id=h.position_id LEFT JOIN institution_personnel_actions_targets t ON t.id=h.start_action_target_id LEFT JOIN institution_personnel_actions a ON a.id=t.personnel_action_id WHERE h.employee_id=:id ORDER BY h.effective_from DESC', $employeeId);
        $employee['job_assignments'] = $this->history('SELECT h.*,j.job_name,a.action_no,a.action_name FROM institution_job_assignments_job_histories h JOIN institution_job_assignments_jobs j ON j.id=h.job_id LEFT JOIN institution_personnel_actions_targets t ON t.id=h.assignment_personnel_action_target_id LEFT JOIN institution_personnel_actions a ON a.id=t.personnel_action_id WHERE h.employee_id=:id ORDER BY h.start_date DESC', $employeeId);
        $employee['project_assignments'] = $this->history('SELECT h.*,p.project_name,a.action_no,a.action_name FROM institution_job_assignments_project_histories h JOIN system_projects p ON p.id=h.project_id LEFT JOIN institution_personnel_actions_targets t ON t.id=h.assignment_personnel_action_target_id LEFT JOIN institution_personnel_actions a ON a.id=t.personnel_action_id WHERE h.employee_id=:id ORDER BY h.start_date DESC', $employeeId);
        $employee['workplace_assignments'] = $this->history('SELECT h.*,COALESCE(h.workplace_name_snapshot,p.project_name) workplace_name,a.action_no,a.action_name FROM institution_job_assignments_workplace_histories h LEFT JOIN system_projects p ON p.id=h.project_id LEFT JOIN institution_personnel_actions_targets t ON t.id=h.assignment_personnel_action_target_id LEFT JOIN institution_personnel_actions a ON a.id=t.personnel_action_id WHERE h.employee_id=:id ORDER BY h.start_date DESC', $employeeId);
        $employee['personnel_actions'] = $this->history('SELECT a.id,a.action_no,a.action_name,a.action_type_code,a.issued_date,a.action_date,a.business_status,t.application_status,t.applied_at,t.applied_by FROM institution_personnel_actions_targets t JOIN institution_personnel_actions a ON a.id=t.personnel_action_id WHERE t.employee_id=:id AND a.deleted_at IS NULL ORDER BY a.action_date DESC,a.sort_no DESC', $employeeId);
        $employee['personnel_actions'] = ActorHelper::enrichActorNames($employee['personnel_actions'], ['applied_by_name' => 'applied_by']);
        return $employee;
    }

    public function options(): array
    {
        $result = [];
        foreach (['EMPLOYMENT_STATUS' => 'employment_statuses','EMPLOYEE_ASSIGNMENT_STATUS' => 'assignment_statuses','EMPLOYEE_WORKPLACE_TYPE' => 'workplace_types','EMPLOYEE_ASSIGNMENT_SOURCE' => 'assignment_sources'] as $group => $key) {
            $stmt = $this->db->prepare('SELECT code value,code_name label FROM system_codes WHERE code_group=:group AND is_active=1 ORDER BY sort_no');
            $stmt->execute([':group' => $group]); $result[$key] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        $result['jobs'] = $this->db->query("SELECT id value,job_name label,is_active FROM institution_job_assignments_jobs WHERE deleted_at IS NULL ORDER BY sort_no,job_name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $result['departments'] = $this->db->query("SELECT id value,dept_name label FROM user_departments WHERE is_active=1 ORDER BY sort_no,dept_name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $result['positions'] = $this->db->query("SELECT id value,position_name label FROM user_positions WHERE is_active=1 ORDER BY sort_no,position_name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $result;
    }

    public function employee(string $id, bool $forUpdate = false): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM user_employees WHERE id=:id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function job(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM institution_job_assignments_jobs WHERE id=:id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function project(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM system_projects WHERE id=:id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function assignment(string $domain, string $id, bool $forUpdate = false): ?array
    {
        $table = strtoupper($domain) === 'JOB' ? 'institution_job_assignments_job_histories' : 'institution_job_assignments_project_histories';
        $stmt = $this->db->prepare("SELECT * FROM {$table} WHERE id=:id LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : ''));
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function activeCode(string $group, string $code): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM system_codes WHERE code_group=:code_group AND code=:code AND is_active=1 LIMIT 1');
        $stmt->execute([':code_group' => $group, ':code' => $code]);
        return (bool) $stmt->fetchColumn();
    }

    public function jobOverlaps(string $employeeId, string $startDate, string $endDate, ?string $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM institution_job_assignments_job_histories WHERE employee_id=:employee AND status_code<>'CANCELLED' AND start_date<=:end_date AND (end_date IS NULL OR end_date>=:start_date)";
        $params = [':employee' => $employeeId, ':start_date' => $startDate, ':end_date' => $endDate];
        if ($excludeId !== null) { $sql .= ' AND id<>:exclude_id'; $params[':exclude_id'] = $excludeId; }
        $stmt = $this->db->prepare($sql . ' LIMIT 1 FOR UPDATE'); $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function projectOverlaps(string $employeeId, string $projectId, string $startDate, ?string $endDate, ?string $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM institution_job_assignments_project_histories WHERE employee_id=:employee AND project_id=:project AND status_code<>'CANCELLED' AND start_date<=:range_end AND (end_date IS NULL OR end_date>=:start_date)";
        $params = [':employee' => $employeeId, ':project' => $projectId, ':start_date' => $startDate, ':range_end' => $endDate ?? '9999-12-31'];
        if ($excludeId !== null) { $sql .= ' AND id<>:exclude_id'; $params[':exclude_id'] = $excludeId; }
        $stmt = $this->db->prepare($sql . ' LIMIT 1 FOR UPDATE'); $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function leaveOverlaps(string $employeeId, string $startDate, ?string $endDate): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM institution_job_assignments_leave_periods WHERE employee_id=:employee AND status_code<>'CANCELLED' AND start_date<=:range_end AND COALESCE(actual_end_date,planned_end_date,'9999-12-31')>=:start_date LIMIT 1 FOR UPDATE");
        $stmt->execute([':employee' => $employeeId, ':start_date' => $startDate, ':range_end' => $endDate ?? '9999-12-31']);
        return (bool) $stmt->fetchColumn();
    }

    public function earliestOfficialJobDate(string $employeeId): ?string
    {
        $stmt = $this->db->prepare('SELECT MIN(start_date) FROM institution_job_assignments_job_histories WHERE employee_id=:employee AND assignment_personnel_action_target_id IS NOT NULL');
        $stmt->execute([':employee' => $employeeId]);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? null : (string) $value;
    }

    public function insertJob(array $data): void { $this->insert('institution_job_assignments_job_histories', $data); }
    public function insertProject(array $data): void { $this->insert('institution_job_assignments_project_histories', $data); }

    public function updateAssignment(string $domain, string $id, array $data): void
    {
        $table = strtoupper($domain) === 'JOB' ? 'institution_job_assignments_job_histories' : 'institution_job_assignments_project_histories';
        $sets = [];
        foreach (array_keys($data) as $column) $sets[] = "`{$column}`=:{$column}";
        $data['id'] = $id;
        $stmt = $this->db->prepare("UPDATE {$table} SET " . implode(',', $sets) . ' WHERE id=:id');
        $stmt->execute($data);
    }

    private function history(string $sql, string $employeeId): array
    {
        $stmt = $this->db->prepare($sql); $stmt->execute([':id' => $employeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function filterFlag(array $filters, string $field, string $default): string
    {
        foreach ($filters as $filter) {
            if (($filter['field'] ?? '') !== $field) continue;
            $value = trim((string) ($filter['value'] ?? ''));
            if ($value === '0' || $value === '1') return $value;
        }
        return $default === '0' ? '0' : '1';
    }

    private function likePattern(string $value): string
    {
        return '%' . strtr($value, ['=' => '==', '%' => '=%', '_' => '=_']) . '%';
    }

    private function insert(string $table, array $data): void
    {
        $columns = array_keys($data);
        $stmt = $this->db->prepare("INSERT INTO {$table} (`" . implode('`,`', $columns) . '`) VALUES (:' . implode(',:', $columns) . ')');
        $stmt->execute($data);
    }
}
