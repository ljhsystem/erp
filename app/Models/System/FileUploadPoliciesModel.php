<?php
namespace App\Models\System;

use PDO;
use Core\Database;

class FileUploadPoliciesModel
{

    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getAll(): array
    {
        $sql = "
            SELECT *
            FROM system_file_upload_policies
            ORDER BY id ASC
        ";

        return $this->db
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getActive(): array
    {
        $sql = "
            SELECT *
            FROM system_file_upload_policies
            WHERE is_active = 1
            ORDER BY id ASC
        ";

        return $this->db
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByKey(string $policyKey): ?array
    {
        $sql = "
            SELECT *
            FROM system_file_upload_policies
            WHERE policy_key = :policy_key
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':policy_key' => $policyKey
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO system_file_upload_policies
            (
                id,
                policy_key,
                policy_name,
                bucket,
                allowed_ext,
                allowed_mime,
                max_size_mb,
                is_active,
                description,
                created_by
            )
            VALUES
            (
                :id,
                :policy_key,
                :policy_name,
                :bucket,
                :allowed_ext,
                :allowed_mime,
                :max_size_mb,
                :is_active,
                :description,
                :created_by
            )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id'           => $data['id'],
            ':policy_key'   => $data['policy_key'],
            ':policy_name'  => $data['policy_name'],
            ':bucket'       => $data['bucket'],
            ':allowed_ext'  => $data['allowed_ext'],
            ':allowed_mime' => $data['allowed_mime'] ?? null,
            ':max_size_mb'  => $data['max_size_mb'],
            ':is_active'    => $data['is_active'] ?? 1,
            ':description'  => $data['description'] ?? null,
            ':created_by'   => $data['created_by'],
        ]);
    }

    public function update(string $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE system_file_upload_policies
            SET
                policy_name  = :policy_name,
                bucket       = :bucket,
                allowed_ext  = :allowed_ext,
                allowed_mime = :allowed_mime,
                max_size_mb  = :max_size_mb,
                is_active    = :is_active,
                description  = :description,
                updated_by   = :updated_by,
                updated_at   = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id'           => $id,
            ':policy_name'  => $data['policy_name'],
            ':bucket'       => $data['bucket'],
            ':allowed_ext'  => $data['allowed_ext'],
            ':allowed_mime' => $data['allowed_mime'] ?? null,
            ':max_size_mb'  => $data['max_size_mb'],
            ':is_active'    => $data['is_active'],
            ':description'  => $data['description'] ?? null,
            ':updated_by'   => $data['updated_by'],
        ]);
    }

    public function setActive(string $id, bool $active, string $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE system_file_upload_policies
            SET
                is_active = :is_active,
                updated_by = :updated_by,
                updated_at = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id'         => $id,
            ':is_active'  => $active ? 1 : 0,
            ':updated_by' => $userId
        ]);
    }

    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM system_file_upload_policies
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id' => $id
        ]);
    }


}
