<?php

namespace App\Controllers\Ledger;

use App\Controllers\Ledger\Concerns\ImportControllerBusinessInfoTrait;
use App\Controllers\Ledger\Concerns\ImportControllerUploadTrait;
use App\Controllers\Ledger\Concerns\ImportControllerUtilityTrait;
use App\Services\Ledger\EvidenceBankHelperService;
use App\Services\Ledger\EvidenceBusinessRefService;
use App\Services\Ledger\EvidenceGenerationService;
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
use App\Services\Ledger\JournalLearningService;
use App\Services\Ledger\SystemFieldService;
use App\Services\Ledger\VoucherCreateService;
use App\Services\Ledger\VoucherLearningService;
use App\Services\Ledger\VoucherPolicyService;
use App\Services\Ledger\VoucherService;
use Core\DbPdo;
use PDO;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class EvidenceListController
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
    private ?EvidenceGenerationService $evidenceGenerationService = null;
    private ?EvidencePayloadHelperService $evidencePayloadHelperService = null;
    private ?EvidenceTypePolicyService $evidenceTypePolicyService = null;
    private ?EvidenceSortHelperService $evidenceSortHelperService = null;
    private ?EvidenceStatusHelperService $evidenceStatusHelperService = null;
    private ?EvidenceBankHelperService $evidenceBankHelperService = null;
    private ?EvidenceBusinessRefService $evidenceBusinessRefService = null;
    private ?EvidenceReferenceResolverService $evidenceReferenceResolverService = null;
    private ?EvidencePayloadNormalizeService $evidencePayloadNormalizeService = null;
    private ?EvidenceTransactionContextService $evidenceTransactionContextService = null;
    private ?EvidenceUploadParserService $evidenceUploadParserService = null;
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

    public function apiList(): void
    {
        $result = $this->evidenceGenerationService()->seedRows($_GET);

        $this->json($result['payload'] ?? ['success' => false], (int) ($result['status'] ?? 200));
    }

    private function evidenceGenerationService(): EvidenceGenerationService
    {
        if ($this->evidenceGenerationService === null) {
            $this->evidenceGenerationService = new EvidenceGenerationService(
                $this->pdo,
                function (): void {
                    $this->ensureEvidenceBusinessInfoColumns();
                },
                function (): void {
                    $this->evidenceSortHelperService()->ensureEvidenceSortColumns();
                },
                fn(string $type): bool => $this->isAllowedDataType($type),
                fn(string $type): string => self::normalizeDataType($type),
                fn(string $sourceType): string => $this->evidenceTypePolicyService()->normalizeImportSourceType($sourceType),
                fn(string $sourceType): array => $this->evidenceTypePolicyService()->importTypesForSourceType($sourceType),
                fn(string $dataType): string => $this->evidenceTypePolicyService()->sourceTypeForDataType($dataType),
                fn(string $sourceType): string => $this->evidenceTypePolicyService()->sourceTypeLabel($sourceType),
                fn(string $importType): string => $this->evidenceTypePolicyService()->importTypeLabel($importType),
                fn(string $table): bool => $this->tableExists($table),
                fn(string $formatId): array => [],
                fn(array $payload): array => $this->evidenceBankHelperService()->normalizeBankTransactionPayload($payload),
                fn(array $payload): array => $this->evidencePayloadNormalizeService()->normalizeEvidenceMappedPayloadForResponse($payload),
                function (array $row, array &$payload): void {
                    $this->mergeEvidenceBusinessInfoIntoPayload($row, $payload);
                },
                fn(string $value): bool => $this->isUuid($value),
                fn(string $refType, string $id): ?string => $this->evidenceReferenceResolverService()->businessRefNameById($refType, $id),
                function (array &$row): void {
                    $this->evidenceStatusHelperService()->applyReadinessToEvidenceRow($row);
                },
                fn(array $row, string $key): int => $this->evidenceSortHelperService()->evidencePayloadSortNo($row, $key),
                fn(string $message, array $row = [], int $rowNo = 0): string => $this->evidenceRuleEngineService()->formatTransactionCreateError($message, $row, $rowNo)
            );
        }

        return $this->evidenceGenerationService;
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
                $this->pdo,
                [
                    'amountOrNull' => fn(mixed $value): ?float => $this->amountOrNull($value),
                ]
            );
        }

        return $this->evidenceTypePolicyService;
    }

    private function evidenceSortHelperService(): EvidenceSortHelperService
    {
        if ($this->evidenceSortHelperService === null) {
            $this->evidenceSortHelperService = new EvidenceSortHelperService();
        }

        return $this->evidenceSortHelperService;
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
}
