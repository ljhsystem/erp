<?php
namespace App\Models\Auth;

use Core\Database;
use PDO;

class PermissionModel
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getAll(array $filters = []): array
    {
        try {
            $sql = "
                SELECT
                    id,
                    sort_no,
                    permission_key,
                    permission_name,
                    description,
                    category,
                    page_key,
                    page,
                    permission_source,
                    is_active,
                    created_at,
                    created_by,
                    updated_at,
                    updated_by
                FROM auth_permissions
                WHERE 1=1
            ";

            $params = [];

            if (!empty($filters)) {
                $fieldMap = [
                    'id' => ['expr' => 'id', 'type' => 'exact'],
                    'sort_no' => ['expr' => 'sort_no', 'type' => 'like'],
                    'permission_key' => ['expr' => 'permission_key', 'type' => 'like'],
                    'permission_name' => ['expr' => 'permission_name', 'type' => 'like'],
                    'category' => ['expr' => 'category', 'type' => 'like'],
                    'description' => ['expr' => 'description', 'type' => 'like'],
                    'page_key' => ['expr' => 'page_key', 'type' => 'like'],
                    'page' => ['expr' => 'page', 'type' => 'like'],
                    'permission_source' => ['expr' => 'permission_source', 'type' => 'like'],
                    'is_active' => ['expr' => 'is_active', 'type' => 'exact'],
                    'created_at' => ['expr' => 'created_at', 'type' => 'datetime'],
                    'created_by' => ['expr' => 'created_by', 'type' => 'like'],
                    'updated_at' => ['expr' => 'updated_at', 'type' => 'datetime'],
                    'updated_by' => ['expr' => 'updated_by', 'type' => 'like'],
                ];

                $globalSearchValues = [];

                foreach ($filters as $filter) {
                    $field = $filter['field'] ?? '';
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

                    $expr = $fieldMap[$field]['expr'];
                    $type = $fieldMap[$field]['type'];

                    if ($type === 'datetime') {
                        if (is_array($value) && isset($value['start'], $value['end'])) {
                            $start = trim((string) ($value['start'] ?? ''));
                            $end = trim((string) ($value['end'] ?? ''));

                            if ($start !== '' && $end !== '') {
                                $sql .= " AND {$expr} BETWEEN ? AND ?";
                                $params[] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)
                                    ? $start . ' 00:00:00'
                                    : $start;
                                $params[] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)
                                    ? $end . ' 23:59:59'
                                    : $end;
                            }
                        } else {
                            $stringValue = trim((string) $value);

                            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $stringValue)) {
                                $sql .= " AND {$expr} BETWEEN ? AND ?";
                                $params[] = $stringValue . ' 00:00:00';
                                $params[] = $stringValue . ' 23:59:59';
                            } else {
                                $sql .= " AND {$expr} = ?";
                                $params[] = $stringValue;
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
                    if (!$keywords) {
                        continue;
                    }

                    $parts = [];
                    foreach ($keywords as $keyword) {
                        $parts[] = "{$expr} LIKE ?";
                        $params[] = '%' . $keyword . '%';
                    }

                    $sql .= " AND (" . implode(' OR ', $parts) . ")";
                }

                foreach ($globalSearchValues as $value) {
                    $keywords = array_filter(array_map('trim', explode(',', (string) $value)));
                    if (!$keywords) {
                        continue;
                    }

                    $orParts = [];
                    foreach ($keywords as $keyword) {
                        foreach ([
                            'permission_name',
                            'category',
                            'permission_key',
                            'page_key',
                            'page',
                            'permission_source',
                            'sort_no',
                            'description',
                            'created_by',
                            'updated_by',
                        ] as $expr) {
                            $orParts[] = "{$expr} LIKE ?";
                            $params[] = '%' . $keyword . '%';
                        }
                    }

                    if ($orParts) {
                        $sql .= " AND (" . implode(' OR ', $orParts) . ")";
                    }
                }
            }

            $sql .= " ORDER BY sort_no ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getById(string $id): ?array
    {
        if (!$id) {
            return null;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT
                    id,
                    sort_no,
                    permission_key,
                    permission_name,
                    description,
                    category,
                    page_key,
                    page,
                    permission_source,
                    is_active,
                    created_at,
                    created_by,
                    updated_at,
                    updated_by
                FROM auth_permissions
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getByKey(string $permissionKey): ?array
    {
        $statement = $this->db->prepare('SELECT id, permission_key, permission_name, is_active FROM auth_permissions WHERE permission_key = ? LIMIT 1');
        $statement->execute([$permissionKey]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function existsKey(string $key, ?string $excludeId = null): bool
    {
        try {
            $sql = "SELECT COUNT(*) FROM auth_permissions WHERE permission_key = ?";
            $params = [$key];

            if ($excludeId) {
                $sql .= " AND id <> ?";
                $params[] = $excludeId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return true;
        }
    }

    public function create(array $data): bool
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO auth_permissions (
                    id,
                    sort_no,
                    permission_key,
                    permission_name,
                    description,
                    category,
                    page_key,
                    page,
                    permission_source,
                    is_active,
                    created_at,
                    created_by,
                    updated_at,
                    updated_by
                ) VALUES (
                    :id,
                    :sort_no,
                    :permission_key,
                    :permission_name,
                    :description,
                    :category,
                    :page_key,
                    :page,
                    :permission_source,
                    :is_active,
                    NOW(),
                    :created_by,
                    NOW(),
                    :updated_by
                )
            ");

            return $stmt->execute([
                ':id' => $data['id'],
                ':sort_no' => $data['sort_no'],
                ':permission_key' => $data['permission_key'],
                ':permission_name' => $data['permission_name'],
                ':description' => $data['description'] ?? null,
                ':category' => $data['category'] ?? null,
                ':page_key' => $data['page_key'] ?? null,
                ':page' => $data['page'] ?? null,
                ':permission_source' => $data['permission_source'] ?? null,
                ':is_active' => $data['is_active'] ?? 1,
                ':created_by' => $data['created_by'] ?? null,
                ':updated_by' => $data['updated_by'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function update(string $id, array $data): bool
    {
        if (!$id) {
            return false;
        }

        try {
            $fields = [];
            $params = [];

            foreach ([
                'permission_key',
                'permission_name',
                'description',
                'category',
                'page_key',
                'page',
                'permission_source',
                'is_active',
                'updated_by',
            ] as $column) {
                if (array_key_exists($column, $data)) {
                    $fields[] = "{$column} = ?";
                    $params[] = $data[$column];
                }
            }

            $fields[] = "updated_at = NOW()";
            $params[] = $id;

            $stmt = $this->db->prepare(
                "UPDATE auth_permissions SET " . implode(', ', $fields) . " WHERE id = ?"
            );

            return $stmt->execute($params);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function delete(string $id): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM auth_permissions WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function toggleActive(string $id, int $active): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE auth_permissions
                SET is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");

            return $stmt->execute([$active, $id]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function updateSortNo(string $id, int $sortNo): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE auth_permissions
                SET sort_no = ?, updated_at = NOW()
                WHERE id = ?
            ");

            return $stmt->execute([$sortNo, $id]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function supportsPageKey(): bool
    {
        $stmt = $this->db->query("SHOW COLUMNS FROM auth_permissions LIKE 'page_key'");
        return (bool) ($stmt->fetch(PDO::FETCH_ASSOC) ?: false);
    }

    public function getRegistrySyncRows(bool $withPageKey): array
    {
        $columns = 'id, permission_key, permission_name, description, category, is_active, created_by, updated_by';
        if ($withPageKey) {
            $columns .= ', page_key';
        }
        $stmt = $this->db->query("SELECT {$columns} FROM auth_permissions");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function insertRegistryPermission(array $data, bool $withPageKey): bool
    {
        $columns = ['id', 'sort_no', 'permission_key', 'permission_name', 'description', 'category'];
        if ($withPageKey) {
            $columns[] = 'page_key';
        }
        array_push($columns, 'is_active', 'created_by', 'updated_by');
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $stmt = $this->db->prepare('INSERT INTO auth_permissions (' . implode(', ', $columns) . ") VALUES ({$placeholders})");
        return $stmt->execute(array_map(static fn(string $column): mixed => $data[$column] ?? null, $columns));
    }

    public function updateRegistryPermission(string $id, array $changes): bool
    {
        if ($changes === []) {
            return false;
        }
        $fields = [];
        $params = [];
        foreach ($changes as $column => $value) {
            if ($column === 'updated_at') {
                $fields[] = 'updated_at = NOW()';
                continue;
            }
            $fields[] = "{$column} = ?";
            $params[] = $value;
        }
        $params[] = $id;
        $stmt = $this->db->prepare('UPDATE auth_permissions SET ' . implode(', ', $fields) . ' WHERE id = ?');
        return $stmt->execute($params);
    }

    public function deleteRegistryPermissions(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $affected = 0;
        foreach (array_chunk(array_values(array_unique($ids)), 200) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $this->db->prepare("DELETE FROM auth_permissions WHERE id IN ({$placeholders})");
            $stmt->execute($chunk);
            $affected += $stmt->rowCount();
        }

        return $affected;
    }

    public function getRegistrySortRows(): array
    {
        $stmt = $this->db->query('SELECT id, permission_key, sort_no FROM auth_permissions ORDER BY sort_no ASC, permission_key ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function offsetSortNumbers(array $ids, int $offset, string $actor): void
    {
        if ($ids === []) return;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("UPDATE auth_permissions SET sort_no = sort_no + {$offset}, updated_at = NOW(), updated_by = ? WHERE id IN ({$placeholders})");
        $stmt->execute(array_merge([$actor], $ids));
    }

    public function applySortNumbers(array $changes, string $actor): void
    {
        foreach (array_chunk($changes, 200) as $chunk) {
            $ids = array_column($chunk, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $caseParts = [];
            $params = [];
            foreach ($chunk as $row) {
                $caseParts[] = 'WHEN ? THEN ?';
                $params[] = $row['id'];
                $params[] = $row['sort_no'];
            }
            $stmt = $this->db->prepare('UPDATE auth_permissions SET sort_no = CASE id ' . implode(' ', $caseParts) . " END, updated_at = NOW(), updated_by = ? WHERE id IN ({$placeholders})");
            $stmt->execute(array_merge($params, [$actor], $ids));
        }
    }
}
