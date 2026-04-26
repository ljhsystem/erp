<?php
namespace App\Models\System;

use Core\Database;
use PDO;

class WorkTeamMemberModel
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
                m.*,
                t.team_name,
                t.team_code,
                c.client_name,
                c.company_name
            FROM system_work_team_members m
            LEFT JOIN system_work_teams t ON t.id = m.team_id
            LEFT JOIN system_clients c ON c.id = m.client_id
            WHERE m.deleted_at IS NULL
        ";

        $params = [];

        $fieldMap = [
            'team_id' => ['col' => 'm.team_id', 'type' => 'exact'],
            'client_id' => ['col' => 'm.client_id', 'type' => 'exact'],
            'team_name' => ['col' => 't.team_name', 'type' => 'like'],
            'team_code' => ['col' => 't.team_code', 'type' => 'like'],
            'client_name' => ['col' => 'c.client_name', 'type' => 'like'],
            'company_name' => ['col' => 'c.company_name', 'type' => 'like'],
            'role' => ['col' => 'm.role', 'type' => 'like'],
            'note' => ['col' => 'm.note', 'type' => 'like'],
            'is_active' => ['col' => 'm.is_active', 'type' => 'exact'],
            'joined_at' => ['col' => 'm.joined_at', 'type' => 'date'],
            'left_at' => ['col' => 'm.left_at', 'type' => 'date'],
            'created_at' => ['col' => 'm.created_at', 'type' => 'date'],
            'updated_at' => ['col' => 'm.updated_at', 'type' => 'date'],
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
            $searchCols = ['t.team_name', 't.team_code', 'c.client_name', 'c.company_name', 'm.role', 'm.note'];
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

        $sql .= " ORDER BY t.sort_no ASC, t.team_name ASC, c.client_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                m.*,
                t.team_name,
                t.team_code,
                c.client_name,
                c.company_name
            FROM system_work_team_members m
            LEFT JOIN system_work_teams t ON t.id = m.team_id
            LEFT JOIN system_clients c ON c.id = m.client_id
            WHERE m.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getByTeamId(string $teamId, bool $activeOnly = true): array
    {
        $sql = "
            SELECT
                m.*,
                c.client_name,
                c.company_name
            FROM system_work_team_members m
            LEFT JOIN system_clients c ON c.id = m.client_id
            WHERE m.team_id = :team_id
              AND m.deleted_at IS NULL
        ";

        if ($activeOnly) {
            $sql .= " AND m.is_active = 1";
        }

        $sql .= " ORDER BY c.client_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':team_id' => $teamId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO system_work_team_members (
                id, team_id, client_id, role, note, joined_at, left_at,
                is_active, created_by, updated_by
            ) VALUES (
                :id, :team_id, :client_id, :role, :note, :joined_at, :left_at,
                :is_active, :created_by, :updated_by
            )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id' => $data['id'],
            ':team_id' => $data['team_id'],
            ':client_id' => $data['client_id'],
            ':role' => $data['role'] ?? null,
            ':note' => $data['note'] ?? null,
            ':joined_at' => $data['joined_at'] ?? date('Y-m-d H:i:s'),
            ':left_at' => $data['left_at'] ?? null,
            ':is_active' => (int)($data['is_active'] ?? 1),
            ':created_by' => $data['created_by'] ?? null,
            ':updated_by' => $data['updated_by'] ?? ($data['created_by'] ?? null),
        ]);
    }

    public function updateById(string $id, array $data): bool
    {
        $sql = "
            UPDATE system_work_team_members SET
                team_id = :team_id,
                client_id = :client_id,
                role = :role,
                note = :note,
                joined_at = :joined_at,
                left_at = :left_at,
                is_active = :is_active,
                updated_by = :updated_by
            WHERE id = :id
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id' => $id,
            ':team_id' => $data['team_id'],
            ':client_id' => $data['client_id'],
            ':role' => $data['role'] ?? null,
            ':note' => $data['note'] ?? null,
            ':joined_at' => $data['joined_at'] ?? null,
            ':left_at' => $data['left_at'] ?? null,
            ':is_active' => (int)($data['is_active'] ?? 1),
            ':updated_by' => $data['updated_by'] ?? null,
        ]);
    }

    public function deleteById(string $id, string $actor): bool
    {
        $stmt = $this->db->prepare("
            UPDATE system_work_team_members
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
                m.*,
                t.team_name,
                t.team_code,
                c.client_name,
                c.company_name
            FROM system_work_team_members m
            LEFT JOIN system_work_teams t ON t.id = m.team_id
            LEFT JOIN system_clients c ON c.id = m.client_id
            WHERE m.deleted_at IS NOT NULL
            ORDER BY m.deleted_at DESC
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function restoreById(string $id, string $actor): bool
    {
        $stmt = $this->db->prepare("
            UPDATE system_work_team_members
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
        $stmt = $this->db->prepare("DELETE FROM system_work_team_members WHERE id = :id");

        return $stmt->execute([':id' => $id]);
    }
}
