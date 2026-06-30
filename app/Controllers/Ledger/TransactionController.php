<?php

namespace App\Controllers\Ledger;

use App\Controllers\System\LayoutController;
use App\Models\Ledger\TransactionFileModel;
use App\Models\Ledger\TransactionModel;
use App\Services\File\FileService;
use App\Services\Ledger\TransactionCrudService;
use App\Services\Ledger\TransactionVoucherService;
use Core\DbPdo;
use PDO;

class TransactionController
{
    private PDO $pdo;
    private TransactionCrudService $service;
    private TransactionVoucherService $transactionVoucherService;
    private TransactionModel $transactionModel;
    private TransactionFileModel $transactionFileModel;
    private FileService $fileService;
    private LayoutController $layout;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbPdo::conn();
        $this->service = new TransactionCrudService($this->pdo);
        $this->transactionVoucherService = new TransactionVoucherService($this->pdo);
        $this->transactionModel = new TransactionModel($this->pdo);
        $this->transactionFileModel = new TransactionFileModel($this->pdo);
        $this->fileService = new FileService($this->pdo);
        $this->layout = new LayoutController($this->pdo);
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
            'pageTitle' => $pageTitle ?? '거래관리',
            'content' => $content,
            'layoutOptions' => $layoutOptions ?? [],
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
        ]);
    }

    public function webTransaction(): void
    {
        $this->redirectToLedgerTransaction();
    }

    public function webCreate(): void
    {
        $this->redirectToLedgerTransaction();
    }

    public function webLedgerTransaction(): void
    {
        $this->renderTransactionCreatePage([
            'pageTitle' => '거래입력',
            'pageSubtitle' => '거래 입력, 목록, 전표 연결을 한 화면에서 관리합니다.',
        ]);
    }

    public function webLedgerCreate(): void
    {
        $this->redirectToLedgerTransaction();
    }

    private function renderTransactionCreatePage(array $params): void
    {
        $this->renderPage('/app/views/ledger/transaction/index.php', $params);
    }

    private function redirectToLedgerTransaction(): void
    {
        header('Location: /ledger/transactions/input', true, 302);
        exit;
    }

    public function apiList(): void
    {
        $this->json(function (): array {
            $filters = [];

            if (!empty($_GET['filters'])) {
                $decoded = json_decode((string) $_GET['filters'], true);
                if (is_array($decoded)) {
                    $filters = $decoded;
                }
            } else {
                $filters = $_GET;
            }

            if (!empty($filters['status'])) {
                $filters['status'] = strtolower(trim((string) $filters['status']));
            }

            return [
                'success' => true,
                'data' => $this->service->getList($filters),
            ];
        });
    }

    public function apiReorder(): void
    {
        $this->json(function (): array {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $changes = $input['changes'] ?? [];

            if ($changes === []) {
                return [
                    'success' => false,
                    'message' => '정렬 데이터가 없습니다.',
                ];
            }

            $this->service->reorder($changes);

            return [
                'success' => true,
                'message' => '정렬 저장 완료',
            ];
        });
    }

    public function apiDetail(): void
    {
        $this->json(function (): array {
            $id = trim((string) ($_GET['id'] ?? ''));
            if ($id === '') {
                throw new \InvalidArgumentException('거래 ID가 필요합니다.');
            }

            $row = $this->service->getById($id);
            if (!$row) {
                http_response_code(404);
                return [
                    'success' => false,
                    'message' => '거래 정보를 찾을 수 없습니다.',
                ];
            }

            return [
                'success' => true,
                'data' => $this->withLinkedVouchers($row),
            ];
        });
    }

    public function apiFile(): void
    {
        $id = trim((string) ($_GET['id'] ?? ''));
        if ($id === '') {
            http_response_code(400);
            exit('Missing file id');
        }

        $file = $this->transactionFileModel->getById($id);
        if (!$file || empty($file['file_path'])) {
            http_response_code(404);
            exit('File not found');
        }

        $abs = \Core\storage_resolve_abs((string) $file['file_path']);
        if (!$abs || !is_file($abs)) {
            http_response_code(404);
            exit('File not found');
        }

        $fileName = (string) ($file['file_name'] ?: basename($abs));
        $mime = mime_content_type($abs) ?: 'application/octet-stream';
        $disposition = in_array($mime, [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'text/plain',
        ], true) ? 'inline' : 'attachment';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($abs));
        header(
            'Content-Disposition: ' . $disposition
            . '; filename="' . addcslashes($fileName, "\\\"") . '"'
            . "; filename*=UTF-8''" . rawurlencode($fileName)
        );
        readfile($abs);
        exit;
    }

    public function apiSave(): void
    {
        $this->json(function (): array {
            $payload = $_POST;
            $rawBody = file_get_contents('php://input');

            if ($rawBody !== false && trim($rawBody) !== '') {
                $decoded = json_decode($rawBody, true);
                if (is_array($decoded)) {
                    $payload = array_replace_recursive($payload, $decoded);
                }
            }

            return $this->service->save($payload, $_FILES);
        });
    }

    public function apiCreateVoucher(): void
    {
        $this->json(function (): array {
            $payload = $this->requestPayload();
            $transactionId = trim((string) ($payload['transaction_id'] ?? ''));
            if ($transactionId === '') {
                throw new \InvalidArgumentException('거래 ID가 필요합니다.');
            }

            return $this->transactionVoucherService->createDraftVoucher($transactionId, $payload);
        });
    }

    public function apiRecommendVoucher(): void
    {
        $this->json(function (): array {
            $transactionId = trim((string) ($_GET['transaction_id'] ?? $_POST['transaction_id'] ?? ''));
            if ($transactionId === '') {
                throw new \InvalidArgumentException('거래 ID가 필요합니다.');
            }

            return $this->transactionVoucherService->recommendVoucherDraft($transactionId);
        });
    }
    public function apiLinkVoucher(): void
    {
        $this->json(function (): array {
            return $this->transactionVoucherService->linkVoucher(
                trim((string) ($_POST['transaction_id'] ?? '')),
                trim((string) ($_POST['voucher_id'] ?? ''))
            );
        });
    }
    public function apiUnlinkVoucher(): void
    {
        $this->json(function (): array {
            return $this->transactionVoucherService->unlinkVoucher(
                trim((string) ($_POST['transaction_id'] ?? '')),
                trim((string) ($_POST['voucher_id'] ?? ''))
            );
        });
    }
    public function apiDelete(): void
    {
        $this->json(function (): array {
            $transactionId = trim((string) ($_POST['transaction_id'] ?? $_POST['id'] ?? ''));
            if ($transactionId === '') {
                throw new \InvalidArgumentException('거래를 선택해 주세요.');
            }

            return $this->service->softDelete($transactionId);
        });
    }

    public function apiTrashList(): void
    {
        $this->json(function (): array {
            $stmt = $this->pdo->query("
                SELECT
                    t.*,
                    COALESCE(sc.client_name, '') AS client_name,
                    COALESCE(sp.project_name, '') AS project_name
                FROM ledger_transactions t
                LEFT JOIN system_clients sc
                    ON t.client_id = sc.id
                LEFT JOIN system_projects sp
                    ON t.project_id = sp.id
                WHERE t.deleted_at IS NOT NULL
                ORDER BY t.deleted_at DESC, t.transaction_date DESC
            ");

            return [
                'success' => true,
                'data' => array_map(static function (array $row): array {
                    unset($row['tax_type']);
                    return $row;
                }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []),
            ];
        });
    }

    public function apiRestore(): void
    {
        $this->json(function (): array {
            $id = trim((string) ($_POST['id'] ?? ''));
            if ($id === '') {
                throw new \InvalidArgumentException('복원할 거래를 선택해 주세요.');
            }

            $this->restoreTransactions([$id]);

            return [
                'success' => true,
                'message' => '거래가 복원되었습니다.',
            ];
        });
    }

    public function apiRestoreBulk(): void
    {
        $this->json(function (): array {
            $ids = $this->idsFromJsonBody();
            if ($ids === []) {
                throw new \InvalidArgumentException('복원할 거래를 선택해 주세요.');
            }

            $this->restoreTransactions($ids);

            return [
                'success' => true,
                'message' => '선택한 거래가 복원되었습니다.',
            ];
        });
    }

    public function apiRestoreAll(): void
    {
        $this->json(function (): array {
            $stmt = $this->pdo->query("SELECT id FROM ledger_transactions WHERE deleted_at IS NOT NULL");
            $ids = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            $this->restoreTransactions($ids);

            return [
                'success' => true,
                'message' => '전체 거래가 복원되었습니다.',
            ];
        });
    }

    public function apiPurge(): void
    {
        $this->json(function (): array {
            $id = trim((string) ($_POST['id'] ?? ''));
            if ($id === '') {
                throw new \InvalidArgumentException('삭제할 거래를 선택해 주세요.');
            }

            $this->purgeTransactions([$id]);

            return [
                'success' => true,
                'message' => '거래가 영구 삭제되었습니다.',
            ];
        });
    }

    public function apiPurgeBulk(): void
    {
        $this->json(function (): array {
            $ids = $this->idsFromJsonBody();
            if ($ids === []) {
                throw new \InvalidArgumentException('삭제할 거래를 선택해 주세요.');
            }

            $this->purgeTransactions($ids);

            return [
                'success' => true,
                'message' => '선택한 거래가 영구 삭제되었습니다.',
            ];
        });
    }

    public function apiPurgeAll(): void
    {
        $this->json(function (): array {
            $stmt = $this->pdo->query("SELECT id FROM ledger_transactions WHERE deleted_at IS NOT NULL");
            $ids = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            $this->purgeTransactions($ids);

            return [
                'success' => true,
                'message' => '전체 거래가 영구 삭제되었습니다.',
            ];
        });
    }

    private function json(callable $callback): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $result = $callback();
            http_response_code(!empty($result['success']) ? 200 : 400);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function idsFromJsonBody(): array
    {
        $payload = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($payload) || !isset($payload['ids']) || !is_array($payload['ids'])) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($id): string {
            return trim((string) $id);
        }, $payload['ids'])));
    }

    private function requestPayload(): array
    {
        $payload = $_POST;
        $raw = file_get_contents('php://input');
        if ($raw !== false && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $payload = array_replace_recursive($payload, $decoded);
            }
        }

        return is_array($payload) ? $payload : [];
    }

    private function restoreTransactions(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $actor = ActorHelper::user();
        $actorId = is_array($actor) ? ($actor['id'] ?? null) : $actor;

        try {
            $this->pdo->beginTransaction();

            foreach ($ids as $id) {
                $this->transactionModel->update($id, [
                    'deleted_at' => null,
                    'deleted_by' => null,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'updated_by' => $actorId,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    private function purgeTransactions(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $filePaths = [];

        try {
            $this->pdo->beginTransaction();

            $deleteItems = $this->pdo->prepare("DELETE FROM ledger_transaction_items WHERE transaction_id = :id");
            $softDeleteLinks = $this->pdo->prepare("
                UPDATE ledger_transaction_links
                SET is_active = 0,
                    deleted_at = NOW(),
                    deleted_by = :deleted_by,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE transaction_id = :id
                  AND is_active = 1
                  AND deleted_at IS NULL
            ");
            $actor = ActorHelper::user();
            $actorId = is_array($actor) ? (string) ($actor['id'] ?? 'SYSTEM') : (string) $actor;

            foreach ($ids as $id) {
                $transaction = $this->transactionModel->getById($id) ?: [];
                foreach ($this->transactionFileModel->getByTransactionId($id) as $file) {
                    if (!empty($file['file_path'])) {
                        $filePaths[] = (string) $file['file_path'];
                    }
                }

                $this->service->resetGeneratedTransactionState($id, $actorId, $transaction);
                $deleteItems->execute([':id' => $id]);
                $softDeleteLinks->execute([
                    ':id' => $id,
                    ':deleted_by' => $actorId,
                    ':updated_by' => $actorId,
                ]);
                $this->transactionModel->hardDelete($id);
            }

            $this->pdo->commit();

            foreach (array_unique($filePaths) as $filePath) {
                $this->fileService->delete($filePath);
            }
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    private function withLinkedVouchers(array $transaction): array
    {
        return $this->transactionVoucherService->appendLinkedVouchers($transaction);
    }
}
