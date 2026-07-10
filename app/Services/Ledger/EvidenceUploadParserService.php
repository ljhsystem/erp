<?php

namespace App\Services\Ledger;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EvidenceUploadParserService
{
    /**
     * @param callable(array,bool):array $splitBankFormatColumns
     * @param callable(mixed):string $cellValueReader
     */
    public function __construct(
        private $splitBankFormatColumns,
        private $cellValueReader
    ) {
    }

    public function parseUploadedRows(array $file, array $columns): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('업로드된 파일을 읽을 수 없습니다.');
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
                    'value' => ($this->cellValueReader)($rawRow[$column['sheet_column']] ?? null),
                ];
            }
            $mapped = [];
            foreach ($mappedColumns as $column) {
                $value = ($this->cellValueReader)($rawRow[$column['sheet_column']] ?? null);
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

    public function hasBankVoucherLineColumns(array $columns): bool
    {
        $lineFields = array_flip([
            'header_row_no',
            'sort_no',
            'line_row_type',
            'account_id',
            'debit',
            'credit',
            'line_summary',
            'line_ref_target',
            'line_ref_id',
        ]);
        foreach ($columns as $column) {
            if (isset($lineFields[(string) ($column['system_field_name'] ?? '')])) {
                return true;
            }
        }

        return false;
    }

    public function parseUploadedBankWorkbook(Spreadsheet $spreadsheet, array $columns): array
    {
        [$headerColumns, $lineColumns] = ($this->splitBankFormatColumns)($columns, $this->bankLineSheetHasRowTypeColumn($spreadsheet));
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

    public function bankLineSheetHasRowTypeColumn(Spreadsheet $spreadsheet): bool
    {
        if ($spreadsheet->getSheetCount() < 2) {
            return false;
        }

        $headerRow = $spreadsheet->getSheet(1)->rangeToArray('1:1', null, true, true, true)[1] ?? [];
        foreach ($headerRow as $header) {
            $header = preg_replace('/\s*\*$/u', '', trim((string) $header)) ?? trim((string) $header);
            if (in_array($header, ['행유형', '행타입', 'line_row_type'], true)) {
                return true;
            }
        }

        return false;
    }

    public function normalizeBankVoucherLineRowType(mixed $value): string
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

    public function parseSheetMappedRows(Worksheet $sheet, array $columns, bool $sequentialColumns = false): array
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
                $value = ($this->cellValueReader)($rawRow[$column['sheet_column']] ?? null);
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

    public function uploadSheetColumnForFormatColumn(
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

    public function uploadHeaderColumnsByName(array $headerRow): array
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

    private function sheetColumnFromFormatColumn(array $column): ?string
    {
        $index = (int) ($column['excel_column_index'] ?? $column['column_order'] ?? 0);
        if ($index < 1) {
            return null;
        }

        return Coordinate::stringFromColumnIndex($index);
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

        $cleanName = self::normalizeUploadHeaderName($name);
        $aliasMap = [
            'approval_number' => ['승인번호', '원본승인번호', '원본식별값', '원본키', '외부원본식별값'],
            'write_date' => ['작성일자', '작성일'],
            'transaction_date' => ['거래일자', '거래일'],
            'transaction_datetime' => ['거래일시', '거래일시시간', '거래일시/시간'],
            'transaction_time' => ['거래시간'],
            'issue_date' => ['발급일자', '발행일자'],
            'issue_type' => ['발급유형'],
            'issue_method' => ['발급방식', '발행방식'],
            'tax_invoice_category' => ['세금계산서종류', '세금계산서구분'],
            'tax_invoice_type' => ['세금계산서유형'],
            'receipt_claim_type' => ['영수청구구분', '영수청구', '영수/청구구분'],
            'business_unit' => ['사업구분'],
            'operation_type' => ['입출금유형', '입출금타입'],
            'transaction_direction' => ['현금영수증거래구분', '카드거래구분', '거래구분'],
            'evidence_date' => ['증빙일자', '증빙일'],
            'user_name' => ['사용자', '사용자명'],
            'source_key' => ['원본승인번호', '원본식별값', '원본키', '외부원본식별값'],
            'merchant_business_number' => ['가맹점사업자등록번호'],
            'merchant_company_name' => ['가맹점명', '가맹점상호', '가맹점상호명'],
            'merchant_industry_code' => ['가맹점업종코드'],
            'merchant_business_category' => ['가맹점업태'],
            'merchant_business_type' => ['가맹점업종'],
            'supplier_business_number' => ['공급자사업자등록번호'],
            'supplier_branch_number' => ['공급자종사업장번호'],
            'supplier_company_name' => ['공급자상호', '공급자회사명', '공급자명'],
            'supplier_ceo_name' => ['공급자대표자명'],
            'supplier_address' => ['공급자주소'],
            'supplier_email' => ['공급자이메일'],
            'customer_business_number' => ['공급받는자사업자등록번호'],
            'customer_branch_number' => ['공급받는자종사업장번호'],
            'customer_company_name' => ['공급받는자상호', '공급받는자회사명'],
            'customer_ceo_name' => ['공급받는자대표자명'],
            'customer_address' => ['공급받는자주소'],
            'customer_email_1' => ['공급받는자이메일1'],
            'customer_email_2' => ['공급받는자이메일2'],
            'business_number' => ['사업자등록번호'],
            'client_company_name' => ['상호', '회사명', '거래처명', '가맹점', '가맹점명', '사용처'],
            'supplier_name' => ['공급자', '공급자명'],
            'customer_name' => ['공급받는자', '공급받는자명'],
            'counterparty_name' => ['상대방명', '상대계좌예금주명', '거래처명'],
            'bank_direction' => ['입출금구분', '은행거래방향'],
            'description' => ['적요', '거래내용', '사용처'],
            'note' => ['비고', '메모'],
            'service_amount' => ['봉사료', '서비스금액'],
            'supply_amount' => ['공급가액', '공급금액'],
            'purchase_amount_krw' => ['원화매입금액', '원화금액', '원화공급가액'],
            'billing_amount' => ['청구금액'],
            'fee_amount' => ['수수료'],
            'actual_billing_amount' => ['실청구금액', '실결제금액'],
            'foreign_amount' => ['외화금액', '외화승인금액'],
            'local_amount' => ['원화환산금액', '국내금액'],
            'exchange_rate' => ['환율'],
            'total_amount' => ['합계금액', '총금액'],
            'vat_amount' => ['부가세', '세액', '부가세액'],
            'deposit_amount' => ['입금액', '입금'],
            'withdraw_amount' => ['출금액', '출금'],
            'balance_amount' => ['잔액', '거래후잔액', '거래후잔액금액'],
            'item_date' => ['품목일자'],
            'item_name' => ['품목명', '품목'],
            'item_spec' => ['품목규격', '규격'],
            'item_qty' => ['품목수량', '수량'],
            'item_price' => ['품목단가', '단가'],
            'item_supply_amount' => ['품목공급가액'],
            'item_vat_amount' => ['품목세액', '품목부가세'],
            'item_note' => ['품목비고'],
            'deduction_status' => ['공제여부'],
        ];

        foreach ($aliasMap as $field => $aliases) {
            foreach ($aliases as $alias) {
                if ($cleanName === self::normalizeUploadHeaderName($alias)) {
                    return $field;
                }
            }
        }

        $key = preg_replace('/[^A-Za-z0-9_]+/', '_', $name) ?? '';
        $key = trim(strtolower($key), '_');

        return $key !== '' ? $key : ($columnIndex > 0 ? 'column_' . $columnIndex : null);
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
}
