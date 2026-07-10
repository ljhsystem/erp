<?php

namespace App\Controllers\Ledger;

use App\Controllers\System\LayoutController;
use App\Models\Ledger\EvidenceDataModel;
use App\Models\Ledger\EvidenceLinkModel;
use App\Models\Ledger\ProcessingItemModel;
use App\Models\Ledger\TransactionLinkModel;
use App\Models\Ledger\TransactionModel;
use App\Models\Ledger\VoucherLineModel;
use App\Models\Ledger\VoucherModel;
use App\Services\Ledger\TransactionCrudService;
use App\Services\Ledger\VoucherExcelService;
use App\Services\Ledger\VoucherLineRefService;
use App\Services\Ledger\VoucherNumberService;
use App\Services\Ledger\VoucherService;
use App\Services\Ledger\VoucherStatus;
use App\Services\Ledger\VoucherValidationException;
use Core\DbPdo;
use Core\Helpers\ActorHelper;
use Core\Helpers\ExcelTemplateFilenameHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use PDO;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class VoucherController
{
    private PDO $pdo;
    private LayoutController $layout;
    private VoucherService $service;
    private VoucherExcelService $excelService;
    private VoucherNumberService $voucherNumberService;
    private VoucherModel $voucherModel;
    private VoucherLineModel $voucherLineModel;
    private VoucherLineRefService $voucherLineRefService;
    private EvidenceDataModel $evidenceDataModel;
    private EvidenceLinkModel $evidenceLinkModel;
    private ProcessingItemModel $processingItemModel;
    private TransactionLinkModel $transactionLinkModel;
    private TransactionModel $transactionModel;
    private TransactionCrudService $transactionCrudService;

    public function __construct()
    {
        $this->pdo = DbPdo::conn();
        $this->layout = new LayoutController($this->pdo);
        $this->service = new VoucherService($this->pdo);
        $this->excelService = new VoucherExcelService($this->pdo);
        $this->voucherNumberService = new VoucherNumberService($this->pdo);
        $this->voucherModel = new VoucherModel($this->pdo);
        $this->voucherLineModel = new VoucherLineModel($this->pdo);
        $this->voucherLineRefService = new VoucherLineRefService($this->pdo);
        $this->evidenceDataModel = new EvidenceDataModel($this->pdo);
        $this->evidenceLinkModel = new EvidenceLinkModel($this->pdo);
        $this->processingItemModel = new ProcessingItemModel($this->pdo);
        $this->transactionLinkModel = new TransactionLinkModel($this->pdo);
        $this->transactionModel = new TransactionModel($this->pdo);
        $this->transactionCrudService = new TransactionCrudService($this->pdo);
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
            'layoutOptions' => $layoutOptions ?? [],
        ]);
    }

    public function webInput(): void
    {
        $this->renderPage('/app/views/ledger/journal/index.php', [
            'pageTitle' => 'Voucher Input',
        ]);
    }

    public function webReview(): void
    {
        $this->renderPage('/app/views/ledger/voucher/review.php', [
            'pageTitle' => '전표검토/승인',
        ]);
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
            if (trim((string) ($_GET['scope'] ?? '')) === 'review') {
                $filters['statuses'] = VoucherStatus::reviewListValues();
            }

            return [
                'success' => true,
                'message' => 'List loaded.',
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
        $this->jsonResponse(function (): array {
            $id = trim((string) ($_GET['id'] ?? ''));
            if ($id === '') {
                return [
                    'success' => false,
                    'message' => 'Voucher ID is required.',
                ];
            }

            $voucher = $this->voucherModel->getById($id);
            if (!$voucher) {
                return [
                    'success' => false,
                    'message' => 'Voucher not found.',
                ];
            }

            $voucher['lines'] = $this->voucherLineRefService->hydrateVoucherLines(
                $this->voucherLineModel->getByVoucherId($id)
            );
            $voucher['reversal_voucher'] = $this->voucherModel->findActiveReversalOf($id);
            $voucher['original_voucher'] = !empty($voucher['reversal_of'])
                ? $this->voucherModel->getById((string) $voucher['reversal_of'])
                : null;
            $voucher['seed_source'] = null;
            $voucher['linked_evidences'] = $this->voucherSeedSourcesByVoucherId($id);
            $voucher['linked_evidence'] = $this->voucherSeedSourceByVoucherId($id);
            $voucher['evidence_link_status'] = is_array($voucher['linked_evidence']) ? 'linked' : 'unlinked';
            $voucher['evidence_id'] = is_array($voucher['linked_evidence'])
                ? (string) ($voucher['linked_evidence']['id'] ?? '')
                : '';
            $voucher['seed_source'] = $voucher['linked_evidence'];

            return [
                'success' => true,
                'message' => 'Detail loaded.',
                'data' => $voucher,
            ];
        });
    }

    public function apiTemplate(): void
    {
        $this->downloadSpreadsheet(
            $this->excelService->createTemplateSpreadsheet($_GET['columns'] ?? null),
            ExcelTemplateFilenameHelper::build('voucher_input_template.xlsx')
        );
    }

    public function apiDownloadExcel(): void
    {
        $this->downloadSpreadsheet(
            $this->excelService->createExportSpreadsheet($_GET['columns'] ?? null),
            'vouchers.xlsx'
        );
    }

    public function apiExcelUpload(): void
    {
        try {
            $uploadedFile = $this->uploadedExcelFile();
            if (!$uploadedFile || empty($uploadedFile['tmp_name']) || !is_uploaded_file((string) $uploadedFile['tmp_name'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => '???????????????? ??????????影?력????????⑹름??????뭽??',
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            echo json_encode(
                $this->excelService->importFromExcelFile((string) $uploadedFile['tmp_name']),
                JSON_UNESCAPED_UNICODE
            );
        } catch (\Throwable $e) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
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
            return $this->voucherModel->searchForPicker([
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
                'message' => 'List loaded.',
                'data' => array_slice($rows, 0, 50),
            ];
        });
    }

    public function apiEvidenceSearch(): void
    {
        $this->jsonResponse(function (): array {
            if (!$this->evidenceDataModel->tableExists()) {
                return [
                    'success' => true,
                    'message' => 'List loaded.',
                    'data' => [],
                ];
            }
            $query = trim((string) ($_GET['q'] ?? ''));
            $rawRows = $this->evidenceDataModel->searchForPicker($query, ['DATA', 'FUND', 'BOTH']);
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
                'message' => 'List loaded.',
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
                'message' => 'Transaction creation is not supported from vouchers. Create it on the transaction page and link it here.',
            ];
        });
    }

    public function apiSummarySearch(): void
    {
        $this->jsonResponse(function (): array {
            if (!$this->evidenceDataModel->tableExists()) {
                return [
                    'success' => true,
                    'items' => [],
                ];
            }

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
            $result = $this->service->save($payload);
            if (($result['success'] ?? false) && trim((string) ($payload['linked_evidence_id'] ?? '')) !== '') {
                $this->replaceVoucherEvidenceLink(
                    (string) ($result['voucher_id'] ?? $result['id'] ?? ''),
                    trim((string) $payload['linked_evidence_id']),
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
                'message' => (['success'] ?? false) ? 'Saved.' : (['message'] ?? 'Save failed.'),
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
            $voucher = $this->voucherModel->getById($id) ?: [];

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
            $voucher = $this->voucherModel->getById((string) ($result['id'] ?? '')) ?: [];

            return [
                'success' => true,
                'message' => '취소전표가 생성되었습니다.',
                'data' => array_merge($voucher, $result),
            ];
        });
    }

    public function apiLinkEvidence(): void
    {
        $this->jsonResponse(function (): array {
            if (!$this->evidenceDataModel->tableExists()) {
                return [
                    'success' => false,
                    'message' => '증빙 payload 저장소를 찾을 수 없습니다.',
                ];
            }

            $id = $this->requestVoucherId();
            $evidenceId = $this->requestValue('linked_evidence_id') ?: $this->requestValue('evidence_id');
            if ($evidenceId === '') {
                throw new \RuntimeException('?????怨뺤르????饔낅떽??影?곗몡???紐???????影?력?????鰲????轅붽틓?????');
            }

            $actor = ActorHelper::user();
            $this->pdo->beginTransaction();
            $this->replaceVoucherEvidenceLink($id, $evidenceId, $actor);
            $this->pdo->commit();

            $voucher = $this->voucherModel->getById($id) ?: [];
            $linkedEvidence = $this->voucherSeedSourceByVoucherId($id);

            return [
                'success' => true,
                'message' => 'Evidence link saved.',
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
            if (!$this->evidenceDataModel->tableExists()) {
                return [
                    'success' => false,
                    'message' => '증빙 payload 저장소를 찾을 수 없습니다.',
                ];
            }

            $id = $this->requestVoucherId();
            $evidenceId = $this->requestValue('linked_evidence_id') ?: $this->requestValue('evidence_id');
            $actor = ActorHelper::user();

            $this->pdo->beginTransaction();
            $this->unlinkVoucherEvidence($id, $evidenceId, $actor);
            $this->pdo->commit();

            $voucher = $this->voucherModel->getById($id) ?: [];

            return [
                'success' => true,
                'message' => 'Evidence link removed.',
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
                'message' => 'Rejected.',
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
                'data' => $this->voucherModel->getTrashList(),
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
            $ids = $this->voucherModel->getDeletedIds();

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

            $this->pdo->beginTransaction();
            $this->purgeVoucherById($id);
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => 'Purge completed.',
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
                    'message' => 'No voucher selected for purge.',
                ];
            }

            $this->pdo->beginTransaction();
            foreach ($ids as $id) {
                $this->purgeVoucherById((string) $id);
            }
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => 'Selected vouchers purged.',
            ];
        });
    }

    public function apiPurgeAll(): void
    {
        $this->jsonResponse(function (): array {
            $ids = $this->voucherModel->getDeletedIds();

            $this->pdo->beginTransaction();
            foreach ($ids as $id) {
                $this->purgeVoucherById((string) $id);
            }
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => 'All vouchers purged.',
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

    private function uploadedExcelFile(): ?array
    {
        return $_FILES['excel'] ?? $_FILES['file'] ?? null;
    }

    private function downloadSpreadsheet(Spreadsheet $spreadsheet, string $filename): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header(
            'Content-Disposition: attachment; filename="' . addcslashes($filename, "\\\"") . '"'
            . "; filename*=UTF-8''" . rawurlencode($filename)
        );
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
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
            throw new \RuntimeException('????熬곻퐢夷??ID????ル늉?? ?????諛몃마????꿔꺂??????');
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
        $operationType = strtoupper(trim((string) ($row['operation_type'] ?? '')));
        $importType = strtoupper(trim((string) ($row['import_type'] ?? '')));

        if ($operationType !== '') {
            return match ($operationType) {
                'GENERAL'          => 'General',
                'PAYROLL'          => 'Payroll',
                'DAILY_WORKER'     => 'Daily Worker',
                'BUSINESS_INCOME'  => 'Business Income',
                'FIXED_ASSET'      => 'Fixed Asset',
                'LOAN'             => 'Loan',
                default            => $operationType,
            };
        }

        return match ($importType) {
            'BANK_TRANSACTION'                  => 'Bank Transaction',
            'TAX_INVOICE'                       => 'Tax Invoice',
            'CASH_RECEIPT'                      => 'Cash Receipt',
            'CARD_APPROVAL',
            'CARD_HOMETAX',
            'CARD_STATEMENT'                    => 'Card',
            default                             => 'Transaction',
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

        return 'Transaction';
    }

    private function linkedVoucherInfoForTransaction(string $transactionId): ?array
    {
        return $this->transactionLinkModel->findLinkedVoucherInfoByTransactionId($transactionId);
    }

    private function linkedVoucherInfoForEvidence(string $evidenceId): ?array
    {
        return $this->evidenceLinkModel->findLinkedVoucherInfoByEvidenceId($evidenceId);
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
            'BANK_TRANSACTION' => 'Bank Transaction',
            'TAX_INVOICE' => 'Tax Invoice',
            'CASH_RECEIPT' => 'Cash Receipt',
            'CARD_APPROVAL', 'CARD_HOMETAX', 'CARD_STATEMENT', 'CARD_COMPANY' => 'Card',
            default => $sourceType !== '' ? $sourceType : 'Transaction source',
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
        ]) ?: trim((string) ($row['format_name'] ?? $row['source_type'] ?? "\u{C99D}\u{BE59}"));
    }

    private function evidenceDisplayAmount(array $row, array $payload): float
    {
        foreach (
            [
                $row['total_amount'] ?? null,
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
        return $this->evidenceDataModel->getTransactionImportType($transactionId);
    }

    private function transactionSeedSource(string $transactionId): ?array
    {
        return $this->evidenceDataModel->getTransactionSeedSource($transactionId);
    }

    private function voucherSeedSourceByVoucherId(string $voucherId): ?array
    {
        $rows = $this->voucherSeedSourcesByVoucherId($voucherId);

        return $rows[0] ?? null;
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

    private function voucherSeedSourcesByVoucherId(string $voucherId): array
    {
        return array_map(
            fn(array $row): array => $this->normalizeEvidenceSearchRow($row),
            $this->evidenceDataModel->getVoucherSeedSourcesByVoucherId($voucherId)
        );
    }

    private function voucherSeedSource(string $evidenceId): ?array
    {
        return $this->evidenceDataModel->getSeedSourceById($evidenceId);
    }

    private function replaceVoucherEvidenceLink(string $voucherId, string $evidenceId, string $actor): void
    {
        if ($voucherId === '' || $evidenceId === '') {
            throw new \RuntimeException('전표 ID와 증빙 ID를 모두 입력해 주세요.');
        }
        if (!$this->evidenceDataModel->tableExists() || !$this->evidenceLinkModel->tableExists()) {
            throw new \RuntimeException('증빙 데이터 또는 증빙 링크 저장소를 찾을 수 없습니다.');
        }

        if (!$this->voucherModel->getById($voucherId)) {
            throw new \RuntimeException('전표를 찾을 수 없습니다.');
        }
        if (!$this->voucherSeedSource($evidenceId)) {
            throw new \RuntimeException('증빙을 찾을 수 없습니다.');
        }
        if (!$this->evidenceDataModel->isLinkableByPolicy($evidenceId, ['DATA', 'FUND', 'BOTH'])) {
            throw new \RuntimeException('증빙정책에서 전표 연결이 허용되지 않은 증빙입니다.');
        }

        $previousEvidenceIds = $this->evidenceIdsLinkedToVoucher($voucherId, $evidenceId);
        if ($previousEvidenceIds !== []) {
            $this->softDeleteVoucherEvidenceLinks($voucherId, $previousEvidenceIds, $actor);
        }

        $this->linkVoucherToEvidence($evidenceId, $voucherId);
        $this->updateEvidenceVoucherStatus($evidenceId, 'CREATED', $actor);

        foreach ($previousEvidenceIds as $previousEvidenceId) {
            if (!$this->activeVoucherExistsForEvidence($previousEvidenceId, $voucherId)) {
                $this->updateEvidenceVoucherStatus($previousEvidenceId, 'READY', $actor);
            }
        }
    }

    private function unlinkVoucherEvidence(string $voucherId, string $evidenceId, string $actor): void
    {
        if ($voucherId === '' || !$this->evidenceLinkModel->tableExists()) {
            return;
        }

        $evidenceIds = $evidenceId !== ''
            ? [$evidenceId]
            : $this->evidenceIdsLinkedToVoucher($voucherId);

        if ($evidenceIds === []) {
            return;
        }

        $this->softDeleteVoucherEvidenceLinks($voucherId, $evidenceIds, $actor);

        foreach ($evidenceIds as $linkedEvidenceId) {
            if (!$this->activeVoucherExistsForEvidence($linkedEvidenceId, $voucherId)) {
                $this->updateEvidenceVoucherStatus($linkedEvidenceId, 'READY', $actor);
            }
        }
    }

    private function evidenceIdsLinkedToVoucher(string $voucherId, string $exceptEvidenceId = ''): array
    {
        return $this->evidenceLinkModel->getEvidenceIdsByVoucherId($voucherId, $exceptEvidenceId);
    }

    private function softDeleteVoucherEvidenceLinks(string $voucherId, array $evidenceIds, string $actor): void
    {
        $this->evidenceLinkModel->softDeleteVoucherLinks($voucherId, $evidenceIds);
    }

    private function linkVoucherToEvidence(string $evidenceId, string $voucherId): void
    {
        $this->evidenceLinkModel->linkVoucher($evidenceId, $voucherId);
    }

    private function updateEvidenceVoucherStatus(string $evidenceId, string $voucherStatus, string $actor, ?string $errorMessage = null): void
    {
        $this->evidenceDataModel->updateVoucherStatus($evidenceId, $voucherStatus, $actor, $errorMessage);
    }

    private function syncLinkedEvidenceVoucherPayload(string $voucherId, string $evidenceId, string $actor): void
    {
        if (
            $voucherId === ''
            || $evidenceId === ''
            || !$this->evidenceDataModel->tableExists()
            || !$this->evidenceDataModel->columnExists('mapped_payload_json')
        ) {
            return;
        }

        $voucher = $this->voucherModel->getById($voucherId);
        if (!$voucher) {
            return;
        }

        $evidence = $this->evidenceDataModel->getPayloadContext($evidenceId);
        if (!$evidence) {
            error_log("[VoucherController] Missing ledger_data_evidences row for evidence_id={$evidenceId}");
            return;
        }

        $mapped = $this->decodeJsonObject($evidence['mapped_payload_json'] ?? null);
        $lines = $this->voucherLineRefService->hydrateVoucherLines(
            $this->voucherLineModel->getByVoucherId($voucherId)
        );

        $mapped['voucher_date'] = (string) ($voucher['voucher_date'] ?? '');
        $mapped['voucher_summary_text'] = (string) ($voucher['summary'] ?? '');
        $mapped['summary'] = (string) ($voucher['summary'] ?? '');
        $mapped['summary_text'] = (string) ($voucher['summary'] ?? '');
        $mapped['note'] = (string) ($voucher['note'] ?? '');
        $mapped['memo'] = (string) ($voucher['memo'] ?? '');
        $mapped['_voucher_lines'] = array_map(function (array $line): array {
            $refs = is_array($line['refs'] ?? null) ? array_values($line['refs']) : [];
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

        $mappedPayloadJson = json_encode($mapped, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $updated = $this->evidenceDataModel->updateMappedPayload(
            $evidenceId,
            (string) ($evidence['source_type'] ?? ''),
            $mappedPayloadJson,
            $actor
        );
        if (!$updated) {
            error_log("[VoucherController] Failed to update ledger_data_evidences row for evidence_id={$evidenceId}");
            return;
        }
        $this->evidenceDataModel->updateVoucherStatus($evidenceId, 'CREATED', $actor, null);
    }

    private function restoreVoucherById(string $id): void
    {
        if ($id === '') {
            return;
        }

        // Links are intentionally not auto-restored here. Reconnect vouchers explicitly from the transaction or voucher UI.
        $this->service->restoreVoucher($id);
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
            throw new \RuntimeException("\u{C804}\u{D45C}\u{B97C} \u{C601}\u{AD6C}\u{C0AD}\u{C81C}\u{D558}\u{C9C0} \u{BABB}\u{D588}\u{C2B5}\u{B2C8}\u{B2E4}.");
        }
    }

    private function purgeVoucherChildrenById(string $voucherId): void
    {
        if ($voucherId === '') {
            return;
        }

        $this->voucherLineModel->purgeByVoucherId($voucherId);
    }

    private function purgeVoucherEvidenceLinksById(string $voucherId): void
    {
        $this->evidenceLinkModel->purgeByVoucherId($voucherId);
    }

    private function purgeTransactionLinksByVoucherId(string $voucherId): void
    {
        $this->transactionLinkModel->purgeByVoucherId($voucherId);
    }

    private function voucherRowForPurge(string $voucherId): array
    {
        return $voucherId !== '' ? ($this->voucherModel->getById($voucherId) ?: []) : [];
    }

    private function evidenceIdsAffectedByVoucherPurge(array $voucher, array $transactionIds): array
    {
        $ids = [];
        $voucherId = trim((string) ($voucher['id'] ?? ''));
        if ($voucherId !== '') {
            $ids = array_merge($ids, $this->evidenceLinkModel->getEvidenceIdsByVoucherId($voucherId));
        }
        $ids = array_merge($ids, $this->evidenceDataModel->findIdsByTransactionIds($transactionIds));

        return array_values(array_filter(array_unique($ids)));
    }

    private function resetEvidenceVoucherStatusAfterPurge(array $evidenceIds, string $actor, string $purgedVoucherId): void
    {
        $evidenceIds = array_values(array_filter(array_unique(array_map('strval', $evidenceIds))));
        if ($evidenceIds === []) {
            return;
        }

        foreach ($evidenceIds as $evidenceId) {
            if ($this->activeVoucherExistsForEvidence($evidenceId, $purgedVoucherId)) {
                continue;
            }

            $this->evidenceDataModel->updateVoucherStatus($evidenceId, 'READY', $actor, null);
        }
    }

    private function processingItemIdsAffectedByVoucherPurge(string $voucherId): array
    {
        if ($voucherId === '') {
            return [];
        }

        $ids = [];
        $ids = array_merge($ids, $this->voucherLineModel->getProcessingItemIdsByVoucherId($voucherId));
        $ids = array_merge($ids, $this->evidenceLinkModel->getProcessingItemIdsByVoucherId($voucherId));

        return array_values(array_filter(array_unique($ids)));
    }

    private function resetProcessingItemVoucherStatusAfterPurge(array $processingItemIds, string $actor, string $purgedVoucherId): void
    {
        $processingItemIds = array_values(array_filter(array_unique(array_map('strval', $processingItemIds))));
        if ($processingItemIds === []) {
            return;
        }

        foreach ($processingItemIds as $processingItemId) {
            if ($this->activeVoucherExistsForProcessingItem($processingItemId, $purgedVoucherId)) {
                continue;
            }

            $this->processingItemModel->update($processingItemId, [
                'voucher_status' => 'READY',
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $actor,
            ]);
        }
    }

    private function activeVoucherExistsForEvidence(string $evidenceId, string $excludeVoucherId = ''): bool
    {
        return $this->evidenceLinkModel->activeVoucherExistsForEvidence($evidenceId, $excludeVoucherId);
    }

    private function activeVoucherExistsForProcessingItem(string $processingItemId, string $excludeVoucherId = ''): bool
    {
        return $this->voucherLineModel->hasActiveVoucherForProcessingItem($processingItemId, $excludeVoucherId);
    }

}
