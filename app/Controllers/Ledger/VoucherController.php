<?php

namespace App\Controllers\Ledger;

use App\Controllers\System\LayoutController;
use App\Services\Ledger\EvidenceTypePolicyService;
use App\Services\Ledger\JournalRecommendationGuardService;
use App\Services\Ledger\JournalRecommendationGuardException;
use App\Services\Ledger\VoucherEvidenceRecommendationService;
use App\Services\Ledger\VoucherNumberService;
use App\Services\Ledger\VoucherPurgeService;
use App\Services\Ledger\VoucherQueryService;
use App\Services\Ledger\VoucherService;
use App\Services\Ledger\VoucherStatus;
use App\Services\Ledger\VoucherValidationException;
use Core\DbPdo;
use Core\Helpers\ActorHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use Core\Session;
use PDO;

class VoucherController
{
    private PDO $pdo;
    private LayoutController $layout;
    private VoucherService $service;
    private VoucherNumberService $voucherNumberService;
    private VoucherPurgeService $voucherPurgeService;
    private VoucherQueryService $queryService;
    private EvidenceTypePolicyService $evidenceTypePolicyService;
    private VoucherEvidenceRecommendationService $evidenceRecommendationService;
    private JournalRecommendationGuardService $recommendationGuardService;

    public function __construct()
    {
        $this->pdo = DbPdo::conn();
        $this->layout = new LayoutController($this->pdo);
        $this->service = new VoucherService($this->pdo);
        $this->voucherNumberService = new VoucherNumberService($this->pdo);
        $this->voucherPurgeService = new VoucherPurgeService($this->pdo);
        $this->queryService = new VoucherQueryService($this->pdo);
        $this->evidenceTypePolicyService = new EvidenceTypePolicyService(null, $this->pdo);
        $this->evidenceRecommendationService = new VoucherEvidenceRecommendationService($this->pdo);
        $this->recommendationGuardService = new JournalRecommendationGuardService($this->pdo);
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
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
            'pageAssetProfile' => $pageAssetProfile ?? 'default',
            'layoutOptions' => $layoutOptions ?? [],
        ]);
    }

    public function webInput(): void
    {
        $this->renderPage('/app/views/ledger/journal/index.php', [
            'pageTitle' => 'Voucher Input',
        ]);
    }

    public function apiList(): void
    {
        $_GET = \Core\Helpers\DataTableRequestHelper::input();
        Session::write();
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
            if (trim((string) ($_GET['scope'] ?? '')) === 'review') {
                $filters['statuses'] = VoucherStatus::reviewListValues();

                $page = $this->queryService->getReviewPage($_GET, $filters);

                return [
                    'success' => true,
                    'message' => '전표 목록을 불러왔습니다.',
                    'draw' => (int) ($_GET['draw'] ?? 0),
                    'recordsTotal' => $page['records_total'],
                    'recordsFiltered' => $page['records_filtered'],
                    'data' => $page['rows'],
                ];
            }

            return [
                'success' => true,
                'message' => '전표 목록을 불러왔습니다.',
                'data' => $this->queryService->getList($filters),
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
                    'message' => 'No reorder changes provided.',
                ];
            }

            $this->service->reorder($changes);

            return [
                'success' => true,
                'message' => 'Order saved.',
            ];
        });
    }

    public function apiDetail(): void
    {
        Session::write();
        $this->jsonResponse(function (): array {
            $id = trim((string) ($_GET['id'] ?? ''));
            if ($id === '') {
                return [
                    'success' => false,
                    'message' => '조회할 전표를 선택해 주세요.',
                ];
            }

            $voucher = $this->queryService->getDetail(
                $id,
                fn(array $row): array => $this->normalizeEvidenceSearchRow($row)
            );
            if (!$voucher) {
                return [
                    'success' => false,
                    'message' => '전표를 찾을 수 없습니다.',
                ];
            }

            return [
                'success' => true,
                'message' => '전표 상세정보를 불러왔습니다.',
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
            $allowedStatuses = VoucherStatus::pickerValues();
            $requestedStatuses = $_GET['status'] ?? $allowedStatuses;
            if (!is_array($requestedStatuses)) {
                $requestedStatuses = [$requestedStatuses];
            }
            $statuses = array_values(array_intersect(
                $allowedStatuses,
                array_map(static fn($status): string => VoucherStatus::normalize($status, ''), $requestedStatuses)
            ));
            if ($statuses === []) {
                $statuses = $allowedStatuses;
            }
            return $this->queryService->searchForPicker([
                'statuses' => $statuses,
                'keyword' => $keyword,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'client_id' => $clientId,
                'min_amount' => $minAmount,
                'max_amount' => $maxAmount,
            ]);
        });
    }

    public function apiEvidenceSearch(): void
    {
        $_GET = \Core\Helpers\DataTableRequestHelper::input();
        $this->jsonResponse(function (): array {
            $query = trim((string) ($_GET['q'] ?? ''));
            $evidenceType = strtoupper(trim((string) ($_GET['evidence_type'] ?? 'ALL')));
            if (!in_array($evidenceType, ['ALL', 'DATA', 'FUND', 'BOTH'], true)) {
                throw new \InvalidArgumentException('증빙 검색 구분이 올바르지 않습니다.');
            }
            $policyTypes = $evidenceType === 'ALL' ? ['DATA', 'FUND', 'BOTH'] : [$evidenceType];
            $metadataOptions = $this->queryService->activeEvidenceMetadataOptions();
            $activeImportTypes = array_values(array_unique(array_map(
                static fn(array $option): string => strtoupper(trim((string) ($option['import_type'] ?? ''))),
                $metadataOptions
            )));
            $importType = strtoupper(trim((string) ($_GET['import_type'] ?? 'ALL')));
            if ($importType !== 'ALL' && !in_array($importType, $activeImportTypes, true)) {
                throw new \InvalidArgumentException('자료유형 검색 조건이 올바르지 않습니다.');
            }
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = max(10, min(100, (int) ($_GET['per_page'] ?? 20)));
            $sort = strtolower(trim((string) ($_GET['sort'] ?? 'date_desc')));
            if (!in_array($sort, ['date_desc', 'date_asc', 'amount_desc', 'amount_asc'], true)) {
                $sort = 'date_desc';
            }
            $sortField = strtolower(trim((string) ($_GET['sort_field'] ?? 'evidence_date')));
            $sortDirection = strtolower(trim((string) ($_GET['sort_direction'] ?? 'desc'))) === 'asc' ? 'asc' : 'desc';
            $orderField = match ($sortField) {
                'display_amount' => 'display_amount',
                'import_type', 'evidence_type' => 'import_type',
                'client_name' => 'client_search_name',
                'project_name' => 'project_search_name',
                'employee_name' => 'employee_search_name',
                'description', 'display_summary' => 'description',
                default => 'standard_date',
            };
            $currentVoucherId = trim((string) ($_GET['voucher_id'] ?? ''));
            $currentLinks = $currentVoucherId !== '' ? $this->queryService->getVoucherEvidences($currentVoucherId) : [];
            $releasedEvidences = json_decode((string) ($_GET['released_evidences'] ?? '[]'), true);
            $releasedEvidences = is_array($releasedEvidences) ? array_values(array_filter(
                $releasedEvidences,
                static fn(mixed $item): bool => is_array($item)
                    && trim((string) ($item['import_type'] ?? '')) !== ''
                    && trim((string) ($item['evidence_id'] ?? '')) !== ''
            )) : [];
            $releasedKeys = array_fill_keys(array_map(
                static fn(array $item): string => strtoupper(trim((string) $item['import_type']))
                    . ':' . trim((string) $item['evidence_id']),
                $releasedEvidences
            ), true);
            $currentLinks = array_values(array_filter(
                $currentLinks,
                static fn(array $item): bool => !isset($releasedKeys[
                    strtoupper(trim((string) ($item['import_type'] ?? '')))
                    . ':' . trim((string) ($item['evidence_id'] ?? ''))
                ])
            ));
            $requestedExclusions = json_decode((string) ($_GET['exclude_evidences'] ?? '[]'), true);
            $requestedExclusions = is_array($requestedExclusions) ? array_values(array_filter(
                $requestedExclusions,
                static fn(mixed $item): bool => is_array($item)
                    && trim((string) ($item['import_type'] ?? '')) !== ''
                    && trim((string) ($item['evidence_id'] ?? '')) !== ''
            )) : [];
            $result = $this->queryService->pagedEvidenceProjections([
                'evidence_types' => $policyTypes,
                'import_types' => $importType === 'ALL' ? [] : [$importType],
                'keyword' => $query,
                'unlinked_voucher_only' => true,
                'current_voucher_id' => $currentVoucherId,
                'released_voucher_evidences' => $releasedEvidences,
                'exclude_evidences' => [...$currentLinks, ...$requestedExclusions],
                'start' => ($page - 1) * $perPage,
                'length' => $perPage,
                'order_field' => $orderField,
                'order_direction' => isset($_GET['sort_field']) ? $sortDirection : (str_ends_with($sort, '_asc') ? 'asc' : 'desc'),
            ]);
            $rows = array_map(function (array $projection): array {
                $row = $this->normalizeEvidenceSearchRow(array_merge($projection['body'] ?? [], $projection['identity'] ?? []));
                $evidenceStatus = strtoupper(trim((string) ($row['evidence_status'] ?? '')));
                $row['processing_status_label'] = $evidenceStatus === 'COMPLETED'
                    ? '완료'
                    : '미완료';
                $row['processing_status_reason'] = '';
                return $row;
            }, $result['projections'] ?? []);
            $total = (int) ($result['records_filtered'] ?? 0);
            $lastPage = max(1, (int) ceil($total / $perPage));

            return [
                'success' => true,
                'message' => 'List loaded.',
                'draw' => max(1, (int) ($_GET['draw'] ?? 1)),
                'recordsTotal' => $total,
                'recordsFiltered' => $total,
                'data' => [
                    'items' => array_values($rows),
                    'pagination' => [
                        'page' => $page,
                        'per_page' => $perPage,
                        'total' => $total,
                        'last_page' => $lastPage,
                    ],
                    'filters' => [
                        'import_types' => array_map(fn(array $option): array => [
                            'value' => (string) ($option['import_type'] ?? ''),
                            'label' => $this->evidenceTypePolicyService->importTypeLabel((string) ($option['import_type'] ?? '')),
                        ], $metadataOptions),
                    ],
                ],
            ];
        });
    }

    public function apiEvidenceRecommendations(): void
    {
        $this->jsonResponse(function (): array {
            $input = json_decode((string) file_get_contents('php://input'), true);
            $evidences = is_array($input['evidences'] ?? null) ? $input['evidences'] : [];
            $accountingDate = trim((string) ($input['accounting_date'] ?? ''));
            $this->recommendationGuardService->assertRecommendationAllowed($evidences);
            $results = $this->evidenceRecommendationService->recommend($evidences, $accountingDate);
            return [
                'success' => true,
                'message' => '분개 추천을 조회했습니다.',
                'data' => [
                    'results' => $results,
                    'recommendations' => $this->evidenceRecommendationService->recommendationSets($results),
                    'coverage' => $this->evidenceRecommendationService->coverage($results),
                ],
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
            $payload['linked_evidences'] = json_decode((string) ($_POST['linked_evidences'] ?? '[]'), true) ?? [];
            $this->recommendationGuardService->assertApplicationAllowed($payload['linked_evidences'], $payload['lines']);
            $result = $this->service->save($payload);

            return [
                'success' => (bool) ($result['success'] ?? false),
                'message' => ($result['success'] ?? false) ? 'Saved.' : ($result['message'] ?? 'Save failed.'),
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
            throw new \RuntimeException("\u{C804}\u{D45C} \u{C0C1}\u{D0DC} \u{BCC0}\u{ACBD}\u{C740} \u{C9C0}\u{C6D0}\u{D558}\u{C9C0} \u{C54A}\u{C2B5}\u{B2C8}\u{B2E4}.");
        });
    }

    public function apiRequestReview(): void
    {
        $this->jsonResponse(function (): array {
            $id = $this->requestVoucherId();
            $result = $this->service->requestReview($id);
            $voucher = $this->queryService->getById($id) ?: [];

            return [
                'success' => true,
                'message' => '검토요청되었습니다.',
                'data' => array_merge($voucher, $result),
            ];
        });
    }

    public function apiCancelReviewRequest(): void
    {
        $this->jsonResponse(function (): array {
            $id = $this->requestVoucherId();
            $result = $this->service->cancelReviewRequest($id);
            $voucher = $this->queryService->getById($id) ?: [];

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
            $voucher = $this->queryService->getById($id) ?: [];

            return [
                'success' => true,
                'message' => '검토완료되었습니다.',
                'data' => array_merge($voucher, $result),
            ];
        });
    }

    public function apiCancelCompleteReview(): void
    {
        $this->jsonResponse(function (): array {
            $id = $this->requestVoucherId();
            $result = $this->service->cancelCompleteReview($id);
            $voucher = $this->queryService->getById($id) ?: [];

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
            $voucher = $this->queryService->getById($id) ?: [];

            return [
                'success' => true,
                'message' => '전표승인되었습니다.',
                'data' => array_merge($voucher, $result),
            ];
        });
    }

    public function apiReverse(): void
    {
        $this->jsonResponse(function (): array {
            $id = $this->requestVoucherId();
            $result = $this->service->createReversalVoucher($id, ActorHelper::user());
            $voucher = $this->queryService->getById((string) ($result['id'] ?? '')) ?: [];

            return [
                'success' => true,
                'message' => '취소전표가 생성되었습니다.',
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
            $voucher = $this->queryService->getById($id) ?: [];

            return [
                'success' => true,
                'message' => '전표가 반려되었습니다.',
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
                    'message' => 'Voucher ID is required.',
                ];
            }

            $this->service->deleteVoucher($id);

            return [
                'success' => true,
                'message' => 'Deleted.',
            ];
        });
    }

    public function apiTrashList(): void
    {
        $this->jsonResponse(function (): array {
            return [
                'success' => true,
                'message' => 'List loaded.',
                'data' => $this->queryService->getTrashList(),
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
                    'message' => 'Voucher ID is required.',
                ];
            }

            $this->restoreVoucherById($id);

            return [
                'success' => true,
                'message' => 'Trash loaded.',
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
                    'message' => 'No voucher selected for restore.',
                ];
            }

            foreach ($ids as $id) {
                $this->restoreVoucherById((string) $id);
            }

            return [
                'success' => true,
                'message' => 'Selected vouchers restored.',
            ];
        });
    }

    public function apiRestoreAll(): void
    {
        $this->jsonResponse(function (): array {
            $ids = $this->queryService->getDeletedIds();

            foreach ($ids as $id) {
                $this->restoreVoucherById((string) $id);
            }

            return [
                'success' => true,
                'message' => 'All vouchers restored.',
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
                    'message' => 'Voucher ID is required.',
                ];
            }

            $this->voucherPurgeService->purge([$id]);

            return [
                'success' => true,
                'message' => '전표가 영구삭제되었습니다.',
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
                    'message' => '영구삭제할 전표를 선택해 주세요.',
                ];
            }

            $this->voucherPurgeService->purge($ids);

            return [
                'success' => true,
                'message' => '선택한 전표가 영구삭제되었습니다.',
            ];
        });
    }

    public function apiPurgeAll(): void
    {
        $this->jsonResponse(function (): array {
            $ids = $this->queryService->getDeletedIds();

            $this->voucherPurgeService->purge($ids);

            return [
                'success' => true,
                'message' => '휴지통의 전체 전표가 영구삭제되었습니다.',
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
            if ($e instanceof JournalRecommendationGuardException) {
                $payload['reason_code'] = $e->reasonCode();
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
            throw new \RuntimeException('전표 ID를 확인할 수 없습니다.');
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

    private function normalizeEvidenceSearchRow(array $row): array
    {
        $payload = $this->decodeJsonObject($row['mapped_payload_json'] ?? null);
        $sourceType = strtoupper(trim((string) ($row['source_type'] ?? '')));
        $row['display_type'] = $this->evidenceTypePolicyService->importTypeLabel((string) ($row['import_type'] ?? $sourceType));
        $row['evidence_date'] = $row['standard_date']
            ?? $row['raw_expense_date']
            ?? $row['raw_transaction_datetime']
            ?? $row['raw_approval_date']
            ?? $row['raw_written_date']
            ?? $row['raw_issue_date']
            ?? $row['evidence_date']
            ?? null;
        $semanticValues = $this->queryService->evidenceSemanticValues((string) ($row['import_type'] ?? ''), $row);
        $row['display_key'] = $this->evidenceDisplayKey($row, $payload);
        $row['display_summary'] = $this->firstSemanticText($semanticValues['DESCRIPTION'] ?? []);
        $row['display_amount'] = $this->evidenceDisplayAmount($row, $payload);
        $depositAmount = $this->firstSemanticNumeric($semanticValues['IN_AMOUNT'] ?? []);
        $withdrawAmount = $this->firstSemanticNumeric($semanticValues['OUT_AMOUNT'] ?? []);
        $row['display_amount_sign'] = '';
        if ($depositAmount > 0) {
            $row['display_amount'] = abs($depositAmount);
            $row['display_amount_sign'] = '+';
        } elseif ($withdrawAmount > 0) {
            $row['display_amount'] = abs($withdrawAmount);
            $row['display_amount_sign'] = '-';
        } elseif (($semanticValues['POST_TAX_AMOUNT'] ?? []) !== []) {
            $row['display_amount'] = $this->firstSemanticNumeric($semanticValues['POST_TAX_AMOUNT']);
        } elseif (($semanticValues['PRE_TAX_AMOUNT'] ?? []) !== []) {
            $row['display_amount'] = $this->firstSemanticNumeric($semanticValues['PRE_TAX_AMOUNT'])
                + array_sum(array_map(
                    fn(mixed $value): float => $this->numericOrNull($value) ?? 0.0,
                    $semanticValues['ADJUST_AMOUNT'] ?? []
                ));
        }
        $row['client_name'] = trim((string) ($row['client_name']
            ?? $row['raw_counterparty_name']
            ?? $row['raw_merchant_company_name']
            ?? $row['raw_supplier_company_name']
            ?? $row['raw_customer_company_name']
            ?? '')) ?: $this->firstPayloadValue($payload, [
            'client_name',
            'client_company_name',
            'company_name',
            'counterparty_name',
            'counterparty_account_holder_name',
            'account_holder',
        ]);
        $row['project_name'] = trim((string) ($row['project_name'] ?? ''));
        $row['employee_name'] = trim((string) ($row['employee_name'] ?? ''));
        $row['bank_account_name'] = trim((string) ($row['bank_account_name'] ?? ''));
        $row['card_name'] = trim((string) ($row['card_name'] ?? ''));
        $row['team_name'] = trim((string) ($row['team_name'] ?? ''));
        unset($row['mapped_payload_json']);

        return $row;
    }

    private function evidenceDisplayKey(array $row, array $payload): string
    {
        return $this->firstPayloadValue($payload, [
            'approval_number',
            'approval_no',
            'invoice_number',
            'cash_receipt_no',
            'card_approval_no',
            'transaction_no',
            'document_no',
        ]) ?: $this->humanEvidenceText(
            $row['raw_approval_number'] ?? $row['raw_approval_no'] ?? $row['source_key'] ?? null
        );
    }

    private function firstSemanticText(array $values): string
    {
        foreach ($values as $value) {
            $text = $this->humanEvidenceText($value);
            if ($text !== '') {
                return $text;
            }
        }
        return '';
    }

    private function evidenceDisplayAmount(array $row, array $payload): float
    {
        foreach (
            [
                $row['total_amount'] ?? null,
                $row['raw_total_amount'] ?? null,
                $row['raw_transaction_amount_krw'] ?? null,
                $row['raw_actual_billing_amount'] ?? null,
                $row['raw_billing_amount'] ?? null,
                $row['raw_deposit_amount'] ?? null,
                $row['raw_withdraw_amount'] ?? null,
                $payload['total_amount'] ?? null,
                $payload['amount'] ?? null,
                $payload['billing_amount'] ?? null,
                $payload['purchase_amount'] ?? null,
                $payload['approval_amount'] ?? null,
                $payload['deposit_amount'] ?? null,
                $payload['withdraw_amount'] ?? null,
            ] as $value
        ) {
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
            $value = $this->humanEvidenceText($payload[$key] ?? null);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function humanEvidenceText(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }
        $text = trim((string) $value);
        if ($text === '' || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $text)) {
            return '';
        }
        return $text;
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

    private function firstSemanticNumeric(array $values): float
    {
        foreach ($values as $value) {
            $numeric = $this->numericOrNull($value);
            if ($numeric !== null && abs($numeric) > 0) return abs($numeric);
        }
        return 0.0;
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

    private function restoreVoucherById(string $id): void
    {
        if ($id === '') {
            return;
        }

        $this->service->restoreVoucher($id);
    }

}
