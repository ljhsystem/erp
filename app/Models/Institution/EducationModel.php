<?php
namespace App\Models\Institution;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PDO;
class EducationModel
{
 public function __construct(private PDO $db) {}
 public function page(array $query,?string $employeeScope=null): array
 {
  $start=max(0,(int)($query['start']??0));$length=max(1,min(200,(int)($query['length']??50)));$params=[];$where=['r.deleted_at IS NULL'];if($employeeScope!==null){$where[]='r.employee_id=:scope';$params[':scope']=$employeeScope;}
  $filters=json_decode((string)($query['filters']??'[]'),true);foreach(is_array($filters)?$filters:[] as $f){$field=(string)($f['field']??'');$raw=$f['value']??'';$value=is_array($raw)?'':trim((string)$raw);if(is_array($raw)&&in_array($field,['valid_to','education_start_at'],true)){$column=$field==='valid_to'?'r.valid_to':'DATE(r.education_start_at)';$from=trim((string)($raw['start']??''));$to=trim((string)($raw['end']??''));if($from!==''){$where[]=$column.'>=:date_from';$params[':date_from']=$from;}if($to!==''){$where[]=$column.'<=:date_to';$params[':date_to']=$to;}continue;}if($value==='')continue;if($field==='employee_id'){$where[]='r.employee_id=:employee';$params[':employee']=$value;}elseif($field==='course_id'){$where[]='r.course_id=:course';$params[':course']=$value;}elseif($field==='education_type_code'){$where[]='c.education_type_code=:type';$params[':type']=$value;}elseif($field==='completion_status_code'){$where[]='r.completion_status_code=:status';$params[':status']=$value;}elseif($field==='expiry_state'&&$value==='EXPIRED'){$where[]='r.valid_to IS NOT NULL AND r.valid_to<CURDATE()';}elseif($field==='expiry_state'&&$value==='EXPIRING'){$where[]='r.valid_to BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 90 DAY)';}}
  $search=trim((string)($query['search']['value']??$query['search']??''));if($search!==''){$where[]='(e.employee_name LIKE :search OR u.username LIKE :search OR r.education_name LIKE :search OR c.course_name LIKE :search OR r.completion_number LIKE :search)';$params[':search']='%'.$search.'%';}
  $sql=' FROM institution_educations_employee_records r JOIN institution_educations_courses c ON c.id=r.course_id JOIN user_employees e ON e.id=r.employee_id JOIN auth_users u ON u.id=e.user_id LEFT JOIN user_departments d ON d.id=e.department_id WHERE '.implode(' AND ',$where);
  $count=$this->db->prepare('SELECT COUNT(*)'.$sql);$count->execute($params);$total=(int)$count->fetchColumn();$stmt=$this->db->prepare("SELECT r.*,c.course_code,c.course_name,c.education_type_code,e.employee_name,u.username,d.dept_name,CASE WHEN r.valid_to IS NOT NULL AND r.valid_to<CURDATE() THEN 'EXPIRED' WHEN r.valid_to IS NOT NULL AND r.valid_to<=DATE_ADD(CURDATE(),INTERVAL 90 DAY) THEN 'EXPIRING' ELSE r.completion_status_code END display_status_code".$sql.' ORDER BY r.education_start_at DESC,e.employee_name LIMIT '.$start.','.$length);$stmt->execute($params);
  return ['rows'=>ActorHelper::enrichActorNames($stmt->fetchAll(PDO::FETCH_ASSOC)?:[],['created_by','updated_by']),'total'=>$total];
 }
 public function detail(string $id): ?array{$s=$this->db->prepare('SELECT r.*,c.course_name,c.education_type_code,e.employee_name FROM institution_educations_employee_records r JOIN institution_educations_courses c ON c.id=r.course_id JOIN user_employees e ON e.id=r.employee_id WHERE r.id=:id AND r.deleted_at IS NULL');$s->execute([':id'=>$id]);$r=$s->fetch(PDO::FETCH_ASSOC);return $r?ActorHelper::enrichActorNamesRow($r,['created_by','updated_by']):null;}
 public function countByEmployee(string $employeeId): int{$s=$this->db->prepare('SELECT COUNT(*) FROM institution_educations_employee_records WHERE employee_id=:employee_id AND deleted_at IS NULL');$s->execute([':employee_id'=>$employeeId]);return (int)$s->fetchColumn();}
 public function courses(bool $activeOnly=true): array{$sql='SELECT * FROM institution_educations_courses WHERE deleted_at IS NULL'.($activeOnly?' AND is_active=1':'').' ORDER BY sort_no,course_name';return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC)?:[];}
 public function course(string $id): ?array{$s=$this->db->prepare('SELECT * FROM institution_educations_courses WHERE id=:id AND deleted_at IS NULL');$s->execute([':id'=>$id]);return $s->fetch(PDO::FETCH_ASSOC)?:null;}
 public function byRequestKey(string $key): ?array{$s=$this->db->prepare('SELECT * FROM institution_educations_employee_records WHERE request_key=:k');$s->execute([':k'=>$key]);return $s->fetch(PDO::FETCH_ASSOC)?:null;}
 public function createRecord(array $data): string{return $this->insert('institution_educations_employee_records',$data);}
 public function updateRecord(string $id,array $data): void{$this->update('institution_educations_employee_records',$id,$data);}
 public function deleteRecord(string $id,string $actor): void{$s=$this->db->prepare('UPDATE institution_educations_employee_records SET deleted_at=NOW(),deleted_by=:a,updated_at=NOW(),updated_by=:a WHERE id=:id AND deleted_at IS NULL');$s->execute([':id'=>$id,':a'=>$actor]);}
 public function createCourse(array $data): string{return $this->insert('institution_educations_courses',$data);}
 public function updateCourse(string $id,array $data): void{$this->update('institution_educations_courses',$id,$data);}
 public function audit(array $data): void{$this->insert('institution_educations_audits',$data);}
 public function auditTargetByRequest(string $key): ?string{$s=$this->db->prepare('SELECT target_id FROM institution_educations_audits WHERE request_key=:k');$s->execute([':k'=>$key]);$v=$s->fetchColumn();return $v===false?null:(string)$v;}
 private function insert(string $table,array $data): string{$id=UuidHelper::generate();$data['id']=$id;$fields=array_keys($data);$s=$this->db->prepare('INSERT INTO '.$table.' ('.implode(',',$fields).') VALUES (:'.implode(',:',$fields).')');$s->execute(array_combine(array_map(fn($f)=>':'.$f,$fields),array_values($data)));return $id;}
 private function update(string $table,string $id,array $data): void{$set=[];$params=[':id'=>$id];foreach($data as $k=>$v){$set[]=$k.'=:'.$k;$params[':'.$k]=$v;}$s=$this->db->prepare('UPDATE '.$table.' SET '.implode(',',$set).' WHERE id=:id');$s->execute($params);}
}
