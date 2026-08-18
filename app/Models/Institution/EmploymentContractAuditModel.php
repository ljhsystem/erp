<?php

namespace App\Models\Institution;

use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PDO;

final class EmploymentContractAuditModel
{
    public function __construct(private readonly PDO $db) {}

    public function find(string $contractId, string $action, string $requestKey): ?array
    {
        $stmt=$this->db->prepare('SELECT * FROM institution_employment_contracts_audits WHERE contract_id=:contract_id AND action_type=:action AND request_key=:request_key LIMIT 1');
        $stmt->execute([':contract_id'=>$contractId,':action'=>$action,':request_key'=>$requestKey]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC)?:null;
        return $row?ActorHelper::enrichActorNamesRow($row,['processed_by_name'=>'processed_by']):null;
    }

    public function record(array $data): array
    {
        if($existing=$this->find((string)$data['contract_id'],(string)$data['action_type'],(string)$data['request_key']))return$existing;
        $row=['id'=>UuidHelper::generate()]+$data;
        $columns=array_keys($row);
        $stmt=$this->db->prepare('INSERT INTO institution_employment_contracts_audits (`'.implode('`,`',$columns).'`) VALUES (:'.implode(',:',$columns).')');
        try{$stmt->execute($row);}catch(\PDOException $e){if((string)$e->getCode()!=='23000')throw$e;return$this->find((string)$data['contract_id'],(string)$data['action_type'],(string)$data['request_key'])??throw$e;}
        return $this->find((string)$data['contract_id'],(string)$data['action_type'],(string)$data['request_key'])??$row;
    }

    public function histories(string $contractId): array
    {
        $stmt=$this->db->prepare('SELECT * FROM institution_employment_contracts_audits WHERE contract_id=:contract_id ORDER BY processed_at DESC,id DESC');
        $stmt->execute([':contract_id'=>$contractId]);
        return ActorHelper::enrichActorNames($stmt->fetchAll(PDO::FETCH_ASSOC)?:[],['processed_by_name'=>'processed_by']);
    }
}
