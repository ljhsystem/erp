<?php

namespace App\Models\Auth;

use Core\Database;
use PDO;

class LogModel
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function write(array $data): bool
    {
        $sql = "
            INSERT INTO auth_logs (
                id,
                user_id, username,
                log_type, action_type, action_detail,
                ip_address, user_agent,
                success,
                ref_table, ref_id,
                created_by, created_at
            ) VALUES (
                :id,
                :user_id, :username,
                :log_type, :action_type, :action_detail,
                :ip_address, :user_agent,
                :success,
                :ref_table, :ref_id,
                :created_by, NOW()
            )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id' => $data['id'],
            ':user_id' => $data['user_id'] ?? null,
            ':username' => $data['username'] ?? null,
            ':log_type' => $data['log_type'] ?? 'auth',
            ':action_type' => $data['action_type'] ?? '',
            ':action_detail' => $data['action_detail'] ?? null,
            ':ip_address' => $data['ip_address'] ?? null,
            ':user_agent' => $data['user_agent'] ?? null,
            ':success' => $data['success'] ?? 1,
            ':ref_table' => $data['ref_table'] ?? null,
            ':ref_id' => $data['ref_id'] ?? null,
            ':created_by' => $data['created_by'] ?? null,
        ]);
    }

    public function getUserLogs(string $userId, int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT *
              FROM auth_logs
             WHERE user_id = :uid
             ORDER BY created_at DESC
             LIMIT :limitVal
        ");
        $stmt->bindValue(':uid', $userId);
        $stmt->bindValue(':limitVal', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecentAuthLogs(int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT *
              FROM auth_logs
             WHERE log_type = 'auth'
             ORDER BY created_at DESC
             LIMIT :limitVal
        ");
        $stmt->bindValue(':limitVal', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLogsByAction(string $actionType, int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT *
              FROM auth_logs
             WHERE action_type = :act
             ORDER BY created_at DESC
             LIMIT :limitVal
        ");
        $stmt->bindValue(':act', $actionType);
        $stmt->bindValue(':limitVal', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getApprovalLogs(int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT *
              FROM auth_logs
             WHERE action_type = 'approve'
             ORDER BY created_at DESC
             LIMIT :limitVal
        ");
        $stmt->bindValue(':limitVal', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserApprovalLogs(string $userId, int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT *
              FROM auth_logs
             WHERE action_type = 'approve'
               AND user_id = :uid
             ORDER BY created_at DESC
             LIMIT :limitVal
        ");
        $stmt->bindValue(':uid', $userId);
        $stmt->bindValue(':limitVal', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
