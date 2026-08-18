<?php

namespace App\Models\User;

use Core\Database;
use PDO;

class ApprovalRequestStepModel
{
    private PDO $db;
    public function __construct(?PDO $pdo=null){$this->db=$pdo??Database::getInstance()->getConnection();}

    public function getSteps(string $requestId): array { $s=$this->db->prepare('SELECT * FROM user_approval_request_steps WHERE request_id=? AND is_active=1 ORDER BY sort_no');$s->execute([$requestId]);return $s->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    public function getById(string $id,bool $forUpdate=false): ?array { $s=$this->db->prepare('SELECT * FROM user_approval_request_steps WHERE id=? AND is_active=1 LIMIT 1'.($forUpdate?' FOR UPDATE':''));$s->execute([$id]);return $s->fetch(PDO::FETCH_ASSOC)?:null; }
    public function create(array $d): bool
    {
        $s = $this->db->prepare("INSERT INTO user_approval_request_steps
            (id,request_id,sort_no,step_name,step_type,role_id,approver_id,acted_by,status,action_at,comment,is_active,created_by,updated_by)
            VALUES
            (:id,:request_id,:sort_no,:step_name,:step_type,:role_id,:approver_id,:acted_by,:status,:action_at,NULL,1,:created_by,:updated_by)");
        return $s->execute([
            ':id'=>$d['id'], ':request_id'=>$d['request_id'], ':sort_no'=>$d['sort_no'],
            ':step_name'=>$d['step_name'], ':step_type'=>$d['step_type'], ':role_id'=>$d['role_id'],
            ':approver_id'=>$d['approver_id'], ':acted_by'=>$d['acted_by'] ?? null,
            ':status'=>$d['status'], ':action_at'=>$d['action_at'] ?? null,
            ':created_by'=>$d['created_by'], ':updated_by'=>$d['updated_by'] ?? $d['created_by'],
        ]);
    }

    public function act(string $id, string $status, ?string $comment, string $userId, ?string $roleId, string $actor): bool
    {
        $s = $this->db->prepare("UPDATE user_approval_request_steps
            SET status=:status,comment=:comment,acted_by=:acted_by,action_at=NOW(),updated_by=:actor,updated_at=NOW()
            WHERE id=:id
              AND status='pending'
              AND is_active=1
              AND step_type IN ('APPROVAL','FINAL_APPROVAL')
              AND (
                    approver_id=:specific_user_id
                    OR (approver_id IS NULL AND role_id=:role_id)
              )");
        $s->execute([
            ':status'=>$status, ':comment'=>$comment, ':acted_by'=>$userId,
            ':specific_user_id'=>$userId, ':role_id'=>$roleId, ':actor'=>$actor, ':id'=>$id,
        ]);
        return $s->rowCount() === 1;
    }
    public function activate(string $requestId,int $sortNo,string $actor): bool { $s=$this->db->prepare("UPDATE user_approval_request_steps SET status='pending',updated_by=:actor,updated_at=NOW() WHERE request_id=:request_id AND sort_no=:sort_no AND status='waiting' AND is_active=1");$s->execute([':actor'=>$actor,':request_id'=>$requestId,':sort_no'=>$sortNo]);return $s->rowCount()===1; }
    public function cancelRemaining(string $requestId,string $actor): void { $s=$this->db->prepare("UPDATE user_approval_request_steps SET status='cancelled',updated_by=:actor,updated_at=NOW() WHERE request_id=:request_id AND status IN ('waiting','pending') AND is_active=1");$s->execute([':actor'=>$actor,':request_id'=>$requestId]); }
    public function delete(string $id): bool { $s=$this->db->prepare('UPDATE user_approval_request_steps SET is_active=0,updated_at=NOW() WHERE id=?');return $s->execute([$id]); }
}
