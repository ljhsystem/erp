<?php

namespace App\Controllers\Site;

use App\Controllers\System\LayoutController;
use App\Services\Ledger\TransactionService as LedgerTransactionService;
use App\Services\Site\TransactionService;
use Core\DbPdo;
use PDO;

class TransactionController
{
    private TransactionService $service;
    private LedgerTransactionService $ledgerTransactionService;
    private LayoutController $layout;

    public function __construct(?PDO $pdo = null)
    {
        $connection = $pdo ?? DbPdo::conn();
        $this->service = new TransactionService($connection);
        $this->ledgerTransactionService = new LedgerTransactionService($connection);
        $this->layout = new LayoutController($connection);
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
        $this->renderTransactionListPage([
            'pageTitle' => '현장 거래내역',
            'pageSubtitle' => '현장관리 기준으로 입력된 거래 내역을 조회합니다.',
            'workUnit' => 'SITE',
            'createUrl' => '/site/transaction/create',
        ]);
    }

    public function webCreate(): void
    {
        $this->renderTransactionCreatePage([
            'pageTitle' => '현장 거래입력',
            'pageSubtitle' => '현장관리 기준 거래를 입력합니다.',
            'workUnit' => 'SITE',
            'listUrl' => '/site/transaction',
            'saveUrl' => '/api/transaction/save',
        ]);
    }

    public function webLedgerTransaction(): void
    {
        $this->renderTransactionListPage([
            'pageTitle' => '회계 거래내역',
            'pageSubtitle' => '회계관리 기준 거래 내역을 조회합니다.',
            'workUnit' => 'OFFICE',
            'createUrl' => '/ledger/transaction/create',
        ]);
    }

    public function webLedgerCreate(): void
    {
        $this->renderTransactionCreatePage([
            'pageTitle' => '회계 거래입력',
            'pageSubtitle' => '회계관리 기준 거래를 입력합니다.',
            'workUnit' => 'OFFICE',
            'listUrl' => '/ledger/transaction',
            'saveUrl' => '/api/transaction/save',
        ]);
    }

    private function renderTransactionListPage(array $params): void
    {
        $this->renderPage('/app/views/site/transaction/index.php', $params);
    }

    private function renderTransactionCreatePage(array $params): void
    {
        $this->renderPage('/app/views/site/transaction/create.php', $params);
    }

    public function apiList(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $filters = [];

            if (!empty($_GET['filters'])) {
                $decoded = json_decode((string) $_GET['filters'], true);
                if (is_array($decoded)) {
                    $filters = $decoded;
                }
            } else {
                $filters = $_GET;
            }

            echo json_encode([
                'success' => true,
                'data' => $this->service->getList($filters),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function apiDetail(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $id = trim((string) ($_GET['id'] ?? ''));
            if ($id === '') {
                throw new \InvalidArgumentException('거래 ID가 필요합니다.');
            }

            $row = $this->service->getById($id);
            if (!$row) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => '거래 정보를 찾을 수 없습니다.',
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            echo json_encode([
                'success' => true,
                'data' => $row,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function apiSave(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $payload = $_POST;
            $rawBody = file_get_contents('php://input');

            if ($rawBody !== false && trim($rawBody) !== '') {
                $decoded = json_decode($rawBody, true);
                if (is_array($decoded)) {
                    $payload = array_replace_recursive($payload, $decoded);
                }
            }

            $result = $this->service->save($payload);
            http_response_code(!empty($result['success']) ? 200 : 400);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function apiCreateVoucher(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $transactionId = trim((string) ($_POST['transaction_id'] ?? ''));

            if ($transactionId === '') {
                throw new \InvalidArgumentException('거래 ID가 필요합니다.');
            }

            $result = $this->ledgerTransactionService->createVoucherFromTransaction($transactionId);
            http_response_code(!empty($result['success']) ? 200 : 400);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
