<?php

namespace App\Models\Auth;

use PDO;
use Core\Database;

class RolePermissionModel
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getPermissionsForRole(string $roleId): array
    {
        $stmt = $this->db->prepare("
                SELECT
                    arp.id AS mapping_id,
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
                    ap.page_key,
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
    }

    public function getPermissionSelectionForRole(string $roleId): array
    {
        $stmt = $this->db->prepare("
            SELECT id AS mapping_id, role_id, permission_id, created_at, created_by
            FROM auth_role_permissions
            WHERE role_id = ?
        ");
        $stmt->execute([$roleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getRolesForPermission(string $permissionId): array
    {
        $stmt = $this->db->prepare("
                SELECT
                    arp.id AS mapping_id,
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
    }

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

    public function roleExists(string $roleId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM auth_roles WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$roleId]);
        return (bool) $stmt->fetchColumn();
    }

    public function getActiveRole(string $roleId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, role_key, role_name, is_active FROM auth_roles WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$roleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function permissionIdsByKeys(array $permissionKeys): array
    {
        if ($permissionKeys === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($permissionKeys), '?'));
        $stmt = $this->db->prepare("SELECT id, permission_key FROM auth_permissions WHERE permission_key IN ({$placeholders}) AND is_active = 1");
        $stmt->execute(array_values($permissionKeys));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['permission_key']] = (string) $row['id'];
        }
        return $result;
    }

    public function lockAdministratorPermissionScope(string $changedRoleId): void
    {
        $roleStatement = $this->db->prepare("SELECT id FROM auth_roles WHERE id = ? OR role_key IN ('super_admin', 'admin') FOR UPDATE");
        $roleStatement->execute([$changedRoleId]);
        $roleStatement->fetchAll(PDO::FETCH_COLUMN);

        $userStatement = $this->db->query("SELECT u.id FROM auth_users u JOIN auth_roles r ON r.id = u.role_id WHERE r.role_key IN ('super_admin', 'admin') FOR UPDATE");
        $userStatement->fetchAll(PDO::FETCH_COLUMN);
    }

    public function activePermissionIds(array $permissionIds): array
    {
        if ($permissionIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($permissionIds), '?'));
        $stmt = $this->db->prepare("SELECT id FROM auth_permissions WHERE id IN ({$placeholders}) AND is_active = 1");
        $stmt->execute(array_values($permissionIds));
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function assignedPermissionIds(string $roleId): array
    {
        $stmt = $this->db->prepare('SELECT permission_id FROM auth_role_permissions WHERE role_id = ?');
        $stmt->execute([$roleId]);
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function insertMappings(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }
        $values = [];
        $params = [];
        foreach (array_values($rows) as $index => $row) {
            $values[] = "(:id_{$index}, :role_id_{$index}, :permission_id_{$index}, NOW(), :created_by_{$index})";
            $params[":id_{$index}"] = $row['id'];
            $params[":role_id_{$index}"] = $row['role_id'];
            $params[":permission_id_{$index}"] = $row['permission_id'];
            $params[":created_by_{$index}"] = $row['created_by'];
        }
        $stmt = $this->db->prepare('INSERT INTO auth_role_permissions (id, role_id, permission_id, created_at, created_by) VALUES ' . implode(',', $values));
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function grantPermissionToRoleKey(string $permissionId, string $roleKey, string $actor): void
    {
        $statement = $this->db->prepare("INSERT INTO auth_role_permissions (id, role_id, permission_id, created_at, created_by)
            SELECT UUID(), r.id, ?, NOW(), ? FROM auth_roles r
            LEFT JOIN auth_role_permissions rp ON rp.role_id = r.id AND rp.permission_id = ?
            WHERE r.role_key = ? AND rp.id IS NULL");
        $statement->execute([$permissionId, $actor, $permissionId, $roleKey]);
    }

    public function removePermissions(string $roleId, array $permissionIds): int
    {
        if ($permissionIds === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($permissionIds), '?'));
        $stmt = $this->db->prepare("DELETE FROM auth_role_permissions WHERE role_id = ? AND permission_id IN ({$placeholders})");
        $stmt->execute([$roleId, ...array_values($permissionIds)]);
        return $stmt->rowCount();
    }

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

    public function deleteByRoleId(string $roleId): int
    {
        $stmt = $this->db->prepare('DELETE FROM auth_role_permissions WHERE role_id = ?');
        $stmt->execute([$roleId]);
        return $stmt->rowCount();
    }

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

    public function clearPermissions(array $permissionIds): int
    {
        if ($permissionIds === []) {
            return 0;
        }

        $affected = 0;
        foreach (array_chunk(array_values(array_unique($permissionIds)), 200) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $this->db->prepare("DELETE FROM auth_role_permissions WHERE permission_id IN ({$placeholders})");
            $stmt->execute($chunk);
            $affected += $stmt->rowCount();
        }

        return $affected;
    }

    public function countByPermission(string $permissionId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM auth_role_permissions WHERE permission_id = ?");
        $stmt->execute([$permissionId]);

        return (int) $stmt->fetchColumn();
    }

    public function roleHasPermission(string $roleId, string $permissionKey): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM auth_role_permissions rp
            JOIN auth_roles r ON r.id = rp.role_id
            JOIN auth_permissions p ON p.id = rp.permission_id
            WHERE rp.role_id = ? AND p.permission_key = ?
              AND r.is_active = 1
              AND COALESCE(p.is_active, 1) = 1
            LIMIT 1
        ");
        $stmt->execute([$roleId, $permissionKey]);

        return $stmt->fetchColumn() > 0;
    }

    public function isRoleActive(string $roleId): bool
    {
        $statement = $this->db->prepare('SELECT 1 FROM auth_roles WHERE id = ? AND is_active = 1 LIMIT 1');
        $statement->execute([$roleId]);
        return (bool) $statement->fetchColumn();
    }
}
