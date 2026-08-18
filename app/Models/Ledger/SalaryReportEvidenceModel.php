<?php
namespace App\Models\Ledger;
use PDO;
class SalaryReportEvidenceModel
{
    public function __construct(private readonly PDO $db){}
    public function findBySource(string$id,bool$lock=false):?array{$s=$this->db->prepare('SELECT * FROM ledger_evidence_salary_report WHERE source_regular_employment_income_id=:id LIMIT 1'.($lock?' FOR UPDATE':''));$s->execute([':id'=>$id]);return$s->fetch(PDO::FETCH_ASSOC)?:null;}
    public function nextSortNo():int{return max(1,(int)$this->db->query('SELECT COALESCE(MAX(sort_no),0)+1 FROM ledger_evidence_salary_report')->fetchColumn());}
    public function insert(array$d):void{$c=array_keys($d);$s=$this->db->prepare('INSERT INTO ledger_evidence_salary_report (`'.implode('`,`',$c).'`) VALUES (:'.implode(',:',$c).')');$s->execute(array_combine(array_map(fn($k)=>':'.$k,$c),array_values($d)));}
}
