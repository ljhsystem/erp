<?php

namespace App\Controllers\Approval;

use App\Controllers\System\LayoutController;
use App\Services\Approval\ApprovalInboxService;
use Core\DbPdo;
use PDO;

class ApprovalInboxController
{
    private PDO $pdo;
    private ApprovalInboxService $service;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbPdo::conn();
        $this->service = new ApprovalInboxService($this->pdo);
    }

    public function webIndex(): void
    {
        ob_start();
        require PROJECT_ROOT . '/app/views/approval/inbox/index.php';
        $content = ob_get_clean();
        (new LayoutController($this->pdo))->render([
            'pageTitle' => '결재함',
            'content' => $content,
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
        ]);
    }

    public function apiList(): void
    {
        $this->respond(fn(): array => $this->service->list(\Core\Helpers\DataTableRequestHelper::input()), '결재함을 조회할 수 없습니다.');
    }

    public function apiDetail(): void
    {
        $this->respond(
            fn(): array => $this->service->detail(trim((string) ($_GET['request_id'] ?? ''))),
            '결재문서를 불러올 수 없습니다.'
        );
    }

    public function apiAct(): void
    {
        $this->respond(function (): array {
            $input = $this->input();
            return $this->service->act(
                trim((string) ($input['step_id'] ?? '')),
                trim((string) ($input['decision'] ?? '')),
                isset($input['comment']) ? (string) $input['comment'] : null
            );
        }, '결재 처리 중 오류가 발생했습니다.');
    }

    private function input(): array
    {
        $raw = file_get_contents('php://input');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : $_POST;
    }

    private function respond(callable $callback, string $failureMessage): void
    {
        try {
            $result = $callback();
            $status = empty($result['success']) ? 400 : 200;
        } catch (\InvalidArgumentException $exception) {
            $result = ['success' => false, 'message' => $exception->getMessage()];
            $status = 400;
        } catch (\RuntimeException $exception) {
            $result = ['success' => false, 'message' => $failureMessage];
            $status = 400;
        } catch (\Throwable $exception) {
            $result = ['success' => false, 'message' => $failureMessage];
            $status = 500;
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
