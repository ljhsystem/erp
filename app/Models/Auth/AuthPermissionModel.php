<?php
// 경로: PROJECT_ROOT . '/app/models/auth/AuthPermissionModel.php'
namespace App\Models\Auth;

use PDO;

class AuthPermissionModel
{
    private PDO $pdo;    

    public function __construct(PDO $pdo)
    {
        $this->pdo    = $pdo;        
    }

    /* ===============================================================
     * 1) 권한 전체 조회
     * =============================================================== */
    public function getAll(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    id, code, permission_key, permission_name,
                    description, category, is_active,
                    created_at, created_by, updated_at, updated_by
                FROM auth_permissions
                ORDER BY code ASC
            ");

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
            $stmt = $this->pdo->prepare("
                SELECT 
                    id, code, permission_key, permission_name,
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

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchColumn() > 0;

        } catch (\Throwable $e) {            
            return true;
        }
    }

    /* ===============================================================
     * 4) 권한 생성 (Service에서 만들어준 id, code 사용)
     * =============================================================== */
    public function create(array $data): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO auth_permissions (
                    id, code, permission_key, permission_name,
                    description, category, is_active,
                    created_at, created_by
                ) VALUES (
                    :id, :code, :pkey, :pname,
                    :description, :category, :is_active,
                    NOW(), :created_by
                )
            ");

            return $stmt->execute([
                ':id'          => $data['id'],
                ':code'        => $data['code'],
                ':pkey'        => $data['permission_key'],
                ':pname'       => $data['permission_name'],
                ':description' => $data['description'] ?? null,
                ':category'    => $data['category'] ?? null,
                ':is_active'   => $data['is_active'] ?? 1,
                ':created_by'  => $data['created_by'] ?? null
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

            $stmt = $this->pdo->prepare(
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
            $stmt = $this->pdo->prepare("DELETE FROM auth_permissions WHERE id = ?");
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
            $stmt = $this->pdo->prepare("
                UPDATE auth_permissions
                SET is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");

            return $stmt->execute([$active, $id]);

        } catch (\Throwable $e) {            
            return false;
        }
    }
}
