<?php

namespace App\Controllers\Institution;

use App\Controllers\System\LayoutController;
use App\Services\Auth\AuthSessionService;
use App\Services\Auth\PermissionService;
use App\Services\Institution\AttendanceService;
use Core\DbPdo;
use PDO;

class AttendanceController
{
    private PDO $db; private AttendanceService $service;
    public function __construct(?PDO $pdo=null){$this->db=$pdo??DbPdo::conn();$this->service=new AttendanceService($this->db);}
    public function webIndex(): void{$attendanceOptions=$this->service->options()['data'];$capabilities=$this->capabilities();ob_start();require PROJECT_ROOT.'/app/views/institution/attendance/index.php';$content=ob_get_clean();(new LayoutController($this->db))->render(['pageTitle'=>'근태관리','content'=>$content,'pageStyles'=>$pageStyles??'','pageScripts'=>$pageScripts??'']);}
    public function apiDailyList(): void{$this->respond(fn()=>$this->service->dailyList(\Core\Helpers\DataTableRequestHelper::input(),$this->scope()));}
    public function apiMonthlyList(): void{$this->respond(fn()=>$this->service->monthlyList(\Core\Helpers\DataTableRequestHelper::input(),$this->scope()));}
    public function apiExceptionList(): void{$this->respond(fn()=>$this->service->exceptionList(\Core\Helpers\DataTableRequestHelper::input(),$this->scope()));}
    public function apiDetail(): void{$this->respond(function(){ $employee=(string)($_GET['employee_id']??'');$scope=$this->scope();if($scope!==null&&$employee!==$scope)throw new \RuntimeException('다른 직원의 근태를 조회할 수 없습니다.');return $this->service->detail($employee,(string)($_GET['work_date']??''));});}
    public function apiClosureHistories(): void{$this->respond(fn()=>$this->service->histories((string)($_GET['closure_id']??''),$this->scope()));}
    public function apiScope(): void{$this->respond(fn()=>['success'=>true,'data'=>['employee_id'=>$this->scope()]]);}
    public function apiClockSelf(): void{$in=$this->input();unset($in['employee_id']);$this->respond(fn()=>$this->service->registerSelfClock($in,$this->currentEmployeeId()));}
    public function apiClockAdmin(): void{$this->respond(fn()=>$this->service->registerAdminClock($this->input()));}
    public function apiClockInvalidate(): void{$this->respond(fn()=>$this->service->invalidateClock($this->input()));}
    public function apiRecalculate(): void{$this->respond(fn()=>$this->service->recalculate($this->input()));}
    public function apiCorrect(): void{$this->respond(fn()=>$this->service->correct($this->input()));}
    public function apiClose(): void{$this->respond(fn()=>$this->service->close($this->input()));}
    public function apiReopen(): void{$this->respond(fn()=>$this->service->reopen($this->input()));}
    public function apiOptions(): void{$this->respond(function(){ $result=$this->service->options();$scope=$this->scope();if($scope!==null)$result['data']['employees']=array_values(array_filter($result['data']['employees'],fn($row)=>$row['value']===$scope));return $result;});}
    private function scope(): ?string{$u=$this->user();$id=(string)($u['id']??'');$p=new PermissionService();if($p->hasPermission($id,'api.institution.human_resources.attendance.view_all'))return null;if($p->hasPermission($id,'api.institution.human_resources.attendance.view_self'))return $this->currentEmployeeId();throw new \RuntimeException('근태 조회 권한이 없습니다.');}
    private function currentEmployeeId(): string{$u=$this->user();return $this->service->employeeIdForUser((string)($u['id']??''));}
    private function user(): array{return (new AuthSessionService())->getCurrentUser()??[];}
    private function capabilities(): array{$id=(string)($this->user()['id']??'');$p=new PermissionService();$r=[];foreach(['view_all','view_self','clock_self','clock_admin','recalculate','correct','clock_invalidate','close','reopen','closure_histories'] as $key)$r[$key]=$id!==''&&$p->hasPermission($id,'api.institution.human_resources.attendance.'.$key);return $r;}
    private function input(): array{$raw=file_get_contents('php://input');$json=json_decode($raw?:'',true);return is_array($json)?$json:$_POST;}
    private function respond(callable $callback): void{try{$r=$callback();$status=empty($r['success'])?400:200;}catch(\InvalidArgumentException|\RuntimeException $e){$r=['success'=>false,'message'=>$e->getMessage()];$status=400;}catch(\Throwable){$r=['success'=>false,'message'=>'근태 처리 중 오류가 발생했습니다.'];$status=500;}http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
}
