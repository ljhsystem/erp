<?php
namespace App\Models\System;

use Core\Database;
use PDO;

class MenuRegistryModel
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
                menu_key,
                page_key,
                module_key,
                menu_label,
                module_order,
                menu_order,
                page_order,
                menu_icon,
                default_entry,
                is_menu,
                visible_in_sidebar,
                visible_in_settings,
                visible_in_sitemap,
                visible_in_navbar,
                is_active,
                created_at,
                updated_at
            FROM system_menu_registry
        ";

        if ($onlyActive) {
            $sql .= " WHERE is_active = 1";
        }

        $sql .= " ORDER BY module_order ASC, menu_order ASC, page_order ASC, menu_key ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getByMenuKey(string $menuKey): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                menu_key,
                page_key,
                module_key,
                menu_label,
                module_order,
                menu_order,
                page_order,
                menu_icon,
                default_entry,
                is_menu,
                visible_in_sidebar,
                visible_in_settings,
                visible_in_sitemap,
                visible_in_navbar,
                is_active,
                created_at,
                updated_at
            FROM system_menu_registry
            WHERE menu_key = ?
            LIMIT 1
        ");
        $stmt->execute([$menuKey]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getSettingsMenus(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                smr.menu_key,
                smr.page_key,
                smr.module_key,
                smr.menu_label,
                smr.module_order,
                smr.menu_order,
                smr.page_order,
                smr.menu_icon,
                smr.default_entry,
                smr.is_menu,
                smr.visible_in_sidebar,
                smr.visible_in_settings,
                smr.visible_in_sitemap,
                smr.visible_in_navbar,
                smr.is_active,
                spr.menu_label AS page_menu_label,
                spr.page_label,
                spr.default_route_key,
                spr.default_route_url,
                spr.breadcrumb
            FROM system_menu_registry smr
            INNER JOIN system_page_registry spr
                ON spr.page_key = smr.page_key
               AND spr.is_active = 1
            WHERE smr.is_active = 1
              AND smr.is_menu = 1
              AND smr.visible_in_settings = 1
              AND smr.module_key = 'settings'
              AND smr.page_key IS NOT NULL
              AND smr.page_key <> ''
            ORDER BY smr.module_order ASC, smr.menu_order ASC, smr.page_order ASC, smr.menu_key ASC
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
