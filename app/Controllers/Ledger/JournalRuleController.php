<?php

namespace App\Controllers\Ledger;

use App\Controllers\System\LayoutController;
use App\Services\Ledger\JournalRuleService;
use Core\DbPdo;
use PDO;

class JournalRuleController
{
    private JournalRuleService $service;
    private LayoutController $layout;

    public function __construct(?PDO $pdo = null)
    {
        $pdo = $pdo ?? DbPdo::conn();
        $this->service = new JournalRuleService($pdo);
        $this->layout = new LayoutController($pdo);
    }

    public function index(): void
    {
        $this->renderPage('/app/views/ledger/journal_rules/index.php', [
            'pageTitle' => '분개규칙',
        ]);
    }

    public function apiList(): void
    {
        $this->json([
            'success' => true,
            'data' => $this->service->getList($this->filters()),
        ]);
    }

    public function apiDetail(): void
    {
        $id = trim((string) ($_GET['id'] ?? ''));
        if ($id === '') {
            $this->json(['success' => false, 'message' => '분개규칙 ID가 없습니다.'], 400);
            return;
        }

        $row = $this->service->getById($id, !empty($_GET['include_deleted']));
        $this->json(
            $row
                ? ['success' => true, 'data' => $row]
                : ['success' => false, 'message' => '분개규칙을 찾을 수 없습니다.'],
            $row ? 200 : 404
        );
    }

    public function apiSave(): void
    {
        try {
            $this->json($this->service->save($_POST));
        } catch (\InvalidArgumentException $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => '분개규칙 처리 중 오류가 발생했습니다.'], 500);
        }
    }

    public function apiStatus(): void
    {
        $id = trim((string) ($_POST['id'] ?? ''));
        $isActive = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 0;

        if ($id === '') {
            $this->json(['success' => false, 'message' => '분개규칙 ID는 필수입니다.'], 400);
            return;
        }

        $this->json($this->service->updateStatus($id, $isActive === 1 ? 1 : 0));
    }

    public function apiReorder(): void
    {
        $input = json_decode(file_get_contents('php://input') ?: '{}', true);
        $changes = is_array($input['changes'] ?? null) ? $input['changes'] : [];

        if ($changes === []) {
            $this->json(['success' => false, 'message' => '정렬 데이터가 없습니다.'], 400);
            return;
        }

        $this->json($this->service->reorder($changes));
    }

    public function apiDelete(): void
    {
        $payload = $this->requestPayload();
        $ids = $this->requestIds($payload);
        if (count($ids) > 1) {
            $this->json($this->service->softDeleteBulk($ids));
            return;
        }

        $id = trim((string) ($payload['id'] ?? $ids[0] ?? ''));
        $this->json(
            $id !== ''
                ? $this->service->softDelete($id)
                : ['success' => false, 'message' => '분개규칙 ID가 없습니다.'],
            $id !== '' ? 200 : 400
        );
    }

    public function apiTrashList(): void
    {
        $this->json(['success' => true, 'data' => $this->service->getTrashList()]);
    }

    public function apiRestore(): void
    {
        $payload = $this->requestPayload();
        $id = trim((string) ($payload['id'] ?? ''));

        $this->json(
            $id !== ''
                ? $this->service->restore($id)
                : ['success' => false, 'message' => '분개규칙 ID가 없습니다.'],
            $id !== '' ? 200 : 400
        );
    }

    public function apiRestoreBulk(): void
    {
        $ids = $this->requestIds($this->requestPayload());
        $this->json(
            $ids !== []
                ? $this->service->restoreBulk($ids)
                : ['success' => false, 'message' => '복원할 분개규칙 ID가 없습니다.'],
            $ids !== [] ? 200 : 400
        );
    }

    public function apiRestoreAll(): void
    {
        $this->json($this->service->restoreAll());
    }

    public function apiPurge(): void
    {
        $payload = $this->requestPayload();
        $id = trim((string) ($payload['id'] ?? ''));

        $this->json(
            $id !== ''
                ? $this->service->hardDelete($id)
                : ['success' => false, 'message' => '분개규칙 ID가 없습니다.'],
            $id !== '' ? 200 : 400
        );
    }

    public function apiPurgeBulk(): void
    {
        $ids = $this->requestIds($this->requestPayload());
        $this->json(
            $ids !== []
                ? $this->service->hardDeleteBulk($ids)
                : ['success' => false, 'message' => '영구삭제할 분개규칙 ID가 없습니다.'],
            $ids !== [] ? 200 : 400
        );
    }

    public function apiPurgeAll(): void
    {
        $this->json($this->service->hardDeleteAll());
    }

    private function filters(): array
    {
        $filters = json_decode((string) ($_GET['filters'] ?? '[]'), true);
        return is_array($filters) ? $filters : [];
    }

    private function requestPayload(): array
    {
        $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
        $payload = is_array($payload) ? $payload : [];

        return array_merge($payload, $_POST);
    }

    private function requestIds(array $payload): array
    {
        return array_values(array_filter(array_map(
            'strval',
            is_array($payload['ids'] ?? null) ? $payload['ids'] : []
        )));
    }

    private function renderPage(string $viewPath, array $params = []): void
    {
        if ($params !== []) {
            extract($params, EXTR_SKIP);
        }

        ob_start();
        require PROJECT_ROOT . $viewPath;
        $content = ob_get_clean();

        $this->layout->render([
            'pageTitle' => $pageTitle ?? '',
            'content' => $content,
            'layoutOptions' => $layoutOptions ?? [],
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
        ]);
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
