<?php
namespace App\Models\Institution;
use PDO;
class SocialInsuranceAuditModel
{
 public function __construct(private readonly PDO$db){}
 public function insert(array$d):void{$c=array_keys($d);$p=[];foreach($d as$k=>$v)$p[':'.$k]=$v;$this->db->prepare('INSERT INTO institution_social_insurance_audits (`'.implode('`,`',$c).'`) VALUES (:'.implode(',:',$c).')')->execute($p);}
}
