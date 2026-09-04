<?php
namespace App\Models\Institution;
use PDO;
class SocialInsuranceAssessmentBasisModel
{
 public function __construct(private readonly PDO$db){}
 public function find(string$id,bool$lock=false):?array{$s=$this->db->prepare('SELECT * FROM institution_social_insurance_assessment_bases WHERE id=:id'.($lock?' FOR UPDATE':''));$s->execute([':id'=>$id]);return$s->fetch(PDO::FETCH_ASSOC)?:null;}
 public function coverageLock(string$id):?array{$s=$this->db->prepare('SELECT * FROM institution_social_insurance_coverages WHERE id=:id FOR UPDATE');$s->execute([':id'=>$id]);return$s->fetch(PDO::FETCH_ASSOC)?:null;}
 public function overlaps(string$coverage,string$type,string$from,?string$to,?string$exclude=null):bool{$sql="SELECT id FROM institution_social_insurance_assessment_bases WHERE coverage_id=:coverage AND basis_type_code=:type AND effective_from<=COALESCE(:to,'9999-12-31') AND COALESCE(effective_to,'9999-12-31')>=:from";$p=[':coverage'=>$coverage,':type'=>$type,':from'=>$from,':to'=>$to];if($exclude){$sql.=' AND id<>:exclude';$p[':exclude']=$exclude;}$sql.=' LIMIT 1 FOR UPDATE';$s=$this->db->prepare($sql);$s->execute($p);return(bool)$s->fetchColumn();}
 public function insert(array$d):void{$this->write('INSERT INTO institution_social_insurance_assessment_bases (`'.implode('`,`',array_keys($d)).'`) VALUES (:'.implode(',:',array_keys($d)).')',$d);}
 public function update(string$id,array$d):void{$sets=[];foreach(array_keys($d)as$k)$sets[]="`$k`=:$k";$d['id']=$id;$this->write('UPDATE institution_social_insurance_assessment_bases SET '.implode(',',$sets).' WHERE id=:id',$d);}
 public function batch(array$coverageIds,string$from,string$to):array{if(!$coverageIds)return[];$in=implode(',',array_fill(0,count($coverageIds),'?'));$s=$this->db->prepare("SELECT * FROM institution_social_insurance_assessment_bases WHERE coverage_id IN($in) AND effective_from<=? AND COALESCE(effective_to,'9999-12-31')>=? ORDER BY coverage_id,basis_type_code,effective_from");$s->execute([...$coverageIds,$to,$from]);return$s->fetchAll(PDO::FETCH_ASSOC)?:[];}
 private function write(string$sql,array$d):void{$p=[];foreach($d as$k=>$v)$p[':'.$k]=$v;$this->db->prepare($sql)->execute($p);}
}
