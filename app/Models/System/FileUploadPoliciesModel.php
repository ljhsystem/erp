<?php
// 경로: PROJECT_ROOT/app/Models/System/FileUploadPoliciesModel.php
// 설명: 파일 업로드 정책 DB 접근 전용 Model

namespace App\Models\System;

use PDO;

class FileUploadPoliciesModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* =========================================================
     * 1. 전체 정책 목록
     * ========================================================= */
    public function getAll(): array
    {
        $sql = "
            SELECT *
            FROM system_file_upload_policies
            ORDER BY id ASC
        ";

        return $this->pdo
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* =========================================================
     * 2. 활성 정책만 조회
     * ========================================================= */
    public function getActive(): array
    {
        $sql = "
            SELECT *
            FROM system_file_upload_policies
            WHERE is_active = 1
            ORDER BY id ASC
        ";

        return $this->pdo
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* =========================================================
     * 3. policy_key 기준 단건 조회
     * ========================================================= */
    public function findByKey(string $policyKey): ?array
    {
        $sql = "
            SELECT *
            FROM system_file_upload_policies
            WHERE policy_key = :policy_key
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':policy_key' => $policyKey
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /* =========================================================
     * 4. 정책 생성
     * ========================================================= */
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
    
        $stmt = $this->pdo->prepare($sql);
    
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
    

    /* =========================================================
    * 5. 정책 수정
    * ========================================================= */
    public function update(string $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
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


    /* =========================================================
    * 6. 정책 활성/비활성
    * ========================================================= */
    public function setActive(string $id, bool $active, string $userId): bool
    {
        $stmt = $this->pdo->prepare("
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
    
    

    /* =========================================================
    * 7. 정책 삭제
    * ========================================================= */
    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM system_file_upload_policies
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id' => $id
        ]);
    }


}
