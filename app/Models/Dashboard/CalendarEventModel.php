<?php
// 경로: PROJECT_ROOT . '/app/Models/Dashboard/CalendarEventModel.php'
namespace App\Models\Dashboard;

use PDO;
use Core\Database;

class CalendarEventModel
{
    private string $table = 'dashboard_calendar_events';

    // PDO 보관
    private PDO $db;

    // 생성자 – 외부에서 PDO 주입 또는 자동 연결
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    /* =========================================================
     * 🔎 조회
     * ========================================================= */

    /**
     * UID + Calendar ID로 이벤트 1건 조회
     */
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

    /**
     * 특정 캘린더의 활성 이벤트 목록
     */
    public function getActiveByCalendar(
        string $calendarId,
        string $synologyLoginId
    ): array {
    
        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE calendar_id = :calendar_id
              AND synology_login_id = :synology_login_id
              AND is_active = 1
            ORDER BY dtstart_ymd ASC, dtstart ASC
        ";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':calendar_id' => $calendarId,
            ':synology_login_id' => $synologyLoginId
        ]);
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /* =========================================================
     * 💾 저장 (UPSERT)
     * ========================================================= */

    /**
     * Synology 이벤트 → 우리 DB 저장/갱신
     *  - uid + calendar_id 기준
     *  - etag 변경 시에만 갱신하는 용도로 사용
     */
    public function upsert(array $data): void
    {
        // 🔥 YMD 파생 컬럼 생성
        $dtstartRaw = $data['dtstart'] ?? null;
        $dtendRaw   = $data['dtend'] ?? null;

        $data['dtstart_ymd'] = $dtstartRaw
            ? substr(preg_replace('/[^0-9]/', '', $dtstartRaw), 0, 8)
            : null;

        $data['dtend_ymd'] = $dtendRaw
            ? substr(preg_replace('/[^0-9]/', '', $dtendRaw), 0, 8)
            : null;

        // 🔥 admin_event_color 초기 정책 적용
        if (!array_key_exists('admin_event_color', $data)) {
            unset($data['admin_event_color']);
        }

        if ($data['admin_event_color'] === null && !empty($data['event_color'])) {
            $data['admin_event_color'] = $data['event_color'];
        }

        $sql = "
            INSERT INTO {$this->table} (
                uid, calendar_id, owner_user_id, synology_login_id, href, etag,
                type, sequence, dtstamp, created, last_modified,
                title, description, event_color, admin_event_color,
                location, dtstart, dtend, dtstart_ymd, dtend_ymd, all_day,
                status, priority, transp,
                alarms_json, attendees_json, recurrence_json,
                categories_json, comments_json, attachments_json,
                raw_json,
                is_active,
                created_by, updated_by
            ) VALUES (
                :uid, :calendar_id, :owner_user_id, :synology_login_id, :href, :etag,
                :type, :sequence, :dtstamp, :created, :last_modified,
                :title, :description, :event_color, :admin_event_color,
                :location, :dtstart, :dtend, :dtstart_ymd, :dtend_ymd, :all_day,
                :status, :priority, :transp,
                :alarms_json, :attendees_json, :recurrence_json,
                :categories_json, :comments_json, :attachments_json,
                :raw_json,
                1,
                :created_by, :updated_by
            )
            ON DUPLICATE KEY UPDATE
                synology_login_id = VALUES(synology_login_id),
                href = VALUES(href),
                etag = VALUES(etag),
                type = VALUES(type),
                sequence = VALUES(sequence),
                dtstamp = VALUES(dtstamp),
                created = VALUES(created),
                last_modified = VALUES(last_modified),
                title = VALUES(title),
                description = VALUES(description),
                event_color = VALUES(event_color),
                admin_event_color = COALESCE(VALUES(admin_event_color), admin_event_color),
                location = VALUES(location),
                dtstart = VALUES(dtstart),
                dtend = VALUES(dtend),
                dtstart_ymd = VALUES(dtstart_ymd),
                dtend_ymd = VALUES(dtend_ymd),
                all_day = VALUES(all_day),
                status = VALUES(status),
                priority = VALUES(priority),
                transp = VALUES(transp),
                alarms_json = VALUES(alarms_json),
                attendees_json = VALUES(attendees_json),
                recurrence_json = VALUES(recurrence_json),
                categories_json = VALUES(categories_json),
                comments_json = VALUES(comments_json),
                attachments_json = VALUES(attachments_json),
                raw_json = VALUES(raw_json),
                is_active = 1,
                deleted_at = NULL,
                deleted_by = NULL,
                updated_by = VALUES(updated_by)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':uid' => $data['uid'] ?? null,
            ':calendar_id' => $data['calendar_id'] ?? null,
            ':owner_user_id' => $data['owner_user_id'],
            ':synology_login_id' => $data['synology_login_id'],

            ':href' => $data['href'] ?? null,
            ':etag' => $data['etag'] ?? null,
        
            ':type' => $data['type'] ?? null,
            ':sequence' => $data['sequence'] ?? null,
            ':dtstamp' => $data['dtstamp'] ?? null,
            ':created' => $data['created'] ?? null,
            ':last_modified' => $data['last_modified'] ?? null,
        
            ':title' => $data['title'] ?? null,
            ':description' => $data['description'] ?? null,
            ':event_color' => $data['event_color'] ?? null,
            ':admin_event_color' => $data['admin_event_color'] ?? null,
            ':location' => $data['location'] ?? null,
        
            ':dtstart' => $data['dtstart'] ?? null,
            ':dtend' => $data['dtend'] ?? null,
            ':dtstart_ymd' => $data['dtstart_ymd'] ?? null,
            ':dtend_ymd' => $data['dtend_ymd'] ?? null,
            ':all_day' => (int)($data['all_day'] ?? 0),
        
            ':status' => $data['status'] ?? null,
            ':priority' => $data['priority'] ?? null,
            ':transp' => $data['transp'] ?? null,
        
            ':alarms_json' => $data['alarms_json'] ?? null,
            ':attendees_json' => $data['attendees_json'] ?? null,
            ':recurrence_json' => $data['recurrence_json'] ?? null,
            ':categories_json' => $data['categories_json'] ?? null,
            ':comments_json' => $data['comments_json'] ?? null,
            ':attachments_json' => $data['attachments_json'] ?? null,
        
            ':raw_json' => $data['raw_json'] ?? null,
        
            ':created_by' => $data['actor'] ?? null,
            ':updated_by' => $data['actor'] ?? null,
        ]);
        
    }

    



    /* =========================================================
     * 🗑️ 삭제 / 비활성
     * ========================================================= */

    /**
     * Synology에서 삭제된 이벤트 → 우리 DB 비활성 처리
     */
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
            ':actor' => $actor
        ]);
    }

    /**
     * ERP에서 최종 삭제
     */
    public function deleteFinal(
        string $uid,
        string $calendarId,
        string $synologyLoginId
    ): void {
    
        $sql = "
            DELETE FROM {$this->table}
            WHERE uid = :uid
              AND calendar_id = :calendar_id
              AND synology_login_id = :synology_login_id
        ";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':uid' => $uid,
            ':calendar_id' => $calendarId,
            ':synology_login_id' => $synologyLoginId
        ]);
    }

    /* =========================================================
    * 🔎 내부 처리용 조회 (Service 보조)
    * ========================================================= */

    /**
     * 특정 캘린더의 마지막 동기화 시각
     * - TTL 판단용
     */
    public function getLastSyncedAtByCalendar(
        string $calendarId,
        string $synologyLoginId
    ): ?string {
    
        $sql = "
            SELECT MAX(updated_at) AS last_synced_at
            FROM {$this->table}
            WHERE calendar_id = :calendar_id
              AND synology_login_id = :synology_login_id
        ";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':calendar_id' => $calendarId,
            ':synology_login_id' => $synologyLoginId
        ]);
    
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['last_synced_at'] ?? null;
    }


    /**
     * 특정 캘린더의 활성 UID 목록
     * - Synology ↔ DB 비교용
     */
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

    /**
     * 활성 이벤트 기간 조회 (UI 전용)
     * - FullCalendar start/end 대응
     * - 🔥 calendar_id 명시 필수
     */
    public function getActiveByPeriod(
        ?string $from,
        ?string $to,
        array $calendarIds,
        string $synologyLoginId
    ): array {
    
        $fromYmd = $from ? (new \DateTimeImmutable($from))->format('Ymd') : null;
        $toYmd   = $to   ? (new \DateTimeImmutable($to))->format('Ymd')   : null;
    
        $sql = "SELECT * FROM {$this->table}
                WHERE is_active = 1
                AND synology_login_id = ?";
    
        $params = [$synologyLoginId];
    
        if (!empty($calendarIds)) {
            $in = implode(',', array_fill(0, count($calendarIds), '?'));
            $sql .= " AND calendar_id IN ($in)";
            $params = array_merge($params, $calendarIds);
        }
    
        if ($toYmd) {
            $sql .= " AND dtstart_ymd < ?";
            $params[] = $toYmd;
        }
    
        if ($fromYmd) {
            $sql .= " AND (dtend_ymd IS NULL OR dtend_ymd = '' OR dtend_ymd > ?)";
            $params[] = $fromYmd;
        }
    
        $sql .= " ORDER BY dtstart_ymd ASC, dtstart ASC";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    


    /* =========================================================
    * 🎨 캘린더 색상 관리 (DB 기준)
    * ========================================================= */

    /**
     * 특정 캘린더의 색상 변경
     * - 해당 캘린더에 속한 모든 활성 이벤트에 동일 색 적용
     * - UI / FullCalendar / API 단일 진실
     */
    public function updateCalendarColor(
        string $calendarId,
        string $synologyLoginId,
        string $color,
        ?string $actor = null
    ): void {
    
        // 🔥 normalize
        $color = strtolower(trim($color));
    
        // 🔥 6자리 HEX만 허용
        if (!preg_match('/^#[0-9a-f]{6}$/', $color)) {
            throw new \InvalidArgumentException('Invalid color format');
        }
    
        $sql = "
            UPDATE {$this->table}
            SET admin_event_color = :color,
                updated_at = NOW(),
                updated_by = :actor
            WHERE calendar_id = :calendar_id
              AND synology_login_id = :synology_login_id
              AND is_active = 1
        ";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':color' => $color, // 🔥 더 이상 strtoupper 안씀
            ':calendar_id' => $calendarId,
            ':synology_login_id' => $synologyLoginId,
            ':actor' => $actor,
        ]);
    }

    /**
     * 캘린더별 현재 색상 조회
     * - 사이드바 목록용
     */
    public function getCalendarEventColor(
        string $calendarId,
        string $synologyLoginId
    ): ?string
    {
        $sql = "
            SELECT admin_event_color
            FROM {$this->table}
            WHERE calendar_id = :calendar_id
              AND synology_login_id = :synology_login_id
              AND is_active = 1
              AND admin_event_color IS NOT NULL
            LIMIT 1
        ";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':calendar_id' => $calendarId,
            ':synology_login_id' => $synologyLoginId
        ]);
    
        return $stmt->fetchColumn() ?: null;
    }


    // DashboardCalendarEventModel.php
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


    public function findAnyByUid(string $uid, string $synologyLoginId): ?array
    {
        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE uid = :uid
              AND synology_login_id = :synology_login_id
            ORDER BY created_at DESC
            LIMIT 1
        ";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':uid' => $uid,
            ':synology_login_id' => $synologyLoginId
        ]);
    
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    


    public function updateById(int $id, array $payload): void
    {
        $sql = "
            UPDATE dashboard_calendar_events
            SET
                uid = :uid,
                href = :href,
                etag = :etag,
                title = :title,
                description = :description,
                location = :location,
                dtstart = :dtstart,
                dtend = :dtend,
                all_day = :all_day,
    
                -- 🔥 핵심 수정 (색 보호)
                admin_event_color = COALESCE(:admin_event_color, admin_event_color),
    
                raw_json = :raw_json,
                updated_at = NOW(),
                is_active = 1
            WHERE id = :id
        ";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id'                => $id,
            'uid'               => $payload['uid'] ?? null,
            'href'              => $payload['href'] ?? null,
            'etag'              => $payload['etag'] ?? null,
            'title'             => $payload['title'] ?? null,
            'description'       => $payload['description'] ?? null,
            'location'          => $payload['location'] ?? null,
            'dtstart'           => $payload['dtstart'] ?? null,
            'dtend'             => $payload['dtend'] ?? null,
            'all_day'           => $payload['all_day'] ?? 0,
    
            // 🔥 바인딩 추가
            'admin_event_color' => $payload['admin_event_color'] ?? null,
    
            'raw_json'          => $payload['raw_json'] ?? null,
        ]);
    }


    public function markSynologyMissing(
        string $uid,
        string $calendarId,
        string $synologyLoginId
    ): void {
    
        $stmt = $this->db->prepare("
            UPDATE dashboard_calendar_events
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
