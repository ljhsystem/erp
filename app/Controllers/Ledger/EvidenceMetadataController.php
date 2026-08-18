<?php

namespace App\Controllers\Ledger;

use App\Controllers\System\LayoutController;
use App\Services\Ledger\EvidenceMetadataService;
use Core\DbPdo;
use PDO;

class EvidenceMetadataController
{
    private EvidenceMetadataService $service;
    private LayoutController $layout;

    public function __construct(?PDO $pdo = null)
    {
        $pdo ??= DbPdo::conn();
        $this->service = new EvidenceMetadataService($pdo);
        $this->layout = new LayoutController($pdo);
    }

    public function index(): void
    {
        $pageTitle = '증빙정책';
        ob_start();
        require PROJECT_ROOT . '/app/views/ledger/evidence_metadata/index.php';
        $content = ob_get_clean();
        $this->layout->render(compact('pageTitle', 'content', 'layoutOptions', 'pageStyles', 'pageScripts'));
    }

    public function apiList(): void
    {
        $filters = json_decode((string) ($_GET['filters'] ?? '[]'), true);
        $this->json(['success' => true, 'data' => $this->service->getList(is_array($filters) ? $filters : [])]);
    }

    public function apiDetail(): void
    {
        $id = trim((string) ($_GET['id'] ?? ''));
        $row = $id !== '' ? $this->service->getById($id) : null;
        $this->json(
            $row ? ['success' => true, 'data' => $row] : ['success' => false, 'message' => '증빙정책을 찾을 수 없습니다.'],
            $row ? 200 : 404
        );
    }

    public function apiSave(): void
    {
        try {
            $this->json($this->service->save($_POST));
        } catch (\InvalidArgumentException $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable) {
            $this->json(['success' => false, 'message' => '저장 중 오류가 발생했습니다.'], 500);
        }
    }

    public function apiDelete(): void
    {
        $payload = $this->payload();
        $ids = $this->ids($payload);
        $id = trim((string) ($payload['id'] ?? $ids[0] ?? ''));
        if ($id === '') {
            $this->json(['success' => false, 'message' => '증빙정책 ID는 필수입니다.'], 400);
            return;
        }
        try {
            $this->json(count($ids) > 1 ? $this->service->deleteBulk($ids) : $this->service->delete($id));
        } catch (\InvalidArgumentException $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable) {
            $this->json(['success' => false, 'message' => '삭제 중 오류가 발생했습니다.'], 500);
        }
    }

    public function apiTrashList(): void
    {
        $this->json(['success' => true, 'data' => $this->service->getTrashList()]);
    }

    public function apiRestore(): void
    {
        $id = trim((string) ($this->payload()['id'] ?? ''));
        if ($id === '') {
            $this->json(['success' => false, 'message' => '증빙정책 ID는 필수입니다.'], 400);
        }
        $this->respond(fn(): array => $this->service->restore($id), '복구 중 오류가 발생했습니다.');
    }

    public function apiRestoreBulk(): void
    {
        $ids = $this->ids($this->payload());
        if ($ids === []) {
            $this->json(['success' => false, 'message' => '복원할 증빙정책을 선택해 주세요.'], 400);
        }
        $this->respond(fn(): array => $this->service->restoreBulk($ids), '복구 중 오류가 발생했습니다.');
    }

    public function apiRestoreAll(): void
    {
        $this->respond(fn(): array => $this->service->restoreAll(), '복구 중 오류가 발생했습니다.');
    }

    public function apiPurge(): void
    {
        $id = trim((string) ($this->payload()['id'] ?? ''));
        if ($id === '') {
            $this->json(['success' => false, 'message' => '증빙정책 ID는 필수입니다.'], 400);
        }
        $this->respond(fn(): array => $this->service->purge($id), '영구삭제 중 오류가 발생했습니다.');
    }

    public function apiPurgeBulk(): void
    {
        $ids = $this->ids($this->payload());
        if ($ids === []) {
            $this->json(['success' => false, 'message' => '영구삭제할 증빙정책을 선택해 주세요.'], 400);
        }
        $this->respond(fn(): array => $this->service->purgeBulk($ids), '영구삭제 중 오류가 발생했습니다.');
    }

    public function apiReorder(): void
    {
        $payload = $this->payload();
        $changes = is_array($payload['changes'] ?? null) ? $payload['changes'] : [];
        $this->json($changes !== [] ? $this->service->reorder($changes) : ['success' => false, 'message' => '정렬 데이터가 없습니다.'], $changes !== [] ? 200 : 400);
    }

    public function apiSourceColumns(): void
    {
        try {
            $this->json(['success' => true, 'data' => $this->service->sourceColumns((string) ($_GET['table'] ?? ''))]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function apiRecommend(): void
    {
        try {
            $this->json(['success' => true, 'data' => $this->service->recommend((string) ($_GET['import_type'] ?? ''))]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function apiOptions(): void
    {
        $this->json(['success' => true, 'data' => $this->service->policyOptions()]);
    }

    private function payload(): array
    {
        $json = json_decode(file_get_contents('php://input') ?: '{}', true);
        return array_merge(is_array($json) ? $json : [], $_POST);
    }

    private function ids(array $payload): array
    {
        return array_values(array_filter(array_map(
            static fn(mixed $id): string => trim((string) $id),
            is_array($payload['ids'] ?? null) ? $payload['ids'] : []
        )));
    }

    private function respond(callable $callback, string $fallback): void
    {
        try {
            $this->json($callback());
        } catch (\InvalidArgumentException $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable) {
            $this->json(['success' => false, 'message' => $fallback], 500);
        }
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
