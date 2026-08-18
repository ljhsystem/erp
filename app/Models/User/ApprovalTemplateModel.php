<?php

namespace App\Models\User;

use Core\Helpers\ActorHelper;
use Core\Database;
use PDO;

class ApprovalTemplateModel
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function findActiveByDocumentType(string $documentType, bool $forUpdate = false): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM user_approval_templates WHERE document_type = :document_type AND is_active = 1 ORDER BY sort_no, id' . ($forUpdate ? ' FOR UPDATE' : ''));
        $stmt->execute([':document_type' => $documentType]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (count($rows) > 1) {
            throw new \RuntimeException('동일 문서유형에 활성 결재템플릿이 여러 개 존재합니다. 관리자에게 문의해 주세요.');
        }
        return $rows[0] ?? null;
    }
    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT
                t.*
            FROM user_approval_templates t
            ORDER BY t.sort_no ASC, t.created_at DESC
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return ActorHelper::enrichActorNames($rows, [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
        ]);
    }

    public function getAllForUpdate(): array
    {
        $stmt = $this->db->query(
            'SELECT id, sort_no
               FROM user_approval_templates
              ORDER BY sort_no, id
              FOR UPDATE'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                t.*
            FROM user_approval_templates t
            WHERE t.id = ?
        ");

        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return ActorHelper::enrichActorNamesRow($row, [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
        ]);
    }

    public function templateKeyExists(string $key): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM user_approval_templates
            WHERE template_key = ?
        ");

        $stmt->execute([$key]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(string $id, string $templateKey, array $data): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO user_approval_templates
                (id, sort_no, template_key, template_name, document_type, description, is_active, created_by, updated_by)
            VALUES
                (:id, :sort_no, :template_key, :template_name, :document_type, :description, :is_active, :created_by, :updated_by)
        ");

        return $stmt->execute([
            ':id' => $id,
            ':sort_no' => $data['sort_no'],
            ':template_key' => $templateKey,
            ':template_name' => $data['template_name'] ?? '',
            ':document_type' => $data['document_type'] ?? null,
            ':description' => $data['description'] ?? null,
            ':is_active' => $data['is_active'] ?? 0,
            ':created_by' => $data['created_by'] ?? null,
            ':updated_by' => $data['updated_by'] ?? $data['created_by'] ?? null,
        ]);
    }

    public function update(string $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE user_approval_templates
            SET
                template_name = :template_name,
                document_type = :document_type,
                description = :description,
                is_active = :is_active,
                updated_by = :updated_by,
                updated_at = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':template_name' => $data['template_name'] ?? '',
            ':document_type' => $data['document_type'] ?? null,
            ':description' => $data['description'] ?? '',
            ':is_active' => $data['is_active'] ?? 1,
            ':updated_by' => $data['updated_by'] ?? null,
            ':id' => $id,
        ]);
    }

    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM user_approval_templates
            WHERE id = ?
        ");

        return $stmt->execute([$id]);
    }

    public function updateSortNo(string $id, int $sortNo, ?string $updatedBy = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE user_approval_templates
            SET sort_no = :sort_no,
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE id = :id
        ");

        return $stmt->execute([
            ':sort_no' => $sortNo,
            ':updated_by' => $updatedBy,
            ':id' => $id,
        ]);
    }

    public function existsName(string $name, string $documentType, ?string $exceptId = null): bool
    {
        if ($exceptId) {
            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM user_approval_templates
                WHERE template_name = ?
                  AND document_type = ?
                  AND id <> ?
            ");
            $stmt->execute([$name, $documentType, $exceptId]);
        } else {
            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM user_approval_templates
                WHERE template_name = ?
                  AND document_type = ?
            ");
            $stmt->execute([$name, $documentType]);
        }

        return (int) $stmt->fetchColumn() > 0;
    }

    public function activeDocumentTypeExists(string $documentType, string $exceptId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM user_approval_templates WHERE document_type = :document_type AND is_active = 1 AND id <> :id LIMIT 1');
        $stmt->execute([':document_type' => $documentType, ':id' => $exceptId]);
        return (bool) $stmt->fetchColumn();
    }

    public function dependencyCounts(string $id): array
    {
        $steps = $this->db->prepare('SELECT COUNT(*) FROM user_approval_template_steps WHERE template_id = :id');
        $requests = $this->db->prepare('SELECT COUNT(*) FROM user_approval_requests WHERE template_id = :id');
        $steps->execute([':id' => $id]);
        $requests->execute([':id' => $id]);
        return ['steps' => (int) $steps->fetchColumn(), 'requests' => (int) $requests->fetchColumn()];
    }

    public function deleteSteps(string $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM user_approval_template_steps WHERE template_id = :id');
        return $stmt->execute([':id' => $id]);
    }
}
