<?php

namespace App\Models\Institution;

use PDO;

class PayComponentModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function activeForDate(string $date): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM institution_employment_contracts_pay_components
             WHERE is_active = 1 AND deleted_at IS NULL
               AND (effective_from IS NULL OR effective_from <= :date_from)
               AND (effective_to IS NULL OR effective_to >= :date_to)
             ORDER BY sort_no ASC, component_name ASC, id ASC'
        );
        $stmt->execute([':date_from' => $date, ':date_to' => $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findActive(string $id, string $date): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM institution_employment_contracts_pay_components
             WHERE id = :id AND is_active = 1 AND deleted_at IS NULL
               AND (effective_from IS NULL OR effective_from <= :date_from)
               AND (effective_to IS NULL OR effective_to >= :date_to) LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':date_from' => $date, ':date_to' => $date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function all(bool $includeDeleted = false): array
    {
        $sql = 'SELECT * FROM institution_employment_contracts_pay_components';
        if (!$includeDeleted) $sql .= ' WHERE deleted_at IS NULL';
        $sql .= ' ORDER BY sort_no ASC, component_name ASC, id ASC';
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(string $id, bool $includeDeleted = false): ?array
    {
        $sql = 'SELECT * FROM institution_employment_contracts_pay_components WHERE id = :id';
        if (!$includeDeleted) $sql .= ' AND deleted_at IS NULL';
        $stmt = $this->db->prepare($sql . ' LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function codeExists(string $code, string $exceptId = ''): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM institution_employment_contracts_pay_components WHERE component_code = :code AND id <> :id LIMIT 1');
        $stmt->execute([':code' => $code, ':id' => $exceptId]);
        return (bool) $stmt->fetchColumn();
    }

    public function sortNoExists(int $sortNo, string $exceptId = ''): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM institution_employment_contracts_pay_components WHERE sort_no = :sort_no AND id <> :id LIMIT 1');
        $stmt->execute([':sort_no' => $sortNo, ':id' => $exceptId]);
        return (bool) $stmt->fetchColumn();
    }

    public function nextSortNo(): int
    {
        return max(1, (int) $this->db->query('SELECT COALESCE(MAX(sort_no), 0) + 1 FROM institution_employment_contracts_pay_components')->fetchColumn());
    }

    public function insert(array $data): void
    {
        $columns = array_keys($data);
        $sql = 'INSERT INTO institution_employment_contracts_pay_components (`' . implode('`,`', $columns) . '`) VALUES (:' . implode(',:', $columns) . ')';
        $this->db->prepare($sql)->execute(array_combine(array_map(static fn(string $column): string => ':' . $column, $columns), array_values($data)));
    }

    public function update(string $id, array $data): void
    {
        $sets = array_map(static fn(string $column): string => "`{$column}` = :{$column}", array_keys($data));
        $stmt = $this->db->prepare('UPDATE institution_employment_contracts_pay_components SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute([':id' => $id] + array_combine(array_map(static fn(string $column): string => ':' . $column, array_keys($data)), array_values($data)));
    }

    public function updateSortNo(string $id, int $sortNo, string $updatedAt, string $updatedBy): void
    {
        $stmt = $this->db->prepare(
            'UPDATE institution_employment_contracts_pay_components
             SET sort_no = :sort_no, updated_at = :updated_at, updated_by = :updated_by
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':sort_no' => $sortNo, ':updated_at' => $updatedAt, ':updated_by' => $updatedBy, ':id' => $id]);
    }
}
