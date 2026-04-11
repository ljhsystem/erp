<?php
// 경로: PROJECT_ROOT . '/app/Models/User/ApprovalRequestModel.php'
namespace App\Models\User;

use PDO;
use Core\Database;

class ApprovalRequestModel
{
    // PDO 보관
    private PDO $db;

    // 생성자 – 외부에서 PDO 주입 또는 자동 연결
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    /* ============================================================
     * 요청 생성 (결재 시작)
     * ============================================================ */
    public function create(array $data): bool
    {
        // ⚠️ UUID는 Service 에서 생성하여 전달됨

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
            ':id'            => $data['id'],               // <- Service 가 생성해서 넣음
            ':template_id'   => $data['template_id'],
            ':document_id'   => $data['document_id'],
            ':requester_id'  => $data['requester_id'],
            ':status'        => $data['status'],
            ':current_step'  => $data['current_step'],
            ':is_active'     => $data['is_active'],
            ':created_by'    => $data['created_by'],
        ]);
    }

    /* ============================================================
     * 요청 단일 조회
     * ============================================================ */
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

    /* ============================================================
     * 상태 변경 (승인/반려/진행 등)
     * ============================================================ */
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

    /* ============================================================
     * 현재 진행중인 스텝 변경 (ex: 1 → 2 → 3)
     * ============================================================ */
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

    /* ============================================================
     * 요청 삭제 (주의: 실제 삭제, soft-delete 아님)
     * ============================================================ */
    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM user_approval_requests
            WHERE id = ?
        ");

        return $stmt->execute([$id]);
    }
}
