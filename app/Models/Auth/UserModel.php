<?php
namespace App\Models\Auth;

use PDO;
use Core\Database;

class UserModel
{
    // PDO 보관
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getPDO(): PDO
    {
        return $this->db;
    }

    public function existsByUsername(string $username): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM auth_users WHERE username = ?
        ");
        $stmt->execute([$username]);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function createUser(array $data): bool
    {
        $sql = "
            INSERT INTO auth_users (
                id, username, password, email, role_id,
                two_factor_enabled, email_notify, sms_notify,
                is_active, approved, created_by
            )
            VALUES (
                :id, :username, :password, :email, :role_id,
                :two_factor_enabled, :email_notify, :sms_notify,
                :is_active, :approved, :created_by
            )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id'                => $data['id'],
            ':username'          => $data['username'],
            ':password'          => $data['password'],
            ':email'             => $data['email'] ?? null,
            ':role_id'           => $data['role_id'] ?? null,
            ':two_factor_enabled'=> $data['two_factor_enabled'] ?? 0,
            ':email_notify'      => $data['email_notify'] ?? 1,
            ':sms_notify'        => $data['sms_notify'] ?? 0,
            ':is_active'         => $data['is_active'] ?? 1,
            ':approved'          => $data['approved'] ?? 0,
            ':created_by'        => $data['created_by'] ?? null,
        ]);
    }

    public function setCreatedBySelf(string $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE auth_users
               SET created_by = :created_by
             WHERE id = :id
        ");

        return $stmt->execute([
            ':created_by' => $userId,
            ':id'         => $userId
        ]);
    }

    public function getById(string $id): ?array
    {
        $sql = "
            SELECT
                u.*,
                e.employee_name,
                r.role_key,
                r.role_name
            FROM auth_users u
            LEFT JOIN user_employees e ON e.user_id = u.id
            LEFT JOIN auth_roles r ON r.id = u.role_id
            WHERE u.id = ?
            LIMIT 1
        ";

        return $this->fetchOne($sql, [$id]);
    }

    public function getByUsername(string $username): ?array
    {
        $sql = "
            SELECT
                u.*,
                e.employee_name,
                r.role_key,
                r.role_name
            FROM auth_users u
            LEFT JOIN user_employees e ON e.user_id = u.id
            LEFT JOIN auth_roles r ON r.id = u.role_id
            WHERE u.username = ?
            LIMIT 1
        ";

        return $this->fetchOne($sql, [$username]);
    }

    public function findByUsernameAndEmail(string $username, string $email): ?array
    {
        $sql = "
            SELECT id, username, email
              FROM auth_users
             WHERE username = ? AND email = ?
             LIMIT 1
        ";

        return $this->fetchOne($sql, [$username, $email]);
    }

    public function findUsernameByEmployeeNameAndEmail(string $employeeName, string $email): ?string
    {
        $sql = "
            SELECT a.username
            FROM auth_users AS a
            INNER JOIN user_employees AS p ON a.id = p.user_id
            WHERE p.employee_name = ? AND a.email = ?
            LIMIT 1
        ";
        $row = $this->fetchOne($sql, [$employeeName, $email]);

        return $row === null ? null : (string) $row['username'];
    }

    public function findApprovalRequestUser(string $userId): ?array
    {
        $sql = "
            SELECT u.id, u.username, u.approved, u.created_at,
                   p.employee_name, p.profile_image
              FROM auth_users u
         LEFT JOIN user_employees p ON p.user_id = u.id
             WHERE u.id = ?
             LIMIT 1
        ";

        return $this->fetchOne($sql, [$userId]);
    }

    private function fetchOne(string $sql, array $params): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function updatePassword(string $userId, string $hash, ?string $updatedBy = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE auth_users
               SET password = ?,
                   password_updated_at = NOW(),
                   password_updated_by = ?
             WHERE id = ?
        ");

        return $stmt->execute([$hash, $updatedBy, $userId]);
    }

    public function increaseFailCount(string $userId): void
    {
        $this->db->prepare("
            UPDATE auth_users
               SET login_fail_count = login_fail_count + 1
             WHERE id = ?
        ")->execute([$userId]);
    }

    public function getFailCount(string $userId): int
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(login_fail_count, 0)
              FROM auth_users
             WHERE id = ?
        ");
        $stmt->execute([$userId]);

        return (int)$stmt->fetchColumn();
    }

    public function lockAccount(string $userId, int $minutes): bool
    {
        $stmt = $this->db->prepare("
            UPDATE auth_users
               SET account_locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE)
             WHERE id = ?
        ");

        return $stmt->execute([$minutes, $userId]);
    }

    public function unlockAccount(string $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE auth_users
               SET account_locked_until = NULL,
                   login_fail_count = 0
             WHERE id = ?
        ");

        return $stmt->execute([$userId]);
    }

    public function updateLastLogin(string $userId, string $ip, ?string $device = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE auth_users
               SET last_login       = NOW(),
                   login_fail_count = 0,
                   last_login_ip    = ?,
                   last_login_device= ?
             WHERE id = ?
        ");

        return $stmt->execute([$ip, $device, $userId]);
    }

    public function approve(string $userId, string $approvedBy): bool
    {
        $stmt = $this->db->prepare("
            UPDATE auth_users
               SET approved    = 1,
                   approved_at = NOW(),
                   approved_by = ?
             WHERE id = ?
        ");

        return $stmt->execute([$approvedBy, $userId]);
    }

    public function setActive(string $userId, int $active, ?string $updatedBy = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE auth_users
               SET is_active = ?,
                   updated_at = NOW(),
                   updated_by = ?
             WHERE id = ?
        ");

        return $stmt->execute([$active, $updatedBy, $userId]);
    }

    public function softDelete(string $userId, ?string $deletedBy = null): bool
    {
        return $this->setActive($userId, 0, $deletedBy);
    }

    public function restore(string $userId): bool
    {
        return $this->setActive($userId, 1, null);
    }

    public function updateNotifySettings(string $userId, int $emailNotify, int $smsNotify): bool
    {
        $stmt = $this->db->prepare("
            UPDATE auth_users
               SET email_notify = ?,
                   sms_notify    = ?,
                   updated_at    = NOW()
             WHERE id = ?
        ");

        return $stmt->execute([$emailNotify, $smsNotify, $userId]);
    }

    public function update2FA(string $userId, int $enabled): bool
    {
        $stmt = $this->db->prepare("
            UPDATE auth_users
               SET two_factor_enabled = ?,
                   updated_at          = NOW()
             WHERE id = ?
        ");

        return $stmt->execute([$enabled, $userId]);
    }

    public function updateUserDirect(string $userId, array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $allowed = [
            'username',
            'email',
            'two_factor_enabled',
            'email_notify',
            'sms_notify',
            'is_active',
            'approved',
            'approved_at',
            'approved_by',
            'login_fail_count',
            'account_locked_until',
            'last_login',
            'last_login_ip',
            'last_login_device',
            'password',
            'password_updated_at',
            'password_updated_by',
            'created_by',
            'updated_by',
            'role_id',
        ];

        $set = [];
        $params = [];

        foreach ($data as $key => $val) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }
            $set[] = "`{$key}` = :{$key}";
            $params[":{$key}"] = $val;
        }

        if (empty($set)) {
            return false;
        }

        $params[':id'] = $userId;

        $sql = "
            UPDATE auth_users
               SET " . implode(', ', $set) . "
             WHERE id = :id
        ";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function isSamePassword(string $userId, string $newPassword): bool
    {
        $stmt = $this->db->prepare("
            SELECT password
            FROM auth_users
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row || empty($row['password'])) {
            return false;
        }

        return password_verify($newPassword, $row['password']);
    }

    public function approveUserFull(string $userId, string $approvedBy): bool
    {
        $stmt = $this->db->prepare("
            UPDATE auth_users
            SET approved    = 1,
                approved_by = :admin,
                approved_at = NOW(),
                updated_at  = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':admin' => $approvedBy,
            ':id'    => $userId
        ]);
    }

    public function getUsername(string $userId): ?string
    {
        $stmt = $this->db->prepare("
            SELECT username
            FROM auth_users
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row['username'] ?? null;
    }

    public function hardDeleteById(string $id): bool
    {
        $sql = "DELETE FROM auth_users WHERE id = :id";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id' => $id
        ]);
    }

    public function getActiveAdminUserIds(): array
    {
        $stmt = $this->db->query("
            SELECT u.id FROM auth_users u JOIN auth_roles r ON r.id = u.role_id
             WHERE u.approved = 1 AND u.is_active = 1
               AND r.role_key IN ('super_admin', 'admin')
             ORDER BY FIELD(r.role_key, 'super_admin', 'admin'), u.username ASC
        ");

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

}
