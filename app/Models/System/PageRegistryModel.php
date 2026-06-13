<?php
namespace App\Models\System;

use Core\Database;
use PDO;

class PageRegistryModel
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getAll(bool $onlyActive = true): array
    {
        $sql = "
            SELECT
                page_key,
                module_key,
                module_label,
                menu_key,
                menu_label,
                page_label,
                page_description,
                breadcrumb,
                default_route_key,
                default_route_url,
                source_description,
                is_active,
                created_at,
                updated_at
            FROM system_page_registry
        ";

        $params = [];
        if ($onlyActive) {
            $sql .= " WHERE is_active = 1";
        }

        $sql .= " ORDER BY module_key ASC, menu_key ASC, page_key ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getByPageKey(string $pageKey): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                page_key,
                module_key,
                module_label,
                menu_key,
                menu_label,
                page_label,
                page_description,
                breadcrumb,
                default_route_key,
                default_route_url,
                source_description,
                is_active,
                created_at,
                updated_at
            FROM system_page_registry
            WHERE page_key = ?
            LIMIT 1
        ");
        $stmt->execute([$pageKey]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getByDefaultRouteKey(string $routeKey): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                page_key,
                module_key,
                module_label,
                menu_key,
                menu_label,
                page_label,
                page_description,
                breadcrumb,
                default_route_key,
                default_route_url,
                source_description,
                is_active,
                created_at,
                updated_at
            FROM system_page_registry
            WHERE default_route_key = ?
            LIMIT 1
        ");
        $stmt->execute([$routeKey]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
