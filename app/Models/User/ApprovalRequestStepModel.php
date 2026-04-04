<?php
// 경로: PROJECT_ROOT . '/app/Models/User/ApprovalRequestStepModel.php'
namespace App\Models\User;

use PDO;

class ApprovalRequestStepModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ============================================================
     * 1) 특정 요청의 모든 스텝 조회
     * ============================================================ */
    public function getSteps(string $requestId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM user_approval_request_steps
            WHERE request_id = ?
            ORDER BY sequence ASC
        ");
        $stmt->execute([$requestId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* ============================================================
     * 2) 단일 스텝 조회 (id 기준)
     * ============================================================ */
    public function getById(string $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM user_approval_request_steps
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* ============================================================
     * 3) 요청 스텝 생성 (※ UUID는 서비스에서 생성해서 전달)
     * ============================================================ */
    public function create(array $data): bool
    {
        $stmt = $this->pdo->prepare("
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

    /* ============================================================
     * 4) 스텝 상태 변경 (승인 / 반려 etc)
     * ============================================================ */
    public function updateStatus(string $id, string $status, ?string $comment, ?string $updatedBy): bool
    {
        $stmt = $this->pdo->prepare("
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

    /* ============================================================
     * 5) 스텝 삭제 (실제 삭제)
     * ============================================================ */
    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM user_approval_request_steps
            WHERE id = ?
        ");

        return $stmt->execute([$id]);
    }
}
