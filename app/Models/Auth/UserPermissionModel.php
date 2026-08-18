<?php
namespace App\Models\Auth;

use PDO;

class UserPermissionModel
{
    public function __construct(private readonly PDO $db) {}

    public function replaceProfile(string $userId, string $mode, string $actor): void
    {
        $sql = 'INSERT INTO auth_user_permission_profiles (user_id, permission_mode, created_at, created_by, updated_at, updated_by)
                VALUES (?, ?, NOW(), ?, NOW(), ?)
                ON DUPLICATE KEY UPDATE permission_mode=VALUES(permission_mode), updated_at=NOW(), updated_by=VALUES(updated_by)';
        $this->db->prepare($sql)->execute([$userId, $mode, $actor, $actor]);
    }

    public function deleteMappings(string $userId, array $permissionIds): int
    {
        if ($permissionIds === []) return 0;
        $sql = 'DELETE FROM auth_user_permissions WHERE user_id=? AND permission_id IN (' . implode(',', array_fill(0, count($permissionIds), '?')) . ')';
        $statement = $this->db->prepare($sql);
        $statement->execute(array_merge([$userId], array_values($permissionIds)));
        return $statement->rowCount();
    }

    public function insertMapping(string $userId, string $permissionId, string $actor, string $id): void
    {
        $this->db->prepare('INSERT INTO auth_user_permissions (id,user_id,permission_id,created_at,created_by) VALUES (?,?,?,NOW(),?)')
            ->execute([$id, $userId, $permissionId, $actor]);
    }

    public function insertAudit(array $row): void
    {
        $this->db->prepare('INSERT INTO auth_user_permission_audits
          (id,batch_id,user_id,username_snapshot,employee_name_snapshot,permission_id,permission_key_snapshot,permission_name_snapshot,change_type,before_mode,after_mode,created_at,created_by)
          VALUES (:id,:batch_id,:user_id,:username_snapshot,:employee_name_snapshot,:permission_id,:permission_key_snapshot,:permission_name_snapshot,:change_type,:before_mode,:after_mode,NOW(),:created_by)')->execute($row);
    }
}
