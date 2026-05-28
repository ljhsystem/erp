<?php

namespace App\Controllers\Funds;

use App\Controllers\System\LayoutController;
use App\Services\Funds\BankTransactionReportService;
use Core\DbPdo;
use Core\Helpers\ActorHelper;
use PDO;

class BankTransactionReportController
{
    private PDO $pdo;
    private BankTransactionReportService $service;
    private LayoutController $layout;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbPdo::conn();
        $this->service = new BankTransactionReportService($this->pdo);
        $this->layout = new LayoutController($this->pdo);
    }

    public function index(): void
    {
        $this->renderPage('/app/views/funds/bank-transactions/index.php', [
            'pageTitle' => '계좌별거래내역',
        ]);
    }

    public function apiList(): void
    {
        $filters = $this->requestFilters();
        $this->json([
            'success' => true,
            'data' => $this->service->rows($filters),
            'summary' => $this->service->summary($filters),
        ]);
    }

    public function apiSummary(): void
    {
        $this->json([
            'success' => true,
            'data' => $this->service->summary($this->requestFilters()),
        ]);
    }

    public function apiShow(): void
    {
        $id = trim((string) ($_GET['id'] ?? ''));
        if ($id === '') {
            $this->json(['success' => false, 'message' => '조회할 입출금 ID가 없습니다.'], 400);
            return;
        }

        $row = $this->service->find($id, true);
        if (!$row) {
            $this->json(['success' => false, 'message' => '입출금 원본을 찾을 수 없습니다.'], 404);
            return;
        }

        $this->json(['success' => true, 'data' => $row]);
    }

    public function apiTrashList(): void
    {
        $filters = $this->requestFilters();
        $filters[] = ['field' => 'deleted_scope', 'value' => 'DELETED'];
        $this->json([
            'success' => true,
            'data' => $this->service->rows($filters),
        ]);
    }

    public function apiDelete(): void
    {
        $id = $this->requestId();
        if ($id === '') {
            $this->json(['success' => false, 'message' => '삭제할 입출금 ID가 없습니다.'], 400);
            return;
        }
        if ($this->service->hasVoucherLink($id)) {
            $this->json([
                'success' => false,
                'code' => 'VOUCHER_LINKED',
                'message' => '전표가 연결된 입출금입니다. 연결 해제 또는 취소전표 처리가 필요합니다.',
            ], 409);
            return;
        }

        $deleted = $this->service->softDelete($id, ActorHelper::user());
        $this->json([
            'success' => $deleted,
            'message' => $deleted ? '입출금 원본을 휴지통으로 이동했습니다.' : '삭제할 입출금 원본을 찾을 수 없습니다.',
        ], $deleted ? 200 : 404);
    }

    public function apiRestore(): void
    {
        $id = $this->requestId();
        if ($id === '') {
            $this->json(['success' => false, 'message' => '복구할 입출금 ID가 없습니다.'], 400);
            return;
        }

        $restored = $this->service->restore($id, ActorHelper::user());
        $this->json([
            'success' => $restored,
            'message' => $restored ? '입출금 원본을 복구했습니다.' : '복구할 입출금 원본을 찾을 수 없습니다.',
        ], $restored ? 200 : 404);
    }

    public function apiRestoreBulk(): void
    {
        $payload = $this->requestPayload();
        $ids = is_array($payload['ids'] ?? null) ? array_values(array_filter(array_map('strval', $payload['ids']))) : [];
        $restored = 0;
        foreach ($ids as $id) {
            if ($this->service->restore($id, ActorHelper::user())) {
                $restored++;
            }
        }
        $this->json([
            'success' => true,
            'message' => "입출금 원본 {$restored}건을 복구했습니다.",
            'restored' => $restored,
        ]);
    }

    public function apiRestoreAll(): void
    {
        $filters = $this->requestFilters();
        $filters[] = ['field' => 'deleted_scope', 'value' => 'DELETED'];
        $restored = 0;
        foreach ($this->service->rows($filters) as $row) {
            if ($this->service->restore((string) ($row['id'] ?? ''), ActorHelper::user())) {
                $restored++;
            }
        }
        $this->json([
            'success' => true,
            'message' => "입출금 원본 {$restored}건을 복구했습니다.",
            'restored' => $restored,
        ]);
    }

    private function requestFilters(): array
    {
        $filters = [];
        $raw = (string) ($_GET['filters'] ?? '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $filters = array_values(array_filter($decoded, 'is_array'));
            }
        }

        foreach ([
            'bank_account_id',
            'account_name',
            'account_number',
            'bank_name',
            'transaction_datetime',
            'direction',
            'deposit_amount',
            'withdraw_amount',
            'client_name',
            'counterparty_name',
            'counterparty_account_number',
            'counterparty_bank_name',
            'description',
            'voucher_link_status',
            'evidence_status',
            'amount_min',
            'amount_max',
            'deleted_scope',
        ] as $field) {
            $value = $_GET[$field] ?? null;
            if ($value !== null && trim((string) $value) !== '') {
                $filters[] = ['field' => $field, 'value' => trim((string) $value)];
            }
        }

        return $filters;
    }

    private function requestId(): string
    {
        $payload = $this->requestPayload();
        return trim((string) ($payload['id'] ?? $_POST['id'] ?? $_GET['id'] ?? ''));
    }

    private function requestPayload(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $json = json_decode($raw, true);
        return is_array($json) ? $json : $_POST;
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
            'pageTitle' => $pageTitle ?? '계좌별거래내역',
            'content' => $content,
            'layoutOptions' => $layoutOptions ?? [],
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
        ]);
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
