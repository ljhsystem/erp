<?php

namespace App\Controllers\Ledger;

use App\Controllers\Ledger\Concerns\ImportControllerBusinessInfoTrait;
use App\Controllers\Ledger\Concerns\ImportControllerUploadTrait;
use App\Controllers\Ledger\Concerns\ImportControllerUtilityTrait;
use App\Services\Ledger\EvidenceBankHelperService;
use App\Services\Ledger\EvidenceBusinessRefService;
use App\Services\Ledger\EvidencePayloadHelperService;
use App\Services\Ledger\EvidencePayloadNormalizeService;
use App\Services\Ledger\EvidenceReferenceResolverService;
use App\Services\Ledger\EvidenceRuleEngineService;
use App\Services\Ledger\EvidenceStatusHelperService;
use App\Services\Ledger\EvidenceTemplateDropdownService;
use App\Services\Ledger\EvidenceTemplateService;
use App\Services\Ledger\EvidenceTransactionContextService;
use App\Services\Ledger\EvidenceTypePolicyService;
use App\Services\Ledger\EvidenceUploadParserService;
use App\Services\Ledger\EvidenceUploadService;
use App\Services\Ledger\EvidenceUploadValidationService;
use App\Services\Ledger\SystemFieldService;
use App\Services\Ledger\VoucherCreateService;
use App\Services\Ledger\VoucherPolicyService;
use App\Services\Ledger\VoucherService;
use Core\DbPdo;
use PDO;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class EvidenceImportController
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
    private const UPLOAD_PREVIEW_ROW_LIMIT = 200;

    private PDO $pdo;
    private ?EvidenceTemplateService $evidenceTemplateService = null;
    private ?SystemFieldService $systemFieldService = null;
    private ?EvidenceUploadService $evidenceUploadService = null;
    private ?EvidenceUploadParserService $evidenceUploadParserService = null;
    private ?EvidenceUploadValidationService $evidenceUploadValidationService = null;
    private ?EvidencePayloadHelperService $evidencePayloadHelperService = null;
    private ?EvidenceTemplateDropdownService $evidenceTemplateDropdownService = null;
    private ?EvidenceTypePolicyService $evidenceTypePolicyService = null;
    private ?EvidencePayloadNormalizeService $evidencePayloadNormalizeService = null;
    private ?EvidenceBusinessRefService $evidenceBusinessRefService = null;
    private ?EvidenceReferenceResolverService $evidenceReferenceResolverService = null;
    private ?EvidenceBankHelperService $evidenceBankHelperService = null;
    private ?EvidenceStatusHelperService $evidenceStatusHelperService = null;
    private ?EvidenceTransactionContextService $evidenceTransactionContextService = null;
    private ?VoucherPolicyService $voucherPolicyService = null;
    private ?VoucherCreateService $voucherCreateService = null;
    private ?VoucherService $voucherService = null;
    private ?EvidenceRuleEngineService $evidenceRuleEngineService = null;
    private ?array $ownCompanyProfile = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbPdo::conn();
    }

    public function apiTemplate(): void
    {
        try {
            $type = self::normalizeDataType((string) ($_GET['type'] ?? 'TAX_INVOICE'));
            if (!$this->isAllowedDataType($type)) {
                $this->json(['success' => false, 'message' => '지원하지 않는 자료유형입니다. 자료유형을 확인해 주세요.'], 400);
                return;
            }

            $columnsCsv = trim((string) ($_GET['columns'] ?? ''));
            $columnDisplayName = $this->requestColumnDisplayName($_GET);
            $columnRequirementPolicy = $this->requestColumnRequirementPolicy($_GET);
            $format = $this->syntheticFormatForDataType($type, $columnsCsv, $columnDisplayName, $columnRequirementPolicy);
            $columns = is_array($format['columns'] ?? null) ? $format['columns'] : [];
            $headers = $this->evidenceTemplateService()->excelHeadersForColumns($columns);
            $samples = $columns !== []
                ? [$this->evidenceTemplateService()->sampleRowForColumns($columns, $type)]
                : [];
            $required = array_map(
                static fn(array $column): int => (int) ($column['is_required'] ?? 0),
                $columns
            );
            $fields = array_map(
                static fn(array $column): string => (string) ($column['system_field_name'] ?? ''),
                $columns
            );
            $filename = strtolower($type) . '_template.xlsx';
            $title = $this->evidenceTypePolicyService()->importTypeLabel($type) ?: $type;
            $this->evidenceTemplateService()->downloadTemplate($filename, $title, $headers, $samples, $required, $fields, $type);
        } catch (\Throwable $e) {
            if (!headers_sent()) {
                self::clearOutputBuffers();
                $this->json(['success' => false, 'message' => '엑셀 템플릿 다운로드 중 오류가 발생했습니다.'], 500);
            }
        }
    }

    public function apiFieldOptions(): void
    {
        $dataType = self::normalizeDataType((string) ($_GET['data_type'] ?? 'TAX_INVOICE'));
        if (!$this->isAllowedDataType($dataType)) {
            $this->json(['success' => true, 'data' => []]);
            return;
        }

        $this->json([
            'success' => true,
            'data' => $this->systemFieldService()->sourceColumnOptions($dataType),
            'target_table' => $this->systemFieldService()->targetTableForDataType($dataType),
        ]);
    }

    public function apiPreview(): void
    {
        $this->evidenceUploadService()->prepareLargeUploadRuntime();
        \Core\Session::write();

        $dataType = $this->requestedImportType($_POST, 'TAX_INVOICE');
        $columnsCsv = trim((string) ($_POST['excel_template_columns'] ?? ''));
        $columnDisplayName = $this->requestColumnDisplayName($_POST);
        $columnRequirementPolicy = $this->requestColumnRequirementPolicy($_POST);
        $format = $this->syntheticFormatForDataType($dataType, $columnsCsv, $columnDisplayName, $columnRequirementPolicy);
        if (!$format || empty($_FILES['file'])) {
            $this->json(['success' => false, 'message' => '업로드 파일을 선택해 주세요.'], 400);
            return;
        }

        try {
            if (!$this->isAllowedDataType($dataType)) {
                throw new \RuntimeException('지원하지 않는 자료유형입니다. 자료유형을 확인해 주세요.');
            }
            $checks = $this->evidenceUploadService()->validateUploadFileColumns($_FILES['file'], $format['columns']);
            $this->evidenceUploadService()->assertUploadFileMatchesFormat($checks, $format['columns']);
            $checkErrors = array_values(array_filter($checks, static fn(array $check): bool => ($check['level'] ?? '') === 'error'));
            if ($checkErrors !== []) {
                throw new \RuntimeException('엑셀 헤더가 자료유형 템플릿과 일치하지 않습니다. 업로드 파일의 헤더를 확인한 뒤 다시 시도하세요. ' . (string) ($checkErrors[0]['message'] ?? ''));
            }
            $rows = $this->evidenceUploadParserService()->parseUploadedRows($_FILES['file'], $format['columns']);
            if ($rows === []) {
                throw new \RuntimeException('업로드할 데이터 행을 찾지 못했습니다. 헤더 아래 2행부터 데이터가 있는지 확인해 주세요.');
            }
            $rows = $this->evidenceUploadValidationService()->enrichUploadRows($rows, $dataType);
            $rows = $this->evidenceUploadValidationService()->validatePreviewRows($rows, $format['columns'], $dataType);
            $rows = $this->evidenceUploadService()->annotateSeedComparison($rows, $dataType);
            $token = $this->evidenceUploadService()->storePreviewSession($format, $_FILES['file'], $rows);
            $summary = $this->evidenceUploadService()->validationSummary($rows);
            $summary['check_error'] = count(array_filter($checks, static fn(array $check): bool => ($check['level'] ?? '') === 'error'));
            $summary['check_warning'] = count(array_filter($checks, static fn(array $check): bool => ($check['level'] ?? '') === 'warning'));
            $summary['preview_rows'] = min(count($rows), self::UPLOAD_PREVIEW_ROW_LIMIT);
            $this->json(['success' => true, 'data' => [
                'preview_token' => $token,
                'summary' => $summary,
                'checks' => $checks,
                'format' => $format,
                'rows' => array_slice($rows, 0, self::UPLOAD_PREVIEW_ROW_LIMIT),
            ]]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    private function evidenceTemplateService(): EvidenceTemplateService
    {
        if ($this->evidenceTemplateService === null) {
            $this->evidenceTemplateService = new EvidenceTemplateService([
                'applyBankTemplateDropdowns' => function (Spreadsheet $spreadsheet, array $sheetSpecs): void {
                    $this->evidenceTemplateDropdownService()->applyBankTemplateDropdowns($spreadsheet, $sheetSpecs);
                },
                'applyTemplateDropdowns' => function (Spreadsheet $spreadsheet, \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $fields, array $headers, string $dataType): void {
                    $this->evidenceTemplateDropdownService()->applyTemplateDropdowns($spreadsheet, $sheet, $fields, $headers, $dataType);
                },
                'clearOutputBuffers' => function (): void {
                    self::clearOutputBuffers();
                },
                'dataTypeLabel' => fn(string $type): string => $this->evidenceTypePolicyService()->importTypeLabel($type) !== ''
                    ? $this->evidenceTypePolicyService()->importTypeLabel($type)
                    : ($type === 'ETC' ? 'ETC' : $type),
                'formatColumnsInOrder' => fn(array $columns): array => $this->evidencePayloadNormalizeService()->formatColumnsInOrder($columns),
                'isBasicInfoTemplateColumn' => fn(string $field, string $header, string $dataType = ''): bool => $this->isBasicInfoTemplateColumn($field, $header, $dataType),
                'isStandardInfoTemplateColumn' => fn(string $field, string $header, string $dataType): bool => $this->isStandardInfoTemplateColumn($field, $header, $dataType),
                'isVoucherTemplateColumn' => fn(string $field, string $header, string $dataType = ''): bool => $this->isVoucherTemplateColumn($field, $header, $dataType),
                'normalizeRequirementMode' => fn(mixed $value): int => self::normalizeRequirementMode($value),
                'safeFilename' => fn(string $name): string => self::safeFilename($name),
                'safeSheetTitle' => fn(string $title): string => self::safeSheetTitle($title),
            ]);
        }

        return $this->evidenceTemplateService;
    }

    private function systemFieldService(): SystemFieldService
    {
        if ($this->systemFieldService === null) {
            $this->systemFieldService = new SystemFieldService($this->pdo);
        }

        return $this->systemFieldService;
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
                    'looksLikeBankTemplateHeaders' => fn(array $headers): bool => $this->evidenceTemplateService()->looksLikeBankTemplateHeaders($headers),
                    'normalizeDataType' => fn(string $type): string => self::normalizeDataType($type),
                    'normalizeVoucherRefType' => fn(string $refType): string => $this->voucherPolicyService()->normalizeVoucherRefType($refType),
                    'tableColumnExists' => fn(string $tableName, string $columnName): bool => $this->tableColumnExists($tableName, $columnName),
                    'tableExists' => fn(string $tableName): bool => $this->tableExists($tableName),
                    'uniqueSheetTitle' => fn(Spreadsheet $spreadsheet, string $baseTitle): string => $this->evidenceTemplateService()->uniqueSheetTitle($spreadsheet, $baseTitle),
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

    private function requestedImportType(array $payload, string $default = 'TAX_INVOICE'): string
    {
        return self::normalizeDataType((string) ($payload['import_type'] ?? $payload['type'] ?? $payload['data_type'] ?? $default));
    }

    private function syntheticFormatForDataType(string $dataType, string $columnsCsv = '', array $columnDisplayName = [], array $columnRequirementPolicy = []): ?array
    {
        $dataType = self::normalizeDataType($dataType);
        if (!$this->isAllowedDataType($dataType)) {
            return null;
        }

        $fieldOptions = $this->sourceFieldOptionsForDataType($dataType);
        $columns = [];
        foreach ($fieldOptions as $index => $fieldOption) {
            $columnKey = trim((string) ($fieldOption['original_column_key'] ?? $fieldOption['value'] ?? ''));
            $field = trim((string) ($fieldOption['system_field_name'] ?? $fieldOption['value'] ?? ''));
            $header = trim((string) ($fieldOption['label'] ?? $columnKey ?? $field));
            if ($columnKey === '' || $field === '' || $header === '') {
                continue;
            }

            $columns[] = [
                'original_column_key' => $columnKey,
                'excel_column_name' => $header,
                'system_field_name' => $field,
                'column_order' => $index + 1,
                'excel_column_index' => $index + 1,
                'is_required' => (int) ($fieldOption['is_required'] ?? $this->systemFieldService()->formatFieldRequiredMode($dataType, $field, $fieldOption)),
                'is_reference_column' => 0,
                'is_visible' => 1,
            ];
        }

        $columns = $this->filterSyntheticColumns($columns, $columnsCsv, $dataType);
        $columns = $this->applySyntheticColumnPolicies($dataType, $columns, $columnDisplayName, $columnRequirementPolicy);
        if (!$this->hasUsableSyntheticColumns($columns)) {
            $columns = $this->fallbackSyntheticColumnsForDataType($dataType, $columnDisplayName, $columnRequirementPolicy);
        }

        return [
            'id' => '',
            'format_name' => strtolower($dataType),
            'data_type' => $dataType,
            'columns' => $columns,
        ];
    }

    private function filterSyntheticColumns(array $columns, string $columnsCsv, string $dataType = ''): array
    {
        $requested = array_values(array_filter(array_map('trim', explode(',', $columnsCsv))));
        if ($requested === []) {
            return $columns;
        }

        $columnMap = [];
        foreach ($columns as $column) {
            $columnKey = trim((string) ($column['original_column_key'] ?? ''));
            $field = trim((string) ($column['system_field_name'] ?? ''));
            if ($columnKey !== '') {
                $columnMap[$columnKey] = $column;
            }
            if ($field !== '') {
                $columnMap[$field] = $columnMap[$field] ?? $column;
            }
        }

        $filtered = [];
        foreach ($requested as $index => $requestedKey) {
            $column = $columnMap[$requestedKey] ?? null;
            if ($column === null) {
                continue;
            }

            $filtered[] = array_replace($column, [
                'column_order' => $index + 1,
                'excel_column_index' => $index + 1,
            ]);
        }

        return $filtered !== [] ? $filtered : $columns;
    }

    private function hasUsableSyntheticColumns(array $columns): bool
    {
        foreach ($columns as $column) {
            if (trim((string) ($column['excel_column_name'] ?? '')) !== ''
                && trim((string) ($column['system_field_name'] ?? '')) !== ''
            ) {
                return true;
            }
        }

        return false;
    }

    private function fallbackSyntheticColumnsForDataType(string $dataType, array $columnDisplayName = [], array $columnRequirementPolicy = []): array
    {
        $dataType = self::normalizeDataType($dataType);
        if ($dataType !== 'TAX_INVOICE_MANUAL') {
            return [];
        }

        $fields = [
            'transaction_date' => '거래일자',
            'business_unit' => '사업구분',
            'transaction_direction' => '거래구분',
            'operation_type' => '업무유형',
            'supplier_business_number' => '공급자 사업자등록번호',
            'supplier_company_name' => '공급자 상호',
            'supplier_ceo_name' => '공급자 대표자명',
            'supplier_address' => '공급자 주소',
            'customer_business_number' => '공급받는자 사업자등록번호',
            'customer_company_name' => '공급받는자 상호',
            'customer_ceo_name' => '공급받는자 대표자명',
            'customer_address' => '공급받는자 주소',
            'project_name' => '프로젝트',
            'supply_amount' => '공급가액',
            'vat_amount' => '부가세',
            'total_amount' => '금액',
            'receipt_claim_type' => '영수/청구구분',
            'description' => '적요',
            'note' => '비고',
        ];

        $columns = [];
        $index = 1;
        foreach ($fields as $field => $label) {
            $columns[] = [
                'original_column_key' => $field,
                'excel_column_name' => trim((string) ($columnDisplayName[$field] ?? $label)),
                'system_field_name' => $field,
                'column_order' => $index,
                'excel_column_index' => $index,
                'is_required' => $this->systemFieldService()->effectiveFormatRequirementMode(
                    $dataType,
                    $field,
                    $this->requestRequirementPolicyMode($columnRequirementPolicy[$field] ?? 0)
                ),
                'is_reference_column' => $this->evidenceTemplateDropdownService()->fallbackTemplateFieldOption($field) !== null ? 1 : 0,
                'is_visible' => 1,
            ];
            $index++;
        }

        return $columns;
    }

    private function syntheticColumnForRequestedKey(string $dataType, string $requestedKey): ?array
    {
        $field = trim($requestedKey);
        if ($field === '') {
            return null;
        }

        $fallbackOption = $this->evidenceTemplateDropdownService()->fallbackTemplateFieldOption($field);
        $knownBankRawFields = [
            'raw_transaction_datetime',
            'raw_deposit_amount',
            'raw_withdraw_amount',
            'raw_balance_amount',
            'raw_description',
            'raw_counterparty_account_number',
            'raw_counterparty_bank_name',
            'raw_memo',
            'raw_transaction_type',
            'raw_check_bill_amount',
            'raw_cms_code',
            'raw_counterparty_name',
        ];
        if ($fallbackOption === null && !(self::normalizeDataType($dataType) === 'BANK_TRANSACTION' && in_array($field, $knownBankRawFields, true))) {
            return null;
        }

        return [
            'original_column_key' => $field,
            'excel_column_name' => $field,
            'system_field_name' => $field,
            'column_order' => 0,
            'excel_column_index' => 0,
            'is_required' => $this->systemFieldService()->formatFieldRequiredMode($dataType, $field),
            'is_reference_column' => $fallbackOption !== null ? 1 : 0,
            'is_visible' => 1,
        ];
    }

    private function sourceFieldOptionsForDataType(string $dataType): array
    {
        $options = [];
        $fieldOptions = $this->systemFieldService()->sourceColumnOptions($dataType);

        foreach ($fieldOptions as $fieldOption) {
            $field = trim((string) ($fieldOption['value'] ?? ''));
            $label = trim((string) ($fieldOption['label'] ?? $field));
            if ($field === '' || $label === '') {
                continue;
            }

            $options[] = [
                'original_column_key' => $field,
                'label' => $label,
                'system_field_name' => $field,
                'is_required' => (int) ($fieldOption['is_required'] ?? $this->systemFieldService()->formatFieldRequiredMode($dataType, $field, $fieldOption)),
            ];
        }

        return $options;
    }

    private function applySyntheticColumnPolicies(string $dataType, array $columns, array $columnDisplayName, array $columnRequirementPolicy): array
    {
        foreach ($columns as &$column) {
            $requirementKey = trim((string) ($column['original_column_key'] ?? $column['system_field_name'] ?? ''));
            $field = trim((string) ($column['system_field_name'] ?? ''));
            if ($field === '' || $requirementKey === '') {
                continue;
            }

            $displayName = trim((string) ($columnDisplayName[$requirementKey] ?? $columnDisplayName[$field] ?? ''));
            if ($displayName !== '') {
                $column['excel_column_name'] = $displayName;
            }

            $column['is_required'] = $this->systemFieldService()->effectiveFormatRequirementMode(
                $dataType,
                $field,
                $this->requestRequirementPolicyMode($columnRequirementPolicy[$requirementKey] ?? ($columnRequirementPolicy[$field] ?? ($column['is_required'] ?? 0)))
            );
        }
        unset($column);

        return $columns;
    }

    private function templateFieldGroup(string $field, string $dataType): string
    {
        $options = $this->evidenceTemplateDropdownService()->systemFieldOptionsByValue($dataType);
        return (string) ($options[trim($field)]['group'] ?? '');
    }

    private function isStandardInfoTemplateColumn(string $field, string $header, string $dataType): bool
    {
        $group = $this->templateFieldGroup($field, $dataType);
        return in_array(trim($field), [
            'source_type',
            'import_type',
            'data_type',
            'evidence_type',
            'business_unit',
            'operation_type',
            'transaction_direction',
            'bank_direction',
            'currency',
            'currency_code',
            'exchange_rate',
        ], true);
    }

    private function isBasicInfoTemplateColumn(string $field, string $header, string $dataType = ''): bool
    {
        $group = $this->templateFieldGroup($field, $dataType);
        if (in_array(trim($field), [
            'client_id',
            'client_name',
            'client_company_name',
            'project_id',
            'project_name',
            'employee_id',
            'employee_name',
            'bank_account_id',
            'bank_account_name',
            'account_name',
            'card_id',
            'card_name',
            'team_id',
            'team_name',
            'supplier_company_name',
            'customer_company_name',
        ], true)) {
            return true;
        }

        return str_contains($group, '기초정보');
    }

    private function isVoucherTemplateColumn(string $field, string $header, string $dataType = ''): bool
    {
        return in_array(trim($field), [
            'voucher_date',
            'voucher_no',
            'summary_text',
            'note',
            'voucher_memo',
            'header_row_no',
            'sort_no',
            'line_row_type',
            'account_id',
            'debit',
            'credit',
            'line_summary',
            'line_ref_target',
            'line_ref_id',
        ], true);
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
