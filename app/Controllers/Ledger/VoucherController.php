<?php

namespace App\Controllers\Ledger;

use App\Models\Ledger\TransactionLinkModel;
use App\Models\Ledger\TransactionModel;
use App\Models\Ledger\VoucherLineRefModel;
use App\Models\Ledger\VoucherLineModel;
use App\Models\Ledger\VoucherModel;
use App\Models\Ledger\VoucherPaymentModel;
use App\Services\Ledger\TransactionCrudService;
use App\Services\Ledger\VoucherService;
use App\Services\Ledger\VoucherValidationException;
use Core\DbPdo;
use Core\Helpers\ActorHelper;
use PDO;

class VoucherController
{
    private PDO $pdo;
    private VoucherService $service;
    private VoucherModel $voucherModel;
    private VoucherLineModel $voucherLineModel;
    private VoucherLineRefModel $voucherLineRefModel;
    private VoucherPaymentModel $voucherPaymentModel;
    private TransactionLinkModel $transactionLinkModel;
    private TransactionModel $transactionModel;
    private TransactionCrudService $transactionCrudService;

    public function __construct()
    {
        $this->pdo = DbPdo::conn();
        $this->service = new VoucherService($this->pdo);
        $this->voucherModel = new VoucherModel($this->pdo);
        $this->voucherLineModel = new VoucherLineModel($this->pdo);
        $this->voucherLineRefModel = new VoucherLineRefModel($this->pdo);
        $this->voucherPaymentModel = new VoucherPaymentModel($this->pdo);
        $this->transactionLinkModel = new TransactionLinkModel($this->pdo);
        $this->transactionModel = new TransactionModel($this->pdo);
        $this->transactionCrudService = new TransactionCrudService($this->pdo);
    }

    public function apiList(): void
    {
        $this->jsonResponse(function (): array {
            $filters = [];
            if (!empty($_GET['filters'])) {
                $filters = json_decode((string) $_GET['filters'], true) ?? [];
            }
            foreach (['status', 'date_from', 'date_to', 'keyword'] as $key) {
                $value = trim((string) ($_GET[$key] ?? ''));
                if ($value !== '') {
                    $filters[$key] = $value;
                }
            }

            return [
                'success' => true,
                'message' => '조회 완료',
                'data' => $this->voucherModel->getList($filters),
            ];
        });
    }

