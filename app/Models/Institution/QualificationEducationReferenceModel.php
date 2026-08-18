<?php
namespace App\Models\Institution;
use PDO;
class QualificationEducationReferenceModel
{
 public function __construct(private PDO $db) {}
 public function options(): array
 {
  return ['employees'=>$this->db->query("SELECT e.id value,CONCAT(e.employee_name,' (',u.username,')') label FROM user_employees e JOIN auth_users u ON u.id=e.user_id WHERE e.employment_status<>'RETIRED' ORDER BY e.employee_name")->fetchAll(PDO::FETCH_ASSOC)?:[],
   'qualification_types'=>$this->codes('QUALIFICATION_TYPE'),'qualification_statuses'=>$this->codes('QUALIFICATION_STATUS'),'education_types'=>$this->codes('EDUCATION_TYPE'),
   'attendance_statuses'=>$this->codes('EDUCATION_ATTENDANCE_STATUS'),'completion_statuses'=>$this->codes('EDUCATION_COMPLETION_STATUS')];
 }
 private function codes(string $group): array{$s=$this->db->prepare('SELECT code value,code_name label FROM system_codes WHERE code_group=:g AND is_active=1 ORDER BY sort_no,code');$s->execute([':g'=>$group]);return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}
 public function codeExists(string $group,string $code): bool{$s=$this->db->prepare('SELECT COUNT(*) FROM system_codes WHERE code_group=:g AND code=:c AND is_active=1');$s->execute([':g'=>$group,':c'=>$code]);return (bool)$s->fetchColumn();}
 public function representativeEmployeeId(string $qualificationId): ?string{$s=$this->db->prepare('SELECT id FROM user_employees WHERE representative_qualification_id=:id LIMIT 1');$s->execute([':id'=>$qualificationId]);$v=$s->fetchColumn();return $v===false?null:(string)$v;}
 public function clearRepresentativeQualification(string $qualificationId): void{$s=$this->db->prepare('UPDATE user_employees SET representative_qualification_id=NULL WHERE representative_qualification_id=:id');$s->execute([':id'=>$qualificationId]);}
}
