<?php

namespace App\Controllers\Ledger;

use App\Models\Ledger\TransactionLinkModel;
use App\Models\Ledger\ProcessingItemModel;
use App\Models\System\ClientModel;
use App\Models\System\CompanyModel;
use App\Services\Ledger\JournalLearningService;
use App\Services\Ledger\ProcessingItemSplitService;
use App\Services\Ledger\ProcessingItemTreeService;
use App\Services\Ledger\SystemFieldService;
use App\Services\Ledger\TransactionCrudService;
use App\Services\Ledger\VoucherService;
use Core\DbPdo;
use Core\Helpers\ActorHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use PDO;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ImportController
{
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
    private const UPLOAD_PREVIEW_ROW_LIMIT = 200;
    private const FORMAT_DEPRECATED_SYSTEM_FIELDS = [
        'voucher_date',
        'summary_text',
        'note',
        'voucher_memo',
        'header_row_no',
        'line_no',
        'account_id',
        'debit',
        'credit',
        'line_summary',
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
    private ?TransactionCrudService $transactionService = null;
    private ?SystemFieldService $systemFieldService = null;
    private ?VoucherService $voucherService = null;
    private ?JournalLearningService $journalLearningService = null;
    private ?array $ownCompanyProfile = null;
    private array $systemFieldOptionsByDataType = [];
    private array $voucherRefIdCache = [];
    private array $voucherRefNameCache = [];
    private array $bankAccountIdCache = [];
    private array $existingSeedRowCache = [];
    private array $existingSeedFingerprintCache = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbPdo::conn();
    }

    public function apiTemplate(): void
    {
        try {
            $formatId = trim((string) ($_GET['format_id'] ?? ''));
            if ($formatId !== '') {
                $format = $this->formatWithColumns($formatId);
                if (!$format || empty($format['columns'])) {
                    $this->json(['success' => false, 'message' => '다운로드할 양식을 찾을 수 없습니다.'], 404);
                    return;
                }

                [$filename, $title, $headers, $samples, $required, $fields] = array_pad($this->templateSpecFromFormat($format), 6, []);
                $this->downloadTemplate($filename, $title, $headers, $samples, $required, $fields, (string) ($format['data_type'] ?? ''));
                return;
            }

            $type = self::normalizeDataType((string) ($_GET['type'] ?? 'TAX_INVOICE'));
            if (!$this->isAllowedDataType($type)) {
                $this->json(['success' => false, 'message' => '지원하지 않는 양식 유형입니다.'], 400);
                return;
            }

            [$filename, $title, $headers, $samples, $required, $fields] = array_pad($this->templateSpec($type), 6, []);
            $this->downloadTemplate($filename, $title, $headers, $samples, $required, $fields, $type);
        } catch (\Throwable $e) {
            error_log('[ImportController] Template download failed: ' . $e->getMessage());
            if (!headers_sent()) {
                self::clearOutputBuffers();
                $this->json(['success' => false, 'message' => '양식 다운로드에 실패했습니다.'], 500);
            }
        }
    }

    private function downloadTemplate(string $filename, string $title, array $headers, array $samples, array $required = [], array $fields = [], string $dataType = ''): void
    {
        $spreadsheet = new Spreadsheet();
        $tempFile = tempnam(sys_get_temp_dir(), 'ledger_template_');
        if ($tempFile === false) {
            throw new \RuntimeException('Failed to create temporary XLSX file.');
        }

        try {
            if ($this->isWorkbookTemplateSpec($headers)) {
                foreach ($headers as $index => $spec) {
                    $sheet = $index === 0
                        ? $spreadsheet->getActiveSheet()
                        : $spreadsheet->createSheet($index);
                    $this->fillTemplateSheet(
                        $sheet,
                        (string) ($spec['title'] ?? 'sheet' . ($index + 1)),
                        is_array($spec['headers'] ?? null) ? $spec['headers'] : [],
                        is_array($spec['samples'] ?? null) ? $spec['samples'] : [],
                        is_array($spec['required'] ?? null) ? $spec['required'] : [],
                        is_array($spec['fields'] ?? null) ? $spec['fields'] : [],
                        $dataType
                    );
                }
                $this->applyBankTemplateDropdowns($spreadsheet, $headers);
                $spreadsheet->setActiveSheetIndex(0);
            } else {
                $sheet = $spreadsheet->getActiveSheet();
                $this->fillTemplateSheet($sheet, $title, $headers, $samples, $required, $fields, $dataType);
                $this->applyTemplateDropdowns($spreadsheet, $sheet, $fields, $headers, $dataType);
            }

            (new Xlsx($spreadsheet))->save($tempFile);
            $spreadsheet->disconnectWorksheets();

            $filename = self::safeFilename($filename);
            if (!str_ends_with(strtolower($filename), '.xlsx')) {
                $filename .= '.xlsx';
            }
            $asciiFallback = preg_replace('#[^A-Za-z0-9_.-]+#', '_', $filename) ?: 'upload_template.xlsx';
            $encodedFilename = rawurlencode($filename);

            if (headers_sent($sentFile, $sentLine)) {
                throw new \RuntimeException("Headers already sent at {$sentFile}:{$sentLine}");
            }

            self::clearOutputBuffers();
            header_remove('Content-Type');
            header_remove('Content-Disposition');
            header_remove('Cache-Control');
            header_remove('Pragma');
            header_remove('Expires');
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header("Content-Disposition: attachment; filename=\"{$asciiFallback}\"; filename*=UTF-8''{$encodedFilename}");
            header('Content-Transfer-Encoding: binary');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: max-age=0');
            header('Content-Length: ' . filesize($tempFile));

            if (readfile($tempFile) === false) {
                error_log('[ImportController] Template download failed while streaming XLSX.');
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
            if (is_file($tempFile)) {
                @unlink($tempFile);
            }
        }
        exit;
    }

    private function applyBankTemplateDropdowns(Spreadsheet $spreadsheet, array $sheetSpecs): void
    {
        $headerSheetIndex = null;
        $lineSheetIndex = null;
        $accountColumnIndex = null;
        $rowTypeColumnIndex = null;
        $businessRefColumns = [
            'CLIENT' => null,
            'PROJECT' => null,
            'EMPLOYEE' => null,
            'ACCOUNT' => null,
            'CARD' => null,
        ];
        $businessRefFields = [
            'client_id' => 'CLIENT',
            'client_name' => 'CLIENT',
            'client_company_name' => 'CLIENT',
            'counterparty_name' => 'CLIENT',
            'project_id' => 'PROJECT',
            'project_name' => 'PROJECT',
            'project_code' => 'PROJECT',
            'employee_id' => 'EMPLOYEE',
            'employee_name' => 'EMPLOYEE',
            'user_name' => 'EMPLOYEE',
            'bank_account_id' => 'ACCOUNT',
            'bank_account_name' => 'ACCOUNT',
            'bank_account' => 'ACCOUNT',
            'account_name' => 'ACCOUNT',
            'payment_account_name' => 'ACCOUNT',
            'card_id' => 'CARD',
            'card_name' => 'CARD',
            'card_number' => 'CARD',
        ];
        $businessRefFieldPriority = [
            'client_name' => 10,
            'client_id' => 20,
            'client_company_name' => 30,
            'counterparty_name' => 90,
            'project_name' => 10,
            'project_id' => 20,
            'project_code' => 30,
            'employee_name' => 10,
            'employee_id' => 20,
            'user_name' => 30,
            'bank_account_name' => 10,
            'bank_account_id' => 20,
            'bank_account' => 30,
            'account_name' => 40,
            'payment_account_name' => 50,
            'card_name' => 10,
            'card_id' => 20,
            'card_number' => 30,
        ];
        $businessRefColumnPriorities = [
            'CLIENT' => PHP_INT_MAX,
            'PROJECT' => PHP_INT_MAX,
            'EMPLOYEE' => PHP_INT_MAX,
            'ACCOUNT' => PHP_INT_MAX,
            'CARD' => PHP_INT_MAX,
        ];

        foreach ($sheetSpecs as $index => $spec) {
            if ($headerSheetIndex === null) {
                $headerSheetIndex = $index;
                $fields = is_array($spec['fields'] ?? null) ? array_values($spec['fields']) : [];
                foreach ($fields as $fieldIndex => $field) {
                    $field = (string) $field;
                    $refType = $businessRefFields[$field] ?? null;
                    $priority = $businessRefFieldPriority[$field] ?? 100;
                    if ($refType !== null && $priority < $businessRefColumnPriorities[$refType]) {
                        $businessRefColumns[$refType] = $fieldIndex + 1;
                        $businessRefColumnPriorities[$refType] = $priority;
                    }
                }
            }

            if (($spec['title'] ?? '') !== '분개라인') {
                continue;
            }
            $headers = is_array($spec['headers'] ?? null) ? array_values($spec['headers']) : [];
            $fields = is_array($spec['fields'] ?? null) ? array_values($spec['fields']) : [];
            $lineSheetIndex = $index;
            foreach ($headers as $headerIndex => $header) {
                $field = (string) ($fields[$headerIndex] ?? '');
                if ($field === 'account_id' || trim((string) $header) === '계정') {
                    $accountColumnIndex = $headerIndex + 1;
                }
                if ($field === 'line_row_type' || trim((string) $header) === '행타입') {
                    $rowTypeColumnIndex = $headerIndex + 1;
                }
            }
            break;
        }

        if ($lineSheetIndex === null || $spreadsheet->getSheetCount() <= $lineSheetIndex) {
            return;
        }

        $accountOptions = $this->accountDropdownOptions();
        $businessRefOptions = [
            'CLIENT' => $this->businessRefDropdownOptions('CLIENT'),
            'PROJECT' => $this->businessRefDropdownOptions('PROJECT'),
            'EMPLOYEE' => $this->businessRefDropdownOptions('EMPLOYEE'),
            'ACCOUNT' => $this->businessRefDropdownOptions('ACCOUNT'),
            'CARD' => $this->businessRefDropdownOptions('CARD'),
        ];
        $rowTypeOptions = ['분개', '보조'];
        if ($accountOptions === [] && $rowTypeColumnIndex === null && !array_filter($businessRefOptions)) {
            return;
        }

        $referenceSheet = $spreadsheet->createSheet();
        $referenceSheet->setTitle('_목록');
        foreach ($accountOptions as $rowIndex => $option) {
            $referenceSheet->setCellValue('A' . ($rowIndex + 1), $option);
        }
        foreach ($rowTypeOptions as $rowIndex => $option) {
            $referenceSheet->setCellValue('B' . ($rowIndex + 1), $option);
        }
        $businessRefListColumns = [
            'CLIENT' => 'C',
            'PROJECT' => 'D',
            'EMPLOYEE' => 'E',
            'ACCOUNT' => 'F',
            'CARD' => 'G',
        ];
        foreach ($businessRefListColumns as $refType => $column) {
            foreach ($businessRefOptions[$refType] as $rowIndex => $option) {
                $referenceSheet->setCellValue($column . ($rowIndex + 1), $option);
            }
        }
        $referenceSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

        $lineSheet = $spreadsheet->getSheet($lineSheetIndex);
        if ($accountColumnIndex !== null && $accountOptions !== []) {
            $this->applyListValidation(
                $lineSheet,
                Coordinate::stringFromColumnIndex($accountColumnIndex),
                "'_목록'!\$A\$1:\$A\$" . count($accountOptions),
                '계정 선택 오류',
                '목록에 있는 계정코드 또는 계정명을 선택하세요.'
            );
        }
        if ($rowTypeColumnIndex !== null) {
            $this->applyListValidation(
                $lineSheet,
                Coordinate::stringFromColumnIndex($rowTypeColumnIndex),
                "'_목록'!\$B\$1:\$B\$" . count($rowTypeOptions),
                '행타입 선택 오류',
                '분개 또는 보조 중 하나를 선택하세요.'
            );
        }

        if ($headerSheetIndex !== null && $spreadsheet->getSheetCount() > $headerSheetIndex) {
            $headerSheet = $spreadsheet->getSheet($headerSheetIndex);
            foreach ($businessRefColumns as $refType => $columnIndex) {
                if ($columnIndex === null || $businessRefOptions[$refType] === []) {
                    continue;
                }
                $listColumn = $businessRefListColumns[$refType];
                $this->applyListValidation(
                    $headerSheet,
                    Coordinate::stringFromColumnIndex($columnIndex),
                    "'" . $referenceSheet->getTitle() . "'!$" . $listColumn . '$1:$' . $listColumn . '$' . count($businessRefOptions[$refType]),
                    'List selection error',
                    'Select a value from the list.'
                );
            }
        }
    }

    private function applySimpleBankTemplateDropdowns(
        Spreadsheet $spreadsheet,
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        array $headers
    ): void {
        $targetColumns = [
            '사업구분' => 'BUSINESS_UNIT',
            '거래유형' => 'TRANSACTION_TYPE',
            '거래처명' => 'CLIENT',
            '거래처' => 'CLIENT',
            '프로젝트명' => 'PROJECT',
            '프로젝트' => 'PROJECT',
            '직원명' => 'EMPLOYEE',
            '직원' => 'EMPLOYEE',
            '계좌명' => 'ACCOUNT',
            '계좌' => 'ACCOUNT',
            '카드명' => 'CARD',
            '카드' => 'CARD',
        ];
        $listColumns = [
            'BUSINESS_UNIT' => 'A',
            'TRANSACTION_TYPE' => 'B',
            'CLIENT' => 'C',
            'PROJECT' => 'D',
            'EMPLOYEE' => 'E',
            'ACCOUNT' => 'F',
            'CARD' => 'G',
        ];
        $options = [
            'BUSINESS_UNIT' => $this->codeDropdownOptions('BUSINESS_UNIT', ['HQ', 'CONSTRUCTION', 'ECOMMERCE']),
            'TRANSACTION_TYPE' => $this->codeDropdownOptions('TRANSACTION_TYPE', ['GENERAL', 'PURCHASE', 'SALES']),
            'CLIENT' => $this->businessRefDropdownOptions('CLIENT'),
            'PROJECT' => $this->businessRefDropdownOptions('PROJECT'),
            'EMPLOYEE' => $this->businessRefDropdownOptions('EMPLOYEE'),
            'ACCOUNT' => $this->businessRefDropdownOptions('ACCOUNT'),
            'CARD' => $this->businessRefDropdownOptions('CARD'),
        ];

        $targetHeaderColumns = [];
        foreach (array_values($headers) as $headerIndex => $header) {
            $cleanHeader = preg_replace('/\s*\*$/u', '', trim((string) $header)) ?? trim((string) $header);
            $refType = $targetColumns[$cleanHeader] ?? null;
            if ($refType !== null && $options[$refType] !== []) {
                $targetHeaderColumns[] = [$headerIndex + 1, $refType];
            }
        }
        if ($targetHeaderColumns === []) {
            return;
        }

        $referenceSheet = $spreadsheet->createSheet();
        $referenceSheet->setTitle('_목록');
        foreach ($listColumns as $refType => $listColumn) {
            foreach ($options[$refType] as $rowIndex => $option) {
                $referenceSheet->setCellValue($listColumn . ($rowIndex + 1), $option);
            }
        }
        $referenceSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

        foreach ($targetHeaderColumns as [$columnIndex, $refType]) {
            $listColumn = $listColumns[$refType];
            $this->applyListValidation(
                $sheet,
                Coordinate::stringFromColumnIndex($columnIndex),
                "'_목록'!$" . $listColumn . '$1:$' . $listColumn . '$' . count($options[$refType]),
                '목록 선택 오류',
                '목록에 있는 값을 선택하세요.'
            );
        }
    }

    private function applyTemplateDropdowns(
        Spreadsheet $spreadsheet,
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        array $fields,
        array $headers,
        string $dataType
    ): void {
        $fields = array_values($fields);
        if ($fields === []) {
            if ($this->looksLikeBankTemplateHeaders($headers)) {
                $this->applySimpleBankTemplateDropdowns($spreadsheet, $sheet, $headers);
            }
            return;
        }

        $fieldOptions = $this->systemFieldOptionsByValue($dataType);

        $targetColumns = [];
        $lists = [];
        foreach ($fields as $index => $field) {
            $field = trim((string) $field);
            if ($this->shouldSkipTemplateDropdown($dataType, $field)) {
                continue;
            }

            $option = $fieldOptions[$field] ?? null;
            if (!is_array($option)) {
                continue;
            }

            $listKey = $this->templateDropdownListKey($option);
            if ($listKey === '') {
                continue;
            }

            if (!array_key_exists($listKey, $lists)) {
                $lists[$listKey] = $this->templateDropdownOptionsForField($option);
            }
            if ($lists[$listKey] === []) {
                continue;
            }

            $targetColumns[] = [$index + 1, $listKey];
        }

        if ($targetColumns === []) {
            return;
        }

        $referenceSheet = $spreadsheet->createSheet();
        $referenceSheet->setTitle($this->uniqueSheetTitle($spreadsheet, '_목록'));

        $listColumns = [];
        $listIndex = 1;
        foreach ($lists as $listKey => $options) {
            if ($options === []) {
                continue;
            }
            $listColumn = Coordinate::stringFromColumnIndex($listIndex);
            $listColumns[$listKey] = [$listColumn, count($options)];
            foreach ($options as $rowIndex => $option) {
                $referenceSheet->setCellValue($listColumn . ($rowIndex + 1), $option);
            }
            $listIndex++;
        }
        $referenceSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

        $quotedSheet = "'" . str_replace("'", "''", $referenceSheet->getTitle()) . "'";
        foreach ($targetColumns as [$columnIndex, $listKey]) {
            if (!isset($listColumns[$listKey])) {
                continue;
            }
            [$listColumn, $rowCount] = $listColumns[$listKey];
            $this->applyListValidation(
                $sheet,
                Coordinate::stringFromColumnIndex($columnIndex),
                "{$quotedSheet}!$" . $listColumn . '$1:$' . $listColumn . '$' . $rowCount,
                '목록 선택 오류',
                '목록에 있는 값을 선택하세요.'
            );
        }
    }

    private function shouldSkipTemplateDropdown(string $dataType, string $field): bool
    {
        $dataType = self::normalizeDataType($dataType);
        if ($dataType === 'CARD_HOMETAX') {
            return in_array($field, [
                'client_name',
                'card_number',
                'merchant_business_number',
                'merchant_company_name',
                'merchant_business_type',
                'merchant_business_category',
            ], true);
        }

        if (in_array($dataType, ['CARD_STATEMENT', 'CARD_APPROVAL'], true)) {
            return in_array($field, [
                'card_number',
                'payment_account_number',
                'payment_bank_name',
                'merchant_business_number',
                'merchant_company_name',
                'merchant_business_category',
                'merchant_address1',
                'merchant_address2',
                'merchant_phone',
            ], true);
        }

        if ($dataType !== 'TAX_INVOICE' && !self::isManualTaxInvoiceDataType($dataType)) {
            return false;
        }

        return in_array($field, [
            'supplier_business_number',
            'supplier_company_name',
            'supplier_ceo_name',
            'supplier_address',
            'supplier_email',
            'customer_business_number',
            'customer_company_name',
            'customer_ceo_name',
            'customer_address',
            'customer_email_1',
        ], true);
    }

    private function templateDropdownListKey(array $fieldOption): string
    {
        $table = trim((string) ($fieldOption['table'] ?? ''));
        $column = trim((string) ($fieldOption['column'] ?? ''));
        if ($table === '' || $column === '') {
            return '';
        }

        if ($table === 'system_codes') {
            $codeGroup = trim((string) ($fieldOption['code_group'] ?? ''));
            return $codeGroup !== '' ? 'code:' . $codeGroup : '';
        }

        if (in_array($table, [
            'system_clients',
            'system_projects',
            'user_employees',
            'system_bank_accounts',
            'system_cards',
            'system_company',
            'user_departments',
        ], true)) {
            return 'table:' . $table . ':' . $column;
        }

        return '';
    }

    private function templateDropdownOptionsForField(array $fieldOption): array
    {
        $table = trim((string) ($fieldOption['table'] ?? ''));
        $column = trim((string) ($fieldOption['column'] ?? ''));
        if ($table === '' || $column === '') {
            return [];
        }

        if ($table === 'system_codes') {
            $codeGroup = trim((string) ($fieldOption['code_group'] ?? ''));
            return $codeGroup !== '' ? $this->codeDropdownOptions($codeGroup) : [];
        }

        return $this->tableColumnDropdownOptions($table, $column);
    }

    private function tableColumnDropdownOptions(string $table, string $column): array
    {
        if (!$this->tableExists($table) || !$this->tableColumnExists($table, $column)) {
            return [];
        }

        $tableSql = '`' . str_replace('`', '``', $table) . '`';
        $columnSql = '`' . str_replace('`', '``', $column) . '`';
        $where = [];
        if ($this->tableColumnExists($table, 'deleted_at')) {
            $where[] = 'deleted_at IS NULL';
        }
        if ($this->tableColumnExists($table, 'is_active')) {
            $where[] = 'COALESCE(is_active, 1) = 1';
        }

        try {
            $stmt = $this->pdo->query(
                "SELECT DISTINCT {$columnSql} AS dropdown_value FROM {$tableSql}"
                . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
                . " ORDER BY {$columnSql} ASC"
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }

        $options = [];
        foreach ($rows as $row) {
            $value = trim((string) ($row['dropdown_value'] ?? ''));
            if ($value !== '') {
                $options[] = $value;
            }
        }

        return array_values(array_unique($options));
    }

    private function systemFieldOptionsByValue(string $dataType): array
    {
        $dataType = self::normalizeDataType($dataType);
        if (isset($this->systemFieldOptionsByDataType[$dataType])) {
            return $this->systemFieldOptionsByDataType[$dataType];
        }

        $options = [];
        foreach ($this->systemFieldService()->fieldOptions($dataType) as $option) {
            $value = trim((string) ($option['value'] ?? ''));
            if ($value !== '') {
                $options[$value] = $option;
            }
        }

        return $this->systemFieldOptionsByDataType[$dataType] = $options;
    }

    private function uniqueSheetTitle(Spreadsheet $spreadsheet, string $baseTitle): string
    {
        $baseTitle = self::safeSheetTitle($baseTitle);
        $title = $baseTitle;
        $index = 1;
        while ($spreadsheet->sheetNameExists($title)) {
            $suffix = '_' . $index;
            $title = self::safeSheetTitle(substr($baseTitle, 0, 31 - strlen($suffix)) . $suffix);
            $index++;
        }

        return $title;
    }

    private function applyListValidation(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $column, string $formula, string $errorTitle, string $error): void
    {
        $range = "{$column}2:{$column}1048576";
        $validation = new \PhpOffice\PhpSpreadsheet\Cell\DataValidation();
        $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle($errorTitle);
        $validation->setError($error);
        $validation->setFormula1($formula);
        $validation->setSqref($range);

        $sheet->setDataValidation($range, $validation);
    }

    private function codeDropdownOptions(string $codeGroup, array $fallback = []): array
    {
        if (!$this->tableExists('system_codes')) {
            return $fallback;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT code, code_name
                FROM system_codes
                WHERE code_group = :code_group
                  AND COALESCE(is_active, 1) = 1
                  AND deleted_at IS NULL
                ORDER BY sort_no ASC, code ASC
            ");
            $stmt->execute([':code_group' => $codeGroup]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return $fallback;
        }

        $options = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row['code_name'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($row['code'] ?? ''));
            }
            if ($label === '') {
                continue;
            }
            $options[] = $label;
        }

        return $options !== [] ? array_values(array_unique($options)) : $fallback;
    }

    private function accountDropdownOptions(): array
    {
        try {
            $where = 'deleted_at IS NULL';
            if ($this->tableColumnExists('ledger_accounts', 'status')) {
                $where .= " AND COALESCE(status, 'active') <> 'deleted'";
            }
            if ($this->tableColumnExists('ledger_accounts', 'is_postable')) {
                $where .= " AND COALESCE(is_postable, 'Y') = 'Y'";
            } elseif ($this->tableColumnExists('ledger_accounts', 'is_posting')) {
                $where .= " AND COALESCE(is_posting, 1) = 1";
            }

            $stmt = $this->pdo->query("
                SELECT account_code, account_name
                FROM ledger_accounts
                WHERE {$where}
                ORDER BY account_code ASC, account_name ASC
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }

        $options = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row['account_code'] ?? ''));
            $name = trim((string) ($row['account_name'] ?? ''));
            $label = trim($code . ($code !== '' && $name !== '' ? ' ' : '') . $name);
            if ($label !== '') {
                $options[] = $label;
            }
        }

        return array_values(array_unique($options));
    }

    private function businessRefDropdownOptions(string $refType): array
    {
        $config = match ($this->normalizeVoucherRefType($refType)) {
            'CLIENT' => ['system_clients', ['client_name', 'company_name', 'business_number'], ['client_name', 'company_name']],
            'PROJECT' => ['system_projects', ['project_name', 'project_code'], ['project_name', 'project_code']],
            'EMPLOYEE' => ['user_employees', ['employee_name', 'name'], ['employee_name', 'name']],
            'ACCOUNT' => ['system_bank_accounts', ['account_name', 'account_number', 'bank_name'], ['account_name', 'bank_name', 'account_number']],
            'CARD' => ['system_cards', ['card_name', 'card_number'], ['card_name', 'card_number']],
            default => null,
        };
        if ($config === null) {
            return [];
        }

        [$table, $labelColumns, $orderColumns] = $config;
        if (!$this->tableExists($table)) {
            return [];
        }

        $selects = [];
        foreach ($labelColumns as $column) {
            if ($this->tableColumnExists($table, $column)) {
                $selects[] = $column;
            }
        }
        if ($selects === []) {
            return [];
        }

        $orderBy = [];
        foreach ($orderColumns as $column) {
            if ($this->tableColumnExists($table, $column)) {
                $orderBy[] = $column . ' ASC';
            }
        }

        $where = $this->tableColumnExists($table, 'deleted_at') ? 'WHERE deleted_at IS NULL' : '';
        if ($this->tableColumnExists($table, 'is_active')) {
            $where .= ($where === '' ? 'WHERE' : ' AND') . ' COALESCE(is_active, 1) = 1';
        }

        try {
            $stmt = $this->pdo->query(
                'SELECT ' . implode(', ', $selects)
                . ' FROM ' . $table . ' '
                . $where
                . ($orderBy !== [] ? ' ORDER BY ' . implode(', ', $orderBy) : '')
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }

        $options = [];
        foreach ($rows as $row) {
            $label = '';
            foreach ($selects as $column) {
                $value = trim((string) ($row[$column] ?? ''));
                if ($value !== '') {
                    $label = $value;
                    break;
                }
            }
            if ($label !== '') {
                $options[] = $label;
            }
        }

        return array_values(array_unique($options));
    }

    private function isWorkbookTemplateSpec(array $headers): bool
    {
        return isset($headers[0]) && is_array($headers[0]) && array_key_exists('headers', $headers[0]);
    }

    private function looksLikeBankTemplateHeaders(array $headers): bool
    {
        $names = array_map(
            static fn(mixed $header): string => preg_replace('/\s*\*$/u', '', trim((string) $header)) ?? trim((string) $header),
            $headers
        );
        $nameSet = array_flip($names);

        foreach (['입금액', '출금액', '입금', '출금', '거래후잔액', '잔액', '상대계좌예금주명', '상대계좌번호', '은행거래번호'] as $header) {
            if (isset($nameSet[$header])) {
                return true;
            }
        }

        return isset($nameSet['계좌명']) && (isset($nameSet['거래구분']) || isset($nameSet['입출구분']));
    }

    private function fillTemplateSheet(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $title,
        array $headers,
        array $samples,
        array $required = [],
        array $fields = [],
        string $dataType = ''
    ): void
    {
        $sheet->setTitle(self::safeSheetTitle($title));
        foreach (array_values($headers) as $index => $header) {
            $cell = Coordinate::stringFromColumnIndex($index + 1) . '1';
            $header = (string) $header;
            $requirementMode = self::normalizeRequirementMode($required[$index] ?? 0);
            if ($requirementMode !== 0 && $header !== '') {
                $richText = new RichText();
                $richText->createText($header . ' ');
                $asterisk = $richText->createTextRun('*');
                $asterisk->getFont()->setBold(true)->getColor()->setARGB($requirementMode === 1 ? 'FFDC2626' : 'FF2563EB');
                $sheet->setCellValue($cell, $richText);
            } else {
                $sheet->setCellValue($cell, $header);
            }
        }
        if ($samples !== []) {
            $sheet->fromArray($samples, null, 'A2');
        }

        $lastColumn = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFEFF6FF');

        foreach (array_values($headers) as $index => $header) {
            $field = (string) ($fields[$index] ?? '');
            $cell = Coordinate::stringFromColumnIndex($index + 1) . '1';
            if ($this->isStandardInfoTemplateColumn($field, (string) $header, $dataType)) {
                $sheet->getStyle($cell)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB('FFF3E8FF');
                continue;
            }
            if ($this->isBasicInfoTemplateColumn($field, (string) $header, $dataType)) {
                $sheet->getStyle($cell)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB('FFE2F0D9');
                continue;
            }
            if ($this->isVoucherTemplateColumn($field, (string) $header, $dataType)) {
                $sheet->getStyle($cell)->getFont()->getColor()->setARGB('FFB91C1C');
            }
        }
        $lastColumnIndex = Coordinate::columnIndexFromString($lastColumn);
        for ($columnIndex = 1; $columnIndex <= $lastColumnIndex; $columnIndex++) {
            $column = Coordinate::stringFromColumnIndex($columnIndex);
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->freezePane('A2');
    }

    private function referenceTemplateFieldSet(string $dataType): array
    {
        $fields = [];
        foreach ($this->systemFieldOptionsByValue($dataType) as $option) {
            $value = trim((string) ($option['value'] ?? ''));
            if ($value !== '' && $this->templateDropdownListKey($option) !== '') {
                $fields[$value] = true;
            }
        }

        return $fields;
    }

    private function isReferenceTemplateColumn(string $field, string $dataType): bool
    {
        $field = trim($field);
        if ($field === '') {
            return false;
        }

        $option = $this->systemFieldOptionsByValue($dataType)[$field] ?? null;

        return is_array($option) && $this->templateDropdownListKey($option) !== '';
    }

    private function templateFieldGroup(string $field, string $dataType): string
    {
        $field = trim($field);
        if ($field === '') {
            return '';
        }

        $option = $this->systemFieldOptionsByValue($dataType)[$field] ?? null;

        return is_array($option) ? (string) ($option['group'] ?? '') : '';
    }

    private function isStandardInfoTemplateColumn(string $field, string $header, string $dataType): bool
    {
        $group = $this->templateFieldGroup($field, $dataType);
        if (str_contains($group, '기준정보')) {
            return true;
        }
        if ($group !== '') {
            return false;
        }

        return in_array(trim($header), ['표준일자', '사업구분', '거래유형', '통화', '환율'], true);
    }

    private function isBasicInfoTemplateColumn(string $field, string $header, string $dataType = ''): bool
    {
        $field = trim($field);
        $group = $this->templateFieldGroup($field, $dataType);
        if (str_contains($group, '기초정보')) {
            return true;
        }
        if ($group !== '') {
            return false;
        }
        if (in_array($field, [
            'client_name',
            'project_name',
            'employee_name',
            'bank_account_name',
            'card_name',
        ], true)) {
            return true;
        }

        return in_array(trim($header), ['거래처명', '거래처', '프로젝트명', '프로젝트', '직원명', '직원', '계좌명', '계좌', '카드명', '카드'], true);
    }

    private function isVoucherTemplateColumn(string $field, string $header, string $dataType = ''): bool
    {
        $field = trim($field);
        if (self::normalizeDataType($dataType) === 'CARD_HOMETAX' && $field === 'note') {
            return false;
        }
        if (in_array($field, [
            'voucher_date',
            'voucher_no',
            'summary_text',
            'note',
            'voucher_memo',
            'header_row_no',
            'line_no',
            'line_row_type',
            'account_id',
            'debit',
            'credit',
            'line_summary',
            'line_ref_type',
            'line_ref_id',
        ], true)) {
            return true;
        }

        return in_array(trim($header), ['전표일자', '전표번호', '전표적요', '전표비고', '전표메모', '헤더행번호', '분개라인번호', '행타입', '계정', '차변금액', '대변금액', '라인적요'], true);
    }

    private static function clearOutputBuffers(): void
    {
        if (ob_get_length()) {
            @ob_end_clean();
        }

        while (ob_get_level() > 0) {
            if (!@ob_end_clean()) {
                break;
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
            'data' => $this->systemFieldService()->fieldOptions($dataType),
            'target_table' => $this->systemFieldService()->targetTableForDataType($dataType),
        ]);
    }

    public function apiFormats(): void
    {
        $dataType = self::normalizeDataType((string) ($_GET['data_type'] ?? ''));
        $sql = 'SELECT * FROM ledger_data_formats WHERE deleted_at IS NULL';
        $params = [];
        if ($dataType !== '') {
            if (!$this->isAllowedDataType($dataType)) {
                $this->json(['success' => true, 'data' => []]);
                return;
            }
            $types = self::queryDataTypes($dataType);
            $placeholders = [];
            foreach ($types as $index => $type) {
                $key = ':data_type_' . $index;
                $placeholders[] = $key;
                $params[$key] = $type;
            }
            $sql .= ' AND data_type IN (' . implode(', ', $placeholders) . ')';
        } else {
            $allowedTypes = $this->allowedDataTypes();
            if ($allowedTypes !== []) {
                $placeholders = [];
                foreach ($allowedTypes as $index => $type) {
                    $key = ':allowed_type_' . $index;
                    $placeholders[] = $key;
                    $params[$key] = $type;
                }
                $sql .= ' AND data_type IN (' . implode(', ', $placeholders) . ')';
            }
        }
        $sql .= ' ORDER BY data_type ASC, is_default DESC, format_name ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['data_type'] = self::normalizeDataType((string) ($row['data_type'] ?? ''));
        }
        unset($row);

        $includeColumns = (string) ($_GET['include_columns'] ?? '') === '1';
        if ($includeColumns) {
            foreach ($rows as &$row) {
                $row['columns'] = $this->columns((string) $row['id']);
            }
            unset($row);
        }

        $this->json(['success' => true, 'data' => $rows]);
    }

    public function apiFormatDetail(): void
    {
        $id = trim((string) ($_GET['id'] ?? ''));
        if ($id === '') {
            $this->json(['success' => false, 'message' => '양식 ID가 없습니다.'], 400);
            return;
        }

        $format = $this->format($id);
        if (!$format) {
            $this->json(['success' => false, 'message' => '양식을 찾을 수 없습니다.'], 404);
            return;
        }
        $dataType = self::normalizeDataType((string) ($format['data_type'] ?? ''));
        if (!$this->isAllowedDataType($dataType)) {
            $this->json(['success' => false, 'message' => '자료업로드는 외부 증빙 원본 자료유형만 사용할 수 있습니다.'], 400);
            return;
        }
        $format['columns'] = $this->columns($id);
        $this->json(['success' => true, 'data' => $format]);
    }

    public function apiFormatSave(): void
    {
        $payload = $this->requestPayload();
        $id = trim((string) ($payload['id'] ?? ''));
        $formatName = trim((string) ($payload['format_name'] ?? ''));
        $dataType = self::normalizeDataType((string) ($payload['data_type'] ?? ''));
        $isDefault = !empty($payload['is_default']) ? 1 : 0;
        $columns = is_array($payload['columns'] ?? null) ? $payload['columns'] : [];

        if ($formatName === '' || !$this->isAllowedDataType($dataType)) {
            $this->json(['success' => false, 'message' => '양식명과 자료 유형을 입력하세요.'], 400);
            return;
        }

        $normalizedColumns = $this->normalizeColumns($columns, $dataType);
        if ($normalizedColumns === []) {
            $this->json(['success' => false, 'message' => '컬럼 매핑을 1개 이상 입력하세요.'], 400);
            return;
        }

        $actor = ActorHelper::user();
        try {
            $hasVisibleColumn = $this->ensureFormatColumnVisibleColumn();
            $this->pdo->beginTransaction();
            if ($isDefault === 1) {
                $defaultTypes = self::queryDataTypes($dataType);
                $placeholders = [];
                $params = [];
                foreach ($defaultTypes as $index => $type) {
                    $key = ':data_type_' . $index;
                    $placeholders[] = $key;
                    $params[$key] = $type;
                }
                $this->pdo->prepare('UPDATE ledger_data_formats SET is_default = 0 WHERE deleted_at IS NULL AND data_type IN (' . implode(', ', $placeholders) . ')')
                    ->execute($params);
            }

            if ($id === '') {
                $id = UuidHelper::generate();
                $stmt = $this->pdo->prepare("
                    INSERT INTO ledger_data_formats (id, format_name, data_type, is_default, created_by)
                    VALUES (:id, :format_name, :data_type, :is_default, :created_by)
                ");
                $stmt->execute([
                    ':id' => $id,
                    ':format_name' => $formatName,
                    ':data_type' => $dataType,
                    ':is_default' => $isDefault,
                    ':created_by' => $actor,
                ]);
            } else {
                $currentFormat = $this->format($id);
                if (!$currentFormat) {
                    $this->pdo->rollBack();
                    $this->json(['success' => false, 'message' => json_decode('"\uC218\uC815\uD560 \uC591\uC2DD\uC744 \uCC3E\uC744 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4."')], 404);
                    return;
                }

                $stmt = $this->pdo->prepare("
                    UPDATE ledger_data_formats
                    SET format_name = :format_name,
                        data_type = :data_type,
                        is_default = :is_default
                    WHERE id = :id
                      AND deleted_at IS NULL
                ");
                $stmt->execute([
                    ':id' => $id,
                    ':format_name' => $formatName,
                    ':data_type' => $dataType,
                    ':is_default' => $isDefault,
                ]);
                $this->pdo->prepare('DELETE FROM ledger_data_format_columns WHERE format_id = :format_id')
                    ->execute([':format_id' => $id]);
            }

            $insert = $this->pdo->prepare($hasVisibleColumn ? "
                INSERT INTO ledger_data_format_columns
                    (id, format_id, excel_column_name, excel_column_index, system_field_name, column_order, is_visible, is_required)
                VALUES
                    (:id, :format_id, :excel_column_name, :excel_column_index, :system_field_name, :column_order, :is_visible, :is_required)
            " : "
                INSERT INTO ledger_data_format_columns
                    (id, format_id, excel_column_name, excel_column_index, system_field_name, column_order, is_required)
                VALUES
                    (:id, :format_id, :excel_column_name, :excel_column_index, :system_field_name, :column_order, :is_required)
            ");
            foreach ($normalizedColumns as $column) {
                $params = [
                    ':id' => UuidHelper::generate(),
                    ':format_id' => $id,
                    ':excel_column_name' => $column['excel_column_name'],
                    ':excel_column_index' => $column['excel_column_index'],
                    ':system_field_name' => $column['system_field_name'],
                    ':column_order' => $column['column_order'],
                    ':is_required' => $column['is_required'],
                ];
                if ($hasVisibleColumn) {
                    $params[':is_visible'] = $column['is_visible'];
                }
                $insert->execute($params);
            }

            $this->pdo->commit();
            $this->json(['success' => true, 'id' => $id, 'message' => '양식이 저장되었습니다.']);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function apiFormatDelete(): void
    {
        $id = trim((string) ($this->requestPayload()['id'] ?? ''));
        if ($id === '') {
            $this->json(['success' => false, 'message' => json_decode('"\uC591\uC2DD ID\uAC00 \uC5C6\uC2B5\uB2C8\uB2E4."')], 400);
            return;
        }

        $format = $this->format($id);
        if (!$format) {
            $this->json(['success' => false, 'message' => json_decode('"\uC591\uC2DD\uC744 \uCC3E\uC744 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4."')], 404);
            return;
        }

        $stmt = $this->pdo->prepare("
            UPDATE ledger_data_formats
            SET deleted_at = NOW(),
                deleted_by = :deleted_by
            WHERE id = :id
              AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':id' => $id,
            ':deleted_by' => ActorHelper::user(),
        ]);

        $this->json(['success' => true, 'message' => json_decode('"\uC591\uC2DD\uC774 \uC0AD\uC81C\uB418\uC5C8\uC2B5\uB2C8\uB2E4."')]);
    }

    public function apiFormatTrashList(): void
    {
        $stmt = $this->pdo->query("
            SELECT
                id,
                format_name,
                data_type,
                is_default,
                created_by,
                deleted_at,
                deleted_by
            FROM ledger_data_formats
            WHERE deleted_at IS NOT NULL
            ORDER BY deleted_at DESC, format_name ASC
        ");

        $this->json(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]);
    }

    public function apiFormatRestore(): void
    {
        $ids = $this->formatIdsFromPayload($this->requestPayload());
        if ($ids === []) {
            $this->json(['success' => false, 'message' => json_decode('"\uBCF5\uC6D0\uD560 \uC591\uC2DD\uC744 \uC120\uD0DD\uD558\uC138\uC694."')], 400);
            return;
        }

        [$inSql, $params] = $this->placeholdersForIds($ids, 'format_id');
        $stmt = $this->pdo->prepare("
            UPDATE ledger_data_formats
            SET deleted_at = NULL,
                deleted_by = NULL
            WHERE id IN ({$inSql})
              AND deleted_at IS NOT NULL
        ");
        $stmt->execute($params);

        $this->json(['success' => true, 'message' => json_decode('"\uC591\uC2DD\uC774 \uBCF5\uC6D0\uB418\uC5C8\uC2B5\uB2C8\uB2E4."')]);
    }

    public function apiFormatRestoreAll(): void
    {
        $this->pdo->exec("
            UPDATE ledger_data_formats
            SET deleted_at = NULL,
                deleted_by = NULL
            WHERE deleted_at IS NOT NULL
        ");

        $this->json(['success' => true, 'message' => json_decode('"\uC804\uCCB4 \uC591\uC2DD\uC774 \uBCF5\uC6D0\uB418\uC5C8\uC2B5\uB2C8\uB2E4."')]);
    }

    public function apiFormatPurge(): void
    {
        $ids = $this->formatIdsFromPayload($this->requestPayload());
        if ($ids === []) {
            $this->json(['success' => false, 'message' => json_decode('"\uC601\uAD6C\uC0AD\uC81C\uD560 \uC591\uC2DD\uC744 \uC120\uD0DD\uD558\uC138\uC694."')], 400);
            return;
        }

        [$inSql, $params] = $this->placeholdersForIds($ids, 'format_id');
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("DELETE FROM ledger_data_format_columns WHERE format_id IN ({$inSql})")->execute($params);
            $this->pdo->prepare("DELETE FROM ledger_data_formats WHERE id IN ({$inSql}) AND deleted_at IS NOT NULL")->execute($params);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
            return;
        }

        $this->json(['success' => true, 'message' => json_decode('"\uC591\uC2DD\uC774 \uC601\uAD6C\uC0AD\uC81C\uB418\uC5C8\uC2B5\uB2C8\uB2E4."')]);
    }

    public function apiFormatPurgeAll(): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec("
                DELETE c
                FROM ledger_data_format_columns c
                INNER JOIN ledger_data_formats f ON f.id = c.format_id
                WHERE f.deleted_at IS NOT NULL
            ");
            $this->pdo->exec("DELETE FROM ledger_data_formats WHERE deleted_at IS NOT NULL");
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
            return;
        }

        $this->json(['success' => true, 'message' => json_decode('"\uC804\uCCB4 \uC591\uC2DD\uC774 \uC601\uAD6C\uC0AD\uC81C\uB418\uC5C8\uC2B5\uB2C8\uB2E4."')]);
    }

    public function apiFormatCopy(): void
    {
        $id = trim((string) ($this->requestPayload()['id'] ?? ''));
        $format = $this->formatWithColumns($id);
        if (!$format) {
            $this->json(['success' => false, 'message' => '복사할 양식을 찾을 수 없습니다.'], 404);
            return;
        }

        $newId = UuidHelper::generate();
        $actor = ActorHelper::user();

        try {
            $hasVisibleColumn = $this->ensureFormatColumnVisibleColumn();
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("
                INSERT INTO ledger_data_formats (id, format_name, data_type, is_default, created_by)
                VALUES (:id, :format_name, :data_type, 0, :created_by)
            ");
            $stmt->execute([
                ':id' => $newId,
                ':format_name' => '복사본 - ' . (string) $format['format_name'],
                ':data_type' => (string) $format['data_type'],
                ':created_by' => $actor,
            ]);

            $insert = $this->pdo->prepare($hasVisibleColumn ? "
                INSERT INTO ledger_data_format_columns
                    (id, format_id, excel_column_name, excel_column_index, system_field_name, column_order, is_visible, is_required)
                VALUES
                    (:id, :format_id, :excel_column_name, :excel_column_index, :system_field_name, :column_order, :is_visible, :is_required)
            " : "
                INSERT INTO ledger_data_format_columns
                    (id, format_id, excel_column_name, excel_column_index, system_field_name, column_order, is_required)
                VALUES
                    (:id, :format_id, :excel_column_name, :excel_column_index, :system_field_name, :column_order, :is_required)
            ");
            foreach ($format['columns'] as $column) {
                $params = [
                    ':id' => UuidHelper::generate(),
                    ':format_id' => $newId,
                    ':excel_column_name' => (string) $column['excel_column_name'],
                    ':excel_column_index' => isset($column['excel_column_index']) ? (int) $column['excel_column_index'] : (int) $column['column_order'],
                    ':system_field_name' => $column['system_field_name'] !== null ? (string) $column['system_field_name'] : null,
                    ':column_order' => (int) $column['column_order'],
                    ':is_required' => (int) $column['is_required'],
                ];
                if ($hasVisibleColumn) {
                    $params[':is_visible'] = (int) ($column['is_visible'] ?? 1);
                }
                $insert->execute($params);
            }

            $this->pdo->commit();
            $this->json(['success' => true, 'id' => $newId, 'message' => '양식이 복사되었습니다.']);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function apiPreview(): void
    {
        $this->prepareLargeUploadRuntime();
        \Core\Session::write();

        $formatId = trim((string) ($_POST['format_id'] ?? ''));
        $format = $this->formatWithColumns($formatId);
        if (!$format || empty($_FILES['file'])) {
            $this->json(['success' => false, 'message' => '양식과 파일을 선택하세요.'], 400);
            return;
        }

        try {
            $dataType = self::normalizeDataType((string) ($format['data_type'] ?? 'ETC'));
            if (!$this->isAllowedDataType($dataType)) {
                throw new \RuntimeException('자료업로드는 외부 증빙 원본 자료유형만 사용할 수 있습니다.');
            }
            $checks = $this->validateUploadFileColumns($_FILES['file'], $format['columns']);
            $this->assertUploadFileMatchesFormat($checks, $format['columns']);
            $checkErrors = array_values(array_filter($checks, static fn(array $check): bool => ($check['level'] ?? '') === 'error'));
            if ($checkErrors !== []) {
                throw new \RuntimeException('업로드 파일의 헤더가 선택한 양식 컬럼과 일치하지 않습니다. ' . (string) ($checkErrors[0]['message'] ?? ''));
            }
            $rows = $this->parseUploadedRows($_FILES['file'], $format['columns']);
            if ($rows === []) {
                throw new \RuntimeException('업로드할 데이터 행이 없습니다. 선택한 시트의 2행부터 데이터를 입력했는지 확인하세요.');
            }
            $rows = $this->enrichUploadRows($rows, $dataType);
            $rows = $this->validatePreviewRows($rows, $format['columns'], $dataType);
            $rows = $this->annotateSeedComparison($rows, $dataType);
            $token = $this->storeUploadPreviewSession($format, $_FILES['file'], $rows);
            $summary = $this->uploadValidationSummary($rows);
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

    public function apiSeedUpload(): void
    {
        $this->prepareLargeUploadRuntime();
        \Core\Session::write();
        $cancelToken = !empty($_FILES['file']) ? $this->uploadCancelTokenFromPayload($_POST) : '';
        $startedAt = microtime(true);
        $this->uploadTrace('start', [
            'token' => $cancelToken,
            'mode' => !empty($_FILES['file']) ? 'file' : 'preview',
            'file' => (string) ($_FILES['file']['name'] ?? ''),
            'size' => (string) ($_FILES['file']['size'] ?? ''),
        ]);

        if (!empty($_FILES['file'])) {
            $formatId = trim((string) ($_POST['format_id'] ?? ''));
            $format = $this->formatWithColumns($formatId);
            if (!$format) {
                $this->json(['success' => false, 'message' => '업로드할 자료유형 양식을 찾을 수 없습니다.'], 400);
                return;
            }

            try {
                $dataType = self::normalizeDataType((string) ($format['data_type'] ?? 'ETC'));
                if (!$this->isAllowedDataType($dataType)) {
                    throw new \RuntimeException('자료업로드는 외부 증빙 원본 자료유형만 사용할 수 있습니다.');
                }
                $this->assertUploadNotCanceled($cancelToken);
                $stageStartedAt = microtime(true);
                $checks = $this->validateUploadFileColumns($_FILES['file'], $format['columns']);
                $this->uploadTrace('validated_columns', [
                    'token' => $cancelToken,
                    'elapsed_ms' => (int) round((microtime(true) - $stageStartedAt) * 1000),
                ]);
                $this->assertUploadFileMatchesFormat($checks, $format['columns']);
                $checkErrors = array_values(array_filter($checks, static fn(array $check): bool => ($check['level'] ?? '') === 'error'));
                if ($checkErrors !== []) {
                    throw new \RuntimeException('업로드 파일의 헤더가 선택한 양식 컬럼과 일치하지 않습니다. ' . (string) ($checkErrors[0]['message'] ?? ''));
                }

                $this->assertUploadNotCanceled($cancelToken);
                $stageStartedAt = microtime(true);
                $rows = $this->parseUploadedRows($_FILES['file'], $format['columns']);
                $this->uploadTrace('parsed_rows', [
                    'token' => $cancelToken,
                    'rows' => count($rows),
                    'elapsed_ms' => (int) round((microtime(true) - $stageStartedAt) * 1000),
                ]);
                if ($rows === []) {
                    throw new \RuntimeException('업로드할 데이터 행이 없습니다. 선택한 시트의 2행부터 데이터를 입력했는지 확인하세요.');
                }
                $this->assertUploadNotCanceled($cancelToken);
                $stageStartedAt = microtime(true);
                $rows = $this->enrichUploadRows($rows, $dataType);
                $rows = $this->validatePreviewRows($rows, $format['columns'], $dataType);
                $rows = $this->annotateSeedComparison($rows, $dataType);
                $this->uploadTrace('prepared_rows', [
                    'token' => $cancelToken,
                    'rows' => count($rows),
                    'elapsed_ms' => (int) round((microtime(true) - $stageStartedAt) * 1000),
                ]);
                $this->assertNoUploadValidationErrors($rows);
                $requiredMissing = $this->uploadRequiredMissingSummary($rows);
                if (!$this->uploadAllowsRequiredMissing($_POST) && $requiredMissing['items'] > 0) {
                    $token = $this->storeUploadPreviewSession($format, $_FILES['file'], $rows);
                    $this->uploadTrace('requires_confirmation', [
                        'token' => $cancelToken,
                        'rows' => count($rows),
                        'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    ]);
                    $this->json([
                        'success' => false,
                        'requires_confirmation' => true,
                        'confirmation_code' => 'REQUIRED_FIELD_MISSING',
                        'message' => $this->uploadRequiredMissingConfirmationMessage($requiredMissing),
                        'data' => [
                            'preview_token' => $token,
                            'total_rows' => count($rows),
                            'required_missing' => $requiredMissing,
                        ],
                    ]);
                    return;
                }
                $stageStartedAt = microtime(true);
                $result = $this->storeUploadBatch($format, $_FILES['file'], $rows, $cancelToken);
                $this->uploadTrace('stored_rows', [
                    'token' => $cancelToken,
                    'rows' => count($rows),
                    'elapsed_ms' => (int) round((microtime(true) - $stageStartedAt) * 1000),
                    'total_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);
                $this->clearUploadCancelToken($cancelToken);
                $this->json(['success' => true, 'data' => $result, 'checks' => $checks, 'message' => '업로드가 완료되었습니다.']);
            } catch (\Throwable $e) {
                $this->uploadTrace('failed', [
                    'token' => $cancelToken,
                    'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'message' => $e->getMessage(),
                ]);
                $this->json(['success' => false, 'message' => $e->getMessage()], 400);
            }
            return;
        }

        $this->uploadTrace('preview_payload_reading', [
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
        $payload = $this->requestPayload();
        $cancelToken = $this->uploadCancelTokenFromPayload($payload);
        $this->uploadTrace('preview_payload_loaded', [
            'token' => $cancelToken,
            'preview_token' => trim((string) ($payload['preview_token'] ?? '')),
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
        $token = trim((string) ($payload['preview_token'] ?? ''));
        $this->uploadTrace('preview_session_loading', [
            'token' => $cancelToken,
            'preview_token' => $token,
        ]);
        $preview = $this->uploadPreviewFromSession($token);
        $this->uploadTrace('preview_session_loaded', [
            'token' => $cancelToken,
            'preview_token' => $token,
            'found' => $preview ? 1 : 0,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
        if (!$preview) {
            $this->json(['success' => false, 'message' => '검증 결과가 없습니다. 먼저 검증을 실행하세요.'], 400);
            return;
        }

        try {
            $this->assertUploadNotCanceled($cancelToken);
            $previewFile = is_array($preview['file'] ?? null) ? $preview['file'] : [];
            if (trim((string) ($previewFile['tmp_name'] ?? '')) === '' || !is_file((string) $previewFile['tmp_name'])) {
                throw new \RuntimeException('업로드 임시 파일이 없습니다. 다시 검증 후 업로드하세요.');
            }
            $dataType = self::normalizeDataType((string) ($preview['format']['data_type'] ?? 'ETC'));
            $rows = is_array($preview['rows'] ?? null) ? $preview['rows'] : [];
            if ($rows === []) {
                $rows = $this->parseUploadedRows($previewFile, $preview['format']['columns']);
                $rows = $this->enrichUploadRows($rows, $dataType);
                $rows = $this->validatePreviewRows($rows, $preview['format']['columns'], $dataType);
                $rows = $this->annotateSeedComparison($rows, $dataType);
            }
            $this->assertNoUploadValidationErrors($rows);
            $requiredMissing = $this->uploadRequiredMissingSummary($rows);
            if (!$this->uploadAllowsRequiredMissing($payload) && $requiredMissing['items'] > 0) {
                $this->json([
                    'success' => false,
                    'requires_confirmation' => true,
                    'confirmation_code' => 'REQUIRED_FIELD_MISSING',
                    'message' => $this->uploadRequiredMissingConfirmationMessage($requiredMissing),
                    'data' => ['required_missing' => $requiredMissing],
                ]);
                return;
            }
            $preview['file'] = $previewFile;
            $preview['rows'] = $rows;
            if (($preview['rows'] ?? []) === []) {
                throw new \RuntimeException('업로드할 데이터 행이 없습니다. 다시 검증 후 업로드하세요.');
            }
            $totalRows = count($preview['rows']);
            $isChunked = !empty($payload['chunked_upload']) || isset($payload['chunk_offset']) || isset($payload['chunk_size']);
            if ($isChunked) {
                $offset = max(0, (int) ($payload['chunk_offset'] ?? 0));
                $chunkSize = max(1, min(50, (int) ($payload['chunk_size'] ?? 5)));
                if ($offset >= $totalRows) {
                    $this->clearUploadPreviewSession($token);
                    $this->clearUploadCancelToken($cancelToken);
                    $this->json(['success' => true, 'data' => [
                        'total_rows' => $totalRows,
                        'processed_count' => 0,
                        'new_count' => 0,
                        'updated_count' => 0,
                        'unchanged_count' => 0,
                        'error_count' => 0,
                        'skipped_count' => 0,
                        'chunk_offset' => $offset,
                        'next_offset' => $totalRows,
                        'done' => true,
                    ], 'message' => 'Seed upload completed.']);
                    return;
                }

                $chunkRows = array_slice($preview['rows'], $offset, $chunkSize);
                $stageStartedAt = microtime(true);
                $result = $this->storeUploadBatch($preview['format'], $preview['file'], $chunkRows, $cancelToken);
                $nextOffset = min($totalRows, $offset + count($chunkRows));
                $done = $nextOffset >= $totalRows;
                $result['total_rows'] = $totalRows;
                $result['chunk_rows'] = count($chunkRows);
                $result['chunk_offset'] = $offset;
                $result['next_offset'] = $nextOffset;
                $result['done'] = $done;
                $this->uploadTrace('stored_preview_chunk', [
                    'token' => $cancelToken,
                    'offset' => $offset,
                    'next' => $nextOffset,
                    'total' => $totalRows,
                    'elapsed_ms' => (int) round((microtime(true) - $stageStartedAt) * 1000),
                    'total_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);
                if ($done) {
                    $this->clearUploadPreviewSession($token);
                    $this->clearUploadCancelToken($cancelToken);
                }
                $this->json(['success' => true, 'data' => $result, 'message' => $done ? 'Seed upload completed.' : 'Upload chunk saved.']);
                return;
            }
            $stageStartedAt = microtime(true);
            $result = $this->storeUploadBatch($preview['format'], $preview['file'], $preview['rows'], $cancelToken);
            $this->uploadTrace('stored_preview_rows', [
                'token' => $cancelToken,
                'rows' => count($preview['rows']),
                'elapsed_ms' => (int) round((microtime(true) - $stageStartedAt) * 1000),
                'total_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
            $this->clearUploadPreviewSession($token);
            $this->clearUploadCancelToken($cancelToken);
            $this->json(['success' => true, 'data' => $result, 'message' => 'Seed 업로드가 완료되었습니다.']);
        } catch (\Throwable $e) {
            $this->uploadTrace('failed_preview', [
                'token' => $cancelToken,
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'message' => $e->getMessage(),
            ]);
            $this->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function apiSeedUploadCancel(): void
    {
        \Core\Session::write();
        $payload = $this->requestPayload();
        $cancelToken = $this->uploadCancelTokenFromPayload($payload);
        if ($cancelToken === '') {
            $this->json(['success' => false, 'message' => '취소할 업로드 토큰이 없습니다.'], 400);
            return;
        }

        $this->markUploadCanceled($cancelToken);
        $this->uploadTrace('cancel_requested', ['token' => $cancelToken]);
        $previewToken = trim((string) ($payload['preview_token'] ?? ''));
        if ($previewToken !== '') {
            $this->clearUploadPreviewSession($previewToken);
        }

        $this->json(['success' => true, 'message' => '업로드 취소 요청을 접수했습니다.']);
    }

    public function apiCreateTransactions(): void
    {
        $payload = $this->requestPayload();
        $batchId = trim((string) ($payload['batch_id'] ?? ''));
        $rowIds = is_array($payload['seed_row_ids'] ?? null)
            ? array_values(array_filter(array_map('strval', $payload['seed_row_ids'])))
            : (is_array($payload['row_ids'] ?? null) ? array_values(array_filter(array_map('strval', $payload['row_ids']))) : []);
        $confirmExistingVoucher = !empty($payload['confirm_existing_voucher']);
        error_log('[ImportController] apiCreateTransactions entered batch_id=' . ($batchId !== '' ? $batchId : '(empty)') . ' seed_rows=' . count($rowIds));

        if ($batchId === '' && $rowIds === []) {
            $this->json(['success' => false, 'message' => '거래 생성할 Seed Data를 선택하세요.'], 400);
            return;
        }

        $batch = null;
        if ($batchId !== '') {
            $batch = $this->uploadBatch($batchId);
            if (!$batch) {
                $this->json(['success' => false, 'message' => 'Seed 배치를 찾을 수 없습니다.'], 404);
                return;
            }
        }

        if (!empty($payload['bundled_voucher'])) {
            $result = $this->createBundledVoucherFromEvidenceRows($rowIds);
            $this->json($result, !empty($result['success']) ? 200 : 422);
            return;
        }

        $dataType = self::normalizeDataType((string) ($batch['data_type'] ?? 'TAX_INVOICE'));
        $existingVoucherRows = $rowIds !== [] ? $this->existingVoucherRowsForEvidenceIds($rowIds) : [];
        if ($existingVoucherRows !== [] && !$confirmExistingVoucher) {
            $this->json([
                'success' => false,
                'requires_confirmation' => true,
                'confirmation_code' => 'EXISTING_VOUCHER',
                'message' => '이미 같은 유형의 전표가 생성되어 있습니다. 기존 전표를 연결할까요?',
                'existing_vouchers' => $existingVoucherRows,
            ], 409);
            return;
        }

        $linkedExistingVoucherCount = $confirmExistingVoucher
            ? $this->linkExistingVouchersForEvidenceRows($existingVoucherRows)
            : 0;
        $rows = $this->claimSeedRowsForTransactionCreate($batchId, $rowIds);
        error_log('[ImportController] transaction target rows=' . count($rows) . ' batch_id=' . $batchId);

        if ($rows === []) {
            if ($linkedExistingVoucherCount > 0) {
                $this->json([
                    'success' => true,
                    'batch_id' => $batchId !== '' ? $batchId : null,
                    'target_count' => count($rowIds),
                    'created_count' => 0,
                    'duplicate_count' => 0,
                    'error_count' => 0,
                    'transaction_line_count' => 0,
                    'success_count' => $linkedExistingVoucherCount,
                    'processed_ids' => array_values(array_unique(array_column($existingVoucherRows, 'evidence_id'))),
                    'error_ids' => [],
                    'updated_status' => [],
                    'errors' => [],
                    'message' => '기존 전표 연결이 완료되었습니다.',
                ]);
                return;
            }
            $this->json([
                'success' => false,
                'batch_id' => $batchId !== '' ? $batchId : null,
                'target_count' => 0,
                'created_count' => 0,
                'duplicate_count' => 0,
                'error_count' => 0,
                'transaction_line_count' => 0,
                'success_count' => 0,
                'processed_ids' => [],
                'error_ids' => [],
                'updated_status' => [],
                'errors' => [],
                'message' => '거래 생성 가능한 READY Seed Data가 없습니다.',
            ], 400);
            return;
        }

        $created = 0;
        $duplicates = 0;
        $errors = [];
        $createdTransactionIds = [];
        $processedIds = [];
        $errorIds = [];
        $updatedStatus = [];

        foreach ($rows as $row) {
            $rowId = (string) ($row['id'] ?? '');
            $rowNo = (int) ($row['row_no'] ?? 0);
            $mapped = $this->decodeMappedPayload($row['mapped_payload'] ?? null);

            if (!is_array($mapped)) {
                $message = '매핑 데이터 JSON을 읽을 수 없습니다.';
                    $this->updateUploadRowStatus($rowId, 'ERROR', $message);
                $errorIds[] = $rowId;
                $updatedStatus[$rowId] = 'ERROR';
                $errors[] = ['row_id' => $rowId, 'row' => $rowNo, 'message' => $message];
                continue;
            }

            try {
                $rowDataType = self::normalizeDataType((string) ($row['source_type'] ?? $dataType));
                if (!self::isTransactionProcessingType($rowDataType) && $rowDataType !== 'BANK_TRANSACTION') {
                    $plan = self::processingPlanForDataType($rowDataType);
                    $message = '이 자료유형은 거래 생성 대상이 아닙니다. 처리대상: ' . $plan['label'];
                    $this->updateUploadRowStatus($rowId, 'ERROR', $message);
                    $errorIds[] = $rowId;
                    $updatedStatus[$rowId] = 'ERROR';
                    $errors[] = ['row_id' => $rowId, 'row' => $rowNo, 'message' => $message];
                    continue;
                }
                $readiness = $this->readinessForEvidenceRow([
                    'source_type' => $rowDataType,
                    'import_type' => $rowDataType,
                    'source_key' => $row['source_key'] ?? '',
                    'evidence_date' => $row['evidence_date'] ?? '',
                ], $mapped);
                if (($readiness['status'] ?? '') !== 'READY') {
                    $message = '생성 준비 검증 미통과: ' . implode(' / ', $readiness['errors'] ?? []);
                    $this->updateUploadRowStatus($rowId, 'ERROR', $message);
                    $errorIds[] = $rowId;
                    $updatedStatus[$rowId] = 'ERROR';
                    $errors[] = ['row_id' => $rowId, 'row' => $rowNo, 'message' => $message, 'missing_fields' => $readiness['missing_fields'] ?? []];
                    continue;
                }
                if ($rowDataType === 'BANK_TRANSACTION') {
                    $voucherId = $this->createVoucherFromBankPayload($rowId, $mapped, '', $confirmExistingVoucher);
                    $this->resetBankEvidenceTransactionClaim($rowId, ActorHelper::user());
                    if ($voucherId !== null) {
                        $created++;
                        $processedIds[] = $rowId;
                        $updatedStatus[$rowId] = 'PROCESSED';
                        continue;
                    }

                    $message = '?꾪몴 ?앹꽦 ?먮뒗 湲곗〈 ?꾪몴 ?곌껐???ㅽ뙣?덉뒿?덈떎.';
                    $errorIds[] = $rowId;
                    $updatedStatus[$rowId] = 'ERROR';
                    $errors[] = ['row_id' => $rowId, 'row' => $rowNo, 'message' => $message];
                    continue;
                }

                if ($this->hasDuplicateTransaction($mapped, $rowId, $rowDataType)) {
                    $duplicates++;
                    $this->updateUploadRowStatus($rowId, 'DUPLICATE', '기존 거래 존재');
                    $updatedStatus[$rowId] = 'DUPLICATED';
                    continue;
                }

                $result = $this->createTransactionFromPayload($mapped, $rowDataType);
                error_log('[ImportController] transaction save row=' . $rowNo . ' success=' . (!empty($result['success']) ? '1' : '0') . ' id=' . (string) ($result['id'] ?? '') . ' message=' . (string) ($result['message'] ?? ''));

                if (!empty($result['success']) && !empty($result['id'])) {
                    $transactionId = (string) $result['id'];
                    $created++;
                    $createdTransactionIds[] = $transactionId;
                    $processedIds[] = $rowId;
                    $updatedStatus[$rowId] = 'PROCESSED';
                    $this->updateUploadRowStatus($rowId, 'CREATED', null, $transactionId);
                    $this->createVoucherFromBankPayload($rowId, $mapped, $transactionId, $confirmExistingVoucher);
                    continue;
                }

                if (!empty($result['fallback_transaction_created']) && !empty($result['id'])) {
                    $transactionId = (string) $result['id'];
                    $created++;
                    $createdTransactionIds[] = $transactionId;
                    $processedIds[] = $rowId;
                    $updatedStatus[$rowId] = 'PROCESSED';
                    $this->updateUploadRowStatus($rowId, 'CREATED', '거래헤더 생성 완료 / 거래내역 보완 필요', $transactionId);
                    $this->createVoucherFromBankPayload($rowId, $mapped, $transactionId, $confirmExistingVoucher);
                    $errors[] = [
                        'row_id' => $rowId,
                        'row' => $rowNo,
                        'message' => '거래헤더는 생성됐지만 거래내역 저장은 보완이 필요합니다. 거래입력에서 거래내역을 완성해 주세요.',
                        'transaction_id' => $transactionId,
                    ];
                    continue;
                }

                $message = $this->formatTransactionCreateError((string) ($result['message'] ?? '거래 생성 실패'), $mapped, $rowNo);
                $this->updateUploadRowStatus($rowId, 'ERROR', $message);
                $errorIds[] = $rowId;
                $updatedStatus[$rowId] = 'ERROR';
                $errors[] = ['row_id' => $rowId, 'row' => $rowNo, 'message' => $message];
            } catch (\Throwable $e) {
                $rawMessage = $e->getMessage();
                $message = $this->formatTransactionCreateError($rawMessage, $mapped, $rowNo);
                error_log('[ImportController] transaction create row failed row=' . $rowNo . ' row_id=' . $rowId . ' error=' . $rawMessage);
                $this->updateUploadRowStatus($rowId, 'ERROR', $message);
                $errorIds[] = $rowId;
                $updatedStatus[$rowId] = 'ERROR';
                $errors[] = ['row_id' => $rowId, 'row' => $rowNo, 'message' => $message];
            }
        }

        if ($batchId !== '') {
            $this->refreshUploadBatchStatus($batchId);
        }

        $errorCount = count($errors);
        $transactionLineCount = $this->countTransactionLines($createdTransactionIds);
        $success = $errorCount === 0;
        $message = sprintf('ERP 거래 생성 완료: 생성 %d건, 중복 %d건, 오류 %d건', $created, $duplicates, $errorCount);

        error_log('[ImportController] apiCreateTransactions completed batch_id=' . $batchId . ' target=' . count($rows) . ' created=' . $created . ' duplicate=' . $duplicates . ' error=' . $errorCount . ' lines=' . $transactionLineCount);

        $this->json([
            'success' => $success,
            'batch_id' => $batchId !== '' ? $batchId : null,
            'target_count' => count($rows),
            'created_count' => $created,
            'duplicate_count' => $duplicates,
            'error_count' => $errorCount,
            'transaction_line_count' => $transactionLineCount,
            'success_count' => $created,
            'processed_ids' => $processedIds,
            'error_ids' => $errorIds,
            'updated_status' => $updatedStatus,
            'errors' => $errors,
            'message' => $message,
        ], $success ? 200 : 422);
        return;
        if ($batchId === '') {
            $this->json(['success' => false, 'message' => '업로드 배치 ID가 없습니다.'], 400);
            return;
        }

        $batch = $this->uploadBatch($batchId);
        if (!$batch) {
            $this->json(['success' => false, 'message' => '업로드 배치를 찾을 수 없습니다.'], 404);
            return;
        }

        $dataType = self::normalizeDataType((string) ($batch['data_type'] ?? 'TAX_INVOICE'));
        $rowIds = is_array($payload['row_ids'] ?? null) ? array_values(array_filter(array_map('strval', $payload['row_ids']))) : [];
        $rows = $this->uploadRowsForTransactionCreate($batchId, $rowIds);
        if ($rows === []) {
            $this->json(['success' => false, 'message' => '거래 생성 가능한 READY Seed Data가 없습니다.'], 400);
            return;
        }

        $created = 0;
        $duplicates = 0;
        $errors = [];
        foreach ($rows as $row) {
            $mapped = $this->decodeMappedPayload($row['mapped_payload'] ?? null);
            if (!is_array($mapped)) {
                $message = '매핑 데이터 JSON을 읽을 수 없습니다.';
                $this->updateUploadRowStatus((string) $row['id'], 'ERROR', $message);
                $errors[] = ['row' => (int) $row['row_no'], 'message' => $message];
                continue;
            }

            if ($this->hasDuplicateTransaction($mapped, (string) ($row['id'] ?? ''), $dataType)) {
                $duplicates++;
                $this->updateUploadRowStatus((string) $row['id'], 'DUPLICATE', '기존 거래 존재');
                continue;
            }

            $result = $this->createTransactionFromPayload($mapped, $dataType);
            if (!empty($result['success'])) {
                $created++;
                $this->updateUploadRowStatus((string) $row['id'], 'CREATED', null, (string) ($result['id'] ?? ''));
            } else {
                $message = $result['message'] ?? '거래 생성 실패';
                $this->updateUploadRowStatus((string) $row['id'], 'ERROR', $message);
                $errors[] = ['row' => (int) $row['row_no'], 'message' => $message];
            }
        }
        $this->refreshUploadBatchStatus($batchId);

        $this->json([
            'success' => $errors === [],
            'created_count' => $created,
            'duplicate_count' => $duplicates,
            'errors' => $errors,
            'message' => $errors === [] ? "{$created}건의 거래가 생성되었습니다." : '일부 거래 생성에 실패했습니다.',
        ], $errors === [] ? 200 : 422);
    }

    private function createBundledVoucherFromEvidenceRows(array $rowIds): array
    {
        $rowIds = array_values(array_filter(array_unique(array_map('strval', $rowIds))));
        if (count($rowIds) < 2) {
            return ['success' => false, 'message' => '묶음전표발행은 2건 이상 선택해야 합니다.'];
        }
        if (!$this->tableExists('ledger_data_evidences')) {
            return ['success' => false, 'message' => '증빙원본 테이블을 찾을 수 없습니다.'];
        }

        [$inSql, $params] = $this->placeholdersForIds($rowIds, 'bundled_voucher_evidence');
        $transactionSelect = $this->evidenceHasTransactionIdColumn() ? 'transaction_id' : 'NULL AS transaction_id';
        $stmt = $this->pdo->prepare("
            SELECT id, source_type, evidence_date,
                   client_id, project_id, employee_id, bank_account_id, card_id,
                   client_name, project_name, employee_name, bank_account_name, card_name,
                   mapped_payload_json,
                   {$transactionSelect}
            FROM ledger_data_evidences
            WHERE id IN ({$inSql})
              AND deleted_at IS NULL
        ");
        $stmt->execute($params);

        $rowsById = [];
        foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
            $rowsById[(string) ($row['id'] ?? '')] = $row;
        }

        $rows = [];
        foreach ($rowIds as $id) {
            if (isset($rowsById[$id])) {
                $rows[] = $rowsById[$id];
            }
        }
        if (count($rows) !== count($rowIds)) {
            return ['success' => false, 'message' => '선택한 증빙원본 중 일부를 찾을 수 없습니다.'];
        }

        $actor = ActorHelper::user();
        $errors = [];
        $voucherLines = [];
        $voucherDate = '';
        $firstEvidenceId = (string) ($rows[0]['id'] ?? '');
        $linkedTransactions = [];

        foreach ($rows as $index => $row) {
            $evidenceId = (string) ($row['id'] ?? '');
            $transactionId = trim((string) ($row['transaction_id'] ?? ''));
            $mapped = $this->decodeMappedPayload($row['mapped_payload_json'] ?? null);
            if (!is_array($mapped)) {
                $errors[] = ($index + 1) . '번째 증빙원본의 매핑 데이터가 올바르지 않습니다.';
                continue;
            }

            $dataType = self::normalizeDataType((string) ($row['source_type'] ?? $mapped['import_type'] ?? ''));
            if ($dataType === 'BANK_TRANSACTION') {
                $mapped = $this->normalizeBankTransactionPayload($mapped);
            }
            $mapped = $this->normalizeEvidenceMappedPayloadForResponse($mapped);
            $this->mergeEvidenceBusinessInfoIntoPayload($row, $mapped);
            $mapped['source_type'] = $dataType;
            $mapped['import_type'] = $dataType;
            $mapped['evidence_date'] = $row['evidence_date'] ?? ($mapped['evidence_date'] ?? '');

            if ($this->activeVoucherExistsForEvidence($evidenceId, $transactionId)) {
                $errors[] = ($index + 1) . '번째 증빙원본은 이미 전표가 연결되어 있습니다.';
                continue;
            }

            $readiness = $this->readinessForEvidenceRow([
                'source_type' => $dataType,
                'import_type' => $dataType,
                'source_key' => $mapped['source_key'] ?? '',
                'evidence_date' => $row['evidence_date'] ?? '',
            ], $mapped);
            if (($readiness['status'] ?? '') !== 'READY') {
                $errors[] = ($index + 1) . '번째 증빙원본 보정필요: ' . implode(' / ', $readiness['errors'] ?? []);
                continue;
            }

            if (!$this->hasVoucherLinesPayload($mapped)) {
                $errors[] = ($index + 1) . '번째 증빙원본에 전표 분개라인이 없습니다.';
                continue;
            }

            try {
                $lines = $this->bankVoucherLinesForSave($mapped['_voucher_lines'] ?? []);
                $missingRefMessage = $this->missingRequiredEvidenceRefsMessage($lines, $mapped);
                if ($missingRefMessage !== null) {
                    $errors[] = ($index + 1) . '번째 증빙원본: ' . $missingRefMessage;
                    continue;
                }
                $lines = $this->applyEvidenceRefsToVoucherLines($lines, $mapped);
                $voucherLines = array_merge($voucherLines, $lines);
            } catch (\Throwable $e) {
                $errors[] = ($index + 1) . '번째 증빙원본: ' . $e->getMessage();
                continue;
            }

            if ($voucherDate === '') {
                $voucherDate = $this->dateValue($mapped['voucher_date'] ?? $mapped['transaction_date'] ?? $mapped['evidence_date'] ?? '');
            }
            if ($transactionId !== '') {
                $linkedTransactions[$evidenceId] = $transactionId;
            }
        }

        if ($errors !== []) {
            return ['success' => false, 'message' => implode("\n", $errors), 'errors' => $errors];
        }
        if ($voucherLines === []) {
            return ['success' => false, 'message' => '묶음전표로 발행할 분개라인이 없습니다.'];
        }

        try {
            $result = $this->voucherService()->save([
                'voucher_date' => $voucherDate !== '' ? $voucherDate : date('Y-m-d'),
                'summary_text' => '묶음전표 ' . count($rows) . '건',
                'source_type' => 'SYSTEM',
                'lines' => $voucherLines,
                'payments' => [],
            ]);
            $voucherId = (string) ($result['voucher_id'] ?? $result['id'] ?? '');
            if ($voucherId === '') {
                return ['success' => false, 'message' => '묶음전표 발행 결과 전표 ID가 없습니다.'];
            }

            $this->tagBundledVoucher($voucherId, $firstEvidenceId, $actor);
            foreach ($rows as $row) {
                $evidenceId = (string) ($row['id'] ?? '');
                $transactionId = $linkedTransactions[$evidenceId] ?? '';
                $this->linkVoucherToEvidence($evidenceId, $voucherId, $transactionId, $actor);
                if ($transactionId !== '') {
                    $this->linkVoucherToTransaction($voucherId, $transactionId, null, 'AUTO', $actor);
                }
                $this->updateEvidenceVoucherStatus($evidenceId, 'CREATED', $actor);
            }

            return [
                'success' => true,
                'voucher_id' => $voucherId,
                'processed_ids' => $rowIds,
                'success_count' => count($rows),
                'message' => '묶음전표 ' . count($rows) . '건 발행이 완료되었습니다.',
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function tagBundledVoucher(string $voucherId, string $firstEvidenceId, string $actor): void
    {
        if ($voucherId === '') {
            return;
        }

        $sets = [];
        $params = [':id' => $voucherId, ':actor' => $actor];
        if ($this->tableColumnExists('ledger_vouchers', 'source_type')) {
            $sets[] = 'source_type = :source_type';
            $params[':source_type'] = 'SYSTEM';
        }
        if ($firstEvidenceId !== '' && $this->tableColumnExists('ledger_vouchers', 'source_id')) {
            $sets[] = 'source_id = :source_id';
            $params[':source_id'] = $firstEvidenceId;
        }
        if ($this->tableColumnExists('ledger_vouchers', 'import_type')) {
            $sets[] = 'import_type = :import_type';
            $params[':import_type'] = 'BUNDLED_EVIDENCE';
        }
        if ($this->tableColumnExists('ledger_vouchers', 'updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }
        if ($this->tableColumnExists('ledger_vouchers', 'updated_by')) {
            $sets[] = 'updated_by = :actor';
        }
        if ($sets === []) {
            return;
        }

        $this->pdo->prepare('UPDATE ledger_vouchers SET ' . implode(', ', $sets) . ' WHERE id = :id')
            ->execute($params);
    }

    public function apiUploadBatches(): void
    {
        $stmt = $this->pdo->query("
            SELECT
                DATE(e.latest_imported_at) AS imported_date,
                e.source_type AS data_type,
                e.source_type,
                e.format_id,
                f.format_name,
                COUNT(*) AS total_rows,
                SUM(CASE WHEN e.evidence_status = 'ACTIVE' AND e.transaction_status = 'NONE' THEN 1 ELSE 0 END) AS ready_count,
                SUM(CASE WHEN e.evidence_status = 'ACTIVE' AND e.transaction_status = 'NONE' THEN 1 ELSE 0 END) AS valid_count,
                0 AS warning_count,
                SUM(CASE WHEN e.evidence_status = 'ERROR' OR e.transaction_status = 'ERROR' THEN 1 ELSE 0 END) AS error_count,
                SUM(CASE WHEN e.evidence_status = 'DUPLICATED' OR e.transaction_status = 'DUPLICATED' THEN 1 ELSE 0 END) AS duplicate_count,
                SUM(CASE WHEN " . $this->evidenceCreatedTransactionSql('e') . " THEN 1 ELSE 0 END) AS created_count,
                SUM(CASE WHEN " . $this->evidenceCreatedTransactionSql('e') . " THEN 1 ELSE 0 END) AS processed_count,
                MAX(e.latest_imported_at) AS created_at,
                MAX(e.updated_at) AS updated_at
            FROM ledger_data_evidences e
            LEFT JOIN ledger_data_formats f ON f.id = e.format_id
            WHERE e.deleted_at IS NULL
            GROUP BY DATE(e.latest_imported_at), e.source_type, e.format_id, f.format_name
            ORDER BY MAX(e.latest_imported_at) DESC
            LIMIT 100
        ");

        $this->json(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]);
    }

    public function apiSeedRows(): void
    {
        if ((string) ($_GET['repair_schema'] ?? '') === '1') {
            $this->ensureEvidenceBusinessInfoColumns();
            $this->ensureEvidenceSortColumns();
        }
        if ((string) ($_GET['repair_bank_orphans'] ?? '') === '1') {
            $this->ensureEvidenceBusinessInfoColumns();
            $this->ensureBankTransactionBalanceColumns();
            $this->ensureBankTransactionEvidenceRows();
        }
        $status = strtoupper(trim((string) ($_GET['process_status'] ?? $_GET['status'] ?? '')));
        $requestedSourceType = strtoupper(trim((string) ($_GET['source_type'] ?? '')));
        $importType = self::normalizeDataType((string) ($_GET['import_type'] ?? $_GET['data_type'] ?? ''));
        $sourceType = '';
        if ($requestedSourceType !== '') {
            $normalizedRequested = self::normalizeDataType($requestedSourceType);
            if ($this->isAllowedDataType($normalizedRequested)) {
                $importType = $normalizedRequested;
            } else {
                $sourceType = self::normalizeImportSourceType($requestedSourceType);
            }
        }
        if ($importType !== '' && !$this->isAllowedDataType($importType)) {
            $this->json(['success' => true, 'data' => []]);
            return;
        }
        $sequenceScope = $this->evidenceSequenceScopeFromRequest('', $importType);
        $this->ensureEvidenceSortColumns();
        if ((string) ($_GET['type_counts'] ?? '') === '1') {
            $stmt = $this->pdo->query("
                SELECT
                    CASE
                        WHEN source_type IS NULL OR source_type = '' THEN 'UNKNOWN'
                        ELSE source_type
                    END AS import_type,
                    COUNT(*) AS row_count
                FROM ledger_data_evidences
                WHERE deleted_at IS NULL
                GROUP BY
                    CASE
                        WHEN source_type IS NULL OR source_type = '' THEN 'UNKNOWN'
                        ELSE source_type
                    END
            ");
            $data = [];
            foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
                $type = self::normalizeDataType((string) ($row['import_type'] ?? ''));
                $data[] = [
                    'import_type' => $type !== '' ? $type : 'UNKNOWN',
                    'row_count' => (int) ($row['row_count'] ?? 0),
                ];
            }
            $this->json(['success' => true, 'data' => $data]);
            return;
        }
        $filters = $this->seedRowFiltersFromRequest();

        $where = [];
        $params = [];
        $requestedId = trim((string) ($_GET['id'] ?? ''));
        if ($requestedId !== '') {
            $where[] = 'r.id = :requested_id';
            $params[':requested_id'] = $requestedId;
        }
        if ($status === 'READY') {
            $where[] = "r.evidence_status = 'ACTIVE'";
            $where[] = "r.transaction_status = 'NONE'";
        } elseif ($status === 'PROCESSED') {
            $where[] = $this->evidenceCreatedTransactionSql('r');
        } elseif ($status === 'ERROR') {
            $where[] = "(r.evidence_status = 'ERROR' OR r.transaction_status = 'ERROR')";
        } elseif ($status === 'DUPLICATED') {
            $where[] = "(r.evidence_status = 'DUPLICATED' OR r.transaction_status = 'DUPLICATED')";
        }
        if ($importType !== '') {
            $where[] = 'r.source_type = :import_type';
            $params[':import_type'] = $importType;
        } elseif ($sourceType !== '') {
            $types = self::importTypesForSourceType($sourceType);
            if ($types === []) {
                $this->json(['success' => true, 'data' => []]);
                return;
            }
            $keys = [];
            foreach ($types as $index => $type) {
                $key = ':source_type_' . $index;
                $keys[] = $key;
                $params[$key] = $type;
            }
            $where[] = 'r.source_type IN (' . implode(', ', $keys) . ')';
        }
        $where[] = $status === 'DELETED' ? 'r.deleted_at IS NOT NULL' : 'r.deleted_at IS NULL';
        $isServerPaged = isset($_GET['draw']) || isset($_GET['start']) || isset($_GET['length']);
        $pageStart = max(0, (int) ($_GET['start'] ?? 0));
        $pageLength = (int) ($_GET['length'] ?? 0);
        if ($pageLength <= 0) {
            $pageLength = 100;
        }
        $pageLength = min($pageLength, 500);
        $recordsFiltered = null;
        if ($isServerPaged && $filters === []) {
            $countStmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM ledger_data_evidences r
                WHERE " . implode(' AND ', $where) . "
            ");
            $countStmt->execute($params);
            $recordsFiltered = (int) $countStmt->fetchColumn();

            $pageIds = $this->evidencePageIdsForServerPaging($where, $params, $importType, $pageStart, $pageLength, $sequenceScope);
            if ($pageIds === []) {
                $this->json([
                    'success' => true,
                    'draw' => (int) ($_GET['draw'] ?? 0),
                    'recordsTotal' => $recordsFiltered,
                    'recordsFiltered' => $recordsFiltered,
                    'data' => [],
                ]);
                return;
            }
            $idPlaceholders = [];
            foreach ($pageIds as $index => $id) {
                $key = ':page_id_' . $index;
                $idPlaceholders[] = $key;
                $params[$key] = $id;
            }
            $where[] = 'r.id IN (' . implode(', ', $idPlaceholders) . ')';
        }

        $sql = "
            SELECT
                r.id,
                NULL AS seed_batch_id,
                " . self::sourceTypeSql('r.source_type') . " AS source_type,
                r.source_type AS import_type,
                st.code_name AS source_type_name,
                it.code_name AS import_type_name,
                r.create_sort_no,
                r.status_sort_no,
                0 AS row_no,
                r.format_id,
                r.raw_json,
                r.mapped_payload_json AS parsed_json,
                r.source_key,
                r.evidence_date,
                r.client_id,
                r.project_id,
                r.employee_id,
                r.bank_account_id,
                r.card_id,
                r.client_name,
                r.project_name,
                r.employee_name,
                r.bank_account_name,
                r.card_name,
                r.evidence_status,
                r.transaction_status,
                r.voucher_status,
                r.review_status,
                CASE
                    WHEN r.evidence_status = 'ERROR' OR r.transaction_status = 'ERROR' THEN 'ERROR'
                    WHEN r.evidence_status = 'DUPLICATED' OR r.transaction_status = 'DUPLICATED' THEN 'DUPLICATED'
                    WHEN r.transaction_status = 'PROCESSING' THEN 'PROCESSING'
                    WHEN " . $this->evidenceCreatedTransactionSql('r') . " THEN 'PROCESSED'
                    ELSE 'READY'
                END AS process_status,
                CASE
                    WHEN r.evidence_status = 'ERROR' OR r.transaction_status = 'ERROR' THEN 'ERROR'
                    WHEN r.evidence_status = 'DUPLICATED' OR r.transaction_status = 'DUPLICATED' THEN 'DUPLICATED'
                    WHEN r.transaction_status = 'PROCESSING' THEN 'PROCESSING'
                    WHEN " . $this->evidenceCreatedTransactionSql('r') . " THEN 'PROCESSED'
                    ELSE 'READY'
                END AS status,
                r.error_message,
                " . $this->evidenceTransactionIdSelect('r') . ",
                r.latest_imported_at AS processed_at,
                r.created_at,
                r.updated_at,
                r.deleted_at,
                NULL AS file_name,
                f.format_name
            FROM ledger_data_evidences r
            LEFT JOIN ledger_data_formats f ON f.id = r.format_id
            LEFT JOIN system_codes st
                ON st.deleted_at IS NULL
               AND st.is_active = 1
               AND st.code_group IN ('IMPORT_SOURCE', 'SOURCE_TYPE')
               AND st.code = " . self::sourceTypeSql('r.source_type') . "
            LEFT JOIN system_codes it
                ON it.deleted_at IS NULL
               AND it.is_active = 1
               AND it.code_group = 'IMPORT_TYPE'
               AND it.code = r.source_type
        ";
        $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ' . $this->evidenceRowsOrderSql($importType, $sequenceScope);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $this->syncVoucherStatusFromActiveLinks($rows);
        foreach ($rows as &$row) {
            $row['raw_payload'] = json_decode((string) ($row['raw_json'] ?? ''), true) ?: [];
            $row['mapped_payload'] = json_decode((string) ($row['parsed_json'] ?? ''), true) ?: [];
            $mappedPayload = is_array($row['mapped_payload']) ? $row['mapped_payload'] : [];
            if ($row['import_type'] === 'BANK_TRANSACTION') {
                $mappedPayload = $this->normalizeBankTransactionPayload($mappedPayload);
            }
            $mappedPayload = $this->normalizeEvidenceMappedPayloadForResponse($mappedPayload);
            $this->mergeEvidenceBusinessInfoIntoPayload($row, $mappedPayload);
            $row['mapped_payload'] = $mappedPayload;
            $payloadDataType = self::normalizeDataType((string) ($mappedPayload['import_type'] ?? $mappedPayload['data_type'] ?? $mappedPayload['evidence_type'] ?? ''));
            if (in_array((string) ($row['import_type'] ?? ''), ['', 'MANUAL'], true) && $payloadDataType !== '') {
                $row['import_type'] = $payloadDataType;
                $row['source_type'] = self::sourceTypeForDataType($payloadDataType);
                $row['source_type_name'] = self::sourceTypeLabel((string) $row['source_type']);
                $row['import_type_name'] = self::importTypeLabel($payloadDataType);
            }
            $resolvedClientName = '';
            $clientIdForDisplay = trim((string) ($row['client_id'] ?? $mappedPayload['client_id'] ?? ''));
            if ($clientIdForDisplay !== '' && $this->isUuid($clientIdForDisplay)) {
                $resolvedClientName = (string) ($this->businessRefNameById('CLIENT', $clientIdForDisplay) ?? '');
            }
            $row['client_name'] = (string) (
                $row['import_type'] === 'BANK_TRANSACTION'
                    ? ($mappedPayload['counterparty_name']
                        ?? $mappedPayload['counterparty_account_holder_name']
                        ?? $mappedPayload['counterparty_account_holder']
                        ?? ($resolvedClientName !== '' ? $resolvedClientName : null)
                        ?? $row['client_name']
                        ?? $mappedPayload['client_name']
                        ?? $mappedPayload['client_company_name']
                        ?? '')
                    : (($resolvedClientName !== '' ? $resolvedClientName : null)
                ?? $row['client_name']
                ?? $mappedPayload['client_name']
                ?? $mappedPayload['client_company_name']
                ?? $mappedPayload['client_business_number']
                ?? $mappedPayload['supplier_name']
                ?? $mappedPayload['customer_name']
                ?? $mappedPayload['supplier_company_name']
                ?? $mappedPayload['customer_company_name']
                ?? '')
            );
            unset($row['raw_json'], $row['parsed_json']);
        }
        unset($row);
        $this->attachRequiredFormatColumnsToRows($rows);
        $this->sortEvidenceRowsForResponse($rows, $importType, $sequenceScope);
        foreach ($rows as &$row) {
            $this->applyReadinessToEvidenceRow($row);
        }
        unset($row);
        if ($status === 'READY') {
            $rows = array_values(array_filter($rows, static fn(array $row): bool => ($row['readiness_status'] ?? '') === 'READY'));
        } elseif (in_array($status, ['NOT_READY', 'REVIEW_REQUIRED', 'VERIFY_ONLY'], true)) {
            $rows = array_values(array_filter($rows, static fn(array $row): bool => ($row['readiness_status'] ?? '') === $status));
        }
        if ($filters !== []) {
            $rows = array_values(array_filter($rows, fn(array $row): bool => $this->seedRowMatchesFilters($row, $filters)));
        }
        $responseSortKey = $this->evidenceSortKeyForScope($sequenceScope, $importType);
        foreach ($rows as $index => &$row) {
            $row['applied_sort_no'] = $this->evidencePayloadSortNo($row, $responseSortKey);
            $row['row_no'] = $index + 1;
            if (!empty($row['error_message'])) {
                $row['error_message'] = $this->formatTransactionCreateError(
                    (string) $row['error_message'],
                    is_array($row['mapped_payload']) ? $row['mapped_payload'] : [],
                    (int) ($row['row_no'] ?? 0)
                );
            }
        }
        unset($row);
        $rows = $this->expandEvidenceRowsWithProcessingItems($rows, $sequenceScope);

        if ($isServerPaged) {
            $total = $recordsFiltered ?? count($rows);
            $this->json([
                'success' => true,
                'draw' => (int) ($_GET['draw'] ?? 0),
                'recordsTotal' => $total,
                'recordsFiltered' => $total,
                'data' => $rows,
            ]);
            return;
        }

        $this->json(['success' => true, 'data' => $rows]);
    }

    private function attachRequiredFormatColumnsToRows(array &$rows): void
    {
        if ($rows === []) {
            return;
        }

        $columnsByFormatId = [];
        $formatIdByType = [];

        foreach ($rows as &$row) {
            $formatId = trim((string) ($row['format_id'] ?? ''));
            if ($formatId === '') {
                $dataType = self::normalizeDataType((string) ($row['import_type'] ?? $row['source_type'] ?? $row['data_type'] ?? ''));
                if ($dataType !== '') {
                    if (!array_key_exists($dataType, $formatIdByType)) {
                        $formatIdByType[$dataType] = $this->defaultFormatIdForDataType($dataType);
                    }
                    $formatId = $formatIdByType[$dataType] ?? '';
                }
            }
            if ($formatId !== '' && !array_key_exists($formatId, $columnsByFormatId)) {
                $columnsByFormatId[$formatId] = $this->columns($formatId);
            }
            if ($formatId !== '' && trim((string) ($row['format_id'] ?? '')) === '') {
                $row['format_id'] = $formatId;
            }
            $row['format_columns'] = $formatId !== '' ? ($columnsByFormatId[$formatId] ?? []) : [];
        }
        unset($row);
    }

    private function expandEvidenceRowsWithProcessingItems(array $rows, string $sequenceScope = ''): array
    {
        if ($rows === [] || !$this->tableExists('ledger_processing_items')) {
            return $rows;
        }

        $itemModel = new ProcessingItemModel($this->pdo);
        $treeService = new ProcessingItemTreeService();
        $expanded = [];

        foreach ($rows as $row) {
            $evidenceId = trim((string) ($row['id'] ?? ''));
            if ($evidenceId === '') {
                $expanded[] = $row;
                continue;
            }

            $items = $itemModel->getBySource('ledger_data_evidences', $evidenceId);
            $hasSplitStructure = false;
            foreach ($items as $item) {
                if (trim((string) ($item['parent_item_id'] ?? '')) !== '' || strtoupper((string) ($item['item_status'] ?? '')) === 'SPLIT') {
                    $hasSplitStructure = true;
                    break;
                }
            }
            if (!$hasSplitStructure || $items === []) {
                $row['evidence_id'] = $evidenceId;
                $row['processing_item_id'] = $items[0]['id'] ?? null;
                $row['processing_display_path'] = (string) ($row['row_no'] ?? '');
                $row['processing_has_children'] = false;
                $row['processing_is_child'] = false;
                $expanded[] = $row;
                continue;
            }

            $tree = $treeService->buildTree($items, (string) ($row['row_no'] ?? ''), false);
            $flat = $treeService->flattenTree($tree);
            $childIdsByParent = [];
            foreach ($items as $item) {
                $parentId = trim((string) ($item['parent_item_id'] ?? ''));
                if ($parentId !== '') {
                    $childIdsByParent[$parentId] = true;
                }
            }

            $processingRows = [];
            foreach ($flat as $item) {
                $expandedRow = $row;
                $expandedRow['evidence_id'] = $evidenceId;
                $expandedRow['processing_item_id'] = (string) ($item['id'] ?? '');
                $expandedRow['processing_parent_item_id'] = $item['parent_item_id'] ?? null;
                $expandedRow['processing_display_path'] = (string) ($item['display_no'] ?? $item['display_path'] ?? $item['sort_no'] ?? $row['row_no'] ?? '');
                $expandedRow['processing_item_status'] = (string) ($item['item_status'] ?? '');
                $expandedRow['processing_is_current'] = (int) ($item['is_current'] ?? 0) === 1;
                $expandedRow['processing_is_child'] = trim((string) ($item['parent_item_id'] ?? '')) !== '';
                $expandedRow['processing_has_children'] = isset($childIdsByParent[(string) ($item['id'] ?? '')]);
                $expandedRow['processing_level'] = (int) ($item['level'] ?? 1);
                $expandedRow['_select_disabled'] = (bool) $expandedRow['processing_is_child'];
                if ($expandedRow['processing_display_path'] !== '') {
                    $expandedRow['row_no'] = $expandedRow['processing_display_path'];
                }

                $payload = json_decode((string) ($item['mapped_payload_json'] ?? ''), true);
                if (is_array($payload)) {
                    if ($expandedRow['import_type'] === 'BANK_TRANSACTION') {
                        $payload = $this->normalizeBankTransactionPayload($payload);
                    }
                    $payload = $this->normalizeEvidenceMappedPayloadForResponse($payload);
                    $this->mergeEvidenceBusinessInfoIntoPayload($expandedRow, $payload);
                    foreach (['quantity', 'unit_price', 'supply_amount', 'vat_amount', 'total_amount', 'currency', 'description', 'memo'] as $key) {
                        if (array_key_exists($key, $item) && $item[$key] !== null && $item[$key] !== '') {
                            $payload[$key] = $item[$key];
                        }
                    }
                    $expandedRow['mapped_payload'] = $payload;
                }

                $processingRows[] = $expandedRow;
            }
            $parentRow = null;
            $children = [];
            foreach ($processingRows as $processingRow) {
                if (!empty($processingRow['processing_is_child'])) {
                    $children[] = $processingRow;
                    continue;
                }
                if ($parentRow === null || !empty($processingRow['processing_has_children'])) {
                    $parentRow = $processingRow;
                }
            }
            if ($parentRow === null) {
                $parentRow = $row;
                $parentRow['evidence_id'] = $evidenceId;
                $parentRow['processing_has_children'] = $children !== [];
                $parentRow['processing_is_child'] = false;
            }
            $parentRow['processing_children'] = array_values($children);
            $parentRow['processing_child_count'] = count($children);
            $parentRow['processing_has_children'] = count($children) > 0;
            $parentRow['processing_is_child'] = false;
            $parentRow['row_no'] = $row['row_no'] ?? ($parentRow['row_no'] ?? '');
            $parentRow['processing_display_path'] = (string) ($parentRow['row_no'] ?? '');
            $parentRow['_select_disabled'] = false;
            $expanded[] = $parentRow;
        }

        return $expanded;
    }

    private function defaultFormatIdForDataType(string $dataType): string
    {
        $dataType = self::normalizeDataType($dataType);
        if ($dataType === '' || !$this->isAllowedDataType($dataType)) {
            return '';
        }

        $types = self::queryDataTypes($dataType);
        $placeholders = [];
        $params = [];
        foreach ($types as $index => $type) {
            $key = ':data_type_' . $index;
            $placeholders[] = $key;
            $params[$key] = $type;
        }

        $stmt = $this->pdo->prepare('
            SELECT id
            FROM ledger_data_formats
            WHERE deleted_at IS NULL
              AND data_type IN (' . implode(', ', $placeholders) . ')
            ORDER BY is_default DESC, format_name ASC, id ASC
            LIMIT 1
        ');
        $stmt->execute($params);

        return (string) ($stmt->fetchColumn() ?: '');
    }

    private function evidencePageIdsForServerPaging(array $where, array $params, string $importType, int $pageStart, int $pageLength, string $sequenceScope = ''): array
    {
        $pageStart = max(0, $pageStart);
        $pageLength = max(1, min($pageLength, 500));
        $stmt = $this->pdo->prepare("
            SELECT r.id
            FROM ledger_data_evidences r
            WHERE " . implode(' AND ', $where) . "
            " . $this->evidenceRowsOrderSql($importType, $sequenceScope) . "
            LIMIT {$pageLength} OFFSET {$pageStart}
        ");
        $stmt->execute($params);

        return array_values(array_filter(array_map(
            static fn(array $row): string => trim((string) ($row['id'] ?? '')),
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        )));
    }

    private function syncVoucherStatusFromActiveLinks(array &$rows): void
    {
        if ($rows === [] || !$this->tableExists('ledger_data_evidence_links') || !$this->tableExists('ledger_vouchers')) {
            return;
        }

        $evidenceIds = array_values(array_filter(array_unique(array_map(
            static fn(array $row): string => trim((string) ($row['id'] ?? '')),
            $rows
        ))));
        if ($evidenceIds === []) {
            return;
        }

        [$inSql, $params] = $this->placeholdersForIds($evidenceIds, 'active_voucher_evidence');
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT l.evidence_id
            FROM ledger_data_evidence_links l
            INNER JOIN ledger_vouchers v
                ON v.id = l.voucher_id
               AND v.deleted_at IS NULL
            WHERE l.deleted_at IS NULL
              AND l.evidence_id IN ({$inSql})
        ");
        $stmt->execute($params);
        $createdEvidenceIds = array_flip(array_filter(array_map(
            static fn(array $row): string => trim((string) ($row['evidence_id'] ?? '')),
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        )));

        if ($createdEvidenceIds === []) {
            return;
        }

        foreach ($rows as &$row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id !== '' && isset($createdEvidenceIds[$id])) {
                $row['voucher_status'] = 'CREATED';
            }
        }
        unset($row);
    }

    private function applyReadinessToEvidenceRow(array &$row): void
    {
        $payload = is_array($row['mapped_payload'] ?? null) ? $row['mapped_payload'] : [];
        $baseStatus = strtoupper(trim((string) ($row['process_status'] ?? $row['status'] ?? '')));
        $readiness = $this->readinessForEvidenceRow($row, $payload);

        $row['readiness_status'] = $readiness['status'];
        $row['readiness_errors'] = $readiness['errors'];
        $row['missing_fields'] = $readiness['missing_fields'];
        $row['processing_type'] = $readiness['processing_type'];
        $row['processing_objects'] = $readiness['processing_objects'];
        $row['processing_label'] = $readiness['processing_label'];
        $row['generation_target'] = $readiness['generation_target'];
        $row['generation_objects'] = $readiness['generation_objects'];
        $row['generation_label'] = $readiness['generation_label'];

        if (in_array($baseStatus, ['', 'READY'], true)) {
            $row['process_status'] = $readiness['status'];
            $row['status'] = $readiness['status'];
        }
    }

    private function readinessForEvidenceRow(array $row, array $payload): array
    {
        $dataType = self::normalizeDataType((string) ($row['import_type'] ?? $row['source_type'] ?? $payload['import_type'] ?? ''));
        $processing = self::processingPlanForDataType($dataType);
        if ($dataType === 'BANK_TRANSACTION') {
            $payload = $this->normalizeBankTransactionPayload($payload);
        }
        if (in_array($processing['type'], ['BUSINESS_DATA', 'UNSUPPORTED'], true)) {
            return $this->readinessResult(
                ['자료업로드 계층에서 처리할 수 없는 자료유형입니다. 업무데이터는 별도 업무시스템에서 거래/전표 생성 흐름으로 처리합니다.'],
                [],
                ['import_type'],
                $processing
            );
        }

        $missing = [];
        $errors = [];
        $warnings = [];
        $require = function (string $field, mixed $value, string $message) use (&$missing, &$errors): void {
            $text = trim((string) ($value ?? ''));
            if ($text === '' || $this->isEmptySelectionLabel($text)) {
                $missing[] = $field;
                $errors[] = $message;
            }
        };

        $require('source_type', $dataType, '자료유형이 확정되지 않았습니다.');
        $require('source_key', $row['source_key'] ?? $payload['source_key'] ?? $payload['approval_number'] ?? '', '원본 식별값이 없습니다.');
        $require('evidence_date', $row['evidence_date'] ?? $payload['evidence_date'] ?? $payload['transaction_date'] ?? $payload['issue_date'] ?? '', '증빙일자가 확정되지 않았습니다.');

        if ($processing['type'] === 'VERIFY_ONLY') {
            $require('approval_number', $payload['approval_number'] ?? $payload['approval_no'] ?? $payload['source_key'] ?? '', '카드 승인/청구 식별값이 없습니다.');
            $require('card_company', $payload['source_card_company_name'] ?? $payload['card_company'] ?? $payload['card_company_name'] ?? $payload['card_name'] ?? '', '카드사 또는 카드 정보가 없습니다.');
            $require('merchant', $payload['merchant_company_name'] ?? $payload['merchant_name'] ?? $payload['client_company_name'] ?? $payload['company_name'] ?? '', '가맹점 정보가 없습니다.');
            if ($this->amountOrNull(
                $payload['billing_amount']
                ?? $payload['claim_amount']
                ?? $payload['actual_billing_amount']
                ?? $payload['purchase_amount_krw']
                ?? $payload['total_amount']
                ?? $payload['supply_amount']
                ?? null
            ) === null) {
                $missing[] = 'billing_amount';
                $errors[] = '청구/승인 금액이 확정되지 않았습니다.';
            }

            $result = $this->readinessResult($errors, $warnings, $missing, $processing);
            if ($result['status'] === 'READY') {
                $result['status'] = 'VERIFY_ONLY';
            }
            return $result;
        }

        if ($processing['type'] === 'BANK_FLOW') {
            $transactionDate = $this->dateValueOrNull($payload['transaction_date'] ?? $payload['transaction_datetime'] ?? $row['evidence_date'] ?? null);
            $require('transaction_date', $transactionDate, '입출금 거래일자가 확정되지 않았습니다.');
            $bankAccountValue = $this->businessRefIdForStorage('ACCOUNT', $payload)
                ?? ($payload['bank_account_name'] ?? $payload['account_name'] ?? $payload['payment_account_name'] ?? $payload['bank_account'] ?? $payload['bank_account_id'] ?? '');
            $require('bank_account_name', $bankAccountValue, 'ERP 계좌명이 선택되지 않았습니다.');
            if ($this->amountOrNull($payload['deposit_amount'] ?? null) === null && $this->amountOrNull($payload['withdraw_amount'] ?? null) === null) {
                $missing[] = 'deposit_amount';
                $missing[] = 'withdraw_amount';
                $errors[] = '입금액 또는 출금액이 확정되지 않았습니다.';
            }
            $require('counterparty_name', $payload['counterparty_name'] ?? $payload['counterparty_account_holder_name'] ?? '', '상대처/예금주가 없습니다.');

            $bankProcessing = $processing;
            $bankProcessing['target'] = 'VOUCHER_WAITING';
            if ($this->hasVoucherLinesPayload($payload)) {
                $bankProcessing['objects'] = ['BANK_FLOW', 'VOUCHER_HEADER', 'VOUCHER_LINE'];
                $bankProcessing['label'] = '입출거래 + 전표생성';
            } else {
                $bankProcessing['objects'] = ['BANK_FLOW'];
                $bankProcessing['label'] = '입출거래 + 전표생성';
            }

            return $this->readinessResult($errors, $warnings, $missing, $bankProcessing);
        }

        $transactionDate = $this->dateValueOrNull($payload['transaction_date'] ?? $row['evidence_date'] ?? null);
        $require('transaction_date', $transactionDate, '거래일자가 확정되지 않았습니다.');

        $businessUnit = trim((string) ($payload['business_unit'] ?? $payload['business_unit_code'] ?? ''));
        $require('business_unit', $businessUnit, '사업구분이 확정되지 않았습니다.');

        try {
            $context = $this->resolveUploadTransactionContext($payload, $dataType);
            if (!empty($context['_direction_error'])) {
                $missing[] = 'transaction_direction';
                $errors[] = (string) $context['_direction_error'];
            }
        } catch (\Throwable $e) {
            $context = [];
            $missing[] = 'transaction_direction';
            $errors[] = '거래방향을 판정할 수 없습니다: ' . $e->getMessage();
        }

        $direction = $this->transactionDirectionForStorage((string) ($context['transaction_direction'] ?? $payload['transaction_direction'] ?? ''), $payload, $dataType);
        if (!in_array($direction, ['PURCHASE', 'SALES', 'IN', 'OUT'], true)) {
            $missing[] = 'transaction_direction';
            $errors[] = '거래방향이 확정되지 않았습니다.';
        }

        $transactionType = trim((string) ($payload['transaction_type'] ?? ''));
        $require('transaction_type', $transactionType, '거래유형이 확정되지 않았습니다.');

        $total = $this->amountOrNull($payload['total_amount'] ?? null);
        $supply = $this->amountOrNull($payload['supply_amount'] ?? null);
        if ($total === null && $supply === null) {
            $missing[] = 'total_amount';
            $errors[] = '거래 금액이 확정되지 않았습니다.';
        }
        if ($supply === null) {
            $missing[] = 'supply_amount';
            $errors[] = '공급가액이 확정되지 않았습니다.';
        }

        $clientId = trim((string) ($payload['client_id'] ?? ''));
        if ($this->isEmptySelectionLabel($clientId)) {
            $clientId = '';
        }
        $clientBusinessNumber = $this->normalizeBusinessNumber((string) ($context['client_business_number'] ?? $payload['client_business_number'] ?? $payload['business_number'] ?? ''));
        $clientName = $this->cleanCompanyName((string) ($context['client_company_name'] ?? $payload['client_company_name'] ?? $payload['company_name'] ?? $payload['counterparty_name'] ?? ''));
        if ($this->isEmptySelectionLabel($clientName)) {
            $clientName = '';
        }
        if ($clientId === '' && $clientBusinessNumber === '' && $clientName === '') {
            $missing[] = 'client_id';
            $errors[] = '거래처 ID 또는 거래처 후보값이 없습니다.';
        }

        if (in_array((string) ($processing['target'] ?? ''), ['TRANSACTION_FULL', 'TRANSACTION_AND_VOUCHER'], true)) {
            $itemName = trim((string) ($payload['item_name'] ?? ''));
            if ($itemName === '') {
                $warnings[] = '거래내역은 거래 화면에서 보완할 수 있습니다.';
            }
        }

        return $this->readinessResult($errors, $warnings, $missing, $processing);
    }

    private function readinessResult(array $errors, array $warnings, array $missing, array $processing): array
    {
        $missing = array_values(array_unique($missing));
        $errors = array_values(array_unique($errors));
        $warnings = array_values(array_unique($warnings));

        return [
            'status' => $errors === [] ? 'READY' : 'NOT_READY',
            'errors' => $errors !== [] ? $errors : $warnings,
            'missing_fields' => $missing,
            'processing_type' => $processing['type'],
            'processing_objects' => $processing['objects'],
            'processing_label' => $processing['label'],
            'generation_target' => $processing['target'] ?? $processing['type'],
            'generation_objects' => $processing['objects'],
            'generation_label' => $processing['label'],
        ];
    }

    private function evidenceRowsOrderSql(string $importType, string $sequenceScope = ''): string
    {
        $normalizedType = self::normalizeDataType($importType);
        $sortColumn = $this->evidenceSortColumnForScope($sequenceScope, $importType);
        if ($sortColumn !== '') {
            $fallback = $sortColumn === 'create_sort_no'
                ? "
                    r.latest_imported_at DESC,
                    r.created_at DESC,
                    r.id ASC
                "
                : "
                    COALESCE(r.evidence_date, DATE(r.latest_imported_at), DATE(r.created_at)) DESC,
                    r.latest_imported_at DESC,
                    r.created_at DESC,
                    r.id ASC
                ";

            return "
                ORDER BY
                    CASE WHEN r.`{$sortColumn}` IS NULL OR r.`{$sortColumn}` < 1 THEN 1 ELSE 0 END ASC,
                    r.`{$sortColumn}` ASC,
                    {$fallback}
            ";
        }

        if ($normalizedType === '') {
            return "
                ORDER BY
                    r.latest_imported_at DESC,
                    r.created_at DESC,
                    r.id ASC
            ";
        }

        return "
            ORDER BY
                COALESCE(r.evidence_date, DATE(r.latest_imported_at), DATE(r.created_at)) DESC,
                r.latest_imported_at DESC,
                r.created_at DESC,
                r.id ASC
        ";
    }

    private function bankTransactionSortValue(array $row): string
    {
        $payload = is_array($row['mapped_payload'] ?? null) ? $row['mapped_payload'] : [];
        $dateTime = trim((string) (
            $payload['transaction_datetime']
            ?? $payload['transaction_at']
            ?? $payload['거래일시']
            ?? ''
        ));
        if ($dateTime === '') {
            $date = trim((string) (
                $payload['transaction_date']
                ?? $payload['거래일자']
                ?? $row['evidence_date']
                ?? ''
            ));
            $time = trim((string) (
                $payload['transaction_time']
                ?? $payload['거래시간']
                ?? ''
            ));
            $dateTime = trim($date . ' ' . $time);
        }

        $timestamp = strtotime($dateTime);
        if ($timestamp !== false) {
            return date('Y-m-d H:i:s', $timestamp);
        }

        return (string) ($row['processed_at'] ?? $row['created_at'] ?? '');
    }

    private function sortEvidenceRowsForResponse(array &$rows, string $importType, string $sequenceScope = ''): void
    {
        $normalizedType = self::normalizeDataType($importType);
        $sortKey = $this->evidenceSortKeyForScope($sequenceScope, $importType);
        $isCreateScope = $sortKey === '_create_sort_no';

        usort($rows, function (array $a, array $b) use ($normalizedType, $sortKey, $isCreateScope): int {
            $aSort = $this->evidencePayloadSortNo($a, $sortKey);
            $bSort = $this->evidencePayloadSortNo($b, $sortKey);
            if ($aSort > 0 && $bSort > 0 && $aSort !== $bSort) {
                return $aSort <=> $bSort;
            }
            if ($aSort > 0 && $bSort < 1) {
                return -1;
            }
            if ($aSort < 1 && $bSort > 0) {
                return 1;
            }

            if ($isCreateScope) {
                $createdCompare = strcmp(
                    (string) ($b['processed_at'] ?? $b['created_at'] ?? ''),
                    (string) ($a['processed_at'] ?? $a['created_at'] ?? '')
                );
                if ($createdCompare !== 0) {
                    return $createdCompare;
                }
                return $this->evidencePayloadSortNo($a, '_row_no') <=> $this->evidencePayloadSortNo($b, '_row_no');
            }

            return strcmp(
                $this->evidenceTypeSortValue($b, $normalizedType),
                $this->evidenceTypeSortValue($a, $normalizedType)
            );
        });
    }

    private function evidenceSequenceScopeFromRequest(string $default = '', string $importType = ''): string
    {
        $scope = strtolower(trim((string) ($_GET['sequence_scope'] ?? $_GET['sort_scope'] ?? $default)));
        if (in_array($scope, ['create', 'status'], true)) {
            return $scope;
        }

        return self::normalizeDataType($importType) === '' ? 'create' : 'status';
    }

    private function evidenceSortKeyForScope(string $sequenceScope, string $importType = ''): string
    {
        $scope = strtolower(trim($sequenceScope));
        if ($scope === 'create') {
            return '_create_sort_no';
        }
        if ($scope === 'status') {
            return '_status_sort_no';
        }

        return self::normalizeDataType($importType) === '' ? '_create_sort_no' : '_status_sort_no';
    }

    private function evidenceSortColumnForScope(string $sequenceScope, string $importType = ''): string
    {
        $key = $this->evidenceSortKeyForScope($sequenceScope, $importType);
        return $key === '_create_sort_no' ? 'create_sort_no' : 'status_sort_no';
    }

    private function evidencePayloadSortNo(array $row, string $key): int
    {
        $column = $key === '_create_sort_no' ? 'create_sort_no' : ($key === '_status_sort_no' ? 'status_sort_no' : '');
        if ($column !== '' && array_key_exists($column, $row) && is_numeric($row[$column])) {
            return max(0, (int) $row[$column]);
        }
        $payload = is_array($row['mapped_payload'] ?? null) ? $row['mapped_payload'] : [];
        $value = $payload[$key] ?? 0;
        if (is_string($value)) {
            $value = str_replace(',', '', trim($value));
        }

        return is_numeric($value) ? max(0, (int) $value) : 0;
    }

    private function evidenceTypeSortValue(array $row, string $dataType): string
    {
        if ($dataType === 'BANK_TRANSACTION') {
            return $this->bankTransactionSortValue($row);
        }

        $payload = is_array($row['mapped_payload'] ?? null) ? $row['mapped_payload'] : [];
        $keys = match ($dataType) {
            'TAX_INVOICE' => ['write_date', 'written_date', 'transaction_date', 'evidence_date', 'issue_date'],
            'CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES' => ['purchase_datetime', 'purchase_at', 'purchase_date', 'transaction_datetime', 'transaction_date', 'evidence_date'],
            'CARD_STATEMENT', 'CARD_APPROVAL', 'CARD_HOMETAX', 'CARD_COMPANY', 'CARD', 'CREDIT_CARD' => ['approval_datetime', 'approved_at', 'approval_date', 'approved_date', 'transaction_datetime', 'transaction_date', 'evidence_date'],
            default => ['transaction_datetime', 'transaction_date', 'evidence_date', 'issue_date'],
        };

        foreach ($keys as $key) {
            $value = trim((string) ($payload[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return date('Y-m-d H:i:s', $timestamp);
            }
        }

        $timestamp = strtotime((string) ($row['processed_at'] ?? $row['created_at'] ?? ''));
        return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : '';
    }

    public function apiEvidencesDownload(): void
    {
        $formatId = trim((string) ($_GET['format_id'] ?? ''));
        $importType = self::normalizeDataType((string) ($_GET['import_type'] ?? $_GET['data_type'] ?? ''));
        $format = $this->formatWithColumns($formatId);
        if (!$format) {
            $this->json(['success' => false, 'message' => '양식을 찾을 수 없습니다.'], 404);
            return;
        }

        $formatType = self::normalizeDataType((string) ($format['data_type'] ?? ''));
        if ($importType === '') {
            $importType = $formatType;
        }
        if ($importType !== $formatType) {
            $this->json(['success' => false, 'message' => '자료유형과 양식이 일치하지 않습니다.'], 400);
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
            $this->json(['success' => false, 'message' => '다운로드할 컬럼이 없습니다.'], 400);
            return;
        }

        $stmt = $this->pdo->prepare("
            SELECT mapped_payload_json, raw_json
            FROM ledger_data_evidences
            WHERE deleted_at IS NULL
              AND source_type = :source_type
              AND format_id = :format_id
            ORDER BY COALESCE(evidence_date, DATE(latest_imported_at), DATE(created_at)) DESC, latest_imported_at DESC, created_at DESC
        ");
        $stmt->execute([
            ':source_type' => $importType,
            ':format_id' => (string) ($format['id'] ?? $formatId),
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('evidences');
        foreach ($columns as $index => $column) {
            $cell = Coordinate::stringFromColumnIndex($index + 1) . '1';
            $sheet->setCellValue($cell, (string) ($column['excel_column_name'] ?? $column['system_field_name'] ?? '컬럼'));
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F6FA');
        }

        $rowNo = 2;
        foreach ($rows as $row) {
            $payload = json_decode((string) ($row['mapped_payload_json'] ?? ''), true) ?: [];
            $raw = json_decode((string) ($row['raw_json'] ?? ''), true) ?: [];
            foreach ($columns as $index => $column) {
                $field = trim((string) ($column['system_field_name'] ?? ''));
                $excelName = trim((string) ($column['excel_column_name'] ?? ''));
                $value = $field !== '' && array_key_exists($field, $payload)
                    ? $payload[$field]
                    : ($payload[$excelName] ?? $raw[$excelName] ?? $raw[$field] ?? '');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1) . $rowNo, $value);
            }
            $rowNo++;
        }

        foreach (range(1, count($columns)) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }

        $baseName = preg_replace('/[^A-Za-z0-9가-힣_-]+/u', '_', (string) ($format['format_name'] ?? $importType)) ?: 'evidences';
        $filename = $baseName . '_data_' . date('Ymd_His') . '.xlsx';

        if (ob_get_length()) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"evidences.xlsx\"; filename*=UTF-8''" . rawurlencode($filename));
        header('Cache-Control: max-age=0');
        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }

    public function apiSeedRowsTrash(): void
    {
        $_GET['status'] = 'DELETED';
        $this->apiSeedRows();
    }

    public function apiUploadBatchRows(): void
    {
        $batchId = trim((string) ($_GET['batch_id'] ?? ''));
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                NULL AS batch_id,
                source_type,
                0 AS row_no,
                raw_json AS raw_payload,
                mapped_payload_json AS mapped_payload,
                CASE
                    WHEN evidence_status = 'ERROR' OR transaction_status = 'ERROR' THEN 'ERROR'
                    WHEN evidence_status = 'DUPLICATED' OR transaction_status = 'DUPLICATED' THEN 'DUPLICATED'
                    WHEN transaction_status = 'PROCESSING' THEN 'PROCESSING'
                    WHEN " . $this->evidenceCreatedTransactionSql() . " THEN 'PROCESSED'
                    ELSE 'READY'
                END AS status,
                error_message,
                " . $this->evidenceTransactionIdSelect() . ",
                latest_imported_at AS processed_at,
                created_at,
                updated_at
            FROM ledger_data_evidences
            WHERE deleted_at IS NULL
              AND (:batch_id_empty = '' OR DATE(latest_imported_at) = :batch_id)
            ORDER BY COALESCE(evidence_date, DATE(latest_imported_at), DATE(created_at)) DESC, latest_imported_at DESC, created_at DESC
        ");
        $stmt->execute([
            ':batch_id_empty' => $batchId,
            ':batch_id' => $batchId,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $index => &$row) {
            $row['row_no'] = $index + 1;
            $row['raw_payload'] = json_decode((string) ($row['raw_payload'] ?? ''), true) ?: [];
            $row['mapped_payload'] = json_decode((string) ($row['mapped_payload'] ?? ''), true) ?: [];
            if (self::normalizeDataType((string) ($row['source_type'] ?? '')) === 'BANK_TRANSACTION') {
                $row['mapped_payload'] = $this->normalizeBankTransactionPayload($row['mapped_payload']);
            }
            $row['mapped_payload'] = $this->normalizeEvidenceMappedPayloadForResponse($row['mapped_payload']);
        }
        unset($row);
        $this->json(['success' => true, 'data' => ['batch' => null, 'rows' => $rows]]);
        return;
        if ($batchId === '') {
            $this->json(['success' => false, 'message' => '업로드 배치 ID가 없습니다.'], 400);
            return;
        }

        $batch = $this->uploadBatch($batchId);
        if (!$batch) {
            $this->json(['success' => false, 'message' => '업로드 배치를 찾을 수 없습니다.'], 404);
            return;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                id,
                NULL AS batch_id,
                source_type,
                0 AS row_no,
                raw_json AS raw_payload,
                mapped_payload_json AS mapped_payload,
                evidence_status AS status,
                error_message,
                " . $this->evidenceTransactionIdSelect() . ",
                latest_imported_at AS processed_at,
                created_at,
                updated_at
            FROM ledger_data_evidences
            WHERE DATE(latest_imported_at) = :batch_id
            ORDER BY latest_imported_at DESC
        ");
        $stmt->execute([':batch_id' => $batchId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['raw_payload'] = json_decode((string) ($row['raw_payload'] ?? ''), true) ?: [];
            $row['mapped_payload'] = json_decode((string) ($row['mapped_payload'] ?? ''), true) ?: [];
            $row['mapped_payload'] = $this->normalizeEvidenceMappedPayloadForResponse($row['mapped_payload']);
            if (!empty($row['error_message'])) {
                $row['error_message'] = $this->formatTransactionCreateError(
                    (string) $row['error_message'],
                    is_array($row['mapped_payload']) ? $row['mapped_payload'] : [],
                    (int) ($row['row_no'] ?? 0)
                );
            }
        }
        unset($row);

        $this->json(['success' => true, 'data' => [
            'batch' => $batch,
            'rows' => $rows,
        ]]);
    }

    public function apiEvidenceSummarySearch(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));

        $this->json([
            'success' => true,
            'items' => $this->searchEvidenceVoucherSummaryTexts($query, 10),
        ]);
    }

    private function searchEvidenceVoucherSummaryTexts(string $keyword, int $limit = 10): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        $limit = max(1, min($limit, 20));
        $stmt = $this->pdo->prepare("
            SELECT mapped_payload_json, updated_at, created_at
            FROM ledger_data_evidences
            WHERE deleted_at IS NULL
              AND mapped_payload_json LIKE :keyword
            ORDER BY updated_at DESC, created_at DESC
            LIMIT 1000
        ");
        $stmt->execute([
            ':keyword' => '%' . $keyword . '%',
        ]);

        $summaries = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $payload = json_decode((string) ($row['mapped_payload_json'] ?? ''), true);
            if (!is_array($payload)) {
                continue;
            }

            $lastUsedAt = (string) ($row['updated_at'] ?? $row['created_at'] ?? '');
            $rowSummaries = [];
            foreach (['voucher_summary_text', 'summary_text'] as $field) {
                $summaryText = trim((string) ($payload[$field] ?? ''));
                if (isset($rowSummaries[$summaryText])) {
                    continue;
                }
                $matched = function_exists('mb_stripos')
                    ? mb_stripos($summaryText, $keyword)
                    : stripos($summaryText, $keyword);
                if ($summaryText === '' || $matched === false) {
                    continue;
                }
                $rowSummaries[$summaryText] = true;

                if (!isset($summaries[$summaryText])) {
                    $summaries[$summaryText] = [
                        'summary_text' => $summaryText,
                        'used_count' => 0,
                        'last_used_at' => $lastUsedAt,
                    ];
                }

                $summaries[$summaryText]['used_count']++;
                if ($lastUsedAt !== '' && strcmp($lastUsedAt, (string) $summaries[$summaryText]['last_used_at']) > 0) {
                    $summaries[$summaryText]['last_used_at'] = $lastUsedAt;
                }
            }
        }

        $items = array_values($summaries);
        usort($items, static function (array $a, array $b): int {
            $countCompare = ((int) ($b['used_count'] ?? 0)) <=> ((int) ($a['used_count'] ?? 0));
            if ($countCompare !== 0) {
                return $countCompare;
            }

            return strcmp((string) ($b['last_used_at'] ?? ''), (string) ($a['last_used_at'] ?? ''));
        });

        return array_slice($items, 0, $limit);
    }

    public function apiSeedRowSave(): void
    {
        $payload = $this->requestPayload();
        $seedRowId = trim((string) ($payload['id'] ?? ''));
        $parsed = $payload['parsed_json'] ?? null;
        $raw = $payload['raw_json'] ?? null;
        if ($seedRowId === '' || !is_array($parsed)) {
            $this->json(['success' => false, 'message' => '수정할 Seed 행과 표준 필드값이 필요합니다.'], 400);
            return;
        }

        $this->ensureEvidenceSortColumns();
        $stmt = $this->pdo->prepare("
            SELECT source_type, format_id, evidence_status, transaction_status, voucher_status, mapped_payload_json, create_sort_no, status_sort_no, " . $this->evidenceTransactionIdSelect() . "
            FROM ledger_data_evidences
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $seedRowId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$current) {
            $this->json(['success' => false, 'message' => 'Seed 행을 찾을 수 없습니다.'], 404);
            return;
        }
        $transactionStatus = strtoupper(trim((string) ($current['transaction_status'] ?? 'NONE')));
        $voucherStatusCurrent = strtoupper(trim((string) ($current['voucher_status'] ?? 'NONE')));
        $transactionId = trim((string) ($current['transaction_id'] ?? ''));
        if (
            $transactionId !== ''
            || in_array($transactionStatus, ['CREATED', 'PROCESSED', 'DONE', 'COMPLETED', 'POSTED'], true)
            || in_array($voucherStatusCurrent, ['CREATED', 'PROCESSED', 'DONE', 'COMPLETED', 'POSTED'], true)
        ) {
            $this->json(['success' => false, 'message' => '거래 또는 전표 생성이 완료된 증빙원본은 수정할 수 없습니다.'], 400);
            return;
        }

        $this->ensureEvidenceBusinessInfoColumns();
        $this->ensureEvidenceSortColumns();
        $parsed = $this->mappedPayloadForStorage($parsed);
        if (self::normalizeDataType((string) ($current['source_type'] ?? '')) === 'BANK_TRANSACTION') {
            $parsed = $this->normalizeBankTransactionPayload($parsed);
        }
        $businessProjectMessages = $this->businessProjectRuleMessages($parsed);
        if ($businessProjectMessages !== []) {
            $this->json(['success' => false, 'message' => implode(' ', $businessProjectMessages)], 400);
            return;
        }
        $format = $this->formatWithColumns(trim((string) ($payload['format_id'] ?? $current['format_id'] ?? '')));
        $missingMessages = $this->requiredFormatMissingMessages($parsed, is_array($format['columns'] ?? null) ? $format['columns'] : []);
        if ($missingMessages !== []) {
            $this->json(['success' => false, 'message' => '필수 항목을 입력해야 저장할 수 있습니다. ' . implode(', ', $missingMessages)], 400);
            return;
        }
        $currentMapped = json_decode((string) ($current['mapped_payload_json'] ?? ''), true);
        $currentMapped = is_array($currentMapped) ? $currentMapped : [];
        foreach (['_status_sort_no', '_create_sort_no'] as $sortKey) {
            if (isset($currentMapped[$sortKey])) {
                $parsed[$sortKey] = $currentMapped[$sortKey];
            }
        }
        $createSortNo = (int) ($parsed['_create_sort_no'] ?? $current['create_sort_no'] ?? 0);
        $statusSortNo = (int) ($parsed['_status_sort_no'] ?? $current['status_sort_no'] ?? 0);
        $this->normalizeUploadAmountFields($parsed);
        $dataType = self::normalizeDataType((string) ($current['source_type'] ?? ''));
        if ($this->shouldSyncTaxInvoiceEvidenceClients($dataType)) {
            $clientSync = $this->syncTaxInvoiceEvidenceClientsFromSource($parsed, $seedRowId, $dataType);
            if (trim((string) ($parsed['client_id'] ?? '')) === '' && trim((string) ($clientSync['primary_client_id'] ?? '')) !== '') {
                $parsed['client_id'] = (string) $clientSync['primary_client_id'];
            }
            if (trim((string) ($parsed['client_id'] ?? '')) !== '') {
                $clientName = $this->businessRefNameById('CLIENT', (string) $parsed['client_id']);
                if ($clientName !== null && $clientName !== '') {
                    $parsed['client_name'] = $clientName;
                }
            }
        }
        $evidenceDate = null;
        foreach (['transaction_date', 'evidence_date', 'purchase_datetime', 'purchase_date', 'approval_datetime', 'approval_date', 'write_date', 'written_date', 'issue_date'] as $dateKey) {
            $evidenceDate = $this->dateValueOrNull($parsed[$dateKey] ?? null);
            if ($evidenceDate !== null) {
                break;
            }
        }
        $rawSql = is_array($raw) ? "raw_json = :raw_json," : "";
        $voucherStatusSql = $dataType === 'BANK_TRANSACTION'
            ? "voucher_status = :voucher_status,"
            : "";
        $voucherErrorMessage = $voucherStatusSql !== ''
            ? $this->bankVoucherValidationMessage($parsed)
            : null;
        $params = [
            ':id' => $seedRowId,
            ':parsed_json' => $this->jsonEncodeForStorage($parsed),
            ':evidence_date' => $evidenceDate,
            ':client_id' => $this->businessRefIdForStorage('CLIENT', $parsed),
            ':project_id' => $this->businessRefIdForStorage('PROJECT', $parsed),
            ':employee_id' => $this->businessRefIdForStorage('EMPLOYEE', $parsed),
            ':bank_account_id' => $this->businessRefIdForStorage('ACCOUNT', $parsed),
            ':card_id' => $this->businessRefIdForStorage('CARD', $parsed),
            ':client_name' => $this->businessRefNameForStorage('CLIENT', $parsed),
            ':project_name' => $this->businessRefNameForStorage('PROJECT', $parsed),
            ':employee_name' => $this->businessRefNameForStorage('EMPLOYEE', $parsed),
            ':bank_account_name' => $this->businessRefNameForStorage('ACCOUNT', $parsed),
            ':card_name' => $this->businessRefNameForStorage('CARD', $parsed),
            ':error_message' => $voucherErrorMessage,
            ':create_sort_no' => $createSortNo > 0 ? $createSortNo : null,
            ':status_sort_no' => $statusSortNo > 0 ? $statusSortNo : null,
            ':actor' => ActorHelper::user(),
        ];
        if ($voucherStatusSql !== '') {
            $params[':voucher_status'] = $this->uploadVoucherStatus((string) ($current['source_type'] ?? ''), $parsed, 'READY');
        }
        if (is_array($raw)) {
            $params[':raw_json'] = $this->jsonEncodeForStorage($raw);
        }
        $this->pdo->prepare("
            UPDATE ledger_data_evidences
            SET mapped_payload_json = :parsed_json,
                evidence_date = :evidence_date,
                client_id = :client_id,
                project_id = :project_id,
                employee_id = :employee_id,
                bank_account_id = :bank_account_id,
                card_id = :card_id,
                client_name = :client_name,
                project_name = :project_name,
                employee_name = :employee_name,
                bank_account_name = :bank_account_name,
                card_name = :card_name,
                create_sort_no = :create_sort_no,
                status_sort_no = :status_sort_no,
                {$rawSql}
                {$voucherStatusSql}
                evidence_status = 'ACTIVE',
                transaction_status = 'NONE',
                error_message = :error_message,
                updated_at = NOW(),
                updated_by = :actor
            WHERE id = :id
        ")->execute($params);

        $responseRow = [
            'id' => $seedRowId,
            'source_type' => self::sourceTypeForDataType((string) ($current['source_type'] ?? '')),
            'import_type' => self::normalizeDataType((string) ($current['source_type'] ?? '')),
            'format_id' => trim((string) ($payload['format_id'] ?? $current['format_id'] ?? '')),
            'evidence_date' => $evidenceDate,
            'client_id' => $params[':client_id'],
            'project_id' => $params[':project_id'],
            'employee_id' => $params[':employee_id'],
            'bank_account_id' => $params[':bank_account_id'],
            'card_id' => $params[':card_id'],
            'client_name' => $params[':client_name'],
            'project_name' => $params[':project_name'],
            'employee_name' => $params[':employee_name'],
            'bank_account_name' => $params[':bank_account_name'],
            'card_name' => $params[':card_name'],
            'create_sort_no' => $createSortNo,
            'status_sort_no' => $statusSortNo,
            'raw_payload' => is_array($raw) ? $raw : [],
            'mapped_payload' => $this->normalizeEvidenceMappedPayloadForResponse($parsed),
            'evidence_status' => 'ACTIVE',
            'transaction_status' => 'NONE',
            'voucher_status' => $params[':voucher_status'] ?? (string) ($current['voucher_status'] ?? 'NONE'),
            'review_status' => 'NORMAL',
            'error_message' => $voucherErrorMessage,
            'process_status' => 'READY',
            'status' => 'READY',
        ];
        $this->applyReadinessToEvidenceRow($responseRow);

        $this->json(['success' => true, 'message' => 'Seed Data가 수정되었습니다.', 'data' => $responseRow]);
    }

    public function apiEvidenceCreate(): void
    {
        $payload = $this->requestPayload();
        $formatId = trim((string) ($payload['format_id'] ?? ''));
        $parsed = $payload['parsed_json'] ?? null;
        if ($formatId === '' || !is_array($parsed)) {
            $this->json(['success' => false, 'message' => '새 증빙을 생성할 양식과 입력값이 필요합니다.'], 400);
            return;
        }

        $format = $this->formatWithColumns($formatId);
        if (!$format) {
            $this->json(['success' => false, 'message' => '양식을 찾을 수 없습니다.'], 404);
            return;
        }

        $actor = ActorHelper::user();
        $dataType = self::normalizeDataType((string) ($format['data_type'] ?? ($payload['import_type'] ?? 'ETC')));
        $parsed = $this->mappedPayloadForStorage($parsed);
        $parsed['import_type'] = $parsed['import_type'] ?? $dataType;
        $parsed['data_type'] = $parsed['data_type'] ?? $dataType;
        if ($dataType === 'BANK_TRANSACTION') {
            $parsed = $this->normalizeBankTransactionPayload($parsed);
        }
        $businessProjectMessages = $this->businessProjectRuleMessages($parsed);
        if ($businessProjectMessages !== []) {
            $this->json(['success' => false, 'message' => implode(' ', $businessProjectMessages)], 400);
            return;
        }
        $missingMessages = $this->requiredFormatMissingMessages($parsed, is_array($format['columns'] ?? null) ? $format['columns'] : []);
        if ($missingMessages !== []) {
            $this->json(['success' => false, 'message' => '필수 항목을 입력해야 저장할 수 있습니다. ' . implode(', ', $missingMessages)], 400);
            return;
        }
        $this->normalizeUploadAmountFields($parsed);

        $evidenceId = UuidHelper::generate();
        $sourceKey = $this->seedSourceKey($parsed, $dataType);
        if ($sourceKey === null || $sourceKey === '') {
            $sourceKey = 'MANUAL-' . $evidenceId;
        }

        $raw = [];
        foreach ($this->formatColumnsInOrder($format['columns'] ?? []) as $column) {
            $index = (string) ($column['excel_column_index'] ?? $column['column_order'] ?? count($raw) + 1);
            $systemField = trim((string) ($column['system_field_name'] ?? ''));
            $columnName = trim((string) ($column['excel_column_name'] ?? $systemField ?? $index));
            $raw[$index] = [
                'column_index' => is_numeric($index) ? (int) $index : null,
                'column_name' => $columnName,
                'system_field_name' => $systemField,
                'is_required' => (int) ($column['is_required'] ?? 0),
                'is_reference_column' => (int) ($column['is_reference_column'] ?? 0),
                'value' => $systemField !== '' ? ($parsed[$systemField] ?? '') : ($parsed[$columnName] ?? ''),
            ];
        }

        $evidenceDate = null;
        foreach (['transaction_date', 'evidence_date', 'purchase_datetime', 'purchase_date', 'approval_datetime', 'approval_date', 'write_date', 'written_date', 'issue_date'] as $dateKey) {
            $evidenceDate = $this->dateValueOrNull($parsed[$dateKey] ?? null);
            if ($evidenceDate !== null) {
                break;
            }
        }
        $voucherStatus = $this->uploadVoucherStatus($dataType, $parsed, 'READY');
        $voucherErrorMessage = $dataType === 'BANK_TRANSACTION'
            ? $this->bankVoucherValidationMessage($parsed)
            : null;
        $this->ensureEvidenceBusinessInfoColumns();
        $this->ensureEvidenceSortColumns();
        $nextStatusSortNo = $this->nextEvidenceJsonSortNo('_status_sort_no', $dataType);
        $nextCreateSortNo = $this->nextEvidenceJsonSortNo('_create_sort_no');
        $parsed['_status_sort_no'] = $nextStatusSortNo;
        $parsed['_create_sort_no'] = $nextCreateSortNo;

        $this->pdo->prepare("
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
                 'ACTIVE', 'NONE', :voucher_status, 'NORMAL', :error_message,
                 NOW(), :raw_json, :mapped_payload_json, :created_by, :updated_by)
        ")->execute([
            ':id' => $evidenceId,
            ':source_type' => $dataType,
            ':source_key' => $sourceKey,
            ':format_id' => $formatId,
            ':evidence_date' => $evidenceDate,
            ':client_id' => $this->businessRefIdForStorage('CLIENT', $parsed),
            ':project_id' => $this->businessRefIdForStorage('PROJECT', $parsed),
            ':employee_id' => $this->businessRefIdForStorage('EMPLOYEE', $parsed),
            ':bank_account_id' => $this->businessRefIdForStorage('ACCOUNT', $parsed),
            ':card_id' => $this->businessRefIdForStorage('CARD', $parsed),
            ':client_name' => $this->businessRefNameForStorage('CLIENT', $parsed),
            ':project_name' => $this->businessRefNameForStorage('PROJECT', $parsed),
            ':employee_name' => $this->businessRefNameForStorage('EMPLOYEE', $parsed),
            ':bank_account_name' => $this->businessRefNameForStorage('ACCOUNT', $parsed),
            ':card_name' => $this->businessRefNameForStorage('CARD', $parsed),
            ':currency' => (string) ($parsed['currency'] ?? 'KRW'),
            ':supply_amount' => $this->number($parsed['supply_amount'] ?? null),
            ':vat_amount' => $this->number($parsed['vat_amount'] ?? null),
            ':total_amount' => $this->evidenceTotalAmountForStorage($parsed, $dataType),
            ':create_sort_no' => $nextCreateSortNo,
            ':status_sort_no' => $nextStatusSortNo,
            ':voucher_status' => $voucherStatus,
            ':error_message' => $voucherErrorMessage,
            ':raw_json' => $this->jsonEncodeForStorage($raw),
            ':mapped_payload_json' => $this->jsonEncodeForStorage($parsed),
            ':created_by' => $actor,
            ':updated_by' => $actor,
        ]);

        $this->json(['success' => true, 'id' => $evidenceId, 'message' => '새 증빙원본이 생성되었습니다.']);
    }

    public function apiEvidenceSplitChild(): void
    {
        $payload = $this->requestPayload();
        $evidenceId = trim((string) ($payload['evidence_id'] ?? $payload['id'] ?? ''));
        $processingItemId = trim((string) ($payload['processing_item_id'] ?? ''));
        if ($evidenceId === '' && $processingItemId === '') {
            $this->json(['success' => false, 'message' => '분할할 증빙원본을 선택해주세요.'], 400);
            return;
        }

        $itemModel = new ProcessingItemModel($this->pdo);
        $parentItem = null;
        if ($processingItemId !== '') {
            $parentItem = $itemModel->getById($processingItemId);
            if ($parentItem && isset($payload['children']) && is_array($payload['children'])) {
                $ancestorId = trim((string) ($parentItem['parent_item_id'] ?? ''));
                if ($ancestorId !== '') {
                    $parentItem = $itemModel->getById($ancestorId) ?: $parentItem;
                }
            }
        }

        if (!$parentItem) {
            $stmt = $this->pdo->prepare("
                SELECT *
                FROM ledger_data_evidences
                WHERE id = :id
                  AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([':id' => $evidenceId]);
            $evidence = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$evidence) {
                $this->json(['success' => false, 'message' => '증빙원본을 찾을 수 없습니다.'], 404);
                return;
            }
            $parentItem = $itemModel->ensureDefaultItemForEvidence($evidence);
        }

        if (!$parentItem) {
            $this->json(['success' => false, 'message' => '분할 기준 처리항목을 만들 수 없습니다.'], 400);
            return;
        }

        if (isset($payload['children']) && is_array($payload['children'])) {
            $result = $this->saveProcessingItemSplitChildren($parentItem, $payload['children']);
            $this->json($result, !empty($result['success']) ? 200 : 422);
            return;
        }

        $parentPayload = json_decode((string) ($parentItem['mapped_payload_json'] ?? ''), true);
        $parentPayload = is_array($parentPayload) ? $parentPayload : [];
        $blankPayload = $parentPayload;
        foreach (['quantity', 'unit_price', 'supply_amount', 'vat_amount', 'total_amount', 'amount', 'deposit_amount', 'withdraw_amount', 'deposit', 'withdraw'] as $key) {
            if (array_key_exists($key, $blankPayload)) {
                $blankPayload[$key] = 0;
            }
        }
        foreach (['description', 'memo', 'item_name', 'line_summary'] as $key) {
            if (array_key_exists($key, $blankPayload)) {
                $blankPayload[$key] = '';
            }
        }

        $children = [
            [
                'sort_no' => 1,
                'item_type' => 'SPLIT',
                'line_type' => $parentItem['line_type'] ?? $parentItem['source_type'] ?? null,
                'quantity' => $parentItem['quantity'] ?? null,
                'unit_price' => $parentItem['unit_price'] ?? null,
                'supply_amount' => $parentItem['supply_amount'] ?? null,
                'vat_amount' => $parentItem['vat_amount'] ?? null,
                'total_amount' => $parentItem['total_amount'] ?? null,
                'currency' => $parentItem['currency'] ?? 'KRW',
                'description' => $parentItem['description'] ?? null,
                'mapped_payload' => $parentPayload,
            ],
            [
                'sort_no' => 2,
                'item_type' => 'SPLIT',
                'line_type' => $parentItem['line_type'] ?? $parentItem['source_type'] ?? null,
                'quantity' => null,
                'unit_price' => null,
                'supply_amount' => 0,
                'vat_amount' => 0,
                'total_amount' => 0,
                'deposit_amount' => 0,
                'withdraw_amount' => 0,
                'currency' => $parentItem['currency'] ?? 'KRW',
                'description' => null,
                'mapped_payload' => $blankPayload,
            ],
        ];

        $result = (new ProcessingItemSplitService($this->pdo, $itemModel))
            ->split((string) ($parentItem['id'] ?? ''), $children, '증빙원본 자식행 추가', ActorHelper::user());

        $this->json($result + ['message' => ($result['message'] ?? '자식행이 추가되었습니다.')], !empty($result['success']) ? 200 : 422);
    }

    public function apiEvidenceDeleteProcessingChild(): void
    {
        $payload = $this->requestPayload();
        $processingItemId = trim((string) ($payload['processing_item_id'] ?? $payload['id'] ?? ''));
        if ($processingItemId === '') {
            $this->json(['success' => false, 'message' => '삭제할 자식행을 선택해주세요.'], 400);
            return;
        }

        $itemModel = new ProcessingItemModel($this->pdo);
        $item = $itemModel->getById($processingItemId);
        if (!$item) {
            $this->json(['success' => false, 'message' => '자식행을 찾을 수 없습니다.'], 404);
            return;
        }
        if (trim((string) ($item['parent_item_id'] ?? '')) === '') {
            $this->json(['success' => false, 'message' => '부모행은 이 버튼으로 삭제할 수 없습니다.'], 400);
            return;
        }

        $stmt = $this->pdo->prepare('DELETE FROM ledger_processing_items WHERE id = :id AND parent_item_id IS NOT NULL');
        $stmt->execute([':id' => $processingItemId]);

        $this->json(['success' => true, 'message' => '자식행을 삭제했습니다.']);
    }

    public function apiEvidenceUpdateProcessingChild(): void
    {
        $payload = $this->requestPayload();
        $processingItemId = trim((string) ($payload['processing_item_id'] ?? $payload['id'] ?? ''));
        $child = $payload['child'] ?? [];
        if (!is_array($child)) {
            $child = [];
        }
        if ($processingItemId === '') {
            $processingItemId = trim((string) ($child['id'] ?? $child['processing_item_id'] ?? ''));
        }
        if ($processingItemId === '') {
            $this->json(['success' => false, 'message' => '수정할 자식행을 선택해주세요.'], 400);
            return;
        }

        $itemModel = new ProcessingItemModel($this->pdo);
        $item = $itemModel->getById($processingItemId);
        if (!$item) {
            $this->json(['success' => false, 'message' => '자식행을 찾을 수 없습니다.'], 404);
            return;
        }
        $parentItemId = trim((string) ($item['parent_item_id'] ?? ''));
        if ($parentItemId === '') {
            $this->json(['success' => false, 'message' => '부모행은 자식 수정 모달에서 수정할 수 없습니다.'], 400);
            return;
        }
        $parentItem = $itemModel->getById($parentItemId);
        if (!$parentItem) {
            $this->json(['success' => false, 'message' => '부모행을 찾을 수 없습니다.'], 404);
            return;
        }

        $payloadJson = $child['mapped_payload'] ?? [];
        if (!is_array($payloadJson)) {
            $payloadJson = [];
        }
        $payloadJson = $this->mappedPayloadForStorage($payloadJson);
        $child['mapped_payload'] = $payloadJson;
        $missingRequired = $this->processingSplitRequiredMissingMessages($parentItem, [$child + ['mapped_payload' => $payloadJson]]);
        if ($missingRequired !== []) {
            $this->json([
                'success' => false,
                'message' => '필수 항목을 입력해야 저장할 수 있습니다. ' . implode(', ', array_slice($missingRequired, 0, 5)) . (count($missingRequired) > 5 ? ' 외 ' . (count($missingRequired) - 5) . '건' : ''),
            ], 422);
            return;
        }

        $actor = ActorHelper::user();
        $timestamp = date('Y-m-d H:i:s');
        $itemModel->update($processingItemId, [
            'quantity' => $this->amountOrNull($child['quantity'] ?? $payloadJson['quantity'] ?? null),
            'unit_price' => $this->amountOrNull($child['unit_price'] ?? $payloadJson['unit_price'] ?? null),
            'supply_amount' => $this->amountOrNull($child['supply_amount'] ?? $payloadJson['supply_amount'] ?? null),
            'vat_amount' => $this->amountOrNull($child['vat_amount'] ?? $payloadJson['vat_amount'] ?? null),
            'total_amount' => $this->amountOrNull($child['total_amount'] ?? $payloadJson['total_amount'] ?? null),
            'description' => $child['description'] ?? ($payloadJson['description'] ?? null),
            'memo' => $child['memo'] ?? ($payloadJson['memo'] ?? null),
            'mapped_payload_json' => json_encode($payloadJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => $timestamp,
            'updated_by' => $actor,
        ]);

        $this->json(['success' => true, 'message' => '자식 항목을 수정했습니다.']);
    }
    public function apiEvidenceBulkSave(): void
    {
        $payload = $this->requestPayload();
        $ids = $this->seedRowIdsFromPayload($payload);
        $patch = $payload['parsed_patch'] ?? [];
        $mode = strtolower(trim((string) ($payload['mode'] ?? 'fill_blank')));

        if ($ids === [] || !is_array($patch) || $patch === []) {
            $this->json(['success' => false, 'message' => '일괄보정할 증빙원본과 항목을 선택하세요.'], 400);
            return;
        }
        if (!in_array($mode, ['fill_blank', 'overwrite'], true)) {
            $mode = 'fill_blank';
        }

        $this->ensureEvidenceBusinessInfoColumns();
        [$inSql, $params] = $this->placeholdersForIds($ids, 'bulk_seed');
        $stmt = $this->pdo->prepare("
            SELECT id, source_type, evidence_status, transaction_status, voucher_status, mapped_payload_json, " . $this->evidenceTransactionIdSelect() . "
            FROM ledger_data_evidences
            WHERE id IN ({$inSql})
              AND deleted_at IS NULL
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $update = $this->pdo->prepare("
            UPDATE ledger_data_evidences
            SET mapped_payload_json = :parsed_json,
                client_id = :client_id,
                project_id = :project_id,
                employee_id = :employee_id,
                bank_account_id = :bank_account_id,
                card_id = :card_id,
                client_name = :client_name,
                project_name = :project_name,
                employee_name = :employee_name,
                bank_account_name = :bank_account_name,
                card_name = :card_name,
                voucher_status = :voucher_status,
                evidence_status = 'ACTIVE',
                transaction_status = 'NONE',
                error_message = :error_message,
                updated_at = NOW(),
                updated_by = :actor
            WHERE id = :id
        ");

        $actor = ActorHelper::user();
        $updated = 0;
        $locked = 0;
        $unchanged = 0;

        foreach ($rows as $row) {
            $transactionStatus = strtoupper(trim((string) ($row['transaction_status'] ?? 'NONE')));
            $voucherStatusCurrent = strtoupper(trim((string) ($row['voucher_status'] ?? 'NONE')));
            $transactionId = trim((string) ($row['transaction_id'] ?? ''));
            if (
                $transactionId !== ''
                || in_array($transactionStatus, ['CREATED', 'PROCESSED', 'DONE', 'COMPLETED', 'POSTED'], true)
                || in_array($voucherStatusCurrent, ['CREATED', 'PROCESSED', 'DONE', 'COMPLETED', 'POSTED'], true)
            ) {
                $locked++;
                continue;
            }

            $mapped = json_decode((string) ($row['mapped_payload_json'] ?? ''), true);
            $mapped = is_array($mapped) ? $mapped : [];
            $next = $mapped;
            foreach ($patch as $key => $value) {
                $field = trim((string) $key);
                if ($field === '') {
                    continue;
                }
                if ($mode === 'fill_blank' && !$this->isBlankValue($next[$field] ?? null)) {
                    continue;
                }
                $next[$field] = is_scalar($value) || $value === null ? $value : '';
            }

            if ($next === $mapped) {
                $unchanged++;
                continue;
            }

            $next = $this->mappedPayloadForStorage($next);
            if (self::normalizeDataType((string) ($row['source_type'] ?? '')) === 'BANK_TRANSACTION') {
                $next = $this->normalizeBankTransactionPayload($next);
            }
            $this->normalizeUploadAmountFields($next);
            $voucherStatus = $this->uploadVoucherStatus((string) ($row['source_type'] ?? ''), $next, 'READY');
            $voucherErrorMessage = self::normalizeDataType((string) ($row['source_type'] ?? '')) === 'BANK_TRANSACTION'
                ? $this->bankVoucherValidationMessage($next)
                : null;

            $update->execute([
                ':id' => (string) $row['id'],
                ':parsed_json' => $this->jsonEncodeForStorage($next),
                ':client_id' => $this->businessRefIdForStorage('CLIENT', $next),
                ':project_id' => $this->businessRefIdForStorage('PROJECT', $next),
                ':employee_id' => $this->businessRefIdForStorage('EMPLOYEE', $next),
                ':bank_account_id' => $this->businessRefIdForStorage('ACCOUNT', $next),
                ':card_id' => $this->businessRefIdForStorage('CARD', $next),
                ':client_name' => $this->businessRefNameForStorage('CLIENT', $next),
                ':project_name' => $this->businessRefNameForStorage('PROJECT', $next),
                ':employee_name' => $this->businessRefNameForStorage('EMPLOYEE', $next),
                ':bank_account_name' => $this->businessRefNameForStorage('ACCOUNT', $next),
                ':card_name' => $this->businessRefNameForStorage('CARD', $next),
                ':voucher_status' => $voucherStatus,
                ':error_message' => $voucherErrorMessage,
                ':actor' => $actor,
            ]);
            $updated++;
        }

        $message = "일괄보정 완료: 변경 {$updated}건, 유지 {$unchanged}건";
        if ($locked > 0) {
            $message .= ", 생성완료 잠금 {$locked}건";
        }
        $this->json([
            'success' => true,
            'message' => $message,
            'updated_count' => $updated,
            'unchanged_count' => $unchanged,
            'locked_count' => $locked,
        ]);
    }

    private function saveProcessingItemSplitChildren(array $parentItem, array $children): array
    {
        $parentItemId = trim((string) ($parentItem['id'] ?? ''));
        if ($parentItemId === '') {
            return ['success' => false, 'message' => '분할 기준 처리항목이 없습니다.'];
        }

        $children = array_values(array_filter($children, static fn($child): bool => is_array($child)));
        if (count($children) < 2) {
            return ['success' => false, 'message' => '분할 항목은 2개 이상 필요합니다.'];
        }

        foreach ($children as &$child) {
            $payload = $child['mapped_payload'] ?? [];
            $child['mapped_payload'] = is_array($payload)
                ? $this->mappedPayloadForStorage($payload)
                : [];
        }
        unset($child);

        foreach ($this->processingSplitAmountFields($parentItem, $children) as $field => $parentAmount) {
            $sum = 0.0;
            foreach ($children as $child) {
                $sum += (float) ($this->processingSplitChildAmountValue($child, $field) ?? 0);
            }
            if (abs((float) $parentAmount - $sum) > 0.01) {
                return [
                    'success' => false,
                    'message' => '분할 합계가 부모 ' . $field . ' 금액과 일치하지 않습니다. 부모=' . $this->formatAmountForMessage((float) $parentAmount) . ' 자식합계=' . $this->formatAmountForMessage($sum),
                ];
            }
        }
        $missingRequired = $this->processingSplitRequiredMissingMessages($parentItem, $children);
        if ($missingRequired !== []) {
            return [
                'success' => false,
                'message' => '필수 항목을 입력해야 저장할 수 있습니다. ' . implode(', ', array_slice($missingRequired, 0, 5)) . (count($missingRequired) > 5 ? ' 외 ' . (count($missingRequired) - 5) . '건' : ''),
            ];
        }

        $itemModel = new ProcessingItemModel($this->pdo);
        $existingChildren = [];
        foreach ($itemModel->getBySource((string) ($parentItem['source_table'] ?? ''), (string) ($parentItem['source_id'] ?? '')) as $item) {
            if (trim((string) ($item['parent_item_id'] ?? '')) === $parentItemId) {
                $existingChildren[(string) ($item['id'] ?? '')] = $item;
            }
        }

        $actor = ActorHelper::user();
        $parentDisplayPath = $this->processingParentDisplayPath($parentItem);
        $timestamp = date('Y-m-d H:i:s');
        $splitGroupId = trim((string) ($parentItem['split_group_id'] ?? '')) ?: UuidHelper::generate();
        $saved = [];

        $this->pdo->beginTransaction();
        try {
            $itemModel->update($parentItemId, [
                'item_status' => 'SPLIT',
                'is_current' => 0,
                'split_group_id' => $splitGroupId,
                'sort_no' => ctype_digit($parentDisplayPath) ? (int) $parentDisplayPath : (int) ($parentItem['sort_no'] ?? 1),
                'display_path' => $parentDisplayPath,
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ]);

            foreach ($children as $index => $child) {
                $sortNo = max(1, (int) ($child['sort_no'] ?? ($index + 1)));
                $childId = trim((string) ($child['id'] ?? $child['processing_item_id'] ?? ''));
                $existing = $childId !== '' ? ($existingChildren[$childId] ?? null) : null;
                $payload = $child['mapped_payload'] ?? [];
                if (!is_array($payload)) {
                    $payload = [];
                }
                $data = [
                    'sort_no' => $sortNo,
                    'display_path' => $parentDisplayPath . '-' . $sortNo,
                    'quantity' => $this->amountOrNull($child['quantity'] ?? $payload['quantity'] ?? null),
                    'unit_price' => $this->amountOrNull($child['unit_price'] ?? $payload['unit_price'] ?? null),
                    'supply_amount' => $this->amountOrNull($child['supply_amount'] ?? $payload['supply_amount'] ?? null),
                    'vat_amount' => $this->amountOrNull($child['vat_amount'] ?? $payload['vat_amount'] ?? null),
                    'total_amount' => $this->amountOrNull($child['total_amount'] ?? $payload['total_amount'] ?? null),
                    'description' => $child['description'] ?? ($payload['description'] ?? null),
                    'memo' => $child['memo'] ?? ($payload['memo'] ?? null),
                    'mapped_payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'item_status' => 'ACTIVE',
                    'is_current' => 1,
                    'updated_at' => $timestamp,
                    'updated_by' => $actor,
                ];

                if ($existing) {
                    $itemModel->update($childId, $data);
                    $saved[] = $itemModel->getById($childId) ?: ($data + ['id' => $childId]);
                    unset($existingChildren[$childId]);
                    continue;
                }

                $newId = UuidHelper::generate();
                $insert = array_merge($this->processingChildBasePayload($parentItem), $data, [
                    'id' => $newId,
                    'parent_item_id' => $parentItemId,
                    'source_item_id' => $parentItemId,
                    'lineage_root_id' => $parentItem['lineage_root_id'] ?? $parentItemId,
                    'split_group_id' => $splitGroupId,
                    'item_type' => 'SPLIT',
                    'created_at' => $timestamp,
                    'created_by' => $actor,
                ]);
                $itemModel->insert($insert);
                $saved[] = $itemModel->getById($newId) ?: $insert;
            }

            foreach (array_keys($existingChildren) as $deletedChildId) {
                $itemModel->update($deletedChildId, [
                    'deleted_at' => $timestamp,
                    'deleted_by' => $actor,
                    'is_current' => 0,
                    'item_status' => 'DELETED',
                    'updated_at' => $timestamp,
                    'updated_by' => $actor,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true, 'children' => $saved, 'message' => '분할 항목을 저장했습니다.'];
    }

    private function processingParentDisplayPath(array $parentItem): string
    {
        if (($parentItem['source_table'] ?? '') === 'ledger_data_evidences') {
            $sourceId = trim((string) ($parentItem['source_id'] ?? ''));
            if ($sourceId !== '') {
                $stmt = $this->pdo->prepare('SELECT mapped_payload_json FROM ledger_data_evidences WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $sourceId]);
                $mapped = json_decode((string) ($stmt->fetchColumn() ?: ''), true);
                if (is_array($mapped)) {
                    foreach (['_status_sort_no', '_create_sort_no', '_row_no', '_upload_row_no'] as $key) {
                        $value = trim((string) ($mapped[$key] ?? ''));
                        if ($value !== '' && $value !== '0') {
                            return $value;
                        }
                    }
                }
            }
        }

        $displayPath = trim((string) ($parentItem['display_path'] ?? ''));
        if ($displayPath !== '') {
            return $displayPath;
        }
        $sortNo = trim((string) ($parentItem['sort_no'] ?? ''));
        return $sortNo !== '' && $sortNo !== '0' ? $sortNo : '1';
    }

    private function processingSplitRequiredMissingMessages(array $parentItem, array $children): array
    {
        $sourceTable = (string) ($parentItem['source_table'] ?? '');
        $sourceId = (string) ($parentItem['source_id'] ?? '');
        if ($sourceTable !== 'ledger_data_evidences' || $sourceId === '') {
            return [];
        }
        $stmt = $this->pdo->prepare("
            SELECT c.excel_column_name, c.system_field_name
            FROM ledger_data_evidences e
            JOIN ledger_data_format_columns c ON c.format_id = e.format_id
            WHERE e.id = :id
              AND COALESCE(c.is_required, 0) = 1
            ORDER BY c.column_order, c.excel_column_index, c.excel_column_name
        ");
        $stmt->execute([':id' => $sourceId]);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($columns === []) {
            return [];
        }
        $messages = [];
        foreach (array_values($children) as $index => $child) {
            if (!is_array($child)) {
                continue;
            }
            $payload = is_array($child['mapped_payload'] ?? null) ? $child['mapped_payload'] : [];
            foreach ($columns as $column) {
                $key = trim((string) ($column['system_field_name'] ?? ''));
                if ($key === '') {
                    $key = trim((string) ($column['excel_column_name'] ?? ''));
                }
                if ($key === '') {
                    continue;
                }
                if ($this->isProcessingSplitExcludedField((string) ($parentItem['source_type'] ?? ''), $key, (string) ($column['excel_column_name'] ?? ''))) {
                    continue;
                }
                if ($this->isBlankValue($payload[$key] ?? null)) {
                    $label = trim((string) ($column['excel_column_name'] ?? $key));
                    $messages[] = ($index + 1) . '행 ' . ($label !== '' ? $label : $key);
                }
            }
        }
        return $messages;
    }

    private function processingSplitAmountFields(array $parentItem, array $children): array
    {
        $sourceType = self::normalizeDataType((string) ($parentItem['source_type'] ?? ''));
        $parentPayload = json_decode((string) ($parentItem['mapped_payload_json'] ?? ''), true);
        $parentPayload = is_array($parentPayload) ? $parentPayload : [];
        if ($sourceType === 'BANK_TRANSACTION') {
            $fields = [];
            foreach (['deposit_amount', 'withdraw_amount'] as $field) {
                $value = $this->processingSplitParentAmountValue($parentItem, $parentPayload, $field);
                if ($value !== null) {
                    $fields[$field] = $value;
                }
            }
            return $fields;
        }
        $fields = [];
        foreach (array_keys($parentPayload) as $field) {
            if ($this->isProcessingSplitAmountField((string) $field) && !$this->isProcessingSplitExcludedField($sourceType, (string) $field, (string) $field)) {
                $value = $this->amountOrNull($parentPayload[$field] ?? null);
                if ($value !== null) {
                    $fields[(string) $field] = $value;
                }
            }
        }
        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }
            $payload = is_array($child['mapped_payload'] ?? null) ? $child['mapped_payload'] : [];
            foreach (array_keys($payload) as $field) {
                $field = (string) $field;
                if (isset($fields[$field]) || !$this->isProcessingSplitAmountField($field) || $this->isProcessingSplitExcludedField($sourceType, $field, $field)) {
                    continue;
                }
                $value = $this->amountOrNull($parentPayload[$field] ?? $parentItem[$field] ?? null);
                if ($value !== null) {
                    $fields[$field] = $value;
                }
            }
        }
        foreach (['supply_amount', 'vat_amount', 'total_amount'] as $field) {
            if (!isset($fields[$field]) && array_key_exists($field, $parentPayload)) {
                $value = $this->amountOrNull($parentPayload[$field] ?? $parentItem[$field] ?? null);
                if ($value !== null) {
                    $fields[$field] = $value;
                }
            }
        }
        return $fields;
    }

    private function processingSplitParentAmountValue(array $parentItem, array $parentPayload, string $field): ?float
    {
        $keys = $field === 'withdraw_amount'
            ? ['withdraw_amount', 'withdrawal_amount']
            : [$field];
        foreach ($keys as $key) {
            $value = $this->amountOrNull($parentPayload[$key] ?? $parentItem[$key] ?? null);
            if ($value !== null) {
                return abs((float) $value);
            }
        }
        return null;
    }

    private function processingSplitChildAmountValue(array $child, string $field): ?float
    {
        $payload = is_array($child['mapped_payload'] ?? null) ? $child['mapped_payload'] : [];
        $keys = $field === 'withdraw_amount'
            ? ['withdraw_amount', 'withdrawal_amount']
            : [$field];
        foreach ($keys as $key) {
            $value = $this->amountOrNull($child[$key] ?? $payload[$key] ?? null);
            if ($value !== null) {
                return abs((float) $value);
            }
        }
        return null;
    }

    private function isProcessingSplitExcludedField(string $sourceType, string $field, string $label = ''): bool
    {
        $sourceType = self::normalizeDataType($sourceType);
        $fieldText = strtolower(trim($field));
        $labelText = trim($label);
        $text = $fieldText . ' ' . $labelText;
        if (preg_match('/balance_amount|check_bill_amount|balance|거래후잔액|잔액|수표어음/u', $text)) {
            return true;
        }
        if ($sourceType === 'BANK_TRANSACTION' && $this->isProcessingSplitAmountField($fieldText)) {
            return !preg_match('/(^|_)(deposit|withdraw|withdrawal)(_|$)|입금|출금/u', $text);
        }
        return false;
    }

    private function isProcessingSplitAmountField(string $field): bool
    {
        $field = strtolower(trim($field));
        if ($field === '') {
            return false;
        }
        if (in_array($field, ['balance_amount', 'unit_price', 'foreign_unit_price', 'exchange_rate', 'rate'], true)) {
            return false;
        }
        return (bool) preg_match('/(^|_)(amount|vat|tax|fee|charge|duty|deposit|withdraw|withdrawal|supply|total|settlement|gross|withholding)(_|$)/', $field);
    }

    private function processingChildBasePayload(array $parent): array
    {
        return [
            'source_domain' => $parent['source_domain'] ?? null,
            'source_table' => $parent['source_table'] ?? '',
            'source_id' => $parent['source_id'] ?? '',
            'source_type' => $parent['source_type'] ?? '',
            'line_type' => $parent['line_type'] ?? null,
            'transaction_status' => 'NONE',
            'voucher_status' => 'NONE',
            'readiness_status' => $parent['readiness_status'] ?? 'UNKNOWN',
            'correction_status' => 'NONE',
            'item_date' => $parent['item_date'] ?? null,
            'client_id' => $parent['client_id'] ?? null,
            'project_id' => $parent['project_id'] ?? null,
            'employee_id' => $parent['employee_id'] ?? null,
            'bank_account_id' => $parent['bank_account_id'] ?? null,
            'card_id' => $parent['card_id'] ?? null,
            'account_id' => $parent['account_id'] ?? null,
            'currency' => $parent['currency'] ?? 'KRW',
            'raw_json' => $parent['raw_json'] ?? null,
        ];
    }

    private function formatAmountForMessage(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function isBlankValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_array($value)) {
            return $value === [];
        }
        return trim((string) $value) === '';
    }

    public function apiSeedRowsStatus(): void
    {
        $payload = $this->requestPayload();
        $ids = $this->seedRowIdsFromPayload($payload);
        $status = strtoupper(trim((string) ($payload['process_status'] ?? $payload['status'] ?? '')));
        if ($ids === [] || !in_array($status, ['READY', 'ERROR', 'DUPLICATED'], true)) {
            $this->json(['success' => false, 'message' => '상태를 변경할 Seed Data와 상태값을 선택하세요.'], 400);
            return;
        }

        [$inSql, $params] = $this->placeholdersForIds($ids, 'seed_id');
        $evidenceStatus = $status === 'READY' ? 'ACTIVE' : $status;
        $params[':status'] = $evidenceStatus;
        $params[':status_check'] = $status;
        $params[':actor'] = ActorHelper::user();
        $stmt = $this->pdo->prepare("
            UPDATE ledger_data_evidences
            SET evidence_status = :status,
                transaction_status = CASE WHEN :status_check = 'READY' THEN 'NONE' ELSE transaction_status END,
                error_message = CASE WHEN :status_check = 'READY' THEN NULL ELSE error_message END,
                updated_at = NOW(),
                updated_by = :actor
            WHERE id IN ({$inSql})
              AND transaction_status IN ('NONE', 'ERROR', 'DUPLICATED')
              AND deleted_at IS NULL
        ");
        $stmt->execute($params);

        $this->json(['success' => true, 'message' => '선택 Seed Data 상태가 변경되었습니다.']);
    }

    public function apiSeedRowsReorder(): void
    {
        $payload = $this->requestPayload();
        $changes = $payload['changes'] ?? [];
        if (is_string($changes)) {
            $decoded = json_decode($changes, true);
            $changes = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($changes) || $changes === []) {
            $this->json(['success' => false, 'message' => '순서변경 데이터가 없습니다.'], 400);
            return;
        }

        $rows = [];
        foreach ($changes as $change) {
            if (!is_array($change)) {
                continue;
            }
            $id = trim((string) ($change['id'] ?? ''));
            $rowNo = (int) ($change['newSortNo'] ?? $change['row_no'] ?? $change['sort_no'] ?? 0);
            if ($id === '' || $rowNo < 1) {
                continue;
            }
            $rows[$id] = $rowNo;
        }
        if ($rows === []) {
            $this->json(['success' => false, 'message' => '순서변경 데이터가 올바르지 않습니다.'], 400);
            return;
        }

        $firstChange = is_array(reset($changes)) ? reset($changes) : [];
        $scope = strtolower(trim((string) ($payload['scope'] ?? $payload['sort_scope'] ?? $firstChange['scope'] ?? $firstChange['sort_scope'] ?? 'create')));
        $sortKey = $scope === 'status' ? '_status_sort_no' : '_create_sort_no';
        $sortColumn = $scope === 'status' ? 'status_sort_no' : 'create_sort_no';
        $importType = self::normalizeDataType((string) ($payload['import_type'] ?? $payload['data_type'] ?? $firstChange['import_type'] ?? $firstChange['data_type'] ?? ''));
        $actor = ActorHelper::user();
        $this->ensureEvidenceSortColumns();

        $this->pdo->beginTransaction();
        try {
            [$inSql, $params] = $this->placeholdersForIds(array_keys($rows), 'reorder_id');
            $sql = "
                SELECT id, mapped_payload_json, `{$sortColumn}` AS current_sort_no
                FROM ledger_data_evidences
                WHERE id IN ({$inSql})
                  AND deleted_at IS NULL
            ";
        if ($scope === 'status') {
            if ($importType === '') {
                $this->pdo->rollBack();
                $this->json(['success' => false, 'message' => '증빙원본 순서 변경에는 자료유형이 필요합니다.'], 400);
                return;
            }
            $typePlaceholders = [];
            foreach (self::queryDataTypes($importType) as $index => $type) {
                $key = ':import_type_' . $index;
                $typePlaceholders[] = $key;
                $params[$key] = $type;
            }
            $sql .= ' AND source_type IN (' . implode(', ', $typePlaceholders) . ')';
        }
            $select = $this->pdo->prepare($sql);
            $select->execute($params);
            $storedRows = $select->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $update = $this->pdo->prepare("
                UPDATE ledger_data_evidences
                SET mapped_payload_json = :mapped_payload_json,
                    `{$sortColumn}` = :sort_no,
                    updated_at = NOW(),
                    updated_by = :actor
                WHERE id = :id
            ");
            foreach ($storedRows as $storedRow) {
                $id = (string) ($storedRow['id'] ?? '');
                if ($id === '' || !isset($rows[$id])) {
                    continue;
                }
                $mappedPayload = json_decode((string) ($storedRow['mapped_payload_json'] ?? ''), true);
                $mappedPayload = is_array($mappedPayload) ? $mappedPayload : [];
                $currentColumnSortNo = is_numeric($storedRow['current_sort_no'] ?? null) ? max(0, (int) $storedRow['current_sort_no']) : 0;
                $currentPayloadSortNo = $this->evidencePayloadSortNo(['mapped_payload' => $mappedPayload], $sortKey);
                if ($currentColumnSortNo === $rows[$id] && $currentPayloadSortNo === $rows[$id]) {
                    continue;
                }
                $mappedPayload[$sortKey] = $rows[$id];
                $update->execute([
                    ':id' => $id,
                    ':mapped_payload_json' => $this->jsonEncodeForStorage($mappedPayload),
                    ':sort_no' => $rows[$id],
                    ':actor' => $actor,
                ]);
                if ($scope === 'status') {
                    $this->syncProcessingItemDisplayPathsForEvidence($id, $rows[$id], $actor);
                }
            }
            if ($scope === 'status') {
                $this->normalizeEvidenceStatusSortNoForType($importType, $actor, $rows);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->json(['success' => false, 'message' => '순서변경 저장에 실패했습니다.'], 500);
            return;
        }

        $this->json(['success' => true, 'message' => '순서가 변경되었습니다.']);
    }

    private function syncProcessingItemDisplayPathsForEvidence(string $evidenceId, int $rowNo, string $actor): void
    {
        if ($evidenceId === '' || $rowNo < 1 || !$this->tableExists('ledger_processing_items')) {
            return;
        }

        $stmt = $this->pdo->prepare("
            SELECT id, parent_item_id, sort_no, display_path, created_at
            FROM ledger_processing_items
            WHERE source_table = 'ledger_data_evidences'
              AND source_id = :source_id
              AND deleted_at IS NULL
            ORDER BY parent_item_id ASC, sort_no ASC, created_at ASC
        ");
        $stmt->execute([':source_id' => $evidenceId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($items === []) {
            return;
        }

        $childrenByParent = [];
        $roots = [];
        foreach ($items as $item) {
            $id = trim((string) ($item['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $parentId = trim((string) ($item['parent_item_id'] ?? ''));
            if ($parentId === '') {
                $roots[] = $item;
            } else {
                $childrenByParent[$parentId][] = $item;
            }
        }

        $sortItems = static function (array &$rows): void {
            usort($rows, static function (array $a, array $b): int {
                $sortA = (int) ($a['sort_no'] ?? 0);
                $sortB = (int) ($b['sort_no'] ?? 0);
                if ($sortA !== $sortB) {
                    return $sortA <=> $sortB;
                }
                return strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? ''));
            });
        };
        $sortItems($roots);
        foreach ($childrenByParent as &$children) {
            $sortItems($children);
        }
        unset($children);

        $update = $this->pdo->prepare("
            UPDATE ledger_processing_items
            SET sort_no = :sort_no,
                display_path = :display_path,
                updated_at = NOW(),
                updated_by = :actor
            WHERE id = :id
        ");

        $walk = function (array $item, string $displayPath, int $sortNo) use (&$walk, $childrenByParent, $update, $actor): void {
            $id = trim((string) ($item['id'] ?? ''));
            if ($id === '') {
                return;
            }

            $update->execute([
                ':id' => $id,
                ':sort_no' => $sortNo,
                ':display_path' => $displayPath,
                ':actor' => $actor,
            ]);

            $children = $childrenByParent[$id] ?? [];
            foreach (array_values($children) as $index => $child) {
                $childSortNo = $index + 1;
                $walk($child, $displayPath . '-' . $childSortNo, $childSortNo);
            }
        };

        foreach (array_values($roots) as $index => $root) {
            $rootDisplayPath = (string) ($index === 0 ? $rowNo : ($rowNo . '-' . ($index + 1)));
            $rootSortNo = $index === 0 ? $rowNo : $index + 1;
            $walk($root, $rootDisplayPath, $rootSortNo);
        }
    }

    private function normalizeEvidenceStatusSortNoForType(string $importType, string $actor, array $priorityRows = []): void
    {
        $importType = self::normalizeDataType($importType);
        if ($importType === '') {
            return;
        }

        $params = [];
        $placeholders = [];
        foreach (self::queryDataTypes($importType) as $index => $type) {
            $key = ':normalize_type_' . $index;
            $placeholders[] = $key;
            $params[$key] = $type;
        }
        if ($placeholders === []) {
            return;
        }

        $stmt = $this->pdo->prepare("
            SELECT id, status_sort_no, mapped_payload_json
            FROM ledger_data_evidences
            WHERE deleted_at IS NULL
              AND source_type IN (" . implode(', ', $placeholders) . ")
            ORDER BY
                CASE WHEN status_sort_no IS NULL OR status_sort_no < 1 THEN 1 ELSE 0 END ASC,
                status_sort_no ASC,
                COALESCE(evidence_date, DATE(latest_imported_at), DATE(created_at)) DESC,
                latest_imported_at DESC,
                created_at DESC,
                id ASC
        ");
        $stmt->execute($params);
        $storedRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($storedRows === []) {
            return;
        }
        $normalizedPriorityRows = [];
        foreach ($priorityRows as $id => $sortNo) {
            $id = trim((string) $id);
            $sortNo = (int) $sortNo;
            if ($id !== '' && $sortNo > 0) {
                $normalizedPriorityRows[$id] = $sortNo;
            }
        }
        $priorityRows = $normalizedPriorityRows;
        if ($priorityRows !== []) {
            usort($storedRows, static function (array $left, array $right) use ($priorityRows): int {
                $leftId = (string) ($left['id'] ?? '');
                $rightId = (string) ($right['id'] ?? '');
                $leftPriority = $priorityRows[$leftId] ?? null;
                $rightPriority = $priorityRows[$rightId] ?? null;
                if ($leftPriority !== null || $rightPriority !== null) {
                    if ($leftPriority === null) {
                        return 1;
                    }
                    if ($rightPriority === null) {
                        return -1;
                    }
                    return $leftPriority <=> $rightPriority;
                }

                $leftSortNo = is_numeric($left['status_sort_no'] ?? null) ? (int) $left['status_sort_no'] : PHP_INT_MAX;
                $rightSortNo = is_numeric($right['status_sort_no'] ?? null) ? (int) $right['status_sort_no'] : PHP_INT_MAX;
                if ($leftSortNo !== $rightSortNo) {
                    return $leftSortNo <=> $rightSortNo;
                }

                return strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? ''));
            });
        }

        $update = $this->pdo->prepare("
            UPDATE ledger_data_evidences
            SET mapped_payload_json = :mapped_payload_json,
                status_sort_no = :status_sort_no,
                updated_at = NOW(),
                updated_by = :actor
            WHERE id = :id
        ");
        foreach ($storedRows as $index => $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $nextSortNo = $index + 1;
            $currentSortNo = is_numeric($row['status_sort_no'] ?? null) ? (int) $row['status_sort_no'] : 0;
            $mappedPayload = json_decode((string) ($row['mapped_payload_json'] ?? ''), true);
            $mappedPayload = is_array($mappedPayload) ? $mappedPayload : [];
            $currentPayloadSortNo = (int) ($mappedPayload['_status_sort_no'] ?? 0);
            if ($currentSortNo === $nextSortNo && $currentPayloadSortNo === $nextSortNo) {
                continue;
            }

            $mappedPayload['_status_sort_no'] = $nextSortNo;
            $update->execute([
                ':id' => $id,
                ':mapped_payload_json' => $this->jsonEncodeForStorage($mappedPayload),
                ':status_sort_no' => $nextSortNo,
                ':actor' => $actor,
            ]);
            $this->syncProcessingItemDisplayPathsForEvidence($id, $nextSortNo, $actor);
        }
    }

    public function apiSeedRowsDelete(): void
    {
        try {
            $payload = $this->requestPayload();
            $ids = $this->seedRowIdsFromPayload($payload);
            if ($ids === []) {
                $this->json(['success' => false, 'message' => '삭제할 Seed Data를 선택하세요.'], 400);
                return;
            }

            $this->pdo->beginTransaction();
            $this->releaseSeedRowsWithoutActiveOutputs($ids);

            $deletableIds = $this->deletableSeedRowIds($ids);
            if ($deletableIds === []) {
                $blocked = $this->seedRowDeleteBlockSummary($ids);
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                $this->json([
                    'success' => true,
                    'message' => $blocked !== ''
                        ? '삭제된 항목은 없습니다. ' . $blocked
                        : '삭제된 항목은 없습니다. 이미 삭제되었거나 거래/전표 생성이 완료된 데이터는 제외됩니다.',
                    'data' => ['deleted_count' => 0, 'skipped_count' => count($ids)],
                ]);
                return;
            }

            [$inSql, $params] = $this->placeholdersForIds($deletableIds, 'seed_id');
            $actor = ActorHelper::user();
            $params[':deleted_by'] = $actor;
            $params[':updated_by'] = $actor;
            $stmt = $this->pdo->prepare("
                UPDATE ledger_data_evidences
                SET deleted_at = NOW(),
                    deleted_by = :deleted_by,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id IN ({$inSql})
                  AND transaction_status IN ('NONE', 'ERROR', 'DUPLICATED')
                  AND deleted_at IS NULL
            ");
            $stmt->execute($params);

            $deletedCount = $stmt->rowCount();
            $this->syncBankTransactionsSoftDelete($deletableIds, $actor);
            if ($deletedCount === 0) {
                $blocked = $this->seedRowDeleteBlockSummary($ids);
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                $this->json([
                    'success' => true,
                    'message' => $blocked !== ''
                        ? '삭제된 항목은 없습니다. ' . $blocked
                        : '삭제된 항목은 없습니다. 이미 삭제되었거나 거래/전표 생성이 완료된 데이터는 제외됩니다.',
                    'data' => ['deleted_count' => 0, 'skipped_count' => count($ids)],
                ]);
                return;
            }

            $this->pdo->commit();
            $skippedIds = array_values(array_diff($ids, $deletableIds));
            $skippedCount = max(0, count($ids) - $deletedCount);
            $blocked = $skippedCount > 0 ? $this->seedRowDeleteBlockSummary($skippedIds) : '';
            $message = "선택 Seed Data {$deletedCount}건이 삭제되었습니다.";
            if ($skippedCount > 0) {
                $message .= $blocked !== ''
                    ? " 제외 {$skippedCount}건: {$blocked}"
                    : " 삭제할 수 없는 {$skippedCount}건은 제외되었습니다.";
            }
            $this->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'deleted_count' => $deletedCount,
                    'skipped_count' => $skippedCount,
                ],
            ]);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[ImportController] apiSeedRowsDelete failed: ' . $e->getMessage());
            $this->json([
                'success' => false,
                'message' => '삭제 처리 중 오류가 발생했습니다: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function releaseSeedRowsWithoutActiveOutputs(array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
        if ($ids === []) {
            return;
        }

        [$inSql, $params] = $this->placeholdersForIds($ids, 'release_seed_id');
        $transactionSelect = $this->evidenceHasTransactionIdColumn() ? ', transaction_id' : '';
        $stmt = $this->pdo->prepare("
            SELECT id, source_type, evidence_date, mapped_payload_json{$transactionSelect}
            FROM ledger_data_evidences
            WHERE id IN ({$inSql})
              AND deleted_at IS NULL
              AND transaction_status NOT IN ('NONE', 'ERROR', 'DUPLICATED')
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $releaseIds = [];
        foreach ($rows as $row) {
            if (!$this->hasActiveOutputForEvidenceRow($row)) {
                $releaseIds[] = (string) ($row['id'] ?? '');
            }
        }

        $releaseIds = array_values(array_unique(array_filter($releaseIds)));
        if ($releaseIds === []) {
            return;
        }

        [$releaseInSql, $releaseParams] = $this->placeholdersForIds($releaseIds, 'release_id');
        $transactionSql = $this->evidenceHasTransactionIdColumn() ? 'transaction_id = NULL,' : '';
        $releaseParams[':actor'] = ActorHelper::user();
        $this->pdo->prepare("
            UPDATE ledger_data_evidences
            SET evidence_status = 'ACTIVE',
                transaction_status = 'NONE',
                {$transactionSql}
                error_message = NULL,
                updated_at = NOW(),
                updated_by = :actor
            WHERE id IN ({$releaseInSql})
              AND deleted_at IS NULL
        ")->execute($releaseParams);
    }

    private function hasActiveOutputForEvidenceRow(array $row): bool
    {
        $rowId = (string) ($row['id'] ?? '');
        $transactionId = trim((string) ($row['transaction_id'] ?? ''));
        if ($transactionId !== '' && $this->activeTransactionExists($transactionId)) {
            return true;
        }

        if ($this->activeVoucherExistsForEvidence($rowId, $transactionId)) {
            return true;
        }

        return $this->activeTransactionExistsForEvidenceFingerprint($row);
    }

    private function activeTransactionExists(string $transactionId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM ledger_transactions
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $transactionId]);

        return (bool) $stmt->fetchColumn();
    }

    private function activeVoucherExistsForEvidence(string $evidenceId, string $transactionId = ''): bool
    {
        if ($evidenceId === '') {
            return false;
        }

        if ($this->tableExists('ledger_data_evidence_links')) {
            $stmt = $this->pdo->prepare("
                SELECT 1
                FROM ledger_data_evidence_links l
                INNER JOIN ledger_vouchers v
                    ON v.id = l.voucher_id
                   AND v.deleted_at IS NULL
                WHERE l.evidence_id = :evidence_id
                  AND l.deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([':evidence_id' => $evidenceId]);
            if ($stmt->fetchColumn()) {
                return true;
            }
        }

        $conditions = [];
        $params = [];
        if ($this->tableColumnExists('ledger_vouchers', 'source_id')) {
            $conditions[] = 'source_id = :source_id';
            $params[':source_id'] = $evidenceId;
        }
        if ($transactionId !== '' && $this->tableColumnExists('ledger_vouchers', 'transaction_id')) {
            $conditions[] = 'transaction_id = :transaction_id';
            $params[':transaction_id'] = $transactionId;
        }
        if ($conditions === []) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM ledger_vouchers
            WHERE (" . implode(' OR ', $conditions) . ")
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    private function activeTransactionExistsForEvidenceFingerprint(array $row): bool
    {
        $mapped = json_decode((string) ($row['mapped_payload_json'] ?? ''), true);
        if (!is_array($mapped)) {
            return false;
        }

        $sourceType = self::normalizeDataType((string) ($row['source_type'] ?? ''));
        $transactionDate = $this->dateValue($mapped['transaction_date'] ?? $mapped['evidence_date'] ?? $row['evidence_date'] ?? '');
        $amount = $this->amountOrNull($mapped['total_amount'] ?? $mapped['amount'] ?? null);
        if ($amount === null) {
            $supply = (float) ($this->amountOrNull($mapped['supply_amount'] ?? null) ?? 0);
            $vat = (float) ($this->amountOrNull($mapped['vat_amount'] ?? null) ?? 0);
            $amount = $supply + $vat;
        }
        if ($sourceType === '' || $transactionDate === '' || $amount === null) {
            return false;
        }

        $description = trim((string) ($mapped['description'] ?? ''));
        $params = [
            ':import_type' => $sourceType,
            ':transaction_date' => $transactionDate,
            ':total_amount' => (float) $amount,
        ];
        $descriptionSql = '';
        if ($description !== '') {
            $descriptionSql = 'AND (description = :description OR description IS NULL OR description = \'\')';
            $params[':description'] = $description;
        }

        $stmt = $this->pdo->prepare("
            SELECT id
            FROM ledger_transactions
            WHERE import_type = :import_type
              AND transaction_date = :transaction_date
              AND ABS(COALESCE(total_amount, 0) - :total_amount) < 0.01
              AND deleted_at IS NULL
              {$descriptionSql}
            LIMIT 1
        ");
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function apiSeedRowsRestore(): void
    {
        $payload = $this->requestPayload();
        $ids = $this->seedRowIdsFromPayload($payload);
        if ($ids === []) {
            $this->json(['success' => false, 'message' => '복구할 Seed Data를 선택하세요.'], 400);
            return;
        }

        [$inSql, $params] = $this->placeholdersForIds($ids, 'seed_id');
        $params[':actor'] = ActorHelper::user();
        $stmt = $this->pdo->prepare("
            UPDATE ledger_data_evidences
            SET deleted_at = NULL,
                deleted_by = NULL,
                updated_at = NOW(),
                updated_by = :actor
            WHERE id IN ({$inSql})
        ");
        $stmt->execute($params);
        $restoredCount = $stmt->rowCount();
        $this->syncBankTransactionsRestore($ids, ActorHelper::user());

        $this->json([
            'success' => true,
            'message' => "선택 Seed Data {$restoredCount}건이 복구되었습니다.",
            'data' => [
                'restored_count' => $restoredCount,
                'skipped_count' => max(0, count($ids) - $restoredCount),
            ],
        ]);
    }

    public function apiSeedRowsRestoreAll(): void
    {
        $payload = $this->requestPayload();
        $importType = self::normalizeDataType((string) ($payload['import_type'] ?? $payload['data_type'] ?? $_GET['import_type'] ?? $_GET['data_type'] ?? ''));
        $ids = $this->deletedSeedRowIds($importType);
        [$scopeSql, $scopeParams] = $this->seedRowImportTypeSqlScope($importType, 'restore_all_type');
        $stmt = $this->pdo->prepare("
            UPDATE ledger_data_evidences
            SET deleted_at = NULL,
                deleted_by = NULL,
                updated_at = NOW(),
                updated_by = :actor
            WHERE deleted_at IS NOT NULL
            {$scopeSql}
        ");
        $actor = ActorHelper::user();
        $stmt->execute([':actor' => $actor] + $scopeParams);
        $restoredCount = $stmt->rowCount();
        $this->syncBankTransactionsRestore($ids, $actor);

        $this->json([
            'success' => true,
            'message' => "휴지통 Seed Data {$restoredCount}건이 복구되었습니다.",
            'data' => ['restored_count' => $restoredCount],
        ]);
    }

    public function apiSeedRowsPurge(): void
    {
        $payload = $this->requestPayload();
        $ids = $this->seedRowIdsFromPayload($payload);
        if ($ids === []) {
            $this->json(['success' => false, 'message' => '영구 삭제할 Seed Data를 선택하세요.'], 400);
            return;
        }

        $purgeableIds = $this->purgeableSeedRowIds($ids);
        if ($purgeableIds === []) {
            $this->json([
                'success' => true,
                'message' => '영구 삭제된 항목은 없습니다. 휴지통에 없는 항목은 제외됩니다.',
                'data' => ['deleted_count' => 0, 'skipped_count' => count($ids)],
            ]);
            return;
        }

        $deletedCount = $this->purgeSeedRowsByIds($purgeableIds);

        $this->json([
            'success' => true,
            'message' => "선택 Seed Data {$deletedCount}건이 영구 삭제되었습니다.",
            'data' => [
                'deleted_count' => $deletedCount,
                'skipped_count' => max(0, count($ids) - $deletedCount),
            ],
        ]);
    }

    private function deletableSeedRowIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
        if ($ids === []) {
            return [];
        }

        [$inSql, $params] = $this->placeholdersForIds($ids, 'deletable_seed_id');
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM ledger_data_evidences
            WHERE id IN ({$inSql})
              AND transaction_status IN ('NONE', 'ERROR', 'DUPLICATED')
              AND deleted_at IS NULL
        ");
        $stmt->execute($params);

        return array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));
    }

    private function seedRowDeleteBlockSummary(array $ids): string
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
        if ($ids === []) {
            return '';
        }

        [$inSql, $params] = $this->placeholdersForIds($ids, 'delete_block_id');
        $transactionSelect = $this->evidenceHasTransactionIdColumn() ? ', transaction_id' : '';
        $stmt = $this->pdo->prepare("
            SELECT id, source_type, source_key, evidence_date, transaction_status, deleted_at, mapped_payload_json{$transactionSelect}
            FROM ledger_data_evidences
            WHERE id IN ({$inSql})
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            return '선택한 항목을 찾을 수 없습니다.';
        }

        $counts = [
            'deleted' => 0,
            'generated' => 0,
            'status' => 0,
        ];
        $samples = [];
        foreach ($rows as $row) {
            $reason = '';
            if (!empty($row['deleted_at'])) {
                $counts['deleted']++;
                $reason = '이미 삭제됨';
            } elseif ($this->hasActiveOutputForEvidenceRow($row)) {
                $counts['generated']++;
                $reason = '거래/전표 생성 결과 있음';
            } elseif (!in_array((string) ($row['transaction_status'] ?? ''), ['NONE', 'ERROR', 'DUPLICATED'], true)) {
                $counts['status']++;
                $reason = '거래생성상태 ' . (string) ($row['transaction_status'] ?? '-');
            }

            if ($reason !== '' && count($samples) < 3) {
                $samples[] = $this->seedRowDeleteBlockLabel($row) . ' - ' . $reason;
            }
        }

        $parts = [];
        if ($counts['generated'] > 0) {
            $parts[] = '거래/전표 생성 결과가 남아있는 항목 ' . $counts['generated'] . '건';
        }
        if ($counts['deleted'] > 0) {
            $parts[] = '이미 삭제된 항목 ' . $counts['deleted'] . '건';
        }
        if ($counts['status'] > 0) {
            $parts[] = '삭제 대상 상태가 아닌 항목 ' . $counts['status'] . '건';
        }

        if ($parts === []) {
            return '선택 항목이 삭제 조건에 맞지 않습니다.';
        }

        return implode(', ', $parts) . ($samples !== [] ? ' (' . implode(' / ', $samples) . ')' : '') . '.';
    }

    private function seedRowDeleteBlockLabel(array $row): string
    {
        $mapped = json_decode((string) ($row['mapped_payload_json'] ?? ''), true);
        $mapped = is_array($mapped) ? $mapped : [];
        $rowNo = $mapped['_status_sort_no'] ?? $mapped['_create_sort_no'] ?? $mapped['_row_no'] ?? '';
        $client = $mapped['client_name']
            ?? $mapped['client_company_name']
            ?? $mapped['counterparty_name']
            ?? $mapped['supplier_company_name']
            ?? $mapped['customer_company_name']
            ?? '';
        $label = [];
        if (trim((string) $rowNo) !== '') {
            $label[] = '순번 ' . trim((string) $rowNo);
        }
        if (trim((string) $client) !== '') {
            $label[] = trim((string) $client);
        }
        if ($label === []) {
            $label[] = trim((string) ($row['source_key'] ?? $row['id'] ?? '항목'));
        }

        return implode(' ', $label);
    }

    public function apiSeedRowsPurgeAll(): void
    {
        $payload = $this->requestPayload();
        $importType = self::normalizeDataType((string) ($payload['import_type'] ?? $payload['data_type'] ?? $_GET['import_type'] ?? $_GET['data_type'] ?? ''));
        $purgeableIds = $this->purgeableSeedRowIds([], $importType);
        if ($purgeableIds === []) {
            $this->json([
                'success' => true,
                'message' => '영구 삭제 가능한 휴지통 Seed Data가 없습니다.',
                'data' => ['deleted_count' => 0],
            ]);
            return;
        }

        $deletedCount = $this->purgeSeedRowsByIds($purgeableIds);

        $this->json([
            'success' => true,
            'message' => "휴지통 Seed Data {$deletedCount}건이 영구 삭제되었습니다.",
            'data' => ['deleted_count' => $deletedCount],
        ]);
    }

    private function purgeableSeedRowIds(array $ids = [], string $importType = ''): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
        $where = ['deleted_at IS NOT NULL'];
        $params = [];

        if ($ids !== []) {
            [$inSql, $params] = $this->placeholdersForIds($ids, 'purge_seed_id');
            $where[] = "id IN ({$inSql})";
        }
        [$scopeSql, $scopeParams] = $this->seedRowImportTypeSqlScope($importType, 'purge_type');
        if ($scopeSql !== '') {
            $where[] = preg_replace('/^\s*AND\s+/i', '', trim($scopeSql));
            $params += $scopeParams;
        }

        $stmt = $this->pdo->prepare("
            SELECT id
            FROM ledger_data_evidences
            WHERE " . implode(' AND ', $where) . "
        ");
        $stmt->execute($params);

        return array_values(array_unique(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []))));
    }

    private function purgeSeedRowsByIds(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
        if ($ids === []) {
            return 0;
        }

        $this->pdo->beginTransaction();
        try {
            $this->deleteEvidencePurgeDependencies($ids);

            [$inSql, $params] = $this->placeholdersForIds($ids, 'purge_delete_seed_id');
            $stmt = $this->pdo->prepare("
                DELETE FROM ledger_data_evidences
                WHERE id IN ({$inSql})
                  AND deleted_at IS NOT NULL
            ");
            $stmt->execute($params);
            $deletedCount = $stmt->rowCount();

            $this->pdo->commit();
            return $deletedCount;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function deleteEvidencePurgeDependencies(array $evidenceIds): void
    {
        $evidenceIds = array_values(array_unique(array_filter(array_map('strval', $evidenceIds))));
        if ($evidenceIds === []) {
            return;
        }

        $this->deleteBankTransactionsByEvidenceIds($evidenceIds);
        $this->deleteEvidenceLinksByEvidenceIds($evidenceIds);
        $this->detachEvidenceSourceRefs($evidenceIds);
        $this->deleteProcessingItemsByEvidenceIds($evidenceIds);
    }

    private function deleteEvidenceLinksByEvidenceIds(array $evidenceIds): void
    {
        if (!$this->tableExists('ledger_data_evidence_links')) {
            return;
        }

        [$inSql, $params] = $this->placeholdersForIds($evidenceIds, 'purge_link_evidence_id');
        $this->pdo->prepare("
            DELETE FROM ledger_data_evidence_links
            WHERE evidence_id IN ({$inSql})
        ")->execute($params);
    }

    private function detachEvidenceSourceRefs(array $evidenceIds): void
    {
        if ($this->tableExists('ledger_vouchers') && $this->tableColumnExists('ledger_vouchers', 'source_id')) {
            [$inSql, $params] = $this->placeholdersForIds($evidenceIds, 'purge_voucher_source_id');
            $this->pdo->prepare("
                UPDATE ledger_vouchers
                SET source_id = NULL
                WHERE source_id IN ({$inSql})
            ")->execute($params);
        }

        if ($this->tableExists('ledger_transactions') && $this->tableColumnExists('ledger_transactions', 'evidence_id')) {
            [$inSql, $params] = $this->placeholdersForIds($evidenceIds, 'purge_transaction_evidence_id');
            $this->pdo->prepare("
                UPDATE ledger_transactions
                SET evidence_id = NULL
                WHERE evidence_id IN ({$inSql})
            ")->execute($params);
        }
    }

    private function deleteProcessingItemsByEvidenceIds(array $evidenceIds): void
    {
        if (!$this->tableExists('ledger_processing_items')) {
            return;
        }

        [$inSql, $params] = $this->placeholdersForIds($evidenceIds, 'purge_processing_source_id');
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM ledger_processing_items
            WHERE source_table = 'ledger_data_evidences'
              AND source_id IN ({$inSql})
        ");
        $stmt->execute($params);
        $itemIds = array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));
        if ($itemIds === []) {
            return;
        }

        if ($this->tableExists('ledger_processing_item_actions')) {
            [$actionInSql, $actionParams] = $this->placeholdersForIds($itemIds, 'purge_processing_action_id');
            [$relatedInSql, $relatedParams] = $this->placeholdersForIds($itemIds, 'purge_processing_related_id');
            $this->pdo->prepare("
                DELETE FROM ledger_processing_item_actions
                WHERE processing_item_id IN ({$actionInSql})
                   OR related_processing_item_id IN ({$relatedInSql})
            ")->execute($actionParams + $relatedParams);
        }

        foreach (['ledger_transaction_lines', 'ledger_voucher_lines', 'ledger_data_evidence_links'] as $table) {
            if (!$this->tableExists($table) || !$this->tableColumnExists($table, 'processing_item_id')) {
                continue;
            }
            [$lineInSql, $lineParams] = $this->placeholdersForIds($itemIds, 'purge_' . $table . '_item_id');
            $this->pdo->prepare("
                UPDATE {$table}
                SET processing_item_id = NULL
                WHERE processing_item_id IN ({$lineInSql})
            ")->execute($lineParams);
        }

        [$itemInSql, $itemParams] = $this->placeholdersForIds($itemIds, 'purge_processing_item_id');
        $this->pdo->prepare("
            DELETE FROM ledger_processing_items
            WHERE id IN ({$itemInSql})
        ")->execute($itemParams);
    }

    private function deletedSeedRowIds(string $importType = ''): array
    {
        [$scopeSql, $params] = $this->seedRowImportTypeSqlScope($importType, 'deleted_type');
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM ledger_data_evidences
            WHERE deleted_at IS NOT NULL
            {$scopeSql}
        ");
        $stmt->execute($params);

        return array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));
    }

    private function seedRowImportTypeSqlScope(string $importType, string $prefix): array
    {
        $importType = self::normalizeDataType($importType);
        if ($importType === '') {
            return ['', []];
        }

        $types = self::queryDataTypes($importType);
        $placeholders = [];
        $params = [];
        foreach ($types as $index => $type) {
            $key = ':' . $prefix . '_' . $index;
            $placeholders[] = $key;
            $params[$key] = $type;
        }

        return [' AND source_type IN (' . implode(', ', $placeholders) . ')', $params];
    }

    private function syncBankTransactionsSoftDelete(array $evidenceIds, string $actor): void
    {
        $evidenceIds = array_values(array_unique(array_filter(array_map('strval', $evidenceIds))));
        if ($evidenceIds === [] || !$this->tableExists('ledger_bank_transactions')) {
            return;
        }

        [$inSql, $params] = $this->placeholdersForIds($evidenceIds, 'bank_soft_delete_id');
        $params[':deleted_by'] = $actor;
        $params[':updated_by'] = $actor;
        $stmt = $this->pdo->prepare("
            UPDATE ledger_bank_transactions
            SET deleted_at = NOW(),
                deleted_by = :deleted_by,
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE evidence_id IN ({$inSql})
              AND deleted_at IS NULL
        ");
        $stmt->execute($params);
    }

    private function syncBankTransactionsRestore(array $evidenceIds, string $actor): void
    {
        $evidenceIds = array_values(array_unique(array_filter(array_map('strval', $evidenceIds))));
        if ($evidenceIds === [] || !$this->tableExists('ledger_bank_transactions')) {
            return;
        }

        [$inSql, $params] = $this->placeholdersForIds($evidenceIds, 'bank_restore_id');
        $params[':updated_by'] = $actor;
        $stmt = $this->pdo->prepare("
            UPDATE ledger_bank_transactions
            SET deleted_at = NULL,
                deleted_by = NULL,
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE evidence_id IN ({$inSql})
        ");
        $stmt->execute($params);
    }

    private function deleteBankTransactionsByEvidenceIds(array $evidenceIds): void
    {
        $evidenceIds = array_values(array_unique(array_filter(array_map('strval', $evidenceIds))));
        if ($evidenceIds === [] || !$this->tableExists('ledger_bank_transactions')) {
            return;
        }

        [$inSql, $params] = $this->placeholdersForIds($evidenceIds, 'bank_purge_id');
        $stmt = $this->pdo->prepare("
            DELETE FROM ledger_bank_transactions
            WHERE evidence_id IN ({$inSql})
        ");
        $stmt->execute($params);
    }

    private function ensureBankTransactionEvidenceRows(): void
    {
        if (!$this->tableExists('ledger_bank_transactions') || !$this->tableExists('ledger_data_evidences')) {
            return;
        }

        $stmt = $this->pdo->query("
            SELECT b.*
            FROM ledger_bank_transactions b
            LEFT JOIN ledger_data_evidences e ON e.id = b.evidence_id
            WHERE e.id IS NULL
            ORDER BY b.created_at ASC, b.id ASC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            return;
        }

        $startedTransaction = !$this->pdo->inTransaction();
        if ($startedTransaction) {
            $this->pdo->beginTransaction();
        }

        $insert = $this->pdo->prepare("
            INSERT INTO ledger_data_evidences
                (id, source_type, source_key, evidence_date, bank_account_id, bank_account_name,
                 currency, supply_amount, vat_amount, total_amount, evidence_status, transaction_status,
                 voucher_status, review_status, error_message, latest_imported_at, raw_json, mapped_payload_json,
                 created_at, created_by, updated_at, updated_by, deleted_at, deleted_by)
            VALUES
                (:id, 'BANK_TRANSACTION', :source_key, :evidence_date, :bank_account_id, :bank_account_name,
                 :currency, 0, 0, :total_amount, :evidence_status, :transaction_status,
                 :voucher_status, 'NORMAL', NULL, :latest_imported_at, :raw_json, :mapped_payload_json,
                 :created_at, :created_by, :updated_at, :updated_by, :deleted_at, :deleted_by)
            ON DUPLICATE KEY UPDATE
                source_key = VALUES(source_key),
                evidence_date = VALUES(evidence_date),
                bank_account_id = VALUES(bank_account_id),
                bank_account_name = VALUES(bank_account_name),
                currency = VALUES(currency),
                total_amount = VALUES(total_amount),
                raw_json = VALUES(raw_json),
                mapped_payload_json = VALUES(mapped_payload_json),
                updated_at = VALUES(updated_at),
                updated_by = VALUES(updated_by),
                deleted_at = VALUES(deleted_at),
                deleted_by = VALUES(deleted_by)
        ");

        try {
            foreach ($rows as $row) {
                $evidenceId = trim((string) ($row['evidence_id'] ?? ''));
                if ($evidenceId === '') {
                    $evidenceId = UuidHelper::generate();
                    $this->pdo->prepare('UPDATE ledger_bank_transactions SET evidence_id = :evidence_id WHERE id = :id')
                        ->execute([':evidence_id' => $evidenceId, ':id' => (string) $row['id']]);
                }

                $payload = $this->bankTransactionPayloadFromRow($row);
                $sourceKey = trim((string) ($row['bank_reference_no'] ?? ''));
                if ($sourceKey === '') {
                    $sourceKey = 'BANK:' . (string) $row['id'];
                }
                $totalAmount = (float) ($this->amountOrNull($row['deposit_amount'] ?? null) ?? 0)
                    + (float) ($this->amountOrNull($row['withdraw_amount'] ?? null) ?? 0);
                $deletedAt = $row['deleted_at'] ?? null;

                $insert->execute([
                    ':id' => $evidenceId,
                    ':source_key' => $sourceKey,
                    ':evidence_date' => $this->dateValueOrNull($row['transaction_date'] ?? null),
                    ':bank_account_id' => (string) ($row['bank_account_id'] ?? ''),
                    ':bank_account_name' => $this->bankAccountNameById((string) ($row['bank_account_id'] ?? '')),
                    ':currency' => (string) ($row['currency_code'] ?? 'KRW'),
                    ':total_amount' => $totalAmount,
                    ':evidence_status' => 'ACTIVE',
                    ':transaction_status' => 'NONE',
                    ':voucher_status' => 'WAITING',
                    ':latest_imported_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                    ':raw_json' => $this->jsonEncodeForStorage($payload),
                    ':mapped_payload_json' => $this->jsonEncodeForStorage($payload),
                    ':created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                    ':created_by' => (string) ($row['created_by'] ?? 'SYSTEM:BANK_SYNC'),
                    ':updated_at' => $row['updated_at'] ?? $row['created_at'] ?? date('Y-m-d H:i:s'),
                    ':updated_by' => (string) ($row['updated_by'] ?? 'SYSTEM:BANK_SYNC'),
                    ':deleted_at' => $deletedAt ?: null,
                    ':deleted_by' => $row['deleted_by'] ?? null,
                ]);
            }

            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function bankTransactionPayloadFromRow(array $row): array
    {
        return [
            'import_type' => 'BANK_TRANSACTION',
            'data_type' => 'BANK_TRANSACTION',
            'source_key' => trim((string) ($row['bank_reference_no'] ?? '')) !== '' ? (string) $row['bank_reference_no'] : 'BANK:' . (string) ($row['id'] ?? ''),
            'transaction_date' => $this->dateValueOrNull($row['transaction_date'] ?? null),
            'transaction_datetime' => $this->dateTimeValue($row['transaction_datetime'] ?? null),
            'transaction_time' => (string) ($row['transaction_time'] ?? ''),
            'bank_direction' => $this->bankDirectionLabel((string) ($row['transaction_type'] ?? '')),
            'bank_account_id' => (string) ($row['bank_account_id'] ?? ''),
            'bank_account_name' => $this->bankAccountNameById((string) ($row['bank_account_id'] ?? '')),
            'deposit_amount' => $this->amountOrNull($row['deposit_amount'] ?? null),
            'withdraw_amount' => $this->amountOrNull($row['withdraw_amount'] ?? null),
            'balance_amount' => $this->amountOrNull($row['balance_amount'] ?? null),
            'balance_status' => (string) ($row['balance_status'] ?? $this->bankBalanceStatus($row['balance_amount'] ?? null)),
            'check_bill_amount' => $this->amountOrNull($row['check_bill_amount'] ?? null),
            'currency_code' => (string) ($row['currency_code'] ?? 'KRW'),
            'exchange_rate' => $this->amountOrNull($row['exchange_rate'] ?? null),
            'description' => (string) ($row['description'] ?? ''),
            'counterparty_name' => (string) ($row['counterparty_name'] ?? ''),
            'counterparty_account_number' => (string) ($row['counterparty_account_number'] ?? ''),
            'counterparty_bank_name' => (string) ($row['counterparty_bank_name'] ?? ''),
            'bank_reference_no' => (string) ($row['bank_reference_no'] ?? ''),
            'memo' => (string) ($row['memo'] ?? ''),
        ];
    }

    private function bankDirectionLabel(string $transactionType): string
    {
        return match (strtoupper(trim($transactionType))) {
            'DEPOSIT', 'IN' => '입금',
            'WITHDRAW', 'OUT' => '출금',
            'TRANSFER' => '이체',
            'FEE' => '수수료',
            'INTEREST' => '이자',
            default => $transactionType,
        };
    }

    private function bankAccountNameById(string $bankAccountId): string
    {
        if ($bankAccountId === '' || !$this->tableExists('system_bank_accounts')) {
            return '';
        }

        if (!array_key_exists($bankAccountId, $this->bankAccountIdCache)) {
            $stmt = $this->pdo->prepare('SELECT account_name FROM system_bank_accounts WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $bankAccountId]);
            $this->bankAccountIdCache[$bankAccountId] = (string) ($stmt->fetchColumn() ?: '');
        }

        return (string) $this->bankAccountIdCache[$bankAccountId];
    }

    public function apiUploadBatchDelete(): void
    {
        $payload = $this->requestPayload();
        $batchId = trim((string) ($payload['batch_id'] ?? ''));
        if ($batchId !== '') {
            $deletableIds = $this->deletableSeedRowIdsByImportDate($batchId);
            $stmt = $this->pdo->prepare("
                UPDATE ledger_data_evidences
                SET deleted_at = NOW(),
                    deleted_by = :deleted_by,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE DATE(latest_imported_at) = :batch_id
                  AND transaction_status IN ('NONE', 'ERROR', 'DUPLICATED')
                  AND deleted_at IS NULL
            ");
            $actor = ActorHelper::user();
            $stmt->execute([':batch_id' => $batchId, ':deleted_by' => $actor, ':updated_by' => $actor]);
            $deletedCount = $stmt->rowCount();
            $this->syncBankTransactionsSoftDelete($deletableIds, $actor);
            if ($deletedCount === 0) {
                $this->json([
                    'success' => false,
                    'message' => '삭제 가능한 업로드 데이터가 없습니다. 이미 삭제되었거나 거래/전표 생성이 완료된 데이터는 제외됩니다.',
                ], 409);
                return;
            }
            $this->json([
                'success' => true,
                'message' => "선택 업로드 데이터 {$deletedCount}건이 삭제되었습니다. 거래/전표 생성 완료 데이터는 제외됩니다.",
                'data' => ['deleted_count' => $deletedCount],
            ]);
            return;
        }
        if ($batchId === '') {
            $this->json(['success' => false, 'message' => '업로드 배치 ID가 없습니다.'], 400);
            return;
        }

        $batch = $this->uploadBatch($batchId);
        if (!$batch) {
            $this->json(['success' => false, 'message' => '업로드 배치를 찾을 수 없습니다.'], 404);
            return;
        }

        try {
            $stmt = $this->pdo->prepare('DELETE FROM ledger_data_evidences WHERE DATE(latest_imported_at) = :id AND deleted_at IS NOT NULL AND transaction_status = \'NONE\'');
            $stmt->execute([':id' => $batchId]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
            return;
        }

        $this->json(['success' => true, 'message' => '업로드 배치가 삭제되었습니다.']);
    }

    private function templateSpec(string $type): array
    {
        $label = self::dataTypeLabel($type);
        if ($type === 'BANK_TRANSACTION') {
            return [
                'bank_upload_template.xlsx',
                'BANK template',
                ['거래일자', '입출구분', '사업구분', '거래유형', '거래처명', '프로젝트명', '직원명', '계좌명', '카드명', '상호', '적요', '금액', '잔액', '비고', '메모'],
                [
                    ['2026-05-04', '입금', 'HQ', 'GENERAL', '샘플상사', '본사', '홍길동', '운영계좌', '', '샘플상사', '매출대금 입금', 55000, 1055000, '샘플', ''],
                    ['2026-05-04', '출금', 'HQ', 'GENERAL', '샘플카드', '본사', '홍길동', '운영계좌', '법인카드', '샘플카드', '사용료 지급', 120000, 935000, '샘플', ''],
                ],
            ];
        }

        return [
            strtolower($type) . '_upload_template.xlsx',
            $label . ' template',
            ['작성일자', '공급자 사업자등록번호', '공급자 상호', '공급받는자 사업자등록번호', '공급받는자 상호', '사업명', '관리명', '적요', '공급가액', '부가세', '합계금액', '비고', '메모'],
            [
                ['2026-05-04', '123-45-67890', '샘플상사', '000-00-00000', '우리회사', '본사', '일반관리', $label . ' 매입', 50000, 5000, 55000, '샘플', ''],
                ['2026-05-04', '000-00-00000', '우리회사', '987-65-43210', '거래처상사', '운영', '매출', $label . ' 매출', 120000, 0, 120000, '샘플', ''],
            ],
        ];
    }

    private function templateSpecFromFormat(array $format): array
    {
        $dataType = self::normalizeDataType((string) ($format['data_type'] ?? 'ETC'));
        $columns = $this->templateColumnsInFormatOrder($format['columns'] ?? [], $dataType);
        $headers = $this->excelHeadersForColumns($columns);
        $formatName = trim((string) ($format['format_name'] ?? self::dataTypeLabel($dataType)));
        if (false && $dataType === 'BANK_TRANSACTION') {
            [$headerColumns, $lineColumns] = $this->splitBankFormatColumns($columns, false);
            $headerColumns = $this->templateColumnsInFormatOrder($headerColumns, $dataType);
            $lineColumns = $this->templateColumnsInFormatOrder($lineColumns, $dataType);
            $headerHeaders = $this->excelHeadersForColumns($headerColumns);
            $headerRequired = array_map(
                static fn(array $column): int => self::normalizeRequirementMode($column['is_required'] ?? 0),
                $headerColumns
            );
            $lineHeaders = $this->excelHeadersForColumns($lineColumns);
            $lineRequired = array_map(
                static fn(array $column): int => self::normalizeRequirementMode($column['is_required'] ?? 0),
                $lineColumns
            );

            return [
                self::safeFilename($formatName) . '_업로드_양식.xlsx',
                function_exists('mb_substr') ? mb_substr($formatName, 0, 31) : substr($formatName, 0, 31),
                [
                    [
                        'title' => '전표헤더',
                        'headers' => $headerHeaders,
                        'fields' => array_values(array_map(static fn(array $column): string => (string) ($column['system_field_name'] ?? ''), $headerColumns)),
                        'required' => $headerRequired,
                        'samples' => [$this->sampleRowForColumns($headerColumns, $dataType)],
                    ],
                    [
                        'title' => '분개라인',
                        'headers' => $lineHeaders,
                        'fields' => array_values(array_map(static fn(array $column): string => (string) ($column['system_field_name'] ?? ''), $lineColumns)),
                        'required' => $lineRequired,
                        'samples' => $this->sampleBankVoucherLineRows($lineColumns),
                    ],
                ],
                [],
            ];
        }

        return [
            self::safeFilename($formatName) . '_업로드_양식.xlsx',
            function_exists('mb_substr') ? mb_substr($formatName, 0, 31) : substr($formatName, 0, 31),
            $headers,
            [$this->sampleRowForColumns($columns, $dataType)],
            array_map(static fn(array $column): int => self::normalizeRequirementMode($column['is_required'] ?? 0), $columns),
            array_values(array_map(static fn(array $column): string => (string) ($column['system_field_name'] ?? ''), $columns)),
        ];
    }

    private function sampleRowForColumns(array $columns, string $dataType): array
    {
        $samples = [
            'transaction_date' => '2026-05-04',
            'supplier_business_number' => '123-45-67890',
            'supplier_branch_number' => '0000',
            'supplier_company_name' => '샘플상사',
            'supplier_ceo_name' => '홍길동',
            'supplier_address' => '서울시 강남구',
            'supplier_email' => 'supplier@example.com',
            'customer_business_number' => '000-00-00000',
            'customer_branch_number' => '0000',
            'customer_company_name' => '우리회사',
            'customer_ceo_name' => '대표자',
            'customer_address' => '서울시',
            'customer_email_1' => 'tax@example.com',
            'customer_email_2' => '',
            'broker_business_number' => '',
            'broker_company_name' => '',
            'approval_number' => '20260504-0001',
            'issue_date' => '2026-05-04',
            'transmit_date' => '2026-05-04',
            'tax_invoice_category' => '일반',
            'tax_invoice_type' => '일반',
            'issue_type' => '정발급',
            'receipt_claim_type' => '청구',
            'project_name' => '본사',
            'description' => self::dataTypeLabel($dataType) . ' 샘플',
            'supply_amount' => 50000,
            'vat_amount' => $dataType === 'BANK_TRANSACTION' ? 0 : 5000,
            'total_amount' => 55000,
            'transaction_datetime' => '2026-05-04 09:30:00',
            'bank_direction' => '입금',
            'business_unit' => 'GENERAL',
            'transaction_type' => 'GENERAL',
            'deposit_amount' => 55000,
            'withdraw_amount' => '',
            'balance_amount' => 1055000,
            'check_bill_amount' => 0,
            'bank_account_name' => '',
            'currency_code' => 'KRW',
            'counterparty_account_number' => '123-456-789012',
            'counterparty_bank_name' => '샘플은행',
            'counterparty_name' => '샘플상사',
            'bank_reference_no' => 'CMS-20260504-0001',
            'voucher_date' => '2026-05-04',
            'voucher_no' => '',
            'summary_text' => '매출대금 입금',
            'voucher_memo' => '',
            'debit_account_id' => '1000',
            'debit_amount' => 55000,
            'debit_line_summary' => '은행 입금',
            'credit_account_id' => '4000',
            'credit_amount' => 55000,
            'credit_line_summary' => '매출대금',
            'item_date' => '2026-05-04',
            'item_name' => '샘플 품목',
            'item_spec' => 'EA',
            'item_qty' => 1,
            'item_price' => 50000,
            'item_supply_amount' => 50000,
            'item_vat_amount' => $dataType === 'BANK_TRANSACTION' ? 0 : 5000,
            'item_note' => '',
            'note' => '샘플',
            'memo' => '',
        ];

        $row = [];
        foreach ($columns as $column) {
            $field = (string) ($column['system_field_name'] ?? '');
            $row[] = $samples[$field] ?? '';
        }

        return $row;
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

    private function storeUploadBatch(array $format, array $file, array $rows, string $cancelToken = ''): array
    {
        $actor = ActorHelper::user();
        $batchId = 'EV-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        $fileName = trim((string) ($file['name'] ?? 'upload'));
        $dataType = self::normalizeDataType((string) ($format['data_type'] ?? 'ETC'));

        $this->ensureEvidenceBusinessInfoColumns();
        $this->ensureEvidenceSortColumns();
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
            $newCount = 0;
            $updatedCount = 0;
            $unchangedCount = 0;
            $errorCount = 0;
            $protectedUpdateCount = 0;
            $protectedTransactionCount = 0;
            $protectedVoucherCount = 0;
            $nextStatusSortNo = $this->nextEvidenceJsonSortNo('_status_sort_no', $dataType);
            $nextCreateSortNo = $this->nextEvidenceJsonSortNo('_create_sort_no');
            $processedRows = 0;
            $chunkSize = self::UPLOAD_STORE_CHUNK_SIZE;
            $this->preloadExistingSeedRowsForUploadRows($rows, $dataType);
            $this->pdo->beginTransaction();
            foreach ($rows as $row) {
                $this->assertUploadNotCanceled($cancelToken);
                if (connection_aborted()) {
                    throw new \RuntimeException('업로드가 취소되었습니다.');
                }
                $validation = is_array($row['_validation'] ?? null) ? $row['_validation'] : [];
                $status = $this->uploadStatusFromValidation($validation);
                $processStatus = $status === 'ERROR' ? 'ERROR' : 'READY';
                $messages = is_array($validation['messages'] ?? null) ? $validation['messages'] : [];
                $parsedPayload = $this->mappedPayloadForStorage($row);
                if (isset($row['_row_no']) && !isset($parsedPayload['_upload_row_no'])) {
                    $parsedPayload['_upload_row_no'] = (int) $row['_row_no'];
                }
                if ($dataType === 'BANK_TRANSACTION') {
                    $parsedPayload = $this->normalizeBankTransactionPayload($parsedPayload);
                }
                $voucherStatus = $this->uploadVoucherStatus($dataType, $parsedPayload, $processStatus);
                $voucherErrorMessage = self::normalizeDataType($dataType) === 'BANK_TRANSACTION'
                    ? $this->bankVoucherValidationMessage($parsedPayload)
                    : null;
                $sourceKey = $this->seedSourceKey($parsedPayload, $dataType);
                if ($sourceKey === null) {
                    $sourceKey = hash('sha256', $dataType . '|' . $this->jsonEncodeForStorage(is_array($row['_raw_payload'] ?? null) ? $row['_raw_payload'] : $parsedPayload));
                }
                $rawJson = $this->jsonEncodeForStorage(is_array($row['_raw_payload'] ?? null) ? $row['_raw_payload'] : []);
                $errorMessages = $messages;
                if ($voucherErrorMessage !== null) {
                    $errorMessages[] = $voucherErrorMessage;
                }
                $errorMessage = $errorMessages !== [] ? implode(', ', $errorMessages) : null;
                $existingSeed = $sourceKey !== null ? $this->findExistingSeedRow($dataType, $sourceKey) : null;
                if (!$existingSeed && $this->usesFingerprintSourceKey($dataType)) {
                    $existingSeed = $this->findExistingSeedRowByFingerprint($dataType, $parsedPayload);
                }
                $existingMappedPayload = json_decode((string) ($existingSeed['mapped_payload_json'] ?? ''), true);
                $existingMappedPayload = is_array($existingMappedPayload) ? $existingMappedPayload : [];
                $this->assignEvidenceJsonSortNo($parsedPayload, $existingMappedPayload, '_status_sort_no', $nextStatusSortNo);
                $this->assignEvidenceJsonSortNo($parsedPayload, $existingMappedPayload, '_create_sort_no', $nextCreateSortNo);
                $parsedJson = $this->jsonEncodeForStorage($parsedPayload);
                if ($existingSeed && (string) ($existingSeed['raw_json'] ?? '') === $rawJson && (string) ($existingSeed['mapped_payload_json'] ?? '') === $parsedJson) {
                    $unchangedCount++;
                    $this->commitUploadChunkIfNeeded(++$processedRows, $chunkSize);
                    continue;
                }
                if ($existingSeed && $this->isUploadProtectedExistingSeed($existingSeed)) {
                    $protectedUpdateCount++;
                    if ($this->existingSeedHasCreatedTransaction($existingSeed)) {
                        $protectedTransactionCount++;
                    }
                    if ($this->existingSeedHasCreatedVoucher($existingSeed)) {
                        $protectedVoucherCount++;
                    }
                    $this->commitUploadChunkIfNeeded(++$processedRows, $chunkSize);
                    continue;
                }
                $evidenceId = (string) ($existingSeed['id'] ?? UuidHelper::generate());
                if ($existingSeed) {
                    $updatedCount++;
                } else {
                    $newCount++;
                }
                $upsertEvidence->execute([
                    ':id' => $evidenceId,
                    ':source_type' => $dataType,
                    ':source_key' => $sourceKey,
                    ':format_id' => (string) ($format['id'] ?? ''),
                    ':evidence_date' => $this->dateValue($parsedPayload['evidence_date'] ?? $parsedPayload['transaction_date'] ?? $parsedPayload['issue_date'] ?? '') ?: null,
                    ':client_id' => $this->businessRefIdForStorage('CLIENT', $parsedPayload),
                    ':project_id' => $this->businessRefIdForStorage('PROJECT', $parsedPayload),
                    ':employee_id' => $this->businessRefIdForStorage('EMPLOYEE', $parsedPayload),
                    ':bank_account_id' => $this->businessRefIdForStorage('ACCOUNT', $parsedPayload),
                    ':card_id' => $this->businessRefIdForStorage('CARD', $parsedPayload),
                    ':client_name' => $this->businessRefNameForStorage('CLIENT', $parsedPayload),
                    ':project_name' => $this->businessRefNameForStorage('PROJECT', $parsedPayload),
                    ':employee_name' => $this->businessRefNameForStorage('EMPLOYEE', $parsedPayload),
                    ':bank_account_name' => $this->businessRefNameForStorage('ACCOUNT', $parsedPayload),
                    ':card_name' => $this->businessRefNameForStorage('CARD', $parsedPayload),
                    ':currency' => (string) ($parsedPayload['currency'] ?? 'KRW'),
                    ':supply_amount' => $this->number($parsedPayload['supply_amount'] ?? null),
                    ':vat_amount' => $this->number($parsedPayload['vat_amount'] ?? null),
                    ':total_amount' => $this->evidenceTotalAmountForStorage($parsedPayload, $dataType),
                    ':create_sort_no' => (int) ($parsedPayload['_create_sort_no'] ?? 0) ?: null,
                    ':status_sort_no' => (int) ($parsedPayload['_status_sort_no'] ?? 0) ?: null,
                    ':evidence_status' => $processStatus === 'ERROR' ? 'ERROR' : 'ACTIVE',
                    ':transaction_status' => $processStatus === 'ERROR' ? 'ERROR' : 'NONE',
                    ':voucher_status' => $voucherStatus,
                    ':error_message' => $errorMessage,
                    ':raw_json' => $rawJson,
                    ':mapped_payload_json' => $parsedJson,
                    ':created_by' => $actor,
                    ':updated_by' => $actor,
                ]);
                if ($sourceKey !== null && $sourceKey !== '') {
                    $cachedSeed = [
                        'id' => $evidenceId,
                        'source_key' => $sourceKey,
                        'raw_json' => $rawJson,
                        'mapped_payload_json' => $parsedJson,
                        'evidence_status' => $processStatus === 'ERROR' ? 'ERROR' : 'ACTIVE',
                        'transaction_status' => $processStatus === 'ERROR' ? 'ERROR' : 'NONE',
                        'voucher_status' => $voucherStatus,
                    ];
                    $this->existingSeedRowCache[$dataType . '|' . $sourceKey] = $cachedSeed;
                    if ($this->usesFingerprintSourceKey($dataType)) {
                        $this->existingSeedFingerprintCache[$dataType][$this->sourceFingerprintKey($parsedPayload, $dataType)] = $cachedSeed;
                    }
                }
                if ($dataType === 'BANK_TRANSACTION') {
                    $this->upsertBankTransactionFromPayload($evidenceId, $parsedPayload, $actor);
                }
                if ($processStatus === 'ERROR') {
                    $errorCount++;
                }
                $this->commitUploadChunkIfNeeded(++$processedRows, $chunkSize);
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

        return [
            'id' => $batchId,
            'file_name' => $fileName,
            'data_type' => $dataType,
            'format_id' => (string) ($format['id'] ?? ''),
            'total_rows' => count($rows),
            'processed_count' => $newCount + $updatedCount + $unchangedCount + $errorCount,
            'new_count' => $newCount,
            'updated_count' => $updatedCount,
            'unchanged_count' => $unchangedCount,
            'error_count' => $errorCount,
            'skipped_count' => max(0, count($rows) - ($newCount + $updatedCount + $unchangedCount + $errorCount)),
            'protected_update_count' => $protectedUpdateCount,
            'protected_transaction_count' => $protectedTransactionCount,
            'protected_voucher_count' => $protectedVoucherCount,
        ];
    }

    private function commitUploadChunkIfNeeded(int $processedRows, int $chunkSize): void
    {
        if ($chunkSize < 1 || $processedRows % $chunkSize !== 0) {
            return;
        }

        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
        $this->pdo->beginTransaction();
    }

    private function deletableSeedRowIdsByImportDate(string $batchId): array
    {
        $batchId = trim($batchId);
        if ($batchId === '') {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT id
            FROM ledger_data_evidences
            WHERE DATE(latest_imported_at) = :batch_id
              AND transaction_status IN ('NONE', 'ERROR', 'DUPLICATED')
              AND deleted_at IS NULL
        ");
        $stmt->execute([':batch_id' => $batchId]);

        return array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));
    }

    private function uploadStatusFromValidation(array $validation): string
    {
        $status = (string) ($validation['status'] ?? 'ok');
        if ($status === 'error') {
            return 'ERROR';
        }
        if ($status === 'warning') {
            return 'WARNING';
        }
        return 'VALID';
    }

    private function uploadVoucherStatus(string $dataType, array $payload, string $processStatus): string
    {
        if ($processStatus === 'ERROR') {
            return 'ERROR';
        }

        if (self::normalizeDataType($dataType) !== 'BANK_TRANSACTION') {
            return 'NONE';
        }

        if (!$this->hasVoucherLinesPayload($payload)) {
            return 'WAITING';
        }

        return $this->bankVoucherValidationMessage($payload) === null ? 'READY' : 'ERROR';
    }

    private function evidenceTotalAmountForStorage(array $payload, string $dataType): float
    {
        $dataType = self::normalizeDataType($dataType);
        $candidates = ['total_amount', 'amount'];
        if (in_array($dataType, ['CARD_STATEMENT', 'CARD_APPROVAL'], true)) {
            $candidates = [
                'total_amount',
                'actual_billing_amount',
                'billing_amount',
                'purchase_amount_krw',
                'supply_amount',
                'foreign_amount',
                'local_amount',
                'amount',
            ];
        }

        foreach ($candidates as $field) {
            $amount = $this->amountOrNull($payload[$field] ?? null);
            if ($amount !== null) {
                return (float) $amount;
            }
        }

        $supply = $this->amountOrNull($payload['supply_amount'] ?? null);
        $vat = $this->amountOrNull($payload['vat_amount'] ?? null);
        $service = $this->amountOrNull($payload['service_amount'] ?? null);
        if ($supply !== null || $vat !== null || $service !== null) {
            return (float) ($supply ?? 0) + (float) ($vat ?? 0) + (float) ($service ?? 0);
        }

        return 0.0;
    }

    private function hasVoucherLinesPayload(array $payload): bool
    {
        $lines = $payload['_voucher_lines'] ?? null;
        if (!is_array($lines) || $lines === []) {
            return false;
        }

        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            if ($this->normalizeBankVoucherLineRowType($line['line_row_type'] ?? null) === 'AUX') {
                continue;
            }
            $account = trim((string) ($line['account_id'] ?? ''));
            $debit = $this->amountOrNull($line['debit'] ?? null);
            $credit = $this->amountOrNull($line['credit'] ?? null);
            if ($account !== '' && (($debit !== null && $debit != 0.0) || ($credit !== null && $credit != 0.0))) {
                return true;
            }
        }

        return false;
    }

    private function bankVoucherValidationMessage(array $payload): ?string
    {
        if (!$this->hasVoucherLinesPayload($payload)) {
            return null;
        }

        try {
            $lines = $this->bankVoucherLinesForSave($payload['_voucher_lines'] ?? []);
            $missingRefMessage = $this->missingRequiredEvidenceRefsMessage($lines, $payload);
            if ($missingRefMessage !== null) {
                return $missingRefMessage;
            }
            $this->bankVoucherPaymentsForSave($payload);
            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    private function updateEvidenceVoucherStatus(string $evidenceId, string $voucherStatus, string $actor, ?string $errorMessage = null): void
    {
        if ($evidenceId === '') {
            return;
        }

        $stmt = $this->pdo->prepare("
            UPDATE ledger_data_evidences
            SET voucher_status = :voucher_status,
                error_message = :error_message,
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE id = :id
              AND deleted_at IS NULL
        ");
        $params = [
            ':id' => $evidenceId,
            ':voucher_status' => $voucherStatus,
            ':error_message' => $errorMessage,
            ':updated_by' => $actor,
        ];
        $stmt->execute($params);
    }

    private function resetBankEvidenceTransactionClaim(string $evidenceId, string $actor): void
    {
        if ($evidenceId === '') {
            return;
        }

        $stmt = $this->pdo->prepare("
            UPDATE ledger_data_evidences
            SET transaction_status = 'NONE',
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE id = :id
              AND source_type = 'BANK_TRANSACTION'
              AND transaction_status = 'PROCESSING'
              AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':id' => $evidenceId,
            ':updated_by' => $actor,
        ]);
    }

    private function upsertBankTransactionFromPayload(string $evidenceId, array $payload, string $actor): void
    {
        if ($evidenceId === '') {
            return;
        }
        $this->ensureBankTransactionBalanceColumns();
        $payload = $this->normalizeBankTransactionPayload($payload);

        $stmt = $this->pdo->prepare('
            SELECT id
            FROM ledger_bank_transactions
            WHERE evidence_id = :evidence_id
              AND deleted_at IS NULL
            ORDER BY created_at DESC
            LIMIT 1
        ');
        $stmt->execute([':evidence_id' => $evidenceId]);
        $existingId = $stmt->fetchColumn();
        $existingId = $existingId !== false ? (string) $existingId : null;

        $values = [
            ':id' => $existingId ?? UuidHelper::generate(),
            ':transaction_date' => $this->dateValue($payload['transaction_date'] ?? $payload['evidence_date'] ?? date('Y-m-d')),
            ':transaction_time' => $this->nullableString($payload['transaction_time'] ?? null),
            ':bank_account_id' => $this->businessRefIdForStorage('ACCOUNT', $payload) ?? '',
            ':transaction_type' => $this->bankTransactionType($payload['bank_direction'] ?? $payload['transaction_direction'] ?? null, $payload),
            ':deposit_amount' => $this->number($payload['deposit_amount'] ?? null),
            ':withdraw_amount' => $this->number($payload['withdraw_amount'] ?? null),
            ':balance_amount' => $this->amountOrNull($payload['balance_amount'] ?? null),
            ':balance_status' => $this->bankBalanceStatus($payload['balance_amount'] ?? null),
            ':currency_code' => (string) ($payload['currency_code'] ?? $payload['currency'] ?? 'KRW'),
            ':exchange_rate' => (float) ($this->amountOrNull($payload['exchange_rate'] ?? null) ?? 1),
            ':description' => $this->nullableString($payload['description'] ?? null),
            ':counterparty_name' => $this->nullableString($payload['counterparty_name'] ?? null),
            ':bank_reference_no' => $this->nullableString($payload['bank_reference_no'] ?? $payload['source_key'] ?? null),
            ':evidence_id' => $evidenceId,
            ':memo' => $this->nullableString($payload['memo'] ?? null),
            ':actor' => $actor,
        ];

        if ($existingId !== null) {
            $update = $this->pdo->prepare('
                UPDATE ledger_bank_transactions
                SET transaction_date = :transaction_date,
                    transaction_time = :transaction_time,
                    bank_account_id = :bank_account_id,
                    transaction_type = :transaction_type,
                    deposit_amount = :deposit_amount,
                    withdraw_amount = :withdraw_amount,
                    balance_amount = :balance_amount,
                    balance_status = :balance_status,
                    currency_code = :currency_code,
                    exchange_rate = :exchange_rate,
                    description = :description,
                    counterparty_name = :counterparty_name,
                    bank_reference_no = :bank_reference_no,
                    evidence_id = :evidence_id,
                    memo = :memo,
                    updated_at = NOW(),
                    updated_by = :actor
                WHERE id = :id
            ');
            $update->execute($values);
            $this->updateBankCounterpartyExtraFields($evidenceId, $payload, $actor);
            return;
        }

        $insert = $this->pdo->prepare('
            INSERT INTO ledger_bank_transactions
                (id, transaction_date, transaction_time, bank_account_id, transaction_type,
                 deposit_amount, withdraw_amount, balance_amount, balance_status, currency_code, exchange_rate,
                 description, counterparty_name, bank_reference_no, evidence_id, memo, created_by, updated_by)
            VALUES
                (:id, :transaction_date, :transaction_time, :bank_account_id, :transaction_type,
                 :deposit_amount, :withdraw_amount, :balance_amount, :balance_status, :currency_code, :exchange_rate,
                 :description, :counterparty_name, :bank_reference_no, :evidence_id, :memo, :created_by, :updated_by)
            ON DUPLICATE KEY UPDATE
                transaction_date = VALUES(transaction_date),
                transaction_time = VALUES(transaction_time),
                transaction_type = VALUES(transaction_type),
                deposit_amount = VALUES(deposit_amount),
                withdraw_amount = VALUES(withdraw_amount),
                balance_amount = VALUES(balance_amount),
                balance_status = VALUES(balance_status),
                currency_code = VALUES(currency_code),
                exchange_rate = VALUES(exchange_rate),
                description = VALUES(description),
                counterparty_name = VALUES(counterparty_name),
                bank_reference_no = VALUES(bank_reference_no),
                evidence_id = VALUES(evidence_id),
                memo = VALUES(memo),
                deleted_at = NULL,
                deleted_by = NULL,
                updated_at = NOW(),
                updated_by = VALUES(updated_by)
        ');
        $insertValues = $values;
        unset($insertValues[':actor']);
        $insert->execute($insertValues + [
            ':created_by' => $actor,
            ':updated_by' => $actor,
        ]);
        $this->updateBankCounterpartyExtraFields($evidenceId, $payload, $actor);
    }

    private function updateBankCounterpartyExtraFields(string $evidenceId, array $payload, string $actor): void
    {
        $sets = [];
        $params = [
            ':evidence_id' => $evidenceId,
            ':actor' => $actor,
        ];

        if ($this->tableColumnExists('ledger_bank_transactions', 'counterparty_account_number')) {
            $sets[] = 'counterparty_account_number = :counterparty_account_number';
            $params[':counterparty_account_number'] = $this->nullableString($payload['counterparty_account_number'] ?? $payload['counterparty_account_no'] ?? $payload['account_number'] ?? null);
        }
        if ($this->tableColumnExists('ledger_bank_transactions', 'counterparty_bank_name')) {
            $sets[] = 'counterparty_bank_name = :counterparty_bank_name';
            $params[':counterparty_bank_name'] = $this->nullableString($payload['counterparty_bank_name'] ?? $payload['counterparty_bank'] ?? $payload['counterparty_bank_name'] ?? $payload['bank_name'] ?? null);
        }
        if ($this->tableColumnExists('ledger_bank_transactions', 'transaction_datetime')) {
            $sets[] = 'transaction_datetime = :transaction_datetime';
            $params[':transaction_datetime'] = $this->dateTimeValue($payload['transaction_datetime'] ?? $payload['transaction_at'] ?? null);
        }
        if ($this->tableColumnExists('ledger_bank_transactions', 'check_bill_amount')) {
            $sets[] = 'check_bill_amount = :check_bill_amount';
            $params[':check_bill_amount'] = $this->amountOrNull($payload['check_bill_amount'] ?? $payload['check_amount'] ?? $payload['bill_amount'] ?? null);
        }

        if ($sets === []) {
            return;
        }

        $sets[] = 'updated_at = NOW()';
        $sets[] = 'updated_by = :actor';
        $stmt = $this->pdo->prepare('
            UPDATE ledger_bank_transactions
            SET ' . implode(', ', $sets) . '
            WHERE evidence_id = :evidence_id
              AND deleted_at IS NULL
        ');
        $stmt->execute($params);
    }

    private function ensureBankTransactionBalanceColumns(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        if (!$this->tableExists('ledger_bank_transactions')) {
            $checked = true;
            return;
        }

        if ($this->tableColumnExists('ledger_bank_transactions', 'balance_amount')) {
            try {
                $this->pdo->exec("
                    ALTER TABLE `ledger_bank_transactions`
                        MODIFY COLUMN `balance_amount` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'Actual bank balance after transaction'
                ");
            } catch (\Throwable) {
            }
        }

        if (!$this->tableColumnExists('ledger_bank_transactions', 'balance_status')) {
            try {
                $after = $this->tableColumnExists('ledger_bank_transactions', 'balance_amount') ? 'balance_amount' : 'withdraw_amount';
                $this->pdo->exec("
                    ALTER TABLE `ledger_bank_transactions`
                        ADD COLUMN `balance_status` VARCHAR(20) NULL DEFAULT 'EMPTY' COMMENT 'ACTUAL, EMPTY, ESTIMATED, INVALID' AFTER `{$after}`
                ");
            } catch (\Throwable) {
            }
        }

        if ($this->tableColumnExists('ledger_bank_transactions', 'balance_status')) {
            try {
                $this->pdo->exec("
                    UPDATE `ledger_bank_transactions`
                    SET `balance_status` = CASE
                        WHEN `balance_amount` IS NULL THEN 'EMPTY'
                        ELSE 'ACTUAL'
                    END
                    WHERE `balance_status` IS NULL OR `balance_status` = ''
                ");
            } catch (\Throwable) {
            }
        }

        $checked = true;
    }

    private function bankTransactionType(mixed $value, array $payload = []): string
    {
        $type = strtoupper(trim((string) $value));
        $aliases = [
            'IN' => 'DEPOSIT',
            'OUT' => 'WITHDRAW',
            '입금' => 'DEPOSIT',
            '출금' => 'WITHDRAW',
            '입금거래' => 'DEPOSIT',
            '출금거래' => 'WITHDRAW',
            '이체' => 'TRANSFER',
            '수수료' => 'FEE',
            '이자' => 'INTEREST',
        ];
        $type = $aliases[$type] ?? $type;
        if ($type === 'ETC' || $type === '') {
            $deposit = $this->amountOrNull($payload['deposit_amount'] ?? null);
            $withdraw = $this->amountOrNull($payload['withdraw_amount'] ?? $payload['withdrawal_amount'] ?? null);
            if ($withdraw !== null && $withdraw > 0) {
                $type = 'WITHDRAW';
            } elseif ($deposit !== null && $deposit > 0) {
                $type = 'DEPOSIT';
            }
        }

        return in_array($type, ['DEPOSIT', 'WITHDRAW', 'TRANSFER', 'CARD_PAYMENT', 'FEE', 'INTEREST', 'ETC'], true) ? $type : 'ETC';
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $this->payloadScalarForStorage($value));
        return $value === '' ? null : $value;
    }

    private function nullableUuid(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $this->isUuid($value) ? $value : null;
    }

    private function looksLikeBankAccountNumber(string $value): bool
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        return strlen($digits) >= 8 && strlen($digits) >= (int) floor(strlen(trim($value)) * 0.65);
    }

    private function tableColumnExists(string $tableName, string $columnName): bool
    {
        static $cache = [];
        $key = $tableName . '.' . $columnName;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

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
                ':table_name' => $tableName,
                ':column_name' => $columnName,
            ]);
            $cache[$key] = (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            $cache[$key] = false;
        }

        return $cache[$key];
    }

    private function tableExists(string $tableName): bool
    {
        static $cache = [];
        if (array_key_exists($tableName, $cache)) {
            return $cache[$tableName];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT 1
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
                LIMIT 1
            ");
            $stmt->execute([':table_name' => $tableName]);
            $cache[$tableName] = (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            $cache[$tableName] = false;
        }

        return $cache[$tableName];
    }

    private function ensureEvidenceBusinessInfoColumns(): void
    {
        if (!$this->tableExists('ledger_data_evidences')) {
            return;
        }

        $columns = [
            'employee_id' => ["VARCHAR(36) NULL DEFAULT NULL COMMENT 'Employee ID'", 'project_id'],
            'bank_account_id' => ["VARCHAR(36) NULL DEFAULT NULL COMMENT 'Bank account ID'", 'employee_id'],
            'card_id' => ["VARCHAR(36) NULL DEFAULT NULL COMMENT 'Card ID'", 'bank_account_id'],
            'client_name' => ["VARCHAR(255) NULL DEFAULT NULL COMMENT 'Client display name'", 'card_id'],
            'project_name' => ["VARCHAR(255) NULL DEFAULT NULL COMMENT 'Project display name'", 'client_name'],
            'employee_name' => ["VARCHAR(255) NULL DEFAULT NULL COMMENT 'Employee display name'", 'project_name'],
            'bank_account_name' => ["VARCHAR(255) NULL DEFAULT NULL COMMENT 'Bank account display name'", 'employee_name'],
            'card_name' => ["VARCHAR(255) NULL DEFAULT NULL COMMENT 'Card display name'", 'bank_account_name'],
        ];

        foreach ($columns as $column => [$definition, $after]) {
            if ($this->tableColumnExists('ledger_data_evidences', $column)) {
                continue;
            }
            $afterSql = $this->tableColumnExists('ledger_data_evidences', (string) $after) ? " AFTER `{$after}`" : '';
            try {
                $this->pdo->exec("
                    ALTER TABLE `ledger_data_evidences`
                        ADD COLUMN `{$column}` {$definition}{$afterSql}
                ");
            } catch (\Throwable) {
            }
        }

        foreach (['client_id', 'project_id', 'employee_id', 'bank_account_id', 'card_id'] as $column) {
            if (!$this->tableColumnExists('ledger_data_evidences', $column)) {
                continue;
            }
            try {
                $this->pdo->exec("
                    CREATE INDEX IF NOT EXISTS `idx_ledger_data_evidences_{$column}`
                        ON `ledger_data_evidences` (`{$column}`)
                ");
            } catch (\Throwable) {
            }
        }
    }

    private function ensureEvidenceSortColumns(): void
    {
        static $ensured = false;
        if ($ensured || !$this->tableExists('ledger_data_evidences')) {
            return;
        }
        $ensured = true;

        $existing = [];
        try {
            $stmt = $this->pdo->query("
                SELECT COLUMN_NAME
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'ledger_data_evidences'
            ");
            foreach (($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) as $column) {
                $existing[(string) $column] = true;
            }
        } catch (\Throwable) {
            return;
        }

        $columns = [
            'create_sort_no' => "INT UNSIGNED NULL DEFAULT NULL COMMENT 'Create center sequence'",
            'status_sort_no' => "INT UNSIGNED NULL DEFAULT NULL COMMENT 'Evidence type sequence'",
        ];
        foreach ($columns as $column => $definition) {
            if (isset($existing[$column])) {
                continue;
            }
            try {
                $this->pdo->exec("
                    ALTER TABLE `ledger_data_evidences`
                        ADD COLUMN `{$column}` {$definition}
                ");
                $existing[$column] = true;
            } catch (\Throwable) {
            }
        }

        foreach ([
            'idx_ledger_data_evidences_create_sort' => ['deleted_at', 'create_sort_no', 'id'],
            'idx_ledger_data_evidences_status_sort' => ['source_type', 'deleted_at', 'status_sort_no', 'id'],
        ] as $indexName => $columns) {
            try {
                $columnSql = implode('`, `', $columns);
                $this->pdo->exec("
                    CREATE INDEX IF NOT EXISTS `{$indexName}`
                        ON `ledger_data_evidences` (`{$columnSql}`)
                ");
            } catch (\Throwable) {
            }
        }

        $this->backfillEvidenceSortColumnIfNeeded('create_sort_no', '_create_sort_no');
        $this->backfillEvidenceSortColumnIfNeeded('status_sort_no', '_status_sort_no');
    }

    private function backfillEvidenceSortColumnIfNeeded(string $column, string $jsonKey): void
    {
        try {
            $stmt = $this->pdo->query("
                SELECT 1
                FROM `ledger_data_evidences`
                WHERE (`{$column}` IS NULL OR `{$column}` < 1)
                LIMIT 1
            ");
            if (!$stmt || !$stmt->fetchColumn()) {
                return;
            }

            $this->pdo->exec("
                UPDATE `ledger_data_evidences`
                SET `{$column}` = CAST(JSON_UNQUOTE(JSON_EXTRACT(`mapped_payload_json`, '$.{$jsonKey}')) AS UNSIGNED)
                WHERE (`{$column}` IS NULL OR `{$column}` < 1)
                  AND JSON_EXTRACT(`mapped_payload_json`, '$.{$jsonKey}') IS NOT NULL
            ");
        } catch (\Throwable) {
        }
    }

    private function isUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', trim($value));
    }

    private function storeUploadPreviewSession(array $format, array $file, array $rows): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $previewPath = tempnam(sys_get_temp_dir(), 'ledger_upload_preview_');
        if ($previewPath === false || $tmpName === '' || !is_file($tmpName) || !copy($tmpName, $previewPath)) {
            if (is_string($previewPath) && is_file($previewPath)) {
                @unlink($previewPath);
            }
            throw new \RuntimeException('?낅줈???꾩떆 ?뚯씪????ν븷 ???놁뒿?덈떎.');
        }

        $token = UuidHelper::generate();
        $_SESSION['ledger_upload_previews'][$token] = [
            'format' => $format,
            'file' => [
                'name' => (string) ($file['name'] ?? 'upload'),
                'tmp_name' => $previewPath,
                'error' => UPLOAD_ERR_OK,
                'size' => (int) ($file['size'] ?? 0),
                'type' => (string) ($file['type'] ?? ''),
            ],
            'rows' => $rows,
            'created_at' => time(),
        ];
        session_write_close();

        return $token;
    }

    private function uploadPreviewFromSession(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $preview = $_SESSION['ledger_upload_previews'][$token] ?? null;
        if (!is_array($preview)) {
            session_write_close();
            return null;
        }

        session_write_close();
        return $preview;
    }

    private function clearUploadPreviewSession(string $token): void
    {
        if ($token === '') {
            return;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $preview = $_SESSION['ledger_upload_previews'][$token] ?? null;
        $previewFile = is_array($preview['file'] ?? null) ? $preview['file'] : [];
        $tmpName = trim((string) ($previewFile['tmp_name'] ?? ''));
        if ($tmpName !== '' && is_file($tmpName)) {
            @unlink($tmpName);
        }
        unset($_SESSION['ledger_upload_previews'][$token]);
        session_write_close();
    }

    private function uploadCancelTokenFromPayload(array $payload): string
    {
        $token = trim((string) ($payload['upload_cancel_token'] ?? $payload['cancel_token'] ?? ''));
        return preg_match('/^[a-zA-Z0-9_-]{12,80}$/', $token) === 1 ? $token : '';
    }

    private function uploadCancelPath(string $token): string
    {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ledger_upload_cancel_' . $token;
    }

    private function markUploadCanceled(string $token): void
    {
        if ($token === '') {
            return;
        }

        @file_put_contents($this->uploadCancelPath($token), (string) time(), LOCK_EX);
    }

    private function clearUploadCancelToken(string $token): void
    {
        if ($token === '') {
            return;
        }

        $path = $this->uploadCancelPath($token);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function assertUploadNotCanceled(string $token): void
    {
        if ($token !== '' && is_file($this->uploadCancelPath($token))) {
            throw new \RuntimeException('업로드가 취소되었습니다.');
        }
    }

    private function uploadValidationSummary(array $rows): array
    {
        $summary = [
            'total' => count($rows),
            'ok' => 0,
            'warning' => 0,
            'error' => 0,
            'new' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'protected_update' => 0,
            'required_missing_rows' => 0,
            'required_missing_items' => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) ($row['_validation']['status'] ?? 'ok');
            if (isset($summary[$status])) {
                $summary[$status]++;
            }
            $action = strtolower((string) ($row['_seed_action'] ?? 'new'));
            if (isset($summary[$action])) {
                $summary[$action]++;
            }
            $requiredMissingCount = (int) ($row['_validation']['required_missing_count'] ?? 0);
            if ($requiredMissingCount > 0) {
                $summary['required_missing_rows']++;
                $summary['required_missing_items'] += $requiredMissingCount;
            }
        }

        return $summary;
    }

    private function uploadAllowsRequiredMissing(array $payload): bool
    {
        $value = $payload['allow_required_missing'] ?? $payload['confirm_required_missing'] ?? false;
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y'], true);
    }

    private function uploadRequiredMissingSummary(array $rows): array
    {
        $summary = [
            'rows' => 0,
            'items' => 0,
            'messages' => [],
        ];

        foreach ($rows as $row) {
            $validation = is_array($row['_validation'] ?? null) ? $row['_validation'] : [];
            $messages = array_values(array_filter(array_map('strval', is_array($validation['required_missing_messages'] ?? null) ? $validation['required_missing_messages'] : [])));
            if ($messages === []) {
                continue;
            }

            $summary['rows']++;
            $summary['items'] += count($messages);
            $rowNo = (int) ($row['_row_no'] ?? 0);
            $prefix = $rowNo > 0 ? "{$rowNo}행: " : '';
            foreach ($messages as $message) {
                $summary['messages'][] = $prefix . $message;
            }
        }

        $summary['messages'] = array_slice(array_values(array_unique($summary['messages'])), 0, 5);
        return $summary;
    }

    private function uploadRequiredMissingConfirmationMessage(array $summary): string
    {
        $rows = number_format((int) ($summary['rows'] ?? 0));
        $items = number_format((int) ($summary['items'] ?? 0));
        $examples = array_values(array_filter(array_map('strval', is_array($summary['messages'] ?? null) ? $summary['messages'] : [])));
        $message = "필수요소가 입력되어 있지 않습니다. {$rows}개 행, {$items}개 항목이 생성센터에서 보정필요로 처리됩니다. 그래도 업로드를 진행할까요?";
        if ($examples !== []) {
            $message .= "\n\n" . implode("\n", $examples);
        }

        return $message;
    }

    private function prepareLargeUploadRuntime(): void
    {
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(false);
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '1024M');
        }
    }

    private function uploadTrace(string $event, array $context = []): void
    {
        $parts = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $parts[] = $key . '=' . (string) $value;
            }
        }

        error_log('[ImportUpload] ' . $event . ($parts !== [] ? ' ' . implode(' ', $parts) : ''));
    }

    private function validateUploadFileColumns(array $file, array $columns): array
    {
        $spreadsheet = $this->loadUploadedSpreadsheetHeaderOnly($file);
        if ($spreadsheet->getSheetCount() > 1 && $this->hasBankVoucherLineColumns($columns)) {
            [$headerColumns, $lineColumns] = $this->splitBankFormatColumns($columns, $this->bankLineSheetHasRowTypeColumn($spreadsheet));
            $checks = array_merge(
                $this->validateSheetColumns($spreadsheet->getSheet(0), $headerColumns, true, '전표헤더'),
                $this->validateSheetColumns($spreadsheet->getSheet(1), $lineColumns, true, '분개라인')
            );
            $spreadsheet->disconnectWorksheets();
            return $checks;
        }

        $sheet = $spreadsheet->getActiveSheet();
        $headerRow = $sheet->rangeToArray('1:1', null, true, true, true)[1] ?? [];
        $spreadsheet->disconnectWorksheets();

        $checks = [];
        $headerColumnsByName = $this->uploadHeaderColumnsByName($headerRow);
        $usedHeaderColumns = [];
        foreach ($columns as $column) {
            $excelName = trim((string) ($column['excel_column_name'] ?? ''));
            $sheetColumn = $this->uploadSheetColumnForFormatColumn($column, $headerRow, $headerColumnsByName, $usedHeaderColumns);
            $actualName = $sheetColumn !== null ? trim((string) ($headerRow[$sheetColumn] ?? '')) : '';
            $actualName = preg_replace('/\s*\*$/u', '', $actualName) ?? $actualName;

            if (self::isRequiredFormatColumn($column) && $actualName === '') {
                $checks[] = ['level' => 'warning', 'message' => "필수컬럼 누락: {$excelName}"];
                continue;
            }
            if ($excelName !== '' && $actualName !== '') {
                $checks[] = ['level' => 'ok', 'message' => "{$excelName} 매핑 성공"];
            }
        }

        return $checks;
    }

    private function validateSheetColumns(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $columns, bool $sequentialColumns, string $sheetLabel): array
    {
        $headerRow = $sheet->rangeToArray('1:1', null, true, true, true)[1] ?? [];
        $checks = [];
        $headerColumnsByName = $this->uploadHeaderColumnsByName($headerRow);
        $usedHeaderColumns = [];

        foreach (array_values($columns) as $column) {
            $excelName = trim((string) ($column['excel_column_name'] ?? ''));
            $sheetColumn = $this->uploadSheetColumnForFormatColumn($column, $headerRow, $headerColumnsByName, $usedHeaderColumns);
            $actualName = $sheetColumn !== null ? trim((string) ($headerRow[$sheetColumn] ?? '')) : '';
            $actualName = preg_replace('/\s*\*$/u', '', $actualName) ?? $actualName;

            if (self::isRequiredFormatColumn($column) && $actualName === '') {
                $checks[] = ['level' => 'warning', 'message' => "{$sheetLabel} 필수컬럼 누락: {$excelName}"];
                continue;
            }
            if ($excelName !== '' && $actualName !== '') {
                $checks[] = ['level' => 'ok', 'message' => "{$sheetLabel} {$excelName} 매핑 성공"];
            }
        }

        return $checks;
    }

    private function assertUploadFileMatchesFormat(array $checks, array $columns): void
    {
        $configuredCount = 0;
        foreach ($columns as $column) {
            if (trim((string) ($column['excel_column_name'] ?? '')) !== '') {
                $configuredCount++;
            }
        }

        if ($configuredCount < 1) {
            throw new \RuntimeException('선택한 양식에 업로드 컬럼 설정이 없습니다. 양식 관리를 확인하세요.');
        }

        $matchedCount = 0;
        foreach ($checks as $check) {
            if (($check['level'] ?? '') === 'ok') {
                $matchedCount++;
            }
        }

        $minimumMatched = min(2, $configuredCount);
        if ($matchedCount < $minimumMatched) {
            throw new \RuntimeException("업로드 파일의 헤더가 선택한 양식과 일치하지 않습니다. 매칭된 컬럼 {$matchedCount}개 / 양식 컬럼 {$configuredCount}개입니다. 올바른 양식 파일을 선택하세요.");
        }
    }

    private function annotateSeedComparison(array $rows, string $dataType): array
    {
        $this->preloadExistingSeedRowsForUploadRows($rows, $dataType);
        foreach ($rows as &$row) {
            $parsed = $this->mappedPayloadForStorage($row);
            $sourceKey = $this->seedSourceKey($parsed, $dataType);
            $row['_seed_key'] = $sourceKey;
            $row['_seed_action'] = 'NEW';
            if ($sourceKey === null) {
                continue;
            }

            $existing = $this->findExistingSeedRow($dataType, $sourceKey);
            if (!$existing && $this->usesFingerprintSourceKey($dataType)) {
                $existing = $this->findExistingSeedRowByFingerprint($dataType, $parsed);
            }
            if (!$existing) {
                continue;
            }

            $rawJson = $this->jsonEncodeForStorage(is_array($row['_raw_payload'] ?? null) ? $row['_raw_payload'] : []);
            $parsedJson = $this->jsonEncodeForStorage($parsed);
            $existingParsed = json_decode((string) ($existing['mapped_payload_json'] ?? ''), true);
            $existingParsed = is_array($existingParsed) ? $existingParsed : [];
            unset($existingParsed['_status_sort_no'], $existingParsed['_create_sort_no']);
            $isUnchanged = (string) ($existing['raw_json'] ?? '') === $rawJson && $this->jsonEncodeForStorage($existingParsed) === $parsedJson;
            $row['_seed_action'] = $isUnchanged
                ? 'UNCHANGED'
                : ($this->isUploadProtectedExistingSeed($existing) ? 'PROTECTED_UPDATE' : 'UPDATED');
        }
        unset($row);

        return $rows;
    }

    private function preloadExistingSeedRowsForUploadRows(array $rows, string $dataType): void
    {
        $sourceKeys = [];
        $dataType = self::normalizeDataType($dataType);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $parsedPayload = $this->mappedPayloadForStorage($row);
            if ($dataType === 'BANK_TRANSACTION') {
                $parsedPayload = $this->normalizeBankTransactionPayload($parsedPayload);
            }
            $sourceKey = $this->seedSourceKey($parsedPayload, $dataType);
            if ($sourceKey === null) {
                $sourceKey = hash('sha256', $dataType . '|' . $this->jsonEncodeForStorage(is_array($row['_raw_payload'] ?? null) ? $row['_raw_payload'] : $parsedPayload));
            }
            if ($sourceKey !== '') {
                $sourceKeys[] = $sourceKey;
            }
        }

        $this->preloadExistingSeedRowsBySourceKeys($dataType, $sourceKeys);
    }

    private function preloadExistingSeedRowsBySourceKeys(string $sourceType, array $sourceKeys): void
    {
        $sourceType = self::normalizeDataType($sourceType);
        $sourceKeys = array_values(array_unique(array_filter(array_map(static fn(mixed $value): string => trim((string) $value), $sourceKeys))));
        if ($sourceKeys === []) {
            return;
        }

        $missing = [];
        foreach ($sourceKeys as $sourceKey) {
            $cacheKey = $sourceType . '|' . $sourceKey;
            if (!array_key_exists($cacheKey, $this->existingSeedRowCache)) {
                $missing[] = $sourceKey;
            }
        }
        if ($missing === []) {
            return;
        }

        foreach (array_chunk($missing, 1000) as $chunkIndex => $chunk) {
            $params = [':source_type' => $sourceType];
            $placeholders = [];
            foreach ($chunk as $index => $sourceKey) {
                $name = ':source_key_' . $chunkIndex . '_' . $index;
                $placeholders[] = $name;
                $params[$name] = $sourceKey;
            }

            $stmt = $this->pdo->prepare("
                SELECT id, source_key, raw_json, mapped_payload_json, evidence_status, transaction_status, voucher_status, " . $this->evidenceTransactionIdSelect() . "
                FROM ledger_data_evidences
                WHERE source_type = :source_type
                  AND source_key IN (" . implode(', ', $placeholders) . ")
                ORDER BY source_key ASC, created_at DESC
            ");
            $stmt->execute($params);

            $seen = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $sourceKey = (string) ($row['source_key'] ?? '');
                if ($sourceKey === '' || isset($seen[$sourceKey])) {
                    continue;
                }
                $seen[$sourceKey] = true;
                $this->existingSeedRowCache[$sourceType . '|' . $sourceKey] = $row;
            }

            foreach ($chunk as $sourceKey) {
                $cacheKey = $sourceType . '|' . $sourceKey;
                if (!array_key_exists($cacheKey, $this->existingSeedRowCache)) {
                    $this->existingSeedRowCache[$cacheKey] = null;
                }
            }
        }
    }

    private function seedSourceKey(array $row, string $dataType): ?string
    {
        $dataType = self::normalizeDataType($dataType);
        $explicitSourceKey = trim((string) ($row['source_key'] ?? ''));
        if ($dataType !== 'BANK_TRANSACTION' && $explicitSourceKey !== '') {
            return $explicitSourceKey;
        }
        if ($dataType === 'BANK_TRANSACTION') {
            $bankReferenceNo = trim((string) ($row['bank_reference_no'] ?? ''));
            $transactionDateTime = $this->dateTimeValue($row['transaction_datetime'] ?? $row['transaction_at'] ?? null);
            $transactionDate = $this->dateValue($row['transaction_date'] ?? $row['evidence_date'] ?? '');
            $transactionTime = trim((string) ($row['transaction_time'] ?? ''));
            $parts = [
                $transactionDateTime ?: trim($transactionDate . ' ' . $transactionTime),
                $this->businessRefIdForStorage('ACCOUNT', $row) ?? '',
                $row['bank_account_name'] ?? '',
                $this->number($row['deposit_amount'] ?? 0),
                $this->number($row['withdraw_amount'] ?? $row['withdrawal_amount'] ?? 0),
                $this->amountOrNull($row['balance_amount'] ?? null) ?? '',
                $this->amountOrNull($row['check_bill_amount'] ?? null) ?? '',
                $row['description'] ?? '',
                $row['counterparty_name'] ?? '',
                $row['counterparty_account_number'] ?? $row['counterparty_account_no'] ?? '',
                $row['counterparty_bank_name'] ?? $row['counterparty_bank'] ?? '',
                $bankReferenceNo,
            ];

            $parts = array_map(static fn(mixed $value): string => trim((string) $value), $parts);
            if (implode('', $parts) === '') {
                return null;
            }

            return hash('sha256', $dataType . '|bank|' . implode('|', $parts));
        }

        $parts = [];
        if ($dataType === 'TAX_INVOICE' || self::isManualTaxInvoiceDataType($dataType)) {
            $parts = [
                $row['approval_number'] ?? '',
                $this->normalizeBusinessNumber((string) ($row['supplier_business_number'] ?? '')),
                $this->normalizeBusinessNumber((string) ($row['customer_business_number'] ?? '')),
                $this->dateValue($row['issue_date'] ?? $row['transaction_date'] ?? ''),
            ];
        } elseif ($dataType === 'CARD_HOMETAX') {
            return $this->sourceFingerprintKey($row, $dataType);
        } elseif (in_array($dataType, ['CARD_STATEMENT', 'CARD_APPROVAL'], true)) {
            return $this->sourceFingerprintKey($row, $dataType);
        } else {
            $parts = [
                $row['approval_number'] ?? '',
                $this->dateValue($row['transaction_date'] ?? $row['issue_date'] ?? ''),
                $this->normalizeBusinessNumber((string) ($row['supplier_business_number'] ?? $row['customer_business_number'] ?? $row['client_business_number'] ?? $row['merchant_business_number'] ?? '')),
                $this->number($row['total_amount'] ?? 0),
                trim((string) ($row['description'] ?? $row['merchant_company_name'] ?? '')),
            ];
        }

        $parts = array_map(static fn(mixed $value): string => trim((string) $value), $parts);
        if (implode('', $parts) === '') {
            return null;
        }

        return hash('sha256', $dataType . '|' . implode('|', $parts));
    }

    private function sourceFingerprintKey(array $row, string $dataType): string
    {
        $payload = $this->sourceFingerprintPayload($row);
        if ($payload === []) {
            return hash('sha256', self::normalizeDataType($dataType) . '|empty');
        }

        return hash('sha256', self::normalizeDataType($dataType) . '|fingerprint|' . $this->jsonEncodeForStorage($payload));
    }

    private function sourceFingerprintPayload(array $row): array
    {
        $skipKeys = [
            '_row_no' => true,
            '_upload_row_no' => true,
            '_raw_payload' => true,
            '_validation' => true,
            '_seed_key' => true,
            '_seed_action' => true,
            '_status_sort_no' => true,
            '_create_sort_no' => true,
            '_voucher_lines' => true,
        ];

        $payload = [];
        foreach ($row as $key => $value) {
            $key = (string) $key;
            if ($key === '' || isset($skipKeys[$key]) || str_starts_with($key, '_')) {
                continue;
            }
            if (preg_match('/^column_\d+$/', $key) === 1) {
                continue;
            }
            if (preg_match('/^\d+$/', $key) === 1) {
                continue;
            }
            $normalized = $this->sourceFingerprintValue($value);
            if ($normalized === '') {
                continue;
            }
            $payload[$key] = $normalized;
        }

        ksort($payload);

        return $payload;
    }

    private function sourceFingerprintValue(mixed $value): string
    {
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $itemValue = $this->sourceFingerprintValue($item);
                if ($itemValue !== '') {
                    $normalized[(string) $key] = $itemValue;
                }
            }
            ksort($normalized);
            return $normalized !== [] ? $this->jsonEncodeForStorage($normalized) : '';
        }

        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        $amount = $this->amountOrNull($text);
        if ($amount !== null && preg_match('/[0-9]/', $text) === 1 && !preg_match('/^\d{4}[-\/.]\d{1,2}[-\/.]\d{1,2}/', $text)) {
            return rtrim(rtrim(sprintf('%.6F', $amount), '0'), '.');
        }

        $date = $this->dateValueOrNull($text);
        if ($date !== null) {
            return $date;
        }

        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }

    private function findExistingSeedRow(string $sourceType, string $sourceKey): ?array
    {
        $cacheKey = self::normalizeDataType($sourceType) . '|' . $sourceKey;
        if (array_key_exists($cacheKey, $this->existingSeedRowCache)) {
            return $this->existingSeedRowCache[$cacheKey];
        }

        $stmt = $this->pdo->prepare("
            SELECT id, raw_json, mapped_payload_json, evidence_status, transaction_status, voucher_status, " . $this->evidenceTransactionIdSelect() . "
            FROM ledger_data_evidences
            WHERE source_type = :source_type
              AND source_key = :source_key
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':source_type' => self::normalizeDataType($sourceType),
            ':source_key' => $sourceKey,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $this->existingSeedRowCache[$cacheKey] = $row;

        return $row;
    }

    private function findExistingSeedRowByFingerprint(string $sourceType, array $payload): ?array
    {
        $sourceType = self::normalizeDataType($sourceType);
        $fingerprint = $this->sourceFingerprintKey($payload, $sourceType);
        if (!isset($this->existingSeedFingerprintCache[$sourceType])) {
            $this->existingSeedFingerprintCache[$sourceType] = $this->loadExistingSeedFingerprintIndex($sourceType);
        }

        return $this->existingSeedFingerprintCache[$sourceType][$fingerprint] ?? null;
    }

    private function loadExistingSeedFingerprintIndex(string $sourceType): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, source_key, raw_json, mapped_payload_json, evidence_status, transaction_status, voucher_status, " . $this->evidenceTransactionIdSelect() . "
            FROM ledger_data_evidences
            WHERE deleted_at IS NULL
              AND source_type = :source_type
        ");
        $stmt->execute([':source_type' => self::normalizeDataType($sourceType)]);

        $index = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $payload = json_decode((string) ($row['mapped_payload_json'] ?? ''), true);
            if (!is_array($payload)) {
                continue;
            }
            $fingerprint = $this->sourceFingerprintKey($payload, $sourceType);
            if (!isset($index[$fingerprint])) {
                $index[$fingerprint] = $row;
            }
        }

        return $index;
    }

    private function usesFingerprintSourceKey(string $dataType): bool
    {
        return in_array(self::normalizeDataType($dataType), ['CARD_HOMETAX', 'CARD_STATEMENT', 'CARD_APPROVAL'], true);
    }

    private function nextEvidenceJsonSortNo(string $key, string $sourceType = ''): int
    {
        $sortColumn = $key === '_create_sort_no' ? 'create_sort_no' : ($key === '_status_sort_no' ? 'status_sort_no' : '');
        $where = ['deleted_at IS NULL'];
        $params = [];
        $sourceType = self::normalizeDataType($sourceType);
        if ($sourceType !== '') {
            $where[] = 'source_type = :source_type';
            $params[':source_type'] = $sourceType;
        }

        if ($sortColumn !== '' && $this->tableColumnExists('ledger_data_evidences', $sortColumn)) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT COALESCE(MAX(`{$sortColumn}`), 0)
                    FROM ledger_data_evidences
                    WHERE " . implode(' AND ', $where)
                );
                $stmt->execute($params);
                return ((int) $stmt->fetchColumn()) + 1;
            } catch (\Throwable) {
            }
        }

        $stmt = $this->pdo->prepare("
            SELECT mapped_payload_json
            FROM ledger_data_evidences
            WHERE " . implode(' AND ', $where)
        );
        $stmt->execute($params);

        $max = 0;
        while ($json = $stmt->fetchColumn()) {
            $payload = json_decode((string) $json, true);
            if (!is_array($payload)) {
                continue;
            }
            $value = $payload[$key] ?? 0;
            if (is_string($value)) {
                $value = str_replace(',', '', trim($value));
            }
            if (is_numeric($value)) {
                $max = max($max, (int) $value);
            }
        }

        return $max + 1;
    }

    private function assignEvidenceJsonSortNo(array &$payload, array $existingPayload, string $key, int &$nextSortNo): void
    {
        $existing = $existingPayload[$key] ?? null;
        if (is_string($existing)) {
            $existing = str_replace(',', '', trim($existing));
        }
        if (is_numeric($existing) && (int) $existing > 0) {
            $payload[$key] = (int) $existing;
            return;
        }

        $current = $payload[$key] ?? null;
        if (is_string($current)) {
            $current = str_replace(',', '', trim($current));
        }
        if (is_numeric($current) && (int) $current > 0) {
            $payload[$key] = (int) $current;
            return;
        }

        $payload[$key] = $nextSortNo++;
    }

    private function mappedPayloadForStorage(array $row): array
    {
        $mapped = [];
        $keepInternalKeys = [
            '_transaction_lines' => true,
            '_transaction_files' => true,
            '_voucher_lines' => true,
        ];
        foreach ($row as $key => $value) {
            if (str_starts_with((string) $key, '_') && !isset($keepInternalKeys[(string) $key])) {
                continue;
            }
            $mapped[$key] = isset($keepInternalKeys[(string) $key])
                ? $value
                : $this->payloadScalarForStorage($value, str_ends_with((string) $key, '_id'));
        }
        if (!isset($mapped['transaction_date']) && isset($mapped['evidence_date'])) {
            $mapped['transaction_date'] = $mapped['evidence_date'];
        }
        if (!isset($mapped['write_date']) && isset($mapped['evidence_date'])) {
            $mapped['write_date'] = $mapped['evidence_date'];
        }
        if (!isset($mapped['approval_number']) && isset($mapped['source_key'])) {
            $mapped['approval_number'] = $mapped['source_key'];
        }
        if (!isset($mapped['withdrawal_amount']) && isset($mapped['withdraw_amount'])) {
            $mapped['withdrawal_amount'] = $mapped['withdraw_amount'];
        }
        if (
            empty($mapped['transaction_datetime'])
            && !empty($mapped['transaction_date'])
            && preg_match('/\d{1,2}:\d{2}/', (string) $mapped['transaction_date'])
        ) {
            $mapped['transaction_datetime'] = $mapped['transaction_date'];
        }
        $mapped = $this->normalizeMappedPayloadDateValues($this->normalizeMappedClientReference($mapped));
        return $this->normalizeBusinessRefPayload($mapped);
    }

    private function payloadScalarForStorage(mixed $value, bool $preferId = false): mixed
    {
        if ($value === null || is_scalar($value)) {
            $text = trim((string) $value);
            if ($text === '[object Object]' || $this->isEmptySelectionLabel($text)) {
                return '';
            }
            return $value;
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                $parts = [];
                foreach ($value as $item) {
                    $text = trim((string) $this->payloadScalarForStorage($item, $preferId));
                    if ($text !== '') {
                        $parts[] = $text;
                    }
                }

                return implode(', ', array_values(array_unique($parts)));
            }

            $candidates = $preferId
                ? ['id', 'value', 'code', 'text', 'label', 'name']
                : ['text', 'label', 'name', 'value', 'code_name', 'code', 'client_name', 'company_name', 'project_name', 'employee_name', 'account_name', 'bank_name'];
            foreach ($candidates as $key) {
                if (array_key_exists($key, $value)) {
                    $text = trim((string) $this->payloadScalarForStorage($value[$key], false));
                    if ($text !== '') {
                        return $text;
                    }
                }
            }

            return '';
        }

        return '';
    }

    private function normalizeMappedClientReference(array $mapped): array
    {
        if (!isset($mapped['client_id'])) {
            return $mapped;
        }

        $clientId = trim((string) $mapped['client_id']);
        if ($clientId === '' || $this->isUuid($clientId)) {
            return $mapped;
        }

        if ($this->looksLikeBankAccountNumber($clientId)) {
            $mapped['counterparty_account_number'] = $mapped['counterparty_account_number'] ?? $clientId;
        } else {
            $mapped['client_company_name'] = $mapped['client_company_name'] ?? $clientId;
            $mapped['merchant_company_name'] = $mapped['merchant_company_name'] ?? $clientId;
        }
        unset($mapped['client_id']);

        return $mapped;
    }

    private function normalizeMappedPayloadDateValues(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $keyText = strtolower((string) $key);
            if ($this->isMappedDateTimeKey($keyText)) {
                $normalized = $this->mappedDateTimeValueOrNull($value);
                if ($normalized !== null) {
                    $payload[$key] = $normalized;
                }
                continue;
            }

            if (!str_contains($keyText, 'date') && !in_array($keyText, ['write_date', 'written_date', 'issue_date', 'transmit_date'], true)) {
                continue;
            }

            $normalized = $this->dateValueOrNull($value);
            if ($normalized !== null) {
                $payload[$key] = $normalized;
            }
        }

        return $payload;
    }

    private function isMappedDateTimeKey(string $key): bool
    {
        return str_contains($key, 'datetime')
            || in_array($key, [
                'transaction_at',
                'approval_at',
                'approved_at',
                'purchase_at',
                'issued_at',
                'transmitted_at',
            ], true);
    }

    private function mappedDateTimeValueOrNull(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '' || $text === '-' || $text === '0000-00-00') {
            return null;
        }

        if (is_numeric($text) && abs((float) $text - floor((float) $text)) > 0.000001) {
            return $this->dateTimeValue($text);
        }

        if (!preg_match('/\d{1,2}:\d{2}/', $text)) {
            return $this->dateValueOrNull($text);
        }

        return $this->dateTimeValue($text) ?? $this->dateValueOrNull($text);
    }

    private function normalizeEvidenceMappedPayloadForResponse(array $payload): array
    {
        $payload = $this->normalizeMappedClientReference($payload);
        $aliases = [
            'client_name' => ['client_name', '거래처명', '거래처'],
            'project_name' => ['project_name', 'project_code'],
            'employee_name' => ['employee_name', 'user_name'],
            'bank_account_name' => ['bank_account_name', 'bank_account', 'account_name', 'payment_account_name', 'account_number', 'payment_account_number'],
            'card_name' => ['card_name', 'card_number', 'card_company_name'],
            'supplier_company_name' => ['supplier_company_name', 'supplier_name', '공급자 상호', '공급자명'],
            'supplier_name' => ['supplier_name', 'supplier_company_name', '공급자 상호', '공급자명'],
            'customer_company_name' => ['customer_company_name', 'customer_name', '공급받는자 상호', '공급받는자명'],
            'customer_name' => ['customer_name', 'customer_company_name', '공급받는자 상호', '공급받는자명'],
            'supplier_business_number' => ['supplier_business_number', '공급자 사업자등록번호'],
            'customer_business_number' => ['customer_business_number', '공급받는자 사업자등록번호'],
            'item_name' => ['item_name', '품목명', '품목'],
            'item_spec' => ['item_spec', '품목규격', '규격'],
            'item_qty' => ['item_qty', '품목수량', '수량'],
            'item_price' => ['item_price', '품목단가', '단가'],
            'issue_date' => ['issue_date', '발급일자', '발행일자'],
            'transmit_date' => ['transmit_date', '전송일자'],
            'note' => ['note', '비고'],
            'description' => ['description', '적요'],
            'counterparty_name' => ['counterparty_name', 'counterparty_account_holder_name', 'counterparty_account_holder', 'account_holder'],
            'counterparty_account_number' => ['counterparty_account_number', 'counterparty_account_no', 'account_number'],
            'counterparty_bank_name' => ['counterparty_bank_name', 'counterparty_bank', 'bank_name'],
            'counterparty_bank' => ['counterparty_bank', 'counterparty_bank_name', 'bank_name'],
            'client_company_name' => ['client_company_name', 'counterparty_name', '가맹점', '가맹점명', '사용처'],
        ];

        foreach ($aliases as $target => $keys) {
            if (isset($payload[$target]) && trim((string) $this->payloadScalarForStorage($payload[$target])) !== '') {
                $payload[$target] = $this->payloadScalarForStorage($payload[$target]);
                continue;
            }
            foreach ($keys as $key) {
                $value = $this->payloadScalarForStorage($payload[$key] ?? null);
                if ($value !== null && trim((string) $value) !== '') {
                    $payload[$target] = $value;
                    break;
                }
            }
        }

        return $this->normalizeMappedClientReference($this->normalizeBusinessRefPayload($payload));
    }

    private function businessRefIdForStorage(string $refType, array $payload): ?string
    {
        $refType = $this->normalizeVoucherRefType($refType);
        foreach ($this->businessRefCandidateValues($refType, $payload, true) as $value) {
            $resolved = $refType === 'ACCOUNT'
                ? $this->resolveBankAccountId($value)
                : $this->resolveVoucherRefId($refType, $value);
            if ($resolved !== null && $resolved !== '') {
                return $resolved;
            }
        }

        return null;
    }

    private function businessRefNameForStorage(string $refType, array $payload): ?string
    {
        $refType = $this->normalizeVoucherRefType($refType);
        $uuidCandidate = null;
        if ($refType === 'ACCOUNT') {
            $accountId = (string) ($this->businessRefIdForStorage('ACCOUNT', $payload) ?? '');
            if ($accountId !== '' && $this->isUuid($accountId)) {
                $resolvedName = $this->businessRefNameById('ACCOUNT', $accountId);
                if ($resolvedName !== null && $resolvedName !== '') {
                    return $resolvedName;
                }
            }
        }
        if ($refType === 'CLIENT') {
            $clientId = (string) ($this->businessRefIdForStorage('CLIENT', $payload) ?? '');
            if ($clientId !== '' && $this->isUuid($clientId)) {
                $resolvedName = $this->businessRefNameById('CLIENT', $clientId);
                if ($resolvedName !== null && $resolvedName !== '') {
                    return $resolvedName;
                }
            }
            $clientName = $this->clientNameFromImportParty($payload);
            if ($this->isEmptySelectionLabel($clientName)) {
                $clientName = '';
            }
            if ($clientName !== '' && !$this->isUuid($clientName)) {
                return $clientName;
            }
            if ($this->isUuid($clientName)) {
                $uuidCandidate = $clientName;
            }
        }

        foreach ($this->businessRefCandidateValues($refType, $payload, false) as $value) {
            $value = trim((string) $value);
            if ($this->isEmptySelectionLabel($value)) {
                continue;
            }
            if ($value !== '' && !$this->isUuid($value)) {
                return $value;
            }
            if ($value !== '' && $this->isUuid($value) && $uuidCandidate === null) {
                $uuidCandidate = $value;
            }
        }

        if ($uuidCandidate !== null) {
            $resolvedName = $this->businessRefNameById($refType, $uuidCandidate);
            if ($resolvedName !== null && $resolvedName !== '') {
                return $resolvedName;
            }
        }

        return null;
    }

    private function businessRefCandidateValues(string $refType, array $payload, bool $includeIds): array
    {
        $keys = match ($this->normalizeVoucherRefType($refType)) {
            'CLIENT' => $includeIds
                ? ['client_id', 'client_name', 'client_name_ko', 'client_name_en', 'company_name_ko', 'company_name_en', 'client_company_name', 'client_company_name_ko', 'client_company_name_en', 'counterparty_name', 'merchant_company_name', 'supplier_company_name', 'customer_company_name', 'client_business_number', 'supplier_business_number', 'customer_business_number']
                : ['client_name', 'client_name_ko', 'client_name_en', 'client_id'],
            'PROJECT' => $includeIds
                ? ['project_id', 'project_name', 'project_code']
                : ['project_name', 'project_code', 'project_id'],
            'EMPLOYEE' => $includeIds
                ? ['employee_id', 'employee_name', 'user_name', 'user_id']
                : ['employee_name', 'user_name', 'employee_id'],
            'ACCOUNT' => $includeIds
                ? ['bank_account_id', 'account_number', 'payment_account_number', 'bank_account_name', 'bank_account', 'account_name', 'payment_account_name', 'bank_name', 'payment_bank_name']
                : ['bank_account_name', 'bank_account', 'account_name', 'payment_account_name', 'bank_account_id', 'account_number', 'payment_account_number'],
            'CARD' => $includeIds
                ? ['card_id', 'card_name', 'card_number', 'card_company_name']
                : ['card_name', 'card_number', 'card_company_name', 'card_id'],
            default => [],
        };

        $values = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }
            $value = trim((string) $payload[$key]);
            if ($value !== '' && !$this->isEmptySelectionLabel($value)) {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    private function isEmptySelectionLabel(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return true;
        }

        $labels = [
            '__none__',
            '__CODE_NONE__',
            '_none_',
            '__none',
            'none__',
            '--none--',
            '선택(없음)',
            '선택',
            '선택(없음)',
            '직접 선택',
            '거래처 선택',
            '프로젝트 선택',
            '직원 선택',
            '계좌 선택',
            '카드 선택',
            '사업구분 선택',
            '거래구분 선택',
            '거래유형 선택',
            '자료출처 선택',
            '자료유형 선택',
            '라인유형 선택',
            '차대구분 선택',
        ];
        if (in_array($value, $labels, true)) {
            return true;
        }

        return str_ends_with($value, ' 선택') || str_ends_with($value, '필수');
    }

    private function mergeEvidenceBusinessInfoIntoPayload(array $evidenceRow, array &$payload): void
    {
        $payload = $this->normalizeBusinessRefPayload($payload);
        foreach (['client_id', 'project_id', 'employee_id', 'bank_account_id', 'card_id', 'client_name', 'project_name', 'employee_name', 'bank_account_name', 'card_name'] as $key) {
            $rowValue = trim((string) ($evidenceRow[$key] ?? ''));
            if ($rowValue === '' || $this->isEmptySelectionLabel($rowValue)) {
                continue;
            }
            $payloadValue = trim((string) $this->payloadScalarForStorage($payload[$key] ?? null));
            if ($payloadValue === '' || $this->isEmptySelectionLabel($payloadValue) || ($this->isUuid($payloadValue) && str_ends_with($key, '_name'))) {
                $payload[$key] = $rowValue;
            }
        }
        $payload = $this->normalizeBusinessRefPayload($payload);
    }

    private function normalizeBusinessRefPayload(array $payload): array
    {
        foreach ($this->businessRefPayloadKeyMap() as $refType => $keys) {
            $idKey = $keys['id'];
            $nameKey = $keys['name'];
            $id = trim((string) ($payload[$idKey] ?? ''));
            $name = trim((string) $this->payloadScalarForStorage($payload[$nameKey] ?? null));
            if ($this->isEmptySelectionLabel($id)) {
                $id = '';
            }
            if ($this->isEmptySelectionLabel($name)) {
                $name = '';
            }

            if ($id === '' && $this->isUuid($name)) {
                $id = $name;
            }
            if ($id === '') {
                $id = (string) ($this->businessRefIdForStorage($refType, $payload) ?? '');
            }

            $displayName = '';
            if ($name !== '' && !$this->isUuid($name)) {
                $displayName = $name;
            } elseif ($id !== '') {
                $displayName = (string) ($this->businessRefNameById($refType, $id) ?? '');
            }
            if ($refType === 'ACCOUNT' && $id !== '') {
                $resolvedName = (string) ($this->businessRefNameById($refType, $id) ?? '');
                if ($resolvedName !== '') {
                    $displayName = $resolvedName;
                }
            }
            if ($displayName === '') {
                $displayName = (string) ($this->businessRefNameForStorage($refType, $payload) ?? '');
            }

            if ($id !== '' && $this->isUuid($id)) {
                $payload[$idKey] = $id;
            }
            if ($displayName !== '' && !$this->isUuid($displayName)) {
                $payload[$nameKey] = $displayName;
            }
        }

        return $payload;
    }

    private function businessRefPayloadKeyMap(): array
    {
        return [
            'CLIENT' => ['id' => 'client_id', 'name' => 'client_name'],
            'PROJECT' => ['id' => 'project_id', 'name' => 'project_name'],
            'EMPLOYEE' => ['id' => 'employee_id', 'name' => 'employee_name'],
            'ACCOUNT' => ['id' => 'bank_account_id', 'name' => 'bank_account_name'],
            'CARD' => ['id' => 'card_id', 'name' => 'card_name'],
        ];
    }

    private function businessRefNameById(string $refType, string $id): ?string
    {
        $refType = $this->normalizeVoucherRefType($refType);
        $id = trim($id);
        if ($id === '' || !$this->isUuid($id)) {
            return null;
        }

        $cacheKey = $refType . '|' . $id;
        if (array_key_exists($cacheKey, $this->voucherRefNameCache)) {
            return $this->voucherRefNameCache[$cacheKey];
        }

        $table = match ($refType) {
            'CLIENT' => 'system_clients',
            'PROJECT' => 'system_projects',
            'EMPLOYEE' => 'user_employees',
            'ACCOUNT' => 'system_bank_accounts',
            'CARD' => 'system_cards',
            default => null,
        };
        if ($table === null || !$this->tableExists($table) || !$this->tableColumnExists($table, 'id')) {
            $this->voucherRefNameCache[$cacheKey] = null;
            return null;
        }

        $columns = match ($refType) {
            'CLIENT' => ['client_name', 'company_name'],
            'PROJECT' => ['project_name', 'construction_name', 'project_code'],
            'EMPLOYEE' => ['employee_name', 'name', 'username'],
            'ACCOUNT' => ['account_name', 'bank_account_name', 'bank_name', 'account_number'],
            'CARD' => ['card_name', 'card_number', 'card_company_name'],
            default => [],
        };
        $selects = [];
        foreach ($columns as $column) {
            if ($this->tableColumnExists($table, $column)) {
                $selects[] = $column;
            }
        }
        if ($selects === []) {
            $this->voucherRefNameCache[$cacheKey] = null;
            return null;
        }

        $deleted = $this->tableColumnExists($table, 'deleted_at') ? ' AND deleted_at IS NULL' : '';
        $stmt = $this->pdo->prepare('SELECT ' . implode(', ', $selects) . " FROM {$table} WHERE id = :id{$deleted} LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        foreach ($selects as $column) {
            $value = trim((string) ($row[$column] ?? ''));
            if ($value !== '' && !$this->isUuid($value)) {
                $this->voucherRefNameCache[$cacheKey] = $value;
                return $value;
            }
        }

        $this->voucherRefNameCache[$cacheKey] = null;
        return null;
    }

    private function jsonEncodeForStorage(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function seedRowIdsFromPayload(array $payload): array
    {
        $ids = [];
        if (is_array($payload['seed_row_ids'] ?? null)) {
            $ids = array_merge($ids, $payload['seed_row_ids']);
        }
        if (is_array($payload['ids'] ?? null)) {
            $ids = array_merge($ids, $payload['ids']);
        }
        foreach (['seed_row_id', 'id'] as $key) {
            if (!empty($payload[$key])) {
                $ids[] = $payload[$key];
            }
        }

        return array_values(array_unique(array_filter(array_map('strval', $ids))));
    }

    private function formatIdsFromPayload(array $payload): array
    {
        $ids = [];
        if (is_array($payload['format_ids'] ?? null)) {
            $ids = array_merge($ids, $payload['format_ids']);
        }
        if (is_array($payload['ids'] ?? null)) {
            $ids = array_merge($ids, $payload['ids']);
        }
        foreach (['format_id', 'id'] as $key) {
            if (!empty($payload[$key])) {
                $ids[] = $payload[$key];
            }
        }

        return array_values(array_unique(array_filter(array_map('strval', $ids))));
    }

    private function seedRowFiltersFromRequest(): array
    {
        $decoded = [];
        if (!empty($_GET['filters'])) {
            $json = json_decode((string) $_GET['filters'], true);
            $decoded = is_array($json) ? $json : [];
        }

        return array_values(array_filter($decoded, static function ($filter): bool {
            return is_array($filter) && !empty($filter['field']);
        }));
    }

    private function seedRowMatchesFilters(array $row, array $filters): bool
    {
        foreach ($filters as $filter) {
            $field = (string) ($filter['field'] ?? '');
            $value = $filter['value'] ?? '';
            if ($field === '' || $value === '' || $value === null) {
                continue;
            }

            $actual = $this->seedRowFilterValue($row, $field);
            if (is_array($value)) {
                $start = (string) ($value['start'] ?? '');
                $end = (string) ($value['end'] ?? '');
                $text = (string) $actual;
                if ($start !== '' && $text < $start) {
                    return false;
                }
                if ($end !== '' && $text > $end) {
                    return false;
                }
                continue;
            }

            $needles = array_values(array_filter(array_map('trim', explode(',', (string) $value))));
            if ($needles === []) {
                continue;
            }

            $haystack = mb_strtolower((string) $actual);
            $matched = false;
            foreach ($needles as $needle) {
                if ($needle !== '' && str_contains($haystack, mb_strtolower($needle))) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    private function seedRowFilterValue(array $row, string $field): string
    {
        if ($field === 'source_type') {
            $value = (string) ($row['source_type'] ?? '');
            return trim($value . ' ' . (string) ($row['source_type_name'] ?? '') . ' ' . self::sourceTypeLabel($value));
        }

        if ($field === 'import_type') {
            $value = (string) ($row['import_type'] ?? $row['seed_source_type'] ?? '');
            return trim($value . ' ' . (string) ($row['import_type_name'] ?? '') . ' ' . self::importTypeLabel($value));
        }

        if ($field === 'client_name') {
            $mapped = is_array($row['mapped_payload'] ?? null) ? $row['mapped_payload'] : [];
            $clientId = trim((string) ($row['client_id'] ?? $mapped['client_id'] ?? ''));
            $resolvedClientName = $clientId !== '' && $this->isUuid($clientId)
                ? (string) ($this->businessRefNameById('CLIENT', $clientId) ?? '')
                : '';
            return (string) (
                ($resolvedClientName !== '' ? $resolvedClientName : null)
                ?? $row['client_name']
                ?? $mapped['client_name']
                ?? $mapped['client_company_name']
                ?? $mapped['client_business_number']
                ?? $mapped['supplier_company_name']
                ?? $mapped['customer_company_name']
                ?? ''
            );
        }

        if (str_starts_with($field, 'mapped_payload.')) {
            $key = substr($field, strlen('mapped_payload.'));
            $mapped = is_array($row['mapped_payload'] ?? null) ? $row['mapped_payload'] : [];
            return (string) ($mapped[$key] ?? '');
        }

        return (string) ($row[$field] ?? '');
    }

    private function placeholdersForIds(array $ids, string $prefix): array
    {
        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $key = ':' . $prefix . '_' . $index;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        return [implode(', ', $placeholders), $params];
    }

    private function uploadBatch(string $batchId): ?array
    {
        return null;
    }

    private function uploadRowsForTransactionCreate(string $batchId, array $rowIds = []): array
    {
        return $this->seedRowsForTransactionCreate($batchId, $rowIds);
    }

    private function claimSeedRowsForTransactionCreate(string $batchId = '', array $rowIds = []): array
    {
        $this->ensureEvidenceBusinessInfoColumns();
        $params = [];
        $claimableStatuses = $rowIds !== [] ? ["'NONE'", "'PROCESSING'", "'ERROR'"] : ["'NONE'"];
        $claimableEvidenceStatuses = $rowIds !== [] ? ["'ACTIVE'", "'ERROR'"] : ["'ACTIVE'"];
        $where = [
            'evidence_status IN (' . implode(', ', $claimableEvidenceStatuses) . ')',
            'transaction_status IN (' . implode(', ', $claimableStatuses) . ')',
            'deleted_at IS NULL',
        ];
        $transactionTypes = self::transactionProcessingDataTypes();
        $typePlaceholders = [];
        foreach ($transactionTypes as $index => $type) {
            $key = ':transaction_type_' . $index;
            $typePlaceholders[] = $key;
            $params[$key] = $type;
        }
        $where[] = 'source_type IN (' . implode(', ', $typePlaceholders) . ')';
        if ($this->evidenceHasTransactionIdColumn()) {
            $where[] = 'transaction_id IS NULL';
        }
        if ($batchId !== '') {
            $where[] = 'DATE(latest_imported_at) = :batch_id';
            $params[':batch_id'] = $batchId;
        }
        if ($rowIds !== []) {
            $placeholders = [];
            foreach ($rowIds as $index => $rowId) {
                $key = ':row_id_' . $index;
                $placeholders[] = $key;
                $params[$key] = $rowId;
            }
            $where[] = 'id IN (' . implode(', ', $placeholders) . ')';
        }

        $this->pdo->beginTransaction();
        try {
            $select = $this->pdo->prepare("
                SELECT id, source_type, source_key, evidence_date,
                       client_id, project_id, employee_id, bank_account_id, card_id,
                       client_name, project_name, employee_name, bank_account_name, card_name,
                       mapped_payload_json
                FROM ledger_data_evidences
                WHERE " . implode(' AND ', $where) . "
                ORDER BY COALESCE(evidence_date, DATE(latest_imported_at), DATE(created_at)) DESC, latest_imported_at DESC, created_at DESC
                FOR UPDATE
            ");
            $select->execute($params);
            $candidateRows = $select->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $ids = [];
            foreach ($candidateRows as $candidateRow) {
                $mapped = json_decode((string) ($candidateRow['mapped_payload_json'] ?? ''), true);
                $mapped = is_array($mapped) ? $this->normalizeEvidenceMappedPayloadForResponse($mapped) : [];
                $this->mergeEvidenceBusinessInfoIntoPayload($candidateRow, $mapped);
                $readiness = $this->readinessForEvidenceRow([
                    'source_type' => $candidateRow['source_type'] ?? '',
                    'import_type' => $candidateRow['source_type'] ?? '',
                    'source_key' => $candidateRow['source_key'] ?? '',
                    'evidence_date' => $candidateRow['evidence_date'] ?? '',
                ], $mapped);
                $processingType = (string) ($readiness['processing_type'] ?? '');
                if (($readiness['status'] ?? '') === 'READY' && in_array($processingType, ['TRANSACTION', 'BANK_FLOW'], true)) {
                    $ids[] = (string) ($candidateRow['id'] ?? '');
                }
            }
            if ($ids === []) {
                $this->pdo->commit();
                return [];
            }

            $idParams = [];
            $idPlaceholders = [];
            foreach ($ids as $index => $id) {
                $key = ':claim_id_' . $index;
                $idPlaceholders[] = $key;
                $idParams[$key] = $id;
            }
            $idParams[':actor'] = ActorHelper::user();
            $update = $this->pdo->prepare("
                UPDATE ledger_data_evidences
                SET transaction_status = 'PROCESSING',
                    error_message = NULL,
                    updated_at = NOW(),
                    updated_by = :actor
                WHERE id IN (" . implode(', ', $idPlaceholders) . ")
                  AND evidence_status IN (" . implode(', ', $claimableEvidenceStatuses) . ")
                  AND transaction_status IN (" . implode(', ', $claimableStatuses) . ")
                  " . ($this->evidenceHasTransactionIdColumn() ? 'AND transaction_id IS NULL' : '') . "
            ");
            $update->execute($idParams);
            $this->pdo->commit();

            return $this->seedRowsForTransactionCreate('', $ids, 'PROCESSING');
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function seedRowsForTransactionCreate(string $batchId = '', array $rowIds = [], string $status = 'READY'): array
    {
        $this->ensureEvidenceBusinessInfoColumns();
        // TODO: Add an explicit ERROR -> READY retry API/button for seed rows.
        $params = [];
        $where = ['deleted_at IS NULL'];
        if ($this->evidenceHasTransactionIdColumn()) {
            $where[] = 'transaction_id IS NULL';
        }
        if ($status === 'PROCESSING') {
            $where[] = "transaction_status = 'PROCESSING'";
        } else {
            $where[] = "evidence_status = 'ACTIVE'";
            $where[] = "transaction_status = 'NONE'";
        }
        $transactionTypes = self::transactionProcessingDataTypes();
        $typePlaceholders = [];
        foreach ($transactionTypes as $index => $type) {
            $key = ':transaction_type_' . $index;
            $typePlaceholders[] = $key;
            $params[$key] = $type;
        }
        $where[] = 'source_type IN (' . implode(', ', $typePlaceholders) . ')';
        if ($batchId !== '') {
            $where[] = 'DATE(latest_imported_at) = :batch_id';
            $params[':batch_id'] = $batchId;
        }
        if ($rowIds !== []) {
            $placeholders = [];
            foreach ($rowIds as $index => $rowId) {
                $key = ':row_id_' . $index;
                $placeholders[] = $key;
                $params[$key] = $rowId;
            }
            $where[] = 'id IN (' . implode(', ', $placeholders) . ')';
        }

        $stmt = $this->pdo->prepare("
            SELECT
                id,
                NULL AS batch_id,
                source_type,
                source_key,
                evidence_date,
                client_id,
                project_id,
                employee_id,
                bank_account_id,
                card_id,
                client_name,
                project_name,
                employee_name,
                bank_account_name,
                card_name,
                0 AS row_no,
                raw_json AS raw_payload,
                mapped_payload_json AS mapped_payload,
                CASE
                    WHEN evidence_status = 'ERROR' OR transaction_status = 'ERROR' THEN 'ERROR'
                    WHEN evidence_status = 'DUPLICATED' OR transaction_status = 'DUPLICATED' THEN 'DUPLICATED'
                    WHEN transaction_status = 'PROCESSING' THEN 'PROCESSING'
                    WHEN " . $this->evidenceCreatedTransactionSql() . " THEN 'PROCESSED'
                    ELSE 'READY'
                END AS status,
                error_message,
                voucher_status,
                " . $this->evidenceTransactionIdSelect() . "
            FROM ledger_data_evidences
            WHERE " . implode(' AND ', $where) . "
            ORDER BY COALESCE(evidence_date, DATE(latest_imported_at), DATE(created_at)) DESC, latest_imported_at DESC, created_at DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $index => &$row) {
            $row['row_no'] = $index + 1;
            $row['raw_payload'] = json_decode((string) ($row['raw_payload'] ?? ''), true) ?: [];
            $row['mapped_payload'] = json_decode((string) ($row['mapped_payload'] ?? ''), true) ?: [];
            $row['mapped_payload'] = $this->normalizeEvidenceMappedPayloadForResponse($row['mapped_payload']);
            $this->mergeEvidenceBusinessInfoIntoPayload($row, $row['mapped_payload']);
            $readiness = $this->readinessForEvidenceRow([
                'source_type' => $row['source_type'] ?? '',
                'import_type' => $row['source_type'] ?? '',
                'source_key' => $row['source_key'] ?? '',
                'evidence_date' => $row['evidence_date'] ?? '',
            ], $row['mapped_payload']);
            $row['readiness_status'] = $readiness['status'];
            $row['readiness_errors'] = $readiness['errors'];
            $row['missing_fields'] = $readiness['missing_fields'];
            $row['processing_type'] = $readiness['processing_type'];
            $row['processing_objects'] = $readiness['processing_objects'];
            $row['processing_label'] = $readiness['processing_label'];
        }
        unset($row);
        return $rows;
    }

    private function countTransactionLines(array $transactionIds): int
    {
        $transactionIds = array_values(array_filter(array_map('strval', $transactionIds)));
        if ($transactionIds === []) {
            return 0;
        }

        $placeholders = [];
        $params = [];
        foreach ($transactionIds as $index => $transactionId) {
            $key = ':transaction_id_' . $index;
            $placeholders[] = $key;
            $params[$key] = $transactionId;
        }

        $stmt = $this->pdo->prepare('
            SELECT COUNT(*)
            FROM ledger_transaction_lines
            WHERE transaction_id IN (' . implode(', ', $placeholders) . ')
              AND deleted_at IS NULL
        ');
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function decodeMappedPayload(mixed $payload): ?array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (!is_string($payload) || trim($payload) === '') {
            return null;
        }

        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function updateUploadRowStatus(string $rowId, string $status, ?string $message, ?string $transactionId = null): void
    {
        $isCorrectionError = $status === 'ERROR' && $this->isGenerationCorrectionMessage($message);
        $processStatus = match ($status) {
            'CREATED', 'PROCESSED' => 'PROCESSED',
            'DUPLICATE', 'DUPLICATED' => 'DUPLICATED',
            'PROCESSING' => 'PROCESSING',
            'ERROR' => 'ERROR',
            default => 'READY',
        };
        if ($isCorrectionError) {
            $processStatus = 'READY';
        }

        $transactionSql = $this->evidenceHasTransactionIdColumn()
            ? 'transaction_id = COALESCE(:transaction_id, transaction_id),'
            : '';
        $stmt = $this->pdo->prepare("
            UPDATE ledger_data_evidences
            SET transaction_status = :transaction_status,
                evidence_status = :evidence_status,
                voucher_status = CASE
                    WHEN :voucher_status IS NULL THEN voucher_status
                    ELSE :voucher_status
                END,
                error_message = :error_message,
                {$transactionSql}
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE id = :id
        ");
        $params = [
            ':id' => $rowId,
            ':transaction_status' => $processStatus === 'PROCESSED' ? 'CREATED' : ($processStatus === 'PROCESSING' ? 'PROCESSING' : 'NONE'),
            ':evidence_status' => $processStatus === 'ERROR' ? 'ERROR' : ($processStatus === 'DUPLICATED' ? 'DUPLICATED' : 'ACTIVE'),
            ':voucher_status' => $isCorrectionError ? 'WAITING' : ($processStatus === 'ERROR' ? 'ERROR' : null),
            ':error_message' => $isCorrectionError ? null : $message,
            ':updated_by' => ActorHelper::user(),
        ];
        if ($this->evidenceHasTransactionIdColumn()) {
            $params[':transaction_id'] = $transactionId !== '' ? $transactionId : null;
        }
        $stmt->execute($params);
    }

    private function isGenerationCorrectionMessage(?string $message): bool
    {
        $text = trim((string) $message);
        if ($text === '') {
            return false;
        }

        return (bool) preg_match('/필수값\s*없음|신규\s*생성\s*예정|미매칭|미기재/u', $text);
    }

    private function refreshUploadBatchStatus(string $batchId): void
    {
        return;
    }

    private function evidenceHasTransactionIdColumn(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'ledger_data_evidences'
              AND COLUMN_NAME = 'transaction_id'
            LIMIT 1
        ");
        $stmt->execute();
        $exists = (bool) $stmt->fetchColumn();

        return $exists;
    }

    private function evidenceTransactionIdSelect(string $alias = ''): string
    {
        if (!$this->evidenceHasTransactionIdColumn()) {
            return 'NULL AS transaction_id';
        }

        return ($alias !== '' ? $alias . '.' : '') . 'transaction_id';
    }

    private function evidenceCreatedTransactionSql(string $alias = ''): string
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        $conditions = [
            "{$prefix}transaction_status IN ('CREATED', 'PROCESSED', 'DONE', 'COMPLETED', 'POSTED')",
        ];
        if ($this->evidenceHasTransactionIdColumn()) {
            $conditions[] = "({$prefix}transaction_id IS NOT NULL AND {$prefix}transaction_id <> '')";
        }

        return '(' . implode(' OR ', $conditions) . ')';
    }

    private function isUploadProtectedExistingSeed(array $row): bool
    {
        return $this->existingSeedHasCreatedTransaction($row) || $this->existingSeedHasCreatedVoucher($row);
    }

    private function existingSeedHasCreatedTransaction(array $row): bool
    {
        $transactionStatus = strtoupper(trim((string) ($row['transaction_status'] ?? '')));
        $transactionId = trim((string) ($row['transaction_id'] ?? ''));

        return $transactionId !== '' || in_array($transactionStatus, ['CREATED', 'PROCESSED', 'DONE', 'COMPLETED', 'POSTED'], true);
    }

    private function existingSeedHasCreatedVoucher(array $row): bool
    {
        $voucherStatus = strtoupper(trim((string) ($row['voucher_status'] ?? '')));

        return in_array($voucherStatus, ['CREATED', 'PROCESSED', 'DONE', 'COMPLETED', 'POSTED'], true);
    }

    private function format(string $id): ?array
    {
        if ($id === '') {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM ledger_data_formats WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row) {
            $row['data_type'] = self::normalizeDataType((string) ($row['data_type'] ?? ''));
        }

        return $row;
    }

    private function activeFormatCount(): int
    {
        return (int) $this->pdo
            ->query('SELECT COUNT(*) FROM ledger_data_formats WHERE deleted_at IS NULL')
            ->fetchColumn();
    }

    private function formatWithColumns(string $id): ?array
    {
        $format = $this->format($id);
        if (!$format) {
            return null;
        }
        $format['columns'] = $this->columns($id);
        return $format;
    }

    private function columns(string $formatId): array
    {
        $format = $this->format($formatId);
        $dataType = self::normalizeDataType((string) ($format['data_type'] ?? 'TAX_INVOICE'));
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM ledger_data_format_columns
            WHERE format_id = :format_id
              AND (system_field_name IS NULL OR system_field_name <> 'tax_type')
            ORDER BY column_order ASC, excel_column_index ASC, id ASC
        ");
        $stmt->execute([':format_id' => $formatId]);
        $fieldOptions = $this->systemFieldOptionsByValue($dataType);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $validFields = array_flip(array_keys($fieldOptions));
        foreach ($columns as &$column) {
            if (!array_key_exists('is_visible', $column)) {
                $column['is_visible'] = 1;
            }
            $field = trim((string) ($column['system_field_name'] ?? ''));
            if ($field !== '') {
                $column['system_field_name'] = $this->canonicalSystemFieldForFormatColumn(
                    $dataType,
                    (string) ($column['excel_column_name'] ?? ''),
                    $field
                );
                $field = trim((string) ($column['system_field_name'] ?? ''));
            }
            if ($field !== '' && !isset($validFields[$field])) {
                if (in_array($field, self::FORMAT_DEPRECATED_SYSTEM_FIELDS, true)) {
                    continue;
                }
                $column['system_field_name'] = $this->legacySystemFieldToDbColumn($dataType, $field);
                $field = trim((string) ($column['system_field_name'] ?? ''));
            }
            $fieldOption = $fieldOptions[$field] ?? null;
            $column['is_required'] = self::normalizeRequirementMode($column['is_required'] ?? 0);
            $column['system_field_label'] = is_array($fieldOption) ? (string) ($fieldOption['label'] ?? '') : '';
            $column['system_field_group'] = is_array($fieldOption) ? (string) ($fieldOption['group'] ?? '') : '';
            $column['system_field_table'] = is_array($fieldOption) ? (string) ($fieldOption['table'] ?? '') : '';
            $column['system_field_column'] = is_array($fieldOption) ? (string) ($fieldOption['column'] ?? '') : '';
            $column['is_reference_column'] = is_array($fieldOption) && $this->templateDropdownListKey($fieldOption) !== '' ? 1 : 0;
        }
        unset($column);
        $columns = array_values(array_filter($columns, fn(array $column): bool => !$this->isHiddenFormatColumn($column, $dataType)));

        return $this->formatColumnsInOrder($columns);
    }

    private function formatColumnHasVisible(bool $refresh = false): bool
    {
        static $hasVisible = null;
        if ($hasVisible !== null && !$refresh) {
            return $hasVisible;
        }

        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM ledger_data_format_columns LIKE 'is_visible'");
            $hasVisible = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $hasVisible = false;
        }

        return $hasVisible;
    }

    private function ensureFormatColumnVisibleColumn(): bool
    {
        if ($this->formatColumnHasVisible(true)) {
            return true;
        }

        try {
            $this->pdo->exec("
                ALTER TABLE ledger_data_format_columns
                    ADD COLUMN is_visible TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Default visibility in data status table' AFTER is_required
            ");
        } catch (\Throwable) {
            return $this->formatColumnHasVisible(true);
        }

        return $this->formatColumnHasVisible(true);
    }

    private function legacySystemFieldToDbColumn(string $dataType, string $field): ?string
    {
        $field = trim($field);
        if ($field === '') {
            return null;
        }

        $dataType = self::normalizeDataType($dataType);
        if ($dataType === 'BANK_TRANSACTION') {
            return [
                'transaction_date' => 'transaction_date',
                'transaction_datetime' => 'transaction_datetime',
                'transaction_at' => 'transaction_datetime',
                'transaction_time' => 'transaction_datetime',
                'bank_account_id' => 'bank_account_name',
                'bank_account' => 'bank_account_name',
                'bank_account_name' => 'bank_account_name',
                'account_name' => 'bank_account_name',
                'payment_account_name' => 'bank_account_name',
                'transaction_type' => 'transaction_type',
                'bank_direction' => 'bank_direction',
                'bank_transaction_type' => 'bank_direction',
                'transaction_direction' => 'transaction_direction',
                'deposit_amount' => 'deposit_amount',
                'withdraw_amount' => 'withdraw_amount',
                'withdrawal_amount' => 'withdraw_amount',
                'balance_amount' => 'balance_amount',
                'check_bill_amount' => 'check_bill_amount',
                'check_amount' => 'check_bill_amount',
                'bill_amount' => 'check_bill_amount',
                'currency' => 'currency_code',
                'currency_code' => 'currency_code',
                'exchange_rate' => 'exchange_rate',
                'description' => 'description',
                'counterparty_name' => 'counterparty_name',
                'client_name' => 'counterparty_name',
                'counterparty_account_holder_name' => 'counterparty_name',
                'counterparty_account_holder' => 'counterparty_name',
                'account_holder' => 'counterparty_name',
                'counterparty_account_number' => 'counterparty_account_number',
                'counterparty_account_no' => 'counterparty_account_number',
                'account_number' => 'counterparty_account_number',
                'counterparty_bank_name' => 'counterparty_bank_name',
                'counterparty_bank' => 'counterparty_bank_name',
                'bank_name' => 'counterparty_bank_name',
                'bank_reference_no' => 'bank_reference_no',
                'approval_number' => 'bank_reference_no',
                'approval_no' => 'bank_reference_no',
                'source_key' => 'bank_reference_no',
                'memo' => 'memo',
                'note' => 'memo',
                'debit_account_id' => 'debit_account_id',
                'debit_amount' => 'debit_amount',
                'debit_line_summary' => 'debit_line_summary',
                'credit_account_id' => 'credit_account_id',
                'credit_amount' => 'credit_amount',
                'credit_line_summary' => 'credit_line_summary',
            ][$field] ?? null;
        }

        return [
            'source_key' => 'source_key',
            'approval_number' => 'source_key',
            'approval_no' => 'source_key',
            'transaction_date' => 'evidence_date',
            'write_date' => 'evidence_date',
            'evidence_date' => 'evidence_date',
            'client_id' => 'client_id',
            'project_id' => 'project_id',
            'bank_account_id' => 'bank_account_name',
            'bank_account' => 'bank_account_name',
            'bank_account_name' => 'bank_account_name',
            'account_name' => 'bank_account_name',
            'payment_account_name' => 'bank_account_name',
            'currency' => 'currency',
            'currency_code' => 'currency',
            'supply_amount' => 'supply_amount',
            'vat_amount' => 'vat_amount',
            'total_amount' => 'total_amount',
            'amount' => 'total_amount',
        ][$field] ?? null;
    }

    private function normalizeColumns(array $columns, string $dataType): array
    {
        $rows = [];
        $usedFields = [];
        $validFields = array_flip($this->systemFieldService()->fieldNames($dataType));
        foreach (array_values($columns) as $index => $column) {
            if (!is_array($column)) {
                continue;
            }

            $excelColumn = trim((string) ($column['excel_column_name'] ?? ''));
            $systemField = trim((string) ($column['system_field_name'] ?? ''));
            $systemField = $systemField === '' ? null : $systemField;
            if ($systemField !== null) {
                $systemField = $this->canonicalSystemFieldForFormatColumn($dataType, $excelColumn, $systemField);
            }
            if ($systemField !== null && !isset($validFields[$systemField])) {
                $systemField = $this->legacySystemFieldToDbColumn($dataType, $systemField);
            }

            if ($excelColumn === '') {
                continue;
            }
            if ($systemField !== null) {
                if (isset($usedFields[$systemField])) {
                    throw new \RuntimeException('시스템 필드는 중복 선택할 수 없습니다: ' . self::fieldLabel($systemField));
                }
                $usedFields[$systemField] = true;
            }

            $rows[] = [
                'excel_column_name' => $excelColumn,
                'excel_column_index' => (int) ($column['excel_column_index'] ?? ($index + 1)),
                'system_field_name' => $systemField,
                'column_order' => (int) ($column['column_order'] ?? ($index + 1)),
                'is_visible' => array_key_exists('is_visible', $column) ? (!empty($column['is_visible']) ? 1 : 0) : 1,
                'is_required' => self::normalizeRequirementMode($column['is_required'] ?? 0),
            ];
        }

        usort($rows, static fn(array $a, array $b): int => $a['column_order'] <=> $b['column_order']);
        foreach ($rows as $index => &$row) {
            $row['column_order'] = $index + 1;
            $row['excel_column_index'] = $index + 1;
        }
        unset($row);

        return $rows;
    }

    private static function normalizeRequirementMode(mixed $value): int
    {
        $mode = (int) $value;

        return in_array($mode, [0, 1, 2], true) ? $mode : 0;
    }

    private static function isRequiredFormatColumn(array $column): bool
    {
        if (self::isOptionalEvidenceFormatColumn($column)) {
            return false;
        }

        return self::normalizeRequirementMode($column['is_required'] ?? 0) === 1;
    }

    private static function isOptionalEvidenceFormatColumn(array $column): bool
    {
        $field = trim((string) ($column['system_field_name'] ?? ''));
        $label = preg_replace('/\s+/u', '', trim((string) ($column['excel_column_name'] ?? ''))) ?? '';

        return in_array($field, ['balance_amount', 'supplier_address', 'customer_address'], true)
            || in_array($label, ['거래후잔액', '잔액', '공급자주소', '공급받는자주소'], true);
    }

    private function parseUploadedRows(array $file, array $columns): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('업로드 파일을 읽을 수 없습니다.');
        }

        $spreadsheet = $this->loadUploadedSpreadsheet($file);
        if ($spreadsheet->getSheetCount() > 1 && $this->hasBankVoucherLineColumns($columns)) {
            $rows = $this->parseUploadedBankWorkbook($spreadsheet, $columns);
            $spreadsheet->disconnectWorksheets();
            return $rows;
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rawRows = $sheet->toArray(null, true, true, true);
        $spreadsheet->disconnectWorksheets();
        if (count($rawRows) < 2) {
            return [];
        }

        $headerRow = array_values($rawRows)[0] ?? [];
        array_shift($rawRows);

        $mappedColumns = [];
        $rawColumns = [];
        $headerColumnsByName = $this->uploadHeaderColumnsByName($headerRow);
        $usedHeaderColumns = [];
        foreach ($columns as $column) {
            $excelName = trim((string) ($column['excel_column_name'] ?? ''));
            $systemField = trim((string) ($column['system_field_name'] ?? ''));
            $sheetColumn = $this->uploadSheetColumnForFormatColumn($column, $headerRow, $headerColumnsByName, $usedHeaderColumns);
            if ($excelName === '' || $sheetColumn === null) {
                continue;
            }
            $columnIndex = (int) ($column['excel_column_index'] ?? $column['column_order'] ?? 0);
            $rawColumns[] = [
                'sheet_column' => $sheetColumn,
                'column_index' => $columnIndex,
                'column_name' => $excelName,
            ];
            if ($systemField === '') {
                $systemField = null;
            }
            $mappedColumns[] = [
                'sheet_column' => $sheetColumn,
                'system_field_name' => $systemField,
                'excel_column_name' => $excelName,
                'payload_key' => $this->payloadKeyFromExcelColumn($excelName, $columnIndex),
            ];
        }

        $rows = [];
        foreach (array_values($rawRows) as $rowIndex => $rawRow) {
            $rawPayload = [];
            foreach ($rawColumns as $column) {
                $index = (int) ($column['column_index'] ?? 0);
                $rawPayload[(string) $index] = [
                    'column_index' => $index,
                    'column_name' => (string) ($column['column_name'] ?? ''),
                    'value' => $this->cellValue($rawRow[$column['sheet_column']] ?? null),
                ];
            }
            $mapped = [];
            foreach ($mappedColumns as $column) {
                $value = $this->cellValue($rawRow[$column['sheet_column']] ?? null);
                if ($column['system_field_name'] !== null) {
                    $mapped[$column['system_field_name']] = $value;
                }
                if ($column['payload_key'] !== null && $column['payload_key'] !== $column['system_field_name']) {
                    $mapped[$column['payload_key']] = $value;
                }
                if ($column['excel_column_name'] !== '' && $column['excel_column_name'] !== $column['system_field_name'] && $column['excel_column_name'] !== $column['payload_key']) {
                    $mapped[$column['excel_column_name']] = $value;
                }
            }
            if (implode('', array_map(static fn($value): string => trim((string) $value), $mapped)) === '') {
                continue;
            }
            $mapped['_row_no'] = $rowIndex + 2;
            $mapped['_raw_payload'] = $rawPayload;
            $rows[] = $mapped;
        }

        return $rows;
    }

    private function hasBankVoucherLineColumns(array $columns): bool
    {
        $lineFields = array_flip(self::BANK_VOUCHER_LINE_FIELDS);
        foreach ($columns as $column) {
            if (isset($lineFields[(string) ($column['system_field_name'] ?? '')])) {
                return true;
            }
        }

        return false;
    }

    private function parseUploadedBankWorkbook(Spreadsheet $spreadsheet, array $columns): array
    {
        [$headerColumns, $lineColumns] = $this->splitBankFormatColumns($columns, $this->bankLineSheetHasRowTypeColumn($spreadsheet));
        $headerRows = $this->parseSheetMappedRows($spreadsheet->getSheet(0), $headerColumns, true);
        $lineRows = $this->parseSheetMappedRows($spreadsheet->getSheet(1), $lineColumns, true);

        $linesByHeaderRow = [];
        foreach ($lineRows as $line) {
            $headerRowNo = (int) ($line['header_row_no'] ?? 0);
            if ($headerRowNo < 2) {
                continue;
            }
            $line['line_row_type'] = $this->normalizeBankVoucherLineRowType($line['line_row_type'] ?? null);
            unset($line['_raw_payload']);
            $linesByHeaderRow[$headerRowNo][] = $line;
        }

        foreach ($headerRows as &$row) {
            $row['_voucher_lines'] = $linesByHeaderRow[(int) ($row['_row_no'] ?? 0)] ?? [];
        }
        unset($row);

        return $headerRows;
    }

    private function bankLineSheetHasRowTypeColumn(Spreadsheet $spreadsheet): bool
    {
        if ($spreadsheet->getSheetCount() < 2) {
            return false;
        }

        $headerRow = $spreadsheet->getSheet(1)->rangeToArray('1:1', null, true, true, true)[1] ?? [];
        foreach ($headerRow as $header) {
            $header = preg_replace('/\s*\*$/u', '', trim((string) $header)) ?? trim((string) $header);
            if ($header === '행타입' || $header === 'line_row_type') {
                return true;
            }
        }

        return false;
    }

    private function normalizeBankVoucherLineRowType(mixed $value): string
    {
        $rawValue = trim((string) ($value ?? ''));
        if ($rawValue === '보조') {
            return 'AUX';
        }
        if ($rawValue === '분개') {
            return 'JOURNAL';
        }
        $value = strtoupper($rawValue);
        return match ($value) {
            '보조', 'AUX', 'AUXILIARY', 'REF', 'REFERENCE' => 'AUX',
            default => 'JOURNAL',
        };
    }

    private function parseSheetMappedRows(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $columns, bool $sequentialColumns = false): array
    {
        $rawRows = $sheet->toArray(null, true, true, true);
        if (count($rawRows) < 2) {
            return [];
        }
        $headerRow = array_values($rawRows)[0] ?? [];
        array_shift($rawRows);

        $mappedColumns = [];
        $headerColumnsByName = $this->uploadHeaderColumnsByName($headerRow);
        $usedHeaderColumns = [];
        foreach (array_values($columns) as $index => $column) {
            $excelName = trim((string) ($column['excel_column_name'] ?? ''));
            $systemField = trim((string) ($column['system_field_name'] ?? ''));
            $sheetColumn = $this->uploadSheetColumnForFormatColumn($column, $headerRow, $headerColumnsByName, $usedHeaderColumns);
            if ($excelName === '' || $sheetColumn === null) {
                continue;
            }
            $mappedColumns[] = [
                'sheet_column' => $sheetColumn,
                'system_field_name' => $systemField !== '' ? $systemField : null,
                'excel_column_name' => $excelName,
                'payload_key' => $this->payloadKeyFromExcelColumn($excelName, (int) ($column['excel_column_index'] ?? $column['column_order'] ?? ($index + 1))),
                'column_index' => (int) ($column['excel_column_index'] ?? $column['column_order'] ?? ($index + 1)),
            ];
        }

        $rows = [];
        foreach (array_values($rawRows) as $rowIndex => $rawRow) {
            $mapped = [];
            $rawPayload = [];
            foreach ($mappedColumns as $column) {
                $value = $this->cellValue($rawRow[$column['sheet_column']] ?? null);
                $rawPayload[(string) $column['column_index']] = [
                    'column_index' => $column['column_index'],
                    'column_name' => $column['excel_column_name'],
                    'value' => $value,
                ];
                if ($column['system_field_name'] !== null) {
                    $mapped[$column['system_field_name']] = $value;
                }
                if ($column['payload_key'] !== null && $column['payload_key'] !== $column['system_field_name']) {
                    $mapped[$column['payload_key']] = $value;
                }
                if ($column['excel_column_name'] !== '' && $column['excel_column_name'] !== $column['system_field_name'] && $column['excel_column_name'] !== $column['payload_key']) {
                    $mapped[$column['excel_column_name']] = $value;
                }
            }
            if (implode('', array_map(static fn($value): string => trim((string) $value), $mapped)) === '') {
                continue;
            }
            $mapped['_row_no'] = $rowIndex + 2;
            $mapped['_raw_payload'] = $rawPayload;
            $rows[] = $mapped;
        }

        return $rows;
    }

    private function sheetColumnFromFormatColumn(array $column): ?string
    {
        $index = (int) ($column['excel_column_index'] ?? $column['column_order'] ?? 0);
        if ($index < 1) {
            return null;
        }

        return Coordinate::stringFromColumnIndex($index);
    }

    private function uploadSheetColumnForFormatColumn(
        array $column,
        array $headerRow,
        array $headerColumnsByName,
        ?array &$usedHeaderColumns = null
    ): ?string {
        $excelName = trim((string) ($column['excel_column_name'] ?? ''));
        if ($excelName === '') {
            return null;
        }

        $configuredColumn = $this->sheetColumnFromFormatColumn($column);
        $expectedHeader = self::normalizeUploadHeaderName($excelName);
        if ($configuredColumn !== null) {
            $actualHeader = self::normalizeUploadHeaderName((string) ($headerRow[$configuredColumn] ?? ''));
            if ($actualHeader !== '' && $actualHeader === $expectedHeader) {
                if (is_array($usedHeaderColumns)) {
                    $usedHeaderColumns[$configuredColumn] = true;
                }
                return $configuredColumn;
            }
        }

        foreach ($headerColumnsByName[$expectedHeader] ?? [] as $sheetColumn) {
            if (is_array($usedHeaderColumns) && isset($usedHeaderColumns[$sheetColumn])) {
                continue;
            }
            if (is_array($usedHeaderColumns)) {
                $usedHeaderColumns[$sheetColumn] = true;
            }
            return $sheetColumn;
        }

        if (($headerColumnsByName[$expectedHeader] ?? []) !== []) {
            return (string) $headerColumnsByName[$expectedHeader][0];
        }

        return null;
    }

    private function uploadHeaderColumnsByName(array $headerRow): array
    {
        $columns = [];
        foreach ($headerRow as $sheetColumn => $header) {
            $name = self::normalizeUploadHeaderName((string) $header);
            if ($name === '') {
                continue;
            }
            $columns[$name][] = (string) $sheetColumn;
        }

        return $columns;
    }

    private static function normalizeUploadHeaderName(string $header): string
    {
        $header = preg_replace('/\s*\*$/u', '', trim($header)) ?? trim($header);
        return preg_replace('/\s+/u', '', $header) ?? $header;
    }

    private function payloadKeyFromExcelColumn(string $excelColumnName, int $columnIndex): ?string
    {
        $name = trim($excelColumnName);
        if ($name === '') {
            return $columnIndex > 0 ? 'column_' . $columnIndex : null;
        }
        $cleanName = preg_replace('/\s+/u', '', $name) ?? $name;
        if (in_array($cleanName, ['거래일자', '거래일', '승인일자', '승인일', '입금일자', '입금일', '출금일자', '출금일', '사용일자', '사용일', '매입일자', '매입일', '표준일자'], true)) {
            return 'transaction_date';
        }
        if ($cleanName === '입출구분') {
            return 'bank_direction';
        }

        $standardAliases = [
            '거래일시' => 'transaction_datetime',
            '거래시간' => 'transaction_time',
            '사업구분' => 'business_unit',
            '거래유형' => 'transaction_type',
            '현금영수증거래구분' => 'cash_receipt_transaction_type',
            '카드거래구분' => 'card_transaction_type',
            '매입일시' => 'evidence_date',
            '사용자명' => 'user_name',
            '가맹점사업자번호' => 'merchant_business_number',
            '가맹점명' => 'merchant_company_name',
            '업종코드' => 'merchant_industry_code',
            '업종' => 'merchant_business_category',
            '업태' => 'merchant_business_type',
            '봉사료' => 'service_amount',
            '거래금액(원화)' => 'supply_amount',
            '거래금액원화' => 'supply_amount',
            '매입금액(원화)' => 'purchase_amount_krw',
            '매입금액원화' => 'purchase_amount_krw',
            '청구금액' => 'billing_amount',
            '청구수수료' => 'fee_amount',
            '실청구금액' => 'actual_billing_amount',
            '거래금액(외화)' => 'foreign_amount',
            '거래금액외화' => 'foreign_amount',
            '현지금액' => 'local_amount',
            '외화거래일환율' => 'exchange_rate',
            '매입금액' => 'purchase_amount_krw',
            '발급수단' => 'issue_method',
            '공제여부' => 'deduction_status',
            '작성일자' => 'evidence_date',
            '승인번호' => 'source_key',
            '발급일자' => 'issue_date',
            '전송일자' => 'transmit_date',
            '전자세금계산서분류' => 'tax_invoice_category',
            '전자세금계산서종류' => 'tax_invoice_type',
            '발급유형' => 'issue_type',
            '영수청구구분' => 'receipt_claim_type',
            '영수/청구구분' => 'receipt_claim_type',
            '공급자사업자등록번호' => 'supplier_business_number',
            '공급자종사업장번호' => 'supplier_branch_number',
            '공급자상호' => 'supplier_company_name',
            '공급자대표자명' => 'supplier_ceo_name',
            '공급자주소' => 'supplier_address',
            '공급자이메일' => 'supplier_email',
            '공급받는자사업자등록번호' => 'customer_business_number',
            '공급받는자종사업장번호' => 'customer_branch_number',
            '공급받는자상호' => 'customer_company_name',
            '공급받는자대표자명' => 'customer_ceo_name',
            '공급받는자주소' => 'customer_address',
            '공급받는자이메일1' => 'customer_email_1',
            '공급받는자이메일2' => 'customer_email_2',
            '합계금액' => 'total_amount',
            '공급가액' => 'supply_amount',
            '세액' => 'vat_amount',
            '부가세' => 'vat_amount',
            '봉사료' => 'service_amount',
            '비고' => 'note',
            '품목일자' => 'item_date',
            '품목명' => 'item_name',
            '품목규격' => 'item_spec',
            '품목수량' => 'item_qty',
            '품목단가' => 'item_price',
            '품목공급가액' => 'item_supply_amount',
            '품목세액' => 'item_vat_amount',
            '품목비고' => 'item_note',
        ];
        if (isset($standardAliases[$cleanName])) {
            return $standardAliases[$cleanName];
        }

        $aliases = [
            '승인번호' => 'approval_number',
            '작성일자' => 'write_date',
            '거래일자' => 'transaction_date',
            '발급일자' => 'issue_date',
            '발행일자' => 'issue_date',
            '공급가액' => 'supply_amount',
            '공급가' => 'supply_amount',
            '세액' => 'vat_amount',
            '부가세' => 'vat_amount',
            '합계금액' => 'total_amount',
            '합계' => 'total_amount',
            '거래금액(원화)' => 'supply_amount',
            '거래금액원화' => 'supply_amount',
            '매입금액(원화)' => 'purchase_amount_krw',
            '매입금액원화' => 'purchase_amount_krw',
            '청구금액' => 'billing_amount',
            '청구수수료' => 'fee_amount',
            '실청구금액' => 'actual_billing_amount',
            '거래금액(외화)' => 'foreign_amount',
            '거래금액외화' => 'foreign_amount',
            '현지금액' => 'local_amount',
            '공급자' => 'supplier_name',
            '공급자명' => 'supplier_name',
            '거래처' => 'counterparty_name',
            '적요' => 'description',
            '입금액' => 'deposit_amount',
            '출금액' => 'withdraw_amount',
            '잔액' => 'balance_amount',
        ];
        $aliases += [
            '승인번호' => 'approval_number',
            '작성일자' => 'write_date',
            '작성일' => 'write_date',
            '거래일자' => 'transaction_date',
            '거래일' => 'transaction_date',
            '발급일자' => 'issue_date',
            '발급일' => 'issue_date',
            '발행일자' => 'issue_date',
            '발행일' => 'issue_date',
            '공급가액' => 'supply_amount',
            '공급가' => 'supply_amount',
            '세액' => 'vat_amount',
            '세금' => 'vat_amount',
            '부가세' => 'vat_amount',
            '합계금액' => 'total_amount',
            '합계' => 'total_amount',
            '공급자' => 'supplier_name',
            '공급자명' => 'supplier_name',
            '공급자 상호' => 'supplier_name',
            '공급받는자' => 'customer_name',
            '공급받는자명' => 'customer_name',
            '공급받는자 상호' => 'customer_name',
            '사업자등록번호' => 'business_number',
            '공급자 사업자등록번호' => 'supplier_business_number',
            '공급받는자 사업자등록번호' => 'customer_business_number',
            '거래처' => 'counterparty_name',
            '적요' => 'description',
            '비고' => 'note',
            '품목명' => 'item_name',
            '품목' => 'item_name',
            '규격' => 'item_spec',
            '수량' => 'item_qty',
            '단가' => 'item_price',
            '입금액' => 'deposit_amount',
            '출금액' => 'withdraw_amount',
            '잔액' => 'balance_amount',
        ];
        $aliases += [
            '가맹점' => 'client_company_name',
            '가맹점명' => 'client_company_name',
            '사용처' => 'client_company_name',
            '거래처명' => 'client_company_name',
            '거래처' => 'client_company_name',
        ];

        if (isset($aliases[$name])) {
            return $aliases[$name];
        }

        $key = preg_replace('/[^A-Za-z0-9_]+/', '_', $name) ?? '';
        $key = trim(strtolower($key), '_');

        return $key !== '' ? $key : ($columnIndex > 0 ? 'column_' . $columnIndex : null);
    }

    private function loadUploadedSpreadsheet(array $file): Spreadsheet
    {
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $mime = strtolower((string) ($file['type'] ?? ''));
        $isCsv = $extension === 'csv' || str_contains($mime, 'csv');

        if (!$isCsv) {
            $reader = IOFactory::createReaderForFile($tmpName);
            if (method_exists($reader, 'setReadDataOnly')) {
                $reader->setReadDataOnly(true);
            }
            if (method_exists($reader, 'setPreCalculateFormulas')) {
                $reader->setPreCalculateFormulas(false);
            }
            return $reader->load($tmpName);
        }

        $reader = IOFactory::createReader('Csv');
        if (method_exists($reader, 'setInputEncoding')) {
            $reader->setInputEncoding($this->detectCsvEncoding($tmpName));
        }
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        return $reader->load($tmpName);
    }

    private function loadUploadedSpreadsheetHeaderOnly(array $file): Spreadsheet
    {
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $mime = strtolower((string) ($file['type'] ?? ''));
        $isCsv = $extension === 'csv' || str_contains($mime, 'csv');
        $readFilter = new class implements IReadFilter {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row === 1;
            }
        };

        if (!$isCsv) {
            $reader = IOFactory::createReaderForFile($tmpName);
            if (method_exists($reader, 'setReadDataOnly')) {
                $reader->setReadDataOnly(true);
            }
            if (method_exists($reader, 'setPreCalculateFormulas')) {
                $reader->setPreCalculateFormulas(false);
            }
            if (method_exists($reader, 'setReadFilter')) {
                $reader->setReadFilter($readFilter);
            }

            return $reader->load($tmpName);
        }

        $reader = IOFactory::createReader('Csv');
        if (method_exists($reader, 'setInputEncoding')) {
            $reader->setInputEncoding($this->detectCsvEncoding($tmpName));
        }
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        if (method_exists($reader, 'setReadFilter')) {
            $reader->setReadFilter($readFilter);
        }

        return $reader->load($tmpName);
    }

    private function detectCsvEncoding(string $path): string
    {
        $sample = file_get_contents($path, false, null, 0, 8192);
        if ($sample === false || $sample === '') {
            return 'UTF-8';
        }

        if (str_starts_with($sample, "\xEF\xBB\xBF")) {
            return 'UTF-8';
        }
        if (function_exists('mb_check_encoding') && mb_check_encoding($sample, 'UTF-8')) {
            return 'UTF-8';
        }

        foreach (['CP949', 'EUC-KR'] as $encoding) {
            if (function_exists('mb_check_encoding') && mb_check_encoding($sample, $encoding)) {
                return $encoding;
            }
        }

        return 'CP949';
    }

    private function enrichUploadRows(array $rows, string $dataType): array
    {
        foreach ($rows as &$row) {
            $this->normalizeUploadAmountFields($row);
            $context = $this->resolveUploadTransactionContext($row, $dataType);
            foreach ($context as $key => $value) {
                if ($key === 'transaction_type' && trim((string) ($row['transaction_type'] ?? '')) === '') {
                    continue;
                }
                if ($value !== null && $value !== '') {
                    $row[$key] = $value;
                }
            }
        }
        unset($row);

        return $rows;
    }

    private function normalizeUploadAmountFields(array &$row): void
    {
        foreach ([
            'supply_amount',
            'vat_amount',
            'total_amount',
            'item_supply_amount',
            'item_vat_amount',
            'item_price',
            'item_qty',
            'service_amount',
            'purchase_amount_krw',
            'previous_notice_amount',
            'billing_amount',
            'fee_amount',
            'actual_billing_amount',
            'foreign_amount',
            'local_amount',
            'exchange_rate',
        ] as $field) {
            if (!array_key_exists($field, $row)) {
                continue;
            }

            $amount = $this->amountOrNull($row[$field]);
            if ($amount !== null) {
                $row[$field] = $amount;
            }
        }

        $supply = $this->amountOrNull($row['supply_amount'] ?? null);
        $vat = $this->amountOrNull($row['vat_amount'] ?? null);
        $total = $this->amountOrNull($row['total_amount'] ?? null);
        $itemSupply = $this->amountOrNull($row['item_supply_amount'] ?? null);
        $itemVat = $this->amountOrNull($row['item_vat_amount'] ?? null);
        $service = $this->amountOrNull($row['service_amount'] ?? null);

        if ($supply === null && $itemSupply !== null) {
            $row['supply_amount'] = $itemSupply;
            $supply = $itemSupply;
        }
        if ($vat === null && $itemVat !== null) {
            $row['vat_amount'] = $itemVat;
            $vat = $itemVat;
        }
        if ($total === null && ($supply !== null || $vat !== null || $service !== null)) {
            $row['total_amount'] = (float) ($supply ?? 0) + (float) ($vat ?? 0) + (float) ($service ?? 0);
        }
        if ($total === null) {
            foreach (['actual_billing_amount', 'billing_amount', 'purchase_amount_krw', 'foreign_amount', 'local_amount'] as $field) {
                $amount = $this->amountOrNull($row[$field] ?? null);
                if ($amount !== null) {
                    $row['total_amount'] = $amount;
                    break;
                }
            }
        }
    }

    private function resolveUploadTransactionContext(array $row, string $dataType): array
    {
        $dataType = self::normalizeDataType($dataType);
        if ($dataType === 'BANK_TRANSACTION') {
            $bankRow = $this->normalizeBankTransactionPayload($row);
            $direction = $this->normalizeTransactionDirection((string) ($bankRow['transaction_direction'] ?? ''));
            if ($direction === '') {
                $direction = $this->transactionDirectionForStorage('', $bankRow, $dataType);
            }

            return [
                'transaction_direction' => $direction,
                'transaction_type' => $this->transactionTypeForUpload($dataType),
                'client_business_number' => '',
                'client_company_name' => $this->bankCounterpartyName($bankRow),
                'own_business_number' => $this->ownCompanyDefaultParty()['business_number'] ?? '',
                'own_company_name' => $this->ownCompanyDefaultParty()['company_name'] ?? '',
                '_direction_error' => null,
            ];
        }

        if (in_array($dataType, ['CARD_STATEMENT', 'CARD_APPROVAL', 'CARD_HOMETAX'], true)) {
            $merchantName = $this->cleanCompanyName((string) (
                $row['merchant_company_name']
                ?? $row['merchant_name']
                ?? $row['client_company_name']
                ?? $row['company_name']
                ?? ''
            ));

            return [
                'transaction_direction' => $this->normalizeTransactionDirection((string) ($row['transaction_direction'] ?? 'PURCHASE')) ?: 'PURCHASE',
                'transaction_type' => $this->transactionTypeForUpload($dataType),
                'client_business_number' => $this->normalizeBusinessNumber((string) ($row['merchant_business_number'] ?? $row['client_business_number'] ?? $row['business_number'] ?? '')),
                'client_company_name' => $merchantName,
                'own_business_number' => $this->ownCompanyDefaultParty()['business_number'] ?? '',
                'own_company_name' => $this->ownCompanyDefaultParty()['company_name'] ?? '',
                '_direction_error' => null,
            ];
        }

        $supplier = $this->partyFromRow($row, 'supplier');
        $customer = $this->partyFromRow($row, 'customer', 'recipient');
        $legacyClient = [
            'business_number' => $this->normalizeBusinessNumber((string) ($row['client_business_number'] ?? $row['business_number'] ?? '')),
            'company_name' => $this->cleanCompanyName((string) ($row['client_company_name'] ?? $row['company_name'] ?? '')),
        ];

        $supplierIsOwn = $this->isOwnCompanyParty($supplier);
        $customerIsOwn = $this->isOwnCompanyParty($customer);
        $direction = $this->normalizeTransactionDirection((string) ($row['transaction_direction'] ?? ''));
        $error = null;

        if ($customerIsOwn && !$supplierIsOwn) {
            $direction = 'PURCHASE';
            $client = $supplier;
            $own = $customer;
        } elseif ($supplierIsOwn && !$customerIsOwn) {
            $direction = 'SALES';
            $client = $customer;
            $own = $supplier;
        } else {
            if ($direction === '') {
                $direction = match ($dataType) {
                'CARD_STATEMENT', 'CARD_APPROVAL', 'CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE' => 'PURCHASE',
                    'CASH_RECEIPT_SALES' => 'SALES',
                    default => '',
                };
            }
            $client = $legacyClient;
            $own = $this->ownCompanyDefaultParty();

            if ($direction === 'PURCHASE' && $supplier['company_name'] . $supplier['business_number'] !== '') {
                $client = $supplier;
            } elseif ($direction === 'SALES' && $customer['company_name'] . $customer['business_number'] !== '') {
                $client = $customer;
            } elseif ($legacyClient['company_name'] . $legacyClient['business_number'] === '') {
                $client = $direction === 'SALES' ? $customer : $supplier;
            }

            if (($dataType === 'TAX_INVOICE' || self::isManualTaxInvoiceDataType($dataType))
                && ($supplier['company_name'] . $supplier['business_number'] !== '' || $customer['company_name'] . $customer['business_number'] !== '')
                && !$supplierIsOwn
                && !$customerIsOwn
            ) {
                $error = '우리회사 식별 실패: 공급자/공급받는자 중 우리회사와 일치하는 값이 없습니다.';
            }
        }

        if ($direction === '') {
            $direction = $dataType === 'BANK_TRANSACTION' ? 'BANK' : 'GENERAL';
        }

        // 개인/미기재 상대방은 Seed 검토 단계에서 보완할 수 있게 거래처 필수 오류로 막지 않는다.
        if (false && $error === null
            && in_array($direction, ['PURCHASE', 'SALES'], true)
            && (($client['company_name'] ?? '') . ($client['business_number'] ?? '')) === ''
        ) {
            $error = '상대 거래처 식별 실패: 거래처로 사용할 공급자/공급받는자 값이 없습니다.';
        }

        return [
            'transaction_direction' => $direction,
            'transaction_type' => $this->transactionTypeForUpload($dataType),
            'client_business_number' => $client['business_number'] ?? '',
            'client_company_name' => $client['company_name'] ?? '',
            'own_business_number' => $own['business_number'] ?? '',
            'own_company_name' => $own['company_name'] ?? '',
            '_direction_error' => $error,
        ];
    }

    private function transactionTypeForUpload(string $dataType): string
    {
        return match (self::normalizeDataType($dataType)) {
            'IMPORT_INVOICE' => 'TRADE_IMPORT',
            'SHOPPING_ORDER' => 'SHOPPING',
            'TAX_INVOICE', 'CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES', 'CARD_STATEMENT', 'CARD_APPROVAL', 'BANK_TRANSACTION', 'ETC' => 'GENERAL',
            default => 'GENERAL',
        };
    }

    private function normalizeBankTransactionPayload(array $row): array
    {
        foreach ($row as $key => $value) {
            if (!str_starts_with((string) $key, '_') && !is_scalar($value) && $value !== null) {
                $row[$key] = $this->payloadScalarForStorage($value, str_ends_with((string) $key, '_id'));
            }
        }
        $row['withdraw_amount'] = $row['withdraw_amount'] ?? $row['withdrawal_amount'] ?? null;
        $deposit = $this->amountOrNull($row['deposit_amount'] ?? null);
        $withdraw = $this->amountOrNull($row['withdraw_amount'] ?? null);
        $amount = $this->amountOrNull($row['total_amount'] ?? $row['amount'] ?? null);

        if (($deposit === null || $deposit == 0.0) && ($withdraw === null || $withdraw == 0.0) && $amount !== null) {
            $direction = $this->normalizeTransactionDirection((string) ($row['transaction_direction'] ?? $row['bank_direction'] ?? ''));
            if ($direction === '') {
                $legacyTypeDirection = $this->normalizeTransactionDirection((string) ($row['transaction_type'] ?? ''));
                if (in_array($legacyTypeDirection, ['IN', 'OUT'], true)) {
                    $direction = $legacyTypeDirection;
                }
            }
            if ($direction === 'OUT') {
                $withdraw = abs($amount);
            } else {
                $deposit = abs($amount);
            }
        }

        if ($deposit !== null) {
            $row['deposit_amount'] = $deposit;
        }
        if ($withdraw !== null) {
            $row['withdraw_amount'] = $withdraw;
            $row['withdrawal_amount'] = $withdraw;
        }
        if (!isset($row['total_amount']) || $this->amountOrNull($row['total_amount']) === null) {
            $row['total_amount'] = (float) ($deposit && $deposit != 0.0 ? $deposit : ($withdraw ?? 0));
        }

        if (empty($row['transaction_direction'])) {
            if ($withdraw !== null && $withdraw > 0) {
                $row['transaction_direction'] = 'OUT';
            } elseif ($deposit !== null && $deposit > 0) {
                $row['transaction_direction'] = 'IN';
            }
        }

        $timeValue = trim((string) ($row['transaction_time'] ?? ''));
        if ($timeValue !== '' && preg_match('/\d{4}[-\/.]\d{1,2}[-\/.]\d{1,2}|\d{1,2}[-\/.]\d{1,2}[-\/.]\d{2,4}/', $timeValue)) {
            $row['transaction_datetime'] = $row['transaction_datetime'] ?? $timeValue;
            if (empty($row['transaction_date'])) {
                $row['transaction_date'] = $this->dateValue($timeValue);
            }
            if (preg_match('/(\d{1,2}:\d{2}(?::\d{2})?)/', $timeValue, $match)) {
                $row['transaction_time'] = $match[1];
            } else {
                unset($row['transaction_time']);
            }
        }

        $dateTime = trim((string) ($row['transaction_datetime'] ?? $row['transaction_at'] ?? ''));
        if ($dateTime === '' && !empty($row['transaction_date']) && preg_match('/\d{1,2}:\d{2}/', (string) $row['transaction_date'])) {
            $dateTime = trim((string) $row['transaction_date']);
            $row['transaction_datetime'] = $row['transaction_datetime'] ?? $dateTime;
        }
        if ($dateTime !== '') {
            $row['transaction_date'] = $this->dateValue($dateTime) ?: ($row['transaction_date'] ?? null);
            if (empty($row['transaction_time']) && preg_match('/(\d{1,2}:\d{2}(?::\d{2})?)/', $dateTime, $match)) {
                $row['transaction_time'] = $match[1];
            }
        }

        $counterpartyName = $this->bankCounterpartyName($row);
        if ($counterpartyName !== '') {
            $row['counterparty_name'] = $counterpartyName;
            $row['client_company_name'] = $counterpartyName;
        } elseif (!empty($row['client_company_name']) && $this->looksLikeBankAccountNumber((string) $row['client_company_name'])) {
            $row['counterparty_account_number'] = $row['counterparty_account_number'] ?? $row['client_company_name'];
            unset($row['client_company_name']);
        }

        foreach (['counterparty_account_number', 'counterparty_account_no', 'account_number', 'client_business_number'] as $key) {
            if (!empty($row[$key]) && $this->looksLikeBankAccountNumber((string) $row[$key])) {
                $row['counterparty_account_number'] = $row['counterparty_account_number'] ?? $row[$key];
                if ($key === 'client_business_number') {
                    unset($row['client_business_number']);
                }
                break;
            }
        }
        foreach (['counterparty_bank_name', 'counterparty_bank', 'bank_name'] as $key) {
            if (!empty($row[$key])) {
                $row['counterparty_bank_name'] = $row['counterparty_bank_name'] ?? $row[$key];
                break;
            }
        }

        return $row;
    }

    private function canonicalSystemFieldForFormatColumn(string $dataType, string $excelColumnName, string $systemField): string
    {
        $dataType = self::normalizeDataType($dataType);
        $cleanExcelName = preg_replace('/\s+/u', '', trim($excelColumnName)) ?? trim($excelColumnName);
        if ($dataType === 'BANK_TRANSACTION' && $cleanExcelName === '거래일시' && $systemField === 'transaction_time') {
            return 'transaction_datetime';
        }
        if ($dataType === 'BANK_TRANSACTION'
            && in_array($cleanExcelName, ['거래구분', '입출구분'], true)
            && in_array($systemField, ['transaction_type', 'card_transaction_type'], true)
        ) {
            return 'bank_direction';
        }
        if (in_array($dataType, ['CARD_HOMETAX', 'CARD_STATEMENT', 'CARD_APPROVAL', 'CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES'], true)
            && $cleanExcelName === '가맹점명'
            && $systemField === 'client_id'
        ) {
            return 'merchant_company_name';
        }
        if ($dataType === 'CARD_HOMETAX'
            && in_array($cleanExcelName, ['카드사', '카드사명', '카드회사'], true)
            && in_array($systemField, ['client_id', 'client_name', 'card_name', 'card_company_name'], true)
        ) {
            return 'source_card_company_name';
        }

        return $systemField;
    }

    private function bankCounterpartyName(array $row): string
    {
        foreach (['counterparty_name', 'counterparty_account_holder_name', 'counterparty_account_holder', 'account_holder', 'client_company_name', 'client_name'] as $key) {
            $value = $this->cleanCompanyName((string) ($row[$key] ?? ''));
            if ($value !== '' && !$this->looksLikeBankAccountNumber($value)) {
                return $value;
            }
        }

        return '';
    }

    private function partyFromRow(array $row, string $prefix, ?string $fallbackPrefix = null): array
    {
        $businessNumber = (string) ($row[$prefix . '_business_number'] ?? '');
        $companyName = (string) ($row[$prefix . '_company_name'] ?? '');
        if ($fallbackPrefix !== null) {
            $businessNumber = $businessNumber !== '' ? $businessNumber : (string) ($row[$fallbackPrefix . '_business_number'] ?? '');
            $companyName = $companyName !== '' ? $companyName : (string) ($row[$fallbackPrefix . '_company_name'] ?? '');
        }

        return [
            'business_number' => $this->normalizeBusinessNumber($businessNumber),
            'company_name' => $this->cleanCompanyName($companyName),
        ];
    }

    private function ownCompanyDefaultParty(): array
    {
        $profile = $this->ownCompanyProfile();
        return [
            'business_number' => $profile['business_numbers'][0] ?? '',
            'company_name' => $profile['company_names'][0] ?? '',
        ];
    }

    private function isOwnCompanyParty(array $party): bool
    {
        $profile = $this->ownCompanyProfile();
        $businessNumber = $this->normalizeBusinessNumber((string) ($party['business_number'] ?? ''));
        if ($businessNumber !== '' && in_array($businessNumber, $profile['business_numbers'], true)) {
            return true;
        }

        $companyName = $this->normalizeCompanyNameForCompare((string) ($party['company_name'] ?? ''));
        return $companyName !== '' && in_array($companyName, $profile['company_names'], true);
    }

    private function ownCompanyProfile(): array
    {
        if ($this->ownCompanyProfile !== null) {
            return $this->ownCompanyProfile;
        }

        $company = (new CompanyModel($this->pdo))->getOne() ?? [];
        $businessNumbers = [];
        foreach (['biz_number', 'business_no', 'business_number'] as $key) {
            $value = $this->normalizeBusinessNumber((string) ($company[$key] ?? ''));
            if ($value !== '') {
                $businessNumbers[] = $value;
            }
        }

        $companyNames = [];
        foreach (['company_name_ko', 'company_name_en', 'company_name'] as $key) {
            $value = $this->normalizeCompanyNameForCompare((string) ($company[$key] ?? ''));
            if ($value !== '') {
                $companyNames[] = $value;
            }
        }

        $this->ownCompanyProfile = [
            'business_numbers' => array_values(array_unique($businessNumbers)),
            'company_names' => array_values(array_unique($companyNames)),
        ];

        return $this->ownCompanyProfile;
    }

    private function normalizeTransactionDirection(string $direction): string
    {
        $direction = strtoupper(trim($direction));
        return match ($direction) {
            'PURCHASE', 'BUY', '매입' => 'PURCHASE',
            'SALES', 'SALE', 'SELL', '매출' => 'SALES',
            'IN', 'DEPOSIT', 'RECEIPT', '입금' => 'IN',
            'OUT', 'WITHDRAWAL', 'PAYMENT', '출금' => 'OUT',
            default => '',
        };
    }

    private function normalizeCompanyNameForCompare(string $companyName): string
    {
        $companyName = $this->cleanCompanyName($companyName);
        $companyName = preg_replace('/\s+/u', '', $companyName) ?? $companyName;
        return function_exists('mb_strtolower') ? mb_strtolower($companyName, 'UTF-8') : strtolower($companyName);
    }

    private function validatePreviewRows(array $rows, array $columns, string $dataType = ''): array
    {
        return $this->validatePreviewRowsV2($rows, $columns, $dataType);
    }

    private function isValidDateValue(mixed $value): bool
    {
        $value = trim((string) $value);
        if ($value === '') {
            return false;
        }
        if (is_numeric($value)) {
            return (float) $value > 0;
        }

        return strtotime($value) !== false;
    }

    private function isValidAmountValue(mixed $value): bool
    {
        $amount = $this->amountOrNull($value);
        return $amount !== null;
    }

    private function validatePreviewRowsV2(array $rows, array $columns, string $dataType = ''): array
    {
        $dataType = self::normalizeDataType($dataType);

        foreach ($rows as &$row) {
            $errors = [];
            $warnings = [];
            $requiredMissingMessages = $this->requiredFormatMissingMessages($row, $columns);
            array_push($warnings, ...$requiredMissingMessages);

            $date = trim((string) ($row['transaction_date'] ?? ''));
            if ($date !== '' && !$this->isValidDateValue($date)) {
                $errors[] = '날짜 형식 오류';
            }

            foreach ([
                'supply_amount',
                'vat_amount',
                'total_amount',
                'item_supply_amount',
                'item_vat_amount',
                'service_amount',
                'purchase_amount_krw',
                'previous_notice_amount',
                'billing_amount',
                'fee_amount',
                'actual_billing_amount',
                'foreign_amount',
                'local_amount',
                'exchange_rate',
            ] as $field) {
                $value = trim((string) ($row[$field] ?? ''));
                if ($value !== '' && !$this->isValidAmountValue($value)) {
                    $errors[] = self::fieldLabel($field) . ' 금액 오류';
                }
            }

            if (!empty($row['_direction_error'])) {
                $errors[] = (string) $row['_direction_error'];
            }

            $businessNumber = $this->normalizeBusinessNumber((string) ($row['client_business_number'] ?? $row['business_number'] ?? ''));
            $companyName = $this->cleanCompanyName((string) ($row['client_company_name'] ?? $row['company_name'] ?? ''));
            if ($businessNumber !== '' && !$this->clientExistsByBusinessNumber($businessNumber)) {
                $warnings[] = '거래처 신규 생성 예정';
            } elseif ($businessNumber === '' && $companyName !== '' && $this->findClientId($companyName) === null) {
                $warnings[] = '거래처 미매칭';
            } elseif ($businessNumber === '' && $companyName === '' && $dataType !== 'CASH_RECEIPT_SALES') {
                $warnings[] = '거래처 상호 미기재';
            }

            $status = 'ok';
            if ($errors !== []) {
                $status = 'error';
            } elseif ($warnings !== []) {
                $status = 'warning';
            }

            $row['_validation'] = [
                'status' => $status,
                'label' => ['ok' => '정상', 'warning' => '경고', 'error' => '오류'][$status],
                'messages' => array_values(array_merge($errors, $warnings)),
                'required_missing_count' => count($requiredMissingMessages),
                'required_missing_messages' => array_values($requiredMissingMessages),
            ];
        }
        unset($row);

        return $rows;
    }

    private function requiredFormatMissingMessages(array $payload, array $columns): array
    {
        $messages = [];
        foreach ($columns as $column) {
            if (!self::isRequiredFormatColumn($column)) {
                continue;
            }

            $field = trim((string) ($column['system_field_name'] ?? ''));
            $excelName = trim((string) ($column['excel_column_name'] ?? ''));
            $label = $excelName !== '' ? $excelName : self::fieldLabel($field);
            $value = $field !== '' ? ($payload[$field] ?? null) : ($payload[$excelName] ?? null);
            if ($this->isBlankRequiredValue($value)) {
                $messages[] = (preg_replace('/\s*\*$/u', '', $label) ?? $label) . ' 필수값 없음';
            }
        }

        return array_values(array_unique($messages));
    }

    private function businessProjectRuleMessages(array $payload): array
    {
        $businessUnit = trim((string) $this->payloadScalarForStorage($payload['business_unit'] ?? $payload['business_unit_code'] ?? ''));
        $businessUnitKey = strtoupper((string) preg_replace('/\s+/u', '', $businessUnit));
        $projectId = trim((string) $this->payloadScalarForStorage($payload['project_id'] ?? ''));
        $projectName = trim((string) $this->payloadScalarForStorage($payload['project_name'] ?? ''));
        $hasProject = $projectId !== '' || $projectName !== '';

        if ($businessUnitKey === '') {
            return [];
        }

        $isHeadOffice = str_contains($businessUnitKey, 'HQ') || str_contains($businessUnitKey, '본사');
        $isConstruction = str_contains($businessUnitKey, 'CONSTRUCTION') || str_contains($businessUnitKey, '전문건설');

        if ($isHeadOffice && $hasProject) {
            return ['사업구분이 본사일 때는 프로젝트명을 선택할 수 없습니다.'];
        }
        if ($isConstruction && !$hasProject) {
            return ['사업구분이 전문건설업일 때는 프로젝트명을 선택해야 합니다.'];
        }

        return [];
    }

    private function isBlankRequiredValue(mixed $value): bool
    {
        if (is_array($value)) {
            return $value === [];
        }

        $text = trim((string) ($value ?? ''));
        return $text === '' || $this->isEmptySelectionLabel($text);
    }

    private function assertNoUploadValidationErrors(array $rows): void
    {
        foreach ($rows as $row) {
            $validation = is_array($row['_validation'] ?? null) ? $row['_validation'] : [];
            if (($validation['status'] ?? '') !== 'error') {
                continue;
            }

            $rowNo = (int) ($row['_row_no'] ?? 0);
            $prefix = $rowNo > 0 ? "{$rowNo}행 " : '';
            $messages = array_values(array_filter(array_map('strval', is_array($validation['messages'] ?? null) ? $validation['messages'] : [])));
            $message = $messages !== [] ? implode(', ', array_slice($messages, 0, 5)) : '필수 항목이 누락되었습니다.';
            throw new \RuntimeException($prefix . $message);
        }
    }

    private function createTransactionFromPayload(array $row, string $dataType): array
    {
        $payload = $this->buildTransactionCreatePayload($row, $dataType);
        $result = $this->transactionService()->save($payload);
        if (!empty($result['success']) || !empty($payload['_header_only_retry'])) {
            return $result;
        }

        $message = (string) ($result['message'] ?? '');
        if (!$this->shouldRetryTransactionHeaderOnly($message, $payload)) {
            return $result;
        }

        $payload['_header_only_retry'] = true;
        $payload['_original_items'] = $payload['items'] ?? [];
        $payload['items'] = [];
        $retry = $this->transactionService()->save($payload);
        if (!empty($retry['success']) && !empty($retry['id'])) {
            $retry['fallback_transaction_created'] = true;
            $retry['message'] = trim(($retry['message'] ?? '') . ' / 거래내역 보완 필요');
            $retry['original_error'] = $message;
        }

        return $retry;
    }

    private function createVoucherFromBankPayload(string $evidenceId, array $row, string $transactionId, bool $linkExistingVoucher = false): ?string
    {
        if (self::normalizeDataType((string) ($row['import_type'] ?? $row['source_type'] ?? 'BANK_TRANSACTION')) !== 'BANK_TRANSACTION') {
            return null;
        }
        if (!$this->hasVoucherLinesPayload($row)) {
            return null;
        }

        $actor = ActorHelper::user();
        try {
            $existingVoucher = $this->existingVoucherForBankPayload($evidenceId, $row);
            if ($existingVoucher) {
                $voucherId = (string) ($existingVoucher['id'] ?? '');
                if ($voucherId !== '' && $linkExistingVoucher) {
                    $this->tagCreatedVoucher($voucherId, $evidenceId, $transactionId, $actor);
                    $this->linkVoucherToEvidence($evidenceId, $voucherId, $transactionId, $actor);
                    if ($transactionId !== '') {
                        $this->linkVoucherToTransaction($voucherId, $transactionId, null, 'AUTO', $actor);
                    }
                    $this->updateEvidenceVoucherStatus($evidenceId, 'CREATED', $actor);
                    return $voucherId;
                }

                $this->updateEvidenceVoucherStatus($evidenceId, 'ERROR', $actor, '이미 같은 유형의 전표가 생성되어 있습니다.');
                return null;
            }

            $lines = $this->bankVoucherLinesForSave($row['_voucher_lines'] ?? []);
            if ($lines === []) {
            $this->updateEvidenceVoucherStatus($evidenceId, 'ERROR', $actor, '분개라인이 비어 있습니다.');
                return null;
            }

            $lines = $this->applyEvidenceRefsToVoucherLines($lines, $row);
            $payments = $this->bankVoucherPaymentsForSave($row);
            $result = $this->voucherService()->save([
                'voucher_date' => $this->dateValue($row['voucher_date'] ?? $row['transaction_date'] ?? date('Y-m-d')),
                'summary_text' => trim((string) ($row['voucher_summary_text'] ?? $row['summary_text'] ?? $row['description'] ?? '')),
                'note' => trim((string) ($row['note'] ?? '')) ?: null,
                'memo' => trim((string) ($row['voucher_memo'] ?? $row['memo'] ?? '')) ?: null,
                'linked_transaction_id' => $transactionId,
                'source_type' => 'BANK',
                'lines' => $lines,
                'payments' => $payments,
            ]);

            $voucherId = (string) ($result['voucher_id'] ?? $result['id'] ?? '');
            if ($voucherId !== '') {
                $this->tagCreatedVoucher($voucherId, $evidenceId, $transactionId, $actor);
                $this->linkVoucherToEvidence($evidenceId, $voucherId, $transactionId, $actor);
                $this->updateEvidenceVoucherStatus($evidenceId, 'CREATED', $actor);
                if ($transactionId !== '') {
                    $this->recordBankVoucherLearning($transactionId, $voucherId, $row, $lines, $actor);
                }
            }

            if ($voucherId === '') {
                $this->updateEvidenceVoucherStatus($evidenceId, 'ERROR', $actor, (string) ($result['message'] ?? '전표 생성에 실패했습니다.'));
                return null;
            }

            return $voucherId;
        } catch (\Throwable $e) {
            $this->updateEvidenceVoucherStatus($evidenceId, 'ERROR', $actor, $e->getMessage());
            return null;
        }
    }

    private function bankVoucherLinesForSave(mixed $rawLines): array
    {
        if (!is_array($rawLines)) {
            return [];
        }

        $journalBySourceNo = [];
        $refsBySourceNo = [];
        $errors = [];
        foreach ($rawLines as $rawLine) {
            if (!is_array($rawLine)) {
                continue;
            }
            if (!$this->bankVoucherRawLineHasMeaningfulValue($rawLine)) {
                continue;
            }

            $rowType = $this->normalizeBankVoucherLineRowType($rawLine['line_row_type'] ?? null);
            $sourceLineNo = (int) ($rawLine['line_no'] ?? 0);
            if ($sourceLineNo <= 0) {
                $errors[] = $rowType === 'AUX' ? '보조계정 라인번호 누락' : '분개라인번호 누락';
                continue;
            }

            if ($rowType === 'AUX') {
                $ref = $this->bankVoucherLineRefForSave($rawLine);
                if ($ref !== null) {
                    $refsBySourceNo[$sourceLineNo][] = $ref;
                }
                continue;
            }

            if (array_key_exists($sourceLineNo, $journalBySourceNo)) {
                $errors[] = '분개라인번호 중복: ' . $sourceLineNo;
                continue;
            }

            $account = $this->normalizeAccountInput((string) ($rawLine['account_id'] ?? ''));
            $debit = $this->amountOrNull($rawLine['debit'] ?? null);
            $credit = $this->amountOrNull($rawLine['credit'] ?? null);
            if ($account === '' && ($debit === null || $debit == 0.0) && ($credit === null || $credit == 0.0)) {
                continue;
            }

            $journalBySourceNo[$sourceLineNo] = [
                'account_id' => $account,
                'debit' => $debit ?? 0,
                'credit' => $credit ?? 0,
                'line_summary' => trim((string) ($rawLine['line_summary'] ?? '')) ?: null,
                'refs' => [],
                'recommend_source' => trim((string) ($rawLine['recommend_source'] ?? $rawLine['source'] ?? '')) ?: null,
                'recommend_confidence' => $rawLine['recommend_confidence'] ?? $rawLine['confidence'] ?? null,
                'journal_rule_id' => trim((string) ($rawLine['journal_rule_id'] ?? '')) ?: null,
                'recommend_reason' => trim((string) ($rawLine['recommend_reason'] ?? $rawLine['reason'] ?? '')) ?: null,
                'recommended_account_id' => trim((string) ($rawLine['recommended_account_id'] ?? $account)) ?: $account,
                'recommended_refs' => is_array($rawLine['recommended_refs'] ?? null) ? $rawLine['recommended_refs'] : [],
                'is_user_modified' => !empty($rawLine['is_user_modified']) ? 1 : 0,
            ];
            $ref = $this->bankVoucherLineRefForSave($rawLine);
            if ($ref !== null) {
                $journalBySourceNo[$sourceLineNo]['refs'][] = $ref;
            }
        }

        foreach (array_keys($refsBySourceNo) as $sourceLineNo) {
            if (!array_key_exists($sourceLineNo, $journalBySourceNo)) {
                $errors[] = '보조계정이 존재하지 않는 분개라인번호를 참조합니다: ' . $sourceLineNo;
            }
        }

        if ($errors !== []) {
            throw new \RuntimeException(implode(', ', array_values(array_unique($errors))));
        }

        ksort($journalBySourceNo, SORT_NUMERIC);
        $lines = [];
        foreach ($journalBySourceNo as $sourceLineNo => $line) {
            foreach ($refsBySourceNo[$sourceLineNo] ?? [] as $ref) {
                $line['refs'][] = $ref;
            }
            $lines[] = $line;
        }
        return $lines;
    }

    private function recordBankVoucherLearning(string $transactionId, string $voucherId, array $evidence, array $lines, string $actor): void
    {
        if ($transactionId === '' || $voucherId === '' || $lines === []) {
            return;
        }

        try {
            [$direction] = $this->bankVoucherPaymentDirectionAndAmount($evidence);
            $context = [
                'id' => $transactionId,
                'client_id' => $this->businessRefIdForStorage('CLIENT', $evidence) ?? '',
                'project_id' => $this->businessRefIdForStorage('PROJECT', $evidence) ?? '',
                'business_unit' => $this->businessUnitForUpload($evidence, 'BANK_TRANSACTION'),
                'transaction_type' => strtoupper(trim((string) ($evidence['transaction_type'] ?? 'GENERAL'))) ?: 'GENERAL',
                'transaction_direction' => $direction ?: $this->transactionDirectionForStorage((string) ($evidence['transaction_direction'] ?? ''), $evidence, 'BANK_TRANSACTION'),
                'import_type' => 'BANK_TRANSACTION',
            ];

            $learningLines = $this->bankVoucherLearningLines($lines, $evidence);
            if ($learningLines === []) {
                return;
            }

            $this->journalLearningService()->recordVoucherDraft($context, $voucherId, $learningLines, $actor);
        } catch (\Throwable $e) {
            error_log('[ImportController] bank voucher learning skipped: ' . $e->getMessage());
        }
    }

    private function bankVoucherLearningLines(array $lines, array $evidence): array
    {
        $learningLines = [];
        $description = trim((string) ($evidence['description'] ?? $evidence['summary_text'] ?? ''));
        $sourceType = self::normalizeDataType((string) ($evidence['source_type'] ?? $evidence['import_type'] ?? 'BANK_TRANSACTION'));

        foreach ($lines as $line) {
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);
            $amount = $debit > 0 ? $debit : $credit;
            if ($amount <= 0) {
                continue;
            }

            $finalAccountId = $this->resolveLedgerAccountId((string) ($line['account_id'] ?? '')) ?? (string) ($line['account_id'] ?? '');
            $recommendedAccountId = $this->resolveLedgerAccountId((string) ($line['recommended_account_id'] ?? '')) ?? $finalAccountId;
            $finalRefs = is_array($line['refs'] ?? null) ? $line['refs'] : [];
            $recommendedRefs = is_array($line['recommended_refs'] ?? null) ? $line['recommended_refs'] : [];
            $refsChanged = json_encode($this->normalizedRefPayload($recommendedRefs)) !== json_encode($this->normalizedRefPayload($finalRefs));

            $learningLines[] = [
                'line_type' => $debit > 0 ? 'DEBIT' : 'CREDIT',
                'account_id' => $finalAccountId,
                'amount' => $amount,
                'recommended_line_type' => $debit > 0 ? 'DEBIT' : 'CREDIT',
                'recommended_account_id' => $recommendedAccountId,
                'recommended_amount' => $amount,
                'source' => $line['recommend_source'] ?? null,
                'confidence' => is_numeric($line['recommend_confidence'] ?? null) ? (int) $line['recommend_confidence'] : null,
                'journal_rule_id' => $line['journal_rule_id'] ?? null,
                'reason' => $line['recommend_reason'] ?? null,
                'project_id' => $this->businessRefIdForStorage('PROJECT', $evidence) ?? null,
                'recommended_refs' => $recommendedRefs,
                'final_refs' => $finalRefs,
                'source_type' => $sourceType,
                'description' => $description,
                'amount_bucket' => $this->amountBucket($amount),
                'is_user_modified' => !empty($line['is_user_modified']) || $refsChanged ? 1 : 0,
            ];
        }

        return $learningLines;
    }

    private function normalizedRefPayload(array $refs): array
    {
        $normalized = [];
        foreach ($refs as $ref) {
            if (!is_array($ref)) {
                continue;
            }
            $type = $this->normalizeVoucherRefType((string) ($ref['ref_type'] ?? $ref['line_ref_type'] ?? ''));
            $id = trim((string) ($ref['ref_id'] ?? $ref['line_ref_id'] ?? ''));
            if ($type === '' || $id === '') {
                continue;
            }
            $normalized[] = $type . ':' . $id;
        }
        sort($normalized, SORT_STRING);

        return $normalized;
    }

    private function amountBucket(float $amount): string
    {
        $amount = abs($amount);
        return match (true) {
            $amount < 10000 => 'LT_10K',
            $amount < 100000 => '10K_100K',
            $amount < 1000000 => '100K_1M',
            $amount < 10000000 => '1M_10M',
            $amount < 100000000 => '10M_100M',
            default => 'GE_100M',
        };
    }

    private function bankVoucherRawLineHasMeaningfulValue(array $line): bool
    {
        foreach (['line_no', 'line_row_type', 'account_id', 'debit', 'credit', 'line_summary', 'line_ref_type', 'line_ref_id'] as $key) {
            if (trim((string) ($line[$key] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function bankVoucherLineRefForSave(array $line): ?array
    {
        $refType = $this->normalizeVoucherRefType((string) ($line['line_ref_type'] ?? ''));
        $refId = trim((string) ($line['line_ref_id'] ?? ''));
        if ($refType === '' || $refId === '') {
            return null;
        }

        return [
            'ref_type' => $refType,
            'ref_id' => $this->resolveVoucherRefId($refType, $refId) ?? $refId,
        ];
    }

    private function templateColumnsInFormatOrder(array $columns, string $dataType = ''): array
    {
        $columns = $this->formatColumnsInOrder($columns);

        return array_values(array_filter(
            $columns,
            fn(array $column): bool => trim((string) ($column['excel_column_name'] ?? '')) !== ''
                && !$this->isHiddenFormatColumn($column, $dataType)
        ));
    }

    private function excelHeadersForColumns(array $columns): array
    {
        return array_map(
            static fn(array $column): string => trim((string) ($column['excel_column_name'] ?? '')),
            $columns
        );
    }

    private function formatColumnsInOrder(array $columns): array
    {
        foreach ($columns as $index => &$column) {
            if (!is_array($column)) {
                $column = [];
            }
            $column['_original_order_index'] = $index;
        }
        unset($column);

        usort($columns, static function (array $a, array $b): int {
            $aOrder = (int) ($a['column_order'] ?? 0);
            $bOrder = (int) ($b['column_order'] ?? 0);
            $aExcel = (int) ($a['excel_column_index'] ?? 0);
            $bExcel = (int) ($b['excel_column_index'] ?? 0);
            $aPrimary = $aOrder > 0 ? $aOrder : ($aExcel > 0 ? $aExcel : ((int) ($a['_original_order_index'] ?? 0) + 1));
            $bPrimary = $bOrder > 0 ? $bOrder : ($bExcel > 0 ? $bExcel : ((int) ($b['_original_order_index'] ?? 0) + 1));

            return [$aPrimary, $aExcel, (int) ($a['_original_order_index'] ?? 0)]
                <=> [$bPrimary, $bExcel, (int) ($b['_original_order_index'] ?? 0)];
        });

        return array_map(static function (array $column): array {
            unset($column['_original_order_index']);
            return $column;
        }, $columns);
    }

    private function isHiddenFormatColumn(array $column, string $dataType = ''): bool
    {
        $field = trim((string) ($column['system_field_name'] ?? ''));
        $excelName = preg_replace('/\s+/u', '', trim((string) ($column['excel_column_name'] ?? ''))) ?? '';
        $deprecatedExcelNames = [
            '전표일자',
            '전표적요',
            '전표비고',
            '전표메모',
            '헤더순번',
            '헤더행번호',
            '분개라인번호',
            '계정',
            '차변금액',
            '차변',
            '대변금액',
            '대변',
            '라인적요',
        ];
        $allowDeprecatedField = self::normalizeDataType($dataType) === 'CARD_HOMETAX' && $field === 'note';
        if (!$allowDeprecatedField && in_array($field, self::FORMAT_DEPRECATED_SYSTEM_FIELDS, true)) {
            return true;
        }
        if (in_array($excelName, $deprecatedExcelNames, true)) {
            return true;
        }
        $deprecatedExcelNames = [
            '전표일자',
            '전표적요',
            '전표비고',
            '전표메모',
            '헤더순번',
            '헤더행번호',
            '분개라인번호',
            '계정',
            '차변금액',
            '차변',
            '대변금액',
            '대변',
            '라인적요',
        ];

        return $field === 'line_row_type' || $excelName === '행타입' || $excelName === 'line_row_type';
    }

    private function applyEvidenceRefsToVoucherLines(array $lines, array $evidence): array
    {
        foreach ($lines as &$line) {
            $accountId = $this->resolveLedgerAccountId((string) ($line['account_id'] ?? ''));
            if ($accountId === null) {
                continue;
            }

            $policies = $this->voucherRefPoliciesForAccount($accountId);
            if ($policies === []) {
                continue;
            }

            $refs = is_array($line['refs'] ?? null) ? $line['refs'] : [];
            $existingTypes = [];
            foreach ($refs as $ref) {
                $type = $this->normalizeVoucherRefType((string) ($ref['ref_type'] ?? ''));
                if ($type !== '') {
                    $existingTypes[$type] = true;
                }
            }

            foreach (array_keys($policies) as $refType) {
                $refType = $this->normalizeVoucherRefType($refType);
                if ($refType === '' || isset($existingTypes[$refType])) {
                    continue;
                }

                $refId = $this->evidenceRefIdForType($refType, $evidence);
                if ($refId === null || $refId === '') {
                    continue;
                }

                $refs[] = [
                    'ref_type' => $refType,
                    'ref_id' => $refId,
                    'is_primary' => 0,
                ];
                $existingTypes[$refType] = true;
            }

            $line['refs'] = $refs;
        }
        unset($line);

        return $lines;
    }

    private function missingRequiredEvidenceRefsMessage(array $lines, array $evidence): ?string
    {
        $missing = [];
        foreach ($lines as $index => $line) {
            $accountId = $this->resolveLedgerAccountId((string) ($line['account_id'] ?? ''));
            if ($accountId === null) {
                continue;
            }

            $policies = $this->voucherRefPoliciesForAccount($accountId);
            foreach ($policies as $refType => $isRequired) {
                if (!$isRequired) {
                    continue;
                }
                if ($this->lineHasRefType($line, $refType)) {
                    continue;
                }
                if ($this->evidenceRefIdForType($refType, $evidence) !== null) {
                    continue;
                }
                $missing[] = ($index + 1) . '번 라인 ' . $this->voucherRefTypeLabel($refType);
            }
        }

        return $missing === [] ? null : 'Evidence 업무 기준정보 부족: ' . implode(', ', array_values(array_unique($missing)));
    }

    private function lineHasRefType(array $line, string $refType): bool
    {
        $target = $this->normalizeVoucherRefType($refType);
        foreach ((array) ($line['refs'] ?? []) as $ref) {
            if ($this->normalizeVoucherRefType((string) ($ref['ref_type'] ?? '')) === $target
                && trim((string) ($ref['ref_id'] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function evidenceRefIdForType(string $refType, array $evidence): ?string
    {
        return $this->businessRefIdForStorage($this->normalizeVoucherRefType($refType), $evidence);
    }

    private function voucherRefPoliciesForAccount(string $accountId): array
    {
        if ($accountId === '') {
            return [];
        }

        $policies = [];

        if ($this->tableExists('ledger_sub_accounts')) {
            $hasRefType = $this->tableColumnExists('ledger_sub_accounts', 'ref_type');
            $hasSubCode = $this->tableColumnExists('ledger_sub_accounts', 'sub_code');
            if ($hasRefType || $hasSubCode) {
                $select = [
                    $hasRefType ? 'ref_type' : "'' AS ref_type",
                    $hasSubCode ? 'sub_code' : "'' AS sub_code",
                    $this->tableColumnExists('ledger_sub_accounts', 'is_required') ? 'is_required' : '0 AS is_required',
                ];
                $deleted = $this->tableColumnExists('ledger_sub_accounts', 'deleted_at') ? ' AND deleted_at IS NULL' : '';
                $stmt = $this->pdo->prepare("
                    SELECT " . implode(', ', $select) . "
                    FROM ledger_sub_accounts
                    WHERE account_id = :account_id
                      {$deleted}
                ");
                $stmt->execute([':account_id' => $accountId]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $type = $this->policyRefTypeFromRow($row);
                    if ($type === '') {
                        continue;
                    }
                    $policies[$type] = !empty($policies[$type]) || (int) ($row['is_required'] ?? 0) === 1;
                }
            }
        }

        if ($this->tableExists('ledger_account_sub_policies')) {
            $deleted = $this->tableColumnExists('ledger_account_sub_policies', 'deleted_at') ? ' AND deleted_at IS NULL' : '';
            $stmt = $this->pdo->prepare("
                SELECT sub_account_type, custom_group_code, is_required
                FROM ledger_account_sub_policies
                WHERE account_id = :account_id
                  {$deleted}
            ");
            $stmt->execute([':account_id' => $accountId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $type = $this->policyRefTypeFromSubPolicy($row);
                if ($type === '') {
                    continue;
                }
                $policies[$type] = !empty($policies[$type]) || (int) ($row['is_required'] ?? 0) === 1;
            }
        }

        return $policies;
    }

    private function policyRefTypeFromRow(array $row): string
    {
        $refType = $this->normalizeVoucherRefType((string) ($row['ref_type'] ?? ''));
        $subCode = $this->normalizeVoucherRefType((string) ($row['sub_code'] ?? ''));
        if ($refType === 'REF_TARGET') {
            return $subCode;
        }

        return $refType !== '' ? $refType : $subCode;
    }

    private function policyRefTypeFromSubPolicy(array $row): string
    {
        $type = strtolower(trim((string) ($row['sub_account_type'] ?? '')));
        return match ($type) {
            'partner', 'client', 'customer', 'vendor', 'counterparty' => 'CLIENT',
            'project' => 'PROJECT',
            'employee', 'staff', 'user' => 'EMPLOYEE',
            'account', 'bank', 'bank_account' => 'ACCOUNT',
            'card' => 'CARD',
            'custom' => $this->normalizeVoucherRefType((string) ($row['custom_group_code'] ?? '')),
            default => $this->normalizeVoucherRefType($type),
        };
    }

    private function resolveLedgerAccountId(string $accountValue): ?string
    {
        $accountValue = $this->normalizeAccountInput($accountValue);
        if ($accountValue === '' || !$this->tableExists('ledger_accounts')) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT id
            FROM ledger_accounts
            WHERE deleted_at IS NULL
              AND (id = :account_id_value OR account_code = :account_code_value)
            LIMIT 1
        ");
        $stmt->execute([
            ':account_id_value' => $accountValue,
            ':account_code_value' => $accountValue,
        ]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (string) $id : null;
    }

    private function voucherRefTypeLabel(string $refType): string
    {
        return match ($this->normalizeVoucherRefType($refType)) {
            'CLIENT' => '거래처',
            'PROJECT' => '프로젝트',
            'EMPLOYEE' => '직원',
            'ACCOUNT' => '계좌',
            'CARD' => '카드',
            default => $refType,
        };
    }

    private function bankVoucherPaymentsForSave(array $row): array
    {
        if (is_array($row['_voucher_payments'] ?? null)) {
            $payments = [];
            foreach ($row['_voucher_payments'] as $payment) {
                if (!is_array($payment)) {
                    continue;
                }
                $type = strtoupper(trim((string) ($payment['payment_type'] ?? 'ACCOUNT')));
                $direction = strtoupper(trim((string) ($payment['payment_direction'] ?? $payment['direction'] ?? 'OUT')));
                $amount = $this->amountOrNull($payment['amount'] ?? null);
                $paymentId = trim((string) ($payment['payment_id'] ?? ''));
                if ($type === '' && $paymentId === '' && ($amount === null || $amount <= 0)) {
                    continue;
                }
                if (!in_array($direction, ['IN', 'OUT'], true)) {
                    $direction = 'OUT';
                }
                if (!in_array($type, ['ACCOUNT', 'CARD'], true)) {
                    $type = 'ACCOUNT';
                }
                if ($type === 'ACCOUNT') {
                    $paymentId = $this->resolveBankAccountId($paymentId) ?? $paymentId;
                }
                if ($paymentId === '' || $amount === null || $amount <= 0) {
                    continue;
                }
                $payments[] = [
                    'payment_direction' => $direction,
                    'payment_type' => $type,
                    'payment_id' => $paymentId,
                    'amount' => abs($amount),
                ];
            }
            if ($payments !== []) {
                return $payments;
            }
        }

        $bankAccountId = $this->businessRefIdForStorage('ACCOUNT', $row);
        if ($bankAccountId === null) {
            throw new \RuntimeException('원본 결제계좌가 ERP 은행계좌와 매칭되지 않았습니다. 생성센터에서 ERP 계좌를 선택해 주세요.');
            throw new \RuntimeException('결제계좌를 은행계좌로 해석할 수 없습니다.');
        }

        [$direction, $amount] = $this->bankVoucherPaymentDirectionAndAmount($row);
        if ($direction === null || $amount === null || $amount <= 0) {
            throw new \RuntimeException('결제금액을 해석할 수 없습니다.');
        }

        return [[
            'payment_direction' => $direction,
            'payment_type' => 'ACCOUNT',
            'payment_id' => $bankAccountId,
            'amount' => $amount,
        ]];
    }

    private function bankVoucherPaymentDirectionAndAmount(array $row): array
    {
        $withdraw = $this->amountOrNull($row['withdraw_amount'] ?? null);
        $deposit = $this->amountOrNull($row['deposit_amount'] ?? null);
        if ($withdraw !== null && abs($withdraw) > 0) {
            return ['OUT', abs($withdraw)];
        }
        if ($deposit !== null && abs($deposit) > 0) {
            return ['IN', abs($deposit)];
        }

        return [null, null];
    }

    private function resolveBankAccountId(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '' || !$this->tableExists('system_bank_accounts')) {
            return null;
        }
        $cacheKey = 'ACCOUNT|' . $value;
        if (array_key_exists($cacheKey, $this->bankAccountIdCache)) {
            return $this->bankAccountIdCache[$cacheKey];
        }

        $where = [];
        $params = [];
        foreach (['id', 'account_name', 'account_number', 'bank_name', 'account_holder'] as $column) {
            if ($this->tableColumnExists('system_bank_accounts', $column)) {
                $param = ':bank_account_' . $column;
                $where[] = $column . ' = ' . $param;
                $params[$param] = $value;
            }
        }

        $normalized = preg_replace('/[\s-]+/u', '', $value) ?? $value;
        $digits = preg_replace('/\D+/u', '', $value) ?? '';
        if ($this->tableColumnExists('system_bank_accounts', 'account_number')) {
            if ($normalized !== '') {
                $where[] = "REPLACE(REPLACE(account_number, '-', ''), ' ', '') = :normalized_account_number";
                $params[':normalized_account_number'] = $normalized;
            }
            if ($digits !== '' && $digits !== $normalized) {
                $where[] = "REPLACE(REPLACE(account_number, '-', ''), ' ', '') = :account_number_digits";
                $params[':account_number_digits'] = $digits;
            }
        }

        if ($where === []) {
            return null;
        }

        $deleted = $this->tableColumnExists('system_bank_accounts', 'deleted_at') ? ' AND deleted_at IS NULL' : '';
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM system_bank_accounts
            WHERE (" . implode(' OR ', $where) . ")
              {$deleted}
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmt->execute($params);
        $id = $stmt->fetchColumn();

        $resolved = $id !== false ? (string) $id : null;
        $this->bankAccountIdCache[$cacheKey] = $resolved;

        return $resolved;
    }

    private function normalizeVoucherRefType(string $value): string
    {
        $rawValue = trim($value);
        $knownKorean = [
            '거래처' => 'CLIENT',
            '프로젝트' => 'PROJECT',
            '계좌' => 'ACCOUNT',
            '은행계좌' => 'ACCOUNT',
            '카드' => 'CARD',
            '직원' => 'EMPLOYEE',
            '사원' => 'EMPLOYEE',
        ];
        if (isset($knownKorean[$rawValue])) {
            return $knownKorean[$rawValue];
        }

        $value = strtoupper($rawValue);
        return match ($value) {
            '거래처', 'CLIENT', 'CUSTOMER', 'VENDOR', 'COUNTERPARTY' => 'CLIENT',
            '프로젝트', 'PROJECT' => 'PROJECT',
            '계좌', '은행계좌', 'ACCOUNT', 'BANK', 'BANK_ACCOUNT' => 'ACCOUNT',
            '카드', 'CARD' => 'CARD',
            '직원', '사원', 'EMPLOYEE', 'USER' => 'EMPLOYEE',
            default => $value,
        };
    }

    private function normalizeAccountInput(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^([0-9A-Za-z._-]+)\s+.+$/u', $value, $matches) === 1) {
            return $matches[1];
        }

        return $value;
    }

    private function resolveVoucherRefId(string $refType, string $value): ?string
    {
        $refType = $this->normalizeVoucherRefType($refType);
        $value = trim($value);
        if ($value === '' || $this->isUuid($value)) {
            return $value !== '' ? $value : null;
        }
        $cacheKey = $refType . '|' . $value;
        if (array_key_exists($cacheKey, $this->voucherRefIdCache)) {
            return $this->voucherRefIdCache[$cacheKey];
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
            $this->voucherRefIdCache[$cacheKey] = null;
            return null;
        }

        $columns = $this->refLookupColumns($table);
        if ($columns === []) {
            $this->voucherRefIdCache[$cacheKey] = null;
            return null;
        }

        $where = [];
        $params = [];
        foreach ($columns as $column) {
            if ($this->tableColumnExists($table, $column)) {
                $param = ':ref_' . $column;
                $where[] = $column . ' = ' . $param;
                $params[$param] = $value;
            }
        }
        if ($where === []) {
            $this->voucherRefIdCache[$cacheKey] = null;
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

        $resolved = $id !== false ? (string) $id : null;
        $this->voucherRefIdCache[$cacheKey] = $resolved;

        return $resolved;
    }

    private function refLookupColumns(string $table): array
    {
        return match ($table) {
            'system_clients' => ['id', 'client_name', 'company_name', 'business_number'],
            'system_projects' => ['id', 'project_name', 'project_code'],
            'system_bank_accounts' => ['id', 'account_name', 'account_number', 'bank_name'],
            'system_cards' => ['id', 'card_name', 'card_number'],
            'user_employees' => ['id', 'employee_name', 'name'],
            default => ['id'],
        };
    }

    private function tagCreatedVoucher(string $voucherId, string $evidenceId, string $transactionId, string $actor): void
    {
        $sets = [];
        $params = [':id' => $voucherId, ':actor' => $actor];
        if ($this->tableColumnExists('ledger_vouchers', 'source_type')) {
            $sets[] = 'source_type = :source_type';
            $params[':source_type'] = 'BANK';
        }
        if ($this->tableColumnExists('ledger_vouchers', 'source_id')) {
            $sets[] = 'source_id = :source_id';
            $params[':source_id'] = $evidenceId;
        }
        if ($this->tableColumnExists('ledger_vouchers', 'import_type')) {
            $sets[] = 'import_type = :import_type';
            $params[':import_type'] = 'BANK_TRANSACTION';
        }
        if ($transactionId !== '' && $this->tableColumnExists('ledger_vouchers', 'transaction_id')) {
            $sets[] = 'transaction_id = :transaction_id';
            $params[':transaction_id'] = $transactionId;
        }
        if ($this->tableColumnExists('ledger_vouchers', 'updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }
        if ($this->tableColumnExists('ledger_vouchers', 'updated_by')) {
            $sets[] = 'updated_by = :actor';
        }
        if ($sets === []) {
            return;
        }

        $this->pdo->prepare('UPDATE ledger_vouchers SET ' . implode(', ', $sets) . ' WHERE id = :id')
            ->execute($params);
    }

    private function existingVoucherForEvidenceId(string $evidenceId): ?array
    {
        if ($evidenceId === '' || !$this->tableExists('ledger_vouchers') || !$this->tableColumnExists('ledger_vouchers', 'source_id')) {
            return null;
        }

        $where = ['deleted_at IS NULL', 'source_id = :evidence_id'];
        $params = [':evidence_id' => $evidenceId];
        if ($this->tableColumnExists('ledger_vouchers', 'import_type')) {
            $where[] = "(import_type IS NULL OR import_type = '' OR import_type = 'BANK_TRANSACTION')";
        }
        if ($this->tableColumnExists('ledger_vouchers', 'source_type')) {
            $where[] = "(source_type IS NULL OR source_type = '' OR source_type = 'BANK')";
        }

        $selects = ['id', 'voucher_no', 'voucher_date', 'source_id'];
        $selects[] = $this->tableColumnExists('ledger_vouchers', 'source_type') ? 'source_type' : 'NULL AS source_type';
        $selects[] = $this->tableColumnExists('ledger_vouchers', 'import_type') ? 'import_type' : 'NULL AS import_type';
        $selects[] = $this->tableColumnExists('ledger_vouchers', 'transaction_id') ? 'transaction_id' : 'NULL AS transaction_id';

        $stmt = $this->pdo->prepare("
            SELECT " . implode(', ', $selects) . "
            FROM ledger_vouchers
            WHERE " . implode(' AND ', $where) . "
            ORDER BY created_at DESC, sort_no DESC
            LIMIT 1
        ");
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function existingVoucherForBankPayload(string $evidenceId, array $row): ?array
    {
        $existing = $this->existingVoucherForEvidenceId($evidenceId);
        if ($existing) {
            return $existing;
        }

        return $this->existingBankVoucherForPayloadFingerprint($row);
    }

    private function existingBankVoucherForPayloadFingerprint(array $row): ?array
    {
        if (!$this->tableExists('ledger_vouchers') || !$this->tableExists('ledger_voucher_lines')) {
            return null;
        }

        $dataType = self::normalizeDataType((string) ($row['import_type'] ?? $row['source_type'] ?? 'BANK_TRANSACTION'));
        if ($dataType !== 'BANK_TRANSACTION') {
            return null;
        }

        $voucherDate = $this->dateValue($row['voucher_date'] ?? $row['transaction_date'] ?? $row['evidence_date'] ?? '');
        [$direction, $paymentAmount] = $this->bankVoucherPaymentDirectionAndAmount($row);
        $amount = $paymentAmount ?? $this->amountOrNull($row['total_amount'] ?? $row['amount'] ?? null);
        if ($voucherDate === '' || $amount === null || abs((float) $amount) <= 0) {
            return null;
        }

        $sourceSelect = $this->tableColumnExists('ledger_vouchers', 'source_type') ? 'v.source_type,' : "NULL AS source_type,";
        $sourceIdSelect = $this->tableColumnExists('ledger_vouchers', 'source_id') ? 'v.source_id,' : "NULL AS source_id,";
        $importSelect = $this->tableColumnExists('ledger_vouchers', 'import_type') ? 'v.import_type,' : "NULL AS import_type,";
        $transactionSelect = $this->tableColumnExists('ledger_vouchers', 'transaction_id') ? 'v.transaction_id,' : "NULL AS transaction_id,";
        $lineDeletedFilter = $this->tableColumnExists('ledger_voucher_lines', 'deleted_at') ? 'AND l.deleted_at IS NULL' : '';
        $groupBy = ['v.id', 'v.voucher_no', 'v.voucher_date', 'v.summary_text', 'v.created_at', 'v.sort_no'];
        foreach (['source_type', 'source_id', 'import_type', 'transaction_id'] as $column) {
            if ($this->tableColumnExists('ledger_vouchers', $column)) {
                $groupBy[] = 'v.' . $column;
            }
        }

        $stmt = $this->pdo->prepare("
            SELECT
                v.id,
                v.voucher_no,
                v.voucher_date,
                {$sourceSelect}
                {$sourceIdSelect}
                {$importSelect}
                {$transactionSelect}
                COALESCE(v.summary_text, '') AS summary_text,
                COALESCE(SUM(l.debit), 0) AS debit_total,
                COALESCE(SUM(l.credit), 0) AS credit_total
            FROM ledger_vouchers v
            INNER JOIN ledger_voucher_lines l
                ON l.voucher_id = v.id
                {$lineDeletedFilter}
            WHERE v.deleted_at IS NULL
              AND v.voucher_date = :voucher_date
            GROUP BY " . implode(', ', $groupBy) . "
            HAVING ABS(GREATEST(debit_total, credit_total) - :amount) < 0.01
            ORDER BY v.created_at DESC, v.sort_no DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':voucher_date' => $voucherDate,
            ':amount' => abs((float) $amount),
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function existingVoucherRowsForEvidenceIds(array $evidenceIds): array
    {
        $evidenceIds = array_values(array_filter(array_unique(array_map('strval', $evidenceIds))));
        if ($evidenceIds === [] || !$this->tableExists('ledger_vouchers')) {
            return [];
        }

        $rowsByEvidenceId = [];
        foreach ($this->evidenceRowsForExistingVoucherCheck($evidenceIds) as $evidenceRow) {
            $evidenceId = (string) ($evidenceRow['id'] ?? '');
            $mapped = json_decode((string) ($evidenceRow['mapped_payload_json'] ?? ''), true);
            $mapped = is_array($mapped) ? $this->normalizeEvidenceMappedPayloadForResponse($mapped) : [];
            $mapped['source_type'] = $evidenceRow['source_type'] ?? '';
            $mapped['import_type'] = $evidenceRow['source_type'] ?? '';
            $mapped['evidence_date'] = $evidenceRow['evidence_date'] ?? '';
            $this->mergeEvidenceBusinessInfoIntoPayload($evidenceRow, $mapped);
            $existing = $this->existingVoucherForBankPayload($evidenceId, $mapped);
            if (!$existing) {
                continue;
            }
            $rowsByEvidenceId[$evidenceId] = [
                'evidence_id' => $evidenceId,
                'evidence_source_type' => $evidenceRow['source_type'] ?? null,
                'evidence_transaction_id' => $evidenceRow['transaction_id'] ?? null,
                'voucher_id' => $existing['id'] ?? null,
                'voucher_no' => $existing['voucher_no'] ?? null,
                'voucher_date' => $existing['voucher_date'] ?? null,
                'source_type' => $existing['source_type'] ?? null,
                'import_type' => $existing['import_type'] ?? null,
                'voucher_transaction_id' => $existing['transaction_id'] ?? null,
                'summary_text' => $existing['summary_text'] ?? null,
            ];
        }

        if (!$this->tableColumnExists('ledger_vouchers', 'source_id')) {
            return array_values($rowsByEvidenceId);
        }

        [$inSql, $params] = $this->placeholdersForIds($evidenceIds, 'existing_voucher_evidence');
        $transactionSelect = $this->evidenceHasTransactionIdColumn() ? 'e.transaction_id AS evidence_transaction_id,' : "NULL AS evidence_transaction_id,";
        $sourceTypeSelect = $this->tableColumnExists('ledger_vouchers', 'source_type') ? 'v.source_type,' : "NULL AS source_type,";
        $importSelect = $this->tableColumnExists('ledger_vouchers', 'import_type') ? 'v.import_type,' : "NULL AS import_type,";
        $voucherTransactionSelect = $this->tableColumnExists('ledger_vouchers', 'transaction_id') ? 'v.transaction_id AS voucher_transaction_id,' : "NULL AS voucher_transaction_id,";
        $where = ["v.deleted_at IS NULL", "v.source_id IN ({$inSql})"];
        if ($this->tableColumnExists('ledger_vouchers', 'import_type')) {
            $where[] = "(v.import_type IS NULL OR v.import_type = '' OR v.import_type = 'BANK_TRANSACTION')";
        }
        if ($this->tableColumnExists('ledger_vouchers', 'source_type')) {
            $where[] = "(v.source_type IS NULL OR v.source_type = '' OR v.source_type = 'BANK')";
        }

        $stmt = $this->pdo->prepare("
            SELECT
                e.id AS evidence_id,
                e.source_type AS evidence_source_type,
                {$transactionSelect}
                v.id AS voucher_id,
                v.voucher_no,
                v.voucher_date,
                {$sourceTypeSelect}
                {$importSelect}
                {$voucherTransactionSelect}
                v.summary_text
            FROM ledger_vouchers v
            INNER JOIN ledger_data_evidences e
                ON e.id = v.source_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY v.created_at DESC, v.sort_no DESC
        ");
        $stmt->execute($params);

        foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
            $rowsByEvidenceId[(string) ($row['evidence_id'] ?? '')] = $row;
        }

        return array_values($rowsByEvidenceId);
    }

    private function evidenceRowsForExistingVoucherCheck(array $evidenceIds): array
    {
        $evidenceIds = array_values(array_filter(array_unique(array_map('strval', $evidenceIds))));
        if ($evidenceIds === []) {
            return [];
        }

        [$inSql, $params] = $this->placeholdersForIds($evidenceIds, 'existing_voucher_check_evidence');
        $transactionSelect = $this->evidenceHasTransactionIdColumn() ? ', transaction_id' : ", NULL AS transaction_id";
        $stmt = $this->pdo->prepare("
            SELECT id, source_type, evidence_date,
                   client_id, project_id, employee_id, bank_account_id, card_id,
                   client_name, project_name, employee_name, bank_account_name, card_name,
                   mapped_payload_json
                   {$transactionSelect}
            FROM ledger_data_evidences
            WHERE id IN ({$inSql})
              AND deleted_at IS NULL
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function linkExistingVouchersForEvidenceRows(array $rows): int
    {
        $actor = ActorHelper::user();
        $count = 0;
        foreach ($rows as $row) {
            $voucherId = (string) ($row['voucher_id'] ?? '');
            $evidenceId = (string) ($row['evidence_id'] ?? '');
            $transactionId = (string) (($row['evidence_transaction_id'] ?? '') ?: ($row['voucher_transaction_id'] ?? ''));
            $isBankEvidence = self::normalizeDataType((string) ($row['evidence_source_type'] ?? '')) === 'BANK_TRANSACTION';
            if ($voucherId === '' || $evidenceId === '') {
                continue;
            }
            if (!$isBankEvidence && $transactionId === '') {
                continue;
            }
            $this->tagCreatedVoucher($voucherId, $evidenceId, $isBankEvidence ? '' : $transactionId, $actor);
            $this->linkVoucherToEvidence($evidenceId, $voucherId, $isBankEvidence ? '' : $transactionId, $actor);
            if (!$isBankEvidence && $transactionId !== '') {
                $this->linkVoucherToTransaction($voucherId, $transactionId, null, 'AUTO', $actor);
            }
            $this->updateEvidenceVoucherStatus($evidenceId, 'CREATED', $actor);
            $count++;
        }

        return $count;
    }

    private function linkVoucherToTransaction(string $voucherId, string $transactionId, mixed $matchAmount, string $linkType, string $actor): void
    {
        if ($voucherId === '' || $transactionId === '') {
            return;
        }

        (new TransactionLinkModel($this->pdo))->insertOrRestore($transactionId, $voucherId, $matchAmount, $linkType, $actor);
    }

    private function linkVoucherToEvidence(string $evidenceId, string $voucherId, string $transactionId, string $actor): void
    {
        if ($evidenceId === '' || $voucherId === '' || !$this->tableExists('ledger_data_evidence_links')) {
            return;
        }

        $existing = $this->pdo->prepare("
            SELECT id, deleted_at
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
                SET transaction_id = COALESCE(NULLIF(:transaction_id, ''), transaction_id),
                    link_type = 'AUTO',
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
                (:id, :sort_no, :evidence_id, :transaction_id, :voucher_id, 'AUTO', 0, 1, NOW(), :created_by, NOW(), :updated_by)
        ")->execute([
            ':id' => UuidHelper::generate(),
            ':sort_no' => SequenceHelper::next('ledger_data_evidence_links', 'sort_no'),
            ':evidence_id' => $evidenceId,
            ':transaction_id' => $transactionId !== '' ? $transactionId : null,
            ':voucher_id' => $voucherId,
            ':created_by' => $actor,
            ':updated_by' => $actor,
        ]);
    }

    private function shouldRetryTransactionHeaderOnly(string $message, array $payload): bool
    {
        if (empty($payload['items']) || !is_array($payload['items'])) {
            return false;
        }

        return str_contains($message, '거래 항목')
            || str_contains($message, '거래 라인')
            || str_contains($message, '거래 내역')
            || str_contains($message, 'line')
            || str_contains($message, 'item')
            || str_contains($message, 'ledger_transaction_lines');
    }

    private function buildTransactionCreatePayload(array $row, string $dataType): array
    {
        if (self::normalizeDataType($dataType) === 'BANK_TRANSACTION') {
            $row = $this->normalizeBankTransactionPayload($row);
        }
        $context = $this->resolveUploadTransactionContext($row, $dataType);
        if (!empty($context['_direction_error'])) {
            throw new \RuntimeException((string) $context['_direction_error']);
        }

        $supplyRaw = $this->amountOrNull($row['supply_amount'] ?? null);
        $vatRaw = $this->amountOrNull($row['vat_amount'] ?? null);
        $totalRaw = $this->amountOrNull($row['total_amount'] ?? null);
        $supply = (float) ($supplyRaw ?? $totalRaw ?? 0);
        $vat = (float) ($vatRaw ?? 0);
        $total = (float) ($totalRaw ?? ($supply + $vat));
        if (($supplyRaw === null || $supply == 0.0) && $totalRaw !== null && $total != 0.0) {
            $supply = $total - $vat;
        }
        $service = (float) ($this->amountOrNull($row['service_amount'] ?? null) ?? 0);
        $taxType = abs($vat) > 0 ? 'TAXABLE' : 'EXEMPT';
        $note = trim((string) ($row['note'] ?? ''));
        $items = $this->transactionLinePayloadsForUpload($row, $supply, $vat, $service, $taxType);

        $clientId = $this->businessRefIdForStorage('CLIENT', $row)
            ?? $this->existingClientIdByBusinessNumber((string) ($context['client_business_number'] ?? $row['client_business_number'] ?? $row['business_number'] ?? ''));

        return [
            'transaction_date' => $this->dateValue($row['transaction_date'] ?? date('Y-m-d')),
            'business_unit' => $this->businessUnitForUpload($row, $dataType),
            'transaction_type' => (string) ($context['transaction_type'] ?? 'GENERAL'),
            'transaction_direction' => $this->transactionDirectionForStorage((string) ($context['transaction_direction'] ?? ''), $row, $dataType),
            'import_type' => self::normalizeDataType($dataType),
            'client_id' => $clientId,
            'project_id' => $this->findProjectId((string) ($row['project_name'] ?? '')),
            'description' => trim((string) ($row['description'] ?? '')),
            'supply_amount' => $supply,
            'vat_amount' => $vat,
            'total_amount' => $total,
            'status' => 'draft',
            'match_status' => 'none',
            'note' => $note !== '' ? $note : null,
            'memo' => trim((string) ($row['memo'] ?? '')) ?: null,
            'items' => $items,
        ];
    }

    private function splitBankFormatColumns(array $columns, bool $ensureLineRowType = false): array
    {
        $lineFields = array_flip(self::BANK_VOUCHER_LINE_FIELDS);
        $headerColumns = [];
        $lineColumns = [];

        foreach ($columns as $column) {
            $field = (string) ($column['system_field_name'] ?? '');
            if (isset($lineFields[$field])) {
                $lineColumns[] = $column;
            } else {
                $headerColumns[] = $column;
            }
        }

        if ($lineColumns === []) {
            $lineColumns = [
                ['excel_column_name' => '헤더순번', 'system_field_name' => 'header_row_no', 'is_required' => 1],
                ['excel_column_name' => '계정', 'system_field_name' => 'account_id', 'is_required' => 1],
                ['excel_column_name' => '차변', 'system_field_name' => 'debit', 'is_required' => 0],
                ['excel_column_name' => '대변', 'system_field_name' => 'credit', 'is_required' => 0],
                ['excel_column_name' => '라인적요', 'system_field_name' => 'line_summary', 'is_required' => 0],
            ];
        } elseif ($ensureLineRowType && !$this->columnsContainSystemField($lineColumns, 'line_row_type')) {
            $insertAt = 1;
            foreach (array_values($lineColumns) as $index => $column) {
                if ((string) ($column['system_field_name'] ?? '') === 'header_row_no') {
                    $insertAt = $index + 1;
                    break;
                }
            }
            array_splice($lineColumns, $insertAt, 0, [[
                'excel_column_name' => '행타입',
                'system_field_name' => 'line_row_type',
                'is_required' => 1,
            ]]);
        }
        if (!$ensureLineRowType) {
            $lineColumns = array_values(array_filter(
                $lineColumns,
                static fn(array $column): bool => (string) ($column['system_field_name'] ?? '') !== 'line_row_type'
            ));
        }

        return [$headerColumns, $lineColumns];
    }

    private function columnsContainSystemField(array $columns, string $field): bool
    {
        foreach ($columns as $column) {
            if ((string) ($column['system_field_name'] ?? '') === $field) {
                return true;
            }
        }

        return false;
    }

    private function sampleBankVoucherLineRows(array $columns): array
    {
        $samples = [
            [
                'header_row_no' => 2,
                'line_no' => 1,
                'line_row_type' => '분개',
                'account_id' => '1000',
                'debit' => 55000,
                'credit' => '',
                'line_summary' => '은행 입금',
            ],
            [
                'header_row_no' => 2,
                'line_no' => 2,
                'line_row_type' => '분개',
                'account_id' => '4000',
                'debit' => '',
                'credit' => 55000,
                'line_summary' => '매출대금',
            ],
        ];

        return array_map(function (array $sample) use ($columns): array {
            $row = [];
            foreach ($columns as $column) {
                $field = (string) ($column['system_field_name'] ?? '');
                $row[] = $sample[$field] ?? '';
            }
            return $row;
        }, $samples);
    }

    private function transactionLinePayloadsForUpload(array $row, float $supply, float $vat, float $service, string $taxType): array
    {
        $autoCreate = trim((string) ($row['auto_create_lines_1set'] ?? '1')) !== '0';
        $itemDate = $this->dateValue($row['item_date'] ?? $row['transaction_date'] ?? date('Y-m-d'));
        $description = trim((string) ($row['item_note'] ?? $row['description'] ?? '')) ?: null;

        if (!$autoCreate) {
            $itemName = trim((string) ($row['item_name'] ?? ''));
            $lineAmount = $this->amountOrNull($row['amount'] ?? null);
            if ($itemName === '' && $lineAmount === null) {
                return [];
            }

            $amount = (float) ($lineAmount ?? $supply);
            return [[
                'line_type' => trim((string) ($row['line_type'] ?? 'ITEM')) ?: 'ITEM',
                'item_date' => $itemDate,
                'item_name' => $itemName !== '' ? $itemName : (trim((string) ($row['description'] ?? '')) ?: '거래내역 보완 필요'),
                'specification' => trim((string) ($row['item_spec'] ?? '')) ?: null,
                'unit_name' => trim((string) ($row['unit_name'] ?? '')) ?: null,
                'quantity' => (float) ($this->amountOrNull($row['item_qty'] ?? null) ?? 1),
                'unit_price' => (float) ($this->amountOrNull($row['item_price'] ?? null) ?? $amount),
                'amount' => $amount,
                'supply_amount' => $amount,
                'vat_amount' => 0.0,
                'total_amount' => $amount,
                'tax_type' => $taxType,
                'description' => $description,
            ]];
        }

        $itemName = trim((string) ($row['item_name'] ?? $row['description'] ?? '')) ?: '공급가액';
        $lines = [];
        if (abs($supply) > 0) {
            $lines[] = $this->oneSetTransactionLine('ITEM', $itemDate, $itemName, $supply, $taxType, $description);
        }
        if (abs($vat) > 0) {
            $lines[] = $this->oneSetTransactionLine('VAT', $itemDate, '부가세', $vat, $taxType, '부가세');
        }
        if (abs($service) > 0) {
            $lines[] = $this->oneSetTransactionLine('SERVICE', $itemDate, '봉사료', $service, $taxType, '봉사료');
        }

        return $lines;
    }

    private function oneSetTransactionLine(string $lineType, string $itemDate, string $itemName, float $amount, string $taxType, ?string $description): array
    {
        return [
            'line_type' => $lineType,
            'item_date' => $itemDate,
            'item_name' => $itemName,
            'specification' => null,
            'unit_name' => '식',
            'quantity' => 1,
            'unit_price' => $amount,
            'amount' => $amount,
            'supply_amount' => $lineType === 'ITEM' ? $amount : 0.0,
            'vat_amount' => $lineType === 'VAT' ? $amount : 0.0,
            'total_amount' => $amount,
            'tax_type' => $taxType,
            'description' => $description,
        ];
    }

    private function hasDuplicateTransaction(array $row, string $uploadRowId, string $dataType): bool
    {
        $approvalNo = trim((string) ($row['approval_number'] ?? $row['approval_no'] ?? ''));
        if ($approvalNo !== '') {
            $stmt = $this->pdo->prepare("
                SELECT 1
                FROM ledger_data_evidences
                WHERE id <> :row_id
                  AND " . $this->evidenceCreatedTransactionSql() . "
                  AND mapped_payload_json LIKE :approval_no
                LIMIT 1
            ");
            $stmt->execute([
                ':row_id' => $uploadRowId,
                ':approval_no' => '%' . $approvalNo . '%',
            ]);
            if ($stmt->fetchColumn()) {
                return true;
            }

            $stmt = $this->pdo->prepare("
                SELECT 1
                FROM ledger_transactions
                WHERE deleted_at IS NULL
                  AND (note LIKE :approval_note OR memo LIKE :approval_memo OR description LIKE :approval_description)
                LIMIT 1
            ");
            $approvalLike = '%' . $approvalNo . '%';
            $stmt->execute([
                ':approval_note' => $approvalLike,
                ':approval_memo' => $approvalLike,
                ':approval_description' => $approvalLike,
            ]);
            return (bool) $stmt->fetchColumn();
        }

        $rawDate = trim((string) ($row['transaction_date'] ?? ''));
        if ($rawDate === '') {
            return false;
        }
        $date = $this->dateValue($rawDate);
        $total = $this->number($row['total_amount'] ?? $row['supply_amount'] ?? 0);
        if ($date === '' || abs($total) <= 0) {
            return false;
        }

        $context = $this->resolveUploadTransactionContext($row, $dataType);
        $businessNumber = $this->normalizeBusinessNumber((string) ($context['client_business_number'] ?? $row['client_business_number'] ?? $row['business_number'] ?? ''));
        $companyName = $this->cleanCompanyName((string) ($context['client_company_name'] ?? $row['client_company_name'] ?? $row['company_name'] ?? ''));
        $clientId = null;
        if ($businessNumber !== '') {
            $stmt = $this->pdo->prepare("
                SELECT id
                FROM system_clients
                WHERE business_number = :business_number
                  AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([':business_number' => $businessNumber]);
            $clientId = $stmt->fetchColumn() ?: null;
        }
        if ($clientId === null && $companyName !== '') {
            $clientId = $this->findClientId($companyName);
        }

        $sql = "
            SELECT 1
            FROM ledger_transactions
            WHERE deleted_at IS NULL
              AND transaction_date = :transaction_date
              AND total_amount = :total_amount
        ";
        $params = [
            ':transaction_date' => $date,
            ':total_amount' => $total,
        ];
        if ($clientId !== null) {
            $sql .= ' AND client_id = :client_id';
            $params[':client_id'] = $clientId;
        } elseif ($companyName !== '') {
            $sql .= ' AND description LIKE :company_name';
            $params[':company_name'] = '%' . $companyName . '%';
        } else {
            return false;
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    private function businessUnitForUpload(array $row, string $dataType): string
    {
        $value = strtoupper(trim((string) ($row['business_unit'] ?? $row['business_unit_code'] ?? '')));
        if ($value !== '') {
            return $value;
        }

        return match (self::normalizeDataType($dataType)) {
            'SHOPPING_ORDER' => 'ECOMMERCE',
            default => 'HQ',
        };
    }

    private function transactionDirectionForStorage(string $direction, array $row, string $dataType): string
    {
        $direction = strtoupper(trim($direction));
        $dataType = self::normalizeDataType($dataType);

        if ($dataType === 'BANK_TRANSACTION') {
            $bankDirection = strtoupper(trim((string) (
                $row['bank_direction']
                ?? $row['deposit_withdrawal_type']
                ?? $row['transaction_direction']
                ?? ''
            )));
            if (in_array($bankDirection, ['IN', 'DEPOSIT', 'RECEIPT', '입금'], true)) {
                return 'IN';
            }
            if (in_array($bankDirection, ['OUT', 'WITHDRAWAL', 'PAYMENT', '출금'], true)) {
                return 'OUT';
            }
        }

        if ($dataType === 'BANK_TRANSACTION') {
            $deposit = $this->amountOrNull($row['deposit_amount'] ?? null);
            $withdraw = $this->amountOrNull($row['withdraw_amount'] ?? $row['withdrawal_amount'] ?? null);
            if ($withdraw !== null && $withdraw > 0) {
                return 'OUT';
            }
            if ($deposit !== null && $deposit > 0) {
                return 'IN';
            }
            if (in_array($direction, ['IN', 'OUT'], true)) {
                return $direction;
            }
        }

        if ($direction === '') {
            $direction = match ($dataType) {
                'CASH_RECEIPT_PURCHASE', 'CARD_STATEMENT', 'CARD_APPROVAL', 'CASH_RECEIPT' => 'PURCHASE',
                'CASH_RECEIPT_SALES' => 'SALES',
                default => '',
            };
        }

        return match ($direction) {
            'SALES', 'SALE', 'SELL', 'OUT' => 'SALES',
            'PURCHASE', 'BUY', 'IN' => 'PURCHASE',
            'DEPOSIT' => 'IN',
            'WITHDRAWAL', 'PAYMENT' => 'OUT',
            default => $direction !== '' ? $direction : ($dataType === 'BANK_TRANSACTION' ? 'IN' : 'GENERAL'),
        };
    }

    private static function normalizeDataType(string $type): string
    {
        $type = strtoupper(trim($type));
        return self::LEGACY_DATA_TYPE_MAP[$type] ?? $type;
    }

    private static function isManualTaxInvoiceDataType(string $dataType): bool
    {
        $type = self::normalizeDataType($dataType);
        if (in_array($type, [
            'TAX_INVOICE_MANUAL',
            'MANUAL_TAX_INVOICE',
            'TAX_INVOICE_PURCHASE_SALES_MANUAL',
            'TAX_INVOICE_BUY_SELL_MANUAL',
        ], true)) {
            return true;
        }

        $compact = preg_replace('/[\s_\-()]+/u', '', $type) ?? $type;
        return (
            str_contains($type, 'TAX')
            && str_contains($type, 'INVOICE')
            && str_contains($type, 'MANUAL')
        ) || (
            str_contains($compact, '세금계산서')
            && str_contains($compact, '수기')
        );
    }

    private static function processingPlanForDataType(string $dataType): array
    {
        $dataType = self::normalizeDataType($dataType);
        if (self::isManualTaxInvoiceDataType($dataType)) {
            return [
                'type' => 'TRANSACTION',
                'target' => 'TRANSACTION_HEADER',
                'objects' => ['TRANSACTION_HEADER'],
                'label' => '거래 + 전표',
            ];
        }

        return match ($dataType) {
            'TAX_INVOICE', 'CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES' => [
                'type' => 'TRANSACTION',
                'target' => 'TRANSACTION_HEADER',
                'objects' => ['TRANSACTION_HEADER'],
                'label' => '거래 + 전표',
            ],
            'CARD_STATEMENT', 'CARD_APPROVAL' => [
                'type' => 'TRANSACTION',
                'target' => 'TRANSACTION_AND_VOUCHER',
                'objects' => ['TRANSACTION_HEADER', 'TRANSACTION_LINE', 'VOUCHER_HEADER', 'VOUCHER_LINE'],
                'label' => '거래 + 전표 생성',
            ],
            'CARD_HOMETAX' => [
                'type' => 'VERIFY_ONLY',
                'target' => 'VERIFY_ONLY',
                'objects' => ['TAX_VERIFY', 'RECONCILIATION'],
                'label' => '세무 검증 + 카드대사',
            ],
            'BANK_TRANSACTION' => [
                'type' => 'BANK_FLOW',
                'target' => 'RECONCILIATION_ONLY',
                'objects' => ['BANK_FLOW', 'RECONCILIATION'],
                'label' => '입출금 원장 + 은행대사',
            ],
            'BUSINESS_DATA', 'SHOPPING_ORDER', 'PAYROLL', 'PAYROLL_WITHHOLDING', 'BUSINESS_INCOME', 'EMPLOYEE_EXPENSE', 'IMPORT_INVOICE', 'CONSTRUCTION' => [
                'type' => 'BUSINESS_DATA',
                'target' => 'BUSINESS_DATA',
                'objects' => ['BUSINESS_SYSTEM'],
                'label' => '업무시스템 처리',
            ],
            default => [
                'type' => 'UNSUPPORTED',
                'target' => 'UNSUPPORTED',
                'objects' => [],
                'label' => '거래 + 전표',
            ],
        };
    }

    private static function isTransactionProcessingType(string $dataType): bool
    {
        return self::processingPlanForDataType($dataType)['type'] === 'TRANSACTION';
    }

    private static function transactionProcessingDataTypes(): array
    {
        $types = array_values(array_filter(self::DATA_TYPES, static fn(string $type): bool => self::isTransactionProcessingType($type)));
        $types[] = 'BANK_TRANSACTION';

        return array_values(array_unique($types));
    }

    private function isAllowedDataType(string $type): bool
    {
        $type = self::normalizeDataType($type);
        return $type !== '' && in_array($type, $this->allowedDataTypes(), true);
    }

    private function allowedDataTypes(): array
    {
        static $types = null;
        if ($types !== null) {
            return $types;
        }

        $types = array_values(array_unique(array_merge(self::DATA_TYPES, self::BUSINESS_DATA_TYPES)));
        if (!$this->systemCodesTableExists()) {
            return $types;
        }

        $stmt = $this->pdo->prepare("
            SELECT code
            FROM system_codes
            WHERE deleted_at IS NULL
              AND is_active = 1
              AND code_group = 'IMPORT_TYPE'
            ORDER BY sort_no ASC, code ASC
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $codeTypes = array_values(array_filter(array_map(
            static fn($code): string => self::normalizeDataType((string) $code),
            $rows
        )));

        return array_values(array_unique(array_merge($types, $codeTypes)));
    }

    private function systemCodesTableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'system_codes'
            LIMIT 1
        ");
        $stmt->execute();
        $exists = (bool) $stmt->fetchColumn();

        return $exists;
    }

    private static function normalizeImportSourceType(string $sourceType): string
    {
        $sourceType = strtoupper(trim($sourceType));
        return match ($sourceType) {
            'HOMETAX' => 'TAX',
            'CARD_COMPANY' => 'CARD',
            'BANK_ACCOUNT' => 'BANK',
            'SHOPPING_MALL' => 'SHOPPING',
            'TRADE_IMPORT', 'IMPORT' => 'TRADE',
            default => $sourceType,
        };
    }

    private static function sourceTypeForDataType(string $dataType): string
    {
        return match (self::normalizeDataType($dataType)) {
            'TAX_INVOICE', 'CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES' => 'HOMETAX',
            'CARD_HOMETAX' => 'HOMETAX',
            'CARD_STATEMENT', 'CARD_APPROVAL' => 'CARD_COMPANY',
            'BANK_TRANSACTION' => 'BANK',
            'SHOPPING_ORDER' => 'SHOPPING',
            'IMPORT_INVOICE' => 'TRADE',
            default => 'MANUAL',
        };
    }

    private static function importTypesForSourceType(string $sourceType): array
    {
        return match (self::normalizeImportSourceType($sourceType)) {
            'TAX' => ['TAX_INVOICE', 'CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES', 'CARD_HOMETAX'],
            'CARD' => ['CARD_STATEMENT', 'CARD_APPROVAL'],
            'BANK' => ['BANK_TRANSACTION'],
            'SHOPPING', 'TRADE' => [],
            default => [],
        };
    }

    private static function sourceTypeLabel(string $sourceType): string
    {
        return match (self::normalizeImportSourceType($sourceType)) {
            'TAX' => '홈택스',
            'CARD', 'CARD_COMPANY' => '카드사',
            'BANK' => '은행',
            'SHOPPING' => '쇼핑몰',
            'TRADE' => '수입/무역',
            'MANUAL' => '수기입력',
            default => '',
        };
    }

    private static function importTypeLabel(string $importType): string
    {
        return match (self::normalizeDataType($importType)) {
            'TAX_INVOICE' => '세금계산서',
            'CASH_RECEIPT' => '현금영수증',
            'CASH_RECEIPT_PURCHASE' => '현금영수증(매입)',
            'CASH_RECEIPT_SALES' => '현금영수증(매출)',
            'CARD_HOMETAX' => '카드(홈택스)',
            'CARD_STATEMENT' => '카드(카드사)',
            'CARD_APPROVAL' => '카드(카드사)',
            'BANK_TRANSACTION' => '입출금',
            'SHOPPING_ORDER' => '주문',
            'IMPORT_INVOICE' => '수입인보이스',
            default => '',
        };
    }

    private static function sourceTypeSql(string $column): string
    {
        return "CASE {$column}
            WHEN 'HOMETAX' THEN 'HOMETAX'
            WHEN 'TAX' THEN 'HOMETAX'
            WHEN 'TAX_INVOICE' THEN 'HOMETAX'
            WHEN 'CASH_RECEIPT' THEN 'HOMETAX'
            WHEN 'CASH_RECEIPT_PURCHASE' THEN 'HOMETAX'
            WHEN 'CASH_RECEIPT_SALES' THEN 'HOMETAX'
            WHEN 'CARD_HOMETAX' THEN 'HOMETAX'
            WHEN 'CARD' THEN 'CARD_COMPANY'
            WHEN 'CARD_COMPANY' THEN 'CARD_COMPANY'
            WHEN 'CREDIT_CARD' THEN 'CARD_COMPANY'
            WHEN 'CARD_STATEMENT' THEN 'CARD_COMPANY'
            WHEN 'CARD_APPROVAL' THEN 'CARD_COMPANY'
            WHEN 'BANK' THEN 'BANK'
            WHEN 'BANK_ACCOUNT' THEN 'BANK'
            WHEN 'BANK_TRANSACTION' THEN 'BANK'
            WHEN 'SHOPPING_ORDER' THEN 'SHOPPING'
            WHEN 'IMPORT_INVOICE' THEN 'TRADE'
            ELSE 'MANUAL'
        END";
    }

    private static function queryDataTypes(string $type): array
    {
        $types = [$type];
        foreach (self::LEGACY_DATA_TYPE_MAP as $legacy => $current) {
            if ($current === $type) {
                $types[] = $legacy;
            }
        }

        return array_values(array_unique($types));
    }

    private static function dataTypeLabel(string $type): string
    {
        return [
            'TAX_INVOICE' => '세금계산서',
            'CASH_RECEIPT' => '현금영수증',
            'CASH_RECEIPT_PURCHASE' => '현금영수증(매입)',
            'CASH_RECEIPT_SALES' => '현금영수증(매출)',
            'CARD_STATEMENT' => '카드(카드사)',
            'CARD_APPROVAL' => '카드(카드사)',
            'BANK_TRANSACTION' => '입출금',
            'SHOPPING_ORDER' => '주문',
            'IMPORT_INVOICE' => '수입인보이스',
            'ETC' => '기타',
        ][$type] ?? '자료';
    }
    private static function safeFilename(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('#[\\/:*?"<>|]+#u', '_', $name) ?? '';
        $name = preg_replace('#[^\p{L}\p{N}_. \-()\[\]]+#u', '_', $name) ?? '';
        $name = preg_replace('#\s+#u', '_', $name) ?? '';
        $name = trim($name, "._- \t\n\n\0\x0B");

        return $name !== '' ? $name : '업로드_양식';
    }

    private function formatTransactionCreateError(string $message, array $row = [], int $rowNo = 0): string
    {
        $message = trim($message);
        $isSqlParameterError = str_contains($message, 'SQLSTATE[HY093]') || str_contains($message, 'Invalid parameter number');

        if ($isSqlParameterError) {
            $clientName = $this->cleanCompanyName((string) (
                $row['client_company_name']
                ?? $row['customer_company_name']
                ?? $row['supplier_company_name']
                ?? $row['company_name']
                ?? ''
            ));
            $approvalNo = trim((string) ($row['approval_number'] ?? $row['approval_no'] ?? ''));

            $parts = [];
            if ($rowNo > 0) {
                $parts[] = $rowNo . '행';
            }
            if ($clientName !== '') {
                $parts[] = '거래처 ' . $clientName;
            }
            if ($approvalNo !== '') {
                $parts[] = '승인번호 ' . $approvalNo;
            }

            $context = $parts !== [] ? ' (' . implode(', ', $parts) . ')' : '';
            return '거래 생성 중 내부 저장 파라미터 오류가 발생했습니다' . $context . '. 중복검사 또는 거래처 저장 단계의 필드 연결을 확인해야 합니다.';
        }

        if (str_contains($message, 'SQLSTATE[')) {
            $prefix = $rowNo > 0 ? $rowNo . '행: ' : '';
            return $prefix . '거래 생성 중 데이터베이스 저장 오류가 발생했습니다. 원본 필드값과 거래처/금액 매핑을 확인해야 합니다.';
        }

        return $message !== '' ? $message : '거래 생성 실패';
    }

    private static function safeSheetTitle(string $title): string
    {
        $title = trim($title);
        $title = preg_replace('#[\\/*?:\[\]]+#u', '_', $title) ?? '';
        $title = preg_replace('#\s+#u', ' ', $title) ?? '';
        $title = trim($title, "' ");
        if ($title === '') {
            $title = '양식';
        }

        return function_exists('mb_substr') ? mb_substr($title, 0, 31) : substr($title, 0, 31);
    }
    private function findClientId(string $clientName): ?string
    {
        $clientName = trim($clientName);
        if ($clientName === '') {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM system_clients WHERE client_name = :name AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':name' => $clientName]);
        return $stmt->fetchColumn() ?: null;
    }

    private function existingClientIdByBusinessNumber(string $businessNumber): ?string
    {
        $businessNumber = $this->normalizeBusinessNumber($businessNumber);
        if ($businessNumber === '') {
            return null;
        }
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM system_clients
            WHERE business_number = :business_number
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':business_number' => $businessNumber]);
        $id = $stmt->fetchColumn();
        return $id ? (string) $id : null;
    }

    private function shouldSyncTaxInvoiceEvidenceClients(string $dataType): bool
    {
        $dataType = self::normalizeDataType($dataType);
        return $dataType === 'TAX_INVOICE' || self::isManualTaxInvoiceDataType($dataType);
    }

    private function syncTaxInvoiceEvidenceClientsFromSource(array $payload, string $evidenceId, string $dataType): array
    {
        $this->ensureClientHistoryTable();
        $direction = $this->transactionDirectionForStorage((string) ($payload['transaction_direction'] ?? ''), $payload, $dataType);
        $primaryRole = $direction === 'SALES' ? 'customer' : 'supplier';
        $synced = [];
        $primaryClientId = null;

        foreach (['supplier', 'customer'] as $role) {
            $party = $this->taxInvoiceEvidenceParty($payload, $role);
            if ($party === null || $this->isOwnCompanyBusinessNumber((string) $party['business_number'])) {
                continue;
            }
            $clientId = $this->upsertEvidenceClientByBusinessNumber($party, $evidenceId, 'HOMETAX_TAX_INVOICE');
            if ($clientId === null) {
                continue;
            }
            $synced[$role] = $clientId;
            if ($role === $primaryRole) {
                $primaryClientId = $clientId;
            }
        }

        return [
            'primary_client_id' => $primaryClientId,
            'synced_client_ids' => $synced,
        ];
    }

    private function taxInvoiceEvidenceParty(array $payload, string $role): ?array
    {
        $prefix = $role === 'customer' ? 'customer' : 'supplier';
        $businessNumber = $this->normalizeBusinessNumber((string) ($payload[$prefix . '_business_number'] ?? ''));
        if ($businessNumber === '') {
            return null;
        }

        $companyName = $this->cleanCompanyName((string) ($payload[$prefix . '_company_name'] ?? $payload[$prefix . '_name'] ?? ''));
        return [
            'role' => $prefix,
            'business_number' => $businessNumber,
            'company_name' => $companyName,
            'ceo_name' => $this->nullableCleanString($payload[$prefix . '_ceo_name'] ?? null),
            'address' => $this->nullableCleanString($payload[$prefix . '_address'] ?? null),
        ];
    }

    private function isOwnCompanyBusinessNumber(string $businessNumber): bool
    {
        $businessNumber = $this->normalizeBusinessNumber($businessNumber);
        if ($businessNumber === '') {
            return false;
        }
        return in_array($businessNumber, $this->ownCompanyProfile()['business_numbers'], true);
    }

    private function upsertEvidenceClientByBusinessNumber(array $party, string $evidenceId, string $sourceType): ?string
    {
        $businessNumber = $this->normalizeBusinessNumber((string) ($party['business_number'] ?? ''));
        if ($businessNumber === '') {
            return null;
        }

        $companyName = $this->cleanCompanyName((string) ($party['company_name'] ?? ''));
        $ceoName = $this->nullableCleanString($party['ceo_name'] ?? null);
        $address = $this->nullableCleanString($party['address'] ?? null);
        $stmt = $this->pdo->prepare("
            SELECT id, client_name, company_name, ceo_name, address
            FROM system_clients
            WHERE business_number = :business_number
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':business_number' => $businessNumber]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$client) {
            $clientId = UuidHelper::generate();
            $clientName = $companyName !== '' ? $companyName : $businessNumber;
            $created = (new ClientModel($this->pdo))->create([
                'id' => $clientId,
                'sort_no' => SequenceHelper::next('system_clients', 'sort_no'),
                'client_name' => $clientName,
                'company_name' => $companyName !== '' ? $companyName : null,
                'business_number' => $businessNumber,
                'ceo_name' => $ceoName,
                'address' => $address,
                'registration_date' => date('Y-m-d'),
                'client_type' => 'CLIENT',
                'is_active' => 1,
                'created_by' => ActorHelper::user(),
                'updated_by' => ActorHelper::user(),
            ]);
            if (!$created) {
                throw new \RuntimeException('Failed to create client from evidence source.');
            }
            return $clientId;
        }

        $clientId = (string) $client['id'];
        $updates = [];
        foreach ([
            'company_name' => $companyName,
            'ceo_name' => $ceoName,
            'address' => $address,
        ] as $field => $newValue) {
            $newValue = trim((string) ($newValue ?? ''));
            if ($newValue === '') {
                continue;
            }
            $oldValue = trim((string) ($client[$field] ?? ''));
            if ($oldValue !== $newValue) {
                $updates[$field] = $newValue;
                $this->insertEvidenceClientHistory($clientId, $field, $oldValue, $newValue, $sourceType, $evidenceId);
            }
        }
        if ($updates !== []) {
            $sets = [];
            $params = [
                ':id' => $clientId,
                ':actor' => ActorHelper::user(),
            ];
            foreach ($updates as $field => $value) {
                $sets[] = "{$field} = :{$field}";
                $params[':' . $field] = $value;
            }
            $sql = 'UPDATE system_clients SET ' . implode(', ', $sets) . ', updated_at = NOW(), updated_by = :actor WHERE id = :id';
            $this->pdo->prepare($sql)->execute($params);
        }

        return $clientId;
    }

    private function insertEvidenceClientHistory(string $clientId, string $fieldName, string $oldValue, string $newValue, string $sourceType, string $evidenceId): void
    {
        $this->ensureClientHistoryTable();
        $stmt = $this->pdo->prepare("
            INSERT INTO system_client_histories
                (id, client_id, field_name, old_value, new_value, source_type, source_evidence_id, changed_at, changed_by)
            VALUES
                (:id, :client_id, :field_name, :old_value, :new_value, :source_type, :source_evidence_id, NOW(), :changed_by)
        ");
        $stmt->execute([
            ':id' => UuidHelper::generate(),
            ':client_id' => $clientId,
            ':field_name' => $fieldName,
            ':old_value' => $oldValue,
            ':new_value' => $newValue,
            ':source_type' => $sourceType,
            ':source_evidence_id' => $evidenceId,
            ':changed_by' => ActorHelper::user(),
        ]);
    }

    private function ensureClientHistoryTable(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS system_client_histories (
                id CHAR(36) NOT NULL,
                client_id VARCHAR(36) NOT NULL,
                field_name VARCHAR(100) NOT NULL,
                old_value TEXT NULL,
                new_value TEXT NULL,
                source_type VARCHAR(80) NULL,
                source_evidence_id VARCHAR(36) NULL,
                changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                changed_by VARCHAR(100) NULL,
                PRIMARY KEY (id),
                INDEX idx_client_changed_at (client_id, changed_at),
                INDEX idx_source_evidence (source_evidence_id),
                CONSTRAINT fk_system_client_histories_client
                    FOREIGN KEY (client_id) REFERENCES system_clients (id)
                    ON UPDATE RESTRICT ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        $ensured = true;
    }

    private function syncUploadClientsForTransaction(array $row, string $dataType, array $context): array
    {
        $dataType = self::normalizeDataType($dataType);
        $parties = $this->clientPartiesFromUploadRow($row, $dataType);
        $direction = $this->transactionDirectionForStorage((string) ($context['transaction_direction'] ?? ''), $row, $dataType);
        $primaryRole = match ($dataType) {
            'CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES', 'CARD_STATEMENT', 'CARD_APPROVAL' => 'merchant',
            'BANK_TRANSACTION' => 'counterparty',
            default => match ($direction) {
                'PURCHASE' => 'supplier',
                'SALES' => 'customer',
                'IN', 'OUT', 'BANK' => 'counterparty',
                default => 'primary',
            },
        };

        $primaryClientId = null;
        $synced = [];

        foreach ($parties as $party) {
            $party += $this->localizedClientNameFieldsFromUploadRow($row, (string) ($party['role'] ?? ''));
            $party = $this->normalizeImportClientParty($party);
            if ($party === null || $this->isOwnCompanyParty($party)) {
                continue;
            }

            $clientId = $this->upsertClientFromImportParty($party);
            if ($clientId === null) {
                continue;
            }

            $role = (string) ($party['role'] ?? '');
            $synced[$role] = $clientId;
            if ($role === $primaryRole) {
                $primaryClientId = $clientId;
            }
        }

        if ($primaryClientId === null) {
            $primary = $this->normalizeImportClientParty([
                'role' => 'primary',
                'business_number' => (string) ($context['client_business_number'] ?? $row['client_business_number'] ?? $row['business_number'] ?? ''),
                'company_name' => (string) ($context['client_company_name'] ?? $row['client_company_name'] ?? $row['company_name'] ?? ''),
            ] + $this->localizedClientNameFieldsFromUploadRow($row, 'primary'));
            if ($primary !== null && !$this->isOwnCompanyParty($primary)) {
                $primaryClientId = $this->upsertClientFromImportParty($primary);
            }
        }

        return [
            'primary_client_id' => $primaryClientId,
            'synced_client_ids' => $synced,
        ];
    }

    private function clientPartiesFromUploadRow(array $row, string $dataType): array
    {
        $dataType = self::normalizeDataType($dataType);

        if ($dataType === 'TAX_INVOICE' || self::isManualTaxInvoiceDataType($dataType)) {
            return [
                [
                    'role' => 'supplier',
                    'business_number' => $this->firstRowValue($row, ['supplier_business_number', '공급자사업자등록번호', '공급자 사업자등록번호']),
                    'branch_number' => $this->firstRowValue($row, ['supplier_branch_number', '공급자종사업장번호', '공급자 종사업장번호']),
                    'company_name' => $this->firstRowValue($row, ['supplier_company_name', 'supplier_name', '공급자상호', '공급자 상호']),
                    'ceo_name' => $this->firstRowValue($row, ['supplier_ceo_name', '공급자대표자명', '공급자 대표자명']),
                    'address' => $this->firstRowValue($row, ['supplier_address', '공급자주소', '공급자 주소']),
                    'email' => $this->firstRowValue($row, ['supplier_email', '공급자이메일', '공급자 이메일']),
                ],
                [
                    'role' => 'customer',
                    'business_number' => $this->firstRowValue($row, ['customer_business_number', 'recipient_business_number', '공급받는자사업자등록번호', '공급받는자 사업자등록번호']),
                    'branch_number' => $this->firstRowValue($row, ['customer_branch_number', 'recipient_branch_number', '공급받는자종사업장번호', '공급받는자 종사업장번호']),
                    'company_name' => $this->firstRowValue($row, ['customer_company_name', 'customer_name', 'recipient_company_name', 'recipient_name', '공급받는자상호', '공급받는자 상호']),
                    'ceo_name' => $this->firstRowValue($row, ['customer_ceo_name', 'recipient_ceo_name', '공급받는자대표자명', '공급받는자 대표자명']),
                    'address' => $this->firstRowValue($row, ['customer_address', 'recipient_address', '공급받는자주소', '공급받는자 주소']),
                    'email' => $this->joinRowValues($row, ['customer_email_1', 'customer_email_2', 'recipient_email_1', 'recipient_email_2', '공급받는자이메일1', '공급받는자 이메일1', '공급받는자이메일2', '공급받는자 이메일2']),
                ],
            ];
        }

        if (in_array($dataType, ['CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES'], true)) {
            return [[
                'role' => 'merchant',
                'business_number' => $this->firstRowValue($row, ['merchant_business_number', 'client_business_number', 'business_number', '가맹점사업자번호', '가맹점 사업자번호']),
                'company_name' => $this->firstRowValue($row, ['merchant_company_name', 'merchant_name', 'client_company_name', 'company_name', '가맹점명']),
                'business_type' => $this->firstRowValue($row, ['business_type', 'merchant_business_type', '업태']),
                'business_category' => $this->firstRowValue($row, ['business_category', 'merchant_business_category', 'industry', 'merchant_industry', '업종']),
                'memo' => $this->industryMemo($row),
            ]];
        }

        if (in_array($dataType, ['CARD_STATEMENT', 'CARD_APPROVAL'], true)) {
            return [
                [
                    'role' => 'card_company',
                    'company_name' => $this->firstRowValue($row, ['card_company', 'card_company_name', '카드사']),
                ],
                [
                    'role' => 'merchant',
                    'business_number' => $this->firstRowValue($row, ['merchant_business_number', 'client_business_number', 'business_number', '가맹점사업자번호', '가맹점 사업자번호']),
                    'company_name' => $this->firstRowValue($row, ['merchant_company_name', 'merchant_name', 'client_company_name', 'company_name', '가맹점명']),
                    'business_type' => $this->firstRowValue($row, ['business_type', 'merchant_business_type', '업태']),
                    'business_category' => $this->firstRowValue($row, ['business_category', 'merchant_business_category', 'merchant_industry', 'merchant_type', '가맹점업종', '가맹점유형', '업종']),
                    'address' => $this->joinRowValues($row, ['merchant_address', 'merchant_address1', 'merchant_address2', '가맹점주소1', '가맹점 주소1', '가맹점주소2', '가맹점 주소2']),
                    'phone' => $this->firstRowValue($row, ['merchant_phone', 'phone', '가맹점전화번호', '가맹점 전화번호']),
                    'memo' => $this->firstRowValue($row, ['merchant_zip_code', 'merchant_postal_code']) !== ''
                        ? 'merchant_zip_code: ' . $this->firstRowValue($row, ['merchant_zip_code', 'merchant_postal_code'])
                        : null,
                ],
            ];
        }

        if ($dataType === 'BANK_TRANSACTION') {
            $row = $this->normalizeBankTransactionPayload($row);
            return [[
                'role' => 'counterparty',
                'company_name' => $this->bankCounterpartyName($row),
                'bank_name' => $this->firstRowValue($row, ['counterparty_bank', 'counterparty_bank_name', 'bank_name', '상대은행']),
                'account_number' => $this->firstRowValue($row, ['counterparty_account_number', 'counterparty_account_no', 'account_number', '상대계좌번호']),
                'account_holder' => $this->firstRowValue($row, ['counterparty_account_holder_name', 'counterparty_account_holder', 'counterparty_name', '상대계좌예금주명']),
            ]];
        }

        return [[
            'role' => 'primary',
            'business_number' => $this->firstRowValue($row, ['client_business_number', 'business_number']),
            'company_name' => $this->firstRowValue($row, ['client_company_name', 'company_name', 'counterparty_name']),
        ]];
    }

    private function localizedClientNameFieldsFromUploadRow(array $row, string $role): array
    {
        $prefixes = match ($role) {
            'supplier' => ['supplier', 'client', 'company'],
            'customer' => ['customer', 'recipient', 'client', 'company'],
            'merchant' => ['merchant', 'client', 'company'],
            'counterparty' => ['counterparty', 'counterparty_account_holder', 'client', 'company'],
            'card_company' => ['card_company', 'card', 'company'],
            default => ['client', 'company', 'counterparty'],
        };

        $koKeys = [];
        $enKeys = [];
        foreach ($prefixes as $prefix) {
            foreach (['_name', '_company_name', ''] as $suffix) {
                $base = $prefix . $suffix;
                $koKeys[] = $base . '_ko';
                $koKeys[] = $base . '_kr';
                $koKeys[] = $base . '_korean';
                $enKeys[] = $base . '_en';
                $enKeys[] = $base . '_eng';
                $enKeys[] = $base . '_english';
            }
        }

        return [
            'company_name_ko' => $this->firstRowValue($row, array_values(array_unique($koKeys))),
            'company_name_en' => $this->firstRowValue($row, array_values(array_unique($enKeys))),
            'ceo_name_ko' => $this->firstRowValue($row, [$role . '_ceo_name_ko', 'client_ceo_name_ko', 'ceo_name_ko']),
            'ceo_name_en' => $this->firstRowValue($row, [$role . '_ceo_name_en', 'client_ceo_name_en', 'ceo_name_en']),
        ];
    }

    private function normalizeImportClientParty(array $party): ?array
    {
        $party['business_number'] = $this->normalizeBusinessNumber((string) ($party['business_number'] ?? ''));
        $party['company_name'] = $this->cleanCompanyName((string) ($party['company_name'] ?? ''));
        foreach ([
            'branch_number',
            'ceo_name',
            'ceo_name_ko',
            'ceo_name_en',
            'company_name_ko',
            'company_name_en',
            'client_name_ko',
            'client_name_en',
            'client_company_name_ko',
            'client_company_name_en',
            'korean_name',
            'english_name',
            'company_korean_name',
            'company_english_name',
            'client_korean_name',
            'client_english_name',
            'address',
            'email',
            'phone',
            'business_type',
            'business_category',
            'bank_name',
            'account_number',
            'account_holder',
            'memo',
        ] as $key) {
            $party[$key] = $this->nullableCleanString($party[$key] ?? null);
        }
        if ($party['company_name'] === '') {
            $party['company_name'] = $this->cleanCompanyName((string) (
                $party['company_name_ko']
                ?? $party['client_company_name_ko']
                ?? $party['client_name_ko']
                ?? $party['company_name_en']
                ?? $party['client_company_name_en']
                ?? $party['client_name_en']
                ?? ''
            ));
        }

        if ($party['business_number'] === '' && $party['company_name'] === '') {
            return null;
        }

        return $party;
    }

    private function upsertClientFromImportParty(array $party): ?string
    {
        $party = $this->normalizeImportClientParty($party);
        if ($party === null) {
            return null;
        }

        $businessNumber = (string) $party['business_number'];
        $companyName = (string) $party['company_name'];
        $client = $this->findClientRowForImportParty($party);
        if ($client) {
            $this->updateClientFromImportParty((string) $client['id'], $client, $party);
            return (string) $client['id'];
        }

        $clientId = UuidHelper::generate();
        $clientName = $this->uniqueClientNameFromImportParty($party);
        $created = (new ClientModel($this->pdo))->create([
            'id' => $clientId,
            'sort_no' => SequenceHelper::next('system_clients', 'sort_no'),
            'client_name' => $clientName,
            'company_name' => $companyName !== '' ? $companyName : null,
            'business_number' => $businessNumber !== '' ? $businessNumber : null,
            'registration_date' => date('Y-m-d'),
            'business_type' => $party['business_type'] ?? null,
            'business_category' => $party['business_category'] ?? null,
            'address' => $party['address'] ?? null,
            'phone' => $party['phone'] ?? null,
            'email' => $party['email'] ?? null,
            'ceo_name' => $party['ceo_name'] ?? null,
            'bank_name' => $party['bank_name'] ?? null,
            'account_number' => $party['account_number'] ?? null,
            'account_holder' => $party['account_holder'] ?? null,
            'client_type' => 'CLIENT',
            'note' => $this->clientPartyNote($party),
            'memo' => $party['memo'] ?? null,
            'is_active' => 1,
            'created_by' => ActorHelper::user(),
            'updated_by' => ActorHelper::user(),
        ]);
        if (!$created) {
            throw new \RuntimeException('거래처 자동 생성에 실패했습니다.');
        }

        return $clientId;
    }

    private function clientNameFromImportParty(array $party): string
    {
        $businessNumber = $this->normalizeBusinessNumber((string) ($party['business_number'] ?? ''));
        $koreanName = $this->internalClientBaseName($this->koreanClientNameCandidate($party));
        $englishName = $this->internalClientBaseName($this->englishClientNameCandidate($party));

        if ($koreanName !== '' && $englishName !== '' && $this->normalizeCompanyNameForCompare($koreanName) !== $this->normalizeCompanyNameForCompare($englishName)) {
            return $koreanName . '(' . $englishName . ')';
        }
        if ($koreanName !== '') {
            return $koreanName;
        }
        if ($englishName !== '') {
            return $englishName;
        }

        $companyName = $this->internalClientBaseName((string) ($party['company_name'] ?? ''));
        if ($companyName !== '') {
            return $companyName;
        }

        return $businessNumber;
    }

    private function uniqueClientNameFromImportParty(array $party, ?string $excludeClientId = null): string
    {
        $clientName = $this->clientNameFromImportParty($party);
        if ($clientName === '') {
            return '';
        }

        $businessNumber = $this->normalizeBusinessNumber((string) ($party['business_number'] ?? ''));
        if (!$this->hasDifferentClientWithName($clientName, $businessNumber, $excludeClientId)) {
            return $clientName;
        }

        $ceoName = $this->localizedPersonNameFromImportParty($party);
        if ($ceoName !== '' && !str_contains($clientName, $ceoName)) {
            return $clientName . '-' . $ceoName;
        }

        return $clientName;
    }

    private function koreanClientNameCandidate(array $party): string
    {
        foreach ([
            'client_name_ko',
            'client_company_name_ko',
            'company_name_ko',
            'korean_name',
            'company_korean_name',
            'client_korean_name',
            'counterparty_name_ko',
            'counterparty_korean_name',
            'client_name',
            'company_name',
            'counterparty_name',
        ] as $key) {
            $value = $this->cleanCompanyName((string) ($party[$key] ?? ''));
            if ($value !== '' && !$this->isUuid($value) && $this->containsHangul($value)) {
                return $value;
            }
        }

        return '';
    }

    private function englishClientNameCandidate(array $party): string
    {
        foreach ([
            'client_name_en',
            'client_company_name_en',
            'company_name_en',
            'english_name',
            'company_english_name',
            'client_english_name',
            'counterparty_name_en',
            'counterparty_english_name',
            'client_name',
            'company_name',
            'counterparty_name',
        ] as $key) {
            $value = $this->cleanCompanyName((string) ($party[$key] ?? ''));
            if ($value !== '' && !$this->isUuid($value) && !$this->containsHangul($value) && preg_match('/[A-Za-z]/', $value)) {
                return $value;
            }
        }

        return '';
    }

    private function localizedPersonNameFromImportParty(array $party): string
    {
        $koreanName = $this->internalClientBaseName((string) ($party['ceo_name_ko'] ?? ''));
        if ($koreanName === '') {
            $ceoName = $this->cleanCompanyName((string) ($party['ceo_name'] ?? ''));
            if ($this->containsHangul($ceoName)) {
                $koreanName = $this->internalClientBaseName($ceoName);
            }
        }

        $englishName = $this->internalClientBaseName((string) ($party['ceo_name_en'] ?? ''));
        if ($englishName === '') {
            $ceoName = $this->cleanCompanyName((string) ($party['ceo_name'] ?? ''));
            if ($ceoName !== '' && !$this->containsHangul($ceoName) && preg_match('/[A-Za-z]/', $ceoName)) {
                $englishName = $this->internalClientBaseName($ceoName);
            }
        }

        if ($koreanName !== '' && $englishName !== '' && $this->normalizeCompanyNameForCompare($koreanName) !== $this->normalizeCompanyNameForCompare($englishName)) {
            return $koreanName . '(' . $englishName . ')';
        }

        return $koreanName !== '' ? $koreanName : $englishName;
    }

    private function containsHangul(string $value): bool
    {
        return preg_match('/\p{Hangul}/u', $value) === 1;
    }

    private function internalClientBaseName(string $companyName): string
    {
        return $this->normalizedInternalClientBaseName($companyName);
    }

    private function normalizedInternalClientBaseName(string $companyName): string
    {
        $name = $this->cleanCompanyName($companyName);
        if ($name === '') {
            return '';
        }

        $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = preg_replace('/[[:space:]\x{00A0}]+/u', '', $name) ?? $name;

        $legalPatterns = [
            '/^\(주\)/u',
            '/^\(유\)/u',
            '/^㈜/u',
            '/^주식회사/u',
            '/^유한회사/u',
            '/^합자회사/u',
            '/^합명회사/u',
            '/^\(株\)/u',
            '/^株式會社/u',
            '/^co\.?,?ltd\.?/iu',
            '/^corporation/iu',
            '/^corp\.?/iu',
            '/^inc\.?/iu',
            '/^ltd\.?/iu',
            '/\(주\)$/u',
            '/\(유\)$/u',
            '/㈜$/u',
            '/주식회사$/u',
            '/유한회사$/u',
            '/합자회사$/u',
            '/합명회사$/u',
            '/\(株\)$/u',
            '/株式會社$/u',
            '/co\.?,?ltd\.?$/iu',
            '/corporation$/iu',
            '/corp\.?$/iu',
            '/inc\.?$/iu',
            '/ltd\.?$/iu',
        ];

        do {
            $before = $name;
            foreach ($legalPatterns as $pattern) {
                $name = preg_replace($pattern, '', $name) ?? $name;
            }
            $name = trim($name);
        } while ($name !== $before);

        return $name;
    }

    private function findClientRowForImportParty(array $party): ?array
    {
        $businessNumber = $this->normalizeBusinessNumber((string) ($party['business_number'] ?? ''));
        $companyName = $this->cleanCompanyName((string) ($party['company_name'] ?? ''));

        if ($businessNumber !== '') {
            $stmt = $this->pdo->prepare('
                SELECT *
                FROM system_clients
                WHERE business_number = :business_number
                  AND deleted_at IS NULL
                LIMIT 1
            ');
            $stmt->execute([':business_number' => $businessNumber]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($client) {
                return $client;
            }

            return null;
        }

        $candidateNames = array_values(array_unique(array_filter([
            $companyName,
            $this->clientNameFromImportParty($party),
            $this->internalClientBaseName($companyName),
            $this->internalClientBaseName($this->koreanClientNameCandidate($party)),
            $this->internalClientBaseName($this->englishClientNameCandidate($party)),
        ], static fn(string $value): bool => $value !== '')));

        if ($candidateNames !== []) {
            $conditions = [];
            $params = [];
            foreach ($candidateNames as $index => $name) {
                $clientParam = ':client_name_' . $index;
                $companyParam = ':company_name_' . $index;
                $conditions[] = "(client_name = {$clientParam} OR company_name = {$companyParam})";
                $params[$clientParam] = $name;
                $params[$companyParam] = $name;
            }

            $stmt = $this->pdo->prepare('
                SELECT *
                FROM system_clients
                WHERE deleted_at IS NULL
                  AND (' . implode(' OR ', $conditions) . ')
                LIMIT 1
            ');
            $stmt->execute($params);
            $client = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($client) {
                return $client;
            }
        }

        return null;
    }

    private function hasDifferentClientWithName(string $clientName, string $businessNumber = '', ?string $excludeClientId = null): bool
    {
        $clientName = trim($clientName);
        if ($clientName === '') {
            return false;
        }

        $where = ['client_name = :client_name', 'deleted_at IS NULL'];
        $params = [':client_name' => $clientName];

        if ($excludeClientId !== null && $excludeClientId !== '') {
            $where[] = 'id <> :exclude_client_id';
            $params[':exclude_client_id'] = $excludeClientId;
        }
        if ($businessNumber !== '') {
            $where[] = "(business_number IS NULL OR business_number = '' OR business_number <> :business_number)";
            $params[':business_number'] = $businessNumber;
        }

        $stmt = $this->pdo->prepare('
            SELECT 1
            FROM system_clients
            WHERE ' . implode(' AND ', $where) . '
            LIMIT 1
        ');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    private function updateClientFromImportParty(string $clientId, array $before, array $party): void
    {
        $updates = [];
        $companyName = $this->cleanCompanyName((string) ($party['company_name'] ?? ''));
        $oldCompanyName = $this->cleanCompanyName((string) ($before['company_name'] ?? ''));
        $oldClientName = $this->cleanCompanyName((string) ($before['client_name'] ?? ''));

        $newClientName = $this->uniqueClientNameFromImportParty($party, $clientId);
        if ($newClientName !== '' && !$this->containsHangul($newClientName) && $this->containsHangul($oldClientName)) {
            $englishName = $this->internalClientBaseName($this->englishClientNameCandidate($party));
            $oldKoreanName = preg_replace('/\([^)]*\)$/u', '', $oldClientName) ?? $oldClientName;
            $oldKoreanName = $this->internalClientBaseName($oldKoreanName);
            if ($oldKoreanName !== '' && $englishName !== '') {
                $newClientName = $oldKoreanName . '(' . $englishName . ')';
            }
        }
        $oldGeneratedClientName = $this->clientNameFromImportParty([
            'company_name' => $oldCompanyName,
            'ceo_name' => $before['ceo_name'] ?? '',
            'business_number' => $before['business_number'] ?? '',
        ]);
        $oldGeneratedPersonName = $this->localizedPersonNameFromImportParty([
            'ceo_name' => $before['ceo_name'] ?? '',
            'ceo_name_ko' => $before['ceo_name_ko'] ?? '',
            'ceo_name_en' => $before['ceo_name_en'] ?? '',
        ]);
        $oldGeneratedClientNameWithPerson = $oldGeneratedClientName;
        if ($oldGeneratedClientName !== '' && $oldGeneratedPersonName !== '' && !str_contains($oldGeneratedClientName, $oldGeneratedPersonName)) {
            $oldGeneratedClientNameWithPerson = $oldGeneratedClientName . '-' . $oldGeneratedPersonName;
        }
        $isAutoClientName = $oldClientName === ''
            || $oldClientName === $oldCompanyName
            || $oldClientName === $oldGeneratedClientName
            || $oldClientName === $oldGeneratedClientNameWithPerson;

        if ($companyName !== '' && $oldCompanyName !== $companyName) {
            if ($oldCompanyName !== '') {
                $this->insertClientNameHistory($clientId, $oldCompanyName, $companyName);
            }
            $updates['company_name'] = $companyName;
            if ($isAutoClientName) {
                $updates['client_name'] = $newClientName;
            }
        } elseif ($newClientName !== '' && $isAutoClientName) {
            $updates['client_name'] = $newClientName;
        }

        foreach ([
            'business_number',
            'business_type',
            'business_category',
            'address',
            'phone',
            'email',
            'ceo_name',
            'bank_name',
            'account_number',
            'account_holder',
        ] as $field) {
            $value = trim((string) ($party[$field] ?? ''));
            if ($value !== '' && trim((string) ($before[$field] ?? '')) !== $value) {
                $updates[$field] = $value;
            }
        }

        $note = $this->clientPartyNote($party);
        if ($note !== null && trim((string) ($before['note'] ?? '')) === '') {
            $updates['note'] = $note;
        }
        if (($party['memo'] ?? null) !== null && trim((string) ($before['memo'] ?? '')) === '') {
            $updates['memo'] = $party['memo'];
        }

        if ($updates === []) {
            return;
        }

        $updates['updated_at'] = date('Y-m-d H:i:s');
        $updates['updated_by'] = ActorHelper::user();

        $set = [];
        $params = [':id' => $clientId];
        foreach ($updates as $column => $value) {
            $param = ':' . $column;
            $set[] = $column . ' = ' . $param;
            $params[$param] = $value;
        }

        $stmt = $this->pdo->prepare('UPDATE system_clients SET ' . implode(', ', $set) . ' WHERE id = :id');
        $stmt->execute($params);
    }

    private function clientPartyNote(array $party): ?string
    {
        $parts = [];
        if (!empty($party['role'])) {
            $parts[] = 'source_role: ' . $party['role'];
        }
        if (!empty($party['branch_number'])) {
            $parts[] = 'branch_number: ' . $party['branch_number'];
        }

        return $parts !== [] ? implode(' / ', $parts) : null;
    }

    private function firstRowValue(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $this->payloadScalarForStorage($row[$key] ?? null);
            if (array_key_exists($key, $row) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return '';
    }

    private function joinRowValues(array $row, array $keys): string
    {
        $values = [];
        foreach ($keys as $key) {
            $value = trim((string) $this->payloadScalarForStorage($row[$key] ?? null));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return implode(', ', array_values(array_unique($values)));
    }

    private function industryMemo(array $row): ?string
    {
        $industryCode = $this->firstRowValue($row, ['industry_code', 'merchant_industry_code']);
        return $industryCode !== '' ? 'industry_code: ' . $industryCode : null;
    }

    private function nullableCleanString(mixed $value): ?string
    {
        $value = trim((string) $this->payloadScalarForStorage($value));
        return $value !== '' ? $value : null;
    }

    private function findOrCreateClient(string $businessNumber, string $companyName, array $profile = []): ?string
    {
        return $this->upsertClientFromImportParty($profile + [
            'role' => 'primary',
            'business_number' => $businessNumber,
            'company_name' => $companyName,
        ]);

        $businessNumber = $this->normalizeBusinessNumber($businessNumber);
        $companyName = $this->cleanCompanyName($companyName);

        if ($businessNumber === '' && $companyName === '') {
            return null;
        }

        if ($businessNumber !== '') {
            $stmt = $this->pdo->prepare('
                SELECT id, client_name, company_name
                FROM system_clients
                WHERE business_number = :business_number
                  AND deleted_at IS NULL
                LIMIT 1
            ');
            $stmt->execute([':business_number' => $businessNumber]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($client) {
                $oldCompanyName = $this->cleanCompanyName((string) ($client['company_name'] ?? $client['client_name'] ?? ''));
                if ($companyName !== '' && $oldCompanyName !== '' && $oldCompanyName !== $companyName) {
                    $this->insertClientNameHistory((string) $client['id'], $oldCompanyName, $companyName);
                    $this->updateClientCompanyName((string) $client['id'], $companyName);
                } elseif ($companyName !== '' && $oldCompanyName === '') {
                    $this->updateClientCompanyName((string) $client['id'], $companyName);
                }

                return (string) $client['id'];
            }
        }

        if ($businessNumber === '' && $companyName !== '') {
            $existingId = $this->findClientId($companyName);
            if ($existingId !== null) {
                return $existingId;
            }
        }

        $clientId = UuidHelper::generate();
        $clientName = $companyName !== '' ? $companyName : $businessNumber;
        $created = (new ClientModel($this->pdo))->create([
            'id' => $clientId,
            'sort_no' => SequenceHelper::next('system_clients', 'sort_no'),
            'client_name' => $clientName,
            'company_name' => $companyName !== '' ? $companyName : null,
            'business_number' => $businessNumber !== '' ? $businessNumber : null,
            'registration_date' => date('Y-m-d'),
            'client_type' => 'CLIENT',
            'is_active' => 1,
            'created_by' => ActorHelper::user(),
            'updated_by' => ActorHelper::user(),
        ]);
        if (!$created) {
            throw new \RuntimeException('거래처 자동 생성에 실패했습니다.');
        }

        return $clientId;
    }

    private function clientExistsByBusinessNumber(string $businessNumber): bool
    {
        $businessNumber = $this->normalizeBusinessNumber($businessNumber);
        if ($businessNumber === '') {
            return false;
        }

        $stmt = $this->pdo->prepare('
            SELECT 1
            FROM system_clients
            WHERE business_number = :business_number
              AND deleted_at IS NULL
            LIMIT 1
        ');
        $stmt->execute([':business_number' => $businessNumber]);
        return (bool) $stmt->fetchColumn();
    }

    private function updateClientCompanyName(string $clientId, string $companyName): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE system_clients
            SET client_name = :client_name,
                company_name = :company_name,
                updated_at = NOW(),
                updated_by = :actor
            WHERE id = :id
        ');
        $stmt->execute([
            ':id' => $clientId,
            ':client_name' => $companyName,
            ':company_name' => $companyName,
            ':actor' => ActorHelper::user(),
        ]);
    }

    private function insertClientNameHistory(string $clientId, string $oldCompanyName, string $newCompanyName): void
    {
        if (!$this->clientNameHistoryTableExists()) {
            return;
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO system_client_name_history
                (id, client_id, old_company_name, new_company_name, changed_at, changed_by)
            VALUES
                (:id, :client_id, :old_company_name, :new_company_name, NOW(), :changed_by)
        ');
        $stmt->execute([
            ':id' => UuidHelper::generate(),
            ':client_id' => $clientId,
            ':old_company_name' => $oldCompanyName,
            ':new_company_name' => $newCompanyName,
            ':changed_by' => ActorHelper::user(),
        ]);
    }

    private function clientNameHistoryTableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'system_client_name_history'
            LIMIT 1
        ");
        $stmt->execute();
        $exists = (bool) $stmt->fetchColumn();

        return $exists;
    }

    private function normalizeBusinessNumber(string $businessNumber): string
    {
        return preg_replace('/[^0-9]/', '', $businessNumber) ?? '';
    }

    private function cleanCompanyName(string $companyName): string
    {
        $companyName = trim($companyName);
        $companyName = preg_replace('/\s+/u', ' ', $companyName) ?? $companyName;
        $companyName = preg_replace('/^\s*[\(（]\s*주\s*[\)）]\s*/u', '', $companyName) ?? $companyName;
        $companyName = preg_replace('/\s*[\(（]\s*주\s*[\)）]\s*$/u', '', $companyName) ?? $companyName;
        return trim($companyName);
    }
    private function findProjectId(string $projectName): ?string
    {
        $projectName = trim($projectName);
        if ($projectName === '') {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM system_projects WHERE project_name = :name AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':name' => $projectName]);
        return $stmt->fetchColumn() ?: null;
    }

    private function requestPayload(): array
    {
        $raw = file_get_contents('php://input');
        $json = $raw !== false && trim($raw) !== '' ? json_decode($raw, true) : null;
        return is_array($json) ? array_replace_recursive($_POST, $json) : $_POST;
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private static function fieldLabel(string $field): string
    {
        return [
            'bank_direction' => '거래구분',
            'cash_receipt_transaction_type' => '현금영수증 거래구분',
            'card_transaction_type' => '카드 거래구분',
            'business_unit' => '사업구분',
            'transaction_type' => '거래유형',
            'currency_code' => '통화',
            'exchange_rate' => '환율',
            'project_name' => '사업명',
            'description' => '적요',
            'transaction_date' => '표준일자',
            'approval_number' => '승인번호',
            'issue_date' => '발급일자',
            'transmit_date' => '전송일자',
            'tax_invoice_category' => '전자세금계산서분류',
            'tax_invoice_type' => '전자세금계산서종류',
            'issue_type' => '발급유형',
            'receipt_claim_type' => '영수/청구구분',
            'total_amount' => '합계금액',
            'supply_amount' => '공급가액',
            'vat_amount' => '세액',
            'note' => '비고',
            'supplier_business_number' => '공급자 사업자등록번호',
            'supplier_branch_number' => '공급자 종사업장번호',
            'supplier_company_name' => '공급자 상호',
            'supplier_ceo_name' => '공급자 대표자명',
            'supplier_address' => '공급자 주소',
            'supplier_email' => '공급자 이메일',
            'customer_business_number' => '공급받는자 사업자등록번호',
            'customer_branch_number' => '공급받는자 종사업장번호',
            'customer_company_name' => '공급받는자 상호',
            'customer_ceo_name' => '공급받는자 대표자명',
            'customer_address' => '공급받는자 주소',
            'customer_email_1' => '공급받는자 이메일1',
            'customer_email_2' => '공급받는자 이메일2',
            'broker_business_number' => '수탁사업자등록번호',
            'broker_company_name' => '수탁자 상호',
            'item_date' => '품목일자',
            'item_name' => '품목명',
            'item_spec' => '품목규격',
            'item_qty' => '품목수량',
            'item_price' => '품목단가',
            'item_supply_amount' => '품목공급가액',
            'item_vat_amount' => '품목세액',
            'item_note' => '품목비고',
        ][$field] ?? $field;
    }
    private function cellValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        return trim((string) $value);
    }

    private function number(mixed $value): float
    {
        return (float) ($this->amountOrNull($value) ?? 0);
    }

    private function amountOrNull(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }

        $text = trim((string) $value);
        if ($text === '' || $text === '-' || strcasecmp($text, 'NaN') === 0) {
            return null;
        }

        $text = strtr($text, [
            '−' => '-',
            '–' => '-',
            '—' => '-',
            '－' => '-',
            '﹣' => '-',
            '△' => '-',
        ]);

        $sign = 1;
        if (preg_match('/^\(.+\)$/', $text) === 1) {
            $sign = -1;
            $text = substr($text, 1, -1);
        }

        $normalized = preg_replace('/[,\s₩원]/u', '', $text) ?? '';
        if (str_ends_with($normalized, '-') && strlen($normalized) > 1) {
            $sign *= -1;
            $normalized = rtrim($normalized, '-');
        }

        if ($normalized === '' || $normalized === '-') {
            return null;
        }

        return is_numeric($normalized) ? ((float) $normalized * $sign) : null;
    }

    private function bankBalanceStatus(mixed $value): string
    {
        if ($this->amountOrNull($value) !== null) {
            return 'ACTUAL';
        }

        $text = trim((string) ($value ?? ''));
        if ($text === '' || $text === '-' || strcasecmp($text, 'NaN') === 0) {
            return 'EMPTY';
        }

        return 'INVALID';
    }

    private function dateValue(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return date('Y-m-d');
        }
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $matches) === 1) {
            return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
        }
        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})/', $value, $matches) === 1) {
            $month = (int) $matches[2];
            $day = (int) $matches[3];
            if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
                return $matches[1] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[3], 2, '0', STR_PAD_LEFT);
            }
        }
        if (preg_match('/^(\d{2})(\d{2})[-\/.](\d{2})[-\/.](\d{2})$/', $value, $matches) === 1) {
            return $matches[3] . $matches[4] . '-' . $matches[1] . '-' . $matches[2];
        }
        if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})/', $value, $matches) === 1) {
            $first = (int) $matches[1];
            $second = (int) $matches[2];
            $month = $first > 12 && $second <= 12 ? $matches[2] : $matches[1];
            $day = $first > 12 && $second <= 12 ? $matches[1] : $matches[2];
            return $matches[3] . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
        }
        if (is_numeric($value)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }
        $time = strtotime($value);
        return $time === false ? $value : date('Y-m-d', $time);
    }

    private function dateValueOrNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '' || $value === '-' || $value === '0000-00-00') {
            return null;
        }
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $matches) === 1) {
            return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
        }
        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})/', $value, $matches) === 1) {
            $month = (int) $matches[2];
            $day = (int) $matches[3];
            if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
                return $matches[1] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[3], 2, '0', STR_PAD_LEFT);
            }
        }
        if (preg_match('/^(\d{2})(\d{2})[-\/.](\d{2})[-\/.](\d{2})$/', $value, $matches) === 1) {
            return $matches[3] . $matches[4] . '-' . $matches[1] . '-' . $matches[2];
        }
        if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})/', $value, $matches) === 1) {
            $first = (int) $matches[1];
            $second = (int) $matches[2];
            $month = $first > 12 && $second <= 12 ? $matches[2] : $matches[1];
            $day = $first > 12 && $second <= 12 ? $matches[1] : $matches[2];
            return $matches[3] . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
        }
        if (is_numeric($value)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $time = strtotime($value);
        return $time === false ? null : date('Y-m-d', $time);
    }

    private function dateTimeValue(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d H:i:s');
        }

        $time = strtotime($value);
        if ($time === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $time);
    }
}