    public function apiReorder(): void
    {
        $this->jsonResponse(function (): array {
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
        $this->jsonResponse(function (): array {
            $id = trim((string) ($_GET['id'] ?? ''));
            if ($id === '') {
                return [
                    'success' => false,
                    'message' => '전표 ID가 없습니다.',
                ];
            }

            $voucher = $this->voucherModel->getById($id);
            if (!$voucher) {
                return [
                    'success' => false,
                    'message' => '전표를 찾을 수 없습니다.',
                ];
            }

            $voucher['lines'] = $this->voucherLineModel->getByVoucherId($id);
            $lineRefs = $this->voucherLineRefModel->getGroupedByVoucherLineIds(array_column($voucher['lines'], 'id'));
            foreach ($voucher['lines'] as &$line) {
                $line['refs'] = array_map(static fn(array $ref): array => [
                    'ref_type' => $ref['ref_type'] ?? '',
                    'ref_id' => $ref['ref_id'] ?? '',
                    'is_primary' => (int) ($ref['is_primary'] ?? 0),
                ], $lineRefs[$line['id']] ?? []);
            }
            unset($line);
            $voucher['payments'] = $this->voucherPaymentModel->getByVoucherId($id);
            $voucher['reversal_voucher'] = $this->voucherModel->findActiveReversalOf($id);
            $voucher['original_voucher'] = !empty($voucher['reversal_of'])
                ? $this->voucherModel->getById((string) $voucher['reversal_of'])
                : null;
            $voucher['source_transaction'] = null;
            if (strtoupper((string) ($voucher['source_type'] ?? '')) === 'TRANSACTION') {
                $sourceTransactionId = trim((string) ($voucher['source_id'] ?? ''));
                if ($sourceTransactionId !== '') {
                    $voucher['source_transaction'] = $this->transactionModel->getById($sourceTransactionId);
                }
            }
            $voucher['linked_transaction'] = null;

            foreach ($this->transactionLinkModel->getByVoucherId($id) as $link) {
                if (($link['link_type'] ?? '') !== 'MANUAL') {
                    continue;
                }

                $transactionId = trim((string) ($link['transaction_id'] ?? ''));
                if ($transactionId !== '') {
                    $voucher['linked_transaction'] = $this->transactionModel->getById($transactionId);
                }
                break;
            }

            return [
                'success' => true,
                'message' => '조회 완료',
                'data' => $voucher,
            ];
        });
    }

    public function apiSearch(): void
    {
        $this->jsonResponse(function (): array {
            $keyword = trim((string) ($_GET['keyword'] ?? $_GET['q'] ?? ''));
            $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
            $dateTo = trim((string) ($_GET['date_to'] ?? ''));
            $clientId = trim((string) ($_GET['client_id'] ?? ''));
            $minAmount = trim((string) ($_GET['min_amount'] ?? ''));
            $maxAmount = trim((string) ($_GET['max_amount'] ?? ''));
            $allowedStatuses = ['draft', 'confirmed', 'reviewed'];
            $requestedStatuses = $_GET['status'] ?? $allowedStatuses;
            if (!is_array($requestedStatuses)) {
                $requestedStatuses = [$requestedStatuses];
            }
            $statuses = array_values(array_intersect(
                $allowedStatuses,
                array_map(static fn($status): string => strtolower(trim((string) $status)), $requestedStatuses)
            ));
            if ($statuses === []) {
                $statuses = $allowedStatuses;
            }
            $params = [];
            $statusPlaceholders = [];
            foreach ($statuses as $index => $status) {
                $key = ":status{$index}";
                $statusPlaceholders[] = $key;
                $params[$key] = $status;
            }

            $sql = "
                SELECT
                    v.id,
                    v.voucher_no,
                    v.voucher_date,
                    COALESCE(linked_clients.client_name, source_clients.client_name, '') AS client_name,
                    COALESCE(v.summary_text, '') AS summary_text,
                    COALESCE(line_totals.amount, 0) AS amount,
                    v.status
                FROM ledger_vouchers v
                LEFT JOIN (
                    SELECT
                        l.voucher_id,
                        MAX(t.client_id) AS client_id,
                        MAX(sc.client_name) AS client_name
                    FROM ledger_transaction_links l
                    INNER JOIN ledger_transactions t
                        ON t.id = l.transaction_id
                       AND t.deleted_at IS NULL
                    LEFT JOIN system_clients sc
                        ON sc.id = t.client_id
                    WHERE l.deleted_at IS NULL
                      AND l.is_active = 1
                    GROUP BY l.voucher_id
                ) linked_clients
                    ON linked_clients.voucher_id = v.id
                LEFT JOIN system_clients source_clients
                    ON source_clients.id = v.source_id
                   AND v.source_type = 'CLIENT'
                LEFT JOIN (
                    SELECT
                        voucher_id,
                        SUM(COALESCE(debit, 0)) AS amount
                    FROM ledger_voucher_lines
                    WHERE deleted_at IS NULL
                    GROUP BY voucher_id
                ) line_totals
                    ON line_totals.voucher_id = v.id
                WHERE v.deleted_at IS NULL
                  AND v.status IN (" . implode(', ', $statusPlaceholders) . ")
            ";

            if ($keyword !== '') {
                $sql .= "
                  AND (
                      v.voucher_no LIKE :keyword
                      OR COALESCE(linked_clients.client_name, source_clients.client_name, '') LIKE :keyword
                      OR v.summary_text LIKE :keyword
                  )
                ";
                $params[':keyword'] = "%{$keyword}%";
            }

            if ($dateFrom !== '') {
                $sql .= " AND v.voucher_date >= :date_from";
                $params[':date_from'] = $dateFrom;
            }

            if ($dateTo !== '') {
                $sql .= " AND v.voucher_date <= :date_to";
                $params[':date_to'] = $dateTo;
            }

            if ($clientId !== '') {
                $sql .= " AND COALESCE(linked_clients.client_id, source_clients.id, '') = :client_id";
                $params[':client_id'] = $clientId;
            }

            if ($minAmount !== '') {
                $sql .= " AND COALESCE(line_totals.amount, 0) >= :min_amount";
                $params[':min_amount'] = (float) $minAmount;
            }

            if ($maxAmount !== '') {
                $sql .= " AND COALESCE(line_totals.amount, 0) <= :max_amount";
                $params[':max_amount'] = (float) $maxAmount;
            }

            $sql .= "
                ORDER BY v.voucher_date DESC, v.voucher_no DESC
                LIMIT 100
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return array_map(static function (array $row): array {
                $row['amount'] = (float) ($row['amount'] ?? 0);
                return $row;
            }, $rows);
        });
    }

    public function apiTransactionSearch(): void
    {
        $this->jsonResponse(function (): array {
            $query = trim((string) ($_GET['q'] ?? ''));
            $rows = $this->transactionModel->getList([]);

            if ($query !== '') {
                $rows = array_values(array_filter($rows, static function (array $row) use ($query): bool {
                    $haystack = implode(' ', [
                        $row['sort_no'] ?? '',
                        $row['transaction_date'] ?? '',
                        $row['client_name'] ?? '',
                        $row['project_name'] ?? '',
                        $row['item_summary'] ?? '',
                        $row['description'] ?? '',
                        $row['total_amount'] ?? '',
                    ]);

                    return stripos($haystack, $query) !== false;
                }));
            }

            return [
                'success' => true,
                'message' => '조회 완료',
                'data' => array_slice($rows, 0, 50),
            ];
        });
    }

    public function apiCreateTransaction(): void
    {
        $this->jsonResponse(function (): array {
            http_response_code(400);

            return [
                'success' => false,
                'message' => '전표에서 거래를 생성할 수 없습니다. 거래입력 화면에서 전표를 생성하거나 연결해 주세요.',
            ];
        });
    }

    public function apiSummarySearch(): void
    {
        $this->jsonResponse(function (): array {
            $query = trim((string) ($_GET['q'] ?? ''));

            return [
                'success' => true,
                'items' => $this->service->searchSummaryTexts($query, 10),
            ];
        });
    }

    public function apiSave(): void
    {
        $this->jsonResponse(function (): array {
            $payload = $_POST;
            $payload['lines'] = json_decode((string) ($_POST['lines'] ?? '[]'), true) ?? [];
            $payload['payments'] = json_decode((string) ($_POST['payments'] ?? '[]'), true) ?? [];

            $result = $this->service->save($payload);

            return [
                'success' => (bool) ($result['success'] ?? false),
                'message' => ($result['success'] ?? false) ? '저장 완료' : ($result['message'] ?? '저장 실패'),
                'data' => $result,
            ];
        });
    }

    public function apiUpdateStatus(): void
    {
        $this->jsonResponse(function (): array {
            throw new \RuntimeException('전표 상태 변경은 전표검토/승인 화면에서만 처리할 수 있습니다.');
        });
    }

    public function apiConfirm(): void
    {
        $this->jsonResponse(function (): array {
            $id = $this->requestVoucherId();
            $result = $this->service->confirm($id);
            $voucher = $this->voucherModel->getById($id) ?: [];

            return [
                'success' => true,
                'message' => '검토요청 처리되었습니다.',
                'data' => array_merge($voucher, $result),
            ];
        });
    }

    public function apiCancelReview(): void
    {
        $this->jsonResponse(function (): array {
            $id = $this->requestVoucherId();
            $result = $this->service->cancelReview($id);
            $voucher = $this->voucherModel->getById($id) ?: [];

            return [
                'success' => true,
                'message' => '검토요청이 취소되었습니다.',
                'data' => array_merge($voucher, $result),
            ];
        });
    }

    public function apiCompleteReview(): void
    {
        $this->jsonResponse(function (): array {
            $id = $this->requestVoucherId();
            $result = $this->service->completeReview($id);
            $voucher = $this->voucherModel->getById($id) ?: [];

            return [
                'success' => true,
                'message' => '검토완료 처리되었습니다.',
                'data' => array_merge($voucher, $result),
            ];
        });
    }

    public function apiCancelCompleteReview(): void
    {
        $this->jsonResponse(function (): array {
            $id = $this->requestVoucherId();
            $result = $this->service->cancelCompleteReview($id);
            $voucher = $this->voucherModel->getById($id) ?: [];

            return [
                'success' => true,
                'message' => '검토완료가 취소되었습니다.',
                'data' => array_merge($voucher, $result),
            ];
        });
    }

    public function apiPost(): void
    {
        $this->jsonResponse(function (): array {
            $id = $this->requestVoucherId();
            $result = $this->service->post($id);
            $voucher = $this->voucherModel->getById($id) ?: [];

            return [
                'success' => true,
                'message' => '승인 처리되었습니다.',
                'data' => array_merge($voucher, $result),
            ];
        });
    }

    public function apiReverse(): void
    {
        $this->jsonResponse(function (): array {
            $id = $this->requestVoucherId();
            $result = $this->service->createReversalVoucher($id, ActorHelper::user());
            $voucher = $this->voucherModel->getById((string) ($result['id'] ?? '')) ?: [];

            return [
                'success' => true,
                'message' => '취소전표가 생성되었습니다.',
                'data' => array_merge($voucher, $result),
            ];
        });
    }

    public function apiLinkTransaction(): void
    {
        $this->jsonResponse(function (): array {
            $id = $this->requestVoucherId();
            $transactionId = $this->requestValue('linked_transaction_id');
            $result = $this->service->updateTransactionLinkOnly($id, $transactionId, ActorHelper::user());
            $voucher = $this->voucherModel->getById($id) ?: [];

            return [
                'success' => true,
                'message' => '거래 연결이 저장되었습니다.',
                'data' => array_merge($voucher, $result),
            ];
        });
    }

    public function apiReject(): void
    {
        $this->jsonResponse(function (): array {
            $id = $this->requestVoucherId();
            $reason = $this->requestValue('reason');
            $result = $this->service->reject($id, $reason);
            $voucher = $this->voucherModel->getById($id) ?: [];

            return [
                'success' => true,
                'message' => '반려 처리되었습니다.',
                'data' => array_merge($voucher, $result),
            ];
        });
    }

    public function apiDelete(): void
    {
        $this->jsonResponse(function (): array {
            $id = trim((string) ($_POST['id'] ?? ''));
            if ($id === '') {
                return [
                    'success' => false,
                    'message' => '전표 ID가 없습니다.',
                ];
            }

            $this->service->deleteVoucher($id);

            return [
                'success' => true,
                'message' => '삭제 완료',
            ];
        });
    }

    public function apiTrashList(): void
    {
        $this->jsonResponse(function (): array {
            $stmt = $this->pdo->query("
                SELECT *
                FROM ledger_vouchers
                WHERE deleted_at IS NOT NULL
                ORDER BY deleted_at DESC, sort_no DESC
            ");

            return [
                'success' => true,
                'message' => '조회 완료',
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            ];
        });
    }

    public function apiRestore(): void
    {
        $this->jsonResponse(function (): array {
            $id = trim((string) ($_POST['id'] ?? ''));
            if ($id === '') {
                return [
                    'success' => false,
                    'message' => '전표 ID가 없습니다.',
                ];
            }

            $this->pdo->beginTransaction();
            $this->restoreVoucherById($id);
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '복원 완료',
            ];
        });
    }

    public function apiRestoreBulk(): void
    {
        $this->jsonResponse(function (): array {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $ids = array_values(array_filter((array) ($input['ids'] ?? [])));

            if ($ids === []) {
                return [
                    'success' => false,
                    'message' => '복원할 전표를 선택해 주세요.',
                ];
            }

            $this->pdo->beginTransaction();
            foreach ($ids as $id) {
                $this->restoreVoucherById((string) $id);
            }
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '선택 복원 완료',
            ];
        });
    }

    public function apiRestoreAll(): void
    {
        $this->jsonResponse(function (): array {
            $stmt = $this->pdo->query("
                SELECT id
                FROM ledger_vouchers
                WHERE deleted_at IS NOT NULL
            ");
            $ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'id');

            $this->pdo->beginTransaction();
            foreach ($ids as $id) {
                $this->restoreVoucherById((string) $id);
            }
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '전체 복원 완료',
            ];
        });
    }

