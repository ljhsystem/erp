<?php

namespace App\Models\System;

use PDO;

class NotificationModel
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByRecipient(string $userId, int $limit): array
    {
        $stmt = $this->pdo->prepare("
            SELECT n.id, n.recipient_user_id, n.actor_user_id,
                   au.username AS actor_username, n.action_type, n.ref_table,
                   n.ref_id, n.title, n.message, n.is_read, n.read_at, n.created_at
              FROM system_notifications n
              LEFT JOIN auth_users au ON au.id = n.actor_user_id
             WHERE n.recipient_user_id = :user_id
             ORDER BY n.created_at DESC
             LIMIT :limit
        ");
        $stmt->bindValue(':user_id', $userId);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function markAsRead(string $id, string $userId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE system_notifications SET is_read = 1, read_at = COALESCE(read_at, NOW())
             WHERE id = :id AND recipient_user_id = :user_id
        ");
        return $stmt->execute([':id' => $id, ':user_id' => $userId]);
    }

    public function markAllAsRead(string $userId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE system_notifications SET is_read = 1, read_at = COALESCE(read_at, NOW())
             WHERE recipient_user_id = :user_id AND is_read = 0
        ");
        return $stmt->execute([':user_id' => $userId]);
    }

    public function insert(array $data): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO system_notifications (
                id, recipient_user_id, actor_user_id, action_type, ref_table,
                ref_id, title, message, is_read, created_at
            ) VALUES (
                :id, :recipient_user_id, :actor_user_id, :action_type, :ref_table,
                :ref_id, :title, :message, 0, NOW()
            )
        ");
        return $stmt->execute($data);
    }
}
