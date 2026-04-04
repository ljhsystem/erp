<?php
// 경로: PROJECT_ROOT . '/app/Models/User/EmployeeModel.php'
namespace App\Models\User;

use PDO;

class EmployeeModel
{
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    /* ============================================================
    * 직원 전체 목록 조회 (JOIN 포함)
    * ============================================================ */
    public function getList(array $filters = []): array
    {
        $sql = "
            SELECT
                p.id,
                p.code,
                p.user_id,
                p.employee_name,
                p.profile_image,
                p.phone,
                p.address,
                p.address_detail,
                p.department_id,
                d.dept_name,
                p.position_id,
                s.position_name,
                p.doc_hire_date,
                p.real_hire_date,
                p.doc_retire_date,
                p.real_retire_date,
                p.rrn,
                p.rrn_image,
                p.emergency_phone,
                p.certificate_name,
                p.certificate_file,
                p.bank_name,
                p.account_number,
                p.account_holder,
                p.bank_file,
                p.note,
                p.memo,

                p.created_at AS profile_created_at,
                p.created_by AS profile_created_by,
                p_created_user.employee_name AS profile_created_by_name,

                p.updated_at AS profile_updated_at,
                p.updated_by AS profile_updated_by,
                p_updated_user.employee_name AS profile_updated_by_name,

                u.username,
                u.email,
                u.role_id,
                u.is_active,
                u.approved,
                u.approved_at,
                u.approved_by,
                u.two_factor_enabled,
                u.email_notify,
                u.sms_notify,
                u.last_login,
                u.last_login_ip,
                u.last_login_device,
                u.login_fail_count,
                u.account_locked_until,

                u.password_updated_at,
                u.password_updated_by,
                pw_updated_user.employee_name AS password_updated_by_name,

                u.created_at AS user_created_at,
                u.created_by AS user_created_by,
                u_created_user.employee_name AS user_created_by_name,

                u.updated_at AS user_updated_at,
                u.updated_by AS user_updated_by,
                u_updated_user.employee_name AS user_updated_by_name,

                u.deleted_at,
                u.deleted_by,
                deleted_user.employee_name AS deleted_by_name,

                r.role_name,
                r.role_key

            FROM user_employees p
            LEFT JOIN auth_users u ON p.user_id = u.id
            LEFT JOIN auth_roles r ON u.role_id = r.id
            LEFT JOIN user_departments d ON p.department_id = d.id
            LEFT JOIN user_positions s ON p.position_id = s.id

            LEFT JOIN user_employees p_created_user
                ON p_created_user.user_id = p.created_by
            LEFT JOIN user_employees p_updated_user
                ON p_updated_user.user_id = p.updated_by
            LEFT JOIN user_employees u_created_user
                ON u_created_user.user_id = u.created_by
            LEFT JOIN user_employees u_updated_user
                ON u_updated_user.user_id = u.updated_by
            LEFT JOIN user_employees pw_updated_user
                ON pw_updated_user.user_id = u.password_updated_by
            LEFT JOIN user_employees deleted_user
                ON deleted_user.user_id = u.deleted_by

            WHERE 1=1
        ";
    
        $params = [];
    
        $fieldMap = [
            'code'                 => 'p.code',
            'username'             => 'u.username',
            'employee_name'        => 'p.employee_name',
            'role_name'            => 'r.role_name',
            'department_name'      => 'd.dept_name',
            'dept_name'            => 'd.dept_name',
            'position_name'        => 's.position_name',
            'email'                => 'u.email',
            'phone'                => 'p.phone',
            'emergency_phone'      => 'p.emergency_phone',
            'address'              => 'p.address',
            'address_detail'       => 'p.address_detail',
            'certificate_name'     => 'p.certificate_name',

            'note'                 => 'p.note',
            'memo'                 => 'p.memo',
            'approved_by'          => 'u.approved_by',
            'last_login_ip'        => 'u.last_login_ip',
            'last_login_device'    => 'u.last_login_device',
        ];
    
        $dateFieldMap = [
            'approved_at'          => 'u.approved_at',
            'last_login'           => 'u.last_login',
            'password_updated_at'  => 'u.password_updated_at',
            'registration_date' => 'u.created_at',
            'user_created_at'      => 'u.created_at',
            'user_updated_at'      => 'u.updated_at',
            'deleted_at'           => 'u.deleted_at',
            'doc_hire_date'        => 'p.doc_hire_date',
            'real_hire_date'       => 'p.real_hire_date',
            'doc_retire_date'      => 'p.doc_retire_date',
            'real_retire_date'     => 'p.real_retire_date',
            'profile_created_at'   => 'p.created_at',
            'profile_updated_at'   => 'p.updated_at',
        ];
    
        foreach ($filters as $i => $filter) {
            $field = $filter['field'] ?? '';
            $value = $filter['value'] ?? '';
    
            if ($field === '' || $value === '' || $value === null) {
                continue;
            }
    
            if (isset($fieldMap[$field])) {
                $paramKey = ":kw{$i}";
                $sql .= " AND {$fieldMap[$field]} LIKE {$paramKey}";
                $params[$paramKey] = '%' . $value . '%';
                continue;
            }
    
            if ($field === 'approved') {
                $paramKey = ":approved{$i}";
                $sql .= " AND u.approved = {$paramKey}";
                $params[$paramKey] = (string)((int)$value);
                continue;
            }
    
            if ($field === 'two_factor_enabled') {
                $paramKey = ":twofa{$i}";
                $sql .= " AND u.two_factor_enabled = {$paramKey}";
                $params[$paramKey] = (string)((int)$value);
                continue;
            }
    
            if ($field === 'email_notify') {
                $paramKey = ":emailnotify{$i}";
                $sql .= " AND u.email_notify = {$paramKey}";
                $params[$paramKey] = (string)((int)$value);
                continue;
            }
    
            if ($field === 'sms_notify') {
                $paramKey = ":smsnotify{$i}";
                $sql .= " AND u.sms_notify = {$paramKey}";
                $params[$paramKey] = (string)((int)$value);
                continue;
            }
    
            if ($field === 'is_active') {
                $paramKey = ":active{$i}";
                $sql .= " AND u.is_active = {$paramKey}";
                $params[$paramKey] = (string)((int)$value);
                continue;
            }
    
            if (isset($dateFieldMap[$field]) && is_array($value)) {
                $start = trim((string)($value['start'] ?? ''));
                $end   = trim((string)($value['end'] ?? ''));
    
                if ($start !== '' && $end !== '') {
                    $startKey = ":start{$i}";
                    $endKey   = ":end{$i}";
    
                    $sql .= " AND DATE({$dateFieldMap[$field]}) BETWEEN {$startKey} AND {$endKey}";
                    $params[$startKey] = $start;
                    $params[$endKey]   = $end;
                } elseif ($start !== '') {
                    $startKey = ":start{$i}";
                    $sql .= " AND DATE({$dateFieldMap[$field]}) >= {$startKey}";
                    $params[$startKey] = $start;
                } elseif ($end !== '') {
                    $endKey = ":end{$i}";
                    $sql .= " AND DATE({$dateFieldMap[$field]}) <= {$endKey}";
                    $params[$endKey] = $end;
                }
    
                continue;
            }
        }
    
        $sql .= " ORDER BY p.code ASC ";
    
        $stmt = $this->db->prepare($sql);
    
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, PDO::PARAM_STR);
        }
    
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /* ============================================================
     * 단일 프로필 조회 (user_id)
     * ============================================================ */
    public function getByUserId(string $userId): ?array
    {
        $sql = "
            SELECT *
            FROM user_employees
            WHERE user_id = ?
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }


    /* ============================================================
    * 사용자 + 프로필 JOIN (role → role_id로 수정)
    * ============================================================ */
    public function getUserWithProfile(string $userId): ?array
    {
        $sql = "
            SELECT 
                u.id AS user_id,
                u.username,
                u.email,
                u.role_id,
                u.two_factor_enabled,
                u.email_notify,
                u.sms_notify,

                /* ===== 로그인 관련 ===== */
                u.last_login,
                u.last_login_ip,
                u.last_login_device,

                /* ===== 비밀번호 변경 기록 ===== */
                u.password_updated_at,
                u.password_updated_by,

                /* ===== 생성/수정/삭제 기록 ===== */
                u.created_at AS user_created_at,
                u.created_by AS user_created_by,
                u.updated_at AS user_updated_at,
                u.updated_by AS user_updated_by,
                u.deleted_at,
                u.deleted_by,

                /* ===== 프로필 ===== */
                p.*
            FROM auth_users u
            LEFT JOIN user_employees p 
                ON p.user_id = u.id                
            WHERE u.id = ?
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }


    public function search(string $q = '', array $filters = [], int $limit = 50): array
    {
        $sql = "
            SELECT 
                p.id,
                p.code,
                p.employee_name,
                u.username,
                u.email,
                d.dept_name,
                s.position_name,
                r.role_name,
                u.is_active,
                u.last_login
            FROM user_employees p
            LEFT JOIN auth_users u ON p.user_id = u.id
            LEFT JOIN auth_roles r ON u.role_id = r.id
            LEFT JOIN user_departments d ON p.department_id = d.id
            LEFT JOIN user_positions s ON p.position_id = s.id
            WHERE 1=1
        ";
    
        $params = [];
    
        /* ============================================================
           🔥 1. q 검색 (통합 검색)
        ============================================================ */
        if ($q !== '') {
            $sql .= "
                AND (
                    p.employee_name LIKE :q
                    OR u.username LIKE :q
                    OR d.dept_name LIKE :q
                    OR s.position_name LIKE :q
                    OR r.role_name LIKE :q
                    OR u.email LIKE :q
                )
            ";
            $params[':q'] = '%' . $q . '%';
        }
    
        /* ============================================================
           🔥 2. filters 검색 (기존 구조 유지)
        ============================================================ */
        $fieldMap = [
            'code'            => 'p.code',
            'employee_name'   => 'p.employee_name',
            'username'        => 'u.username',
            'email'           => 'u.email',
            'role_name'       => 'r.role_name',
            'department_name' => 'd.dept_name',
            'position_name'   => 's.position_name',
            'phone'           => 'p.phone',
        ];
    
        foreach ($filters as $i => $filter) {
    
            $field = $filter['field'] ?? '';
            $value = $filter['value'] ?? '';
    
            if ($field === '' || $value === '' || $value === null) {
                continue;
            }
    
            if (isset($fieldMap[$field])) {
                $paramKey = ":f{$i}";
                $sql .= " AND {$fieldMap[$field]} LIKE {$paramKey}";
                $params[$paramKey] = '%' . $value . '%';
            }
    
            // boolean 필터
            if ($field === 'is_active') {
                $paramKey = ":active{$i}";
                $sql .= " AND u.is_active = {$paramKey}";
                $params[$paramKey] = (int)$value;
            }
        }
    
        /* ============================================================
           🔥 3. 정렬 + 제한
        ============================================================ */
        $sql .= " ORDER BY p.code ASC LIMIT :limit";
    
        $stmt = $this->db->prepare($sql);
    
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
    
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
    
        $stmt->execute();
    
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }


   /* ============================================================
    * 직원 검색 (Select2용)
    * ============================================================ */
    public function searchPicker(string $q = '', int $limit = 20): array
    {
        $limit = max(1, min(100, (int)$limit));

        $q = trim($q);
        $keyword = '%' . $q . '%';
        $prefixKeyword = $q . '%';

        $sql = "
            SELECT
                p.id AS id,
                p.user_id,
                p.employee_name AS text,
                p.code,
                p.employee_name,
                p.department_id,
                d.dept_name AS department_name,
                p.position_id,
                s.position_name AS position_name,
                u.username,
                u.email,
                u.is_active
            FROM user_employees p
            LEFT JOIN auth_users u
                ON p.user_id = u.id
            LEFT JOIN user_departments d
                ON p.department_id = d.id
            LEFT JOIN user_positions s
                ON p.position_id = s.id
            WHERE (
                p.employee_name LIKE :keyword1
                OR CAST(p.code AS CHAR) LIKE :keyword2
                OR COALESCE(u.username, '') LIKE :keyword3
                OR COALESCE(u.email, '') LIKE :keyword4
            )
            ORDER BY
                CASE
                    WHEN p.employee_name LIKE :prefixKeyword THEN 0
                    ELSE 1
                END,
                p.employee_name ASC
            LIMIT {$limit}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':keyword1', $keyword, PDO::PARAM_STR);
        $stmt->bindValue(':keyword2', $keyword, PDO::PARAM_STR);
        $stmt->bindValue(':keyword3', $keyword, PDO::PARAM_STR);
        $stmt->bindValue(':keyword4', $keyword, PDO::PARAM_STR);
        $stmt->bindValue(':prefixKeyword', $prefixKeyword, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }








    /* ============================================================
     * 직원 프로필 생성
     * ============================================================ */
    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO user_employees (
                id, code, user_id, employee_name,
                phone, address, address_detail,
                department_id, position_id,
                doc_hire_date, real_hire_date,
                doc_retire_date, real_retire_date,
                rrn, rrn_image,
                emergency_phone, 
                profile_image, certificate_name, certificate_file,
                bank_name,
                account_number,
                account_holder,
                bank_file,
                note, memo,
                created_at, created_by
            )
            VALUES (
                :id, :code, :user_id, :employee_name,
                :phone, :address, :address_detail,
                :department_id, :position_id,
                :doc_hire_date, :real_hire_date,
                :doc_retire_date, :real_retire_date,
                :rrn, :rrn_image,
                :emergency_phone, 
                :profile_image, :certificate_name, :certificate_file,
                :bank_name,
                :account_number,
                :account_holder,
                :bank_file,
                :note, :memo,
                NOW(), :created_by
        )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id'               => $data['id'],
            ':code'             => $data['code'],
            ':user_id'          => $data['user_id'],
            ':employee_name'    => $data['employee_name'],
            ':phone'            => $data['phone'] ?? null,
            ':address'          => $data['address'] ?? null,
            ':address_detail'   => $data['address_detail'] ?? null,
            ':department_id'    => $data['department_id'] ?? null,
            ':position_id'      => $data['position_id'] ?? null,
            ':doc_hire_date'    => $data['doc_hire_date'] ?? null,
            ':real_hire_date'   => $data['real_hire_date'] ?? null,
            ':doc_retire_date'  => $data['doc_retire_date'] ?? null,
            ':real_retire_date' => $data['real_retire_date'] ?? null,
            ':rrn'              => $data['rrn'] ?? null,
            ':rrn_image'        => $data['rrn_image'] ?? null,
            ':emergency_phone'  => $data['emergency_phone'] ?? null,   
            ':profile_image'    => $data['profile_image'] ?? null,
            ':certificate_name' => $data['certificate_name'] ?? null,
            ':certificate_file' => $data['certificate_file'] ?? null,
            ':bank_name'       => $data['bank_name'] ?? null,
            ':account_number'  => $data['account_number'] ?? null,
            ':account_holder'  => $data['account_holder'] ?? null,
            ':bank_file'       => $data['bank_file'] ?? null,
            ':note'             => $data['note'] ?? null,
            ':memo'             => $data['memo'] ?? null,
            ':created_by'       => $data['created_by'] ?? null
        ]);
    }

    /* ============================================================
     * 부분 업데이트 (user_id 기준)
     * ============================================================ */
    public function updateByUserId(string $userId, array $data): bool
    {
        if (empty($data)) {
            return true;
        }

        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            $fields[] = "`{$key}` = ?";
            $values[] = $value;
        }

        $fields[] = "updated_at = NOW()";
        $sql = "UPDATE user_employees SET " . implode(", ", $fields) . " WHERE user_id = ?";
        $values[] = $userId;

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }




