<?php
namespace App\Models\User;

use PDO;
use Core\Database;
use Core\Helpers\ActorHelper;

class EmployeeModel
{
    public function findEmployeeNameByUserId(string $userId): ?string
    {
        $stmt = $this->db->prepare("
            SELECT employee_name FROM user_employees WHERE user_id = :user_id LIMIT 1
        ");
        $stmt->execute([':user_id' => $userId]);
        $name = $stmt->fetchColumn();

        return $name === false ? null : (string) $name;
    }

    public function findByUserId(string $userId, bool $forUpdate = false): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM user_employees WHERE user_id = :user_id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function hasEligibleUserForRole(string $roleId): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM auth_users u
            INNER JOIN user_employees e ON e.user_id = u.id
            INNER JOIN auth_roles r ON r.id = u.role_id
            WHERE u.role_id = :role_id
              AND u.approved = 1
              AND u.is_active = 1
              AND r.is_active = 1
              AND (e.doc_retire_date IS NULL OR e.doc_retire_date > CURRENT_DATE())
              AND (e.real_retire_date IS NULL OR e.real_retire_date > CURRENT_DATE())
            LIMIT 1");
        $stmt->execute([':role_id' => $roleId]);
        return (bool) $stmt->fetchColumn();
    }

    public function userIsEligibleForRole(string $userId, string $roleId): bool
    {
        return $this->userEligibilityForRole($userId, $roleId)['eligible'];
    }

