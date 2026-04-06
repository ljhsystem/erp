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
     * 모든 자산 타입 조회
     * ========================================================= */
    public function getList(array $filters = []): array
    {
        $sql = "
            SELECT
                b.*,

                CASE 
                    WHEN b.created_by LIKE 'SYSTEM:%' THEN b.created_by
                    WHEN p1.employee_name IS NOT NULL THEN CONCAT('USER:', p1.employee_name)
                    ELSE b.created_by
                END AS created_by_name,

                CASE 
                    WHEN b.updated_by LIKE 'SYSTEM:%' THEN b.updated_by
                    WHEN p2.employee_name IS NOT NULL THEN CONCAT('USER:', p2.employee_name)
                    ELSE b.updated_by
                END AS updated_by_name

            FROM system_brand_assets b

            LEFT JOIN user_employees p1
                ON b.created_by NOT LIKE 'SYSTEM:%'
            AND p1.user_id = REPLACE(b.created_by, 'USER:', '')

            LEFT JOIN user_employees p2
                ON b.updated_by NOT LIKE 'SYSTEM:%'
            AND p2.user_id = REPLACE(b.updated_by, 'USER:', '')

            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['asset_type'])) {
            $sql .= " AND b.asset_type = :asset_type";
            $params[':asset_type'] = $filters['asset_type'];
        }

        if (isset($filters['is_active'])) {
            $sql .= " AND b.is_active = :is_active";
            $params[':is_active'] = $filters['is_active'];
        }

        $sql .= " ORDER BY b.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* =========================================================
     * 단건 조회 (ID 기준)
     * ========================================================= */
    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                b.*,

                CASE 
                    WHEN b.created_by LIKE 'SYSTEM:%' THEN b.created_by
                    WHEN p1.employee_name IS NOT NULL THEN CONCAT('USER:', p1.employee_name)
                    ELSE b.created_by
                END AS created_by_name,

                CASE 
                    WHEN b.updated_by LIKE 'SYSTEM:%' THEN b.updated_by
                    WHEN p2.employee_name IS NOT NULL THEN CONCAT('USER:', p2.employee_name)
                    ELSE b.updated_by
                END AS updated_by_name

            FROM system_brand_assets b

            LEFT JOIN user_employees p1
                ON b.created_by NOT LIKE 'SYSTEM:%'
            AND p1.user_id = REPLACE(b.created_by, 'USER:', '')

            LEFT JOIN user_employees p2
                ON b.updated_by NOT LIKE 'SYSTEM:%'
            AND p2.user_id = REPLACE(b.updated_by, 'USER:', '')

            WHERE b.id = :id
            LIMIT 1
        ");
    
        $stmt->execute([
            ':id' => $id
        ]);
    
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }


  /* =========================================================
     * 활성 브랜드 자산 조회 (타입별 1건)
     * ========================================================= */
    public function getActiveByType(string $assetType): ?array
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
     * 신규 브랜드 자산 등록
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
     * 자산 삭제 (DB만)
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


    public function updateStatusById(string $id, int $isActive, string $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE system_brand_assets
            SET is_active = :is_active,
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id'         => $id,
            ':is_active'  => $isActive,
            ':updated_by' => $userId
        ]);
    }
    public function deactivateByAssetType(string $assetType, string $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE system_brand_assets
            SET is_active = 0,
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE asset_type = :asset_type
              AND is_active = 1
        ");
    
        return $stmt->execute([
            ':asset_type' => $assetType,
            ':updated_by' => $userId
        ]);
    }


}