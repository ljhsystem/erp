<?php

namespace App\Models\Institution;

use PDO;

final class WorkplaceSizePeriodModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findByRequestKey(string $companyId, string $requestKey): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM institution_workplace_size_periods WHERE company_id = :company_id AND request_key = :request_key');
        $statement->execute([':company_id' => $companyId, ':request_key' => $requestKey]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function lockCompany(string $companyId): void
    {
        $statement = $this->db->prepare('SELECT id FROM system_company WHERE id = :id FOR UPDATE');
        $statement->execute([':id' => $companyId]);
        if (!$statement->fetchColumn()) throw new \InvalidArgumentException('회사를 찾을 수 없습니다.');
    }

    public function findForUpdate(string $id): ?array
    {
        $statement = $this->db->prepare('SELECT p.* FROM institution_workplace_size_periods p LEFT JOIN institution_workplace_size_periods next_revision ON next_revision.previous_period_id = p.id WHERE p.id = :id AND next_revision.id IS NULL FOR UPDATE');
        $statement->execute([':id' => $id]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function resolve(string $companyId, string $purposeCode, string $date, bool $lock = false): ?array
    {
        $sql = 'SELECT p.* FROM institution_workplace_size_periods p LEFT JOIN institution_workplace_size_periods next_revision ON next_revision.previous_period_id = p.id WHERE p.company_id = :company_id AND p.calculation_purpose_code = :purpose_code AND p.confirmation_status_code = \'CONFIRMED\' AND p.effective_from <= :target_date_from AND (p.effective_to IS NULL OR p.effective_to >= :target_date_to) AND next_revision.id IS NULL ORDER BY p.effective_from DESC, p.revision_no DESC LIMIT 1';
        $statement = $this->db->prepare($sql . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute([':company_id' => $companyId, ':purpose_code' => $purposeCode, ':target_date_from' => $date, ':target_date_to' => $date]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function hasLeafOverlap(string $companyId, string $purposeCode, string $from, ?string $to, ?string $excludedId = null): bool
    {
        $sql = 'SELECT 1 FROM institution_workplace_size_periods p LEFT JOIN institution_workplace_size_periods next_revision ON next_revision.previous_period_id = p.id WHERE p.company_id = :company_id AND p.calculation_purpose_code = :purpose_code AND next_revision.id IS NULL AND p.id <> COALESCE(:excluded_id, \'\') AND p.effective_from <= COALESCE(:effective_to, \'9999-12-31\') AND COALESCE(p.effective_to, \'9999-12-31\') >= :effective_from LIMIT 1 FOR UPDATE';
        $statement = $this->db->prepare($sql);
        $statement->execute([':company_id' => $companyId, ':purpose_code' => $purposeCode, ':excluded_id' => $excludedId, ':effective_from' => $from, ':effective_to' => $to]);
        return (bool) $statement->fetchColumn();
    }

    public function insert(array $row): void
    {
        $columns = array_keys($row);
        $sql = 'INSERT INTO institution_workplace_size_periods (`' . implode('`,`', $columns) . '`) VALUES (:' . implode(',:', $columns) . ')';
        $statement = $this->db->prepare($sql);
        $statement->execute(array_combine(array_map(fn (string $column): string => ':' . $column, $columns), array_values($row)));
    }
}