    public function userEligibilityForRole(string $userId, string $roleId): array
    {
        $stmt = $this->db->prepare("SELECT
                u.id user_id, u.role_id, u.approved, u.is_active,
                e.employee_name, e.doc_retire_date, e.real_retire_date,
                assigned_role.role_name assigned_role_name,
                selected_role.id selected_role_id, selected_role.role_name selected_role_name,
                selected_role.is_active selected_role_active
            FROM auth_users u
            LEFT JOIN user_employees e ON e.user_id = u.id
            LEFT JOIN auth_roles assigned_role ON assigned_role.id = u.role_id
            LEFT JOIN auth_roles selected_role ON selected_role.id = :role_id
            WHERE u.id = :user_id
            LIMIT 1");
        $stmt->execute([':user_id' => $userId, ':role_id' => $roleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ['eligible' => false, 'message' => '선택한 특정 결재자의 사용자 계정을 찾을 수 없습니다.'];
        }

        $name = trim((string) ($row['employee_name'] ?? '')) ?: '선택한 사용자';
        if (trim((string) ($row['selected_role_id'] ?? '')) === '') {
            return ['eligible' => false, 'message' => '선택한 결재자 역할을 찾을 수 없습니다.'];
        }
        if ((string) ($row['role_id'] ?? '') !== $roleId) {
            $assignedRoleName = trim((string) ($row['assigned_role_name'] ?? '')) ?: '역할 없음';
            $selectedRoleName = trim((string) ($row['selected_role_name'] ?? '')) ?: $roleId;
            return [
                'eligible' => false,
                'message' => "{$name} 사용자의 현재 역할은 {$assignedRoleName}이며, 선택한 {$selectedRoleName} 역할과 일치하지 않습니다.",
            ];
        }
        if ((int) ($row['approved'] ?? 0) !== 1) {
            return [
                'eligible' => false,
                'message' => "{$name} 사용자는 사용자 승인이 완료되지 않아 특정 결재자로 지정할 수 없습니다. 사용자 승인 후 다시 시도해 주세요.",
            ];
        }
        if ((int) ($row['is_active'] ?? 0) !== 1) {
            return ['eligible' => false, 'message' => "{$name} 사용자는 비활성 계정이어서 특정 결재자로 지정할 수 없습니다."];
        }
        if ((int) ($row['selected_role_active'] ?? 0) !== 1) {
            return ['eligible' => false, 'message' => '선택한 결재자 역할이 비활성 상태입니다.'];
        }
        if (trim((string) ($row['employee_name'] ?? '')) === '') {
            return ['eligible' => false, 'message' => '선택한 특정 결재자와 연결된 직원 정보를 찾을 수 없습니다.'];
        }
        if (
            ($row['doc_retire_date'] !== null && (string) $row['doc_retire_date'] <= date('Y-m-d'))
            || ($row['real_retire_date'] !== null && (string) $row['real_retire_date'] <= date('Y-m-d'))
        ) {
            return ['eligible' => false, 'message' => "{$name} 사용자는 퇴사 처리되어 특정 결재자로 지정할 수 없습니다."];
        }

        return ['eligible' => true, 'message' => ''];
    }

    public function userEligibility(string $userId): array
    {
        $stmt = $this->db->prepare("SELECT u.id, u.approved, u.is_active,
                e.employee_name, e.doc_retire_date, e.real_retire_date
            FROM auth_users u
            LEFT JOIN user_employees e ON e.user_id = u.id
            WHERE u.id = :user_id
            LIMIT 1");
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ['eligible' => false, 'message' => '선택한 특정 결재자의 사용자 계정을 찾을 수 없습니다.'];
        }
        $name = trim((string) ($row['employee_name'] ?? '')) ?: '선택한 사용자';
        if ((int) ($row['approved'] ?? 0) !== 1) {
            return ['eligible' => false, 'message' => "{$name} 사용자는 사용자 승인이 완료되지 않아 특정 결재자로 지정할 수 없습니다."];
        }
        if ((int) ($row['is_active'] ?? 0) !== 1) {
            return ['eligible' => false, 'message' => "{$name} 사용자는 비활성 계정이어서 특정 결재자로 지정할 수 없습니다."];
        }
        if (trim((string) ($row['employee_name'] ?? '')) === '') {
            return ['eligible' => false, 'message' => '선택한 특정 결재자와 연결된 직원 정보를 찾을 수 없습니다.'];
        }
        if (($row['doc_retire_date'] !== null && (string) $row['doc_retire_date'] <= date('Y-m-d'))
            || ($row['real_retire_date'] !== null && (string) $row['real_retire_date'] <= date('Y-m-d'))) {
            return ['eligible' => false, 'message' => "{$name} 사용자는 퇴사 처리되어 특정 결재자로 지정할 수 없습니다."];
        }
        return ['eligible' => true, 'message' => ''];
    }
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getList(array $filters = []): array
    {
        $sql = "
            SELECT
                p.id,
                p.sort_no,
                p.user_id,
                p.employee_name,
                p.phone,
                p.address,
                p.address_detail,
                p.department_id,
                p.position_id,
                p.employment_status,
                p.job_id,
                p.doc_hire_date,
                p.real_hire_date,
                p.doc_retire_date,
                p.real_retire_date,
                p.rrn,
                p.rrn_image,
                p.emergency_phone,
                p.profile_image,
                p.representative_qualification_id,
                p.bank_name,
                p.account_number,
                p.account_holder,
                p.bank_file,
                p.note,
                p.memo,

                d.dept_name AS department_name,
                s.position_name,
                CASE WHEN rq.deleted_at IS NULL AND rq.status_code = 'ACTIVE' AND rq.verified_at IS NOT NULL AND (rq.valid_from IS NULL OR rq.valid_from <= CURDATE()) AND (rq.valid_to IS NULL OR rq.valid_to >= CURDATE()) THEN rq.qualification_name END AS representative_qualification_name,
                CASE WHEN rq.deleted_at IS NULL AND rq.status_code = 'ACTIVE' AND rq.verified_at IS NOT NULL AND (rq.valid_from IS NULL OR rq.valid_from <= CURDATE()) AND (rq.valid_to IS NULL OR rq.valid_to >= CURDATE()) THEN rq.attachment_path END AS representative_qualification_file,
                CASE WHEN rq.deleted_at IS NULL AND rq.status_code = 'ACTIVE' AND rq.verified_at IS NOT NULL AND (rq.valid_from IS NULL OR rq.valid_from <= CURDATE()) AND (rq.valid_to IS NULL OR rq.valid_to >= CURDATE()) THEN rq.attachment_name END AS representative_qualification_file_name,
                CASE WHEN rq.id IS NOT NULL AND rq.deleted_at IS NULL AND rq.status_code = 'ACTIVE' AND rq.verified_at IS NOT NULL AND (rq.valid_from IS NULL OR rq.valid_from <= CURDATE()) AND (rq.valid_to IS NULL OR rq.valid_to >= CURDATE()) THEN 1 ELSE 0 END AS representative_qualification_is_valid,
                CASE WHEN p.representative_qualification_id IS NOT NULL AND NOT (rq.id IS NOT NULL AND rq.deleted_at IS NULL AND rq.status_code = 'ACTIVE' AND rq.verified_at IS NOT NULL AND (rq.valid_from IS NULL OR rq.valid_from <= CURDATE()) AND (rq.valid_to IS NULL OR rq.valid_to >= CURDATE())) THEN 1 ELSE 0 END AS representative_qualification_requires_reassignment,
                (SELECT COUNT(*) FROM institution_qualifications_employee_records qc WHERE qc.employee_id = p.id AND qc.deleted_at IS NULL) AS qualification_count,
                (SELECT COUNT(*) FROM institution_educations_employee_records ec WHERE ec.employee_id = p.id AND ec.deleted_at IS NULL) AS education_count,

                u.id AS auth_user_id,
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

                r.role_name,

                u.created_by AS user_created_by_name,
                u.updated_by AS user_updated_by_name,
                u.password_updated_by AS password_updated_by_name,
                u.approved_by AS approved_by_name

            FROM user_employees p

            LEFT JOIN auth_users u
                ON p.user_id = u.id

            LEFT JOIN auth_roles r
                ON u.role_id = r.id

            LEFT JOIN user_departments d
                ON p.department_id = d.id

            LEFT JOIN user_positions s
                ON p.position_id = s.id

            LEFT JOIN institution_qualifications_employee_records rq
                ON p.representative_qualification_id = rq.id
                AND rq.employee_id = p.id
                AND rq.deleted_at IS NULL

            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters)) {

            $fieldMap = [

                'sort_no'              => ['expr' => 'p.sort_no', 'type' => 'exact'],
                'employee_name'     => ['expr' => 'p.employee_name', 'type' => 'like'],

                'username'          => ['expr' => 'u.username', 'type' => 'like'],
                'email'             => ['expr' => 'u.email', 'type' => 'like'],
                'role_name'         => ['expr' => 'r.role_name', 'type' => 'like'],

                'department_name'   => ['expr' => 'd.dept_name', 'type' => 'like'],
                'position_name'     => ['expr' => 's.position_name', 'type' => 'like'],

                'phone'             => ['expr' => 'p.phone', 'type' => 'like'],
                'emergency_phone'   => ['expr' => 'p.emergency_phone', 'type' => 'like'],
                'address'           => ['expr' => 'p.address', 'type' => 'like'],
                'address_detail'    => ['expr' => 'p.address_detail', 'type' => 'like'],
                'bank_name'         => ['expr' => 'p.bank_name', 'type' => 'like'],
                'account_number'    => ['expr' => 'p.account_number', 'type' => 'like'],
                'account_holder'    => ['expr' => 'p.account_holder', 'type' => 'like'],
                'note'              => ['expr' => 'p.note', 'type' => 'like'],
                'memo'              => ['expr' => 'p.memo', 'type' => 'like'],

                'is_active'           => ['expr' => 'u.is_active', 'type' => 'exact'],
                'approved'            => ['expr' => 'u.approved', 'type' => 'exact'],
                'two_factor_enabled'  => ['expr' => 'u.two_factor_enabled', 'type' => 'exact'],
                'email_notify'        => ['expr' => 'u.email_notify', 'type' => 'exact'],
                'sms_notify'          => ['expr' => 'u.sms_notify', 'type' => 'exact'],
                'login_fail_count'    => ['expr' => 'u.login_fail_count', 'type' => 'exact'],

                'doc_hire_date'     => ['expr' => 'p.doc_hire_date', 'type' => 'date'],
                'real_hire_date'    => ['expr' => 'p.real_hire_date', 'type' => 'date'],
                'doc_retire_date'   => ['expr' => 'p.doc_retire_date', 'type' => 'date'],
                'real_retire_date'  => ['expr' => 'p.real_retire_date', 'type' => 'date'],

                'approved_at'         => ['expr' => 'u.approved_at', 'type' => 'datetime'],
                'last_login'          => ['expr' => 'u.last_login', 'type' => 'datetime'],
                'password_updated_at' => ['expr' => 'u.password_updated_at', 'type' => 'datetime'],
                'user_created_at'     => ['expr' => 'u.created_at', 'type' => 'datetime'],
                'user_updated_at'     => ['expr' => 'u.updated_at', 'type' => 'datetime'],
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

                if ($type === 'exact') {
                    $sql .= " AND $expr = ?";
                    $params[] = $value;
                    continue;
                }

                if ($type === 'like') {
                    $sql .= " AND $expr LIKE ?";
                    $params[] = "%{$value}%";
                    continue;
                }
            }

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
                    'u.password_updated_by'
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

        $sql .= " ORDER BY p.sort_no ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ActorHelper::enrichActorNames(
            $rows ?: [],
            [
                'user_created_by_name' => 'user_created_by',
                'user_updated_by_name' => 'user_updated_by',
                'password_updated_by_name' => 'password_updated_by',
                'approved_by_name' => 'approved_by',
            ]
        );
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                p.id,
                p.sort_no,
                p.user_id,
                p.employee_name,
                p.phone,
                p.address,
                p.address_detail,
                p.department_id,
                p.position_id,
                p.employment_status,
                p.job_id,
                p.doc_hire_date,
                p.real_hire_date,
                p.doc_retire_date,
                p.real_retire_date,
                p.rrn,
                p.rrn_image,
                p.emergency_phone,
                p.profile_image,
                p.representative_qualification_id,
                p.bank_name,
                p.account_number,
                p.account_holder,
                p.bank_file,
                p.note,
                p.memo,

                d.dept_name AS department_name,
                s.position_name,
                CASE WHEN rq.deleted_at IS NULL AND rq.status_code = 'ACTIVE' AND rq.verified_at IS NOT NULL AND (rq.valid_from IS NULL OR rq.valid_from <= CURDATE()) AND (rq.valid_to IS NULL OR rq.valid_to >= CURDATE()) THEN rq.qualification_name END AS representative_qualification_name,
                CASE WHEN rq.deleted_at IS NULL AND rq.status_code = 'ACTIVE' AND rq.verified_at IS NOT NULL AND (rq.valid_from IS NULL OR rq.valid_from <= CURDATE()) AND (rq.valid_to IS NULL OR rq.valid_to >= CURDATE()) THEN rq.attachment_path END AS representative_qualification_file,
                CASE WHEN rq.deleted_at IS NULL AND rq.status_code = 'ACTIVE' AND rq.verified_at IS NOT NULL AND (rq.valid_from IS NULL OR rq.valid_from <= CURDATE()) AND (rq.valid_to IS NULL OR rq.valid_to >= CURDATE()) THEN rq.attachment_name END AS representative_qualification_file_name,
                CASE WHEN rq.id IS NOT NULL AND rq.deleted_at IS NULL AND rq.status_code = 'ACTIVE' AND rq.verified_at IS NOT NULL AND (rq.valid_from IS NULL OR rq.valid_from <= CURDATE()) AND (rq.valid_to IS NULL OR rq.valid_to >= CURDATE()) THEN 1 ELSE 0 END AS representative_qualification_is_valid,
                CASE WHEN p.representative_qualification_id IS NOT NULL AND NOT (rq.id IS NOT NULL AND rq.deleted_at IS NULL AND rq.status_code = 'ACTIVE' AND rq.verified_at IS NOT NULL AND (rq.valid_from IS NULL OR rq.valid_from <= CURDATE()) AND (rq.valid_to IS NULL OR rq.valid_to >= CURDATE())) THEN 1 ELSE 0 END AS representative_qualification_requires_reassignment,

                u.id AS auth_user_id,
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

                r.role_name,

                u.created_by AS user_created_by_name,
                u.updated_by AS user_updated_by_name,
                u.password_updated_by AS password_updated_by_name,
                u.approved_by AS approved_by_name

            FROM user_employees p
            LEFT JOIN auth_users u
                ON p.user_id = u.id
            LEFT JOIN auth_roles r
                ON u.role_id = r.id
            LEFT JOIN user_departments d
                ON p.department_id = d.id
            LEFT JOIN user_positions s
                ON p.position_id = s.id

            LEFT JOIN institution_qualifications_employee_records rq
                ON p.representative_qualification_id = rq.id
                AND rq.employee_id = p.id
                AND rq.deleted_at IS NULL

            WHERE p.id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return ActorHelper::enrichActorNamesRow(
            $row,
            [
                'user_created_by_name' => 'user_created_by',
                'user_updated_by_name' => 'user_updated_by',
                'password_updated_by_name' => 'password_updated_by',
                'approved_by_name' => 'approved_by',
            ]
        );
    }

