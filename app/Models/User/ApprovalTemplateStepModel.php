<?php
namespace App\Models\User;

use Core\Helpers\ActorHelper;
use PDO;
use Core\Database;

class ApprovalTemplateStepModel
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

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

    public function getSteps(string $templateId): array
    {
        $stmt = $this->db->prepare("
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
            ORDER BY s.sort_no ASC
        ");
        $stmt->execute([$templateId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return ActorHelper::enrichActorNames($rows, [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
        ]);
    }

    public function getActiveSteps(string $templateId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM user_approval_template_steps'
            . ' WHERE template_id = :template_id AND is_active = 1 ORDER BY sort_no'
        );
        $stmt->execute([':template_id' => $templateId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    public function lockTemplate(string $templateId): void
    {
        $stmt = $this->db->prepare('SELECT id FROM user_approval_templates WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $templateId]);
        if (!$stmt->fetchColumn()) {
            throw new \RuntimeException('결재템플릿을 찾을 수 없습니다.');
        }
    }

    public function nextSortNoForTemplate(string $templateId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(MAX(sort_no), 0) + 1 FROM user_approval_template_steps WHERE template_id = :template_id'
        );
        $stmt->execute([':template_id' => $templateId]);
        return max(1, (int) $stmt->fetchColumn());
    }

    public function getAllForTemplate(string $templateId, bool $forUpdate = false): array
    {
        $sql = 'SELECT * FROM user_approval_template_steps'
            . ' WHERE template_id = :template_id ORDER BY sort_no, id'
            . ($forUpdate ? ' FOR UPDATE' : '');
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':template_id' => $templateId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function setSortNo(string $id, string $templateId, int $sortNo, string $actor): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE user_approval_template_steps'
            . ' SET sort_no = :sort_no, updated_by = :updated_by, updated_at = NOW()'
            . ' WHERE id = :id AND template_id = :template_id'
        );
        return $stmt->execute([
            ':id' => $id,
            ':template_id' => $templateId,
            ':sort_no' => $sortNo,
            ':updated_by' => $actor,
        ]);
    }

    public function normalizeActiveExecutionTypes(string $templateId, string $actor): void
    {
        $rows = array_values(array_filter(
            $this->getAllForTemplate($templateId, true),
            static fn(array $row): bool => (int) ($row['is_active'] ?? 0) === 1
        ));
        $lastIndex = count($rows) - 1;
        foreach ($rows as $index => $row) {
            $stepType = $index === 0
                ? 'SUBMIT'
                : ($index === $lastIndex ? 'FINAL_APPROVAL' : 'APPROVAL');
            $stmt = $this->db->prepare(
                'UPDATE user_approval_template_steps
                 SET step_type = :step_type,
                     role_id = CASE WHEN :is_submit_role = 1 THEN NULL ELSE role_id END,
                     approver_id = CASE WHEN :is_submit_approver = 1 THEN NULL ELSE approver_id END,
                     updated_by = :updated_by,
                     updated_at = NOW()
                 WHERE id = :id AND template_id = :template_id'
            );
            $stmt->execute([
                ':step_type' => $stepType,
                ':is_submit_role' => $index === 0 ? 1 : 0,
                ':is_submit_approver' => $index === 0 ? 1 : 0,
                ':updated_by' => $actor,
                ':id' => $row['id'],
                ':template_id' => $templateId,
            ]);
        }
    }
    public function create(array $data): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO user_approval_template_steps
            (id, sort_no, template_id, step_name, step_type, role_id, approver_id, is_active, created_by, updated_by, created_at)
            VALUES
            (:id, :sort_no, :template_id, :step_name, :step_type, :role_id, :approver_id, :is_active, :created_by, :updated_by, NOW())
        ");

        return $stmt->execute([
            ':id'          => $data['id'],
            ':template_id' => $data['template_id'],
            ':sort_no'     => $data['sort_no'],
            ':step_name'   => $data['step_name'],
            ':step_type'   => $data['step_type'],
            ':role_id'     => $data['role_id'],
            ':approver_id' => $data['approver_id'],
            ':is_active'   => $data['is_active'],
            ':created_by'  => $data['created_by'],
            ':updated_by'  => $data['updated_by'] ?? $data['created_by'] ?? null,
        ]);
    }

    public function update(string $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE user_approval_template_steps
            SET
                template_id = :template_id,
                sort_no     = :sort_no,
                step_name   = :step_name,
                step_type   = :step_type,
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
            ':step_type'   => $data['step_type'],
            ':role_id'     => $data['role_id'],
            ':approver_id' => $data['approver_id'],
            ':is_active'   => $data['is_active'],
            ':updated_by'  => $data['updated_by'],
        ]);
    }

    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM user_approval_template_steps
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

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
