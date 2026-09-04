<?php
namespace App\Controllers\Main\Settings;

use App\Services\Auth\AuthSessionService;
use App\Services\Auth\UserPermissionService;
use Core\DbPdo;
use Core\Session;

class UserPermissionController
{
    private UserPermissionService $service;
    public function __construct(){$this->service=new UserPermissionService(DbPdo::conn());}
    public function apiList(): void
    {
        $actorId = $this->actorId();
        Session::write();
        $this->respond(fn() => $this->service->listUsers($actorId));
    }

    public function apiDetail(): void
    {
        $actorId = $this->actorId();
        $input = $this->input();
        Session::write();
        $this->respond(fn() => $this->service->detail($actorId, trim((string) ($input['user_id'] ?? ''))));
    }
    public function apiSave():void{$this->respond(fn()=>$this->service->save($this->actorId(),$this->input()),'개인권한이 저장되었습니다.');}
    private function actorId():string{$id=trim((string)((new AuthSessionService())->getCurrentUserId()??''));if($id==='')throw new \InvalidArgumentException('로그인이 필요합니다.');return $id;}
    private function input():array{$json=json_decode(file_get_contents('php://input')?:'[]',true);return is_array($json)&&$json!==[]?$json:$_POST;}
    private function respond(callable $callback,string $message=''):void{header('Content-Type: application/json; charset=utf-8');try{echo json_encode(['success'=>true,'data'=>$callback(),'message'=>$message],JSON_UNESCAPED_UNICODE);}catch(\InvalidArgumentException $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}catch(\Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'message'=>'개인권한 처리 중 오류가 발생했습니다.'],JSON_UNESCAPED_UNICODE);}}
}
