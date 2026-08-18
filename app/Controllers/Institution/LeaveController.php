<?php

namespace App\Controllers\Institution;

use App\Controllers\System\LayoutController;
use App\Services\Auth\AuthSessionService;
use App\Services\Auth\PermissionService;
use App\Services\Institution\LeaveService;
use Core\DbPdo;
use PDO;

class LeaveController
{
    private PDO $db;private LeaveService $service;
    public function __construct(?PDO $pdo=null){$this->db=$pdo??DbPdo::conn();$this->service=new LeaveService($this->db);}
    public function webIndex(): void{$leaveOptions=$this->service->options()['data'];$capabilities=$this->capabilities();ob_start();require PROJECT_ROOT.'/app/views/institution/leave/index.php';$content=ob_get_clean();(new LayoutController($this->db))->render(['pageTitle'=>'휴가관리','content'=>$content,'pageStyles'=>$pageStyles??'','pageScripts'=>$pageScripts??'']);}
    public function apiList(): void{$this->respond(fn()=>($_GET['mode']??'')==='balances'?$this->service->balances($_GET,$this->scope()):$this->service->list($_GET,$this->scope()));}
    public function apiBalances(): void{$this->respond(fn()=>$this->service->balances($_GET,$this->scope()));}
    public function apiOptions(): void{$this->respond(function(){$r=$this->service->options();$scope=$this->scope();if($scope!==null)$r['data']['employees']=array_values(array_filter($r['data']['employees'],fn($e)=>$e['value']===$scope));return$r;});}
    public function apiDetail(): void{$this->respond(function(){$r=$this->service->detail((string)($_GET['id']??''));$scope=$this->scope();if($scope!==null&&$r['data']['employee_id']!==$scope)throw new \RuntimeException('다른 직원의 휴가를 조회할 수 없습니다.');return$r;});}
    public function apiSave(): void{$in=$this->input();unset($in['employee_id']);$this->respond(fn()=>$this->service->save($in,$this->employeeId()));}
    public function apiSubmit(): void{$in=$this->input();$this->respond(fn()=>$this->service->submit((string)($in['id']??'')));}
    public function apiWithdraw(): void{$in=$this->input();$this->respond(fn()=>$this->service->withdraw((string)($in['approval_request_id']??'')));}
    public function apiCancel(): void{$in=$this->input();$this->respond(fn()=>$this->service->cancel((string)($in['id']??''),(string)($in['request_key']??''),(string)($in['reason']??'')));}
    public function apiGrant(): void{$this->respond(fn()=>$this->service->grant($this->input()));}
    public function apiAdjust(): void{$this->respond(fn()=>$this->service->adjust($this->input()));}
    public function apiTypeSave(): void{$this->respond(fn()=>$this->service->typeSave($this->input()));}
    public function apiExcel(): void{$this->service->excel($_GET,$this->scope());}
    private function scope(): ?string{$id=(string)($this->user()['id']??'');$p=new PermissionService();if($p->hasPermission($id,'api.institution.human_resources.leave.view_all'))return null;if($p->hasPermission($id,'api.institution.human_resources.leave.view_self'))return$this->employeeId();throw new \RuntimeException('휴가 조회 권한이 없습니다.');}
    private function employeeId(): string{return$this->service->employeeIdForUser((string)($this->user()['id']??''));}
    private function user(): array{return(new AuthSessionService())->getCurrentUser()??[];}
    private function capabilities(): array{$id=(string)($this->user()['id']??'');$p=new PermissionService();$out=[];foreach(['view_self','view_all','detail','save','submit','withdraw','cancel','grant','adjust','type_save','excel']as$key)$out[$key]=$id!==''&&$p->hasPermission($id,'api.institution.human_resources.leave.'.$key);return$out;}
    private function input(): array{$raw=file_get_contents('php://input');$json=json_decode($raw?:'',true);return is_array($json)?$json:$_POST;}
    private function respond(callable$cb): void{try{$r=$cb();$status=empty($r['success'])?400:200;}catch(\InvalidArgumentException|\RuntimeException$e){$r=['success'=>false,'message'=>$e->getMessage()];$status=400;}catch(\Throwable){$r=['success'=>false,'message'=>'휴가 처리 중 오류가 발생했습니다.'];$status=500;}http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
}
