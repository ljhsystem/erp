<?php

namespace App\Controllers\Ledger;

use App\Models\Ledger\TransactionLinkModel;
use App\Models\Ledger\TransactionModel;
use App\Models\Ledger\VoucherLineRefModel;
use App\Models\Ledger\VoucherLineModel;
use App\Models\Ledger\VoucherModel;
use App\Models\Ledger\VoucherPaymentModel;
use App\Services\Ledger\TransactionCrudService;
use App\Services\Ledger\VoucherNumberService;
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
    private VoucherNumberService $voucherNumberService;
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
        $this->voucherNumberService = new VoucherNumberService($this->pdo);
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
            $fallbackRefsByLineNo = $this->evidenceVoucherRefsByLineNo($id);
            foreach ($voucher['lines'] as &$line) {
                $refs = $lineRefs[$line['id']] ?? [];
                $fallbackRefs = $fallbackRefsByLineNo[(int) ($line['line_no'] ?? 0)] ?? [];
                if ($fallbackRefs !== []) {
                    $existingTypes = [];
                    foreach ($refs as $ref) {
                        $type = $this->normalizeVoucherRefType((string) ($ref['ref_type'] ?? $ref['line_ref_type'] ?? ''));
                        if ($type !== '') {
                            $existingTypes[$type] = true;
                        }
                    }
                    foreach ($fallbackRefs as $fallbackRef) {
                        $type = $this->normalizeVoucherRefType((string) ($fallbackRef['ref_type'] ?? $fallbackRef['line_ref_type'] ?? ''));
                        if ($type === '' || isset($existingTypes[$type])) {
                            continue;
                        }
                        $refs[] = $fallbackRef;
                        $existingTypes[$type] = true;
                    }
                }

                $line['refs'] = array_map(static fn(array $ref): array => [
                    'ref_type' => $ref['ref_type'] ?? '',
                    'ref_id' => $ref['ref_id'] ?? '',
                    'line_ref_type' => $ref['line_ref_type'] ?? $ref['ref_type'] ?? '',
                    'line_ref_id' => $ref['line_ref_id'] ?? $ref['ref_id'] ?? '',
                    'line_ref_label' => $ref['line_ref_label'] ?? $ref['ref_label'] ?? '',
                    'ref_label' => $ref['ref_label'] ?? $ref['line_ref_label'] ?? '',
                    'client_name' => $ref['client_name'] ?? '',
                    'project_name' => $ref['project_name'] ?? '',
                    'employee_name' => $ref['employee_name'] ?? '',
                    'bank_account_name' => $ref['bank_account_name'] ?? '',
                    'account_name' => $ref['bank_account_name'] ?? $ref['account_name'] ?? '',
                    'card_name' => $ref['card_name'] ?? '',
                    'is_primary' => (int) ($ref['is_primary'] ?? 0),
                ], $refs);
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
            $voucher['linked_evidences'] = $this->voucherSeedSourcesByVoucherId($id);
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
            $currentVoucherId = trim((string) ($_GET['voucher_id'] ?? ''));
            $rows = $this->transactionModel->getList([]);
            foreach ($rows as &$row) {
                $transactionId = (string) ($row['id'] ?? '');
                $row['import_type'] = $this->transactionImportType($transactionId);
                $row['seed_source'] = $this->transactionSeedSource($transactionId);
                $row['display_type'] = $this->transactionDisplayType($row);
                $row['display_summary'] = $this->transactionDisplaySummary($row);
                $row['display_amount'] = (float) ($row['total_amount'] ?? $row['amount'] ?? 0);
                $row['linked_voucher'] = $this->linkedVoucherInfoForTransaction($transactionId);
                $row['is_linked_to_current_voucher'] = $currentVoucherId !== ''
                    && (string) ($row['linked_voucher']['id'] ?? '') === $currentVoucherId;
                $row['is_linked_to_other_voucher'] = (string) ($row['linked_voucher']['id'] ?? '') !== ''
                    && !$row['is_linked_to_current_voucher'];
            }
            unset($row);

            if ($query !== '') {
                $rows = array_values(array_filter($rows, function (array $row) use ($query): bool {
                    $haystack = implode(' ', [
                        $row['sort_no'] ?? '',
                        $row['transaction_date'] ?? '',
                        $row['transaction_type'] ?? '',
                        $row['import_type'] ?? '',
                        $row['display_type'] ?? '',
                        $row['client_name'] ?? '',
                        $row['project_name'] ?? '',
                        $row['item_summary'] ?? '',
                        $row['description'] ?? '',
                        $row['total_amount'] ?? '',
                        $row['amount'] ?? '',
                        $row['display_amount'] ?? '',
                        $row['display_summary'] ?? '',
                    ]);

                    return $this->searchTextMatches($haystack, $query);
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
            $hasFormats = $this->tableExists('ledger_data_formats')
                && $this->tableColumnExists('ledger_data_evidences', 'format_id');
            $formatSelect = $hasFormats ? "COALESCE(f.format_name, '')" : "''";
            $formatJoin = $hasFormats ? "
                LEFT JOIN ledger_data_formats f
                    ON f.id = e.format_id
                   AND f.deleted_at IS NULL
            " : '';
            $payloadSelect = $this->tableColumnExists('ledger_data_evidences', 'mapped_payload_json')
                ? 'e.mapped_payload_json'
                : 'NULL AS mapped_payload_json';
            $amountSelect = $this->tableColumnExists('ledger_data_evidences', 'total_amount')
                ? 'e.total_amount'
                : 'NULL AS total_amount';

            $params = [];
            $where = "e.deleted_at IS NULL";
            if ($query !== '') {
                $numericQuery = preg_replace('/[^0-9.\-]/', '', $query) ?? '';
                $where .= "
                    AND (
                        e.source_type LIKE :keyword
                        OR e.source_key LIKE :keyword
                        OR e.evidence_date LIKE :keyword
                        OR e.client_name LIKE :keyword
                        " . ($this->tableColumnExists('ledger_data_evidences', 'total_amount') ? "OR e.total_amount LIKE :keyword" : '') . "
                        " . ($this->tableColumnExists('ledger_data_evidences', 'total_amount') && $numericQuery !== '' ? "OR e.total_amount LIKE :numeric_keyword" : '') . "
                        " . ($this->tableColumnExists('ledger_data_evidences', 'mapped_payload_json') ? "OR e.mapped_payload_json LIKE :keyword" : '') . "
                        " . ($this->tableColumnExists('ledger_data_evidences', 'mapped_payload_json') && $numericQuery !== '' ? "OR e.mapped_payload_json LIKE :numeric_keyword" : '') . "
                        " . ($hasClients ? "OR sc.client_name LIKE :keyword" : '') . "
                        " . ($hasFormats ? "OR f.format_name LIKE :keyword" : '') . "
                    )
                ";
                $params[':keyword'] = '%' . $query . '%';
                if ($numericQuery !== '') {
                    $params[':numeric_keyword'] = '%' . $numericQuery . '%';
                }
            }

            $baseSelectSql = "
                SELECT
                    e.id,
                    e.source_type,
                    e.source_key,
                    e.evidence_date,
                    {$formatSelect} AS format_name,
                    {$amountSelect},
                    {$payloadSelect},
                    e.latest_imported_at AS processed_at,
                    e.created_at,
                    e.voucher_status,
                    COALESCE(NULLIF(e.client_name, ''), {$clientSelect}) AS client_name
                FROM ledger_data_evidences e
                {$clientJoin}
                {$formatJoin}
                WHERE {$where}
            ";
            $orderSql = " ORDER BY e.evidence_date DESC, e.latest_imported_at DESC, e.created_at DESC";

            if ($query !== '') {
                $stmt = $this->pdo->prepare($baseSelectSql . $orderSql . " LIMIT 200");
                $stmt->execute($params);
                $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } else {
                $rawRows = [];
                $groupSelect = $hasFormats ? 'e.source_type, e.format_id' : 'e.source_type';
                $groupOrder = $hasFormats ? 'e.source_type ASC, e.format_id ASC' : 'e.source_type ASC';
                $groupsStmt = $this->pdo->query("
                    SELECT {$groupSelect}
                    FROM ledger_data_evidences e
                    WHERE e.deleted_at IS NULL
                    GROUP BY {$groupSelect}
                    ORDER BY {$groupOrder}
                ");
                $groups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                foreach ($groups as $group) {
                    $groupWhere = $where . " AND e.source_type <=> :group_source_type";
                    $groupParams = $params;
                    $groupParams[':group_source_type'] = $group['source_type'] ?? null;
                    if ($hasFormats) {
                        $groupWhere .= " AND e.format_id <=> :group_format_id";
                        $groupParams[':group_format_id'] = $group['format_id'] ?? null;
                    }

                    $stmt = $this->pdo->prepare(str_replace("WHERE {$where}", "WHERE {$groupWhere}", $baseSelectSql) . $orderSql . " LIMIT 30");
                    $stmt->execute($groupParams);
                    $rawRows = array_merge($rawRows, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
                }
            }

            $currentVoucherId = trim((string) ($_GET['voucher_id'] ?? ''));
            $rows = array_map(function (array $row) use ($currentVoucherId): array {
                $row = $this->normalizeEvidenceSearchRow($row);
                $row['linked_voucher'] = $this->linkedVoucherInfoForEvidence((string) ($row['id'] ?? ''));
                $row['is_linked_to_current_voucher'] = $currentVoucherId !== ''
                    && (string) ($row['linked_voucher']['id'] ?? '') === $currentVoucherId;
                $row['is_linked_to_other_voucher'] = (string) ($row['linked_voucher']['id'] ?? '') !== ''
                    && !$row['is_linked_to_current_voucher'];

                return $row;
            }, $rawRows);

            if ($query !== '') {
                $rows = array_values(array_filter($rows, function (array $row) use ($query): bool {
                    $haystack = implode(' ', [
                        $row['source_type'] ?? '',
                        $row['source_key'] ?? '',
                        $row['display_key'] ?? '',
                        $row['evidence_date'] ?? '',
                        $row['processed_at'] ?? '',
                        $row['created_at'] ?? '',
                        $row['format_name'] ?? '',
                        $row['display_type'] ?? '',
                        $row['client_name'] ?? '',
                        $row['display_summary'] ?? '',
                        $row['total_amount'] ?? '',
                        $row['display_amount'] ?? '',
                    ]);

                    return $this->searchTextMatches($haystack, $query);
                }));
            }

            return [
                'success' => true,
                'message' => '조회 완료',
                'data' => $rows,
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
            $scope = strtolower(trim((string) ($_GET['scope'] ?? 'voucher')));
            $items = $scope === 'line'
                ? $this->service->searchLineSummaryTexts($query, 10)
                : $this->service->searchSummaryTexts($query, 10);

            return [
                'success' => true,
                'items' => $items,
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

            if (($result['success'] ?? false)) {
                $voucherId = (string) ($result['voucher_id'] ?? $result['id'] ?? '');
                $evidenceId = trim((string) ($payload['linked_evidence_id'] ?? ''))
                    ?: (string) ($this->voucherSeedSourceByVoucherId($voucherId)['id'] ?? '');
                $this->syncLinkedEvidenceVoucherPayload($voucherId, $evidenceId, ActorHelper::user());
            }

            return [
                'success' => (bool) ($result['success'] ?? false),
                'message' => ($result['success'] ?? false) ? '저장 완료' : ($result['message'] ?? '저장 실패'),
                'data' => $result,
            ];
        });
    }

    public function apiChangeNumber(): void
    {
        $this->jsonResponse(function (): array {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!is_array($input)) {
                $input = [];
            }

            $id = trim((string) ($_POST['id'] ?? $input['id'] ?? ''));
            $voucherNo = trim((string) ($_POST['voucher_no'] ?? $input['voucher_no'] ?? ''));
            $result = $this->voucherNumberService->change($id, $voucherNo, ActorHelper::user());

            return [
                'success' => true,
                'message' => ($result['changed'] ?? false) ? '전표번호가 변경되었습니다.' : '변경된 전표번호가 없습니다.',
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
                SELECT
                    v.*,
                    CASE
                        WHEN v.deleted_by IS NULL THEN NULL
                        WHEN v.deleted_by LIKE 'SYSTEM:%' THEN v.deleted_by
                        WHEN ue.employee_name IS NOT NULL AND ue.employee_name <> '' THEN CONCAT('USER:', ue.employee_name)
                        ELSE v.deleted_by
                    END AS deleted_by_name
                FROM ledger_vouchers v
                LEFT JOIN user_employees ue
                    ON ue.user_id = REPLACE(v.deleted_by, 'USER:', '')
                WHERE v.deleted_at IS NOT NULL
                ORDER BY v.deleted_at DESC, v.sort_no DESC
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
            $id = $this->requestValue('id');
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
            $id = $this->requestValue('id');
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

    private function transactionDisplayType(array $row): string
    {
        $type = strtoupper(trim((string) ($row['transaction_type'] ?? $row['import_type'] ?? '')));
        return match ($type) {
            'IN', 'DEPOSIT', 'SALE', 'SALES' => '입금',
            'OUT', 'WITHDRAW', 'WITHDRAWAL', 'PURCHASE', 'EXPENSE' => '출금',
            'BANK_TRANSACTION' => '입출금',
            'TAX_INVOICE' => '세금계산서',
            'CASH_RECEIPT' => '현금영수증',
            'CARD_APPROVAL', 'CARD_HOMETAX', 'CARD_STATEMENT' => '카드',
            default => $type !== '' ? $type : '거래',
        };
    }

    private function transactionDisplaySummary(array $row): string
    {
        foreach (['item_summary', 'description', 'summary_text', 'note'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '거래 내역';
    }

    private function linkedVoucherInfoForTransaction(string $transactionId): ?array
    {
        if ($transactionId === '' || !$this->tableExists('ledger_vouchers')) {
            return null;
        }

        if ($this->tableExists('ledger_transaction_links')) {
            $stmt = $this->pdo->prepare("
                SELECT v.id, v.voucher_no, v.voucher_date, v.status
                FROM ledger_transaction_links l
                INNER JOIN ledger_vouchers v
                    ON v.id = l.voucher_id
                   AND v.deleted_at IS NULL
                WHERE l.transaction_id = :transaction_id
                  AND l.deleted_at IS NULL
                  AND l.is_active = 1
                ORDER BY v.voucher_date DESC, v.voucher_no DESC
                LIMIT 1
            ");
            $stmt->execute([':transaction_id' => $transactionId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return $row;
            }
        }

        if (!$this->tableColumnExists('ledger_vouchers', 'transaction_id')) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT id, voucher_no, voucher_date, status
            FROM ledger_vouchers
            WHERE transaction_id = :transaction_id
              AND deleted_at IS NULL
            ORDER BY voucher_date DESC, voucher_no DESC
            LIMIT 1
        ");
        $stmt->execute([':transaction_id' => $transactionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function linkedVoucherInfoForEvidence(string $evidenceId): ?array
    {
        if ($evidenceId === '' || !$this->tableExists('ledger_vouchers')) {
            return null;
        }

        if ($this->tableExists('ledger_data_evidence_links')) {
            $stmt = $this->pdo->prepare("
                SELECT v.id, v.voucher_no, v.voucher_date, v.status
                FROM ledger_data_evidence_links l
                INNER JOIN ledger_vouchers v
                    ON v.id = l.voucher_id
                   AND v.deleted_at IS NULL
                WHERE l.evidence_id = :evidence_id
                  AND l.deleted_at IS NULL
                ORDER BY v.voucher_date DESC, v.voucher_no DESC
                LIMIT 1
            ");
            $stmt->execute([':evidence_id' => $evidenceId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return $row;
            }
        }

        if (!$this->tableColumnExists('ledger_vouchers', 'source_id')) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT id, voucher_no, voucher_date, status
            FROM ledger_vouchers
            WHERE source_id = :evidence_id
              AND deleted_at IS NULL
            ORDER BY voucher_date DESC, voucher_no DESC
            LIMIT 1
        ");
        $stmt->execute([':evidence_id' => $evidenceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function normalizeEvidenceSearchRow(array $row): array
    {
        $payload = $this->decodeJsonObject($row['mapped_payload_json'] ?? null);
        $sourceType = strtoupper(trim((string) ($row['source_type'] ?? '')));
        $row['display_type'] = $this->evidenceDisplayType($sourceType, (string) ($row['format_name'] ?? ''));
        $row['display_key'] = $this->evidenceDisplayKey($row, $payload);
        $row['display_summary'] = $this->evidenceDisplaySummary($row, $payload);
        $row['display_amount'] = $this->evidenceDisplayAmount($row, $payload);
        $row['client_name'] = trim((string) ($row['client_name'] ?? '')) ?: $this->firstPayloadValue($payload, [
            'client_name',
            'client_company_name',
            'company_name',
            'counterparty_name',
            'counterparty_account_holder_name',
            'account_holder',
        ]);
        unset($row['mapped_payload_json']);

        return $row;
    }

    private function evidenceDisplayType(string $sourceType, string $formatName = ''): string
    {
        $formatName = trim($formatName);
        if ($formatName !== '') {
            return $formatName;
        }

        return match ($sourceType) {
            'BANK_TRANSACTION' => '입출금',
            'TAX_INVOICE' => '세금계산서',
            'CASH_RECEIPT' => '현금영수증',
            'CARD_APPROVAL', 'CARD_HOMETAX', 'CARD_STATEMENT', 'CARD_COMPANY' => '카드',
            default => $sourceType !== '' ? $sourceType : '증빙',
        };
    }

    private function evidenceDisplayKey(array $row, array $payload): string
    {
        return $this->firstPayloadValue($payload, [
            'approval_number',
            'approval_no',
            'issue_id',
            'invoice_number',
            'cash_receipt_no',
            'card_approval_no',
            'transaction_no',
            'document_no',
        ]) ?: trim((string) ($row['source_key'] ?? ''));
    }

    private function evidenceDisplaySummary(array $row, array $payload): string
    {
        return $this->firstPayloadValue($payload, [
            'description',
            'summary_text',
            'item_summary',
            'memo',
            'note',
            'product_name',
            'item_name',
        ]) ?: trim((string) ($row['format_name'] ?? $row['source_type'] ?? '증빙'));
    }

    private function evidenceDisplayAmount(array $row, array $payload): float
    {
        foreach ([
            $row['total_amount'] ?? null,
            $payload['total_amount'] ?? null,
            $payload['amount'] ?? null,
            $payload['billing_amount'] ?? null,
            $payload['purchase_amount'] ?? null,
            $payload['approval_amount'] ?? null,
            $payload['deposit_amount'] ?? null,
            $payload['withdraw_amount'] ?? null,
        ] as $value) {
            $numeric = $this->numericOrNull($value);
            if ($numeric !== null) {
                return $numeric;
            }
        }

        return 0.0;
    }

    private function decodeJsonObject(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) ($value ?? ''), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function firstPayloadValue(array $payload, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($payload[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function searchTextMatches(string $haystack, string $query): bool
    {
        $query = trim($query);
        if ($query === '') {
            return true;
        }

        if (function_exists('mb_stripos') && mb_stripos($haystack, $query, 0, 'UTF-8') !== false) {
            return true;
        }

        if (stripos($haystack, $query) !== false) {
            return true;
        }

        $numericHaystack = preg_replace('/[^0-9.\-]/', '', $haystack) ?? '';
        $numericQuery = preg_replace('/[^0-9.\-]/', '', $query) ?? '';

        return $numericQuery !== '' && str_contains($numericHaystack, $numericQuery);
    }

    private function numericOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9.\-]/', '', (string) $value);
        if ($normalized === '' || !is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
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
        $rows = $this->voucherSeedSourcesByVoucherId($voucherId);

        return $rows[0] ?? null;
    }

    private function evidenceVoucherRefsByLineNo(string $voucherId): array
    {
        $source = $this->voucherSeedSourceByVoucherId($voucherId);
        $evidenceId = (string) ($source['id'] ?? '');
        if ($evidenceId === ''
            || !$this->tableExists('ledger_data_evidences')
            || !$this->tableColumnExists('ledger_data_evidences', 'mapped_payload_json')
        ) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT mapped_payload_json
            FROM ledger_data_evidences
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $evidenceId]);
        $mapped = $this->decodeJsonObject($stmt->fetchColumn() ?: null);
        $rawLines = is_array($mapped['_voucher_lines'] ?? null) ? $mapped['_voucher_lines'] : [];
        if ($rawLines === []) {
            return [];
        }

        $refsByLineNo = [];
        foreach ($rawLines as $rawLine) {
            if (!is_array($rawLine)) {
                continue;
            }

            $lineNo = (int) ($rawLine['line_no'] ?? 0);
            if ($lineNo <= 0) {
                continue;
            }

            $rowType = strtoupper(trim((string) ($rawLine['line_row_type'] ?? 'JOURNAL')));
            $inlineRefs = is_array($rawLine['refs'] ?? null) ? $rawLine['refs'] : [];
            if ($rowType === 'AUX') {
                $inlineRefs[] = $rawLine;
            }

            foreach ($inlineRefs as $ref) {
                if (!is_array($ref)) {
                    continue;
                }

                $refType = $this->normalizeVoucherRefType((string) ($ref['ref_type'] ?? $ref['line_ref_type'] ?? ''));
                $refId = trim((string) ($ref['ref_id'] ?? $ref['line_ref_id'] ?? ''));
                if ($refType === '' || $refId === '') {
                    continue;
                }
                $refId = $this->resolveVoucherRefId($refType, $refId) ?? $refId;

                $refsByLineNo[$lineNo][] = [
                    'ref_type' => $refType,
                    'ref_id' => $refId,
                    'line_ref_type' => $refType,
                    'line_ref_id' => $refId,
                    'ref_label' => (string) ($ref['ref_label'] ?? $ref['line_ref_label'] ?? ''),
                    'line_ref_label' => (string) ($ref['line_ref_label'] ?? $ref['ref_label'] ?? ''),
                    'is_primary' => (int) ($ref['is_primary'] ?? 0),
                ];
            }
        }

        return $refsByLineNo;
    }

    private function normalizeVoucherRefType(string $value): string
    {
        $value = strtoupper(trim($value));

        return match ($value) {
            'CLIENT', 'CUSTOMER', 'VENDOR', 'COUNTERPARTY' => 'CLIENT',
            'PROJECT' => 'PROJECT',
            'ACCOUNT', 'BANK', 'BANK_ACCOUNT' => 'ACCOUNT',
            'CARD' => 'CARD',
            'EMPLOYEE', 'USER' => 'EMPLOYEE',
            default => $value,
        };
    }

    private function resolveVoucherRefId(string $refType, string $value): ?string
    {
        $refType = $this->normalizeVoucherRefType($refType);
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1) {
            return $value;
        }

        $table = match ($refType) {
            'CLIENT' => 'system_clients',
            'PROJECT' => 'system_projects',
            'ACCOUNT' => 'system_bank_accounts',
            'CARD' => 'system_cards',
            'EMPLOYEE' => 'user_employees',
            default => null,
        };
        if ($table === null || !$this->tableExists($table)) {
            return null;
        }

        $columns = match ($table) {
            'system_clients' => ['id', 'client_name', 'company_name', 'business_number'],
            'system_projects' => ['id', 'project_name', 'project_code'],
            'system_bank_accounts' => ['id', 'account_name', 'account_number', 'bank_name'],
            'system_cards' => ['id', 'card_name', 'card_number'],
            'user_employees' => ['id', 'employee_name', 'name'],
            default => ['id'],
        };

        $where = [];
        $params = [];
        foreach ($columns as $column) {
            if (!$this->tableColumnExists($table, $column)) {
                continue;
            }
            $key = ':ref_' . $column;
            $where[] = $column . ' = ' . $key;
            $params[$key] = $value;
        }
        if ($where === []) {
            return null;
        }

        $deleted = $this->tableColumnExists($table, 'deleted_at') ? ' AND deleted_at IS NULL' : '';
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM {$table}
            WHERE (" . implode(' OR ', $where) . ")
              {$deleted}
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmt->execute($params);
        $id = $stmt->fetchColumn();

        return $id !== false ? (string) $id : null;
    }

    private function voucherSeedSourcesByVoucherId(string $voucherId): array
    {
        if ($voucherId === ''
            || !$this->tableExists('ledger_data_evidence_links')
            || !$this->tableExists('ledger_data_evidences')
        ) {
            return [];
        }

        $transactionIdSelect = $this->tableColumnExists('ledger_data_evidences', 'transaction_id')
            ? 'e.transaction_id'
            : 'NULL AS transaction_id';
        $payloadSelect = $this->tableColumnExists('ledger_data_evidences', 'mapped_payload_json')
            ? 'e.mapped_payload_json'
            : 'NULL AS mapped_payload_json';
        $amountSelect = $this->tableColumnExists('ledger_data_evidences', 'total_amount')
            ? 'e.total_amount'
            : 'NULL AS total_amount';
        $hasFormat = $this->tableExists('ledger_data_formats')
            && $this->tableColumnExists('ledger_data_evidences', 'format_id');
        $formatSelect = $hasFormat
            ? 'e.format_id, f.format_name,'
            : 'NULL AS format_id, NULL AS format_name,';
        $formatJoin = $hasFormat
            ? 'LEFT JOIN ledger_data_formats f ON f.id = e.format_id AND f.deleted_at IS NULL'
            : '';

        $stmt = $this->pdo->prepare("
            SELECT
                e.id,
                0 AS row_no,
                e.source_type,
                e.source_key,
                {$formatSelect}
                e.evidence_date,
                e.client_name,
                {$amountSelect},
                {$payloadSelect},
                {$transactionIdSelect},
                e.latest_imported_at AS processed_at,
                e.created_at
            FROM ledger_data_evidence_links l
            INNER JOIN ledger_data_evidences e
                ON e.id = l.evidence_id
               AND e.deleted_at IS NULL
            {$formatJoin}
            WHERE l.voucher_id = :voucher_id
              AND l.deleted_at IS NULL
            ORDER BY l.is_primary DESC, l.updated_at DESC, l.created_at DESC
        ");
        $stmt->execute([':voucher_id' => $voucherId]);

        return array_map(
            fn(array $row): array => $this->normalizeEvidenceSearchRow($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
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

    private function syncLinkedEvidenceVoucherPayload(string $voucherId, string $evidenceId, string $actor): void
    {
        if ($voucherId === ''
            || $evidenceId === ''
            || !$this->tableExists('ledger_data_evidences')
            || !$this->tableColumnExists('ledger_data_evidences', 'mapped_payload_json')
        ) {
            return;
        }

        $voucher = $this->voucherModel->getById($voucherId);
        if (!$voucher) {
            return;
        }

        $stmt = $this->pdo->prepare("
            SELECT mapped_payload_json
            FROM ledger_data_evidences
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $evidenceId]);
        $evidence = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$evidence) {
            return;
        }

        $mapped = $this->decodeJsonObject($evidence['mapped_payload_json'] ?? null);
        $lines = $this->voucherLineModel->getByVoucherId($voucherId);
        $lineRefs = $this->voucherLineRefModel->getGroupedByVoucherLineIds(array_column($lines, 'id'));

        $mapped['voucher_date'] = (string) ($voucher['voucher_date'] ?? '');
        $mapped['voucher_summary_text'] = (string) ($voucher['summary_text'] ?? '');
        $mapped['summary_text'] = (string) ($voucher['summary_text'] ?? '');
        $mapped['note'] = (string) ($voucher['note'] ?? '');
        $mapped['memo'] = (string) ($voucher['memo'] ?? '');
        $mapped['_voucher_lines'] = array_map(function (array $line) use ($lineRefs): array {
            $refs = array_map(function (array $ref): array {
                $label = $this->voucherLineRefLabel($ref);

                return [
                    'ref_type' => (string) ($ref['ref_type'] ?? ''),
                    'ref_id' => (string) ($ref['ref_id'] ?? ''),
                    'ref_label' => $label,
                    'line_ref_type' => (string) ($ref['ref_type'] ?? ''),
                    'line_ref_id' => (string) ($ref['ref_id'] ?? ''),
                    'line_ref_label' => $label,
                    'is_primary' => (int) ($ref['is_primary'] ?? 0),
                ];
            }, $lineRefs[$line['id']] ?? []);

            return [
                'line_no' => (int) ($line['line_no'] ?? 0),
                'line_row_type' => 'JOURNAL',
                'account_id' => (string) ($line['account_id'] ?? ''),
                'account_text' => (string) ($line['account_text'] ?? $line['account_name'] ?? ''),
                'debit' => (string) ($line['debit'] ?? '0'),
                'credit' => (string) ($line['credit'] ?? '0'),
                'line_summary' => (string) ($line['line_summary'] ?? ''),
                'refs' => $refs,
                'recommended_refs' => $refs,
                'is_user_modified' => 1,
            ];
        }, $lines);

        $sets = ['mapped_payload_json = :mapped_payload_json'];
        $params = [
            ':id' => $evidenceId,
            ':mapped_payload_json' => json_encode($mapped, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        if ($this->tableColumnExists('ledger_data_evidences', 'voucher_status')) {
            $sets[] = 'voucher_status = :voucher_status';
            $params[':voucher_status'] = 'CREATED';
        }
        if ($this->tableColumnExists('ledger_data_evidences', 'error_message')) {
            $sets[] = 'error_message = NULL';
        }
        if ($this->tableColumnExists('ledger_data_evidences', 'updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }
        if ($this->tableColumnExists('ledger_data_evidences', 'updated_by')) {
            $sets[] = 'updated_by = :actor';
            $params[':actor'] = $actor;
        }

        $this->pdo->prepare("
            UPDATE ledger_data_evidences
            SET " . implode(', ', $sets) . "
            WHERE id = :id
        ")->execute($params);
    }

    private function voucherLineRefLabel(array $ref): string
    {
        foreach ([
            'client_name',
            'project_name',
            'employee_name',
            'bank_account_name',
            'account_name',
            'card_name',
            'ref_label',
            'line_ref_label',
        ] as $key) {
            $value = trim((string) ($ref[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
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
        $storedTransactionId = trim((string) ($voucher['transaction_id'] ?? ''));
        if ($storedTransactionId !== '') {
            $transactionIds[] = $storedTransactionId;
            $transactionIds = array_values(array_unique(array_filter($transactionIds)));
        }
        $evidenceIds = $this->evidenceIdsAffectedByVoucherPurge($voucher, $transactionIds);
        $processingItemIds = $this->processingItemIdsAffectedByVoucherPurge($id);

        foreach ($transactionIds as $transactionId) {
            $this->transactionLinkModel->softDeleteByTransactionAndVoucher($transactionId, $id, $actor);
            $this->transactionCrudService->recalculateMatchStatus($transactionId, $actor);
        }

        $this->resetEvidenceVoucherStatusAfterPurge($evidenceIds, $actor, $id);
        $this->resetProcessingItemVoucherStatusAfterPurge($processingItemIds, $actor, $id);
        $this->purgeVoucherEvidenceLinksById($id);
        $this->purgeTransactionLinksByVoucherId($id);
        $this->purgeVoucherChildrenById($id);

        if (!$this->voucherModel->hardDelete($id)) {
            throw new \RuntimeException('전표 완전 삭제에 실패했습니다.');
        }
    }

    private function purgeVoucherChildrenById(string $voucherId): void
    {
        if ($voucherId === '') {
            return;
        }

        foreach ($this->voucherLineModel->getByVoucherId($voucherId) as $line) {
            $lineId = trim((string) ($line['id'] ?? ''));
            if ($lineId !== '') {
                $this->voucherLineRefModel->deleteByVoucherLineId($lineId);
            }
        }

        $this->voucherLineModel->purgeByVoucherId($voucherId);
        $this->voucherPaymentModel->purgeByVoucherId($voucherId);
    }

    private function purgeVoucherEvidenceLinksById(string $voucherId): void
    {
        if ($voucherId === '' || !$this->tableExists('ledger_data_evidence_links')) {
            return;
        }

        $stmt = $this->pdo->prepare("
            DELETE FROM ledger_data_evidence_links
            WHERE voucher_id = :voucher_id
        ");
        $stmt->execute([':voucher_id' => $voucherId]);
    }

    private function purgeTransactionLinksByVoucherId(string $voucherId): void
    {
        if ($voucherId === '' || !$this->tableExists('ledger_transaction_links')) {
            return;
        }

        $stmt = $this->pdo->prepare("
            DELETE FROM ledger_transaction_links
            WHERE voucher_id = :voucher_id
        ");
        $stmt->execute([':voucher_id' => $voucherId]);
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
                  AND voucher_status IN ('CREATED', 'PROCESSED', 'DONE', 'COMPLETED', 'POSTED', 'ERROR')
            ");
            $stmt->execute([
                ':actor' => $actor,
                ':id' => $evidenceId,
            ]);
        }
    }

    private function processingItemIdsAffectedByVoucherPurge(string $voucherId): array
    {
        if ($voucherId === '' || !$this->tableExists('ledger_processing_items')) {
            return [];
        }

        $ids = [];
        if ($this->tableColumnExists('ledger_voucher_lines', 'processing_item_id')) {
            $stmt = $this->pdo->prepare('
                SELECT processing_item_id
                FROM ledger_voucher_lines
                WHERE voucher_id = :voucher_id
                  AND processing_item_id IS NOT NULL
            ');
            $stmt->execute([':voucher_id' => $voucherId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $ids[] = (string) ($row['processing_item_id'] ?? '');
            }
        }

        if ($this->tableExists('ledger_data_evidence_links')
            && $this->tableColumnExists('ledger_data_evidence_links', 'processing_item_id')
        ) {
            $stmt = $this->pdo->prepare('
                SELECT processing_item_id
                FROM ledger_data_evidence_links
                WHERE voucher_id = :voucher_id
                  AND processing_item_id IS NOT NULL
            ');
            $stmt->execute([':voucher_id' => $voucherId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $ids[] = (string) ($row['processing_item_id'] ?? '');
            }
        }

        return array_values(array_filter(array_unique($ids)));
    }

    private function resetProcessingItemVoucherStatusAfterPurge(array $processingItemIds, string $actor, string $purgedVoucherId): void
    {
        $processingItemIds = array_values(array_filter(array_unique(array_map('strval', $processingItemIds))));
        if ($processingItemIds === [] || !$this->tableExists('ledger_processing_items')) {
            return;
        }

        foreach ($processingItemIds as $processingItemId) {
            if ($this->activeVoucherExistsForProcessingItem($processingItemId, $purgedVoucherId)) {
                continue;
            }

            $stmt = $this->pdo->prepare("
                UPDATE ledger_processing_items
                SET voucher_status = 'READY',
                    updated_at = NOW(),
                    updated_by = :actor
                WHERE id = :id
                  AND deleted_at IS NULL
                  AND voucher_status IN ('CREATED', 'PROCESSED', 'DONE', 'COMPLETED', 'POSTED', 'ERROR')
            ");
            $stmt->execute([
                ':actor' => $actor,
                ':id' => $processingItemId,
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

    private function activeVoucherExistsForProcessingItem(string $processingItemId, string $excludeVoucherId = ''): bool
    {
        if ($processingItemId === ''
            || !$this->tableExists('ledger_vouchers')
            || !$this->tableColumnExists('ledger_voucher_lines', 'processing_item_id')
        ) {
            return false;
        }

        $excludeSql = $excludeVoucherId !== '' ? 'AND v.id <> :exclude_voucher_id' : '';
        $params = [':processing_item_id' => $processingItemId];
        if ($excludeVoucherId !== '') {
            $params[':exclude_voucher_id'] = $excludeVoucherId;
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM ledger_voucher_lines l
            INNER JOIN ledger_vouchers v
                ON v.id = l.voucher_id
               AND v.deleted_at IS NULL
            WHERE l.processing_item_id = :processing_item_id
              {$excludeSql}
            LIMIT 1
        ");
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
