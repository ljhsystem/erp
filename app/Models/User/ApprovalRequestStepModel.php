<?php
namespace App\Models\User;

use PDO;
use Core\Database;

class ApprovalRequestStepModel
{

    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getSteps(string $requestId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM user_approval_request_steps
            WHERE request_id = ?
            ORDER BY sequence ASC
        ");
        $stmt->execute([$requestId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM user_approval_request_steps
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO user_approval_request_steps (
                id, request_id, sequence,
                approver_id, role_id,
                status, approved_at, rejected_at, comment,
                is_active, created_by, created_at
            )
            VALUES (
                :id, :request_id, :sequence,
                :approver_id, :role_id,
                :status, :approved_at, :rejected_at, :comment,
                :is_active, :created_by, NOW()
            )
        ");

        return $stmt->execute($data);
    }

    public function updateStatus(string $id, string $status, ?string $comment, ?string $updatedBy): bool
    {
        $stmt = $this->db->prepare("
            UPDATE user_approval_request_steps
            SET
                status      = :status,
                comment     = :comment,
                updated_by  = :updated_by,
                updated_at  = NOW(),
                approved_at = IF(:status = 'approved', NOW(), approved_at),
                rejected_at = IF(:status = 'rejected', NOW(), rejected_at)
            WHERE id = :id
        ");

        return $stmt->execute([
            ':status'     => $status,
            ':comment'    => $comment,
            ':updated_by' => $updatedBy,
            ':id'         => $id
        ]);
    }

    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM user_approval_request_steps
            WHERE id = ?
        ");

        return $stmt->execute([$id]);
    }
}
