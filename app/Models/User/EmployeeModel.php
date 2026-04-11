<?php
// 경로: PROJECT_ROOT . '/app/Models/User/EmployeeModel.php'
namespace App\Models\User;

use PDO;
use Core\Database;

class EmployeeModel
{
    // PDO 보관
    private PDO $db;

    // 생성자 – 외부에서 PDO 주입 또는 자동 연결
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    /* =========================================================
    * 직원 전체 목록 (거래처 구조 기준 통일)
    * ========================================================= */
    public function getList(array $filters = []): array
    {
        $sql = "
            SELECT
                /* =========================
                * user_employees
                * ========================= */
                p.id,
                p.code,
                p.user_id,
                p.employee_name,
                p.phone,
                p.address,
                p.address_detail,
                p.department_id,
                p.position_id,
                p.doc_hire_date,
                p.real_hire_date,
                p.doc_retire_date,
                p.real_retire_date,
                p.rrn,
                p.rrn_image,
                p.emergency_phone,
                p.profile_image,
                p.certificate_name,
                p.certificate_file,
                p.bank_name,
                p.account_number,
                p.account_holder,
                p.bank_file,
                p.note,
                p.memo,
                p.created_at,
                p.created_by,
                p.updated_at,
                p.updated_by,

                /* =========================
                * 조직
                * ========================= */
                d.dept_name AS department_name,
                s.position_name,

                /* =========================
                * auth_users
                * ========================= */
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
                u.login_fail_count,
                u.account_locked_until,
                u.last_login,
                u.last_login_ip,
                u.last_login_device,
                u.password_updated_at,
                u.password_updated_by,
                u.created_at AS user_created_at,
                u.created_by AS user_created_by,
                u.updated_at AS user_updated_at,
                u.updated_by AS user_updated_by,
                u.deleted_at,
                u.deleted_by,

                /* =========================
                * 역할
                * ========================= */
                r.role_name,

                /* =========================
                * 직원 row 기준 표시명
                * ========================= */
                CASE 
                    WHEN p.created_by IS NULL THEN NULL
                    WHEN p.created_by LIKE 'SYSTEM:%' THEN p.created_by
                    WHEN p1.employee_name IS NOT NULL THEN CONCAT('USER:', p1.employee_name)
                    ELSE p.created_by
                END AS created_by_name,

                CASE 
                    WHEN p.updated_by IS NULL THEN NULL
                    WHEN p.updated_by LIKE 'SYSTEM:%' THEN p.updated_by
                    WHEN p2.employee_name IS NOT NULL THEN CONCAT('USER:', p2.employee_name)
                    ELSE p.updated_by
                END AS updated_by_name,

                /* =========================
                * auth_users 기준 표시명
                * ========================= */
                CASE 
                    WHEN u.created_by IS NULL THEN NULL
                    WHEN u.created_by LIKE 'SYSTEM:%' THEN u.created_by
                    WHEN uc.employee_name IS NOT NULL THEN CONCAT('USER:', uc.employee_name)
                    ELSE u.created_by
                END AS user_created_by_name,

                CASE 
                    WHEN u.updated_by IS NULL THEN NULL
                    WHEN u.updated_by LIKE 'SYSTEM:%' THEN u.updated_by
                    WHEN uu.employee_name IS NOT NULL THEN CONCAT('USER:', uu.employee_name)
                    ELSE u.updated_by
                END AS user_updated_by_name,

                CASE 
                    WHEN u.password_updated_by IS NULL THEN NULL
                    WHEN u.password_updated_by LIKE 'SYSTEM:%' THEN u.password_updated_by
                    WHEN upw.employee_name IS NOT NULL THEN CONCAT('USER:', upw.employee_name)
                    ELSE u.password_updated_by
                END AS password_updated_by_name,

                CASE 
                    WHEN u.approved_by IS NULL THEN NULL
                    WHEN u.approved_by LIKE 'SYSTEM:%' THEN u.approved_by
                    WHEN ua.employee_name IS NOT NULL THEN CONCAT('USER:', ua.employee_name)
                    ELSE u.approved_by
                END AS approved_by_name,

                CASE 
                    WHEN u.deleted_by IS NULL THEN NULL
                    WHEN u.deleted_by LIKE 'SYSTEM:%' THEN u.deleted_by
                    WHEN ud.employee_name IS NOT NULL THEN CONCAT('USER:', ud.employee_name)
                    ELSE u.deleted_by
                END AS deleted_by_name

            FROM user_employees p

            LEFT JOIN auth_users u
                ON p.user_id = u.id

            LEFT JOIN auth_roles r
                ON u.role_id = r.id

            LEFT JOIN user_departments d
                ON p.department_id = d.id

            LEFT JOIN user_positions s
                ON p.position_id = s.id

            /* 직원 row 기준 */
            LEFT JOIN user_employees p1
                ON p.created_by NOT LIKE 'SYSTEM:%'
                AND p1.user_id = REPLACE(p.created_by, 'USER:', '')

            LEFT JOIN user_employees p2
                ON p.updated_by NOT LIKE 'SYSTEM:%'
                AND p2.user_id = REPLACE(p.updated_by, 'USER:', '')

            /* auth_users 기준 */
            LEFT JOIN user_employees uc
                ON u.created_by NOT LIKE 'SYSTEM:%'
                AND uc.user_id = REPLACE(u.created_by, 'USER:', '')

            LEFT JOIN user_employees uu
                ON u.updated_by NOT LIKE 'SYSTEM:%'
                AND uu.user_id = REPLACE(u.updated_by, 'USER:', '')

            LEFT JOIN user_employees upw
                ON u.password_updated_by NOT LIKE 'SYSTEM:%'
                AND upw.user_id = REPLACE(u.password_updated_by, 'USER:', '')

            LEFT JOIN user_employees ua
                ON u.approved_by NOT LIKE 'SYSTEM:%'
                AND ua.user_id = REPLACE(u.approved_by, 'USER:', '')

            LEFT JOIN user_employees ud
                ON u.deleted_by NOT LIKE 'SYSTEM:%'
                AND ud.user_id = REPLACE(u.deleted_by, 'USER:', '')

            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters)) {

            $fieldMap = [

                // 기본
                'code'              => ['expr' => 'p.code', 'type' => 'exact'],
                'employee_name'     => ['expr' => 'p.employee_name', 'type' => 'like'],

                // 사용자
                'username'          => ['expr' => 'u.username', 'type' => 'like'],
                'email'             => ['expr' => 'u.email', 'type' => 'like'],
                'role_name'         => ['expr' => 'r.role_name', 'type' => 'like'],

                // 조직
                'department_name'   => ['expr' => 'd.dept_name', 'type' => 'like'],
                'position_name'     => ['expr' => 's.position_name', 'type' => 'like'],

                // 직원 테이블 전체 주요 컬럼
                'phone'             => ['expr' => 'p.phone', 'type' => 'like'],
                'emergency_phone'   => ['expr' => 'p.emergency_phone', 'type' => 'like'],
                'address'           => ['expr' => 'p.address', 'type' => 'like'],
                'address_detail'    => ['expr' => 'p.address_detail', 'type' => 'like'],
                'certificate_name'  => ['expr' => 'p.certificate_name', 'type' => 'like'],
                'bank_name'         => ['expr' => 'p.bank_name', 'type' => 'like'],
                'account_number'    => ['expr' => 'p.account_number', 'type' => 'like'],
                'account_holder'    => ['expr' => 'p.account_holder', 'type' => 'like'],
                'note'              => ['expr' => 'p.note', 'type' => 'like'],
                'memo'              => ['expr' => 'p.memo', 'type' => 'like'],

                // 상태
                'is_active'           => ['expr' => 'u.is_active', 'type' => 'exact'],
                'approved'            => ['expr' => 'u.approved', 'type' => 'exact'],
                'two_factor_enabled'  => ['expr' => 'u.two_factor_enabled', 'type' => 'exact'],
                'email_notify'        => ['expr' => 'u.email_notify', 'type' => 'exact'],
                'sms_notify'          => ['expr' => 'u.sms_notify', 'type' => 'exact'],
                'login_fail_count'    => ['expr' => 'u.login_fail_count', 'type' => 'exact'],

                // 날짜 (DATE)
                'doc_hire_date'     => ['expr' => 'p.doc_hire_date', 'type' => 'date'],
                'real_hire_date'    => ['expr' => 'p.real_hire_date', 'type' => 'date'],
                'doc_retire_date'   => ['expr' => 'p.doc_retire_date', 'type' => 'date'],
                'real_retire_date'  => ['expr' => 'p.real_retire_date', 'type' => 'date'],

                // 날짜시간 (DATETIME)
                'approved_at'         => ['expr' => 'u.approved_at', 'type' => 'datetime'],
                'last_login'          => ['expr' => 'u.last_login', 'type' => 'datetime'],
                'password_updated_at' => ['expr' => 'u.password_updated_at', 'type' => 'datetime'],
                'user_created_at'     => ['expr' => 'u.created_at', 'type' => 'datetime'],
                'user_updated_at'     => ['expr' => 'u.updated_at', 'type' => 'datetime'],
                'deleted_at'          => ['expr' => 'u.deleted_at', 'type' => 'datetime'],
                'created_at'          => ['expr' => 'p.created_at', 'type' => 'datetime'],
                'updated_at'          => ['expr' => 'p.updated_at', 'type' => 'datetime'],
            ];

            $globalSearchValues = [];

            foreach ($filters as $f) {

                $field = $f['field'] ?? '';
                $value = $f['value'] ?? '';

                if ($value === '' || $value === null) {
                    continue;
                }

                // 전체검색
                if ($field === '') {
                    $globalSearchValues[] = $value;
                    continue;
                }

                if (!isset($fieldMap[$field])) {
                    continue;
                }

                $expr = $fieldMap[$field]['expr'];
                $type = $fieldMap[$field]['type'];

                // DATE
                if ($type === 'date') {

                    if (is_array($value) && isset($value['start'], $value['end'])) {
                        $sql .= " AND DATE($expr) BETWEEN ? AND ?";
                        $params[] = $value['start'];
                        $params[] = $value['end'];
                    } else {
                        $sql .= " AND DATE($expr) = ?";
                        $params[] = $value;
                    }

                    continue;
                }

                // DATETIME
                if ($type === 'datetime') {

                    if (is_array($value) && isset($value['start'], $value['end'])) {
                        $start = trim((string)($value['start'] ?? ''));
                        $end   = trim((string)($value['end'] ?? ''));

                        if ($start !== '' && $end !== '') {
                            $sql .= " AND $expr BETWEEN ? AND ?";

                            $params[] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)
                                ? $start . ' 00:00:00'
                                : $start;

                            $params[] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)
                                ? $end . ' 23:59:59'
                                : $end;
                        }

                    } else {
                        $stringValue = trim((string)$value);

                        // 날짜만 들어오면 하루 범위 검색
                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $stringValue)) {
                            $sql .= " AND $expr BETWEEN ? AND ?";
                            $params[] = $stringValue . ' 00:00:00';
                            $params[] = $stringValue . ' 23:59:59';
                        } else {
                            $sql .= " AND $expr = ?";
                            $params[] = $stringValue;
                        }
                    }

                    continue;
                }
                // exact
                if ($type === 'exact') {
                    $sql .= " AND $expr = ?";
                    $params[] = $value;
                    continue;
                }

                // like
                if ($type === 'like') {
                    $sql .= " AND $expr LIKE ?";
                    $params[] = "%{$value}%";
                    continue;
                }
            }

            // 전체검색
            if (!empty($globalSearchValues)) {

                $searchableColumns = [
                    'p.employee_name',
                    'u.username',
                    'u.email',
                    'd.dept_name',
                    's.position_name',
                    'p.phone',
                    'p.emergency_phone',
                    'p.address',
                    'p.address_detail',
                    'p.certificate_name',
                    'p.bank_name',
                    'p.account_number',
                    'p.account_holder',
                    'p.note',
                    'p.memo',
                    'r.role_name',
                    'u.last_login_ip',
                    'u.last_login_device',
                    'u.approved_by',
                    'u.created_by',
                    'u.updated_by',
                    'u.password_updated_by',
                    'u.deleted_by'
                ];

                $sql .= " AND (";

                $first = true;

                foreach ($globalSearchValues as $value) {

                    if (!$first) {
                        $sql .= " OR ";
                    }

                    $sql .= "(";

                    $colFirst = true;

                    foreach ($searchableColumns as $col) {

                        if (!$colFirst) {
                            $sql .= " OR ";
                        }

                        $sql .= "$col LIKE ?";
                        $params[] = "%{$value}%";

                        $colFirst = false;
                    }

                    $sql .= ")";
                    $first = false;
                }

                $sql .= ")";
            }
        }

        $sql .= " ORDER BY p.code ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /* =========================================================
    * 직원 단일 조회 (auth_users + user_employees 완전 조회)
    * ========================================================= */
    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                /* =========================
                * user_employees
                * ========================= */
                p.id,
                p.code,
                p.user_id,
                p.employee_name,
                p.phone,
                p.address,
                p.address_detail,
                p.department_id,
                p.position_id,
                p.doc_hire_date,
                p.real_hire_date,
                p.doc_retire_date,
                p.real_retire_date,
                p.rrn,
                p.rrn_image,
                p.emergency_phone,
                p.profile_image,
                p.certificate_name,
                p.certificate_file,
                p.bank_name,
                p.account_number,
                p.account_holder,
                p.bank_file,
                p.note,
                p.memo,
                p.created_at,
                p.created_by,
                p.updated_at,
                p.updated_by,

                /* =========================
                * 조직
                * ========================= */
                d.dept_name AS department_name,
                s.position_name,

                /* =========================
                * auth_users
                * ========================= */
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
                u.login_fail_count,
                u.account_locked_until,
                u.last_login,
                u.last_login_ip,
                u.last_login_device,
                u.password_updated_at,
                u.password_updated_by,
                u.created_at AS user_created_at,
                u.created_by AS user_created_by,
                u.updated_at AS user_updated_at,
                u.updated_by AS user_updated_by,
                u.deleted_at,
                u.deleted_by,

                /* =========================
                * 역할
                * ========================= */
                r.role_name,

                /* =========================
                * 직원 테이블 기준 생성/수정 표시명
                * ========================= */
                CASE 
                    WHEN p.created_by IS NULL THEN NULL
                    WHEN p.created_by LIKE 'SYSTEM:%' THEN p.created_by
                    WHEN p1.employee_name IS NOT NULL THEN CONCAT('USER:', p1.employee_name)
                    ELSE p.created_by
                END AS created_by_name,

                CASE 
                    WHEN p.updated_by IS NULL THEN NULL
                    WHEN p.updated_by LIKE 'SYSTEM:%' THEN p.updated_by
                    WHEN p2.employee_name IS NOT NULL THEN CONCAT('USER:', p2.employee_name)
                    ELSE p.updated_by
                END AS updated_by_name,

                /* =========================
                * auth_users 기준 표시명
                * ========================= */
                CASE 
                    WHEN u.created_by IS NULL THEN NULL
                    WHEN u.created_by LIKE 'SYSTEM:%' THEN u.created_by
                    WHEN uc.employee_name IS NOT NULL THEN CONCAT('USER:', uc.employee_name)
                    ELSE u.created_by
                END AS user_created_by_name,

                CASE 
                    WHEN u.updated_by IS NULL THEN NULL
                    WHEN u.updated_by LIKE 'SYSTEM:%' THEN u.updated_by
                    WHEN uu.employee_name IS NOT NULL THEN CONCAT('USER:', uu.employee_name)
                    ELSE u.updated_by
                END AS user_updated_by_name,

                CASE 
                    WHEN u.password_updated_by IS NULL THEN NULL
                    WHEN u.password_updated_by LIKE 'SYSTEM:%' THEN u.password_updated_by
                    WHEN upw.employee_name IS NOT NULL THEN CONCAT('USER:', upw.employee_name)
                    ELSE u.password_updated_by
                END AS password_updated_by_name,

                CASE 
                    WHEN u.approved_by IS NULL THEN NULL
                    WHEN u.approved_by LIKE 'SYSTEM:%' THEN u.approved_by
                    WHEN ua.employee_name IS NOT NULL THEN CONCAT('USER:', ua.employee_name)
                    ELSE u.approved_by
                END AS approved_by_name,

                CASE 
                    WHEN u.deleted_by IS NULL THEN NULL
                    WHEN u.deleted_by LIKE 'SYSTEM:%' THEN u.deleted_by
                    WHEN ud.employee_name IS NOT NULL THEN CONCAT('USER:', ud.employee_name)
                    ELSE u.deleted_by
                END AS deleted_by_name

            FROM user_employees p
            LEFT JOIN auth_users u
                ON p.user_id = u.id
            LEFT JOIN auth_roles r
                ON u.role_id = r.id
            LEFT JOIN user_departments d
                ON p.department_id = d.id
            LEFT JOIN user_positions s
                ON p.position_id = s.id

            /* 직원 row 기준 */
            LEFT JOIN user_employees p1
                ON p.created_by NOT LIKE 'SYSTEM:%'
                AND p1.user_id = REPLACE(p.created_by, 'USER:', '')

            LEFT JOIN user_employees p2
                ON p.updated_by NOT LIKE 'SYSTEM:%'
                AND p2.user_id = REPLACE(p.updated_by, 'USER:', '')

            /* auth row 기준 */
            LEFT JOIN user_employees uc
                ON u.created_by NOT LIKE 'SYSTEM:%'
                AND uc.user_id = REPLACE(u.created_by, 'USER:', '')

            LEFT JOIN user_employees uu
                ON u.updated_by NOT LIKE 'SYSTEM:%'
                AND uu.user_id = REPLACE(u.updated_by, 'USER:', '')

            LEFT JOIN user_employees upw
                ON u.password_updated_by NOT LIKE 'SYSTEM:%'
                AND upw.user_id = REPLACE(u.password_updated_by, 'USER:', '')

            LEFT JOIN user_employees ua
                ON u.approved_by NOT LIKE 'SYSTEM:%'
                AND ua.user_id = REPLACE(u.approved_by, 'USER:', '')

            LEFT JOIN user_employees ud
                ON u.deleted_by NOT LIKE 'SYSTEM:%'
                AND ud.user_id = REPLACE(u.deleted_by, 'USER:', '')

            WHERE p.id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }


    
    /* =========================================================
    * 직원 검색 (Select2용 - Model은 RAW만 반환)
    * ========================================================= */
    public function searchPicker(string $q = '', int $limit = 20): array
    {
        $limit = max(1, min(100, (int)$limit));

        $q = trim($q);
        $keyword = '%' . $q . '%';
        $prefixKeyword = $q . '%';

        $sql = "
            SELECT
                p.id,
                p.code,
                p.employee_name,
                d.dept_name AS department_name,
                s.position_name,
                u.username,
                u.email,
                u.is_active

            FROM user_employees p

            LEFT JOIN auth_users u ON p.user_id = u.id
            LEFT JOIN user_departments d ON p.department_id = d.id
            LEFT JOIN user_positions s ON p.position_id = s.id

            WHERE u.is_active = 1
            AND (
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





    /* =========================================================
    * 직원 생성 (표준화)
    * ========================================================= */
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
                created_by, updated_by
            ) VALUES (
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
                :created_by, :updated_by
            )
        ";

        $stmt = $this->db->prepare($sql);

        /* =========================================================
        * 필수값 검증
        * ========================================================= */
        if (empty($data['created_by'])) {
            throw new \Exception('created_by 없음');
        }

        return $stmt->execute([

            'id'               => $data['id'],
            'code'             => $data['code'],
            'user_id'          => $data['user_id'],
            'employee_name'    => $data['employee_name'],

            'phone'            => $data['phone'] ?? null,
            'address'          => $data['address'] ?? null,
            'address_detail'   => $data['address_detail'] ?? null,

            'department_id'    => $data['department_id'] ?? null,
            'position_id'      => $data['position_id'] ?? null,

            'doc_hire_date'    => $data['doc_hire_date'] ?? null,
            'real_hire_date'   => $data['real_hire_date'] ?? null,
            'doc_retire_date'  => $data['doc_retire_date'] ?? null,
            'real_retire_date' => $data['real_retire_date'] ?? null,

            'rrn'              => $data['rrn'] ?? null,
            'rrn_image'        => $data['rrn_image'] ?? null,

            'emergency_phone'  => $data['emergency_phone'] ?? null,

            'profile_image'    => $data['profile_image'] ?? null,
            'certificate_name' => $data['certificate_name'] ?? null,
            'certificate_file' => $data['certificate_file'] ?? null,

            'bank_name'        => $data['bank_name'] ?? null,
            'account_number'   => $data['account_number'] ?? null,
            'account_holder'   => $data['account_holder'] ?? null,
            'bank_file'        => $data['bank_file'] ?? null,

            'note'             => $data['note'] ?? null,
            'memo'             => $data['memo'] ?? null,

            'created_by'       => $data['created_by'],
            'updated_by'       => $data['updated_by'] ?? $data['created_by']
        ]);
    }

    /* =========================================================
    * 직원 수정 (employee_id 기준)
    * ========================================================= */
    public function updateById(string $id, array $data): bool
    {
        if (empty($data['updated_by'])) {
            throw new \Exception('updated_by 없음');
        }

        $sql = "
            UPDATE user_employees SET
                employee_name = :employee_name,
                phone = :phone,
                address = :address,
                address_detail = :address_detail,

                department_id = :department_id,
                position_id = :position_id,

                doc_hire_date = :doc_hire_date,
                real_hire_date = :real_hire_date,
                doc_retire_date = :doc_retire_date,
                real_retire_date = :real_retire_date,

                rrn = :rrn,
                rrn_image = :rrn_image,

                emergency_phone = :emergency_phone,

                profile_image = :profile_image,
                certificate_name = :certificate_name,
                certificate_file = :certificate_file,

                bank_name = :bank_name,
                account_number = :account_number,
                account_holder = :account_holder,
                bank_file = :bank_file,

                note = :note,
                memo = :memo,

                updated_by = :updated_by

            WHERE id = :id
        ";

        $params = [

            'id'               => $id,
            'employee_name'    => trim((string)($data['employee_name'] ?? '')),

            'phone'            => $data['phone'] ?? null,
            'address'          => $data['address'] ?? null,
            'address_detail'   => $data['address_detail'] ?? null,

            'department_id'    => $data['department_id'] ?? null,
            'position_id'      => $data['position_id'] ?? null,

            'doc_hire_date'    => $data['doc_hire_date'] ?? null,
            'real_hire_date'   => $data['real_hire_date'] ?? null,
            'doc_retire_date'  => $data['doc_retire_date'] ?? null,
            'real_retire_date' => $data['real_retire_date'] ?? null,

            'rrn'              => $data['rrn'] ?? null,
            'rrn_image'        => $data['rrn_image'] ?? null,

            'emergency_phone'  => $data['emergency_phone'] ?? null,

            'profile_image'    => $data['profile_image'] ?? null,
            'certificate_name' => $data['certificate_name'] ?? null,
            'certificate_file' => $data['certificate_file'] ?? null,

            'bank_name'        => $data['bank_name'] ?? null,
            'account_number'   => $data['account_number'] ?? null,
            'account_holder'   => $data['account_holder'] ?? null,
            'bank_file'        => $data['bank_file'] ?? null,

            'note'             => $data['note'] ?? null,
            'memo'             => $data['memo'] ?? null,

            'updated_by'       => $data['updated_by']
        ];

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }


    /* =========================================================
    * 상태 변경 (활성 / 비활성)
    * ========================================================= */
    public function updateStatus(string $userId, array $data): bool
    {
        $sql = "
            UPDATE auth_users SET
                is_active  = :is_active,
                deleted_at = :deleted_at,
                deleted_by = :deleted_by,
                updated_by = :updated_by
            WHERE id = :id
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'is_active'  => $data['is_active'],
            'deleted_at' => $data['deleted_at'] ?? null,
            'deleted_by' => $data['deleted_by'] ?? null,
            'updated_by' => $data['updated_by'],
            'id'         => $userId
        ]);
    }

    /* =========================================================
    * 직원 프로필 삭제 (employee_id 기준)
    * ⚠️ 일반적으로 직접 호출하지 않음 (CASCADE 사용 권장)
    * ========================================================= */
    public function hardDeleteById(string $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM user_employees
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id
        ]);
    }



    /* =========================================================
    * 직원 code 수정 (거래처와 통일)
    * ========================================================= */
    public function updateCode(string $id, int $newCode): bool
    {
        $sql = "UPDATE user_employees SET code = :newCode WHERE id = :id";

        $stmt = $this->db->prepare($sql);

        $ok = $stmt->execute([
            'newCode' => (int)$newCode,
            'id'      => $id
        ]);

        if (!$ok) {
            throw new \Exception('쿼리 실행 실패');
        }

        return true;
    }

