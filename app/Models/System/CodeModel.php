<?php
namespace App\Models\System;

use Core\Database;
use PDO;

class CodeModel
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getList(array $filters = []): array
    {
        $sql = "
            SELECT
                c.*,
                CASE
                    WHEN c.created_by LIKE 'SYSTEM:%' THEN c.created_by
                    WHEN p1.employee_name IS NOT NULL THEN CONCAT('USER:', p1.employee_name)
                    ELSE c.created_by
                END AS created_by_name,
                CASE
                    WHEN c.updated_by LIKE 'SYSTEM:%' THEN c.updated_by
                    WHEN p2.employee_name IS NOT NULL THEN CONCAT('USER:', p2.employee_name)
                    ELSE c.updated_by
                END AS updated_by_name,
                CASE
                    WHEN c.deleted_by LIKE 'SYSTEM:%' THEN c.deleted_by
                    WHEN p3.employee_name IS NOT NULL THEN CONCAT('USER:', p3.employee_name)
                    ELSE c.deleted_by
                END AS deleted_by_name
            FROM system_codes c
            LEFT JOIN user_employees p1
                ON c.created_by NOT LIKE 'SYSTEM:%'
               AND p1.user_id = REPLACE(c.created_by, 'USER:', '')
            LEFT JOIN user_employees p2
                ON c.updated_by NOT LIKE 'SYSTEM:%'
               AND p2.user_id = REPLACE(c.updated_by, 'USER:', '')
            LEFT JOIN user_employees p3
                ON c.deleted_by NOT LIKE 'SYSTEM:%'
               AND p3.user_id = REPLACE(c.deleted_by, 'USER:', '')
            WHERE c.deleted_at IS NULL
        ";

        $params = [];

        $fieldMap = [
            'sort_no' => ['col' => 'c.sort_no', 'type' => 'exact'],
            'code_group' => ['col' => 'c.code_group', 'type' => 'exact'],
            'code' => ['col' => 'c.code', 'type' => 'like'],
            'code_name' => ['col' => 'c.code_name', 'type' => 'like'],
            'note' => ['col' => 'c.note', 'type' => 'like'],
            'memo' => ['col' => 'c.memo', 'type' => 'like'],
            'is_active' => ['col' => 'c.is_active', 'type' => 'exact'],
            'created_at' => ['col' => 'c.created_at', 'type' => 'date'],
            'created_by' => ['col' => 'c.created_by', 'type' => 'like'],
            'created_by_name' => ['col' => "COALESCE(CONCAT('USER:', p1.employee_name), c.created_by)", 'type' => 'like'],
            'updated_at' => ['col' => 'c.updated_at', 'type' => 'date'],
            'updated_by' => ['col' => 'c.updated_by', 'type' => 'like'],
            'updated_by_name' => ['col' => "COALESCE(CONCAT('USER:', p2.employee_name), c.updated_by)", 'type' => 'like'],
            'deleted_at' => ['col' => 'c.deleted_at', 'type' => 'date'],
            'deleted_by' => ['col' => 'c.deleted_by', 'type' => 'like'],
            'deleted_by_name' => ['col' => "COALESCE(CONCAT('USER:', p3.employee_name), c.deleted_by)", 'type' => 'like'],
        ];

        $globalSearch = [];

        foreach ($filters as $filter) {
            $field = $filter['field'] ?? '';
            $value = $filter['value'] ?? '';

            if ($value === '' || $value === null) {
                continue;
            }

            if ($field === '') {
                $globalSearch[] = $value;
                continue;
            }

            if (!isset($fieldMap[$field])) {
                continue;
            }

            $col = $fieldMap[$field]['col'];
            $type = $fieldMap[$field]['type'];

            if ($type === 'date') {
                if (is_array($value)) {
                    $sql .= " AND DATE({$col}) BETWEEN ? AND ?";
                    $params[] = $value['start'];
                    $params[] = $value['end'];
                } else {
                    $sql .= " AND DATE({$col}) = ?";
                    $params[] = $value;
                }
                continue;
            }

            if ($type === 'like') {
                $sql .= " AND {$col} LIKE ?";
                $params[] = "%{$value}%";
                continue;
            }

            $sql .= " AND {$col} = ?";
            $params[] = $value;
        }

        if (!empty($globalSearch)) {
            $searchCols = [
                'c.code',
                'c.code_name',
                'c.note',
                'c.memo',
                'c.created_by',
                'c.updated_by',
                "COALESCE(CONCAT('USER:', p1.employee_name), c.created_by)",
                "COALESCE(CONCAT('USER:', p2.employee_name), c.updated_by)"
            ];
            $sql .= " AND (";

            $groups = [];
            foreach ($globalSearch as $keyword) {
                $parts = [];
                foreach ($searchCols as $col) {
                    $parts[] = "{$col} LIKE ?";
                    $params[] = "%{$keyword}%";
                }
                $groups[] = '(' . implode(' OR ', $parts) . ')';
            }

            $sql .= implode(' OR ', $groups) . ")";
        }

        $sql .= " ORDER BY c.code_group ASC, c.sort_no ASC, c.code_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.*,
                CASE
                    WHEN c.created_by LIKE 'SYSTEM:%' THEN c.created_by
                    WHEN p1.employee_name IS NOT NULL THEN CONCAT('USER:', p1.employee_name)
                    ELSE c.created_by
                END AS created_by_name,
                CASE
                    WHEN c.updated_by LIKE 'SYSTEM:%' THEN c.updated_by
                    WHEN p2.employee_name IS NOT NULL THEN CONCAT('USER:', p2.employee_name)
                    ELSE c.updated_by
                END AS updated_by_name,
                CASE
                    WHEN c.deleted_by LIKE 'SYSTEM:%' THEN c.deleted_by
                    WHEN p3.employee_name IS NOT NULL THEN CONCAT('USER:', p3.employee_name)
                    ELSE c.deleted_by
                END AS deleted_by_name
            FROM system_codes c
            LEFT JOIN user_employees p1
                ON c.created_by NOT LIKE 'SYSTEM:%'
               AND p1.user_id = REPLACE(c.created_by, 'USER:', '')
            LEFT JOIN user_employees p2
                ON c.updated_by NOT LIKE 'SYSTEM:%'
               AND p2.user_id = REPLACE(c.updated_by, 'USER:', '')
            LEFT JOIN user_employees p3
                ON c.deleted_by NOT LIKE 'SYSTEM:%'
               AND p3.user_id = REPLACE(c.deleted_by, 'USER:', '')
            WHERE c.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getGroups(): array
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT UPPER(TRIM(code_group)) AS code_group
            FROM system_codes
            WHERE deleted_at IS NULL
              AND code_group IS NOT NULL
              AND TRIM(code_group) <> ''
            ORDER BY code_group ASC
        ");
        $stmt->execute();

        return array_values(array_filter(array_map(
            static fn(array $row): string => (string)($row['code_group'] ?? ''),
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        )));
    }

    public function getByGroup(string $codeGroup, bool $activeOnly = true): array
    {
        $sql = "
            SELECT *
            FROM system_codes
            WHERE code_group = :code_group
              AND deleted_at IS NULL
        ";

        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }

        $sql .= " ORDER BY sort_no ASC, code_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':code_group' => $codeGroup]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getOptionsByGroup(string $codeGroup): array
    {
        $stmt = $this->db->prepare("
            SELECT code, code_name
            FROM system_codes
            WHERE code_group = :code_group
              AND is_active = 1
              AND deleted_at IS NULL
            ORDER BY sort_no ASC, code_name ASC
        ");
        $stmt->execute([':code_group' => $codeGroup]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getByGroupAndCode(string $codeGroup, string $code): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM system_codes
            WHERE code_group = :code_group
              AND code = :code
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([
            ':code_group' => $codeGroup,
            ':code' => $code,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function existsByGroupAndCode(string $codeGroup, string $code, ?string $excludeId = null): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM system_codes
            WHERE code_group = :code_group
              AND code = :code
        ";

        $params = [
            ':code_group' => $codeGroup,
            ':code' => $code,
        ];

        if ($excludeId !== null && $excludeId !== '') {
            $sql .= " AND id <> :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO system_codes (
                id, sort_no, code_group, code, code_name, note, memo,
                is_active, created_by, updated_by, extra_data
            ) VALUES (
                :id, :sort_no, :code_group, :code, :code_name, :note, :memo,
                :is_active, :created_by, :updated_by, :extra_data
            )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id' => $data['id'],
            ':sort_no' => (int)($data['sort_no'] ?? 0),
            ':code_group' => trim((string)($data['code_group'] ?? '')),
            ':code' => trim((string)($data['code'] ?? '')),
            ':code_name' => trim((string)($data['code_name'] ?? '')),
            ':note' => $data['note'] ?? null,
            ':memo' => $data['memo'] ?? null,
            ':is_active' => (int)($data['is_active'] ?? 1),
            ':created_by' => $data['created_by'] ?? null,
            ':updated_by' => $data['updated_by'] ?? ($data['created_by'] ?? null),
            ':extra_data' => $data['extra_data'] ?? null,
        ]);
    }

    public function updateById(string $id, array $data): bool
    {
        $sql = "
            UPDATE system_codes SET
                sort_no = :sort_no,
                code_group = :code_group,
                code = :code,
                code_name = :code_name,
                note = :note,
                memo = :memo,
                is_active = :is_active,
                updated_by = :updated_by,
                extra_data = :extra_data
            WHERE id = :id
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id' => $id,
            ':sort_no' => (int)($data['sort_no'] ?? 0),
            ':code_group' => trim((string)($data['code_group'] ?? '')),
            ':code' => trim((string)($data['code'] ?? '')),
            ':code_name' => trim((string)($data['code_name'] ?? '')),
            ':note' => $data['note'] ?? null,
            ':memo' => $data['memo'] ?? null,
            ':is_active' => (int)($data['is_active'] ?? 1),
            ':updated_by' => $data['updated_by'] ?? null,
            ':extra_data' => $data['extra_data'] ?? null,
        ]);
    }

    public function deleteById(string $id, string $actor): bool
    {
        $stmt = $this->db->prepare("
            UPDATE system_codes
            SET is_active = 0,
                deleted_at = NOW(),
                deleted_by = :deleted_by,
                updated_by = :updated_by
            WHERE id = :id
              AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':id' => $id,
            ':deleted_by' => $actor,
            ':updated_by' => $actor,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function getDeleted(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.*,
                CASE
                    WHEN c.created_by LIKE 'SYSTEM:%' THEN c.created_by
                    WHEN p1.employee_name IS NOT NULL THEN CONCAT('USER:', p1.employee_name)
                    ELSE c.created_by
                END AS created_by_name,
                CASE
                    WHEN c.updated_by LIKE 'SYSTEM:%' THEN c.updated_by
                    WHEN p2.employee_name IS NOT NULL THEN CONCAT('USER:', p2.employee_name)
                    ELSE c.updated_by
                END AS updated_by_name,
                CASE
                    WHEN c.deleted_by LIKE 'SYSTEM:%' THEN c.deleted_by
                    WHEN p3.employee_name IS NOT NULL THEN CONCAT('USER:', p3.employee_name)
                    ELSE c.deleted_by
                END AS deleted_by_name
            FROM system_codes c
            LEFT JOIN user_employees p1
                ON c.created_by NOT LIKE 'SYSTEM:%'
               AND p1.user_id = REPLACE(c.created_by, 'USER:', '')
            LEFT JOIN user_employees p2
                ON c.updated_by NOT LIKE 'SYSTEM:%'
               AND p2.user_id = REPLACE(c.updated_by, 'USER:', '')
            LEFT JOIN user_employees p3
                ON c.deleted_by NOT LIKE 'SYSTEM:%'
               AND p3.user_id = REPLACE(c.deleted_by, 'USER:', '')
            WHERE c.deleted_at IS NOT NULL
            ORDER BY c.deleted_at DESC
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function restoreById(string $id, string $actor): bool
    {
        $stmt = $this->db->prepare("
            UPDATE system_codes
            SET is_active = 1,
                deleted_at = NULL,
                deleted_by = NULL,
                updated_by = :actor
            WHERE id = :id
              AND deleted_at IS NOT NULL
        ");

        $stmt->execute([
            ':id' => $id,
            ':actor' => $actor,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function hardDeleteById(string $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM system_codes WHERE id = :id");

        return $stmt->execute([':id' => $id]);
    }

    public function updateSortNo(string $id, string $newSortNo): bool
    {
        $stmt = $this->db->prepare("
            UPDATE system_codes
            SET sort_no = :sort_no
            WHERE id = :id
        ");

        return $stmt->execute([
            ':sort_no' => (int)$newSortNo,
            ':id' => $id,
        ]);
    }
}
