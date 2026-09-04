<?php

namespace App\Controllers\Institution;

use App\Controllers\System\LayoutController;
use App\Services\Auth\AuthSessionService;
use App\Services\Auth\PermissionService;
use App\Services\Institution\LeaveService;
use Core\DbPdo;
use PDO;

class LeaveController
{
    private PDO $db;
    private LeaveService $service;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? DbPdo::conn();
        $this->service = new LeaveService($this->db);
    }

    public function webIndex(): void
    {
        $leaveOptions = $this->service->options()['data'];
        $capabilities = $this->capabilities();
        ob_start();
        require PROJECT_ROOT . '/app/views/institution/leave/index.php';
        $content = ob_get_clean();
        (new LayoutController($this->db))->render([
            'pageTitle' => '휴가관리',
            'content' => $content,
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
        ]);
    }

    public function apiStatusList(): void
    {
        $query = \Core\Helpers\DataTableRequestHelper::input();
        $this->releaseSession();
        $this->respond(fn() => $this->service->list($query, null));
    }

    public function apiBalanceList(): void
    {
        $query = \Core\Helpers\DataTableRequestHelper::input();
        $this->releaseSession();
        $this->respond(fn() => $this->service->balances($query, null));
    }

    public function apiOptions(): void
    {
        $this->releaseSession();
        $this->respond(fn() => $this->service->options());
    }

    public function apiDetail(): void
    {
        $this->respond(fn() => $this->service->detail((string) ($_GET['id'] ?? '')));
    }

    public function apiGrant(): void
    {
        $this->respond(fn() => $this->service->grant($this->input()));
    }

    public function apiAdjust(): void
    {
        $this->respond(fn() => $this->service->adjust($this->input()));
    }

    public function apiTypeSave(): void
    {
        $this->respond(fn() => $this->service->typeSave($this->input()));
    }

    private function user(): array
    {
        return (new AuthSessionService())->getCurrentUser() ?? [];
    }

    private function capabilities(): array
    {
        $id = (string) ($this->user()['id'] ?? '');
        $permission = new PermissionService();
        $output = [];
        foreach (['grant', 'adjust', 'type_save'] as $key) {
            $output[$key] = $id !== '' && $permission->hasPermission(
                $id,
                'api.institution.human_resources.leave.' . $key
            );
        }
        return $output;
    }

    private function input(): array
    {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw ?: '', true);
        return is_array($json) ? $json : $_POST;
    }

    private function releaseSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    private function respond(callable $callback): void
    {
        try {
            $response = $callback();
            $status = empty($response['success']) ? 400 : 200;
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            $response = ['success' => false, 'message' => $exception->getMessage()];
            $status = 400;
        } catch (\Throwable) {
            $response = ['success' => false, 'message' => '휴가 처리 중 오류가 발생했습니다.'];
            $status = 500;
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
