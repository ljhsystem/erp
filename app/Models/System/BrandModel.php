<?php
namespace App\Models\System;

use Core\Helpers\ActorHelper;
use PDO;
use Core\Database;

class BrandModel
{

    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getList(array $filters = []): array
    {
        $sql = "
            SELECT
                b.*,
                b.created_by AS created_by_name,
                b.updated_by AS updated_by_name

            FROM system_brand_assets b

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

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return ActorHelper::enrichActorNames($rows, [
            'created_by_name' => 'created_by_name',
            'updated_by_name' => 'updated_by_name',
        ]);
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                b.*,
                b.created_by AS created_by_name,
                b.updated_by AS updated_by_name

            FROM system_brand_assets b

            WHERE b.id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $id
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return ActorHelper::enrichActorNamesRow($row, [
            'created_by_name' => 'created_by_name',
            'updated_by_name' => 'updated_by_name',
        ]);
    }

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

    public function getLatestByType(string $assetType, ?string $excludeId = null): ?array
    {
        $sql = "
            SELECT *
            FROM system_brand_assets
            WHERE asset_type = :asset_type
        ";

        $params = [
            ':asset_type' => $assetType,
        ];

        if ($excludeId) {
            $sql .= " AND id <> :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= " ORDER BY created_at DESC LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

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

    public function hardDeleteById(string $id): bool
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
