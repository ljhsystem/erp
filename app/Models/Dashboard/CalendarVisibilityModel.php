<?php
// 경로: PROJECT_ROOT . '/app/Models/Dashboard/CalendarVisibilityModel.php'
namespace App\Models\Dashboard;

use PDO;

class CalendarVisibilityModel
{
    private PDO $db;
    private string $table = 'dashboard_calendar_visibility';

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
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