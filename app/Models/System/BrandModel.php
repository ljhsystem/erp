<?php
// 경로: PROJECT_ROOT/app/Models/System/BrandModel.php

namespace App\Models\System;

use PDO;

class BrandModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    /* =========================================================
     * 7. 모든 자산 타입 조회
     * ========================================================= */
    public function getList(array $filters = []): array
    {
        $sql = "SELECT * FROM system_brand_assets WHERE 1=1";
        $params = [];
    
        if (!empty($filters['asset_type'])) {
            $sql .= " AND asset_type = :asset_type";
            $params[':asset_type'] = $filters['asset_type'];
        }
    
        if (isset($filters['is_active'])) {
            $sql .= " AND is_active = :is_active";
            $params[':is_active'] = $filters['is_active'];
        }
    
        $sql .= " ORDER BY created_at DESC";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* =========================================================
     * 5. 단건 조회 (ID 기준)
     * ========================================================= */
    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM system_brand_assets
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([
            ':id' => $id
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // 🔥 디버깅 로그 추가
        error_log("🔍 getById 호출: ID = {$id}");
        error_log("🔍 getById 결과: " . json_encode($result));

        return $result ?: null;
    }

  /* =========================================================
     * 1. 활성 브랜드 자산 조회 (타입별 1건)
     * ========================================================= */
    public function getByType(string $assetType): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM system_brand_assets
            WHERE asset_type = :asset_type
              AND is_active = 1
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':asset_type' => $assetType
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }


    /* =========================================================
     * 3. 신규 브랜드 자산 등록
     * ========================================================= */
    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO system_brand_assets (
                id,
                asset_type,
                db_path,
                file_name,
                mime_type,
                is_active,
                created_at,
                created_by
            ) VALUES (
                :id,
                :asset_type,
                :db_path,
                :file_name,
                :mime_type,
                :is_active,
                NOW(),
                :created_by
            )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id'         => $data['id'],
            ':asset_type' => $data['asset_type'],
            ':db_path'    => $data['db_path'],
            ':file_name'  => $data['file_name'],
            ':mime_type'  => $data['mime_type'],
            ':is_active'  => $data['is_active'] ?? 1,
            ':created_by' => $data['created_by'],
        ]);
    }
  







    /* =========================================================
     * 6. 자산 삭제 (DB만)
     * ========================================================= */
    public function deleteById(string $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM system_brand_assets
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id' => $id
        ]);
    }



    /* =========================================================
     * 4. 동일 타입 기존 자산 비활성화
     * ========================================================= */
    public function deactivateByType(string $assetType, string $userId): int
    {
        $stmt = $this->db->prepare("
            UPDATE system_brand_assets
            SET
                is_active  = 0,
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE asset_type = :asset_type
              AND is_active = 1
        ");

        $stmt->execute([
            ':asset_type' => $assetType,
            ':updated_by' => $userId,
        ]);

        return $stmt->rowCount();
    }



}
    // /* =========================================================
    //  * 2. 특정 타입 전체 목록 조회 (관리용)
    //  * ========================================================= */
    // public function getAllByType(string $assetType): array
    // {
    //     $stmt = $this->db->prepare("
    //         SELECT *
    //         FROM system_brand_assets
    //         WHERE asset_type = :asset_type
    //         ORDER BY created_at DESC
    //     ");
    //     $stmt->execute([
    //         ':asset_type' => $assetType
    //     ]);

    //     return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    // }
