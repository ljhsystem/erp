<?php

namespace App\Controllers\Institution;

use App\Services\Institution\PayComponentService;
use Core\DbPdo;
use PDO;

final class PayComponentController
{
    private PayComponentService $service;

    public function __construct(?PDO $db = null)
    {
        $this->service = new PayComponentService($db ?? DbPdo::conn());
    }

    public function apiOptions(): void
    {
        try {
            $data = $this->service->optionsForDate(trim((string) ($_GET['effective_date'] ?? '')));
            $result = ['success' => true, 'data' => $data, 'message' => ''];
            $status = 200;
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            $result = ['success' => false, 'data' => [], 'message' => $exception->getMessage()];
            $status = 400;
        } catch (\Throwable $exception) {
            $result = ['success' => false, 'data' => [], 'message' => '급여항목 조회 중 오류가 발생했습니다.'];
            $status = 500;
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function apiList(): void { $this->respond(fn(): array => $this->service->list((string)($_GET['include_deleted']??'')==='1')); }
    public function apiDetail(): void { $this->respond(fn(): array => $this->service->detail((string)($_GET['id']??''))); }
    public function apiSave(): void { $this->respond(fn(): array => $this->service->save($this->input())); }
    public function apiDelete(): void { $input=$this->input();$this->respond(fn(): array => $this->service->delete((string)($input['id']??''))); }
    public function apiReorder(): void { $input=$this->input();$this->respond(fn(): array => $this->service->reorder(is_array($input['changes']??null)?$input['changes']:[])); }

    private function input(): array { $raw=file_get_contents('php://input');$decoded=is_string($raw)?json_decode($raw,true):null;return is_array($decoded)?$decoded:$_POST; }
    private function respond(callable $callback): void { \Core\Session::write();try{$result=$callback();$status=empty($result['success'])?400:200;}catch(\InvalidArgumentException|\RuntimeException $exception){$result=['success'=>false,'data'=>[],'message'=>$exception->getMessage()];$status=400;}catch(\Throwable){$result=['success'=>false,'data'=>[],'message'=>'급여항목 처리 중 오류가 발생했습니다.'];$status=500;}http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
}
