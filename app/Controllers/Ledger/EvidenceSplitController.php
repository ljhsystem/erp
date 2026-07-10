<?php

namespace App\Controllers\Ledger;

use App\Controllers\Ledger\Concerns\ImportControllerBusinessInfoTrait;
use App\Controllers\Ledger\Concerns\ImportControllerUploadTrait;
use App\Controllers\Ledger\Concerns\ImportControllerUtilityTrait;
use App\Services\Ledger\EvidenceBankHelperService;
use App\Services\Ledger\EvidenceBatchSaveService;
use App\Services\Ledger\EvidenceBusinessRefService;
use App\Services\Ledger\EvidenceClientSyncService;
use App\Services\Ledger\EvidenceGenerationSplitService;
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
use PDO;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class EvidenceSplitController
{
    use ImportControllerUtilityTrait;
    use ImportControllerBusinessInfoTrait;
    use ImportControllerUploadTrait;

    private const BANK_VOUCHER_LINE_FIELDS = [
        'header_row_no',
        'sort_no',
        'line_row_type',
        'account_id',
        'debit',
        'credit',
        'line_summary',
        'line_ref_target',
        'line_ref_id',
    ];
    private PDO $pdo;
    private ?EvidenceGenerationSplitService $evidenceGenerationSplitService = null;
    private ?EvidenceUploadService $evidenceUploadService = null;
    private ?EvidenceUploadParserService $evidenceUploadParserService = null;
    private ?EvidenceUploadValidationService $evidenceUploadValidationService = null;
    private ?EvidenceBatchSaveService $evidenceBatchSaveService = null;
    private ?EvidenceSortHelperService $evidenceSortHelperService = null;
    private ?EvidencePayloadHelperService $evidencePayloadHelperService = null;
    private ?EvidenceTemplateDropdownService $evidenceTemplateDropdownService = null;
    private ?EvidenceTypePolicyService $evidenceTypePolicyService = null;
    private ?EvidencePayloadNormalizeService $evidencePayloadNormalizeService = null;
    private ?EvidenceReferenceResolverService $evidenceReferenceResolverService = null;
    private ?EvidenceBusinessRefService $evidenceBusinessRefService = null;
    private ?EvidenceClientSyncService $evidenceClientSyncService = null;
    private ?EvidenceBankHelperService $evidenceBankHelperService = null;
    private ?VoucherPolicyService $voucherPolicyService = null;
    private ?VoucherCreateService $voucherCreateService = null;
    private ?VoucherService $voucherService = null;
    private ?JournalLearningService $journalLearningService = null;
    private ?VoucherLearningService $voucherLearningService = null;
    private ?EvidenceStatusHelperService $evidenceStatusHelperService = null;
    private ?EvidenceRuleEngineService $evidenceRuleEngineService = null;
    private ?EvidenceTransactionContextService $evidenceTransactionContextService = null;
    private ?SystemFieldService $systemFieldService = null;
    private ?array $ownCompanyProfile = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbPdo::conn();
    }

    public function apiSplitChild(): void
    {
        $result = $this->evidenceGenerationSplitService()->splitChild($this->requestPayload());

        $this->json($result['payload'] ?? ['success' => false], (int) ($result['status'] ?? 200));
    }

    private function evidenceGenerationSplitService(): EvidenceGenerationSplitService
    {
        if ($this->evidenceGenerationSplitService === null) {
            $this->evidenceGenerationSplitService = new EvidenceGenerationSplitService(
                $this->pdo,
                fn(array $payload): array => $this->evidencePayloadNormalizeService()->mappedPayloadForStorage($payload),
                fn(mixed $value): ?float => $this->amountOrNull($value),
                fn(string $type): string => self::normalizeDataType($type)
            );
        }

        return $this->evidenceGenerationSplitService;
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
                     'tableExists' => fn(string $tableName): bool => $this->tableExists($tableName),
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
                'applyReadinessToEvidenceRow' => function (array &$row): void {
                    $this->evidenceStatusHelperService()->applyReadinessToEvidenceRow($row);
                },
            ]);
        }

        return $this->evidenceBatchSaveService;
    }

    private function evidenceSortHelperService(): EvidenceSortHelperService
    {
        if ($this->evidenceSortHelperService === null) {
            $this->evidenceSortHelperService = new EvidenceSortHelperService();
        }

        return $this->evidenceSortHelperService;
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
                'clientNameFromImportParty' => fn(array $payload): string => $this->evidenceClientSyncService()->clientNameFromImportParty($payload),
                'isUuid' => fn(string $value): bool => $this->isUuid($value),
            ]);
        }

        return $this->evidenceBusinessRefService;
    }

    private function evidenceClientSyncService(): EvidenceClientSyncService
    {
        if ($this->evidenceClientSyncService === null) {
            $this->evidenceClientSyncService = new EvidenceClientSyncService($this->pdo, [
                'bankCounterpartyName' => fn(array $row): string => $this->evidenceBankHelperService()->bankCounterpartyName($row),
                'cleanCompanyName' => fn(string $value): string => $this->cleanCompanyName($value),
                'isManualTaxInvoiceDataType' => fn(string $value): bool => $this->evidenceTypePolicyService()->isManualTaxInvoiceDataType($value),
                'isUuid' => fn(string $value): bool => $this->isUuid($value),
                'normalizeBankTransactionPayload' => fn(array $payload): array => $this->evidenceBankHelperService()->normalizeBankTransactionPayload($payload),
                'normalizeBusinessNumber' => fn(string $value): string => self::normalizeBusinessNumber($value),
                'normalizeCompanyNameForCompare' => fn(string $value): string => $this->normalizeCompanyNameForCompare($value),
                'normalizeDataType' => fn(string $value): string => self::normalizeDataType($value),
                'ownCompanyProfile' => fn(): array => $this->ownCompanyProfile(),
                'payloadScalarForStorage' => fn(mixed $value, bool $preferId = false): mixed => $this->evidencePayloadHelperService()->payloadScalarForStorage($value, $preferId),
                'transactionDirectionForStorage' => fn(string $direction, array $payload, string $dataType): string => $this->evidenceTypePolicyService()->transactionDirectionForStorage($direction, $payload, $dataType),
            ]);
        }

        return $this->evidenceClientSyncService;
    }

    private function evidenceBankHelperService(): EvidenceBankHelperService
    {
        if ($this->evidenceBankHelperService === null) {
            $this->evidenceBankHelperService = new EvidenceBankHelperService($this->pdo, [
                'amountOrNull' => fn(mixed $value): ?float => $this->amountOrNull($value),
                'bankBalanceStatus' => fn(mixed $value): string => $this->evidenceStatusHelperService()->bankBalanceStatus($value),
                'bankVoucherLinesForSave' => fn(array $lines): array => $this->voucherCreateService()->bankVoucherLinesForSave($lines),
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

    private function evidenceStatusHelperService(): EvidenceStatusHelperService
    {
        if ($this->evidenceStatusHelperService === null) {
            $this->evidenceStatusHelperService = new EvidenceStatusHelperService($this->pdo, [
                'amountOrNull' => fn(mixed $value): ?float => $this->amountOrNull($value),
                'bankVoucherValidationMessage' => fn(array $payload): ?string => $this->evidenceBankHelperService()->bankVoucherValidationMessage($payload),
                'businessReadinessForEvidenceRow' => fn(array $row, array $payload): array => $this->evidenceRuleEngineService()->businessReadinessForEvidenceRow($row, $payload),
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

    private function systemFieldService(): SystemFieldService
    {
        if ($this->systemFieldService === null) {
            $this->systemFieldService = new SystemFieldService($this->pdo);
        }

        return $this->systemFieldService;
    }
}