////////////////////////////////////////////////////////////////////////아래는 프로필관련모델 메소드////////////



    
    // /* ============================================================
    //  * 프로필 이미지 변경 (updatedBy optional)
    //  * ============================================================ */
    // public function updateProfileImage(string $userId, string $imagePath, ?string $updatedBy = null): bool
    // {
    //     $sql = "
    //         UPDATE user_employees
    //            SET profile_image = ?, 
    //                updated_by = ?, 
    //                updated_at = NOW()
    //          WHERE user_id = ?
    //     ";

    //     return $this->db->prepare($sql)->execute([$imagePath, $updatedBy, $userId]);
    // }

    // /* ============================================================
    //  * 직위/부서 업데이트
    //  * ============================================================ */
    // public function updateDeptPos(string $userId, ?string $departmentId, ?string $positionId): bool
    // {
    //     $sql = "
    //         UPDATE user_employees
    //            SET department_id = ?, 
    //                position_id = ?, 
    //                updated_at = NOW()
    //          WHERE user_id = ?
    //     ";

    //     return $this->db->prepare($sql)->execute([$departmentId, $positionId, $userId]);
    // }


    // /* ============================================================
    //  * 직원 이름 수정
    //  * ============================================================ */
    // public function updateEmployeeName(string $userId, string $employeeName, ?string $updatedBy = null): bool
    // {
    //     $sql = "
    //         UPDATE user_employees
    //            SET employee_name = :name,
    //                updated_by = :updated_by,
    //                updated_at = NOW()
    //          WHERE user_id = :user_id
    //     ";

    //     return $this->db->prepare($sql)->execute([
    //         ':name'       => $employeeName,
    //         ':updated_by' => $updatedBy,
    //         ':user_id'    => $userId
    //     ]);
    // }


    // /* ============================================================
    // * 프로필 전체 업데이트 (user_id 기준)
    // * ============================================================ */
    // public function updateProfileByUserId(string $userId, array $data): bool
    // {
    //     if (empty($data)) {
    //         return true;
    //     }

    //     $fields = [];
    //     $values = [];

    //     foreach ($data as $key => $value) {
    //         $fields[] = "`{$key}` = ?";
    //         $values[] = $value;
    //     }

    //     $fields[] = "updated_at = NOW()";
    //     $sql = "UPDATE user_employees SET " . implode(", ", $fields) . " WHERE user_id = ?";
    //     $values[] = $userId;

    //     $stmt = $this->db->prepare($sql);
    //     return $stmt->execute($values);
    // }







    // /* ============================================================
    // * 사용자 + 프로필 JOIN (role → role_id로 수정)
    // * ============================================================ */
    // public function getUserWithProfile(string $userId): ?array
    // {
    //     $sql = "
    //         SELECT 
    //             u.id AS user_id,
    //             u.username,
    //             u.email,
    //             u.role_id,
    //             u.two_factor_enabled,
    //             u.email_notify,
    //             u.sms_notify,

    //             /* ===== 로그인 관련 ===== */
    //             u.last_login,
    //             u.last_login_ip,
    //             u.last_login_device,

    //             /* ===== 비밀번호 변경 기록 ===== */
    //             u.password_updated_at,
    //             u.password_updated_by,

    //             /* ===== 생성/수정/삭제 기록 ===== */
    //             u.created_at AS user_created_at,
    //             u.created_by AS user_created_by,
    //             u.updated_at AS user_updated_at,
    //             u.updated_by AS user_updated_by,
    //             u.deleted_at,
    //             u.deleted_by,

    //             /* ===== 프로필 ===== */
    //             p.*
    //         FROM auth_users u
    //         LEFT JOIN user_employees p 
    //             ON p.user_id = u.id                
    //         WHERE u.id = ?
    //         LIMIT 1
    //     ";

    //     $stmt = $this->db->prepare($sql);
    //     $stmt->execute([$userId]);
    //     return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    // }











}
