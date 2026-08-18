<?php

namespace App\Models\User;

use Core\Database;
use PDO;

class ApprovalRequestModel
{
    private PDO $db;
    public function __construct(?PDO $pdo = null) { $this->db = $pdo ?? Database::getInstance()->getConnection(); }

    public function nextSortNo(): int { return max(1, (int) $this->db->query('SELECT COALESCE(MAX(sort_no),0)+1 FROM user_approval_requests')->fetchColumn()); }

    public function create(array $data): bool
    {
        $stmt=$this->db->prepare("INSERT INTO user_approval_requests
            (id,sort_no,template_id,document_type,document_id,requester_id,status,current_step,is_active,created_by,updated_by)
            VALUES (:id,:sort_no,:template_id,:document_type,:document_id,:requester_id,:status,:current_step,:is_active,:created_by,:updated_by)");
        return $stmt->execute([
            ':id'=>$data['id'], ':sort_no'=>$data['sort_no'], ':template_id'=>$data['template_id'],
            ':document_type'=>$data['document_type'], ':document_id'=>$data['document_id'], ':requester_id'=>$data['requester_id'],
            ':status'=>$data['status'] ?? 'pending', ':current_step'=>$data['current_step'] ?? 1, ':is_active'=>$data['is_active'] ?? 1,
            ':created_by'=>$data['created_by'] ?? null, ':updated_by'=>$data['updated_by'] ?? $data['created_by'] ?? null,
        ]);
    }

    public function getById(string $id, bool $forUpdate=false): ?array
    {
        $stmt=$this->db->prepare('SELECT * FROM user_approval_requests WHERE id=:id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
        $stmt->execute([':id'=>$id]); return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function latestForDocument(string $documentType,string $documentId,bool $forUpdate=false): ?array
    {
        $stmt=$this->db->prepare("SELECT * FROM user_approval_requests WHERE document_type=:document_type AND document_id=:document_id AND is_active=1 ORDER BY requested_at DESC,sort_no DESC LIMIT 1" . ($forUpdate?' FOR UPDATE':''));
        $stmt->execute([':document_type'=>$documentType,':document_id'=>$documentId]); return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function hasBlockingHistory(string $documentType, string $documentId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM user_approval_requests
             WHERE document_type = :document_type
               AND document_id = :document_id
               AND is_active = 1
             LIMIT 1"
        );
        $stmt->execute([':document_type' => $documentType, ':document_id' => $documentId]);
        return (bool) $stmt->fetchColumn();
    }

    public function updateStatus(string $id,string $status,?string $updatedBy=null): bool
    {
        $completed=in_array($status,['approved','rejected','cancelled'],true) ? 'completed_at=NOW(),' : '';
        $stmt=$this->db->prepare("UPDATE user_approval_requests SET {$completed} status=:status,updated_by=:updated_by,updated_at=NOW() WHERE id=:id");
        return $stmt->execute([':status'=>$status,':updated_by'=>$updatedBy,':id'=>$id]);
    }

    public function updateCurrentStep(string $id,int $step,?string $updatedBy=null): bool
    {
        $stmt=$this->db->prepare('UPDATE user_approval_requests SET current_step=:step,status=\'in_progress\',updated_by=:actor,updated_at=NOW() WHERE id=:id');
        return $stmt->execute([':step'=>$step,':actor'=>$updatedBy,':id'=>$id]);
    }

    public function withdraw(string $id,string $requesterId,string $actor): bool
    {
        $stmt=$this->db->prepare("UPDATE user_approval_requests SET status='withdrawn',withdrawn_at=NOW(),withdrawn_by=:withdrawn_by,updated_by=:actor,updated_at=NOW() WHERE id=:id AND requester_id=:requester_id AND status IN ('pending','in_progress')");
        $stmt->execute([':id'=>$id,':withdrawn_by'=>$requesterId,':requester_id'=>$requesterId,':actor'=>$actor]); return $stmt->rowCount()===1;
    }

    public function delete(string $id): bool { $stmt=$this->db->prepare('UPDATE user_approval_requests SET is_active=0,updated_at=NOW() WHERE id=?'); return $stmt->execute([$id]); }
}
