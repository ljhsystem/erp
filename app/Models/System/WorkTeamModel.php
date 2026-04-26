<?php
namespace App\Models\System;

use Core\Database;
use PDO;

class WorkTeamModel
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
                t.*,
                CASE
                    WHEN t.created_by LIKE 'SYSTEM:%' THEN t.created_by
                    WHEN e1.employee_name IS NOT NULL THEN CONCAT('USER:', e1.employee_name)
                    ELSE t.created_by
                END AS created_by_name,
                CASE
                    WHEN t.updated_by LIKE 'SYSTEM:%' THEN t.updated_by
                    WHEN e2.employee_name IS NOT NULL THEN CONCAT('USER:', e2.employee_name)
                    ELSE t.updated_by
                END AS updated_by_name,
                CASE
                    WHEN t.deleted_by LIKE 'SYSTEM:%' THEN t.deleted_by
                    WHEN e3.employee_name IS NOT NULL THEN CONCAT('USER:', e3.employee_name)
                    ELSE t.deleted_by
                END AS deleted_by_name,
                c.client_name AS team_leader_client_name
            FROM system_work_teams t
            LEFT JOIN system_clients c
                ON c.id = t.team_leader_client_id
               AND c.deleted_at IS NULL
            LEFT JOIN user_employees e1
                ON t.created_by NOT LIKE 'SYSTEM:%'
               AND e1.user_id = REPLACE(t.created_by, 'USER:', '')
            LEFT JOIN user_employees e2
                ON t.updated_by NOT LIKE 'SYSTEM:%'
               AND e2.user_id = REPLACE(t.updated_by, 'USER:', '')
            LEFT JOIN user_employees e3
                ON t.deleted_by NOT LIKE 'SYSTEM:%'
               AND e3.user_id = REPLACE(t.deleted_by, 'USER:', '')
            WHERE t.deleted_at IS NULL
        ";

        $params = [];

        $fieldMap = [
            'sort_no' => ['col' => 't.sort_no', 'type' => 'exact'],
            'team_name' => ['col' => 't.team_name', 'type' => 'like'],
            'team_leader_client_name' => ['col' => 'c.client_name', 'type' => 'like'],
            'note' => ['col' => 't.note', 'type' => 'like'],
            'memo' => ['col' => 't.memo', 'type' => 'like'],
            'is_active' => ['col' => 't.is_active', 'type' => 'exact'],
            'created_at' => ['col' => 't.created_at', 'type' => 'date'],
            'created_by' => ['col' => 't.created_by', 'type' => 'like'],
            'created_by_name' => ['col' => "COALESCE(CONCAT('USER:', e1.employee_name), t.created_by)", 'type' => 'like'],
            'updated_at' => ['col' => 't.updated_at', 'type' => 'date'],
            'updated_by' => ['col' => 't.updated_by', 'type' => 'like'],
            'updated_by_name' => ['col' => "COALESCE(CONCAT('USER:', e2.employee_name), t.updated_by)", 'type' => 'like'],
            'deleted_at' => ['col' => 't.deleted_at', 'type' => 'date'],
            'deleted_by' => ['col' => 't.deleted_by', 'type' => 'like'],
            'deleted_by_name' => ['col' => "COALESCE(CONCAT('USER:', e3.employee_name), t.deleted_by)", 'type' => 'like'],
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
                't.team_name',
                'c.client_name',
                't.note',
                't.memo',
                't.created_by',
                't.updated_by',
                "COALESCE(CONCAT('USER:', e1.employee_name), t.created_by)",
                "COALESCE(CONCAT('USER:', e2.employee_name), t.updated_by)",
            ];
            $groups = [];

            foreach ($globalSearch as $keyword) {
                $parts = [];
                foreach ($searchCols as $col) {
                    $parts[] = "{$col} LIKE ?";
                    $params[] = "%{$keyword}%";
                }
                $groups[] = '(' . implode(' OR ', $parts) . ')';
            }

            $sql .= " AND (" . implode(' OR ', $groups) . ")";
        }

        $sql .= " ORDER BY t.sort_no ASC, t.team_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                t.*,
                c.client_name AS team_leader_client_name
            FROM system_work_teams t
            LEFT JOIN system_clients c
                ON c.id = t.team_leader_client_id
               AND c.deleted_at IS NULL
            WHERE t.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO system_work_teams (
                id, sort_no, team_name, team_leader_client_id, note, memo,
                is_active, created_by, updated_by
            ) VALUES (
                :id, :sort_no, :team_name, :team_leader_client_id, :note, :memo,
                :is_active, :created_by, :updated_by
            )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id' => $data['id'],
            ':sort_no' => (int)($data['sort_no'] ?? 0),
            ':team_name' => trim((string)($data['team_name'] ?? '')),
            ':team_leader_client_id' => $data['team_leader_client_id'] ?? null,
            ':note' => $data['note'] ?? null,
            ':memo' => $data['memo'] ?? null,
            ':is_active' => (int)($data['is_active'] ?? 1),
            ':created_by' => $data['created_by'] ?? null,
            ':updated_by' => $data['updated_by'] ?? ($data['created_by'] ?? null),
        ]);
    }

    public function updateById(string $id, array $data): bool
    {
        $sql = "
            UPDATE system_work_teams SET
                sort_no = :sort_no,
                team_name = :team_name,
                team_leader_client_id = :team_leader_client_id,
                note = :note,
                memo = :memo,
                is_active = :is_active,
                updated_by = :updated_by
            WHERE id = :id
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id' => $id,
            ':sort_no' => (int)($data['sort_no'] ?? 0),
            ':team_name' => trim((string)($data['team_name'] ?? '')),
            ':team_leader_client_id' => $data['team_leader_client_id'] ?? null,
            ':note' => $data['note'] ?? null,
            ':memo' => $data['memo'] ?? null,
            ':is_active' => (int)($data['is_active'] ?? 1),
            ':updated_by' => $data['updated_by'] ?? null,
        ]);
    }

    public function deleteById(string $id, string $actor): bool
    {
        $stmt = $this->db->prepare("
            UPDATE system_work_teams
            SET is_active = 0,
                deleted_at = NOW(),
                deleted_by = :actor,
                updated_by = :actor
            WHERE id = :id
        ");
        $stmt->execute([
            ':id' => $id,
            ':actor' => $actor,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function getDeleted(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                t.*,
                CASE
                    WHEN t.deleted_by LIKE 'SYSTEM:%' THEN t.deleted_by
                    WHEN e.employee_name IS NOT NULL THEN CONCAT('USER:', e.employee_name)
                    ELSE t.deleted_by
                END AS deleted_by_name,
                c.client_name AS team_leader_client_name
            FROM system_work_teams t
            LEFT JOIN system_clients c
                ON c.id = t.team_leader_client_id
               AND c.deleted_at IS NULL
            LEFT JOIN user_employees e
                ON t.deleted_by NOT LIKE 'SYSTEM:%'
               AND e.user_id = REPLACE(t.deleted_by, 'USER:', '')
            WHERE t.deleted_at IS NOT NULL
            ORDER BY t.deleted_at DESC
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function restoreById(string $id, string $actor): bool
    {
        $stmt = $this->db->prepare("
            UPDATE system_work_teams
            SET is_active = 1,
                deleted_at = NULL,
                deleted_by = NULL,
                updated_by = :actor
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id' => $id,
            ':actor' => $actor,
        ]);
    }

    public function hardDeleteById(string $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM system_work_teams WHERE id = :id");

        return $stmt->execute([':id' => $id]);
    }

    public function updateSortNo(string $id, string $newSortNo): bool
    {
        $stmt = $this->db->prepare("
            UPDATE system_work_teams
            SET sort_no = :sort_no
            WHERE id = :id
        ");

        return $stmt->execute([
            ':sort_no' => (int)$newSortNo,
            ':id' => $id,
        ]);
    }
}
