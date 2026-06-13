<?php
namespace App\Models\User;

use PDO;
use Core\Database;

class ApprovalRequestModel
{

    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function create(array $data): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO user_approval_requests
            (
                id, template_id, document_id, requester_id,
                status, current_step, is_active,
                created_by, created_at
            )
            VALUES
            (
                :id, :template_id, :document_id, :requester_id,
                :status, :current_step, :is_active,
                :created_by, NOW()
            )
        ");

        return $stmt->execute([
            ':id'            => $data['id'],
            ':template_id'   => $data['template_id'],
            ':document_id'   => $data['document_id'],
            ':requester_id'  => $data['requester_id'],
            ':status'        => $data['status'],
            ':current_step'  => $data['current_step'],
            ':is_active'     => $data['is_active'],
            ':created_by'    => $data['created_by'],
        ]);
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM user_approval_requests
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function updateStatus(string $id, string $status, ?string $updatedBy = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE user_approval_requests
            SET
                status = :status,
                updated_by = :updated_by,
                updated_at = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':status'     => $status,
            ':updated_by' => $updatedBy,
            ':id'         => $id,
        ]);
    }

    public function updateCurrentStep(string $id, int $step, ?string $updatedBy = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE user_approval_requests
            SET
                current_step = :current_step,
                updated_by = :updated_by,
                updated_at = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':current_step' => $step,
            ':updated_by'   => $updatedBy,
            ':id'           => $id,
        ]);
    }

    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM user_approval_requests
            WHERE id = ?
        ");

        return $stmt->execute([$id]);
    }
}
