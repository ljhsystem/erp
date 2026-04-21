<?php

namespace App\Models\User;

use Core\Database;
use PDO;

class ApprovalTemplateModel
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM user_approval_templates
            ORDER BY created_at DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM user_approval_templates
            WHERE id = ?
        ");

        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
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
                (id, template_key, template_name, document_type, description, is_active, created_by)
            VALUES
                (:id, :template_key, :template_name, :document_type, :description, :is_active, :created_by)
        ");

        return $stmt->execute([
            ':id' => $id,
            ':template_key' => $templateKey,
            ':template_name' => $data['template_name'] ?? '',
            ':document_type' => $data['document_type'] ?? null,
            ':description' => $data['description'] ?? null,
            ':is_active' => $data['is_active'] ?? 1,
            ':created_by' => $data['created_by'] ?? null,
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
}
