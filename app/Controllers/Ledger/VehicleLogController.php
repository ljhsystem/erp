<?php

namespace App\Controllers\Ledger;

use App\Controllers\System\LayoutController;
use App\Services\Ledger\VehicleLogService;
use Core\DbPdo;
use InvalidArgumentException;
use Throwable;

class VehicleLogController
{
    private VehicleLogService $service; private LayoutController $layout;
    public function __construct(){ $pdo=DbPdo::conn();$this->service=new VehicleLogService($pdo);$this->layout=new LayoutController($pdo); }
    public function index():void{ob_start();require PROJECT_ROOT.'/app/views/ledger/book/vehicle-log/index.php';$content=ob_get_clean();$this->layout->render(['pageTitle'=>'차량운행기록부','content'=>$content,'pageStyles'=>$pageStyles??'','pageScripts'=>$pageScripts??'','layoutOptions'=>$layoutOptions??[]]);}
    public function apiList():void{$this->respond(function(){$r=$this->service->list($_GET);return['success'=>true,'data'=>$r['rows'],'summary'=>$r['summary']];});}
    public function apiDetail():void{$this->respond(function(){$r=$this->service->detail(trim((string)($_GET['id']??'')));if(!$r)throw new InvalidArgumentException('운행기록을 찾을 수 없습니다.');return['success'=>true,'data'=>$r];});}
    public function apiOptions():void{$this->respond(fn()=>['success'=>true,'data'=>$this->service->options()]);}
    public function apiSave():void{$this->respond(fn()=>$this->service->saveTrip($this->input()));}
    public function apiSaveVehicle():void{$this->respond(fn()=>$this->service->saveVehicle($this->input()));}
    public function apiDelete():void{$this->respond(fn()=>$this->service->delete(trim((string)($this->input()['id']??''))));}
    public function apiTrashList():void{$this->respond(fn()=>['success'=>true,'data'=>$this->service->trash($_GET)]);}
    public function apiRestore():void{$this->respond(fn()=>$this->service->restore(trim((string)($this->input()['id']??''))));}
    public function apiTemplate():void{$this->service->download(true,$_GET['columns']??null);}
    public function apiExcel():void{$this->service->download(false,$_GET['columns']??null);}
    public function apiExcelUpload():void{$this->respond(function(){if(!isset($_FILES['excel'])||!is_uploaded_file($_FILES['excel']['tmp_name']))throw new InvalidArgumentException('업로드할 엑셀 파일을 선택해 주세요.');return$this->service->upload($_FILES['excel']['tmp_name']);});}
    private function input():array{$raw=json_decode(file_get_contents('php://input')?:'{}',true);return is_array($raw)?$raw:$_POST;}
    private function respond(callable$callback):void{try{$this->json($callback());}catch(InvalidArgumentException$e){$this->json(['success'=>false,'message'=>$e->getMessage()],422);}catch(Throwable$e){$this->json(['success'=>false,'message'=>'차량운행기록부 처리 중 오류가 발생했습니다.'],500);}}
    private function json(array$payload,int$status=200):void{http_response_code($status);header('Content-Type: application/json; charset=UTF-8');echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
}
