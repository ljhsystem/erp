<?php

namespace App\Models\Institution;

use PDO;

class EducationSessionModel
{
    public function __construct(private readonly PDO $db) {}

    public function page(array $query): array
    {
        $start = max(0, (int) ($query['start'] ?? 0));
        $length = max(1, min(200, (int) ($query['length'] ?? 50)));
        [$where, $params] = $this->sessionWhere($query);
        $whereSql = implode(' AND ', $where);
        $total = $this->scalar('SELECT COUNT(*) FROM institution_educations_sessions s JOIN institution_educations_courses c ON c.id=s.course_id WHERE ' . $whereSql, $params);
        $order = $this->order($query, [
            'title' => 's.title', 'course_name' => 'c.course_name', 'starts_at' => 's.starts_at',
            'ends_at' => 's.ends_at', 'location_name' => 's.location_name', 'status_code' => 's.status_code',
            'target_count' => 'COALESCE(x.target_count,0)', 'acknowledged_count' => 'COALESCE(x.acknowledged_count,0)',
            'attended_count' => 'COALESCE(x.attendance_count,0)', 'absent_count' => 'COALESCE(x.absent_count,0)',
            'completed_count' => 'COALESCE(x.completed_count,0)', 'not_completed_count' => 'COALESCE(x.not_completed_count,0)',
        ], 's.starts_at DESC,s.created_at DESC');
        $sql = "SELECT s.*,c.course_name,c.education_type_code,c.default_institution_name,o.employee_name organizer_name,
                       COALESCE(x.target_count,0) target_count,COALESCE(x.acknowledged_count,0) acknowledged_count,
                       COALESCE(x.target_count,0)-COALESCE(x.acknowledged_count,0) unacknowledged_count,
                       COALESCE(x.attendance_count,0) attended_count,COALESCE(x.absent_count,0) absent_count,
                       COALESCE(x.completed_count,0) completed_count,COALESCE(x.not_completed_count,0) not_completed_count
                  FROM institution_educations_sessions s
                  JOIN institution_educations_courses c ON c.id=s.course_id
                  LEFT JOIN user_employees o ON o.id=s.organizer_employee_id
                  LEFT JOIN (
                    SELECT session_id,COUNT(*) target_count,
                           SUM(acknowledged_at IS NOT NULL) acknowledged_count,
                           SUM(attendance_status_code='ATTENDED') attendance_count,
                           SUM(attendance_status_code='ABSENT') absent_count,
                           SUM(completion_status_code='COMPLETED') completed_count,
                           SUM(completion_status_code='NOT_COMPLETED') not_completed_count
                      FROM institution_educations_session_targets
                     WHERE removed_at IS NULL GROUP BY session_id
                  ) x ON x.session_id=s.id
                 WHERE {$whereSql} ORDER BY {$order} LIMIT {$start},{$length}";
        return ['rows' => $this->all($sql, $params), 'total' => $total];
    }

