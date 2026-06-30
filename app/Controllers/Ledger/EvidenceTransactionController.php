<?php

namespace App\Controllers\Ledger;

use App\Controllers\Ledger\Concerns\ImportControllerBusinessInfoTrait;
use App\Controllers\Ledger\Concerns\ImportControllerUploadTrait;
use App\Controllers\Ledger\Concerns\ImportControllerUtilityTrait;
use App\Services\Ledger\BundledVoucherService;
use App\Services\Ledger\EvidenceBankHelperService;
use App\Services\Ledger\EvidenceBusinessRefService;
use App\Services\Ledger\EvidencePayloadHelperService;
use App\Services\Ledger\EvidencePayloadNormalizeService;
use App\Services\Ledger\EvidenceReferenceResolverService;
use App\Services\Ledger\EvidenceRuleEngineService;
use App\Services\Ledger\EvidenceStatusHelperService;
use App\Services\Ledger\EvidenceTemplateDropdownService;
use App\Services\Ledger\EvidenceTransactionContextService;
use App\Services\Ledger\EvidenceTransactionCreateService;
use App\Services\Ledger\EvidenceTypePolicyService;
use App\Services\Ledger\EvidenceUploadParserService;
use App\Services\Ledger\EvidenceUploadService;
use App\Services\Ledger\EvidenceUploadValidationService;
use App\Services\Ledger\JournalLearningService;
use App\Services\Ledger\SystemFieldService;
use App\Services\Ledger\TransactionCrudService;
use App\Services\Ledger\TransactionPayloadBuildService;
use App\Services\Ledger\VoucherCreateService;
use App\Services\Ledger\VoucherLearningService;
use App\Services\Ledger\VoucherPolicyService;
use App\Services\Ledger\VoucherService;
use Core\DbPdo;
use PDO;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class EvidenceTransactionController
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
    private ?EvidenceTransactionCreateService $evidenceTransactionCreateService = null;
    private ?EvidenceUploadService $evidenceUploadService = null;
    private ?EvidenceUploadParserService $evidenceUploadParserService = null;
    private ?EvidenceUploadValidationService $evidenceUploadValidationService = null;
    private ?EvidencePayloadHelperService $evidencePayloadHelperService = null;
    private ?EvidenceTemplateDropdownService $evidenceTemplateDropdownService = null;
    private ?EvidenceTypePolicyService $evidenceTypePolicyService = null;
    private ?EvidencePayloadNormalizeService $evidencePayloadNormalizeService = null;
    private ?EvidenceReferenceResolverService $evidenceReferenceResolverService = null;
    private ?EvidenceBusinessRefService $evidenceBusinessRefService = null;
    private ?EvidenceBankHelperService $evidenceBankHelperService = null;
    private ?BundledVoucherService $bundledVoucherService = null;
    private ?VoucherPolicyService $voucherPolicyService = null;
    private ?VoucherCreateService $voucherCreateService = null;
    private ?EvidenceStatusHelperService $evidenceStatusHelperService = null;
    private ?EvidenceRuleEngineService $evidenceRuleEngineService = null;
    private ?EvidenceTransactionContextService $evidenceTransactionContextService = null;
    private ?TransactionPayloadBuildService $transactionPayloadBuildService = null;
    private ?TransactionCrudService $transactionService = null;
    private ?SystemFieldService $systemFieldService = null;
    private ?VoucherService $voucherService = null;
    private ?JournalLearningService $journalLearningService = null;
    private ?VoucherLearningService $voucherLearningService = null;
    private ?array $ownCompanyProfile = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbPdo::conn();
    }

    public function apiCreateTransactions(): void
    {
        $result = $this->evidenceTransactionCreateService()->createTransactions($this->requestPayload());

        $this->json($result['payload'] ?? ['success' => false], (int) ($result['status'] ?? 200));
    }

    private function evidenceTransactionCreateService(): EvidenceTransactionCreateService
    {
        if ($this->evidenceTransactionCreateService === null) {
            $this->evidenceTransactionCreateService = new EvidenceTransactionCreateService($this->pdo, [
                'uploadBatch' => fn(string $batchId): ?array => $this->uploadBatch($batchId),
                'createBundledVoucherFromEvidenceRows' => fn(array $rowIds): array => $this->bundledVoucherService()->createBundledVoucherFromEvidenceRows($rowIds),
                'readinessForEvidenceRow' => fn(array $row, array $payload): array => $this->evidenceRuleEngineService()->readinessForEvidenceRow($row, $payload),
                'createVoucherFromBankPayload' => fn(string $rowId, array $payload, string $transactionId = '', bool $allowExisting = false): ?string => $this->voucherCreateService()->createVoucherFromBankPayload($rowId, $payload, $transactionId, $allowExisting),
                'resetBankEvidenceTransactionClaim' => function (string $evidenceId, string $actor): void {
                    $this->evidenceBankHelperService()->resetBankEvidenceTransactionClaim($evidenceId, $actor);
                },
                'updateUploadRowStatus' => function (string $rowId, string $status, ?string $message = null, ?string $transactionId = null): void {
                    $this->evidenceUploadService()->updateUploadRowStatus($rowId, $status, $message, $transactionId);
                },
                'formatTransactionCreateError' => fn(string $message, array $row = [], int $rowNo = 0): string => $this->evidenceRuleEngineService()->formatTransactionCreateError($message, $row, $rowNo),
                'refreshUploadBatchStatus' => function (string $batchId): void {
                    $this->refreshUploadBatchStatus($batchId);
                },
                'normalizeDataType' => fn(string $type): string => self::normalizeDataType($type),
                'isTransactionProcessingType' => fn(string $dataType): bool => $this->evidenceTypePolicyService()->isTransactionProcessingType($dataType),
                'processingPlanForDataType' => fn(string $dataType): array => $this->evidenceTypePolicyService()->processingPlanForDataType($dataType),
                'transactionProcessingDataTypes' => fn(): array => $this->evidenceTypePolicyService()->transactionProcessingDataTypes(self::DATA_TYPES),
                'ensureEvidenceBusinessInfoColumns' => function (): void {
                    $this->ensureEvidenceBusinessInfoColumns();
                },
                'mergeEvidenceBusinessInfoIntoPayload' => function (array $evidenceRow, array &$payload): void {
                    $this->mergeEvidenceBusinessInfoIntoPayload($evidenceRow, $payload);
                },
                'normalizeEvidenceMappedPayloadForResponse' => fn(array $payload): array => $this->evidencePayloadNormalizeService()->normalizeEvidenceMappedPayloadForResponse($payload),
                'transactionService' => fn(): TransactionCrudService => $this->transactionService(),
                'buildTransactionCreatePayload' => fn(array $row, string $dataType): array => $this->transactionPayloadBuildService()->buildTransactionCreatePayload($row, $dataType),
                'shouldRetryTransactionHeaderOnly' => fn(string $message, array $payload): bool => $this->transactionPayloadBuildService()->shouldRetryTransactionHeaderOnly($message, $payload),
                'cleanCompanyName' => fn(string $name): string => $this->cleanCompanyName($name),
                'dateValue' => fn(mixed $value): string => $this->dateValue($value),
                'evidenceCreatedTransactionSql' => fn(string $alias = ''): string => $this->evidenceStatusHelperService()->evidenceCreatedTransactionSql($alias),
                'findClientId' => fn(string $clientName): ?string => $this->evidenceReferenceResolverService()->findClientId($clientName),
                'normalizeBusinessNumber' => fn(mixed $value): string => self::normalizeBusinessNumber($value),
                'number' => fn(mixed $value): float => $this->number($value),
                'resolveUploadTransactionContext' => fn(array $row, string $dataType): array => $this->evidenceTransactionContextService()->resolveUploadTransactionContext($row, $dataType),
                'evidenceRowsForExistingVoucherCheck' => fn(array $evidenceIds): array => $this->voucherCreateService()->evidenceRowsForExistingVoucherCheck($evidenceIds),
                'existingVoucherForBankPayload' => fn(string $evidenceId, array $payload): ?array => $this->voucherCreateService()->existingVoucherForBankPayload($evidenceId, $payload),
                'placeholdersForIds' => fn(array $ids, string $prefix): array => $this->placeholdersForIds($ids, $prefix),
                'tableColumnExists' => fn(string $tableName, string $columnName): bool => $this->tableColumnExists($tableName, $columnName),
                'tableExists' => fn(string $tableName): bool => $this->tableExists($tableName),
                'linkVoucherToEvidence' => function (string $evidenceId, string $voucherId, string $transactionId, string $actor): void {
                    $this->voucherCreateService()->linkVoucherToEvidence($evidenceId, $voucherId, $transactionId, $actor);
                },
                'linkVoucherToTransaction' => function (string $voucherId, string $transactionId, mixed $matchAmount = null, string $linkType = 'AUTO', string $actor = 'SYSTEM'): void {
                    $this->voucherCreateService()->linkVoucherToTransaction($voucherId, $transactionId, $matchAmount, $linkType, $actor);
                },
                'tagCreatedVoucher' => function (string $voucherId, string $evidenceId, string $transactionId = '', string $actor = 'SYSTEM'): void {
                    $this->voucherCreateService()->tagCreatedVoucher($voucherId, $evidenceId, $transactionId, $actor);
                },
                'updateEvidenceVoucherStatus' => function (string $evidenceId, string $voucherStatus, string $actor, ?string $errorMessage = null): void {
                    $this->voucherCreateService()->updateEvidenceVoucherStatus($evidenceId, $voucherStatus, $actor, $errorMessage);
                },
            ]);
        }

        return $this->evidenceTransactionCreateService;
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

    private function evidenceUploadValidationService(): EvidenceUploadValidationService
    {
        if ($this->evidenceUploadValidationService === null) {
            $this->evidenceUploadValidationService = new EvidenceUploadValidationService([
                'amountOrNull' => fn(mixed $value): ?float => $this->amountOrNull($value),
                'dateTimeValue' => fn(mixed $value): ?string => $this->dateTimeValue($value),
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

    private function bundledVoucherService(): BundledVoucherService
    {
        if ($this->bundledVoucherService === null) {
            $this->bundledVoucherService = new BundledVoucherService($this->pdo, [
                'activeVoucherExistsForEvidence' => fn(string $evidenceId, string $transactionId = ''): bool => $this->evidenceStatusHelperService()->activeVoucherExistsForEvidence($evidenceId, $transactionId),
                'applyEvidenceRefsToVoucherLines' => fn(array $lines, array $payload): array => $this->voucherPolicyService()->applyEvidenceRefsToVoucherLines($lines, $payload),
                'bankVoucherLinesForSave' => fn(array $lines): array => $this->voucherCreateService()->bankVoucherLinesForSave($lines),
                'dateValue' => fn(mixed $value): string => $this->dateValue($value),
                'hasVoucherLinesPayload' => fn(array $payload): bool => $this->evidenceBankHelperService()->hasVoucherLinesPayload($payload),
                'linkVoucherToEvidence' => function (string $evidenceId, string $voucherId, string $transactionId, string $actor): void {
                    $this->voucherCreateService()->linkVoucherToEvidence($evidenceId, $voucherId, $transactionId, $actor);
                },
                'linkVoucherToTransaction' => function (string $voucherId, string $transactionId, mixed $matchAmount = null, string $linkType = 'AUTO', string $actor = 'SYSTEM'): void {
                    $this->voucherCreateService()->linkVoucherToTransaction($voucherId, $transactionId, $matchAmount, $linkType, $actor);
                },
                'mergeEvidenceBusinessInfoIntoPayload' => function (array $evidenceRow, array $payload): array {
                    $this->mergeEvidenceBusinessInfoIntoPayload($evidenceRow, $payload);
                    return $payload;
                },
                'missingRequiredEvidenceRefsMessage' => fn(array $lines, array $payload): ?string => $this->voucherPolicyService()->missingRequiredEvidenceRefsMessage($lines, $payload),
                'normalizeBankTransactionPayload' => fn(array $payload): array => $this->evidenceBankHelperService()->normalizeBankTransactionPayload($payload),
                'normalizeDataType' => fn(string $type): string => self::normalizeDataType($type),
                'normalizeEvidenceMappedPayloadForResponse' => fn(array $payload): array => $this->evidencePayloadNormalizeService()->normalizeEvidenceMappedPayloadForResponse($payload),
                'placeholdersForIds' => fn(array $ids, string $prefix): array => $this->placeholdersForIds($ids, $prefix),
                'readinessForEvidenceRow' => fn(array $row, array $payload): array => $this->evidenceRuleEngineService()->readinessForEvidenceRow($row, $payload),
                'saveVoucher' => fn(array $payload): array => $this->voucherService()->save($payload),
                'tableColumnExists' => fn(string $tableName, string $columnName): bool => $this->tableColumnExists($tableName, $columnName),
                'tableExists' => fn(string $tableName): bool => $this->tableExists($tableName),
                'updateEvidenceVoucherStatus' => function (string $evidenceId, string $voucherStatus, string $actor, ?string $errorMessage = null): void {
                    $this->voucherCreateService()->updateEvidenceVoucherStatus($evidenceId, $voucherStatus, $actor, $errorMessage);
                },
            ]);
        }

        return $this->bundledVoucherService;
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

    private function transactionPayloadBuildService(): TransactionPayloadBuildService
    {
        if ($this->transactionPayloadBuildService === null) {
            $this->transactionPayloadBuildService = new TransactionPayloadBuildService([
                'amountOrNull' => fn(mixed $value): ?float => $this->amountOrNull($value),
                'businessRefIdForStorage' => fn(string $refType, array $payload): ?string => $this->evidenceBusinessRefService()->businessRefIdForStorage($refType, $payload),
                'businessUnitForUpload' => fn(array $row, string $dataType): string => $this->evidenceTypePolicyService()->businessUnitForUpload($row, $dataType),
                'dateValue' => fn(mixed $value): string => $this->dateValue($value),
                'existingClientIdByBusinessNumber' => fn(string $value): ?string => $this->evidenceReferenceResolverService()->existingClientIdByBusinessNumber($value),
                'findProjectId' => fn(string $value): ?string => $this->evidenceReferenceResolverService()->findProjectId($value),
                'normalizeBankTransactionPayload' => fn(array $payload): array => $this->evidenceBankHelperService()->normalizeBankTransactionPayload($payload),
                'normalizeDataType' => fn(string $type): string => self::normalizeDataType($type),
                'resolveUploadTransactionContext' => fn(array $row, string $dataType): array => $this->evidenceTransactionContextService()->resolveUploadTransactionContext($row, $dataType),
                'transactionDirectionForStorage' => fn(string $direction, array $payload, string $dataType): string => $this->evidenceTypePolicyService()->transactionDirectionForStorage($direction, $payload, $dataType),
            ]);
        }

        return $this->transactionPayloadBuildService;
    }

    private function transactionService(): TransactionCrudService
    {
        if ($this->transactionService === null) {
            $this->transactionService = new TransactionCrudService($this->pdo);
        }

        return $this->transactionService;
    }

    private function systemFieldService(): SystemFieldService
    {
        if ($this->systemFieldService === null) {
            $this->systemFieldService = new SystemFieldService($this->pdo);
        }

        return $this->systemFieldService;
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
}
