<?php

namespace App\Controllers\Ledger;

use App\Controllers\Ledger\Concerns\ImportControllerBusinessInfoTrait;
use App\Controllers\Ledger\Concerns\ImportControllerUtilityTrait;
use App\Services\Ledger\EvidenceBusinessRefService;
use App\Services\Ledger\EvidenceFormatMappingService;
use App\Services\Ledger\EvidencePayloadHelperService;
use App\Services\Ledger\EvidencePayloadService;
use App\Services\Ledger\EvidenceReferenceResolverService;
use App\Services\Ledger\EvidenceTemplateDropdownService;
use App\Services\Ledger\EvidenceTypePolicyService;
use App\Services\Ledger\SystemFieldService;
use App\Services\Ledger\VoucherPolicyService;
use Core\DbPdo;
use PDO;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class EvidencePayloadController
{
    use ImportControllerBusinessInfoTrait;
    use ImportControllerUtilityTrait;

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
    private ?EvidenceFormatMappingService $evidenceFormatMappingService = null;
    private ?EvidenceTemplateDropdownService $evidenceTemplateDropdownService = null;
    private ?EvidenceTypePolicyService $evidenceTypePolicyService = null;
    private ?EvidencePayloadHelperService $evidencePayloadHelperService = null;
    private ?EvidenceBusinessRefService $evidenceBusinessRefService = null;
    private ?EvidenceReferenceResolverService $evidenceReferenceResolverService = null;
    private ?VoucherPolicyService $voucherPolicyService = null;
    private ?SystemFieldService $systemFieldService = null;
    private ?array $ownCompanyProfile = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbPdo::conn();
    }

    public function apiDownload(): void
    {
        $formatId = trim((string) ($_GET['format_id'] ?? ''));
        $importType = self::normalizeDataType((string) ($_GET['import_type'] ?? $_GET['data_type'] ?? ''));
        $format = $this->evidenceFormatMappingService()->formatWithColumns($formatId);
        if (!$format) {
            $this->json(['success' => false, 'message' => '증빙 형식 정보를 찾을 수 없습니다.'], 404);
            return;
        }

        $formatType = self::normalizeDataType((string) ($format['data_type'] ?? ''));
        if ($importType === '') {
            $importType = $formatType;
        }
        if ($importType !== $formatType) {
            $this->json(['success' => false, 'message' => '요청한 데이터 유형과 형식의 데이터 유형이 일치하지 않습니다.'], 400);
            return;
        }

        $columns = array_values(array_filter(
            $format['columns'] ?? [],
            static fn(array $column): bool => (int) ($column['is_visible'] ?? 1) === 1
        ));
        if ($columns === []) {
            $columns = $format['columns'] ?? [];
        }
        if ($columns === []) {
            $this->json(['success' => false, 'message' => '다운로드할 표시 컬럼이 없습니다.'], 400);
            return;
        }

        (new EvidencePayloadService($this->pdo))->outputDownload($format, $columns, $formatId, $importType);
    }

    public function apiSearchSummary(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));

        $this->json([
            'success' => true,
            'items' => (new EvidencePayloadService($this->pdo))->searchSummary($query, 10),
        ]);
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

    private function systemFieldService(): SystemFieldService
    {
        if ($this->systemFieldService === null) {
            $this->systemFieldService = new SystemFieldService($this->pdo);
        }

        return $this->systemFieldService;
    }
}
