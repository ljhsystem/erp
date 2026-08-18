<?php

namespace App\Models\Auth;

use Core\Database;
use Core\Helpers\ActorHelper;
use PDO;

class RoleModel
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getAll(array $filters = []): array
    {
        $sql = "
            SELECT id, sort_no, role_key, role_name, description, is_active,
                   created_at, created_by, updated_at, updated_bY AS updated_by
              FROM auth_roles
             WHERE 1=1
        ";
        $params = [];
        $fieldMap = [
            'id' => ['expr' => 'id', 'type' => 'exact'],
            'sort_no' => ['expr' => 'sort_no', 'type' => 'like'],
            'role_key' => ['expr' => 'role_key', 'type' => 'like'],
            'role_name' => ['expr' => 'role_name', 'type' => 'like'],
            'description' => ['expr' => 'description', 'type' => 'like'],
            'is_active' => ['expr' => 'is_active', 'type' => 'exact'],
            'created_at' => ['expr' => 'created_at', 'type' => 'datetime'],
            'created_by' => ['expr' => 'created_by', 'type' => 'like'],
            'updated_at' => ['expr' => 'updated_at', 'type' => 'datetime'],
            'updated_by' => ['expr' => 'updated_bY', 'type' => 'like'],
            'updated_bY' => ['expr' => 'updated_bY', 'type' => 'like'],
        ];
        $globalSearchValues = [];

        foreach ($filters as $filter) {
            $field = trim((string) ($filter['field'] ?? ''));
            $value = $filter['value'] ?? '';
            if ($value === '' || $value === null) {
                continue;
            }
            if ($field === '') {
                $globalSearchValues[] = $value;
                continue;
            }
            if (!isset($fieldMap[$field])) {
                continue;
            }

            ['expr' => $expr, 'type' => $type] = $fieldMap[$field];
            if ($type === 'datetime') {
                if (is_array($value)) {
                    $start = trim((string) ($value['start'] ?? ''));
                    $end = trim((string) ($value['end'] ?? ''));
                    if ($start !== '' && $end !== '') {
                        $sql .= " AND {$expr} BETWEEN ? AND ?";
                        $params[] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) ? $start . ' 00:00:00' : $start;
                        $params[] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) ? $end . ' 23:59:59' : $end;
                    }
                } else {
                    $date = trim((string) $value);
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                        $sql .= " AND {$expr} BETWEEN ? AND ?";
                        $params[] = $date . ' 00:00:00';
                        $params[] = $date . ' 23:59:59';
                    } else {
                        $sql .= " AND {$expr} = ?";
                        $params[] = $date;
                    }
                }
                continue;
            }
            if ($type === 'exact') {
                $sql .= " AND {$expr} = ?";
                $params[] = $value;
                continue;
            }

            $keywords = array_filter(array_map('trim', explode(',', (string) $value)));
            if ($keywords !== []) {
                $parts = [];
                foreach ($keywords as $keyword) {
                    $parts[] = "{$expr} LIKE ?";
                    $params[] = '%' . $keyword . '%';
                }
                $sql .= ' AND (' . implode(' OR ', $parts) . ')';
            }
        }

        foreach ($globalSearchValues as $value) {
            $keywords = array_filter(array_map('trim', explode(',', (string) $value)));
            $parts = [];
            foreach ($keywords as $keyword) {
                foreach (['id', 'sort_no', 'role_key', 'role_name', 'description', 'created_by', 'updated_bY'] as $expr) {
                    $parts[] = "{$expr} LIKE ?";
                    $params[] = '%' . $keyword . '%';
                }
            }
            if ($parts !== []) {
                $sql .= ' AND (' . implode(' OR ', $parts) . ')';
            }
        }

        $statement = $this->db->prepare($sql . ' ORDER BY sort_no ASC');
        $statement->execute($params);
        return ActorHelper::enrichActorNames(
            $statement->fetchAll(PDO::FETCH_ASSOC) ?: [],
            ['created_by', 'updated_by']
        );
    }

    public function getById(string $id): ?array
    {
        $statement = $this->db->prepare("
            SELECT id, sort_no, role_key, role_name, description, is_active,
                   created_at, created_by, updated_at, updated_bY AS updated_by
              FROM auth_roles
             WHERE id = ?
             LIMIT 1
        ");
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ? ActorHelper::enrichActorNamesRow($row, ['created_by', 'updated_by']) : null;
    }

    public function existsKey(string $roleKey, ?string $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM auth_roles WHERE role_key = ?';
        $params = [$roleKey];
        if ($excludeId !== null && $excludeId !== '') {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return (int) $statement->fetchColumn() > 0;
    }

    public function create(array $data): bool
    {
        $statement = $this->db->prepare("
            INSERT INTO auth_roles
                (id, sort_no, role_key, role_name, description, is_active, created_at, created_by)
            VALUES
                (:id, :sort_no, :role_key, :role_name, :description, :is_active, NOW(), :created_by)
        ");
        return $statement->execute([
            ':id' => $data['id'],
            ':sort_no' => $data['sort_no'],
            ':role_key' => $data['role_key'],
            ':role_name' => $data['role_name'],
            ':description' => $data['description'],
            ':is_active' => $data['is_active'],
            ':created_by' => $data['created_by'],
        ]);
    }

    public function update(string $id, array $data): bool
    {
        $statement = $this->db->prepare("
            UPDATE auth_roles
               SET role_key = :role_key,
                   role_name = :role_name,
                   description = :description,
                   is_active = :is_active,
                   updated_at = NOW(),
                   updated_bY = :updated_by
             WHERE id = :id
        ");
        return $statement->execute([
            ':role_key' => $data['role_key'],
            ':role_name' => $data['role_name'],
            ':description' => $data['description'],
            ':is_active' => $data['is_active'],
            ':updated_by' => $data['updated_by'],
            ':id' => $id,
        ]);
    }

    public function delete(string $id): bool
    {
        $statement = $this->db->prepare('DELETE FROM auth_roles WHERE id = ?');
        $statement->execute([$id]);
        return $statement->rowCount() === 1;
    }

    public function findByIdOrKey(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $statement = $this->db->prepare('SELECT * FROM auth_roles WHERE id = ? OR role_key = ? LIMIT 1');
        $statement->execute([$value, $value]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findIdByKey(string $roleKey): ?string
    {
        $statement = $this->db->prepare('SELECT id FROM auth_roles WHERE role_key = ? LIMIT 1');
        $statement->execute([$roleKey]);
        $id = $statement->fetchColumn();
        return $id === false ? null : (string) $id;
    }

    public function updateSortNo(string $id, int $sortNo): bool
    {
        $statement = $this->db->prepare('UPDATE auth_roles SET sort_no = ?, updated_at = NOW() WHERE id = ?');
        return $statement->execute([$sortNo, $id]);
    }
}
