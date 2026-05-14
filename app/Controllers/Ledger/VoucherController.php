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
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
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
            $voucher['linked_transaction'] = null;
            $voucher['transaction_id'] = null;
            $voucher['import_type'] = null;
            $voucher['source_type'] = null;
            $voucher['seed_source'] = null;
            $voucher['linked_evidence'] = $this->voucherSeedSourceByVoucherId($id);
            $voucher['evidence_link_status'] = is_array($voucher['linked_evidence']) ? 'linked' : 'unlinked';
            $voucher['evidence_id'] = is_array($voucher['linked_evidence'])
                ? (string) ($voucher['linked_evidence']['id'] ?? '')
                : '';
            $voucher['seed_source'] = $voucher['linked_evidence']
                ?: $this->voucherSeedSource((string) ($voucher['source_id'] ?? ''));
            if (is_array($voucher['seed_source'])) {
                $voucher['import_type'] = (string) ($voucher['seed_source']['source_type'] ?? '');
                $voucher['source_type'] = $this->voucherSourceTypeFromImportType((string) $voucher['import_type'], 'MANUAL');
            }

            foreach ($this->transactionLinkModel->getByVoucherId($id) as $link) {
                $transactionId = trim((string) ($link['transaction_id'] ?? ''));
                if ($transactionId !== '') {
                    $voucher['linked_transaction'] = $this->transactionModel->getById($transactionId);
                    $voucher['transaction_id'] = $transactionId;
                    $voucher['import_type'] = $this->transactionImportType($transactionId) ?: $voucher['import_type'];
                    $voucher['seed_source'] = $voucher['seed_source'] ?: $this->transactionSeedSource($transactionId);
                    $voucher['source_type'] = $this->voucherSourceTypeFromImportType((string) ($voucher['import_type'] ?? ''), 'MANUAL');
                    if (is_array($voucher['linked_transaction'])) {
                        $voucher['linked_transaction']['import_type'] = $voucher['import_type'];
                        $voucher['linked_transaction']['seed_source'] = $voucher['seed_source'];
                    }
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
                    COALESCE(linked_clients.client_name, '') AS client_name,
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
                      OR COALESCE(linked_clients.client_name, '') LIKE :keyword
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
                $sql .= " AND COALESCE(linked_clients.client_id, '') = :client_id";
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
            foreach ($rows as &$row) {
                $transactionId = (string) ($row['id'] ?? '');
                $row['import_type'] = $this->transactionImportType($transactionId);
                $row['seed_source'] = $this->transactionSeedSource($transactionId);
            }
            unset($row);

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

    public function apiEvidenceSearch(): void
    {
        $this->jsonResponse(function (): array {
            if (!$this->tableExists('ledger_data_evidences')) {
                return [
                    'success' => true,
                    'message' => '조회 완료',
                    'data' => [],
                ];
            }

            $query = trim((string) ($_GET['q'] ?? ''));
            $hasClients = $this->tableExists('system_clients')
                && $this->tableColumnExists('ledger_data_evidences', 'client_id');
            $clientSelect = $hasClients ? "COALESCE(sc.client_name, '')" : "''";
            $clientJoin = $hasClients ? "
                LEFT JOIN system_clients sc
                    ON sc.id = e.client_id
            " : '';

            $params = [];
            $where = "e.deleted_at IS NULL";
            if ($query !== '') {
                $where .= "
                    AND (
                        e.source_type LIKE :keyword
                        OR e.source_key LIKE :keyword
                        OR e.evidence_date LIKE :keyword
                        " . ($hasClients ? "OR sc.client_name LIKE :keyword" : '') . "
                    )
                ";
                $params[':keyword'] = '%' . $query . '%';
            }

            $stmt = $this->pdo->prepare("
                SELECT
                    e.id,
                    e.source_type,
                    e.source_key,
                    e.evidence_date,
                    e.latest_imported_at AS processed_at,
                    e.created_at,
                    e.voucher_status,
                    {$clientSelect} AS client_name
                FROM ledger_data_evidences e
                {$clientJoin}
                WHERE {$where}
                ORDER BY e.evidence_date DESC, e.latest_imported_at DESC, e.created_at DESC
                LIMIT 50
            ");
            $stmt->execute($params);

            return [
                'success' => true,
                'message' => '조회 완료',
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
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
            if (($result['success'] ?? false) && trim((string) ($payload['linked_evidence_id'] ?? '')) !== '') {
                $this->replaceVoucherEvidenceLink(
                    (string) ($result['voucher_id'] ?? $result['id'] ?? ''),
                    trim((string) $payload['linked_evidence_id']),
                    trim((string) ($payload['linked_transaction_id'] ?? $payload['transaction_id'] ?? '')),
                    ActorHelper::user()
                );
            }

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
            $importType = $this->requestValue('import_type');
            $result = $this->service->updateTransactionLinkOnly($id, $transactionId, ActorHelper::user(), null, $importType);
            $voucher = $this->voucherModel->getById($id) ?: [];

            return [
                'success' => true,
                'message' => '거래 연결이 저장되었습니다.',
                'data' => array_merge($voucher, $result),
            ];
        });
    }

    public function apiLinkEvidence(): void
    {
        $this->jsonResponse(function (): array {
            $id = $this->requestVoucherId();
            $evidenceId = $this->requestValue('linked_evidence_id') ?: $this->requestValue('evidence_id');
            if ($evidenceId === '') {
                throw new \RuntimeException('연결할 증빙을 선택해 주세요.');
            }

            $actor = ActorHelper::user();
            $transactionId = $this->requestValue('linked_transaction_id') ?: $this->requestValue('transaction_id');

            $this->pdo->beginTransaction();
            $this->replaceVoucherEvidenceLink($id, $evidenceId, $transactionId, $actor);
            $this->pdo->commit();

            $voucher = $this->voucherModel->getById($id) ?: [];
            $linkedEvidence = $this->voucherSeedSourceByVoucherId($id);

            return [
                'success' => true,
                'message' => '증빙 연결이 저장되었습니다.',
                'data' => array_merge($voucher, [
                    'linked_evidence' => $linkedEvidence,
                    'evidence_id' => (string) ($linkedEvidence['id'] ?? $evidenceId),
                    'evidence_link_status' => 'linked',
                ]),
            ];
        });
    }

    public function apiUnlinkEvidence(): void
    {
        $this->jsonResponse(function (): array {
            $id = $this->requestVoucherId();
            $evidenceId = $this->requestValue('linked_evidence_id') ?: $this->requestValue('evidence_id');
            $actor = ActorHelper::user();

            $this->pdo->beginTransaction();
            $this->unlinkVoucherEvidence($id, $evidenceId, $actor);
            $this->pdo->commit();

            $voucher = $this->voucherModel->getById($id) ?: [];

            return [
                'success' => true,
                'message' => '증빙 연결을 해제했습니다.',
                'data' => array_merge($voucher, [
                    'linked_evidence' => null,
                    'evidence_id' => '',
                    'evidence_link_status' => 'unlinked',
                ]),
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
            error_log('[VoucherController] API error uri=' . ($_SERVER['REQUEST_URI'] ?? '') . ' message=' . $e->getMessage());

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

    private function transactionImportType(string $transactionId): ?string
    {
        if ($transactionId === ''
            || !$this->tableExists('ledger_data_evidences')
            || !$this->tableColumnExists('ledger_data_evidences', 'transaction_id')
        ) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT source_type
            FROM ledger_data_evidences
            WHERE transaction_id = :transaction_id
              AND deleted_at IS NULL
            ORDER BY latest_imported_at DESC, updated_at DESC, created_at DESC
            LIMIT 1
        ");
        $stmt->execute([':transaction_id' => $transactionId]);
        $sourceType = trim((string) ($stmt->fetchColumn() ?: ''));

        return $sourceType !== '' ? $sourceType : null;
    }

    private function transactionSeedSource(string $transactionId): ?array
    {
        if ($transactionId === ''
            || !$this->tableExists('ledger_data_evidences')
            || !$this->tableColumnExists('ledger_data_evidences', 'transaction_id')
        ) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT id, 0 AS row_no, source_type, source_key, latest_imported_at AS processed_at, created_at
            FROM ledger_data_evidences
            WHERE transaction_id = :transaction_id
              AND deleted_at IS NULL
            ORDER BY latest_imported_at DESC, updated_at DESC, created_at DESC
            LIMIT 1
        ");
        $stmt->execute([':transaction_id' => $transactionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return $row ?: null;
    }

    private function voucherSeedSourceByVoucherId(string $voucherId): ?array
    {
        if ($voucherId === ''
            || !$this->tableExists('ledger_data_evidence_links')
            || !$this->tableExists('ledger_data_evidences')
        ) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT e.id, 0 AS row_no, e.source_type, e.source_key, e.latest_imported_at AS processed_at, e.created_at
            FROM ledger_data_evidence_links l
            INNER JOIN ledger_data_evidences e
                ON e.id = l.evidence_id
               AND e.deleted_at IS NULL
            WHERE l.voucher_id = :voucher_id
              AND l.deleted_at IS NULL
            ORDER BY l.is_primary DESC, l.updated_at DESC, l.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([':voucher_id' => $voucherId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return $row ?: null;
    }

    private function voucherSeedSource(string $evidenceId): ?array
    {
        if ($evidenceId === '' || !$this->tableExists('ledger_data_evidences')) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT id, 0 AS row_no, source_type, source_key, latest_imported_at AS processed_at, created_at
            FROM ledger_data_evidences
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $evidenceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return $row ?: null;
    }

    private function replaceVoucherEvidenceLink(string $voucherId, string $evidenceId, string $transactionId, string $actor): void
    {
        if ($voucherId === '' || $evidenceId === '') {
            throw new \RuntimeException('전표와 증빙 정보를 확인할 수 없습니다.');
        }
        if (!$this->tableExists('ledger_data_evidences') || !$this->tableExists('ledger_data_evidence_links')) {
            throw new \RuntimeException('증빙 연결 테이블을 찾을 수 없습니다.');
        }

        if (!$this->voucherModel->getById($voucherId)) {
            throw new \RuntimeException('전표를 찾을 수 없습니다.');
        }
        if (!$this->voucherSeedSource($evidenceId)) {
            throw new \RuntimeException('증빙을 찾을 수 없습니다.');
        }

        $previousEvidenceIds = $this->evidenceIdsLinkedToVoucher($voucherId, $evidenceId);
        if ($previousEvidenceIds !== []) {
            $this->softDeleteVoucherEvidenceLinks($voucherId, $previousEvidenceIds, $actor);
        }

        $this->linkVoucherToEvidence($evidenceId, $voucherId, $transactionId, $actor);
        $this->updateEvidenceVoucherStatus($evidenceId, 'CREATED', $actor);
        $this->syncVoucherEvidenceColumns($voucherId, $evidenceId, $actor);

        foreach ($previousEvidenceIds as $previousEvidenceId) {
            if (!$this->activeVoucherExistsForEvidence($previousEvidenceId, $voucherId)) {
                $this->updateEvidenceVoucherStatus($previousEvidenceId, 'READY', $actor);
            }
        }
    }

    private function unlinkVoucherEvidence(string $voucherId, string $evidenceId, string $actor): void
    {
        if ($voucherId === '' || !$this->tableExists('ledger_data_evidence_links')) {
            return;
        }

        $evidenceIds = $evidenceId !== ''
            ? [$evidenceId]
            : $this->evidenceIdsLinkedToVoucher($voucherId);

        if ($evidenceIds === []) {
            return;
        }

        $this->softDeleteVoucherEvidenceLinks($voucherId, $evidenceIds, $actor);
        $this->clearVoucherEvidenceColumns($voucherId, $evidenceIds, $actor);

        foreach ($evidenceIds as $linkedEvidenceId) {
            if (!$this->activeVoucherExistsForEvidence($linkedEvidenceId, $voucherId)) {
                $this->updateEvidenceVoucherStatus($linkedEvidenceId, 'READY', $actor);
            }
        }
    }

    private function evidenceIdsLinkedToVoucher(string $voucherId, string $exceptEvidenceId = ''): array
    {
        if ($voucherId === '' || !$this->tableExists('ledger_data_evidence_links')) {
            return [];
        }

        $params = [':voucher_id' => $voucherId];
        $exceptSql = '';
        if ($exceptEvidenceId !== '') {
            $exceptSql = 'AND evidence_id <> :except_evidence_id';
            $params[':except_evidence_id'] = $exceptEvidenceId;
        }

        $stmt = $this->pdo->prepare("
            SELECT evidence_id
            FROM ledger_data_evidence_links
            WHERE voucher_id = :voucher_id
              AND deleted_at IS NULL
              {$exceptSql}
        ");
        $stmt->execute($params);

        return array_values(array_filter(array_unique(array_map(
            static fn(array $row): string => trim((string) ($row['evidence_id'] ?? '')),
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        ))));
    }

    private function softDeleteVoucherEvidenceLinks(string $voucherId, array $evidenceIds, string $actor): void
    {
        $evidenceIds = array_values(array_filter(array_unique(array_map('strval', $evidenceIds))));
        if ($voucherId === '' || $evidenceIds === [] || !$this->tableExists('ledger_data_evidence_links')) {
            return;
        }

        $placeholders = [];
        $params = [
            ':voucher_id' => $voucherId,
            ':deleted_by' => $actor,
            ':updated_by' => $actor,
        ];
        foreach ($evidenceIds as $index => $linkedEvidenceId) {
            $key = ':evidence_id_' . $index;
            $placeholders[] = $key;
            $params[$key] = $linkedEvidenceId;
        }

        $stmt = $this->pdo->prepare("
            UPDATE ledger_data_evidence_links
            SET deleted_at = NOW(),
                deleted_by = :deleted_by,
                is_primary = 0,
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE voucher_id = :voucher_id
              AND evidence_id IN (" . implode(', ', $placeholders) . ")
              AND deleted_at IS NULL
        ");
        $stmt->execute($params);
    }

    private function linkVoucherToEvidence(string $evidenceId, string $voucherId, string $transactionId, string $actor): void
    {
        $existing = $this->pdo->prepare("
            SELECT id
            FROM ledger_data_evidence_links
            WHERE evidence_id = :evidence_id
              AND voucher_id = :voucher_id
            ORDER BY deleted_at IS NULL DESC, updated_at DESC, created_at DESC
            LIMIT 1
        ");
        $existing->execute([
            ':evidence_id' => $evidenceId,
            ':voucher_id' => $voucherId,
        ]);
        $row = $existing->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row) {
            $this->pdo->prepare("
                UPDATE ledger_data_evidence_links
                SET transaction_id = NULLIF(:transaction_id, ''),
                    link_type = 'MANUAL',
                    is_primary = 1,
                    deleted_at = NULL,
                    deleted_by = NULL,
                    updated_at = NOW(),
                    updated_by = :actor
                WHERE id = :id
            ")->execute([
                ':id' => (string) $row['id'],
                ':transaction_id' => $transactionId,
                ':actor' => $actor,
            ]);
            return;
        }

        $this->pdo->prepare("
            INSERT INTO ledger_data_evidence_links
                (id, sort_no, evidence_id, transaction_id, voucher_id, link_type, match_amount, is_primary, created_at, created_by, updated_at, updated_by)
            VALUES
                (:id, :sort_no, :evidence_id, NULLIF(:transaction_id, ''), :voucher_id, 'MANUAL', 0, 1, NOW(), :created_by, NOW(), :updated_by)
        ")->execute([
            ':id' => UuidHelper::generate(),
            ':sort_no' => SequenceHelper::next('ledger_data_evidence_links', 'sort_no'),
            ':evidence_id' => $evidenceId,
            ':transaction_id' => $transactionId,
            ':voucher_id' => $voucherId,
            ':created_by' => $actor,
            ':updated_by' => $actor,
        ]);
    }

    private function updateEvidenceVoucherStatus(string $evidenceId, string $voucherStatus, string $actor, ?string $errorMessage = null): void
    {
        if ($evidenceId === '' || !$this->tableExists('ledger_data_evidences')) {
            return;
        }

        $stmt = $this->pdo->prepare("
            UPDATE ledger_data_evidences
            SET voucher_status = :voucher_status,
                error_message = :error_message,
                updated_at = NOW(),
                updated_by = :actor
            WHERE id = :id
              AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':id' => $evidenceId,
            ':voucher_status' => $voucherStatus,
            ':error_message' => $errorMessage,
            ':actor' => $actor,
        ]);
    }

    private function syncVoucherEvidenceColumns(string $voucherId, string $evidenceId, string $actor): void
    {
        $evidence = $this->voucherSeedSource($evidenceId);
        if (!$evidence) {
            return;
        }

        $sets = [];
        $params = [':id' => $voucherId];
        if ($this->tableColumnExists('ledger_vouchers', 'source_id')) {
            $sets[] = 'source_id = :source_id';
            $params[':source_id'] = $evidenceId;
        }
        if ($this->tableColumnExists('ledger_vouchers', 'source_type')) {
            $sets[] = 'source_type = :source_type';
            $params[':source_type'] = $this->voucherSourceTypeFromImportType((string) ($evidence['source_type'] ?? ''), 'MANUAL');
        }
        if ($this->tableColumnExists('ledger_vouchers', 'import_type')) {
            $sets[] = 'import_type = :import_type';
            $params[':import_type'] = (string) ($evidence['source_type'] ?? '');
        }
        if ($this->tableColumnExists('ledger_vouchers', 'updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }
        if ($this->tableColumnExists('ledger_vouchers', 'updated_by')) {
            $sets[] = 'updated_by = :actor';
            $params[':actor'] = $actor;
        }

        if ($sets === []) {
            return;
        }

        $this->pdo->prepare("
            UPDATE ledger_vouchers
            SET " . implode(', ', $sets) . "
            WHERE id = :id
        ")->execute($params);
    }

    private function clearVoucherEvidenceColumns(string $voucherId, array $evidenceIds, string $actor): void
    {
        if ($voucherId === '' || !$this->tableColumnExists('ledger_vouchers', 'source_id')) {
            return;
        }

        $evidenceIds = array_values(array_filter(array_unique(array_map('strval', $evidenceIds))));
        if ($evidenceIds === []) {
            return;
        }

        $placeholders = [];
        $params = [':id' => $voucherId];
        foreach ($evidenceIds as $index => $linkedEvidenceId) {
            $key = ':source_id_' . $index;
            $placeholders[] = $key;
            $params[$key] = $linkedEvidenceId;
        }

        $sets = ['source_id = NULL'];
        if ($this->tableColumnExists('ledger_vouchers', 'source_type')) {
            $sets[] = "source_type = 'MANUAL'";
        }
        if ($this->tableColumnExists('ledger_vouchers', 'import_type')) {
            $sets[] = 'import_type = NULL';
        }
        if ($this->tableColumnExists('ledger_vouchers', 'updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }
        if ($this->tableColumnExists('ledger_vouchers', 'updated_by')) {
            $sets[] = 'updated_by = :actor';
            $params[':actor'] = $actor;
        }

        $this->pdo->prepare("
            UPDATE ledger_vouchers
            SET " . implode(', ', $sets) . "
            WHERE id = :id
              AND source_id IN (" . implode(', ', $placeholders) . ")
        ")->execute($params);
    }

    private function voucherSourceTypeFromImportType(string $importType, string $fallback = 'MANUAL'): string
    {
        return match (strtoupper(trim($importType))) {
            'TAX_INVOICE', 'CASH_RECEIPT' => 'TAX',
            'CARD_STATEMENT', 'CARD_APPROVAL' => 'CARD_COMPANY',
            'BANK_TRANSACTION' => 'BANK',
            'SHOPPING_ORDER' => 'SHOPPING',
            'IMPORT_INVOICE' => 'TRADE',
            default => strtoupper(trim($fallback)) ?: 'MANUAL',
        };
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];

        if (!array_key_exists($table, $cache)) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT 1
                    FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = :table_name
                    LIMIT 1
                ");
                $stmt->execute([':table_name' => $table]);
                $cache[$table] = (bool) $stmt->fetchColumn();
            } catch (\Throwable) {
                $cache[$table] = false;
            }
        }

        return $cache[$table];
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
        $voucher = $this->voucherRowForPurge($id);
        $transactionIds = array_values(array_unique(array_filter(array_map(
            static fn(array $link): string => trim((string) ($link['transaction_id'] ?? '')),
            $this->transactionLinkModel->getList([
                'voucher_id' => $id,
                'is_active' => 1,
            ])
        ))));
        $evidenceIds = $this->evidenceIdsAffectedByVoucherPurge($voucher, $transactionIds);

        foreach ($transactionIds as $transactionId) {
            $this->transactionLinkModel->softDeleteByTransactionAndVoucher($transactionId, $id, $actor);
            $this->transactionCrudService->recalculateMatchStatus($transactionId, $actor);
        }

        $this->resetEvidenceVoucherStatusAfterPurge($evidenceIds, $actor, $id);

        if (!$this->voucherModel->hardDelete($id)) {
            throw new \RuntimeException('전표 완전 삭제에 실패했습니다.');
        }
    }

    private function voucherRowForPurge(string $voucherId): array
    {
        if ($voucherId === '') {
            return [];
        }

        $selects = ['id'];
        foreach (['source_type', 'source_id', 'import_type', 'transaction_id'] as $column) {
            $selects[] = $this->tableColumnExists('ledger_vouchers', $column) ? $column : 'NULL AS ' . $column;
        }

        $stmt = $this->pdo->prepare('
            SELECT ' . implode(', ', $selects) . '
            FROM ledger_vouchers
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $voucherId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function evidenceIdsAffectedByVoucherPurge(array $voucher, array $transactionIds): array
    {
        if (!$this->tableExists('ledger_data_evidences')) {
            return [];
        }

        $ids = [];
        $voucherId = trim((string) ($voucher['id'] ?? ''));
        if ($voucherId !== '' && $this->tableExists('ledger_data_evidence_links')) {
            $stmt = $this->pdo->prepare('
                SELECT evidence_id
                FROM ledger_data_evidence_links
                WHERE voucher_id = :voucher_id
                  AND deleted_at IS NULL
            ');
            $stmt->execute([':voucher_id' => $voucherId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $ids[] = (string) ($row['evidence_id'] ?? '');
            }
        }

        $sourceId = trim((string) ($voucher['source_id'] ?? ''));
        if ($sourceId !== '') {
            $stmt = $this->pdo->prepare('
                SELECT id
                FROM ledger_data_evidences
                WHERE id = :id
                  AND deleted_at IS NULL
                LIMIT 1
            ');
            $stmt->execute([':id' => $sourceId]);
            $evidenceId = trim((string) ($stmt->fetchColumn() ?: ''));
            if ($evidenceId !== '') {
                $ids[] = $evidenceId;
            }
        }

        $voucherTransactionId = trim((string) ($voucher['transaction_id'] ?? ''));
        if ($voucherTransactionId !== '') {
            $transactionIds[] = $voucherTransactionId;
        }
        $transactionIds = array_values(array_filter(array_unique(array_map('strval', $transactionIds))));
        if ($transactionIds !== [] && $this->tableColumnExists('ledger_data_evidences', 'transaction_id')) {
            $placeholders = [];
            $params = [];
            foreach ($transactionIds as $index => $transactionId) {
                $key = ':transaction_id_' . $index;
                $placeholders[] = $key;
                $params[$key] = $transactionId;
            }
            $stmt = $this->pdo->prepare('
                SELECT id
                FROM ledger_data_evidences
                WHERE transaction_id IN (' . implode(', ', $placeholders) . ')
                  AND deleted_at IS NULL
            ');
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $ids[] = (string) ($row['id'] ?? '');
            }
        }

        return array_values(array_filter(array_unique($ids)));
    }

    private function resetEvidenceVoucherStatusAfterPurge(array $evidenceIds, string $actor, string $purgedVoucherId): void
    {
        $evidenceIds = array_values(array_filter(array_unique(array_map('strval', $evidenceIds))));
        if ($evidenceIds === [] || !$this->tableExists('ledger_data_evidences')) {
            return;
        }

        foreach ($evidenceIds as $evidenceId) {
            if ($this->activeVoucherExistsForEvidence($evidenceId, $purgedVoucherId)) {
                continue;
            }

            $stmt = $this->pdo->prepare("
                UPDATE ledger_data_evidences
                SET voucher_status = 'READY',
                    error_message = NULL,
                    updated_at = NOW(),
                    updated_by = :actor
                WHERE id = :id
                  AND deleted_at IS NULL
                  AND source_type = 'BANK_TRANSACTION'
            ");
            $stmt->execute([
                ':actor' => $actor,
                ':id' => $evidenceId,
            ]);
        }
    }

    private function activeVoucherExistsForEvidence(string $evidenceId, string $excludeVoucherId = ''): bool
    {
        if ($evidenceId === '' || !$this->tableExists('ledger_vouchers')) {
            return false;
        }

        if ($this->tableExists('ledger_data_evidence_links')) {
            $excludeSql = $excludeVoucherId !== '' ? 'AND v.id <> :exclude_voucher_id' : '';
            $params = [':evidence_id' => $evidenceId];
            if ($excludeVoucherId !== '') {
                $params[':exclude_voucher_id'] = $excludeVoucherId;
            }

            $stmt = $this->pdo->prepare("
                SELECT 1
                FROM ledger_data_evidence_links l
                INNER JOIN ledger_vouchers v
                    ON v.id = l.voucher_id
                   AND v.deleted_at IS NULL
                WHERE l.evidence_id = :evidence_id
                  AND l.deleted_at IS NULL
                  {$excludeSql}
                LIMIT 1
            ");
            $stmt->execute($params);
            if ($stmt->fetchColumn()) {
                return true;
            }
        }

        $conditions = [];
        $params = [];
        if ($this->tableColumnExists('ledger_vouchers', 'source_id')) {
            $conditions[] = 'v.source_id = :evidence_id';
            $params[':evidence_id'] = $evidenceId;
        }
        if ($this->tableColumnExists('ledger_vouchers', 'transaction_id')
            && $this->tableColumnExists('ledger_data_evidences', 'transaction_id')
        ) {
            $conditions[] = 'v.transaction_id = e.transaction_id';
        }
        if ($conditions === []) {
            return false;
        }

        $excludeSql = $excludeVoucherId !== '' ? 'AND v.id <> :exclude_voucher_id' : '';
        if ($excludeVoucherId !== '') {
            $params[':exclude_voucher_id'] = $excludeVoucherId;
        }
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM ledger_data_evidences e
            INNER JOIN ledger_vouchers v
                ON (" . implode(' OR ', $conditions) . ")
            WHERE e.id = :row_id
              AND e.deleted_at IS NULL
              AND v.deleted_at IS NULL
              {$excludeSql}
            LIMIT 1
        ");
        $params[':row_id'] = $evidenceId;
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    private function tableColumnExists(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (!array_key_exists($key, $cache)) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT 1
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = :table_name
                      AND COLUMN_NAME = :column_name
                    LIMIT 1
                ");
                $stmt->execute([
                    ':table_name' => $table,
                    ':column_name' => $column,
                ]);
                $cache[$key] = (bool) $stmt->fetchColumn();
            } catch (\Throwable) {
                $cache[$key] = false;
            }
        }

        return $cache[$key];
    }
}
