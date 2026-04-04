<?php
// 경로: PROJECT_ROOT/app/Models/User/ApprovalTemplateStepModel.php
namespace App\Models\User;

use PDO;

class ApprovalTemplateStepModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ============================================================
     * 📌 단건 조회
     * ============================================================ */
    public function getById(string $id): ?array
    {
        $stmt = $this->pdo->prepare("
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
        $stmt = $this->pdo->prepare("
            SELECT 
                s.*,
                r.role_name,
                r.role_key,
                u.employee_name AS specific_employee_name,
                au.username     AS specific_username
            FROM user_approval_template_steps s
            LEFT JOIN auth_roles    r  ON r.id  = s.role_id
            LEFT JOIN auth_users    au ON au.id = s.approver_id
            LEFT JOIN user_employees u  ON u.user_id = au.id
            WHERE s.template_id = ?
            ORDER BY s.sequence ASC
        ");
        $stmt->execute([$templateId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* ============================================================
     * 📌 다음 sequence 번호 조회
     * ============================================================ */
    public function getNextSequence(string $templateId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(MAX(sequence), 0) + 1
            FROM user_approval_template_steps
            WHERE template_id = ?
        ");
        $stmt->execute([$templateId]);
        return (int)$stmt->fetchColumn();
    }

    /* ============================================================
     * 📌 생성(Create)
     * ※ UUID 및 sequence는 Service에서 미리 계산하여 전달한다
     * ============================================================ */
    public function create(array $data): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO user_approval_template_steps
            (id, template_id, sequence, step_name, role_id, approver_id, is_active, created_by, created_at)
            VALUES
            (:id, :template_id, :sequence, :step_name, :role_id, :approver_id, :is_active, :created_by, NOW())
        ");

        return $stmt->execute([
            ':id'          => $data['id'],
            ':template_id' => $data['template_id'],
            ':sequence'    => $data['sequence'],
            ':step_name'   => $data['step_name'],
            ':role_id'     => $data['role_id'],
            ':approver_id' => $data['approver_id'],
            ':is_active'   => $data['is_active'],
            ':created_by'  => $data['created_by'],
        ]);
    }

    /* ============================================================
     * 📌 수정(Update)
     * ============================================================ */
    public function update(string $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE user_approval_template_steps
            SET 
                template_id = :template_id,
                sequence    = :sequence,
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
            ':sequence'    => $data['sequence'],
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
        $stmt = $this->pdo->prepare("
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

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() > 0;
    }
}