/* ============================================================
 * 프로필 삭제 (user_id 기준)
 * ============================================================ */
public function hardDeleteByUserId(string $userId): bool
{
    $sql = "DELETE FROM user_employees WHERE user_id = ?";
    $stmt = $this->db->prepare($sql);
    return $stmt->execute([$userId]);
}


/* ============================================================
 * 상태 변경 (활성 / 비활성)
 * ============================================================ */
public function updateStatus(string $userId, array $data): bool
{
    $sql = "
        UPDATE auth_users
        SET 
            is_active   = :is_active,
            deleted_at  = :deleted_at,
            deleted_by  = :deleted_by,
            updated_at  = :updated_at,
            updated_by  = :updated_by
        WHERE id = :id
    ";

    $stmt = $this->db->prepare($sql);

    return $stmt->execute([
        ':is_active'  => $data['is_active'],
        ':deleted_at' => $data['deleted_at'],
        ':deleted_by' => $data['deleted_by'],
        ':updated_at' => $data['updated_at'],
        ':updated_by' => $data['updated_by'],
        ':id'         => $userId
    ]);
}


public function updateCode(string $id, int $newCode): bool
{
    $sql = "UPDATE user_employees SET code = :code WHERE id = :id";
    $stmt = $this->db->prepare($sql);

    $ok = $stmt->execute([
        ':code' => $newCode,
        ':id'   => $id
    ]);

    if (!$ok) {
        throw new \Exception('코드 업데이트 실패');
    }

    return true;
}


























    
    /* ============================================================
     * 프로필 이미지 변경 (updatedBy optional)
     * ============================================================ */
    public function updateProfileImage(string $userId, string $imagePath, ?string $updatedBy = null): bool
    {
        $sql = "
            UPDATE user_employees
               SET profile_image = ?, 
                   updated_by = ?, 
                   updated_at = NOW()
             WHERE user_id = ?
        ";

        return $this->db->prepare($sql)->execute([$imagePath, $updatedBy, $userId]);
    }

    /* ============================================================
     * 6. 직위/부서 업데이트
     * ============================================================ */
    public function updateDeptPos(string $userId, ?string $departmentId, ?string $positionId): bool
    {
        $sql = "
            UPDATE user_employees
               SET department_id = ?, 
                   position_id = ?, 
                   updated_at = NOW()
             WHERE user_id = ?
        ";

        return $this->db->prepare($sql)->execute([$departmentId, $positionId, $userId]);
    }


    /* ============================================================
     * 8. 직원 이름 수정
     * ============================================================ */
    public function updateEmployeeName(string $userId, string $employeeName, ?string $updatedBy = null): bool
    {
        $sql = "
            UPDATE user_employees
               SET employee_name = :name,
                   updated_by = :updated_by,
                   updated_at = NOW()
             WHERE user_id = :user_id
        ";

        return $this->db->prepare($sql)->execute([
            ':name'       => $employeeName,
            ':updated_by' => $updatedBy,
            ':user_id'    => $userId
        ]);
    }


    /* ============================================================
    * 프로필 전체 업데이트 (user_id 기준)
    * ============================================================ */
    public function updateProfileByUserId(string $userId, array $data): bool
    {
        if (empty($data)) {
            return true;
        }

        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            $fields[] = "`{$key}` = ?";
            $values[] = $value;
        }

        $fields[] = "updated_at = NOW()";
        $sql = "UPDATE user_employees SET " . implode(", ", $fields) . " WHERE user_id = ?";
        $values[] = $userId;

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }



    public function findByName(string $name): ?array
    {
        $sql = "
            SELECT *
            FROM user_employees
            WHERE employee_name = :name
            LIMIT 1
        ";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['name' => $name]);
    
        return $stmt->fetch() ?: null;
    }



    /* ============================================================
     * 직원 이름 중복 확인 (단일 함수로 정리)
     * Validation (보조 기능)
     * ============================================================ */
    public function existsByUsername(string $username): bool
    {
        $sql = "
            SELECT COUNT(*) 
            FROM auth_users
            WHERE username = :username
        ";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':username' => $username
        ]);
    
        return (int)$stmt->fetchColumn() > 0;
    }

    public function existsByUsernameExceptId(string $username, string $id): bool
    {
        $sql = "
            SELECT COUNT(*) 
            FROM auth_users
            WHERE username = :username
              AND id <> :id
        ";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':username' => $username,
            ':id' => $id
        ]);
    
        return (int)$stmt->fetchColumn() > 0;
    }

    public function getUserIdByEmployeeId(string $employeeId): ?string
    {
        $sql = "SELECT user_id FROM user_employees WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $employeeId]);
    
        return $stmt->fetchColumn() ?: null;
    }

}
