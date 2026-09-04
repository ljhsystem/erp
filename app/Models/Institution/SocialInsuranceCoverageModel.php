<?php

namespace App\Models\Institution;

use PDO;

class SocialInsuranceCoverageModel
{
    public function __construct(private readonly PDO $db) {}

    public function employeeLock(string $employeeId): void
    {
        $stmt=$this->db->prepare('SELECT id FROM user_employees WHERE id=:id FOR UPDATE');$stmt->execute([':id'=>$employeeId]);
        if(!$stmt->fetchColumn())throw new \InvalidArgumentException('직원을 찾을 수 없습니다.');
    }
    public function find(string $id,bool $lock=false):?array{$s=$this->db->prepare('SELECT * FROM institution_social_insurance_coverages WHERE id=:id'.($lock?' FOR UPDATE':''));$s->execute([':id'=>$id]);return$s->fetch(PDO::FETCH_ASSOC)?:null;}
    public function overlaps(string$employeeId,string$type,string$from,?string$to,?string$excludeId=null):bool{$sql="SELECT id FROM institution_social_insurance_coverages WHERE employee_id=:employee AND insurance_type_code=:type AND effective_from<=COALESCE(:to,'9999-12-31') AND COALESCE(effective_to,'9999-12-31')>=:from";$p=[':employee'=>$employeeId,':type'=>$type,':from'=>$from,':to'=>$to];if($excludeId){$sql.=' AND id<>:exclude';$p[':exclude']=$excludeId;}$sql.=' LIMIT 1 FOR UPDATE';$s=$this->db->prepare($sql);$s->execute($p);return(bool)$s->fetchColumn();}
    public function healthContains(string$employeeId,string$from,?string$to):bool{$s=$this->db->prepare("SELECT id FROM institution_social_insurance_coverages WHERE employee_id=:employee AND insurance_type_code='HEALTH_INSURANCE' AND coverage_status_code='ACQUIRED' AND confirmed_at IS NOT NULL AND effective_from<=:from AND COALESCE(effective_to,'9999-12-31')>=COALESCE(:to,'9999-12-31') LIMIT 1 FOR UPDATE");$s->execute([':employee'=>$employeeId,':from'=>$from,':to'=>$to]);return(bool)$s->fetchColumn();}
    public function insert(array$d):void{$this->write('INSERT INTO institution_social_insurance_coverages (`'.implode('`,`',array_keys($d)).'`) VALUES (:'.implode(',:',array_keys($d)).')',$d);}
    public function update(string$id,array$d):void{$sets=[];foreach(array_keys($d)as$k)$sets[]="`$k`=:$k";$d['id']=$id;$this->write('UPDATE institution_social_insurance_coverages SET '.implode(',',$sets).' WHERE id=:id',$d);}
    public function batch(array$employeeIds,string$from,string$to):array{if(!$employeeIds)return[];$in=implode(',',array_fill(0,count($employeeIds),'?'));$s=$this->db->prepare("SELECT * FROM institution_social_insurance_coverages WHERE employee_id IN($in) AND effective_from<=? AND COALESCE(effective_to,'9999-12-31')>=? ORDER BY employee_id,insurance_type_code,effective_from");$s->execute([...$employeeIds,$to,$from]);return$s->fetchAll(PDO::FETCH_ASSOC)?:[];}
    private function write(string$sql,array$d):void{$p=[];foreach($d as$k=>$v)$p[':'.$k]=$v;$this->db->prepare($sql)->execute($p);}
}
