<?php
namespace App\Models\Institution;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PDO;
class QualificationModel
{
 public function __construct(private PDO $db) {}
 public function page(array $query, ?string $employeeScope=null): array
 {
  $start=max(0,(int)($query['start']??0));$length=max(1,min(200,(int)($query['length']??50)));$params=[];$where=['q.deleted_at IS NULL'];
  if($employeeScope!==null){$where[]='q.employee_id=:scope_employee';$params[':scope_employee']=$employeeScope;}
  $filters=json_decode((string)($query['filters']??'[]'),true);foreach(is_array($filters)?$filters:[] as $f){$field=(string)($f['field']??'');$raw=$f['value']??'';$value=is_array($raw)?'':trim((string)$raw);if(is_array($raw)&&in_array($field,['valid_to','acquired_date'],true)){$column=$field==='valid_to'?'q.valid_to':'q.acquired_date';$from=trim((string)($raw['start']??''));$to=trim((string)($raw['end']??''));if($from!==''){$where[]=$column.'>=:date_from';$params[':date_from']=$from;}if($to!==''){$where[]=$column.'<=:date_to';$params[':date_to']=$to;}continue;}if($value==='')continue;if($field==='employee_id'){$where[]='q.employee_id=:employee';$params[':employee']=$value;}elseif($field==='qualification_type_code'){$where[]='q.qualification_type_code=:type';$params[':type']=$value;}elseif($field==='status_code'){$where[]='q.status_code=:status';$params[':status']=$value;}elseif($field==='expiry_state'){$today=date('Y-m-d');if($value==='EXPIRED'){$where[]='q.valid_to IS NOT NULL AND q.valid_to<:today';$params[':today']=$today;}elseif($value==='EXPIRING'){$where[]='q.valid_to BETWEEN :today AND DATE_ADD(:today2,INTERVAL 90 DAY)';$params[':today']=$today;$params[':today2']=$today;}}}
  $search=trim((string)($query['search']['value']??$query['search']??''));if($search!==''){$where[]='(e.employee_name LIKE :search OR u.username LIKE :search OR q.qualification_name LIKE :search OR q.credential_number LIKE :search)';$params[':search']='%'.$search.'%';}
  $sql=' FROM institution_qualifications_employee_records q JOIN user_employees e ON e.id=q.employee_id JOIN auth_users u ON u.id=e.user_id LEFT JOIN user_departments d ON d.id=e.department_id LEFT JOIN institution_job_assignments_jobs j ON j.id=e.job_id WHERE '.implode(' AND ',$where);
  $count=$this->db->prepare('SELECT COUNT(*)'.$sql);$count->execute($params);$total=(int)$count->fetchColumn();
  $stmt=$this->db->prepare("SELECT q.*,e.employee_name,u.username,d.dept_name,j.job_name,CASE WHEN q.status_code IN ('SUSPENDED','REVOKED') THEN q.status_code WHEN q.valid_to IS NOT NULL AND q.valid_to<CURDATE() THEN 'EXPIRED' WHEN q.valid_to IS NOT NULL AND q.valid_to<=DATE_ADD(CURDATE(),INTERVAL 90 DAY) THEN 'EXPIRING' ELSE q.status_code END display_status_code".$sql.' ORDER BY e.employee_name,q.acquired_date DESC,q.created_at DESC LIMIT '.$start.','.$length);$stmt->execute($params);
  return ['rows'=>ActorHelper::enrichActorNames($stmt->fetchAll(PDO::FETCH_ASSOC)?:[],['created_by','updated_by','verified_by']),'total'=>$total];
 }
 public function detail(string $id): ?array{$s=$this->db->prepare('SELECT q.*,e.employee_name FROM institution_qualifications_employee_records q JOIN user_employees e ON e.id=q.employee_id WHERE q.id=:id AND q.deleted_at IS NULL');$s->execute([':id'=>$id]);$r=$s->fetch(PDO::FETCH_ASSOC);return $r?ActorHelper::enrichActorNamesRow($r,['created_by','updated_by','verified_by']):null;}
 public function countByEmployee(string $employeeId): int{$s=$this->db->prepare('SELECT COUNT(*) FROM institution_qualifications_employee_records WHERE employee_id=:employee_id AND deleted_at IS NULL');$s->execute([':employee_id'=>$employeeId]);return (int)$s->fetchColumn();}
 public function byRequestKey(string $key): ?array{$s=$this->db->prepare('SELECT * FROM institution_qualifications_employee_records WHERE request_key=:k');$s->execute([':k'=>$key]);return $s->fetch(PDO::FETCH_ASSOC)?:null;}
 public function create(array $data): string{$id=UuidHelper::generate();$data['id']=$id;$fields=array_keys($data);$s=$this->db->prepare('INSERT INTO institution_qualifications_employee_records ('.implode(',',$fields).') VALUES (:'.implode(',:',$fields).')');$s->execute(array_combine(array_map(fn($f)=>':'.$f,$fields),array_values($data)));return $id;}
 public function update(string $id,array $data): void{$set=[];$params=[':id'=>$id];foreach($data as $k=>$v){$set[]=$k.'=:'.$k;$params[':'.$k]=$v;}$s=$this->db->prepare('UPDATE institution_qualifications_employee_records SET '.implode(',',$set).' WHERE id=:id AND deleted_at IS NULL');$s->execute($params);}
 public function softDelete(string $id,string $actor): void{$s=$this->db->prepare('UPDATE institution_qualifications_employee_records SET deleted_at=NOW(),deleted_by=:a,updated_at=NOW(),updated_by=:a WHERE id=:id AND deleted_at IS NULL');$s->execute([':id'=>$id,':a'=>$actor]);}
 public function audit(array $data): void{$data['id']=UuidHelper::generate();$fields=array_keys($data);$s=$this->db->prepare('INSERT INTO institution_qualifications_audits ('.implode(',',$fields).') VALUES (:'.implode(',:',$fields).')');$s->execute(array_combine(array_map(fn($f)=>':'.$f,$fields),array_values($data)));}
 public function auditTargetByRequest(string $key): ?string{$s=$this->db->prepare('SELECT target_id FROM institution_qualifications_audits WHERE request_key=:k');$s->execute([':k'=>$key]);$v=$s->fetchColumn();return $v===false?null:(string)$v;}
 public function employeeExists(string $id): bool{$s=$this->db->prepare('SELECT COUNT(*) FROM user_employees WHERE id=:id');$s->execute([':id'=>$id]);return (bool)$s->fetchColumn();}
 public function employeeIdForUser(string $userId): string{$s=$this->db->prepare('SELECT id FROM user_employees WHERE user_id=:id LIMIT 1');$s->execute([':id'=>$userId]);return (string)$s->fetchColumn();}
}
