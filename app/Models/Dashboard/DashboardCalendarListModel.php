<?php
// 경로: PROJECT_ROOT . '/app/models/Dashboard/DashboardCalendarListModel.php'
namespace App\Models\Dashboard;

use PDO;

class DashboardCalendarListModel
{
    private PDO $db;
    private string $table = 'dashboard_calendar_list';

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    /* =========================================================
     * 🔎 조회
     * ========================================================= */

    /**
     * 특정 소유자 기준 활성 캘린더 조회 (기존 유지)
     */
    public function getActiveListByOwner(string $ownerId): array
    {
        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE is_active = 1
            AND owner_user_id = :owner
            ORDER BY is_personal DESC, name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':owner' => $ownerId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ID + owner 기준 단건 조회 (기존 유지)
     */
    public function findByIdAndOwner(string $calendarId, string $ownerId): ?array
    {
        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE id = :id
            AND owner_user_id = :owner
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $calendarId,
            ':owner' => $ownerId
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * ID + Synology Login 기준 단일 조회 (정상 구조)
     */
    public function findByIdAndLogin(
        string $calendarId,
        string $synologyLoginId
    ): ?array {
    
        $sql = "
            SELECT l.*
            FROM {$this->table} l
            JOIN dashboard_calendar_visibility v
              ON l.id = v.calendar_id
            WHERE l.id = :id
              AND v.synology_login_id = :login
              AND v.is_visible = 1
            LIMIT 1
        ";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id'    => $calendarId,
            ':login' => $synologyLoginId,
        ]);
    
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * 전체 활성 캘린더 목록 (기존 유지)
     */
    public function getActiveCalendarList(): array
    {
        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE is_active = 1
            ORDER BY is_personal DESC, name ASC
        ";

        return $this->db
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =========================================================
     * 🔐 권한 판단
     * ========================================================= */

    /**
     * 조회 가능 여부 판단
     */
    public function canRead(
        array $calendar,
        string $erpUserId,
        string $currentSynologyLoginId
    ): bool {
    
        // 🔹 개인 캘린더 판단 (principal 기준)
        if (
            !empty($calendar['synology_owner_principal']) &&
            !empty($calendar['synology_login_principal']) &&
            $calendar['synology_owner_principal'] ===
            $calendar['synology_login_principal']
        ) {
            // 개인이면 로그인 계정 일치해야만 읽기 가능
            return $calendar['synology_login_id'] === $currentSynologyLoginId;
        }
    
        // 🔹 공유 캘린더
        $allowed = json_decode($calendar['allowed_users_json'] ?? '[]', true);
    
        if (empty($allowed)) {
            return false;
        }
    
        return in_array($erpUserId, $allowed, true);
    }

    /**
     * 수정 가능 여부 판단
     */
    public function canWrite(
        array $calendar,
        string $erpUserId,
        string $currentSynologyLoginId
    ): bool {
    
        // 🔹 개인 캘린더 (principal 기준)
        if (
            !empty($calendar['synology_owner_principal']) &&
            !empty($calendar['synology_login_principal']) &&
            $calendar['synology_owner_principal'] ===
            $calendar['synology_login_principal']
        ) {
            return $calendar['synology_login_id'] === $currentSynologyLoginId;
        }
    
        // 🔹 공유 캘린더
        $writers = json_decode($calendar['write_users_json'] ?? '[]', true);
    
        if (empty($writers)) {
            return false;
        }
    
        return in_array($erpUserId, $writers, true);
    }

    /* =========================================================
     * 💾 저장 / 동기화 (UPSERT)
     * ========================================================= */

    public function upsert(array $cal, ?string $actor = null): void
    {
        $calendarColor = $cal['calendar_color'] ?? '#999999';

        // 🔥 normalize
        $calendarColor = strtolower(trim($calendarColor));
        
        // 🔥 검증
        if (!preg_match('/^#[0-9a-f]{6}$/', $calendarColor)) {
            $calendarColor = '#999999';
        }

        $sql = "
        INSERT INTO {$this->table} (
            id,
            synology_owner_principal,
            synology_login_principal,
            name,
            href,
            type,
            calendar_color,
            admin_calendar_color,
            is_active,
            synced_at,
            owner_user_id,
            synology_login_id,
            is_personal,
            allowed_users_json,
            write_users_json,
            created_by,
            updated_by
        ) VALUES (
            :id,
            :synology_owner_principal,
            :synology_login_principal,
            :name,
            :href,
            :type,
            :calendar_color,
            NULL,
            1,
            NOW(),
            :owner_user_id,
            :synology_login_id,
            :is_personal,
            :allowed_users_json,
            :write_users_json,
            :created_by,
            :updated_by
        )
        ON DUPLICATE KEY UPDATE
            synology_owner_principal = VALUES(synology_owner_principal),
            synology_login_principal = VALUES(synology_login_principal),
            name = VALUES(name),
            href = VALUES(href),
            type = VALUES(type),
            calendar_color = VALUES(calendar_color),
            admin_calendar_color =
                IF(admin_calendar_color IS NULL OR admin_calendar_color = '',
                   VALUES(calendar_color),
                   admin_calendar_color),
            owner_user_id       = VALUES(owner_user_id),
            synology_login_id = VALUES(synology_login_id),
            is_personal         = VALUES(is_personal),
            allowed_users_json  = VALUES(allowed_users_json),
            write_users_json    = VALUES(write_users_json),
            is_active           = 1,
            synced_at           = NOW(),
            updated_by          = VALUES(updated_by)
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':id'                 => $cal['id'],
            ':synology_owner_principal' => $cal['synology_owner_principal'] ?? null,
            ':synology_login_principal' => $cal['synology_login_principal'] ?? null,
            ':name'               => $cal['name'],
            ':href'               => $cal['href'],
            ':type'               => $cal['type'],
            ':calendar_color'     => $calendarColor,
            ':owner_user_id'      => $cal['owner_user_id'] ?? null,
            ':synology_login_id' => $cal['synology_login_id'] ?? null,
            ':is_personal'        => (int)$cal['is_personal'],
            ':allowed_users_json' => $cal['allowed_users_json'] ?? null,
            ':write_users_json'   => $cal['write_users_json'] ?? null,
            ':created_by'         => $actor,
            ':updated_by'         => $actor,
        ]);
    }

