<?php

namespace App\Services\Ledger;

use Core\Helpers\ExcelValueFormatterHelper;
use App\Models\Ledger\EvidenceImportModel;
use App\Models\Ledger\EvidenceSchemaModel;
use PDO;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class EvidenceDownloadService
{
    private const LEGACY_DATA_TYPE_MAP = [
        'DATA' => 'TAX_INVOICE',
        'TAX' => 'TAX_INVOICE',
        'CARD' => 'CARD_STATEMENT',
        'CARD_PURCHASE' => 'CARD_STATEMENT',
        'CARD_SALE' => 'CARD_STATEMENT',
        'CASH_RECEIPT_PURCHASE' => 'CASH_RECEIPT',
        'CASH_RECEIPT_PURCHAS' => 'CASH_RECEIPT',
        'CASH_RECEIPT_BUY' => 'CASH_RECEIPT',
        'CASH_RECEIPT_SALES' => 'CASH_RECEIPT_SALES',
        'CASH_RECEIPT_SALE' => 'CASH_RECEIPT_SALES',
        'CASH_RECEIPT_SELL' => 'CASH_RECEIPT_SALES',
        'BANK' => 'BANK_TRANSACTION',
        'SHOPPING' => 'SHOPPING_ORDER',
        'TRADE_IMPORT' => 'IMPORT_INVOICE',
        'IMPORT' => 'IMPORT_INVOICE',
    ];

    private PDO $pdo;
    private EvidenceImportModel $evidenceModel;
    private EvidenceSchemaModel $schemaModel;
    private ?SystemFieldService $systemFieldService = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->evidenceModel = new EvidenceImportModel($pdo);
        $this->schemaModel = new EvidenceSchemaModel($pdo);
    }

    public function outputDownload(array $format, array $columns, string $formatId, string $importType): void
    {
        $rows = $this->fetchDownloadRows($importType, (string) ($format['id'] ?? $formatId), true);
        $this->streamDownloadWorkbook($rows, $columns, (string) ($format['format_name'] ?? $importType));
    }

    public function outputSyntheticDownloadByType(string $importType, string $columnsCsv = ''): bool
    {
        $dataType = self::normalizeDataType($importType);
        if ($dataType === '') {
            return false;
        }

        $columns = $this->syntheticColumnsForDataType($dataType, $columnsCsv);
        if ($columns === []) {
            return false;
        }

        $rows = $this->fetchDownloadRows($dataType, '', false);
        $this->streamDownloadWorkbook($rows, $columns, strtolower($dataType));

        return true;
    }

    public function searchSummary(string $query, int $limit = 10): array
    {
        return $this->searchEvidenceVoucherSummaryTexts($query, $limit);
    }

    private function searchEvidenceVoucherSummaryTexts(string $keyword, int $limit = 10): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        $limit = max(1, min($limit, 20));
        $summaries = [];
        foreach ($this->evidenceModel->findSummaryPayloadRows($keyword) as $row) {
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

    private function fetchDownloadRows(string $importType, string $formatId = '', bool $withFormatId = false): array
    {
        return $this->evidenceModel->findDownloadRows($importType, $formatId, $withFormatId);
    }

    private function streamDownloadWorkbook(array $rows, array $columns, string $formatName): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('evidences');
        $headers = [];
        $downloadRows = [];
        foreach ($columns as $index => $column) {
            $headers[] = (string) ($column['excel_column_name'] ?? $column['system_field_name'] ?? 'column');
            $cell = Coordinate::stringFromColumnIndex($index + 1) . '1';
            $sheet->setCellValue($cell, (string) ($column['excel_column_name'] ?? $column['system_field_name'] ?? 'column'));
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F6FA');
        }

        foreach ($rows as $row) {
            $payload = json_decode((string) ($row['mapped_payload_json'] ?? ''), true) ?: [];
            $raw = json_decode((string) ($row['raw_json'] ?? ''), true) ?: [];
            $downloadRow = [];
            foreach ($columns as $column) {
                $downloadRow[] = $this->downloadValueForColumn($payload, $raw, $column);
            }
            $downloadRows[] = $downloadRow;
        }

        ExcelValueFormatterHelper::writeTable($sheet, $headers, $downloadRows, 'A1', $columns);

        foreach (range(1, count($columns)) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }

        $baseName = preg_replace('/[^\p{L}\p{N}_-]+/u', '_', $formatName) ?: 'evidences';
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

    private function syntheticColumnsForDataType(string $dataType, string $columnsCsv = ''): array
    {
        $columns = [];
        foreach ($this->sourceFieldOptionsForDataType($dataType) as $index => $fieldOption) {
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
                'is_required' => (int) ($fieldOption['is_required'] ?? 0),
                'is_reference_column' => 0,
                'is_visible' => 1,
            ];
        }

        return $this->filterColumns($columns, $columnsCsv);
    }

    private function filterColumns(array $columns, string $columnsCsv): array
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
        foreach ($requested as $requestedKey) {
            if (isset($columnMap[$requestedKey])) {
                $filtered[] = $columnMap[$requestedKey];
            }
        }

        return $filtered !== [] ? $filtered : $columns;
    }

    private function sourceFieldOptionsForDataType(string $dataType): array
    {
        $options = [];
        foreach ($this->systemFieldService()->sourceColumnOptions($dataType) as $fieldOption) {
            $field = trim((string) ($fieldOption['value'] ?? ''));
            $label = trim((string) ($fieldOption['label'] ?? $field));
            if ($field === '' || $label === '') {
                continue;
            }

            $options[] = [
                'original_column_key' => $field,
                'label' => $label,
                'system_field_name' => $field,
                'is_required' => (int) ($fieldOption['is_required'] ?? 0),
            ];
        }

        return $options;
    }

    private function downloadValueForColumn(array $payload, array $raw, array $column): mixed
    {
        $originalKey = trim((string) ($column['original_column_key'] ?? ''));
        $field = trim((string) ($column['system_field_name'] ?? ''));
        $excelName = trim((string) ($column['excel_column_name'] ?? ''));

        if ($field !== '' && array_key_exists($field, $payload)) {
            return $payload[$field];
        }

        if ($originalKey !== '' && array_key_exists($originalKey, $payload)) {
            return $payload[$originalKey];
        }

        return $payload[$excelName] ?? $raw[$excelName] ?? $raw[$field] ?? $raw[$originalKey] ?? '';
    }

    private function systemFieldService(): SystemFieldService
    {
        if ($this->systemFieldService === null) {
            $this->systemFieldService = new SystemFieldService($this->pdo);
        }

        return $this->systemFieldService;
    }

    private static function normalizeDataType(string $type): string
    {
        $type = strtoupper(trim($type));

        return self::LEGACY_DATA_TYPE_MAP[$type] ?? $type;
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        $cache[$table] = $this->schemaModel->tableExists($table);

        return $cache[$table];
    }
}
