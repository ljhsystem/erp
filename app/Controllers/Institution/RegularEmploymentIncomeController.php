<?php
namespace App\Controllers\Institution;
use App\Controllers\System\LayoutController;use App\Services\Institution\RegularEmploymentIncomeService;use Core\DbPdo;use PDO;
class RegularEmploymentIncomeController
{
    private PDO$pdo;private RegularEmploymentIncomeService$service;
    public function __construct(?PDO$pdo=null){$this->pdo=$pdo??DbPdo::conn();$this->service=new RegularEmploymentIncomeService($this->pdo);}
    public function webIndex():void{ob_start();require PROJECT_ROOT.'/app/views/institution/regular-employment-income/index.php';$content=ob_get_clean();(new LayoutController($this->pdo))->render(['pageTitle'=>'상용근로소득','content'=>$content,'pageStyles'=>$pageStyles??'','pageScripts'=>$pageScripts??'']);}
    public function apiList():void{$this->respond(fn()=>$this->service->page($_GET));}
    public function apiDetail():void{$this->respond(fn()=>$this->service->detail(trim((string)($_GET['id']??''))));}
    public function apiEligibleEmployees():void{$this->respond(fn()=>$this->service->eligibleEmployees(trim((string)($_GET['income_year_month']??''))));}
    public function apiSave():void{$this->respond(fn()=>$this->service->save($this->input()));}
    public function apiSubmit():void{$this->respond(fn()=>$this->service->submit(trim((string)($this->input()['id']??''))));}
    public function apiWithdraw():void{$this->respond(fn()=>$this->service->withdraw(trim((string)($this->input()['request_id']??''))));}
    public function apiDelete():void{$this->respond(fn()=>$this->service->delete(trim((string)($this->input()['id']??''))));}
    private function input():array{$raw=file_get_contents('php://input');$d=is_string($raw)?json_decode($raw,true):null;return is_array($d)?$d:$_POST;}
    private function respond(callable$fn):void{try{$r=$fn();$status=empty($r['success'])?400:200;}catch(\InvalidArgumentException|\RuntimeException$e){$r=['success'=>false,'message'=>$e->getMessage()];$status=400;}catch(\Throwable$e){$r=['success'=>false,'message'=>'처리 중 오류가 발생했습니다.'];$status=500;}http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
}