    public function getByUsername(string $username): ?array
    {
        $sql = "
            SELECT
                p.*,
                u.username,
                u.email,
                u.role_id,
                u.is_active,
                u.approved
            FROM user_employees p
            INNER JOIN auth_users u
                ON p.user_id = u.id
            WHERE u.username = :username
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findActiveIdByEmployeeName(string $employeeName): ?string
    {
        $stmt = $this->db->prepare('SELECT p.id FROM user_employees p LEFT JOIN auth_users u ON p.user_id = u.id WHERE p.employee_name = :employee_name AND COALESCE(u.is_active, 1) = 1 LIMIT 1');
        $stmt->execute([':employee_name' => $employeeName]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (string) $id;
    }

    public function getActiveDropdownValues(string $field): array
    {
        if ($field !== 'employee_name') return [];
        $stmt = $this->db->query("
            SELECT DISTINCT p.employee_name AS dropdown_value
            FROM user_employees p
            LEFT JOIN auth_users u ON p.user_id = u.id
            WHERE COALESCE(u.is_active, 1) = 1
              AND p.employee_name IS NOT NULL
              AND TRIM(p.employee_name) <> ''
            ORDER BY p.employee_name ASC
        ");
        return array_values(array_unique(array_map(
            static fn(array $row): string => trim((string) ($row['dropdown_value'] ?? '')),
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        )));
    }

    public function getByEmail(string $email): ?array
    {
        $sql = "
            SELECT
                p.*,
                u.username,
                u.email,
                u.role_id,
                u.is_active,
                u.approved
            FROM user_employees p
            INNER JOIN auth_users u
                ON p.user_id = u.id
            WHERE u.email = :email
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function searchPicker(string $q = '', int $limit = 20, bool $includeInactive = false): array
    {
        $limit = max(1, min(100, (int)$limit));

        $q = trim($q);
        $keyword = '%' . $q . '%';

        $sql = "
            SELECT
                p.id,
                p.sort_no,
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

            WHERE " . ($includeInactive ? '1=1' : 'u.is_active = 1') . "
            AND (
                p.employee_name LIKE :keyword1
                OR CAST(p.sort_no AS CHAR) LIKE :keyword2
                OR COALESCE(u.username, '') LIKE :keyword3
                OR COALESCE(u.email, '') LIKE :keyword4
            )

            ORDER BY
                p.sort_no ASC,
                p.employee_name ASC,
                p.id ASC

            LIMIT {$limit}
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(':keyword1', $keyword, PDO::PARAM_STR);
        $stmt->bindValue(':keyword2', $keyword, PDO::PARAM_STR);
        $stmt->bindValue(':keyword3', $keyword, PDO::PARAM_STR);
        $stmt->bindValue(':keyword4', $keyword, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO user_employees (
                id, sort_no, user_id, employee_name,
                phone, address, address_detail,
                department_id, position_id, job_id, employment_status,
                doc_hire_date, real_hire_date,
                doc_retire_date, real_retire_date,
                rrn, rrn_image,
                emergency_phone,
                profile_image,
                bank_name,
                account_number,
                account_holder,
                bank_file,
                note, memo
            ) VALUES (
                :id, :sort_no, :user_id, :employee_name,
                :phone, :address, :address_detail,
                :department_id, :position_id, :job_id, :employment_status,
                :doc_hire_date, :real_hire_date,
                :doc_retire_date, :real_retire_date,
                :rrn, :rrn_image,
                :emergency_phone,
                :profile_image,
                :bank_name,
                :account_number,
                :account_holder,
                :bank_file,
                :note, :memo
            )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([

            'id'               => $data['id'],
            'sort_no'             => $data['sort_no'] ?? null,
            'user_id'          => $data['user_id'],
            'employee_name'    => $data['employee_name'],

            'phone'            => $data['phone'] ?? null,
            'address'          => $data['address'] ?? null,
            'address_detail'   => $data['address_detail'] ?? null,

            'department_id'    => $data['department_id'] ?? null,
            'position_id'      => $data['position_id'] ?? null,
            'job_id'           => $data['job_id'] ?? null,
            'employment_status'=> $data['employment_status'] ?? 'ACTIVE',

            'doc_hire_date'    => $data['doc_hire_date'] ?? null,
            'real_hire_date'   => $data['real_hire_date'] ?? null,
            'doc_retire_date'  => $data['doc_retire_date'] ?? null,
            'real_retire_date' => $data['real_retire_date'] ?? null,

            'rrn'              => $data['rrn'] ?? null,
            'rrn_image'        => $data['rrn_image'] ?? null,

            'emergency_phone'  => $data['emergency_phone'] ?? null,

            'profile_image'    => $data['profile_image'] ?? null,

            'bank_name'        => $data['bank_name'] ?? null,
            'account_number'   => $data['account_number'] ?? null,
            'account_holder'   => $data['account_holder'] ?? null,
            'bank_file'        => $data['bank_file'] ?? null,

            'note'             => $data['note'] ?? null,
            'memo'             => $data['memo'] ?? null
        ]);
    }

    public function updateById(string $id, array $data): bool
    {
        $sql = "
            UPDATE user_employees SET
                employee_name = :employee_name,
                phone = :phone,
                address = :address,
                address_detail = :address_detail,

                rrn = :rrn,
                rrn_image = :rrn_image,

                emergency_phone = :emergency_phone,

                profile_image = :profile_image,

                bank_name = :bank_name,
                account_number = :account_number,
                account_holder = :account_holder,
                bank_file = :bank_file,

                note = :note,
                memo = :memo

            WHERE id = :id
        ";

        $params = [

            'id'               => $id,
            'employee_name'    => trim((string)($data['employee_name'] ?? '')),

            'phone'            => $data['phone'] ?? null,
            'address'          => $data['address'] ?? null,
            'address_detail'   => $data['address_detail'] ?? null,

            'rrn'              => $data['rrn'] ?? null,
            'rrn_image'        => $data['rrn_image'] ?? null,

            'emergency_phone'  => $data['emergency_phone'] ?? null,

            'profile_image'    => $data['profile_image'] ?? null,

            'bank_name'        => $data['bank_name'] ?? null,
            'account_number'   => $data['account_number'] ?? null,
            'account_holder'   => $data['account_holder'] ?? null,
            'bank_file'        => $data['bank_file'] ?? null,
            'note'             => $data['note'] ?? null,
            'memo'             => $data['memo'] ?? null
        ];

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function updateRepresentativeQualificationId(string $id, ?string $qualificationId): bool
    {
        $stmt = $this->db->prepare('UPDATE user_employees SET representative_qualification_id = :qualification_id WHERE id = :id');
        return $stmt->execute([':id' => $id, ':qualification_id' => $qualificationId]);
    }

    public function updateStatus(string $userId, array $data): bool
    {
        $sql = "
            UPDATE auth_users SET
                is_active  = :is_active,
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE id = :id
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'is_active'  => $data['is_active'],
            'updated_by' => $data['updated_by'],
            'id'         => $userId
        ]);
    }

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
    
    public function updateSortNo(string $id, int $sortNo): bool
    {
        $sql = "UPDATE user_employees SET sort_no = :sort_no WHERE id = :id";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'sort_no' => (int)$sortNo,
            'id'      => $id
        ]);
    }

}
