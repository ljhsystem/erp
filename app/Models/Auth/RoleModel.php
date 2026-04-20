<?php
// 경로: PROJECT_ROOT . '/app/Models/Auth/AuthRoleModel.php'
namespace App\Models\Auth;

use PDO;
use Core\Database;

class RoleModel
{
    // PDO 보관
    private PDO $db;

    // 생성자 – 외부에서 PDO 주입 또는 자동 연결
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    /* ===============================================================
     * 1) 역할 전체 조회
     * =============================================================== */
    public function getAll(array $filters = []): array
    {
        try {
            $sql = "
                SELECT 
                    id, code, role_key, role_name,
                    description, is_active,
                    created_at, created_by,
                    updated_at, updated_by
                FROM auth_roles
                WHERE 1=1
            ";

            $params = [];

            if (!empty($filters)) {
                $fieldMap = [
                    'id'          => ['expr' => 'id', 'type' => 'exact'],
                    'code'        => ['expr' => 'code', 'type' => 'like'],
                    'role_key'    => ['expr' => 'role_key', 'type' => 'like'],
                    'role_name'   => ['expr' => 'role_name', 'type' => 'like'],
                    'description' => ['expr' => 'description', 'type' => 'like'],
                    'is_active'   => ['expr' => 'is_active', 'type' => 'exact'],
                    'created_at'  => ['expr' => 'created_at', 'type' => 'datetime'],
                    'updated_at'  => ['expr' => 'updated_at', 'type' => 'datetime'],
                ];

                $globalSearchValues = [];

                foreach ($filters as $f) {
                    $field = $f['field'] ?? '';
                    $value = $f['value'] ?? '';

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
                        foreach (['code', 'role_key', 'role_name', 'description'] as $expr) {
                            $orParts[] = "{$expr} LIKE ?";
                            $params[] = '%' . $keyword . '%';
                        }
                    }

                    if ($orParts) {
                        $sql .= " AND (" . implode(' OR ', $orParts) . ")";
                    }
                }
            }

            $sql .= " ORDER BY code ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        } catch (\Throwable $e) {            
            return [];
        }
    }

    /* ===============================================================
     * 2) 역할 단일 조회
     * =============================================================== */
    public function getById(string $id): ?array
    {
        if (!$id) return null;

        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id, code, role_key, role_name,
                    description, is_active,
                    created_at, created_by,
                    updated_at, updated_by
                FROM auth_roles
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        } catch (\Throwable $e) {            
            return null;
        }
    }

    /* ===============================================================
     * 3) role_key 중복 체크
     * =============================================================== */
    public function existsKey(string $roleKey, ?string $excludeId = null): bool
    {
        try {
            $sql = "SELECT COUNT(*) FROM auth_roles WHERE role_key = ?";
            $params = [$roleKey];

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
     * 4) 역할 생성 (UUID/Code는 Service에서 생성됨)
     * =============================================================== */
    public function create(array $data): array
    {
        try {
            if (empty($data['role_key']) || empty($data['role_name'])) {
                return ['success' => false, 'message' => 'role_key 또는 role_name 누락'];
            }

            if ($this->existsKey($data['role_key'])) {
                return ['success' => false, 'message' => 'duplicate'];
            }

            $stmt = $this->db->prepare("
                INSERT INTO auth_roles (
                    id, code, role_key, role_name, description,
                    is_active, created_at, created_by
                ) VALUES (
                    :id, :code, :role_key, :role_name, :description,
                    :is_active, NOW(), :created_by
                )
            ");

            $ok = $stmt->execute([
                ':id'          => $data['id'],     // ⭐ Service 생성값
                ':code'        => $data['code'],   // ⭐ Service 생성값
                ':role_key'    => $data['role_key'],
                ':role_name'   => $data['role_name'],
                ':description' => $data['description'] ?? null,
                ':is_active'   => $data['is_active'] ?? 1,
                ':created_by'  => $data['created_by'] ?? null
            ]);

            return ['success' => $ok];

        } catch (\Throwable $e) {            
            return ['success' => false, 'message' => 'error'];
        }
    }

    /* ===============================================================
     * 5) 역할 수정
     * =============================================================== */
    public function update(string $id, array $data): array
    {
        if (!$id) return ['success' => false, 'message' => 'no_id'];

        try {
            if (!empty($data['role_key'])) {
                if ($this->existsKey($data['role_key'], $id)) {
                    return ['success' => false, 'message' => 'duplicate'];
                }
            }

            $fields = [];
            $params = [];

            foreach (['role_key', 'role_name', 'description', 'is_active', 'updated_by'] as $col) {
                if (array_key_exists($col, $data)) {
                    $fields[] = "$col = ?";
                    $params[] = $data[$col];
                }
            }

            $fields[] = "updated_at = NOW()";
            $params[] = $id;

            $sql = "UPDATE auth_roles SET " . implode(", ", $fields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);

            return ['success' => $stmt->execute($params)];

        } catch (\Throwable $e) {           
            return ['success' => false];
        }
    }

    /* ===============================================================
     * 6) 역할 삭제
     * =============================================================== */
    public function delete(string $id): bool
    {
        if (!$id) return false;

        try {
            $stmt = $this->db->prepare("DELETE FROM auth_roles WHERE id = ?");
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
                UPDATE auth_roles
                SET is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            return $stmt->execute([$active, $id]);

        } catch (\Throwable $e) {           
            return false;
        }
    }

    /* ===============================================================
     * 8) 역할 조회(id 또는 role_key)
     * =============================================================== */
    public function findByIdOrKey(?string $value): ?array
    {
        try {
            if (!$value) return null;

            // ID 조회
            $stmt = $this->db->prepare("SELECT * FROM auth_roles WHERE id = ?");
            $stmt->execute([$value]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) return $row;

            // role_key 조회
            $stmt = $this->db->prepare("SELECT * FROM auth_roles WHERE role_key = ?");
            $stmt->execute([$value]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        } catch (\Throwable $e) {            
            return null;
        }
    }

    public function updateCode(string $id, int $code): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE auth_roles
                SET code = ?, updated_at = NOW()
                WHERE id = ?
            ");

            return $stmt->execute([$code, $id]);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
