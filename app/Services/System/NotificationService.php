<?php

namespace App\Services\System;

use Core\Helpers\UuidHelper;
use PDO;

class NotificationService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getNotifications(string $userId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("
            SELECT n.id,
                   n.recipient_user_id,
                   n.actor_user_id,
                   au.username AS actor_username,
                   n.action_type,
                   n.ref_table,
                   n.ref_id,
                   n.title,
                   n.message,
                   n.is_read,
                   n.read_at,
                   n.created_at
              FROM system_notifications n
              LEFT JOIN auth_users au ON au.id = n.actor_user_id
             WHERE n.recipient_user_id = :user_id
             ORDER BY n.created_at DESC
             LIMIT :limit
        ");
        $stmt->bindValue(':user_id', $userId);
        $stmt->bindValue(':limit', max(1, min($limit, 50)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function markAsRead(string $id, string $userId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE system_notifications
               SET is_read = 1,
                   read_at = COALESCE(read_at, NOW())
             WHERE id = :id
               AND recipient_user_id = :user_id
        ");

        return $stmt->execute([
            ':id' => $id,
            ':user_id' => $userId,
        ]);
    }

    public function markAllAsRead(string $userId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE system_notifications
               SET is_read = 1,
                   read_at = COALESCE(read_at, NOW())
             WHERE recipient_user_id = :user_id
               AND is_read = 0
        ");

        return $stmt->execute([':user_id' => $userId]);
    }

    public function createNotification(array $data): bool
    {
        $recipientUserId = trim((string) ($data['recipient_user_id'] ?? ''));
        $title = trim((string) ($data['title'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));
        $actionType = trim((string) ($data['action_type'] ?? ''));

        if ($recipientUserId === '' || $title === '' || $message === '' || $actionType === '') {
            return false;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO system_notifications (
                id,
                recipient_user_id,
                actor_user_id,
                action_type,
                ref_table,
                ref_id,
                title,
                message,
                is_read,
                created_at
            ) VALUES (
                :id,
                :recipient_user_id,
                :actor_user_id,
                :action_type,
                :ref_table,
                :ref_id,
                :title,
                :message,
                0,
                NOW()
            )
        ");

        return $stmt->execute([
            ':id' => $data['id'] ?? UuidHelper::generate(),
            ':recipient_user_id' => $recipientUserId,
            ':actor_user_id' => $this->nullableString($data['actor_user_id'] ?? null),
            ':action_type' => $actionType,
            ':ref_table' => $this->nullableString($data['ref_table'] ?? null),
            ':ref_id' => $this->nullableString($data['ref_id'] ?? null),
            ':title' => $title,
            ':message' => $message,
        ]);
    }

    public function getAdminUserIds(): array
    {
        $stmt = $this->pdo->query("
            SELECT u.id
              FROM auth_users u
              JOIN auth_roles r ON r.id = u.role_id
             WHERE u.approved = 1
               AND u.is_active = 1
               AND u.deleted_at IS NULL
               AND r.role_key IN ('super_admin', 'admin')
             ORDER BY FIELD(r.role_key, 'super_admin', 'admin'), u.sort_no ASC
        ");

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }
}
