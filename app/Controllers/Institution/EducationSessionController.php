<?php

namespace App\Controllers\Institution;

use App\Services\Auth\AuthSessionService;
use App\Services\Institution\EducationSessionService;
use App\Services\Institution\QualificationEducationService;
use Core\DbPdo;
use Core\Helpers\DataTableRequestHelper;
use PDO;

class EducationSessionController
{
    private PDO $db;
    private EducationSessionService $service;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? DbPdo::conn();
        $this->service = new EducationSessionService($this->db);
    }

    public function apiList(): void { $query = DataTableRequestHelper::input(); $this->releaseSession(); $this->respond(fn() => $this->service->list($query)); }
    public function apiDetail(): void { $this->respond(fn() => $this->service->detail((string) ($_GET['id'] ?? ''))); }
    public function apiSave(): void { $input = $this->input(); $this->respond(fn() => $this->service->save($input)); }
    public function apiTransition(): void { $input = $this->input(); $this->respond(fn() => $this->service->transition($input)); }
    public function apiTargetList(): void { $query = DataTableRequestHelper::input(); $sessionId = (string) ($query['session_id'] ?? $_GET['session_id'] ?? ''); $this->releaseSession(); $this->respond(fn() => $this->service->targetList($sessionId, $query)); }
    public function apiTargetAdd(): void { $input = $this->input(); $this->respond(fn() => $this->service->addTargets($input)); }
    public function apiTargetRemove(): void { $input = $this->input(); $this->respond(fn() => $this->service->removeTarget($input)); }
    public function apiTargetOutcome(): void { $input = $this->input(); $this->respond(fn() => $this->service->updateOutcome($input)); }

    public function apiTargetAcknowledge(): void
    {
        $input = $this->input();
        $user = (new AuthSessionService())->getCurrentUser() ?? [];
        $employeeId = (new QualificationEducationService($this->db))->employeeIdForUser((string) ($user['id'] ?? ''));
        $this->respond(fn() => $this->service->acknowledge((string) ($input['id'] ?? ''), $employeeId, $input));
    }

    private function input(): array
    {
        if ($_POST) return $_POST;
        $raw = file_get_contents('php://input'); $json = json_decode($raw ?: '', true);
        return is_array($json) ? $json : [];
    }

    private function releaseSession(): void { if (session_status() === PHP_SESSION_ACTIVE) session_write_close(); }

    private function respond(callable $callback): void
    {
        try { $result = $callback(); $status = empty($result['success']) ? 400 : 200; }
        catch (\InvalidArgumentException|\RuntimeException $exception) { $result = ['success' => false, 'message' => $exception->getMessage()]; $status = 400; }
        catch (\Throwable) { $result = ['success' => false, 'message' => '교육 일정 처리 중 오류가 발생했습니다.']; $status = 500; }
        http_response_code($status); header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
