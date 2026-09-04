<?php

declare(strict_types=1);

namespace App\Models\System;

use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PDO;

class StatutoryStandardSupersessionModel
{
    public function __construct(private PDO $db)
    {
    }

    public function create(string $predecessorId, string $successorId, string $reason, string $actor): string
    {
        if (!$this->db->inTransaction()) {
            throw new \LogicException('법정기준 Revision 정정은 DB Transaction 안에서만 등록할 수 있습니다.');
        }
        if ($predecessorId === $successorId) {
            throw new \InvalidArgumentException('법정기준 Revision은 자기 자신을 대체할 수 없습니다.');
        }
        $ids=[$predecessorId,$successorId];sort($ids,SORT_STRING);$rows=[];
        $lock=$this->db->prepare('SELECT id,standard_type_code,policy_component_code,employment_type_code,work_scope_code,additional_dimension_key FROM system_statutory_standards WHERE id IN (?,?) ORDER BY id FOR UPDATE');
        $lock->execute($ids);foreach($lock->fetchAll(PDO::FETCH_ASSOC)?:[]as$row)$rows[(string)$row['id']]=$row;
        if(!isset($rows[$predecessorId],$rows[$successorId]))throw new \InvalidArgumentException('대체할 법정기준 Revision을 찾을 수 없습니다.');
        foreach(['standard_type_code','policy_component_code','employment_type_code','work_scope_code','additional_dimension_key']as$field){if($rows[$predecessorId][$field]!==$rows[$successorId][$field])throw new \InvalidArgumentException('동일한 법정기준 Type과 Scope Revision만 대체할 수 있습니다.');}
        $relations=$this->db->query('SELECT predecessor_revision_id,successor_revision_id FROM system_statutory_standard_supersessions ORDER BY predecessor_revision_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC)?:[];
        $next=[];$previous=[];foreach($relations as$relation){$next[(string)$relation['predecessor_revision_id']]=(string)$relation['successor_revision_id'];$previous[(string)$relation['successor_revision_id']]=(string)$relation['predecessor_revision_id'];}
        if(isset($next[$predecessorId])||isset($previous[$successorId]))throw new \DomainException('STATUTORY_SUPERSESSION_BRANCH_NOT_ALLOWED');
        $cursor=$successorId;$visited=[];while(isset($next[$cursor])){if(isset($visited[$cursor])||$cursor===$predecessorId)throw new \DomainException('STATUTORY_SUPERSESSION_CYCLE_NOT_ALLOWED');$visited[$cursor]=true;$cursor=$next[$cursor];}if($cursor===$predecessorId)throw new \DomainException('STATUTORY_SUPERSESSION_CYCLE_NOT_ALLOWED');
        $id = UuidHelper::generate();
        $statement = $this->db->prepare(
            'INSERT INTO system_statutory_standard_supersessions('
            . 'id,predecessor_revision_id,successor_revision_id,correction_reason,created_at,created_by'
            . ') VALUES(:id,:predecessor,:successor,:reason,:created_at,:created_by)'
        );
        $statement->execute([
            ':id' => $id,
            ':predecessor' => $predecessorId,
            ':successor' => $successorId,
            ':reason' => $reason,
            ':created_at' => date('Y-m-d H:i:s'),
            ':created_by' => $actor,
        ]);
        return $id;
    }

    public function chain(string $revisionId): array
    {
        $statement = $this->db->prepare(
            'SELECT relation.*,p.effective_from predecessor_effective_from,p.effective_to predecessor_effective_to,'
            . ' n.effective_from successor_effective_from,n.effective_to successor_effective_to'
            . ' FROM system_statutory_standard_supersessions relation'
            . ' JOIN system_statutory_standards p ON p.id=relation.predecessor_revision_id'
            . ' JOIN system_statutory_standards n ON n.id=relation.successor_revision_id'
            . ' JOIN system_statutory_standards target ON target.id=:revision'
            . ' WHERE p.standard_type_code=target.standard_type_code'
            . ' AND p.policy_component_code<=>target.policy_component_code'
            . ' AND p.employment_type_code<=>target.employment_type_code'
            . ' AND p.work_scope_code<=>target.work_scope_code'
            . ' AND p.additional_dimension_key<=>target.additional_dimension_key'
            . ' ORDER BY relation.created_at,relation.id'
        );
        $statement->execute([':revision' => $revisionId]);
        $rows=$statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $connected=[$revisionId=>true];$selected=[];
        do{$changed=false;foreach($rows as$row){$pre=(string)$row['predecessor_revision_id'];$next=(string)$row['successor_revision_id'];if(isset($connected[$pre])||isset($connected[$next])){$key=(string)$row['id'];if(!isset($selected[$key])){$selected[$key]=$row;$changed=true;}$connected[$pre]=true;$connected[$next]=true;}}}while($changed);
        return ActorHelper::enrichActorNames(array_values($selected), ['created_by']);
    }

    public function update(string $id, array $data): void
    {
        throw new \LogicException('확정된 법정기준 Revision 대체 관계는 수정할 수 없습니다.');
    }

    public function delete(string $id): void
    {
        throw new \LogicException('확정된 법정기준 Revision 대체 관계는 삭제할 수 없습니다.');
    }
}
