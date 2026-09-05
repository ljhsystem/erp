<?php

namespace App\Controllers\Ledger;

use App\Controllers\System\LayoutController;
use App\Services\Ledger\InventoryBalanceService;
use Core\DbPdo;
use InvalidArgumentException;
use Throwable;

class InventoryBalanceController
{
    private InventoryBalanceService $service;
    private LayoutController $layout;
    public function __construct(){ $pdo=DbPdo::conn();$this->service=new InventoryBalanceService($pdo);$this->layout=new LayoutController($pdo); }
    public function index(): void { ob_start();require PROJECT_ROOT.'/app/views/ledger/inventory-balances/index.php';$content=ob_get_clean();$this->layout->render(['pageTitle'=>$pageTitle??'재고관리','content'=>$content,'pageStyles'=>$pageStyles??'','pageScripts'=>$pageScripts??'','layoutOptions'=>$layoutOptions??[]]); }
    public function apiList(): void { $this->respond(fn()=>['success'=>true,'data'=>$this->service->getList(['company_id'=>trim((string)($_GET['company_id']??'')),'fiscal_year'=>trim((string)($_GET['fiscal_year']??''))])]); }
    public function apiDetail(): void { $this->respond(function(){ $row=$this->service->getDetail(trim((string)($_GET['id']??'')));if(!$row)throw new InvalidArgumentException('재고관리 문서를 찾을 수 없습니다.');return ['success'=>true,'data'=>$row];}); }
    public function apiOptions(): void { $this->respond(fn()=>['success'=>true,'data'=>$this->service->options()]); }
    public function apiSave(): void { $this->respond(fn()=>$this->service->save($this->input())); }
    public function apiDelete(): void { $this->respond(fn()=>$this->service->delete(trim((string)($this->input()['id']??'')))); }
    public function apiConfirm(): void { $this->respond(fn()=>$this->service->confirm(trim((string)($this->input()['id']??'')),true)); }
    public function apiCancelConfirm(): void { $this->respond(fn()=>$this->service->confirm(trim((string)($this->input()['id']??'')),false)); }
    private function input(): array { $raw=json_decode(file_get_contents('php://input')?:'{}',true);return is_array($raw)?$raw:$_POST; }
    private function respond(callable $callback): void { try{$this->json($callback());}catch(InvalidArgumentException $e){$this->json(['success'=>false,'message'=>$e->getMessage()],422);}catch(Throwable){$this->json(['success'=>false,'message'=>'재고관리 처리 중 오류가 발생했습니다.'],500);} }
    private function json(array $payload,int $status=200): void { http_response_code($status);header('Content-Type: application/json; charset=UTF-8');echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit; }
}
