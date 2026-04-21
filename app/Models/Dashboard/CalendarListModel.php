<?php

namespace App\Models\Dashboard;

use Core\Database;
use PDO;

class CalendarListModel
{
    private string $table = 'dashboard_calendar_list';
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

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
            ':owner' => $ownerId,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByIdAndLogin(string $calendarId, string $synologyLoginId): ?array
    {
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
            ':id' => $calendarId,
            ':login' => $synologyLoginId,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getActiveCalendarList(): array
    {
        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE is_active = 1
            ORDER BY is_personal DESC, name ASC
        ";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function upsert(array $cal, ?string $actor = null): void
    {
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
                owner_user_id = VALUES(owner_user_id),
                synology_login_id = VALUES(synology_login_id),
                is_personal = VALUES(is_personal),
                allowed_users_json = VALUES(allowed_users_json),
                write_users_json = VALUES(write_users_json),
                is_active = 1,
                synced_at = NOW(),
                updated_by = VALUES(updated_by)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $cal['id'],
            ':synology_owner_principal' => $cal['synology_owner_principal'] ?? null,
            ':synology_login_principal' => $cal['synology_login_principal'] ?? null,
            ':name' => $cal['name'],
            ':href' => $cal['href'],
            ':type' => $cal['type'],
            ':calendar_color' => $cal['calendar_color'] ?? '#999999',
            ':owner_user_id' => $cal['owner_user_id'] ?? null,
            ':synology_login_id' => $cal['synology_login_id'] ?? null,
            ':is_personal' => (int) ($cal['is_personal'] ?? 0),
            ':allowed_users_json' => $cal['allowed_users_json'] ?? null,
            ':write_users_json' => $cal['write_users_json'] ?? null,
            ':created_by' => $actor,
            ':updated_by' => $actor,
        ]);
    }

    public function markVisibilityInactiveMissing(array $activeIds, string $synologyLoginId): void
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

    public function updateAdminColor(
        string $calendarId,
        string $synologyLoginId,
        string $color,
        ?string $actor = null
    ): void {
        $sql = "
            UPDATE {$this->table}
            SET admin_calendar_color = :color,
                updated_by = :actor
            WHERE id = :id
              AND synology_login_id = :synology_login_id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':color' => $color,
            ':actor' => $actor,
            ':id' => $calendarId,
            ':synology_login_id' => $synologyLoginId,
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
