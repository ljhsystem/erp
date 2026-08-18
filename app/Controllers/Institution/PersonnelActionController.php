<?php

namespace App\Controllers\Institution;

use App\Controllers\System\LayoutController;
use App\Services\Institution\PersonnelActionService;
use Core\DbPdo;
use PDO;
use PDOException;

class PersonnelActionController
{
    private PDO $pdo;
    private PersonnelActionService $service;
    public function __construct(?PDO $pdo=null){$this->pdo=$pdo??DbPdo::conn();$this->service=new PersonnelActionService($this->pdo);}
    public function webIndex(): void { ob_start();require PROJECT_ROOT.'/app/views/institution/personnel-action/index.php';$content=ob_get_clean();(new LayoutController($this->pdo))->render(['pageTitle'=>'인사발령관리','content'=>$content,'pageStyles'=>$pageStyles??'','pageScripts'=>$pageScripts??'']); }
    public function apiList(): void {$this->respond(fn()=>$this->service->list($_GET));}
    public function apiOptions(): void {$this->respond(fn()=>$this->service->modalOptions());}
    public function apiDetail(): void {$this->respond(fn()=>$this->service->detail(trim((string)($_GET['id']??''))));}
    public function apiSave(): void {$this->respond(fn()=>$this->service->save($this->input()));}
    public function apiReorder(): void {$input=$this->input();$this->respond(fn()=>$this->service->reorder(is_array($input['changes']??null)?$input['changes']:[]));}
    public function apiSubmit(): void {$this->respond(fn()=>$this->service->submit($this->id()));}
    public function apiWithdraw(): void {$this->respond(fn()=>$this->service->withdraw(trim((string)($this->input()['request_id']??''))));}
    public function apiApply(): void {$this->respond(fn()=>$this->service->apply($this->id()));}
    public function apiDelete(): void {$this->respond(fn()=>$this->service->delete($this->id()));}
    public function apiTrashList(): void {$this->respond(fn()=>$this->service->trash($_GET));}
    public function apiRestore(): void {$this->respond(fn()=>$this->service->restore($this->id()));}
    public function apiPurge(): void {$this->respond(fn()=>$this->service->purge($this->id()));}
    public function apiPurgeBulk(): void {$input=$this->input();$this->respond(fn()=>$this->service->purgeMany(is_array($input['ids']??null)?$input['ids']:[]));}
    public function apiPurgeAll(): void {$this->respond(fn()=>$this->service->purgeAll());}
    private function input(): array {$raw=file_get_contents('php://input');$data=is_string($raw)?json_decode($raw,true):null;return is_array($data)?$data:$_POST;}
    private function id(): string {return trim((string)($this->input()['id']??''));}
    private function respond(callable $callback): void {try{$result=$callback();$status=empty($result['success'])?400:200;}catch(\InvalidArgumentException|\RuntimeException$e){$unsafe=$e instanceof PDOException||$e->getPrevious() instanceof PDOException||str_contains($e->getMessage(),'SQLSTATE');$result=['success'=>false,'message'=>$unsafe?'인사발령 처리 중 오류가 발생했습니다.':$e->getMessage()];$status=$unsafe?500:400;}catch(\Throwable$e){$result=['success'=>false,'message'=>'인사발령 처리 중 오류가 발생했습니다.'];$status=500;}http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
}