    public function apiPurge(): void
    {
        $this->jsonResponse(function (): array {
            $id = trim((string) ($_POST['id'] ?? ''));
            if ($id === '') {
                return [
                    'success' => false,
                    'message' => '전표 ID가 없습니다.',
                ];
            }

            $this->pdo->beginTransaction();
            $this->purgeVoucherById($id);
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '완전 삭제 완료',
            ];
        });
    }

    public function apiPurgeBulk(): void
    {
        $this->jsonResponse(function (): array {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $ids = array_values(array_filter((array) ($input['ids'] ?? [])));

            if ($ids === []) {
                return [
                    'success' => false,
                    'message' => '완전 삭제할 전표를 선택해 주세요.',
                ];
            }

            $this->pdo->beginTransaction();
            foreach ($ids as $id) {
                $this->purgeVoucherById((string) $id);
            }
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '선택 완전 삭제 완료',
            ];
        });
    }

    public function apiPurgeAll(): void
    {
        $this->jsonResponse(function (): array {
            $stmt = $this->pdo->query("
                SELECT id
                FROM ledger_vouchers
                WHERE deleted_at IS NOT NULL
            ");
            $ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'id');

            $this->pdo->beginTransaction();
            foreach ($ids as $id) {
                $this->purgeVoucherById((string) $id);
            }
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '전체 완전 삭제 완료',
            ];
        });
    }

    private function jsonResponse(callable $callback): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            echo json_encode($callback(), JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $payload = [
                'success' => false,
                'message' => $e->getMessage(),
            ];
            if ($e instanceof VoucherValidationException) {
                $payload['validation_type'] = $e->getValidationType();
            }

            http_response_code(400);
            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    private function requestVoucherId(): string
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = [];
        }

        $id = trim((string) ($_POST['id'] ?? $input['id'] ?? $_GET['id'] ?? ''));
        if ($id === '') {
            throw new \RuntimeException('전표 ID가 없습니다.');
        }

        return $id;
    }

    private function requestValue(string $key): string
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = [];
        }

        return trim((string) ($_POST[$key] ?? $input[$key] ?? $_GET[$key] ?? ''));
    }

    private function restoreVoucherById(string $id): void
    {
        if ($id === '') {
            return;
        }

        // Links are intentionally not auto-restored here. Reconnect vouchers explicitly from the transaction or voucher UI.
        $this->voucherModel->restore($id, null);
    }

    private function purgeVoucherById(string $id): void
    {
        if ($id === '') {
            return;
        }

        $actor = ActorHelper::user();
        $transactionIds = array_values(array_unique(array_filter(array_map(
            static fn(array $link): string => trim((string) ($link['transaction_id'] ?? '')),
            $this->transactionLinkModel->getList([
                'voucher_id' => $id,
                'is_active' => 1,
            ])
        ))));

        foreach ($transactionIds as $transactionId) {
            $this->transactionLinkModel->softDeleteByTransactionAndVoucher($transactionId, $id, $actor);
            $this->transactionCrudService->recalculateMatchStatus($transactionId, $actor);
        }

        if (!$this->voucherModel->hardDelete($id)) {
            throw new \RuntimeException('전표 완전 삭제에 실패했습니다.');
        }
    }

}
