<?php
namespace App\Models\System;

use PDO;
use Core\Database;

class SettingConfigModel
{

    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function get(string $key, $default = null)
    {
        static $cache = [];

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $stmt = $this->db->prepare("
            SELECT config_value
            FROM system_settings_config
            WHERE config_key = :key
            LIMIT 1
        ");
        $stmt->execute(['key' => $key]);

        $value = $stmt->fetchColumn();

        if ($value !== false) {
            return $cache[$key] = $value;
        }

        return $cache[$key] = $default;
    }

    public function getByCategory(string $category): array
    {
        $stmt = $this->db->prepare("
            SELECT
                config_key,
                config_value,
                description,
                is_editable,
                updated_at
            FROM system_settings_config
            WHERE category = :category
            ORDER BY config_key ASC
        ");
        $stmt->execute(['category' => $category]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT
                config_key,
                config_value,
                category,
                description,
                is_editable,
                updated_at
            FROM system_settings_config
            ORDER BY category ASC, config_key ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function set(array $data): bool
    {
        $sql = "
            INSERT INTO system_settings_config (
                config_key,
                config_value,
                category,
                description,
                is_editable,
                created_by,
                updated_by
            ) VALUES (
                :config_key,
                :config_value,
                :category,
                :description,
                :is_editable,
                :created_by,
                :updated_by
            )
            ON DUPLICATE KEY UPDATE
                config_value = VALUES(config_value),
                category     = VALUES(category),
                description  = VALUES(description),
                is_editable  = VALUES(is_editable),
                updated_by   = VALUES(updated_by),
                updated_at   = NOW()
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'config_key'   => $data['config_key'],
            'config_value' => $data['config_value'],
            'category'     => $data['category'],
            'description'  => $data['description'] ?? null,
            'is_editable'  => $data['is_editable'] ?? 1,
            'created_by'   => $data['user_id'],
            'updated_by'   => $data['user_id'],
        ]);
    }

    public function delete(string $key): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM system_settings_config
            WHERE config_key = :key
        ");
        $stmt->execute(['key' => $key]);

        return $stmt->rowCount() > 0;
    }

    public function isEditable(string $key): bool
    {
        $stmt = $this->db->prepare("
            SELECT is_editable
            FROM system_settings_config
            WHERE config_key = :key
            LIMIT 1
        ");
        $stmt->execute(['key' => $key]);

        $value = $stmt->fetchColumn();

        if ($value === false) {
            return true;
        }

        return (bool)$value;
    }

    public function exists(string $key): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM system_settings_config
            WHERE config_key = :key
            LIMIT 1
        ");
        $stmt->execute(['key' => $key]);

        return (bool)$stmt->fetchColumn();
    }


}
