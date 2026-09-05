<?php
namespace App\Controllers\Site;
use App\Controllers\System\LayoutController;
use App\Services\Site\SalesService;
use Core\DbPdo;
use InvalidArgumentException;
use Throwable;

class SalesController
{
    private SalesService $service;private LayoutController $layout;
    public function __construct(){$pdo=DbPdo::conn();$this->service=new SalesService($pdo);$this->layout=new LayoutController($pdo);}
    public function index():void{ob_start();require PROJECT_ROOT.'/app/views/site/sales/index.php';$content=ob_get_clean();$this->layout->render(['pageTitle'=>'영업관리','content'=>$content,'pageStyles'=>$pageStyles??'','pageScripts'=>$pageScripts??'','layoutOptions'=>$layoutOptions??[]]);}
    public function apiList():void{$this->respond(fn()=>['success'=>true,'data'=>$this->service->list($_GET)]);}
    public function apiDashboard():void{$this->respond(fn()=>['success'=>true,'data'=>$this->service->dashboard()]);}
    public function apiOptions():void{$this->respond(fn()=>['success'=>true,'data'=>$this->service->options()]);}
    public function apiDetail():void{$this->respond(fn()=>['success'=>true,'data'=>$this->service->detail(trim((string)($_GET['id']??'')))]);}
    public function apiSaveOrganization():void{$this->respond(fn()=>$this->service->saveOrganization($this->input()));}
    public function apiAddPerson():void{$this->respond(fn()=>$this->service->addPerson($this->input()));}
    public function apiAddActivity():void{$this->respond(fn()=>$this->service->addActivity($this->input()));}
    public function apiAddOpportunity():void{$this->respond(fn()=>$this->service->addOpportunity($this->input()));}
    public function apiAddFollowup():void{$this->respond(fn()=>$this->service->addFollowup($this->input()));}
    public function apiCompleteFollowup():void{$this->respond(fn()=>$this->service->completeFollowup($this->input()));}
    private function input():array{$row=json_decode(file_get_contents('php://input')?:'{}',true);return is_array($row)?$row:$_POST;}
    private function respond(callable$callback):void{try{$this->json($callback());}catch(InvalidArgumentException$e){$this->json(['success'=>false,'message'=>$e->getMessage()],422);}catch(Throwable){$this->json(['success'=>false,'message'=>'영업관리 처리 중 오류가 발생했습니다.'],500);}}
    private function json(array$payload,int$status=200):void{http_response_code($status);header('Content-Type: application/json; charset=UTF-8');echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
}
