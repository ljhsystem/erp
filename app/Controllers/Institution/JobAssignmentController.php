<?php

namespace App\Controllers\Institution;

use App\Controllers\System\LayoutController;
use App\Services\Auth\AuthSessionService;
use App\Services\Auth\PermissionService;
use App\Services\Institution\JobAssignmentService;
use Core\DbPdo;
use Core\Session;
use PDO;

class JobAssignmentController
{
    private PDO $db;
    private JobAssignmentService $service;
    public function __construct(?PDO $pdo = null) { $this->db = $pdo ?? DbPdo::conn(); $this->service = new JobAssignmentService($this->db); }
    public function webIndex(): void { $capabilities = $this->capabilities(); ob_start(); require PROJECT_ROOT . '/app/views/institution/job-assignment/index.php'; $content = ob_get_clean(); (new LayoutController($this->db))->render(['pageTitle' => '직무·배치관리', 'content' => $content, 'pageStyles' => $pageStyles ?? '', 'pageScripts' => $pageScripts ?? '']); }
    public function apiList(): void { Session::write(); $this->respond(fn() => $this->service->list($_GET)); }
    public function apiDetail(): void { Session::write(); $this->respond(fn() => $this->service->detail(trim((string) ($_GET['employee_id'] ?? '')))); }
    public function apiOptions(): void { Session::write(); $this->respond(fn() => ['success' => true, 'data' => $this->service->options()]); }
    public function apiHistorySave(): void { $this->respond(fn() => $this->service->saveHistory($this->input())); }
    public function apiProjectSave(): void { $this->respond(fn() => $this->service->saveProject($this->input())); }
    public function apiEnd(): void { $this->respond(fn() => $this->service->endProject($this->input())); }
    public function apiCorrect(): void { $this->respond(fn() => $this->service->correct($this->input())); }
    private function input(): array { $raw=file_get_contents('php://input');$json=json_decode($raw?:'',true);return is_array($json)?$json:$_POST; }
    private function capabilities(): array { $user=(new AuthSessionService())->getCurrentUser();$userId=(string)($user['id']??'');$permissions=new PermissionService();$keys=['history_save','project_save','end','correct'];$result=[];foreach($keys as $key)$result[$key]=$userId!==''&&$permissions->hasPermission($userId,'api.institution.human_resources.job_assignment.'.$key);return$result; }
    private function respond(callable $callback): void { try { $result = $callback(); $status = 200; } catch (\InvalidArgumentException|\RuntimeException $e) { $result = ['success' => false, 'message' => $e->getMessage()]; $status = 400; } catch (\Throwable) { $result = ['success' => false, 'message' => '직무·배치 처리 중 오류가 발생했습니다.']; $status = 500; } http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
}
