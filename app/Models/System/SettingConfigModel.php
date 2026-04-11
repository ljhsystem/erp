<?php
// 경로: PROJECT_ROOT . '/app/Models/System/SettingConfigModel.php'
namespace App\Models\System;

use PDO;
use Core\Database;

class SettingConfigModel
{
    // PDO 보관
    private PDO $db;

    // 생성자 – 외부에서 PDO 주입 또는 자동 연결
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    /* =============================================================
    * 1. 단일 설정값 조회 (key 기준)
    * ============================================================= */
    // get() 함수에서 캐시를 항상 비우고 새로 값을 가져오도록 수정
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
    
    
    



    /* =============================================================
     * 2. 카테고리별 설정 조회 (SITE / SESSION / API ...)
     * ============================================================= */
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

    /* =============================================================
     * 3. 전체 설정 조회 (관리자 / 디버그용)
     * ============================================================= */
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

    /* =============================================================
     * 4. 설정 저장 또는 업데이트
     * - category / description / is_editable 포함
     * ============================================================= */
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
    
    

    /* =============================================================
     * 5. 단일 설정 삭제 (주의: 시스템 설정용)
     * ============================================================= */
    public function delete(string $key): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM system_settings_config
            WHERE config_key = :key
        ");
        $stmt->execute(['key' => $key]);

        return $stmt->rowCount() > 0;
    }

    /* =============================================================
     * 6. 수정 가능 여부 확인
     * ============================================================= */
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
    
        // 🔥 아직 존재하지 않는 설정 → 생성 허용
        if ($value === false) {
            return true;
        }
    
        return (bool)$value;
    }

    // SystemSettingConfigModel.php
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
