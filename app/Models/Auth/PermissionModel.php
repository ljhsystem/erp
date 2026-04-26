<?php
// 경로: PROJECT_ROOT . '/app/Models/Auth/AuthPermissionModel.php'
namespace App\Models\Auth;

use PDO;
use Core\Database;

class PermissionModel
{
    // PDO 보관
    private PDO $db;

    // 생성자 – 외부에서 PDO 주입 또는 자동 연결
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    /* ===============================================================
     * 1) 권한 전체 조회
     * =============================================================== */
    public function getAll(array $filters = []): array
    {
        try {
            $sql = "
                SELECT 
                    id, sort_no, permission_key, permission_name,
                    description, category, is_active,
                    created_at, created_by, updated_at, updated_by
                FROM auth_permissions
                WHERE 1=1
            ";

            $params = [];

            if (!empty($filters)) {
                $fieldMap = [
                    'id'              => ['expr' => 'id', 'type' => 'exact'],
                    'sort_no'            => ['expr' => 'sort_no', 'type' => 'like'],
                    'permission_key'  => ['expr' => 'permission_key', 'type' => 'like'],
                    'permission_name' => ['expr' => 'permission_name', 'type' => 'like'],
                    'category'        => ['expr' => 'category', 'type' => 'like'],
                    'description'     => ['expr' => 'description', 'type' => 'like'],
                    'is_active'       => ['expr' => 'is_active', 'type' => 'exact'],
                    'created_at'      => ['expr' => 'created_at', 'type' => 'datetime'],
                    'created_by'      => ['expr' => 'created_by', 'type' => 'like'],
                    'updated_at'      => ['expr' => 'updated_at', 'type' => 'datetime'],
                    'updated_by'      => ['expr' => 'updated_by', 'type' => 'like'],
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
                            $start = trim((string)($value['start'] ?? ''));
                            $end   = trim((string)($value['end'] ?? ''));

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
                            $stringValue = trim((string)$value);

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

                    $keywords = array_filter(array_map('trim', explode(',', (string)$value)));

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
                    $keywords = array_filter(array_map('trim', explode(',', (string)$value)));

                    if (!$keywords) {
                        continue;
                    }

                    $orParts = [];
                    foreach ($keywords as $keyword) {
                        foreach (['permission_name', 'category', 'permission_key', 'sort_no', 'description', 'created_by', 'updated_by'] as $expr) {
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

    /* ===============================================================
     * 2) 단건 조회
     * =============================================================== */
    public function getById(string $id): ?array
    {
        if (!$id) return null;

        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id, sort_no, permission_key, permission_name,
                    description, category, is_active,
                    created_at, created_by, updated_at, updated_by
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

    /* ===============================================================
     * 3) permission_key 중복 체크
     * =============================================================== */
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

    /* ===============================================================
     * 4) 권한 생성 (Service에서 만들어준 id, sort_no 사용)
     * =============================================================== */
    public function create(array $data): bool
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO auth_permissions (
                    id, sort_no, permission_key, permission_name,
                    description, category, is_active,
                    created_at, created_by, updated_at, updated_by
                ) VALUES (
                    :id, :sort_no, :pkey, :pname,
                    :description, :category, :is_active,
                    NOW(), :created_by, NOW(), :updated_by
                )
            ");

            return $stmt->execute([
                ':id'          => $data['id'],
                ':sort_no'        => $data['sort_no'],
                ':pkey'        => $data['permission_key'],
                ':pname'       => $data['permission_name'],
                ':description' => $data['description'] ?? null,
                ':category'    => $data['category'] ?? null,
                ':is_active'   => $data['is_active'] ?? 1,
                ':created_by'  => $data['created_by'] ?? null,
                ':updated_by'  => $data['updated_by'] ?? null
            ]);

        } catch (\Throwable $e) {            
            return false;
        }
    }

    /* ===============================================================
     * 5) 권한 수정
     * =============================================================== */
    public function update(string $id, array $data): bool
    {
        if (!$id) return false;

        try {
            $fields = [];
            $params = [];

            foreach (['permission_key', 'permission_name', 'description', 'category', 'is_active', 'updated_by'] as $col) {
                if (array_key_exists($col, $data)) {
                    $fields[] = "$col = ?";
                    $params[] = $data[$col];
                }
            }

            $fields[] = "updated_at = NOW()";
            $params[] = $id;

            $stmt = $this->db->prepare(
                "UPDATE auth_permissions SET " . implode(", ", $fields) . " WHERE id = ?"
            );

            return $stmt->execute($params);

        } catch (\Throwable $e) {           
            return false;
        }
    }

    /* ===============================================================
     * 6) 삭제
     * =============================================================== */
    public function delete(string $id): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM auth_permissions WHERE id = ?");
            return $stmt->execute([$id]);

        } catch (\Throwable $e) {            
            return false;
        }
    }

    /* ===============================================================
     * 7) 활성/비활성 토글
     * =============================================================== */
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

    public function updateSortNo(string $id, int $sort_no): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE auth_permissions
                SET sort_no = ?, updated_at = NOW()
                WHERE id = ?
            ");

            return $stmt->execute([$sort_no, $id]);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
