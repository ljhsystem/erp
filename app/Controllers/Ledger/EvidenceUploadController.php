<?php

namespace App\Controllers\Ledger;

use App\Controllers\Ledger\Concerns\ImportControllerBusinessInfoTrait;
use App\Controllers\Ledger\Concerns\ImportControllerUploadTrait;
use App\Controllers\Ledger\Concerns\ImportControllerUtilityTrait;
use App\Services\Ledger\EvidenceBankHelperService;
use App\Services\Ledger\EvidenceBatchSaveService;
use App\Services\Ledger\EvidenceBusinessRefService;
use App\Services\Ledger\EvidenceFormatMappingService;
use App\Services\Ledger\EvidencePayloadHelperService;
use App\Services\Ledger\EvidencePayloadNormalizeService;
use App\Services\Ledger\EvidenceReferenceResolverService;
use App\Services\Ledger\EvidenceRuleEngineService;
use App\Services\Ledger\EvidenceSortHelperService;
use App\Services\Ledger\EvidenceStatusHelperService;
use App\Services\Ledger\EvidenceTemplateDropdownService;
use App\Services\Ledger\EvidenceTransactionContextService;
use App\Services\Ledger\EvidenceTypePolicyService;
use App\Services\Ledger\EvidenceUploadParserService;
use App\Services\Ledger\EvidenceUploadService;
use App\Services\Ledger\EvidenceUploadValidationService;
use App\Services\Ledger\JournalLearningService;
use App\Services\Ledger\SystemFieldService;
use App\Services\Ledger\VoucherCreateService;
use App\Services\Ledger\VoucherLearningService;
use App\Services\Ledger\VoucherPolicyService;
use App\Services\Ledger\VoucherService;
use Core\DbPdo;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PDO;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class EvidenceUploadController
{
    use ImportControllerUtilityTrait;
    use ImportControllerBusinessInfoTrait;
    use ImportControllerUploadTrait;

    private const EVIDENCE_UPLOAD_TYPES = [
        'TAX_INVOICE',
        'CASH_RECEIPT',
        'CARD',
        'CARD_HOMETAX',
        'CARD_STATEMENT',
        'CARD_APPROVAL',
        'BANK_TRANSACTION',
        'CASH_RECEIPT_PURCHASE',
        'CASH_RECEIPT_SALES',
    ];

    private const BUSINESS_DATA_TYPES = [
        'BUSINESS_DATA',
        'SHOPPING_ORDER',
        'PAYROLL',
        'PAYROLL_WITHHOLDING',
        'BUSINESS_INCOME',
        'EMPLOYEE_EXPENSE',
        'IMPORT_INVOICE',
        'CONSTRUCTION',
    ];

    private const DATA_TYPES = self::EVIDENCE_UPLOAD_TYPES;
    private const BANK_VOUCHER_LINE_FIELDS = [
        'header_row_no',
        'line_no',
        'line_row_type',
        'account_id',
        'debit',
        'credit',
        'line_summary',
        'line_ref_type',
        'line_ref_id',
    ];
    private const UPLOAD_STORE_CHUNK_SIZE = 500;
    private const LEGACY_DATA_TYPE_MAP = [
        'DATA' => 'TAX_INVOICE',
        'TAX' => 'TAX_INVOICE',
        'CARD' => 'CARD_STATEMENT',
        'CARD_PURCHASE' => 'CARD_STATEMENT',
        'CARD_SALE' => 'CARD_STATEMENT',
        'CASH_RECEIPT_PURCHAS' => 'CASH_RECEIPT_PURCHASE',
        'CASH_RECEIPT_BUY' => 'CASH_RECEIPT_PURCHASE',
        'CASH_RECEIPT_SALE' => 'CASH_RECEIPT_SALES',
        'CASH_RECEIPT_SELL' => 'CASH_RECEIPT_SALES',
        'BANK' => 'BANK_TRANSACTION',
        'SHOPPING' => 'SHOPPING_ORDER',
        'TRADE_IMPORT' => 'IMPORT_INVOICE',
        'IMPORT' => 'IMPORT_INVOICE',
    ];

    private PDO $pdo;
    private ?EvidenceUploadService $evidenceUploadService = null;
    private ?EvidenceUploadParserService $evidenceUploadParserService = null;
    private ?EvidenceUploadValidationService $evidenceUploadValidationService = null;
    private ?EvidenceBatchSaveService $evidenceBatchSaveService = null;
    private ?EvidenceSortHelperService $evidenceSortHelperService = null;
    private ?EvidenceBankHelperService $evidenceBankHelperService = null;
    private ?EvidencePayloadHelperService $evidencePayloadHelperService = null;
    private ?EvidenceTypePolicyService $evidenceTypePolicyService = null;
    private ?EvidencePayloadNormalizeService $evidencePayloadNormalizeService = null;
    private ?EvidenceBusinessRefService $evidenceBusinessRefService = null;
    private ?EvidenceStatusHelperService $evidenceStatusHelperService = null;
    private ?EvidenceTransactionContextService $evidenceTransactionContextService = null;
    private ?EvidenceReferenceResolverService $evidenceReferenceResolverService = null;
    private ?EvidenceFormatMappingService $evidenceFormatMappingService = null;
    private ?EvidenceTemplateDropdownService $evidenceTemplateDropdownService = null;
    private ?SystemFieldService $systemFieldService = null;
    private ?VoucherPolicyService $voucherPolicyService = null;
    private ?VoucherCreateService $voucherCreateService = null;
    private ?VoucherService $voucherService = null;
    private ?JournalLearningService $journalLearningService = null;
    private ?VoucherLearningService $voucherLearningService = null;
    private ?EvidenceRuleEngineService $evidenceRuleEngineService = null;
    private ?array $ownCompanyProfile = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbPdo::conn();
    }

    public function apiUpload(): void
    {
        $this->handleSeedUpload();
    }

    public function apiUploadCancel(): void
    {
        $this->handleSeedUploadCancel();
    }

    public function apiBatchList(): void
    {
        $this->handleUploadBatches();
    }

    public function apiBatchRows(): void
    {
        $this->handleUploadBatchRows();
    }

    protected function handleSeedUpload(): void
    {
        $this->evidenceUploadService()->prepareLargeUploadRuntime();
        \Core\Session::write();
        $cancelToken = !empty($_FILES['file']) ? $this->evidenceUploadService()->uploadCancelTokenFromPayload($_POST) : '';
        $startedAt = microtime(true);
        $this->evidenceUploadService()->uploadTrace(
            'start',
            $this->evidenceUploadService()->buildUploadStartTraceContext($cancelToken, $_FILES['file'] ?? [], !empty($_FILES['file']) ? 'file' : 'preview')
        );

        if (!empty($_FILES['file'])) {
            $formatId = trim((string) ($_POST['format_id'] ?? ''));
            $format = $this->evidenceFormatMappingService()->formatWithColumns($formatId);
            if (!$format) {
                $this->json(['success' => false, 'message' => '??????????????????????????????????????????????????????????????????????????????????????????'], 400);
                return;
            }

            try {
                $dataType = self::normalizeDataType((string) ($format['data_type'] ?? 'ETC'));
                if (!$this->isAllowedDataType($dataType)) {
                    throw new \RuntimeException('??????????????????????????????? ???????????????????????????????????????椰??????????????????????????????????????????????????????????????????????????????????????????????????');
                }
                $prepared = $this->evidenceUploadService()->prepareSeedUploadFilePath(
                    $format,
                    $_FILES['file'],
                    $dataType,
                    $cancelToken,
                    $startedAt,
                    $_POST
                );
                if (is_array($prepared['confirmation_response'] ?? null)) {
                    $this->json($prepared['confirmation_response']);
                    return;
                }

                $checks = is_array($prepared['checks'] ?? null) ? $prepared['checks'] : [];
                $rows = is_array($prepared['rows'] ?? null) ? $prepared['rows'] : [];
                $stageStartedAt = microtime(true);
                $result = $this->storeUploadBatch($format, $_FILES['file'], $rows, $cancelToken);
                $this->evidenceUploadService()->uploadTrace(
                    'stored_rows',
                    $this->evidenceUploadService()->buildStoredRowsTraceContext($cancelToken, count($rows), $stageStartedAt, $startedAt)
                );
                $this->evidenceUploadService()->clearUploadCancelToken($cancelToken);
                $this->json(['success' => true, 'data' => $result, 'checks' => $checks, 'message' => '???????? ????????????????????獄쏅챶留덌┼??????????????筌롈살젔??????????????????????븐뼐????????쑩?젆???????ㅻ쑋??????????????????????????????????????????????????????????????']);
            } catch (\Throwable $e) {
                $this->evidenceUploadService()->uploadTrace('failed', $this->evidenceUploadService()->buildFailedUploadTraceContext($cancelToken, $startedAt, $e));
                $this->json(['success' => false, 'message' => $e->getMessage()], 400);
            }
            return;
        }

        $this->evidenceUploadService()->uploadTrace('preview_payload_reading', $this->evidenceUploadService()->buildPreviewPayloadReadTraceContext($startedAt));
        $payload = $this->requestPayload();

        try {
            $previewState = $this->evidenceUploadService()->preparePreviewConfirmation($payload, $startedAt);
            $cancelToken = $previewState['cancel_token'];
            $token = $previewState['preview_token'];
            $preview = $previewState['preview'];
            $requiredMissing = $previewState['required_missing'];
            $confirmationResponse = $this->evidenceUploadService()->buildPreviewRequiredMissingConfirmationResponse($payload, $requiredMissing);
            if (is_array($confirmationResponse)) {
                $this->json($confirmationResponse);
                return;
            }

            $totalRows = (int) ($previewState['total_rows'] ?? count($preview['rows']));
            $isChunked = !empty($payload['chunked_upload']) || isset($payload['chunk_offset']) || isset($payload['chunk_size']);
            if ($isChunked) {
                $offset = max(0, (int) ($payload['chunk_offset'] ?? 0));
                $chunkSize = max(1, min(50, (int) ($payload['chunk_size'] ?? 5)));
                if ($offset >= $totalRows) {
                    $this->evidenceUploadService()->clearPreviewSession($token);
                    $this->evidenceUploadService()->clearUploadCancelToken($cancelToken);
                    $this->json([
                        'success' => true,
                        'data' => $this->evidenceUploadService()->buildCompletedChunkUploadResult($totalRows, $offset),
                        'message' => 'Seed upload completed.',
                    ]);
                    return;
                }

                $chunkRows = array_slice($preview['rows'], $offset, $chunkSize);
                $stageStartedAt = microtime(true);
                $result = $this->storeUploadBatch($preview['format'], $preview['file'], $chunkRows, $cancelToken);
                $result = $this->evidenceUploadService()->buildChunkUploadProgressResult($result, $totalRows, $offset, count($chunkRows));
                $nextOffset = (int) ($result['next_offset'] ?? $totalRows);
                $done = !empty($result['done']);
                $this->evidenceUploadService()->uploadTrace(
                    'stored_preview_chunk',
                    $this->evidenceUploadService()->buildStoredPreviewChunkTraceContext($cancelToken, $offset, $nextOffset, $totalRows, $stageStartedAt, $startedAt)
                );
                if ($done) {
                    $this->evidenceUploadService()->clearPreviewSession($token);
                    $this->evidenceUploadService()->clearUploadCancelToken($cancelToken);
                }
                $this->json(['success' => true, 'data' => $result, 'message' => $done ? 'Seed upload completed.' : 'Upload chunk saved.']);
                return;
            }

            $stageStartedAt = microtime(true);
            $result = $this->storeUploadBatch($preview['format'], $preview['file'], $preview['rows'], $cancelToken);
            $this->evidenceUploadService()->uploadTrace(
                'stored_preview_rows',
                $this->evidenceUploadService()->buildStoredPreviewRowsTraceContext($cancelToken, count($preview['rows']), $stageStartedAt, $startedAt)
            );
            $this->evidenceUploadService()->clearPreviewSession($token);
            $this->evidenceUploadService()->clearUploadCancelToken($cancelToken);
            $this->json(['success' => true, 'data' => $result, 'message' => 'Seed ???????? ????????????????????獄쏅챶留덌┼??????????????筌롈살젔??????????????????????븐뼐????????쑩?젆???????ㅻ쑋??????????????????????????????????????????????????????????????']);
        } catch (\Throwable $e) {
            $this->evidenceUploadService()->uploadTrace('failed_preview', $this->evidenceUploadService()->buildFailedPreviewTraceContext($cancelToken, $startedAt, $e));
            $this->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    protected function handleSeedUploadCancel(): void
    {
        \Core\Session::write();
        $payload = $this->requestPayload();
        $result = $this->evidenceUploadService()->cancel($payload);

        $this->json($result, (int) ($result['status'] ?? 200));
    }

    protected function handleUploadBatches(): void
    {
        $this->json(['success' => true, 'data' => $this->evidenceUploadService()->batches()]);
    }

    protected function handleUploadBatchRows(): void
    {
        $batchId = trim((string) ($_GET['batch_id'] ?? ''));
        $rows = $this->evidenceUploadService()->batchRows($batchId);

        $this->json(['success' => true, 'data' => ['batch' => null, 'rows' => $rows]]);
    }

    private function evidenceUploadService(): EvidenceUploadService
    {
        if ($this->evidenceUploadService === null) {
            $this->evidenceUploadService = new EvidenceUploadService(
                $this->pdo,
                fn(string $type): string => self::normalizeDataType($type),
                fn(array $payload): array => $this->evidenceBankHelperService()->normalizeBankTransactionPayload($payload),
                fn(array $payload): array => $this->evidencePayloadNormalizeService()->normalizeEvidenceMappedPayloadForResponse($payload),
                fn(array $row): array => $this->evidencePayloadNormalizeService()->mappedPayloadForStorage($row),
                fn(array $payload, string $dataType): ?string => $this->evidenceUploadService()->seedSourceKey($payload, $dataType),
                fn(array $payload): string => $this->evidencePayloadHelperService()->jsonEncodeForStorage($payload),
                fn(string $alias = ''): string => $this->evidenceStatusHelperService()->evidenceTransactionIdSelect($alias),
                fn(mixed $value): ?float => $this->amountOrNull($value),
                fn(mixed $value): ?string => $this->dateValueOrNull($value),
                fn(array $columns): bool => $this->evidenceUploadParserService()->hasBankVoucherLineColumns($columns),
                fn(array $columns, bool $ensureLineRowType = false): array => $this->evidenceTemplateDropdownService()->splitBankFormatColumns($columns, $ensureLineRowType),
                fn(Spreadsheet $spreadsheet): bool => $this->evidenceUploadParserService()->bankLineSheetHasRowTypeColumn($spreadsheet),
                fn(array $headerRow): array => $this->evidenceUploadParserService()->uploadHeaderColumnsByName($headerRow),
                fn(array $column, array $headerRow, array $headerColumnsByName, ?array &$usedHeaderColumns = null): ?string => $this->evidenceUploadParserService()->uploadSheetColumnForFormatColumn($column, $headerRow, $headerColumnsByName, $usedHeaderColumns),
                fn(array $column): bool => self::isRequiredFormatColumn($column),
                [
                    'amountOrNull' => fn(mixed $value): ?float => $this->amountOrNull($value),
                    'annotateSeedComparison' => fn(array $rows, string $dataType): array => $this->evidenceUploadService()->annotateSeedComparison($rows, $dataType),
                    'assertNoUploadValidationErrors' => function (array $rows): void {
                        $this->evidenceUploadValidationService()->assertNoUploadValidationErrors($rows);
                    },
                    'businessRefIdForStorage' => fn(string $refType, array $payload): ?string => $this->evidenceBusinessRefService()->businessRefIdForStorage($refType, $payload),
                    'dateTimeValue' => fn(mixed $value): ?string => $this->dateTimeValue($value),
                    'dateValue' => fn(mixed $value): string => $this->dateValue($value),
                    'enrichUploadRows' => fn(array $rows, string $dataType): array => $this->evidenceUploadValidationService()->enrichUploadRows($rows, $dataType),
                    'isManualTaxInvoiceDataType' => fn(string $dataType): bool => $this->evidenceTypePolicyService()->isManualTaxInvoiceDataType($dataType),
                    'normalizeBusinessNumber' => fn(string $value): string => self::normalizeBusinessNumber($value),
                    'number' => fn(mixed $value): float => $this->number($value),
                    'parseUploadedRows' => fn(array $file, array $columns): array => $this->evidenceUploadParserService()->parseUploadedRows($file, $columns),
                    'tableColumnExists' => fn(string $tableName, string $columnName): bool => $this->tableColumnExists($tableName, $columnName),
                    'validatePreviewRows' => fn(array $rows, array $columns, string $dataType): array => $this->evidenceUploadValidationService()->validatePreviewRows($rows, $columns, $dataType),
                ]
            );
        }

        return $this->evidenceUploadService;
    }

    private function evidenceUploadParserService(): EvidenceUploadParserService
    {
        if ($this->evidenceUploadParserService === null) {
            $this->evidenceUploadParserService = new EvidenceUploadParserService(
                fn(array $columns, bool $ensureLineRowType = false): array => $this->evidenceTemplateDropdownService()->splitBankFormatColumns($columns, $ensureLineRowType),
                fn(mixed $value): string => $this->cellValue($value)
            );
        }

        return $this->evidenceUploadParserService;
    }

    private function evidencePayloadHelperService(): EvidencePayloadHelperService
    {
        if ($this->evidencePayloadHelperService === null) {
            $this->evidencePayloadHelperService = new EvidencePayloadHelperService([
                'amountOrNull' => fn(mixed $value): ?float => $this->amountOrNull($value),
                'dateValue' => fn(mixed $value): string => $this->dateValue($value),
                'isEmptySelectionLabel' => fn(string $value): bool => $this->evidenceBusinessRefService()->isEmptySelectionLabel($value),
                'normalizeDataType' => fn(string $type): string => self::normalizeDataType($type),
            ]);
        }

        return $this->evidencePayloadHelperService;
    }

    private function evidenceUploadValidationService(): EvidenceUploadValidationService
    {
        if ($this->evidenceUploadValidationService === null) {
            $this->evidenceUploadValidationService = new EvidenceUploadValidationService([
                'amountOrNull' => fn(mixed $value): ?float => $this->amountOrNull($value),
                'resolveUploadTransactionContext' => fn(array $row, string $dataType): array => $this->evidenceTransactionContextService()->resolveUploadTransactionContext($row, $dataType),
                'normalizeDataType' => fn(string $dataType): string => self::normalizeDataType($dataType),
                'requiredFormatMissingMessages' => fn(array $payload, array $columns): array => $this->evidencePayloadNormalizeService()->requiredFormatMissingMessages($payload, $columns),
                'fieldLabel' => fn(string $field): string => self::fieldLabel($field),
                'normalizeBusinessNumber' => fn(string $value): string => $this->normalizeBusinessNumber($value),
                'cleanCompanyName' => fn(string $value): string => $this->cleanCompanyName($value),
                'clientExistsByBusinessNumber' => fn(string $businessNumber): bool => $this->clientExistsByBusinessNumber($businessNumber),
                'findClientId' => fn(string $companyName): ?string => $this->evidenceReferenceResolverService()->findClientId($companyName),
                'payloadScalarForStorage' => fn(mixed $value): mixed => $this->evidencePayloadHelperService()->payloadScalarForStorage($value),
            ]);
        }

        return $this->evidenceUploadValidationService;
    }

    private function evidenceSortHelperService(): EvidenceSortHelperService
    {
        if ($this->evidenceSortHelperService === null) {
            $this->evidenceSortHelperService = new EvidenceSortHelperService();
        }

        return $this->evidenceSortHelperService;
    }

    private function evidenceBatchSaveService(): EvidenceBatchSaveService
    {
        if ($this->evidenceBatchSaveService === null) {
            $this->evidenceBatchSaveService = new EvidenceBatchSaveService($this->pdo, [
                'mappedPayloadForStorage' => fn(array $row): array => $this->evidencePayloadNormalizeService()->mappedPayloadForStorage($row),
                'normalizeBankTransactionPayload' => fn(array $payload): array => $this->evidenceBankHelperService()->normalizeBankTransactionPayload($payload),
                'uploadVoucherStatus' => fn(string $dataType, array $payload, string $processStatus): string => $this->evidenceStatusHelperService()->uploadVoucherStatus($dataType, $payload, $processStatus),
                'bankVoucherValidationMessage' => fn(array $payload): ?string => $this->evidenceBankHelperService()->bankVoucherValidationMessage($payload),
                'seedSourceKey' => fn(array $payload, string $dataType): ?string => $this->evidenceUploadService()->seedSourceKey($payload, $dataType),
                'jsonEncodeForStorage' => fn(array $payload): string => $this->evidencePayloadHelperService()->jsonEncodeForStorage($payload),
                'findExistingSeedRow' => fn(string $dataType, string $sourceKey): ?array => $this->evidenceUploadService()->findExistingSeedRow($dataType, $sourceKey),
                'usesFingerprintSourceKey' => fn(string $dataType): bool => $this->evidenceUploadService()->usesFingerprintSourceKey($dataType),
                'findExistingSeedRowByFingerprint' => fn(string $dataType, array $payload): ?array => $this->evidenceUploadService()->findExistingSeedRowByFingerprint($dataType, $payload),
                'isUploadProtectedExistingSeed' => fn(array $existingSeed): bool => $this->evidenceUploadService()->isUploadProtectedExistingSeed($existingSeed),
                'existingSeedHasCreatedTransaction' => fn(array $existingSeed): bool => $this->evidenceUploadService()->existingSeedHasCreatedTransaction($existingSeed),
                'existingSeedHasCreatedVoucher' => fn(array $existingSeed): bool => $this->evidenceUploadService()->existingSeedHasCreatedVoucher($existingSeed),
                'dateValue' => fn(mixed $value): string => $this->dateValue($value),
                'businessRefIdForStorage' => fn(string $refType, array $payload): ?string => $this->evidenceBusinessRefService()->businessRefIdForStorage($refType, $payload),
                'businessRefNameForStorage' => fn(string $refType, array $payload): ?string => $this->evidenceBusinessRefService()->businessRefNameForStorage($refType, $payload),
                'number' => fn(mixed $value): float => $this->number($value),
                'evidenceTotalAmountForStorage' => fn(array $payload, string $dataType): float => $this->evidencePayloadHelperService()->evidenceTotalAmountForStorage($payload, $dataType),
            ]);
        }

        return $this->evidenceBatchSaveService;
    }

    private function evidenceFormatMappingService(): EvidenceFormatMappingService
    {
        if ($this->evidenceFormatMappingService === null) {
            $this->evidenceFormatMappingService = new EvidenceFormatMappingService([
                'normalizeDataType' => fn(string $type): string => self::normalizeDataType($type),
                'systemFieldOptionsByValue' => fn(string $dataType): array => $this->evidenceTemplateDropdownService()->systemFieldOptionsByValue($dataType),
            ]);
        }

        return $this->evidenceFormatMappingService;
    }

    private function evidenceTemplateDropdownService(): EvidenceTemplateDropdownService
    {
        if ($this->evidenceTemplateDropdownService === null) {
            $this->evidenceTemplateDropdownService = new EvidenceTemplateDropdownService(
                $this->pdo,
                self::BANK_VOUCHER_LINE_FIELDS,
                [
                    'fieldOptions' => fn(string $dataType): array => $this->systemFieldService()->fieldOptions($dataType),
                    'isManualTaxInvoiceDataType' => fn(string $dataType): bool => $this->evidenceTypePolicyService()->isManualTaxInvoiceDataType($dataType),
                    'looksLikeBankTemplateHeaders' => fn(array $headers): bool => false,
                    'normalizeDataType' => fn(string $type): string => self::normalizeDataType($type),
                    'normalizeVoucherRefType' => fn(string $refType): string => $this->voucherPolicyService()->normalizeVoucherRefType($refType),
                    'tableColumnExists' => fn(string $tableName, string $columnName): bool => $this->tableColumnExists($tableName, $columnName),
                    'tableExists' => fn(string $tableName): bool => $this->tableExists($tableName),
                    'uniqueSheetTitle' => fn(Spreadsheet $spreadsheet, string $baseTitle): string => $baseTitle,
                ]
            );
        }

        return $this->evidenceTemplateDropdownService;
    }

    private function evidenceTypePolicyService(): EvidenceTypePolicyService
    {
        if ($this->evidenceTypePolicyService === null) {
            $this->evidenceTypePolicyService = new EvidenceTypePolicyService(
                fn(string $type): string => self::normalizeDataType($type),
                self::LEGACY_DATA_TYPE_MAP,
                $this->pdo,
                [
                    'amountOrNull' => fn(mixed $value): ?float => $this->amountOrNull($value),
                ]
            );
        }

        return $this->evidenceTypePolicyService;
    }

    private function evidencePayloadNormalizeService(): EvidencePayloadNormalizeService
    {
        if ($this->evidencePayloadNormalizeService === null) {
            $this->evidencePayloadNormalizeService = new EvidencePayloadNormalizeService(
                fn(array $payload): array => $this->evidenceBusinessRefService()->normalizeBusinessRefPayload($payload),
                fn(mixed $value): ?string => $this->dateValueOrNull($value),
                fn(mixed $value): ?string => $this->dateTimeValue($value),
                fn(string $value): bool => $this->isUuid($value),
                fn(string $value): bool => $this->evidenceBankHelperService()->looksLikeBankAccountNumber($value),
                fn(string $value): bool => $this->evidenceBusinessRefService()->isEmptySelectionLabel($value),
                fn(array $column): bool => self::isRequiredFormatColumn($column),
                fn(string $field): string => self::fieldLabel($field)
            );
        }

        return $this->evidencePayloadNormalizeService;
    }

    private function evidenceReferenceResolverService(): EvidenceReferenceResolverService
    {
        if ($this->evidenceReferenceResolverService === null) {
            $this->evidenceReferenceResolverService = new EvidenceReferenceResolverService(
                $this->pdo,
                fn(string $tableName): bool => $this->tableExists($tableName),
                fn(string $tableName, string $columnName): bool => $this->tableColumnExists($tableName, $columnName),
                fn(string $value): bool => $this->isUuid($value),
                fn(string $value): string => $this->voucherPolicyService()->normalizeVoucherRefType($value),
                fn(mixed $value): string => self::normalizeBusinessNumber($value)
            );
        }

        return $this->evidenceReferenceResolverService;
    }

    private function evidenceBusinessRefService(): EvidenceBusinessRefService
    {
        if ($this->evidenceBusinessRefService === null) {
            $this->evidenceBusinessRefService = new EvidenceBusinessRefService([
                'normalizeVoucherRefType' => fn(string $refType): string => $this->voucherPolicyService()->normalizeVoucherRefType($refType),
                'resolveBankAccountId' => fn(string $value): ?string => $this->evidenceReferenceResolverService()->resolveBankAccountId($value),
                'resolveVoucherRefId' => fn(string $refType, string $value): ?string => $this->evidenceReferenceResolverService()->resolveVoucherRefId($refType, $value),
                'businessRefNameById' => fn(string $refType, string $id): ?string => $this->evidenceReferenceResolverService()->businessRefNameById($refType, $id),
                'payloadScalarForStorage' => fn(mixed $value, bool $preferId = false): mixed => $this->evidencePayloadHelperService()->payloadScalarForStorage($value, $preferId),
                'clientNameFromImportParty' => fn(array $payload): string => trim((string) ($payload['client_name'] ?? $payload['client_company_name'] ?? '')),
                'isUuid' => fn(string $value): bool => $this->isUuid($value),
            ]);
        }

        return $this->evidenceBusinessRefService;
    }

    private function evidenceBankHelperService(): EvidenceBankHelperService
    {
        if ($this->evidenceBankHelperService === null) {
            $this->evidenceBankHelperService = new EvidenceBankHelperService($this->pdo, [
                'amountOrNull' => fn(mixed $value): ?float => $this->amountOrNull($value),
                'bankBalanceStatus' => fn(mixed $value): string => $this->evidenceStatusHelperService()->bankBalanceStatus($value),
                'bankVoucherLinesForSave' => fn(array $lines): array => $this->voucherCreateService()->bankVoucherLinesForSave($lines),
                'bankVoucherPaymentsForSave' => fn(array $payload): array => $this->voucherCreateService()->bankVoucherPaymentsForSave($payload),
                'businessRefIdForStorage' => fn(string $refType, array $payload): ?string => $this->evidenceBusinessRefService()->businessRefIdForStorage($refType, $payload),
                'businessRefNameById' => fn(string $refType, string $id): ?string => $this->evidenceReferenceResolverService()->businessRefNameById($refType, $id),
                'cleanCompanyName' => fn(string $value): string => $this->cleanCompanyName($value),
                'dateTimeValue' => fn(mixed $value): ?string => $this->dateTimeValue($value),
                'dateValue' => fn(mixed $value): string => $this->dateValue($value),
                'dateValueOrNull' => fn(mixed $value): ?string => $this->dateValueOrNull($value),
                'jsonEncodeForStorage' => fn(array $payload): string => $this->evidencePayloadHelperService()->jsonEncodeForStorage($payload),
                'missingRequiredEvidenceRefsMessage' => fn(array $lines, array $payload): ?string => $this->voucherPolicyService()->missingRequiredEvidenceRefsMessage($lines, $payload),
                'normalizeBankVoucherLineRowType' => fn(mixed $value): string => $this->evidenceUploadParserService()->normalizeBankVoucherLineRowType($value),
                'normalizeTransactionDirection' => fn(string $direction): string => $this->normalizeTransactionDirection($direction),
                'number' => fn(mixed $value): float => $this->number($value),
                'payloadScalarForStorage' => fn(mixed $value, bool $preferId = false): mixed => $this->evidencePayloadHelperService()->payloadScalarForStorage($value, $preferId),
                'tableColumnExists' => fn(string $tableName, string $columnName): bool => $this->tableColumnExists($tableName, $columnName),
                'tableExists' => fn(string $tableName): bool => $this->tableExists($tableName),
                'uploadVoucherStatus' => fn(string $dataType, array $payload, string $processStatus): string => $this->evidenceStatusHelperService()->uploadVoucherStatus($dataType, $payload, $processStatus),
            ]);
        }

        return $this->evidenceBankHelperService;
    }

    private function evidenceStatusHelperService(): EvidenceStatusHelperService
    {
        if ($this->evidenceStatusHelperService === null) {
            $this->evidenceStatusHelperService = new EvidenceStatusHelperService($this->pdo, [
                'amountOrNull' => fn(mixed $value): ?float => $this->amountOrNull($value),
                'bankVoucherValidationMessage' => fn(array $payload): ?string => $this->evidenceBankHelperService()->bankVoucherValidationMessage($payload),
                'dateValue' => fn(mixed $value): string => $this->dateValue($value),
                'hasVoucherLinesPayload' => fn(array $payload): bool => $this->evidenceBankHelperService()->hasVoucherLinesPayload($payload),
                'normalizeDataType' => fn(string $type): string => self::normalizeDataType($type),
                'readinessForEvidenceRow' => fn(array $row, array $payload): array => $this->evidenceRuleEngineService()->readinessForEvidenceRow($row, $payload),
                'tableColumnExists' => fn(string $tableName, string $columnName): bool => $this->tableColumnExists($tableName, $columnName),
                'tableExists' => fn(string $tableName): bool => $this->tableExists($tableName),
            ]);
        }

        return $this->evidenceStatusHelperService;
    }

    private function evidenceTransactionContextService(): EvidenceTransactionContextService
    {
        if ($this->evidenceTransactionContextService === null) {
            $this->evidenceTransactionContextService = new EvidenceTransactionContextService(
                fn(string $dataType): string => self::normalizeDataType($dataType),
                fn(array $row): array => $this->evidenceBankHelperService()->normalizeBankTransactionPayload($row),
                fn(string $direction): string => $this->normalizeTransactionDirection($direction),
                fn(string $direction, array $row, string $dataType): string => $this->evidenceTypePolicyService()->transactionDirectionForStorage($direction, $row, $dataType),
                fn(array $row): string => $this->evidenceBankHelperService()->bankCounterpartyName($row),
                fn(): array => $this->ownCompanyDefaultParty(),
                fn(string $name): string => $this->cleanCompanyName($name),
                fn(mixed $value): string => self::normalizeBusinessNumber($value),
                fn(array $row, string $prefix, ?string $fallbackPrefix = null): array => $this->partyFromRow($row, $prefix, $fallbackPrefix),
                fn(array $party): bool => $this->isOwnCompanyParty($party),
                fn(string $dataType): bool => $this->evidenceTypePolicyService()->isManualTaxInvoiceDataType($dataType)
            );
        }

        return $this->evidenceTransactionContextService;
    }

    private function systemFieldService(): SystemFieldService
    {
        if ($this->systemFieldService === null) {
            $this->systemFieldService = new SystemFieldService($this->pdo);
        }

        return $this->systemFieldService;
    }

    private function voucherPolicyService(): VoucherPolicyService
    {
        if ($this->voucherPolicyService === null) {
            $this->voucherPolicyService = new VoucherPolicyService($this->pdo, [
                'tableExists' => fn(string $tableName): bool => $this->tableExists($tableName),
                'tableColumnExists' => fn(string $tableName, string $columnName): bool => $this->tableColumnExists($tableName, $columnName),
                'businessRefIdForStorage' => fn(string $refType, array $payload): ?string => $this->evidenceBusinessRefService()->businessRefIdForStorage($refType, $payload),
            ]);
        }

        return $this->voucherPolicyService;
    }

    private function voucherCreateService(): VoucherCreateService
    {
        if ($this->voucherCreateService === null) {
            $this->voucherCreateService = new VoucherCreateService($this->pdo, [
                'normalizeDataType' => fn(string $type): string => self::normalizeDataType($type),
                'hasVoucherLinesPayload' => fn(array $payload): bool => $this->evidenceBankHelperService()->hasVoucherLinesPayload($payload),
                'dateValue' => fn(mixed $value): string => $this->dateValue($value),
                'amountOrNull' => fn(mixed $value): ?float => $this->amountOrNull($value),
                'businessRefIdForStorage' => fn(string $refType, array $payload): ?string => $this->evidenceBusinessRefService()->businessRefIdForStorage($refType, $payload),
                'applyEvidenceRefsToVoucherLines' => fn(array $lines, array $payload): array => $this->voucherPolicyService()->applyEvidenceRefsToVoucherLines($lines, $payload),
                'normalizeAccountInput' => fn(string $value): string => $this->voucherPolicyService()->normalizeAccountInput($value),
                'normalizeVoucherRefType' => fn(string $value): string => $this->voucherPolicyService()->normalizeVoucherRefType($value),
                'normalizeBankVoucherLineRowType' => fn(mixed $value): string => $this->evidenceUploadParserService()->normalizeBankVoucherLineRowType($value),
                'resolveVoucherRefId' => fn(string $refType, string $value): ?string => $this->evidenceReferenceResolverService()->resolveVoucherRefId($refType, $value),
                'resolveBankAccountId' => fn(string $value): ?string => $this->evidenceReferenceResolverService()->resolveBankAccountId($value),
                'saveVoucher' => fn(array $payload): array => $this->voucherService()->save($payload),
                'recordBankVoucherLearning' => function (string $transactionId, string $voucherId, array $evidence, array $lines, string $actor): void {
                    $this->voucherLearningService()->recordBankVoucherLearning($transactionId, $voucherId, $evidence, $lines, $actor);
                },
                'tableExists' => fn(string $tableName): bool => $this->tableExists($tableName),
                'tableColumnExists' => fn(string $tableName, string $columnName): bool => $this->tableColumnExists($tableName, $columnName),
                'placeholdersForIds' => fn(array $ids, string $prefix): array => $this->placeholdersForIds($ids, $prefix),
                'evidenceHasTransactionIdColumn' => fn(): bool => $this->evidenceStatusHelperService()->evidenceHasTransactionIdColumn(),
            ]);
        }

        return $this->voucherCreateService;
    }

    private function voucherService(): VoucherService
    {
        if ($this->voucherService === null) {
            $this->voucherService = new VoucherService($this->pdo);
        }

        return $this->voucherService;
    }

    private function journalLearningService(): JournalLearningService
    {
        if ($this->journalLearningService === null) {
            $this->journalLearningService = new JournalLearningService($this->pdo);
        }

        return $this->journalLearningService;
    }

    private function voucherLearningService(): VoucherLearningService
    {
        if ($this->voucherLearningService === null) {
            $this->voucherLearningService = new VoucherLearningService(
                $this->journalLearningService(),
                $this->voucherPolicyService(),
                $this->evidenceBusinessRefService(),
                [
                    'bankVoucherPaymentDirectionAndAmount' => fn(array $evidence): array => $this->voucherCreateService()->bankVoucherPaymentDirectionAndAmount($evidence),
                    'businessUnitForUpload' => fn(array $row, string $dataType): string => $this->evidenceTypePolicyService()->businessUnitForUpload($row, $dataType),
                    'normalizeDataType' => fn(string $type): string => self::normalizeDataType($type),
                    'transactionDirectionForStorage' => fn(string $direction, array $payload, string $dataType): string => $this->evidenceTypePolicyService()->transactionDirectionForStorage($direction, $payload, $dataType),
                ]
            );
        }

        return $this->voucherLearningService;
    }

    private function evidenceRuleEngineService(): EvidenceRuleEngineService
    {
        if ($this->evidenceRuleEngineService === null) {
            $this->evidenceRuleEngineService = new EvidenceRuleEngineService(
                fn(string $type): string => self::normalizeDataType($type),
                fn(string $dataType): array => $this->evidenceTypePolicyService()->processingPlanForDataType($dataType),
                fn(array $payload): array => $this->evidenceBankHelperService()->normalizeBankTransactionPayload($payload),
                fn(string $refType, array $payload): ?string => $this->evidenceBusinessRefService()->businessRefIdForStorage($refType, $payload),
                fn(array $payload): bool => $this->evidenceBankHelperService()->hasVoucherLinesPayload($payload),
                fn(string $direction, array $payload, string $dataType): string => $this->evidenceTypePolicyService()->transactionDirectionForStorage($direction, $payload, $dataType),
                fn(array $payload, string $dataType): array => $this->evidenceTransactionContextService()->resolveUploadTransactionContext($payload, $dataType),
                fn(mixed $value): ?float => $this->amountOrNull($value),
                fn(mixed $value): ?string => $this->dateValueOrNull($value),
                fn(mixed $value): string => self::normalizeBusinessNumber($value),
                fn(string $value): string => $this->cleanCompanyName($value),
                fn(string $value): bool => $this->evidenceBusinessRefService()->isEmptySelectionLabel($value)
            );
        }

        return $this->evidenceRuleEngineService;
    }

    private function storeUploadBatch(array $format, array $file, array $rows, string $cancelToken = ''): array
    {
        $actor = ActorHelper::user();
        $batchId = 'EV-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        $fileName = trim((string) ($file['name'] ?? 'upload'));
        $dataType = self::normalizeDataType((string) ($format['data_type'] ?? 'ETC'));

        $this->ensureEvidenceBusinessInfoColumns();
        $this->evidenceSortHelperService()->ensureEvidenceSortColumns();
        try {
            $upsertEvidence = $this->pdo->prepare("
                INSERT INTO ledger_data_evidences
                    (id, source_type, source_key, format_id, evidence_date, client_id, project_id, employee_id, bank_account_id, card_id,
                     client_name, project_name, employee_name, bank_account_name, card_name, currency, supply_amount, vat_amount, total_amount,
                     create_sort_no, status_sort_no,
                     evidence_status, transaction_status, voucher_status, review_status, error_message,
                     latest_imported_at, raw_json, mapped_payload_json, created_by, updated_by)
                VALUES
                    (:id, :source_type, :source_key, :format_id, :evidence_date, :client_id, :project_id, :employee_id, :bank_account_id, :card_id,
                     :client_name, :project_name, :employee_name, :bank_account_name, :card_name, :currency, :supply_amount, :vat_amount, :total_amount,
                     :create_sort_no, :status_sort_no,
                     :evidence_status, :transaction_status, :voucher_status, 'NORMAL', :error_message,
                     NOW(), :raw_json, :mapped_payload_json, :created_by, :updated_by)
                ON DUPLICATE KEY UPDATE
                    source_key = VALUES(source_key),
                    format_id = VALUES(format_id),
                    evidence_date = VALUES(evidence_date),
                    client_id = VALUES(client_id),
                    project_id = VALUES(project_id),
                    employee_id = VALUES(employee_id),
                    bank_account_id = VALUES(bank_account_id),
                    card_id = VALUES(card_id),
                    client_name = VALUES(client_name),
                    project_name = VALUES(project_name),
                    employee_name = VALUES(employee_name),
                    bank_account_name = VALUES(bank_account_name),
                    card_name = VALUES(card_name),
                    currency = VALUES(currency),
                    supply_amount = VALUES(supply_amount),
                    vat_amount = VALUES(vat_amount),
                    total_amount = VALUES(total_amount),
                    create_sort_no = VALUES(create_sort_no),
                    status_sort_no = VALUES(status_sort_no),
                    evidence_status = VALUES(evidence_status),
                    transaction_status = CASE
                        WHEN transaction_status IN ('NONE', 'ERROR', 'DUPLICATED') THEN VALUES(transaction_status)
                        ELSE transaction_status
                    END,
                    voucher_status = VALUES(voucher_status),
                    error_message = VALUES(error_message),
                    latest_imported_at = NOW(),
                    raw_json = VALUES(raw_json),
                    mapped_payload_json = VALUES(mapped_payload_json),
                    deleted_at = NULL,
                    deleted_by = NULL,
                    updated_at = NOW(),
                    updated_by = VALUES(updated_by)
            ");
            $counters = $this->evidenceBatchSaveService()->createBatchCounters();
            $nextStatusSortNo = $this->evidenceBatchSaveService()->nextEvidenceJsonSortNo('_status_sort_no', $dataType);
            $nextCreateSortNo = $this->evidenceBatchSaveService()->nextEvidenceJsonSortNo('_create_sort_no');
            $processedRows = 0;
            $chunkSize = self::UPLOAD_STORE_CHUNK_SIZE;
            $this->evidenceUploadService()->preloadExistingSeedRowsForUploadRows($rows, $dataType);
            $this->pdo->beginTransaction();
            foreach ($rows as $row) {
                $this->evidenceUploadService()->assertUploadNotCanceled($cancelToken);
                if (connection_aborted()) {
                    throw new \RuntimeException('???????? ???????????????');
                }
                $rowState = $this->evidenceBatchSaveService()->buildUploadRowState($row, $dataType);
                $parsedPayload = $rowState['parsed_payload'];
                $processStatus = $rowState['process_status'];
                $voucherStatus = $rowState['voucher_status'];
                $sourceKey = $rowState['source_key'];
                $rawJson = $rowState['raw_json'];
                $errorMessage = $rowState['error_message'];
                $existingSeed = $this->evidenceBatchSaveService()->findExistingUploadSeed($dataType, $sourceKey, $parsedPayload);
                $existingMappedPayload = $this->evidenceBatchSaveService()->existingMappedPayload($existingSeed);
                $this->evidenceBatchSaveService()->assignEvidenceJsonSortNo($parsedPayload, $existingMappedPayload, '_status_sort_no', $nextStatusSortNo);
                $this->evidenceBatchSaveService()->assignEvidenceJsonSortNo($parsedPayload, $existingMappedPayload, '_create_sort_no', $nextCreateSortNo);
                $parsedJson = $this->evidencePayloadHelperService()->jsonEncodeForStorage($parsedPayload);
                if ($this->evidenceBatchSaveService()->isUnchangedExistingSeed($existingSeed, $rawJson, $parsedJson)) {
                    $this->evidenceBatchSaveService()->incrementUnchanged($counters);
                    $this->evidenceBatchSaveService()->commitUploadChunkIfNeeded(++$processedRows, $chunkSize);
                    continue;
                }
                $protectedSeedInfo = $this->evidenceBatchSaveService()->protectedExistingSeedInfo($existingSeed);
                if ($protectedSeedInfo['is_protected']) {
                    $this->evidenceBatchSaveService()->incrementProtectedSkip($counters, $protectedSeedInfo);
                    $this->evidenceBatchSaveService()->commitUploadChunkIfNeeded(++$processedRows, $chunkSize);
                    continue;
                }
                $evidenceId = (string) ($existingSeed['id'] ?? UuidHelper::generate());
                $this->evidenceBatchSaveService()->incrementPersisted($counters, $existingSeed !== null);
                $upsertEvidence->execute($this->evidenceBatchSaveService()->buildPersistParams(
                    $evidenceId,
                    $dataType,
                    (string) ($format['id'] ?? ''),
                    $sourceKey,
                    $parsedPayload,
                    $processStatus,
                    $voucherStatus,
                    $errorMessage,
                    $rawJson,
                    $parsedJson,
                    $actor
                ));
                $cachedSeed = $this->evidenceBatchSaveService()->buildCachedSeed(
                    $evidenceId,
                    $sourceKey,
                    $rawJson,
                    $parsedJson,
                    $processStatus,
                    $voucherStatus
                );
                if ($cachedSeed !== null) {
                    $this->evidenceUploadService()->rememberExistingSeedRow($dataType, $sourceKey, $cachedSeed, $parsedPayload);
                }
                if ($dataType === 'BANK_TRANSACTION') {
                    $this->evidenceBankHelperService()->upsertBankTransactionFromPayload($evidenceId, $parsedPayload, $actor);
                }
                $this->evidenceBatchSaveService()->incrementErrorIfNeeded($counters, $processStatus);
                $this->evidenceBatchSaveService()->commitUploadChunkIfNeeded(++$processedRows, $chunkSize);
            }

            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->evidenceBatchSaveService()->buildBatchResult(
            $counters,
            $batchId,
            $fileName,
            $dataType,
            (string) ($format['id'] ?? ''),
            count($rows)
        );
    }
}
