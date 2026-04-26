<?php
// 경로: PROJECT_ROOT . '/app/Models/Auth/AuthRolePermissionModel.php'
namespace App\Models\Auth;

use PDO;
use Core\Database;

class RolePermissionModel
{
    // PDO 보관
    private PDO $db;

    // 생성자 – 외부에서 PDO 주입 또는 자동 연결
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    /* ===============================================================
     * 1) 역할별 권한 전체 조회
     * =============================================================== */
    public function getPermissionsForRole(string $roleId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    arp.id AS mapping_id,
                    arp.sort_no AS mapping_sort_no,
                    arp.role_id AS mapping_role_id,
                    arp.permission_id AS mapping_permission_id,
                    arp.created_at,
                    arp.created_by,
                    ap.id AS permission_id,
                    ap.sort_no,
                    ap.permission_key,
                    ap.permission_name,
                    ap.description,
                    ap.category,
                    ap.is_active,
                    ap.created_at AS permission_created_at,
                    ap.created_by AS permission_created_by,
                    ap.updated_at AS permission_updated_at,
                    ap.updated_by AS permission_updated_by
                FROM auth_role_permissions arp
                JOIN auth_permissions ap ON ap.id = arp.permission_id
                WHERE arp.role_id = ?
                ORDER BY ap.permission_name ASC
            ");

            $stmt->execute([$roleId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        } catch (\Throwable $e) {            
            return [];
        }
    }

    /* ===============================================================
     * 2) 특정 권한을 가진 역할 조회
     * =============================================================== */
    public function getRolesForPermission(string $permissionId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    arp.id AS mapping_id,
                    arp.sort_no AS mapping_sort_no,
                    arp.role_id AS mapping_role_id,
                    arp.permission_id AS mapping_permission_id,
                    arp.created_at,
                    arp.created_by,
                    ar.id AS role_id,
                    ar.role_key,
                    ar.role_name
                FROM auth_role_permissions arp
                JOIN auth_roles ar ON ar.id = arp.role_id
                WHERE arp.permission_id = ?
                ORDER BY ar.role_name ASC
            ");

            $stmt->execute([$permissionId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        } catch (\Throwable $e) {            
            return [];
        }
    }

    /* ===============================================================
     * 3) 중복 여부 확인
     * =============================================================== */
    public function exists(string $roleId, string $permissionId): bool
    {
        $stmt = $this->db->prepare("
            SELECT id FROM auth_role_permissions
            WHERE role_id = ? AND permission_id = ?
            LIMIT 1
        ");
        $stmt->execute([$roleId, $permissionId]);

        return (bool)$stmt->fetchColumn();
    }

    /* ===============================================================
     * 4) 역할에 권한 부여 (Service에서 UUID/Code 생성 완료)
     * =============================================================== */
    public function insertMapping(array $data): bool
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO auth_role_permissions (
                    id, sort_no, role_id, permission_id,
                    created_at, created_by
                ) VALUES (
                    :id, :sort_no, :role_id, :permission_id,
                    NOW(), :created_by
                )
            ");

            return $stmt->execute([
                ':id'           => $data['id'],
                ':sort_no'      => $data['sort_no'],
                ':role_id'      => $data['role_id'],
                ':permission_id'=> $data['permission_id'],
                ':created_by'   => $data['created_by'],
            ]);

        } catch (\Throwable $e) {            
            return false;
        }
    }

    /* ===============================================================
     * 5) 특정 역할에서 권한 제거
     * =============================================================== */
    public function remove(string $roleId, string $permissionId): bool
    {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM auth_role_permissions
                WHERE role_id = ? AND permission_id = ?
            ");
            return $stmt->execute([$roleId, $permissionId]);

        } catch (\Throwable $e) {            
            return false;
        }
    }

    /* ===============================================================
     * 6) 매핑 단건 삭제
     * =============================================================== */
    public function delete(string $mappingId): bool
    {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM auth_role_permissions
                WHERE id = ?
            ");
            return $stmt->execute([$mappingId]);

        } catch (\Throwable $e) {            
            return false;
        }
    }

    /* ===============================================================
     * 7) 특정 역할의 모든 권한 삭제
     * =============================================================== */
    public function clearRole(string $roleId): bool
    {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM auth_role_permissions WHERE role_id = ?
            ");
            return $stmt->execute([$roleId]);

        } catch (\Throwable $e) {           
            return false;
        }
    }

    /* ===============================================================
     * 8) 특정 권한을 가진 매핑 삭제
     * =============================================================== */
    public function clearPermission(string $permissionId): bool
    {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM auth_role_permissions WHERE permission_id = ?
            ");
            return $stmt->execute([$permissionId]);

        } catch (\Throwable $e) {            
            return false;
        }
    }

    /* ===============================================================
     * 9) 역할이 특정 permission_key 를 가지고 있는지 확인
     * =============================================================== */
    public function roleHasPermission(string $roleId, string $permissionKey): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM auth_role_permissions rp
            JOIN auth_permissions p ON p.id = rp.permission_id
            WHERE rp.role_id = ? AND p.permission_key = ?
              AND COALESCE(p.is_active, 1) = 1
            LIMIT 1
        ");
        $stmt->execute([$roleId, $permissionKey]);

        return $stmt->fetchColumn() > 0;
    }
}
