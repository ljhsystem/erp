<?php
// 경로: PROJECT_ROOT . '/app/Models/Dashboard/CalendarTaskModel.php'
namespace App\Models\Dashboard;

use PDO;
use Core\Database;

class CalendarTaskModel
{
    private string $table = 'dashboard_calendar_tasks';

    // PDO 보관
    private PDO $db;

    // 생성자 – 외부에서 PDO 주입 또는 자동 연결
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function upsert(array $data): void
    {
        $dueRaw = $data['due'] ?? null;
    
        $existing = $this->findAnyByUid(
            $data['uid'],
            $data['synology_login_id']
        );
    
        $dueYmd = $dueRaw
            ? substr(preg_replace('/[^0-9]/', '', (string)$dueRaw), 0, 8)
            : ($existing['due_ymd'] ?? null);
    
        $sql = "
            INSERT INTO dashboard_calendar_tasks (
                uid, calendar_id, owner_user_id, synology_login_id,
                href, collection_href, etag,
                title, description,
                due, due_ymd,
                status, percent_complete, completed,
                raw_json, is_active,
                synology_exists,
                created_by, updated_by
            ) VALUES (
                :uid, :calendar_id, :owner_user_id, :synology_login_id,
                :href, :collection_href, :etag,
                :title, :description,
                :due, :due_ymd,
                :status, :percent_complete, :completed,
                :raw_json, :is_active,
                :synology_exists,
                :created_by, :updated_by
            )
            ON DUPLICATE KEY UPDATE
                owner_user_id = VALUES(owner_user_id),
                synology_login_id = VALUES(synology_login_id),
                href = VALUES(href),
                collection_href = VALUES(collection_href),
                etag = VALUES(etag),
                title = VALUES(title),
                description = VALUES(description),
                due = VALUES(due),
                due_ymd = VALUES(due_ymd),
                status = VALUES(status),
                percent_complete = VALUES(percent_complete),
                completed = VALUES(completed),
                raw_json = VALUES(raw_json),
                synology_exists = VALUES(synology_exists),
                updated_by = VALUES(updated_by);
        ";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':uid'              => $data['uid'],
            ':calendar_id'      => $data['calendar_id'],
            ':owner_user_id'    => $data['owner_user_id'],
            ':synology_login_id' => $data['synology_login_id'],
            ':href'             => $data['href'],
            ':collection_href'  => $data['collection_href'],
            ':etag'             => $data['etag'] ?? null,
            ':title'            => $data['title'] ?? null,
            ':description'      => $data['description'] ?? null,
            ':due'              => $dueRaw,
            ':due_ymd'          => $dueYmd,
            ':status'           => $data['status'] ?? null,
            ':percent_complete' => isset($data['percent_complete'])
                                    ? (int)$data['percent_complete']
                                    : null,
            ':completed'        => !empty($data['completed']) ? 1 : 0,
            ':raw_json'         => $data['raw_json'] ?? '{}',
            ':is_active'        => isset($data['is_active'])
                                    ? (int)$data['is_active']
                                    : 1,
            ':synology_exists' => 1,
            ':created_by'       => $data['created_by'] ?? null,
            ':updated_by'       => $data['updated_by'] ?? null,
        ]);
    }


    public function markInactive(
        string $uid,
        string $calendarId,
        string $synologyLoginId,
        ?string $actor = null
    ): void {
    
        $sql = "
            UPDATE {$this->table}
            SET is_active = 0,
                deleted_at = NOW(),
                deleted_by = :actor
            WHERE uid = :uid
              AND calendar_id = :calendar_id
              AND synology_login_id = :synology_login_id
              AND is_active = 1
        ";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':uid' => $uid,
            ':calendar_id' => $calendarId,
            ':synology_login_id' => $synologyLoginId,
            ':actor' => $actor,
        ]);
    }
    

    public function getCalendarIdsByUid(
        string $uid,
        string $synologyLoginId
    ): array {
    
        $sql = "
            SELECT calendar_id
            FROM {$this->table}
            WHERE uid = :uid
              AND synology_login_id = :synology_login_id
        ";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':uid' => $uid,
            ':synology_login_id' => $synologyLoginId
        ]);
    
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    

    public function findByUidAndCalendar(
        string $uid,
        string $calendarId,
        string $synologyLoginId
    ): ?array {
    
        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE uid = :uid
              AND calendar_id = :calendar_id
              AND synology_login_id = :synology_login_id
            LIMIT 1
        ";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':uid' => $uid,
            ':calendar_id' => $calendarId,
            ':synology_login_id' => $synologyLoginId
        ]);
    
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getActiveUidsByCalendar(
        string $calendarId,
        string $synologyLoginId
    ): array {
    
        $sql = "
            SELECT uid
            FROM {$this->table}
            WHERE calendar_id = :calendar_id
              AND synology_login_id = :synology_login_id
              AND is_active = 1
        ";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':calendar_id' => $calendarId,
            ':synology_login_id' => $synologyLoginId
        ]);
    
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }


    public function findAnyByUid(
        string $uid,
        string $synologyLoginId
    ): ?array {
    
        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE uid = :uid
              AND synology_login_id = :synology_login_id
            ORDER BY updated_at DESC
            LIMIT 1
        ";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':uid' => $uid,
            ':synology_login_id' => $synologyLoginId
        ]);
    
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    public function findAnyByUidUnsafe(string $uid): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM dashboard_calendar_tasks
            WHERE uid = :uid
            LIMIT 1
        ");
        $stmt->execute([':uid' => $uid]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function markSynologyMissing(
        string $uid,
        string $calendarId,
        string $synologyLoginId
    ): void {
    
        $stmt = $this->db->prepare("
            UPDATE dashboard_calendar_tasks
            SET synology_exists = 0
            WHERE uid = :uid
            AND calendar_id = :calendar
            AND synology_login_id = :login
        ");
    
        $stmt->execute([
            ':uid' => $uid,
            ':calendar' => $calendarId,
            ':login' => $synologyLoginId
        ]);
    }
}
