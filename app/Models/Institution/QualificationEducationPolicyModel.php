<?php
namespace App\Models\Institution;

use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PDO;

class QualificationEducationPolicyModel
{
    public function __construct(private PDO $db) {}

    public function qualificationTypes(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM institution_qualifications_types WHERE deleted_at IS NULL'
            . ($activeOnly ? ' AND is_active=1' : '') . ' ORDER BY sort_no,qualification_name';
        return $this->enrich($this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function qualificationType(string $id, bool $lock = false): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM institution_qualifications_types WHERE id=:id AND deleted_at IS NULL' . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute([':id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ? ActorHelper::enrichActorNamesRow($row, ['created_by', 'updated_by', 'deleted_by']) : null;
    }

    public function courses(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM institution_educations_courses WHERE deleted_at IS NULL'
            . ($activeOnly ? ' AND is_active=1' : '') . ' ORDER BY sort_no,course_name';
        return $this->enrich($this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function course(string $id, bool $lock = false): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM institution_educations_courses WHERE id=:id AND deleted_at IS NULL' . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute([':id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ? ActorHelper::enrichActorNamesRow($row, ['created_by', 'updated_by', 'deleted_by']) : null;
    }

    public function jobs(): array
    {
        return $this->db->query("SELECT id value,job_name label,job_code FROM institution_job_assignments_jobs WHERE deleted_at IS NULL AND is_active=1 ORDER BY sort_no,job_name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function requirements(string $kind, ?string $jobId = null): array
    {
        [$table, $targetColumn, $join, $select] = $this->requirementDefinition($kind);
        $where = ['r.deleted_at IS NULL']; $params = [];
        if ($jobId !== null && $jobId !== '') { $where[] = 'r.job_id=:job'; $params[':job'] = $jobId; }
        $sql = 'SELECT r.*,j.job_code,j.job_name,' . $select . ' FROM ' . $table . ' r'
            . ' JOIN institution_job_assignments_jobs j ON j.id=r.job_id ' . $join
            . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY j.sort_no,r.effective_from,' . $targetColumn;
        $statement = $this->db->prepare($sql); $statement->execute($params);
        return $this->enrich($statement->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function requirement(string $kind, string $id, bool $lock = false): ?array
    {
        [$table] = $this->requirementDefinition($kind);
        $statement = $this->db->prepare('SELECT * FROM ' . $table . ' WHERE id=:id AND deleted_at IS NULL' . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute([':id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ? ActorHelper::enrichActorNamesRow($row, ['created_by', 'updated_by', 'deleted_by']) : null;
    }

    public function save(string $table, string $id, array $data): string
    {
        if ($id === '') { $id = UuidHelper::generate(); $data = ['id' => $id] + $data; $this->insert($table, $data); return $id; }
        $sets = []; $params = [':id' => $id];
        foreach ($data as $key => $value) { $sets[] = $key . '=:' . $key; $params[':' . $key] = $value; }
        $statement = $this->db->prepare('UPDATE ' . $table . ' SET ' . implode(',', $sets) . ' WHERE id=:id AND deleted_at IS NULL');
        $statement->execute($params); return $id;
    }

    public function assertNoOverlap(string $kind, string $jobId, string $targetId, string $from, ?string $to, string $excludeId = ''): void
    {
        [$table, $targetColumn] = $this->requirementDefinition($kind);
        $sql = 'SELECT id FROM ' . $table . ' WHERE job_id=:job AND ' . $targetColumn . '=:target AND deleted_at IS NULL'
            . ' AND effective_from<=COALESCE(:end_date,\'9999-12-31\') AND COALESCE(effective_to,\'9999-12-31\')>=:start_date'
            . ($excludeId !== '' ? ' AND id<>:id' : '') . ' FOR UPDATE';
        $params = [':job' => $jobId, ':target' => $targetId, ':start_date' => $from, ':end_date' => $to];
        if ($excludeId !== '') $params[':id'] = $excludeId;
        $statement = $this->db->prepare($sql); $statement->execute($params);
        if ($statement->fetchColumn()) throw new \InvalidArgumentException('같은 직무와 대상에 적용기간이 겹치는 요구조건이 있습니다.');
    }

    public function referenceCount(string $kind, string $id): int
    {
        if ($kind === 'qualification') {
            $sql = 'SELECT (SELECT COUNT(*) FROM institution_qualifications_employee_records WHERE qualification_type_id=:id) + (SELECT COUNT(*) FROM institution_qualifications_job_requirements WHERE qualification_type_id=:id AND deleted_at IS NULL)';
        } else {
            $sql = 'SELECT (SELECT COUNT(*) FROM institution_educations_employee_records WHERE course_id=:id) + (SELECT COUNT(*) FROM institution_educations_job_requirements WHERE course_id=:id AND deleted_at IS NULL)';
        }
        $statement = $this->db->prepare($sql); $statement->execute([':id' => $id]); return (int) $statement->fetchColumn();
    }

    public function employeeCompliance(string $employeeId, string $asOfDate): array
    {
        $employee = $this->db->prepare('SELECT id,job_id FROM user_employees WHERE id=:id');
        $employee->execute([':id' => $employeeId]);
        $row = $employee->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['job_id'])) return ['job_id' => null, 'qualifications' => [], 'educations' => [], 'satisfied' => true];

        $qualificationSql = "SELECT r.id,r.requirement_level_code,t.qualification_name,
            EXISTS(SELECT 1 FROM institution_qualifications_employee_records q
                WHERE q.employee_id=:employee AND q.qualification_type_id=r.qualification_type_id
                  AND q.deleted_at IS NULL AND q.status_code='ACTIVE'
                  AND (q.valid_from IS NULL OR q.valid_from<=:as_of_q1)
                  AND (q.valid_to IS NULL OR q.valid_to>=:as_of_q2)) satisfied
            FROM institution_qualifications_job_requirements r
            JOIN institution_qualifications_types t ON t.id=r.qualification_type_id
            WHERE r.job_id=:job_q AND r.deleted_at IS NULL
              AND r.effective_from<=:as_of_q3 AND (r.effective_to IS NULL OR r.effective_to>=:as_of_q4)";
        $statement = $this->db->prepare($qualificationSql);
        $statement->execute([':employee'=>$employeeId, ':job_q'=>$row['job_id'], ':as_of_q1'=>$asOfDate, ':as_of_q2'=>$asOfDate, ':as_of_q3'=>$asOfDate, ':as_of_q4'=>$asOfDate]);
        $qualifications = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $educationSql = "SELECT r.id,r.requirement_level_code,c.course_name,c.recurrence_policy_code,
            CASE
              WHEN c.recurrence_policy_code='STATUTORY' THEN 0
              WHEN c.recurrence_policy_code='EVENT' THEN EXISTS(
                SELECT 1 FROM institution_educations_employee_records er
                WHERE er.employee_id=:employee_e1 AND er.course_id=r.course_id AND er.deleted_at IS NULL
                  AND er.completion_status_code='COMPLETED' AND DATE(er.education_end_at)>=COALESCE((
                    SELECT MAX(jh.start_date) FROM institution_job_assignments_job_histories jh
                    WHERE jh.employee_id=:employee_e2 AND jh.job_id=r.job_id AND jh.status_code<>'CANCELLED'
                      AND jh.start_date<=:as_of_e1), '1000-01-01'))
              WHEN c.recurrence_policy_code='PERIODIC' THEN EXISTS(
                SELECT 1 FROM institution_educations_employee_records er
                WHERE er.employee_id=:employee_e3 AND er.course_id=r.course_id AND er.deleted_at IS NULL
                  AND er.completion_status_code='COMPLETED' AND
                    CASE c.recurrence_interval_unit_code
                      WHEN 'DAY' THEN DATE_ADD(DATE(er.education_end_at),INTERVAL c.recurrence_interval_value DAY)
                      WHEN 'MONTH' THEN DATE_ADD(DATE(er.education_end_at),INTERVAL c.recurrence_interval_value MONTH)
                      WHEN 'YEAR' THEN DATE_ADD(DATE(er.education_end_at),INTERVAL c.recurrence_interval_value YEAR)
                    END >=:as_of_e2)
              ELSE EXISTS(SELECT 1 FROM institution_educations_employee_records er
                WHERE er.employee_id=:employee_e4 AND er.course_id=r.course_id AND er.deleted_at IS NULL
                  AND er.completion_status_code='COMPLETED')
            END satisfied
            FROM institution_educations_job_requirements r
            JOIN institution_educations_courses c ON c.id=r.course_id
            WHERE r.job_id=:job_e AND r.deleted_at IS NULL
              AND r.effective_from<=:as_of_e3 AND (r.effective_to IS NULL OR r.effective_to>=:as_of_e4)";
        $statement = $this->db->prepare($educationSql);
        $statement->execute([':employee_e1'=>$employeeId, ':employee_e2'=>$employeeId, ':employee_e3'=>$employeeId, ':employee_e4'=>$employeeId, ':job_e'=>$row['job_id'], ':as_of_e1'=>$asOfDate, ':as_of_e2'=>$asOfDate, ':as_of_e3'=>$asOfDate, ':as_of_e4'=>$asOfDate]);
        $educations = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $required = static fn(array $item): bool => $item['requirement_level_code'] !== 'REQUIRED' || (bool)$item['satisfied'];
        return ['job_id'=>$row['job_id'], 'qualifications'=>$qualifications, 'educations'=>$educations,
            'satisfied'=>array_reduce(array_merge($qualifications,$educations), static fn(bool $ok,array $item): bool=>$ok&&$required($item), true)];
    }

    public function statutoryTypeExists(string $code): bool
    {
        $statement = $this->db->prepare("SELECT COUNT(*) FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE' AND code=:code AND is_active=1");
        $statement->execute([':code' => $code]); return (bool) $statement->fetchColumn();
    }

    public function reorder(string $table, array $ids, string $actor): void
    {
        $statement = $this->db->prepare('UPDATE ' . $table . ' SET sort_no=:sort,updated_at=NOW(),updated_by=:actor WHERE id=:id AND deleted_at IS NULL');
        foreach (array_values(array_unique($ids)) as $index => $id) $statement->execute([':sort' => $index + 1, ':actor' => $actor, ':id' => $id]);
    }

    public function audit(array $data): void
    {
        $this->insert('institution_qualification_education_policy_audits', ['id' => UuidHelper::generate()] + $data);
    }

    private function requirementDefinition(string $kind): array
    {
        return match ($kind) {
            'qualification' => ['institution_qualifications_job_requirements', 'qualification_type_id', ' JOIN institution_qualifications_types t ON t.id=r.qualification_type_id', 't.qualification_code,t.qualification_name target_name'],
            'education' => ['institution_educations_job_requirements', 'course_id', ' JOIN institution_educations_courses c ON c.id=r.course_id', 'c.course_code,c.course_name target_name'],
            default => throw new \InvalidArgumentException('요구조건 유형이 올바르지 않습니다.'),
        };
    }

    private function insert(string $table, array $data): void
    {
        $fields = array_keys($data);
        $statement = $this->db->prepare('INSERT INTO ' . $table . ' (' . implode(',', $fields) . ') VALUES (:' . implode(',:', $fields) . ')');
        $statement->execute(array_combine(array_map(static fn($field) => ':' . $field, $fields), array_values($data)));
    }

    private function enrich(array $rows): array
    {
        return array_map(static fn(array $row) => ActorHelper::enrichActorNamesRow($row, ['created_by', 'updated_by', 'deleted_by']), $rows);
    }
}
