<?php

namespace App\Controllers\Ledger;

use App\Controllers\System\LayoutController;
use App\Services\Ledger\TransactionCrudService;
use App\Services\Ledger\TransactionVoucherService;
use Core\DbPdo;
use PDO;

class TransactionController
{
    private PDO $pdo;
    private TransactionCrudService $service;
    private TransactionVoucherService $transactionVoucherService;
    private LayoutController $layout;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbPdo::conn();
        $this->service = new TransactionCrudService($this->pdo);
        $this->transactionVoucherService = new TransactionVoucherService($this->pdo);
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

        $download = $this->service->getFileDownloadPayload($id);
        if (!$download) {
            http_response_code(404);
            exit('File not found');
        }

        header('Content-Type: ' . $download['mime']);
        header('Content-Length: ' . $download['size']);
        header(
            'Content-Disposition: ' . $download['disposition']
            . '; filename="' . addcslashes((string) $download['file_name'], "\\\"") . '"'
            . "; filename*=UTF-8''" . rawurlencode((string) $download['file_name'])
        );
        readfile((string) $download['absolute_path']);
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
            return [
                'success' => true,
                'data' => $this->service->getTrashList(),
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

            $this->service->restoreTransactions([$id]);

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

            $this->service->restoreTransactions($ids);

            return [
                'success' => true,
                'message' => '선택한 거래가 복원되었습니다.',
            ];
        });
    }

    public function apiRestoreAll(): void
    {
        $this->json(function (): array {
            $this->service->restoreAllTransactions();

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

            $this->service->purgeTransactions([$id]);

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

            $this->service->purgeTransactions($ids);

            return [
                'success' => true,
                'message' => '선택한 거래가 영구 삭제되었습니다.',
            ];
        });
    }

    public function apiPurgeAll(): void
    {
        $this->json(function (): array {
            $this->service->purgeAllTransactions();

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

    private function withLinkedVouchers(array $transaction): array
    {
        return $this->transactionVoucherService->appendLinkedVouchers($transaction);
    }
}
