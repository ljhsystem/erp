<?php
// 경로: PROJECT_ROOT/app/Models/User/ApprovalTemplateStepModel.php
namespace App\Models\User;

use PDO;
use Core\Database;

class ApprovalTemplateStepModel
{
    // PDO 보관
    private PDO $db;

    // 생성자 – 외부에서 PDO 주입 또는 자동 연결
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    /* ============================================================
     * 📌 단건 조회
     * ============================================================ */
    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM user_approval_template_steps
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* ============================================================
     * 📌 템플릿별 스텝 전체 조회 (JOIN 포함)
     * ============================================================ */
    public function getSteps(string $templateId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                s.*,
                r.role_name,
                r.role_key,
                u.employee_name AS specific_employee_name,
                au.username     AS specific_username,
                CASE
                    WHEN s.created_by IS NULL THEN NULL
                    WHEN s.created_by LIKE 'SYSTEM:%' THEN s.created_by
                    WHEN cu.employee_name IS NOT NULL THEN CONCAT('USER:', cu.employee_name)
                    ELSE s.created_by
                END AS created_by_name,
                CASE
                    WHEN s.updated_by IS NULL THEN NULL
                    WHEN s.updated_by LIKE 'SYSTEM:%' THEN s.updated_by
                    WHEN uu.employee_name IS NOT NULL THEN CONCAT('USER:', uu.employee_name)
                    ELSE s.updated_by
                END AS updated_by_name
            FROM user_approval_template_steps s
            LEFT JOIN auth_roles    r  ON r.id  = s.role_id
            LEFT JOIN auth_users    au ON au.id = s.approver_id
            LEFT JOIN user_employees u  ON u.user_id = au.id
            LEFT JOIN user_employees cu
                ON s.created_by NOT LIKE 'SYSTEM:%'
                AND cu.user_id = REPLACE(s.created_by, 'USER:', '')
            LEFT JOIN user_employees uu
                ON s.updated_by NOT LIKE 'SYSTEM:%'
                AND uu.user_id = REPLACE(s.updated_by, 'USER:', '')
            WHERE s.template_id = ?
            ORDER BY s.sort_no ASC
        ");
        $stmt->execute([$templateId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* ============================================================
     * 📌 다음 sort_no 번호 조회
     * ============================================================ */
    public function getNextSortNo(string $templateId): int
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(MAX(sort_no), 0) + 1
            FROM user_approval_template_steps
            WHERE template_id = ?
        ");
        $stmt->execute([$templateId]);
        return (int)$stmt->fetchColumn();
    }

    /* ============================================================
     * 📌 생성(Create)
     * ※ UUID 및 sort_no는 Service에서 미리 계산하여 전달한다
     * ============================================================ */
    public function create(array $data): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO user_approval_template_steps
            (id, sort_no, template_id, step_name, role_id, approver_id, is_active, created_by, updated_by, created_at)
            VALUES
            (:id, :sort_no, :template_id, :step_name, :role_id, :approver_id, :is_active, :created_by, :updated_by, NOW())
        ");

        return $stmt->execute([
            ':id'          => $data['id'],
            ':template_id' => $data['template_id'],
            ':sort_no'     => $data['sort_no'],
            ':step_name'   => $data['step_name'],
            ':role_id'     => $data['role_id'],
            ':approver_id' => $data['approver_id'],
            ':is_active'   => $data['is_active'],
            ':created_by'  => $data['created_by'],
            ':updated_by'  => $data['updated_by'] ?? $data['created_by'] ?? null,
        ]);
    }

    /* ============================================================
     * 📌 수정(Update)
     * ============================================================ */
    public function update(string $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE user_approval_template_steps
            SET 
                template_id = :template_id,
                sort_no     = :sort_no,
                step_name   = :step_name,
                role_id     = :role_id,
                approver_id = :approver_id,
                is_active   = :is_active,
                updated_by  = :updated_by,
                updated_at  = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id'          => $id,
            ':template_id' => $data['template_id'],
            ':sort_no'     => $data['sort_no'],
            ':step_name'   => $data['step_name'],
            ':role_id'     => $data['role_id'],
            ':approver_id' => $data['approver_id'],
            ':is_active'   => $data['is_active'],
            ':updated_by'  => $data['updated_by'],
        ]);
    }

    /* ============================================================
     * 📌 삭제(Delete)
     * ============================================================ */
    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM user_approval_template_steps
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    /* ============================================================
     * 📌 템플릿 내 스텝명 중복 여부 체크
     * ============================================================ */
    public function existsStepName(string $templateId, string $stepName, ?string $excludeId = null): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM user_approval_template_steps
            WHERE template_id = ?
            AND step_name = ?
        ";

        $params = [$templateId, $stepName];

        if ($excludeId) {
            $sql .= " AND id <> ?";
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() > 0;
    }
}