    /* =========================================================
     * 🗑️ 비활성 처리
     * ========================================================= */

     public function markVisibilityInactiveMissing(
        array $activeIds,
        string $synologyLoginId
    ): void
    {
        if (empty($activeIds)) {
            return;
        }
    
        $placeholders = implode(',', array_fill(0, count($activeIds), '?'));
    
        $sql = "
            UPDATE dashboard_calendar_visibility
            SET is_visible = 0,
                updated_at = NOW()
            WHERE calendar_id NOT IN ({$placeholders})
              AND synology_login_id = ?
              AND is_visible = 1
        ";
    
        $params = array_merge($activeIds, [$synologyLoginId]);
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    /* =========================================================
     * 🎨 관리자 색상 변경 (기존 유지)
     * ========================================================= */

     public function updateAdminColor(
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
            SET admin_calendar_color = :color,
                updated_by = :actor
            WHERE id = :id
              AND synology_login_id = :synology_login_id
        ";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':color' => $color, // 🔥 더 이상 strtoupper 안씀
            ':actor' => $actor,
            ':id'    => $calendarId,
            ':synology_login_id' => $synologyLoginId
        ]);
    }

    public function getActiveListBySynology(string $synologyLoginId): array
    {
        $sql = "
            SELECT l.*
            FROM {$this->table} l
            JOIN dashboard_calendar_visibility v
              ON l.id = v.calendar_id
            WHERE l.is_active = 1
              AND v.synology_login_id = :login
              AND v.is_visible = 1
            ORDER BY l.is_personal DESC, l.name ASC
        ";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':login' => $synologyLoginId]);
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}