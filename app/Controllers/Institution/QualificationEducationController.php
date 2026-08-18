<?php
namespace App\Controllers\Institution;
use App\Controllers\System\LayoutController;
use App\Services\Auth\AuthSessionService;
use App\Services\Auth\PermissionService;
use App\Services\Institution\QualificationEducationService;
use Core\DbPdo;
use PDO;
class QualificationEducationController
{
 private PDO $db;private QualificationEducationService $service;
 public function __construct(?PDO $pdo=null){$this->db=$pdo??DbPdo::conn();$this->service=new QualificationEducationService($this->db);}
 public function webIndex(): void{$scope=$this->scope();$bootstrap=$this->service->options($scope)['data'];$capabilities=$this->capabilities();ob_start();require PROJECT_ROOT.'/app/views/institution/qualification-education/index.php';$content=ob_get_clean();(new LayoutController($this->db))->render(['pageTitle'=>'자격·교육관리','content'=>$content,'pageStyles'=>$pageStyles??'','pageScripts'=>$pageScripts??'']);}
 public function apiOptions(): void{$this->respond(fn()=>$this->service->options($this->scope()));}
 public function apiQualificationList(): void{$this->respond(fn()=>$this->service->qualificationList($_GET,$this->scope()));}
 public function apiQualificationDetail(): void{$this->respond(fn()=>$this->service->qualificationDetail((string)($_GET['id']??''),$this->scope()));}
 public function apiQualificationSave(): void{$this->respond(fn()=>$this->service->saveQualification($this->input(),$_FILES));}
 public function apiQualificationVerify(): void{$this->respond(fn()=>$this->service->verifyQualification($this->input()));}
 public function apiQualificationRenew(): void{$this->respond(fn()=>$this->service->renewQualification($this->input(),$_FILES));}
 public function apiQualificationDelete(): void{$this->respond(fn()=>$this->service->deleteQualification($this->input()));}
 public function apiEducationList(): void{$this->respond(fn()=>$this->service->educationList($_GET,$this->scope()));}
 public function apiEducationDetail(): void{$this->respond(fn()=>$this->service->educationDetail((string)($_GET['id']??''),$this->scope()));}
 public function apiEducationSave(): void{$this->respond(fn()=>$this->service->saveEducation($this->input(),$_FILES));}
 public function apiEducationDelete(): void{$this->respond(fn()=>$this->service->deleteEducation($this->input()));}
 public function apiCourseSave(): void{$this->respond(fn()=>$this->service->saveCourse($this->input()));}
 public function apiExcel(): void{$this->service->excel($_GET,$this->scope());}
 private function scope(): ?string{$user=$this->user();$id=(string)($user['id']??'');$p=new PermissionService();if($p->hasPermission($id,'api.institution.human_resources.qualification_education.view_all'))return null;if($p->hasPermission($id,'api.institution.human_resources.qualification_education.view_self'))return $this->service->employeeIdForUser($id);throw new \RuntimeException('자격·교육 조회 권한이 없습니다.');}
 private function capabilities(): array{$id=(string)($this->user()['id']??'');$p=new PermissionService();$out=[];foreach(['view_self','view_all','save','delete','verify','renew','course_manage','education_manage','excel'] as $key)$out[$key]=$id!==''&&$p->hasPermission($id,'api.institution.human_resources.qualification_education.'.$key);return $out;}
 private function user(): array{return (new AuthSessionService())->getCurrentUser()??[];}
 private function input(): array{if($_POST)return $_POST;$raw=file_get_contents('php://input');$json=json_decode($raw?:'',true);return is_array($json)?$json:[];}
 private function respond(callable $callback): void{try{$result=$callback();$status=empty($result['success'])?400:200;}catch(\InvalidArgumentException|\RuntimeException $e){$result=['success'=>false,'message'=>$e->getMessage()];$status=400;}catch(\Throwable){$result=['success'=>false,'message'=>'자격·교육 처리 중 오류가 발생했습니다.'];$status=500;}http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
}
