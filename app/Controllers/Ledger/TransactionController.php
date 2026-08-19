<?php

namespace App\Controllers\Ledger;

use App\Controllers\System\LayoutController;
use App\Services\Ledger\TransactionCrudService;
use App\Services\Ledger\TransactionEvidenceReferenceService;
use Core\DbPdo;
use Core\Session;
use PDO;

class TransactionController
{
    private PDO $pdo;
    private TransactionCrudService $service;
    private LayoutController $layout;
    private TransactionEvidenceReferenceService $evidenceReferenceService;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbPdo::conn();
        $this->service = new TransactionCrudService($this->pdo);
        $this->layout = new LayoutController($this->pdo);
        $this->evidenceReferenceService = new TransactionEvidenceReferenceService($this->pdo);
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
            'pageAssetProfile' => $pageAssetProfile ?? 'default',
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
            'pageSubtitle' => '거래 입력, 목록, 자료증빙 연결을 한 화면에서 관리합니다.',
            'evidenceTypePolicies' => [],
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
        $_GET = \Core\Helpers\DataTableRequestHelper::input();
        Session::write();
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

            $filters['_start'] = max(0, (int) ($_GET['start'] ?? 0));
            $filters['_length'] = max(10, min(100, (int) ($_GET['length'] ?? 100)));
            $sortField = trim((string) ($_GET['sort_field'] ?? ''));
            $sortDirection = trim((string) ($_GET['sort_direction'] ?? ''));
            if ($sortField === '') {
                $order = is_array($_GET['order'] ?? null) ? ($_GET['order'][0] ?? []) : [];
                $columns = is_array($_GET['columns'] ?? null) ? $_GET['columns'] : [];
                $orderColumnIndex = filter_var($order['column'] ?? null, FILTER_VALIDATE_INT);
                if ($orderColumnIndex !== false && isset($columns[$orderColumnIndex])) {
                    $orderColumn = is_array($columns[$orderColumnIndex]) ? $columns[$orderColumnIndex] : [];
                    $sortField = trim((string) ($orderColumn['data'] ?? ''));
                    $sortDirection = trim((string) ($order['dir'] ?? ''));
                }
            }
            if ($sortField !== '') {
                $filters['_order_field'] = $sortField;
                $filters['_order_direction'] = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';
            }
            $page = $this->service->getPage($filters);

            return [
                'success' => true,
                'draw' => (int) ($_GET['draw'] ?? 0),
                'recordsTotal' => $page['records_total'],
                'recordsFiltered' => $page['records_filtered'],
                'data' => $page['rows'],
            ];
        });
    }

    public function apiReorder(): void
    {
        Session::write();
        $this->json(function (): array {
            $input = json_decode((string) file_get_contents('php://input'), true);
            $changes = is_array($input) && is_array($input['changes'] ?? null)
                ? $input['changes']
                : [];
            $this->service->reorder($changes);

            return [
                'success' => true,
                'message' => '정렬이 저장되었습니다.',
            ];
        });
    }

    public function apiDetail(): void
    {
        Session::write();
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
                'data' => $row,
            ];
        });
    }

    public function apiEvidenceSearch(): void
    {
        $_GET = \Core\Helpers\DataTableRequestHelper::input();
        Session::write();
        $this->json(function (): array {
            $query = trim((string) ($_GET['q'] ?? ''));
            $excludeEvidences = json_decode((string) ($_GET['exclude_evidences'] ?? '[]'), true);
            if (!is_array($excludeEvidences)) {
                $excludeEvidences = [];
            }
            $sortField = trim((string) ($_GET['sort_field'] ?? 'evidence_date'));
            $orderField = match ($sortField) {
                'display_amount' => 'display_amount',
                'import_type', 'display_type' => 'import_type',
                'client_name' => 'client_search_name',
                'display_summary' => 'description',
                default => 'standard_date',
            };
            $result = $this->evidenceReferenceService->searchPage([
                'keyword' => $query,
                'start' => max(0, (int) ($_GET['start'] ?? 0)),
                'length' => max(10, min(100, (int) ($_GET['length'] ?? 20))),
                'order_field' => $orderField,
                'order_direction' => strtolower(trim((string) ($_GET['sort_direction'] ?? 'desc'))) === 'asc' ? 'asc' : 'desc',
                'exclude_evidences' => $excludeEvidences,
            ]);

            return [
                'success' => true,
                'data' => [
                    'items' => $result['items'],
                    'pagination' => [
                        'total' => $result['records_filtered'],
                        'records_total' => $result['records_total'],
                    ],
                ],
            ];
        });
    }

    public function apiFile(): void
    {
        $id = trim((string) ($_GET['id'] ?? ''));
        if ($id === '') {
            http_response_code(400);
            exit('파일을 처리할 수 없습니다.');
        }

        $download = $this->service->getFileDownloadPayload($id);
        if (!$download) {
            http_response_code(404);
            exit('파일을 처리할 수 없습니다.');
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

            // 사용자 CRUD에서는 상태를 임의 전환하지 않는다. 승인 원천 업무는 Service를 직접 호출한다.
            $payload['status'] = 'draft';

            return $this->service->save($payload, $_FILES);
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
                'message' => $e instanceof \InvalidArgumentException || $e instanceof \RuntimeException
                    ? $e->getMessage()
                    : '요청 처리 중 오류가 발생했습니다.',
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

}
