<?php

namespace App\Models\Institution;

use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PDO;

class EmployeeAssignmentAuditModel
{
    public function __construct(private readonly PDO $db) {}

    public function findByRequestKey(string $requestKey): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM institution_job_assignments_audits WHERE request_key=:request_key LIMIT 1');
        $stmt->execute([':request_key' => $requestKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        return $row ? ActorHelper::enrichActorNamesRow($row, ['processed_by_name' => 'processed_by']) : null;
    }

    public function hasCreateAudit(string $domain, string $assignmentId): bool
    {
        $column = $this->domainColumn($domain);
        $stmt = $this->db->prepare("SELECT 1 FROM institution_job_assignments_audits WHERE {$column}=:id AND action_type='CREATE' LIMIT 1");
        $stmt->execute([':id' => $assignmentId]);
        return (bool) $stmt->fetchColumn();
    }

    public function createAssignmentIds(string $employeeId): array
    {
        $stmt = $this->db->prepare("SELECT assignment_domain,job_assignment_id,project_assignment_id FROM institution_job_assignments_audits WHERE employee_id=:employee_id AND action_type='CREATE'");
        $stmt->execute([':employee_id' => $employeeId]);
        $result = ['JOB' => [], 'PROJECT' => []];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $domain = (string) $row['assignment_domain'];
            $column = $domain === 'JOB' ? 'job_assignment_id' : 'project_assignment_id';
            if (isset($result[$domain]) && !empty($row[$column])) $result[$domain][(string) $row[$column]] = true;
        }
        return $result;
    }

    public function record(array $data): array
    {
        $domain = strtoupper((string) $data['assignment_domain']);
        $column = $this->domainColumn($domain);
        $id = UuidHelper::generate();
        $assignmentId = (string) $data['assignment_id'];
        $row = [
            'id' => $id,
            'assignment_domain' => $domain,
            'job_assignment_id' => null,
            'project_assignment_id' => null,
            'workplace_assignment_id' => null,
            'employee_id' => (string) $data['employee_id'],
            'action_type' => (string) $data['action_type'],
            'source_type' => (string) $data['source_type'],
            'reason' => (string) $data['reason'],
            'personnel_action_target_id' => $data['personnel_action_target_id'] ?? null,
            'request_key' => (string) $data['request_key'],
            'before_data' => $this->encode($data['before_data'] ?? null),
            'after_data' => $this->encode($data['after_data']),
            'processed_by' => (string) $data['processed_by'],
        ];
        $row[$column] = $assignmentId;
        $columns = array_keys($row);
        $stmt = $this->db->prepare(
            'INSERT INTO institution_job_assignments_audits (`' . implode('`,`', $columns) . '`) VALUES (:' . implode(',:', $columns) . ')'
        );
        $stmt->execute($row);
        return $this->findByRequestKey((string) $data['request_key']) ?? $row;
    }

    private function domainColumn(string $domain): string
    {
        return match ($domain) {
            'JOB' => 'job_assignment_id',
            'PROJECT' => 'project_assignment_id',
            'WORKPLACE' => 'workplace_assignment_id',
            default => throw new \InvalidArgumentException('배치 도메인을 확인해 주세요.'),
        };
    }

    private function encode(?array $value): ?string
    {
        return $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
