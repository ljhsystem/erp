<?php
// 경로: PROJECT_ROOT . '/app/Models/Auth/AuthUserModel.php'
namespace App\Models\Auth;

use PDO;
use Core\Database;

class UserModel
{
    // PDO 보관
    private PDO $db;

    // 생성자 – 외부에서 PDO 주입 또는 자동 연결
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }
    // ---------------------------------------------------------------
    // PDO 반환
    // ---------------------------------------------------------------
    public function getPDO(): PDO
    {
        return $this->db;
    }

    // ---------------------------------------------------------------
    // 아이디 중복 확인
    // ---------------------------------------------------------------
    public function existsByUsername(string $username): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM auth_users WHERE username = ?
        ");
        $stmt->execute([$username]);

        return (int)$stmt->fetchColumn() > 0;
    }

    // ---------------------------------------------------------------
    // 사용자 생성 (Service 에서 UUID/CODE 생성 → Model 은 INSERT 만 수행)
    // ---------------------------------------------------------------
    public function createUser(array $data): bool
    {
        /**
         * ⚠ 주의
         * - $data['id'], $data['code'], $data['username'], $data['password']
         *   등은 SERVICE 에서 생성/검증 후 그대로 전달됨.
         */

        $sql = "
            INSERT INTO auth_users (
                id, sort_no, username, password, email, role_id,
                two_factor_enabled, email_notify, sms_notify,
                is_active, approved, created_by
            )
            VALUES (
                :id, :sort_no, :username, :password, :email, :role_id,
                :two_factor_enabled, :email_notify, :sms_notify,
                :is_active, :approved, :created_by
            )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id'                => $data['id'],
            ':sort_no'           => $data['sort_no'] ?? $data['code'] ?? null,
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

    // ---------------------------------------------------------------
    // 자기가 자기 생성자로 기록
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // 사용자 조회
    // ---------------------------------------------------------------
    public function getById(string $id): ?array
    {
        $sql = "
            SELECT 
                u.*,
                u.sort_no AS code,
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
                u.sort_no AS code,
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

    public function getByCode(string $code): ?array
    {
        $sql = "
            SELECT 
                u.*,
                u.sort_no AS code,
                r.role_key,
                r.role_name
            FROM auth_users u
            LEFT JOIN auth_roles r ON r.id = u.role_id
            WHERE u.sort_no = ?
            LIMIT 1
        ";

        return $this->fetchOne($sql, [$code]);
    }

    private function fetchOne(string $sql, array $params): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ---------------------------------------------------------------
    // 비밀번호 변경
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // 로그인 실패 기록
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // 계정 잠금
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // 최근 로그인 기록
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // 승인 처리
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // 활성/비활성
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // Soft Delete / Restore
    // ---------------------------------------------------------------
    public function softDelete(string $userId, ?string $deletedBy = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE auth_users
               SET deleted_at = NOW(),
                   deleted_by = ?
             WHERE id = ?
        ");

        return $stmt->execute([$deletedBy, $userId]);
    }

    public function restore(string $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE auth_users
               SET deleted_at = NULL,
                   deleted_by = NULL
             WHERE id = ?
        ");

        return $stmt->execute([$userId]);
    }

    // ---------------------------------------------------------------
    // 알림 설정
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // 2FA 설정
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // ★ 화이트리스트 기반 업데이트(auth_users만 수정 가능)
    // ---------------------------------------------------------------
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
            'deleted_at',
            'deleted_by',
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

    // ---------------------------------------------------------------
    // 새로운 비밀번호가 기존 비밀번호와 동일한지 확인
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // 완료된 승인 처리
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // username 조회
    // ---------------------------------------------------------------
    public function getUsername(string $userId): ?string
    {
        $stmt = $this->db->prepare("
            SELECT username
            FROM auth_users
            WHERE id = ?
            AND (deleted_at IS NULL OR deleted_at = '')
            LIMIT 1
        ");
        $stmt->execute([$userId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row['username'] ?? null;
    } 
    

    /* =========================================================
    * 사용자 삭제 (auth_users.id 기준)
    * ========================================================= */
    public function hardDeleteById(string $id): bool
    {
        $sql = "DELETE FROM auth_users WHERE id = :id";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id' => $id
        ]);
    }
    
}

