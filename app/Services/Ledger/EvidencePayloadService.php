<?php

namespace App\Services\Ledger;

use PDO;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class EvidencePayloadService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function outputDownload(array $format, array $columns, string $formatId, string $importType): void
    {
        $stmt = $this->pdo->prepare("
            SELECT mapped_payload_json, raw_json
            FROM ledger_evidence_payloads
            WHERE deleted_at IS NULL
              AND evidence_type = :source_type
              AND format_id = :format_id
            ORDER BY latest_imported_at DESC, created_at DESC
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

        $baseName = preg_replace('/[^\p{L}\p{N}_-]+/u', '_', (string) ($format['format_name'] ?? $importType)) ?: 'evidences';
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
        $stmt = $this->pdo->prepare("
            SELECT mapped_payload_json, updated_at, created_at
            FROM ledger_evidence_payloads
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
}
