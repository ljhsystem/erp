<?php
namespace App\Models\Main;

use PDO;
use Core\Database;

class CalendarVisibilityModel
{
    private string $table = 'main_calendar_visibility';

    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function upsert(array $data): void
    {
        $sql = "
        INSERT INTO {$this->table} (
            calendar_id,
            synology_login_id,
            owner_user_id,
            is_visible,
            last_synced_at
        ) VALUES (
            :calendar_id,
            :synology_login_id,
            :owner_user_id,
            :is_visible,
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            is_visible = VALUES(is_visible),
            last_synced_at = NOW()
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':calendar_id' => $data['calendar_id'],
            ':synology_login_id' => $data['synology_login_id'],
            ':owner_user_id' => $data['owner_user_id'],
            ':is_visible' => (int)$data['is_visible'],
        ]);
    }
}
