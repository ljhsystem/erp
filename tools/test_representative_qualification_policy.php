<?php
declare(strict_types=1);

use App\Models\Institution\QualificationModel;
use App\Models\User\EmployeeModel;
use App\Services\Institution\QualificationEducationService;
use Core\DbPdo;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';
$db=DbPdo::conn();$user=$db->query("SELECT id,username FROM auth_users WHERE is_active=1 ORDER BY created_at LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$db->exec("DELETE q FROM institution_qualifications_employee_records q LEFT JOIN user_employees e ON e.representative_qualification_id=q.id WHERE q.request_key LIKE 'FIXTURE-REP-%' AND e.id IS NULL");
$employees=$db->query('SELECT id,representative_qualification_id FROM user_employees ORDER BY sort_no,id LIMIT 2')->fetchAll(PDO::FETCH_ASSOC);
$type=$db->query('SELECT id,category_code,qualification_name FROM institution_qualifications_types WHERE deleted_at IS NULL AND is_active=1 ORDER BY sort_no,id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if(!$user||count($employees)<2||!$type)throw new RuntimeException('Fixture 기준 데이터가 부족합니다.');
if(session_status()!==PHP_SESSION_ACTIVE)session_start();$_SESSION['user']=['id'=>$user['id'],'username'=>$user['username']];$_SESSION['auth_state']=['user_id'=>$user['id'],'status'=>'NORMAL'];
$model=new QualificationModel($db);$employeeModel=new EmployeeModel($db);$service=new QualificationEducationService($db);$actor=ActorHelper::user();$ids=[];$checks=[];
$make=function(string $employee,string $status,?string $verified,?string $validTo)use($model,$type,$actor,&$ids):string{$seed=UuidHelper::generate();$now=date('Y-m-d H:i:s');$id=$model->create(['employee_id'=>$employee,'qualification_type_id'=>$type['id'],'qualification_type_code'=>$type['category_code'],'qualification_name'=>'Fixture 대표자격','status_code'=>$status,'verified_at'=>$verified,'valid_to'=>$validTo,'request_key'=>'FIXTURE-REP-'.$seed,'created_at'=>$now,'created_by'=>$actor,'updated_at'=>$now,'updated_by'=>$actor]);$ids[]=$id;return$id;};
try{
 $pending=$make($employees[0]['id'],'PENDING_VERIFICATION',null,null);$checks['pending_blocked']=$model->eligibleRepresentativeQualification($employees[0]['id'],$pending)===null;
 $unverified=$make($employees[0]['id'],'ACTIVE',null,null);$checks['unverified_blocked']=$model->eligibleRepresentativeQualification($employees[0]['id'],$unverified)===null;
 $active=$make($employees[0]['id'],'ACTIVE',date('Y-m-d H:i:s'),date('Y-m-d',strtotime('+1 year')));$checks['active_allowed']=$model->eligibleRepresentativeQualification($employees[0]['id'],$active)!==null;
 $checks['other_owner_blocked']=$model->eligibleRepresentativeQualification($employees[1]['id'],$active)===null;$employeeModel->updateRepresentativeQualificationId($employees[0]['id'],$active);
 $row=$model->detail($active);$service->saveQualification(array_merge($row,['id'=>$active,'status_code'=>'REVOKED','request_key'=>'FIXTURE-REP-REVOKE','reason'=>'Fixture 말소']));$checks['revoked_cleared']=$db->query("SELECT representative_qualification_id IS NULL FROM user_employees WHERE id=".$db->quote($employees[0]['id']))->fetchColumn()==1;
 $expired=$make($employees[0]['id'],'ACTIVE',date('Y-m-d H:i:s'),date('Y-m-d',strtotime('-1 day')));$employeeModel->updateRepresentativeQualificationId($employees[0]['id'],$expired);$detail=$employeeModel->getById($employees[0]['id']);$checks['expired_not_valid']=(int)($detail['representative_qualification_is_valid']??1)===0&&(int)($detail['representative_qualification_requires_reassignment']??0)===1&&empty($detail['representative_qualification_name']);
}finally{
 foreach($employees as$employee)$employeeModel->updateRepresentativeQualificationId($employee['id'],$employee['representative_qualification_id']?:null);
 if($ids){$marks=implode(',',array_fill(0,count($ids),'?'));$db->prepare("DELETE FROM institution_qualifications_audits WHERE target_id IN ($marks)")->execute($ids);$db->prepare("DELETE FROM institution_qualifications_employee_records WHERE id IN ($marks)")->execute($ids);}
}
echo json_encode(['success'=>!in_array(false,$checks,true),'checks'=>$checks],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
