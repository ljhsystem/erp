<?php

namespace App\Controllers\Approval;

use App\Controllers\System\LayoutController;
use App\Services\Approval\LeaveRequestService;
use Core\DbPdo;
use PDO;
use Throwable;

class LeaveRequestController
{
    private PDO $pdo;
    private LeaveRequestService $service;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbPdo::conn();
        $this->service = new LeaveRequestService($this->pdo);
    }

    public function webIndex(): void
    {
        $leaveOptions = $this->service->options()['data'];
        ob_start();
        require PROJECT_ROOT . '/app/views/approval/leave-request/index.php';
        $content = ob_get_clean();
        (new LayoutController($this->pdo))->render([
            'pageTitle' => '휴가신청',
            'content' => $content,
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
        ]);
    }

    public function apiList(): void { $this->respond(fn () => $this->service->list()); }
    public function apiOptions(): void { $this->respond(fn () => $this->service->options()); }
    public function apiDetail(): void { $this->respond(fn () => $this->service->detail(trim((string) ($_GET['id'] ?? '')))); }
    public function apiSave(): void { $this->respond(fn () => $this->service->save($this->input())); }
    public function apiSaveAndSubmit(): void { $this->respond(fn () => $this->service->saveAndSubmit($this->input())); }
    public function apiSubmit(): void { $this->respond(fn () => $this->service->submit(trim((string) ($this->input()['id'] ?? '')))); }
    public function apiWithdraw(): void { $this->respond(fn () => $this->service->withdraw(trim((string) ($this->input()['approval_request_id'] ?? '')))); }
    public function apiCancelRequest(): void { $this->respond(fn () => $this->service->cancelRequest($this->input())); }

    private function input(): array
    {
        $raw = file_get_contents('php://input');
        $json = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($json) ? $json : $_POST;
    }

    private function respond(callable $callback): void
    {
        try {
            $result = $callback();
            $status = empty($result['success']) ? 400 : 200;
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            $result = ['success' => false, 'message' => $exception->getMessage()];
            $status = 400;
        } catch (Throwable) {
            $result = ['success' => false, 'message' => '휴가 신청 처리 중 오류가 발생했습니다.'];
            $status = 500;
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