    public function targetPage(string $sessionId, array $query): array
    {
        $start = max(0, (int) ($query['start'] ?? 0));
        $length = max(1, min(200, (int) ($query['length'] ?? 50)));
        $where = ['t.session_id=:session', 't.removed_at IS NULL'];
        $params = [':session' => $sessionId];
        $keyword = trim((string) ($query['search']['value'] ?? ''));
        if ($keyword !== '') {
            $where[] = "CONCAT_WS(' ',e.employee_name,d.dept_name,j.job_name) LIKE :keyword";
            $params[':keyword'] = '%' . $keyword . '%';
        }
        $whereSql = implode(' AND ', $where);
        $total = $this->scalar("SELECT COUNT(*) FROM institution_educations_session_targets t JOIN user_employees e ON e.id=t.employee_id LEFT JOIN user_departments d ON d.id=e.department_id LEFT JOIN institution_job_assignments_jobs j ON j.id=e.job_id WHERE {$whereSql}", $params);
        $order = $this->order($query, [
            'employee_name' => 'e.employee_name', 'dept_name' => 'd.dept_name', 'job_name' => 'j.job_name',
            'acknowledged_at' => 't.acknowledged_at', 'attendance_status_code' => 't.attendance_status_code',
            'completion_status_code' => 't.completion_status_code',
        ], 'e.sort_no,e.employee_name');
        $rows = $this->all("SELECT t.*,e.employee_name,d.dept_name department_name,j.job_name
                              FROM institution_educations_session_targets t
                              JOIN user_employees e ON e.id=t.employee_id
                              LEFT JOIN user_departments d ON d.id=e.department_id
                              LEFT JOIN institution_job_assignments_jobs j ON j.id=e.job_id
                             WHERE {$whereSql} ORDER BY {$order} LIMIT {$start},{$length}", $params);
        return ['rows' => $rows, 'total' => $total];
    }

    public function detail(string $id, bool $lock = false): ?array
    {
        return $this->one('SELECT s.*,c.course_name,c.education_type_code,c.default_institution_name,o.employee_name organizer_employee_name FROM institution_educations_sessions s JOIN institution_educations_courses c ON c.id=s.course_id LEFT JOIN user_employees o ON o.id=s.organizer_employee_id WHERE s.id=:id' . ($lock ? ' FOR UPDATE' : ''), [':id' => $id]);
    }

    public function target(string $id, bool $lock = false): ?array
    {
        return $this->one('SELECT * FROM institution_educations_session_targets WHERE id=:id' . ($lock ? ' FOR UPDATE' : ''), [':id' => $id]);
    }

    public function activeTargets(string $sessionId, bool $lock = false): array
    {
        return $this->all('SELECT t.*,e.user_id,e.employee_name FROM institution_educations_session_targets t JOIN user_employees e ON e.id=t.employee_id WHERE t.session_id=:session AND t.removed_at IS NULL ORDER BY t.created_at,t.id' . ($lock ? ' FOR UPDATE' : ''), [':session' => $sessionId]);
    }

    public function course(string $id): ?array { return $this->one('SELECT * FROM institution_educations_courses WHERE id=:id AND deleted_at IS NULL', [':id' => $id]); }
    public function employee(string $id): ?array { return $this->one('SELECT id,employee_name,user_id FROM user_employees WHERE id=:id', [':id' => $id]); }
    public function recordForTarget(string $sessionId, string $employeeId): ?array { return $this->one('SELECT * FROM institution_educations_employee_records WHERE session_id=:session AND employee_id=:employee LIMIT 1', [':session' => $sessionId, ':employee' => $employeeId]); }

    public function createSession(array $data): void { $this->insert('institution_educations_sessions', $data); }
    public function updateSession(string $id, array $data): void { $this->update('institution_educations_sessions', $id, $data); }

    public function addTarget(array $data): string
    {
        $existing = $this->one('SELECT id,removed_at FROM institution_educations_session_targets WHERE session_id=:session AND employee_id=:employee FOR UPDATE', [':session' => $data['session_id'], ':employee' => $data['employee_id']]);
        if ($existing) {
            if ($existing['removed_at'] === null) throw new \InvalidArgumentException('이미 지정된 교육 대상자입니다.');
            $id = (string) $existing['id'];
            unset($data['id'], $data['created_at'], $data['created_by']);
            $data['removed_at'] = null; $data['removed_by'] = null;
            $this->update('institution_educations_session_targets', $id, $data);
            return $id;
        }
        $this->insert('institution_educations_session_targets', $data);
        return (string) $data['id'];
    }

    public function updateTarget(string $id, array $data): void { $this->update('institution_educations_session_targets', $id, $data); }
    public function audit(array $data): void { $this->insert('institution_educations_audits', $data); }

    private function sessionWhere(array $query): array
    {
        $where = ['1=1']; $params = [];
        $filters = json_decode((string) ($query['filters'] ?? '[]'), true);
        foreach (is_array($filters) ? $filters : [] as $index => $filter) {
            $field = (string) ($filter['field'] ?? ''); $raw = $filter['value'] ?? ''; $key = ':f' . $index;
            if (is_array($raw) && $field === 'starts_at') {
                $from = trim((string) ($raw['start'] ?? '')); $to = trim((string) ($raw['end'] ?? ''));
                if ($from !== '') { $where[] = 's.starts_at>=:from'; $params[':from'] = $from . ' 00:00:00'; }
                if ($to !== '') { $where[] = 's.starts_at<=:to'; $params[':to'] = $to . ' 23:59:59'; }
                continue;
            }
            $value = is_array($raw) ? '' : trim((string) $raw); if ($value === '') continue;
            if ($field === 'course_id') { $where[] = 's.course_id=' . $key; $params[$key] = $value; }
            elseif ($field === 'education_type_code') { $where[] = 'c.education_type_code=' . $key; $params[$key] = $value; }
            elseif ($field === 'status_code') { $where[] = 's.status_code=' . $key; $params[$key] = $value; }
            elseif ($field === 'location_name') { $where[] = 's.location_name LIKE ' . $key; $params[$key] = '%' . $value . '%'; }
        }
        $keyword = trim((string) ($query['search']['value'] ?? ''));
        if ($keyword !== '') { $where[] = "CONCAT_WS(' ',s.title,c.course_name,s.location_name,s.instructor_name) LIKE :keyword"; $params[':keyword'] = '%' . $keyword . '%'; }
        return [$where, $params];
    }

    private function order(array $query, array $allowed, string $fallback): string
    {
        $index = (int) ($query['order'][0]['column'] ?? -1); $direction = strtolower((string) ($query['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $key = (string) ($query['columns'][$index]['data'] ?? ''); return isset($allowed[$key]) ? $allowed[$key] . ' ' . $direction : $fallback;
    }

    private function one(string $sql, array $params = []): ?array { $statement = $this->db->prepare($sql); $statement->execute($params); return $statement->fetch(PDO::FETCH_ASSOC) ?: null; }
    private function all(string $sql, array $params = []): array { $statement = $this->db->prepare($sql); $statement->execute($params); return $statement->fetchAll(PDO::FETCH_ASSOC) ?: []; }
    private function scalar(string $sql, array $params = []): int { $statement = $this->db->prepare($sql); $statement->execute($params); return (int) $statement->fetchColumn(); }
    private function insert(string $table, array $data): void { $columns = array_keys($data); $statement = $this->db->prepare('INSERT INTO `' . $table . '` (`' . implode('`,`', $columns) . '`) VALUES (:' . implode(',:', $columns) . ')'); $statement->execute($data); }
    private function update(string $table, string $id, array $data): void { $sets = []; foreach (array_keys($data) as $column) $sets[] = '`' . $column . '`=:' . $column; $data['id'] = $id; $statement = $this->db->prepare('UPDATE `' . $table . '` SET ' . implode(',', $sets) . ' WHERE id=:id'); $statement->execute($data); }
}
