<?php

namespace App\Services\System;

use App\Models\System\ClientModel;
use Core\LoggerFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ClientExcelService
{
    private const COLUMN_DEFINITIONS = [
        ['key' => 'sort_no', 'label' => '순번', 'required' => false, 'template_default' => false, 'download_default' => true],
        ['key' => 'client_name', 'label' => '거래처명', 'required' => true, 'template_default' => true, 'download_default' => true],
        ['key' => 'company_name', 'label' => '상호명', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'registration_date', 'label' => '등록일자', 'required' => false, 'template_default' => true, 'download_default' => false],
        ['key' => 'business_number', 'label' => '사업자등록번호', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'rrn', 'label' => '법인/주민등록번호', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'business_type', 'label' => '업태', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'business_category', 'label' => '업종', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'business_status', 'label' => '사업자상태', 'required' => false, 'template_default' => true, 'download_default' => false],
        ['key' => 'business_certificate', 'label' => '사업자등록증', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'address', 'label' => '주소', 'required' => false, 'template_default' => false, 'download_default' => true],
        ['key' => 'address_detail', 'label' => '상세주소', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'phone', 'label' => '전화번호', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'fax', 'label' => '팩스', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'email', 'label' => '이메일', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'ceo_name', 'label' => '대표자명', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'ceo_phone', 'label' => '대표자전화', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'manager_name', 'label' => '담당자명', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'manager_phone', 'label' => '담당자전화', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'bank_name', 'label' => '은행명', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'account_number', 'label' => '계좌번호', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'account_holder', 'label' => '예금주', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'bank_file', 'label' => '통장사본', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'trade_category', 'label' => '거래구분', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'default_account_text', 'label' => '기본계정과목', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'item_category', 'label' => '취급품목', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'client_category', 'label' => '거래처분류', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'client_type', 'label' => '거래처구분', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'tax_type', 'label' => '과세구분', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'payment_term', 'label' => '결제조건', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'client_grade', 'label' => '거래처등급', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'homepage', 'label' => '홈페이지', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'note', 'label' => '비고', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'memo', 'label' => '메모', 'required' => false, 'template_default' => false, 'download_default' => true],
        ['key' => 'is_active', 'label' => '상태', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'created_at', 'label' => '생성일시', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'created_by_name', 'label' => '생성자', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'updated_at', 'label' => '수정일시', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'updated_by_name', 'label' => '수정자', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'deleted_at', 'label' => '삭제일시', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'deleted_by_name', 'label' => '삭제자', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'rrn_image', 'label' => '신분증이미지', 'required' => false, 'template_default' => false, 'download_default' => false],
    ];

    private const SAMPLE_ROW = [
        'client_name' => '샘플 거래처',
        'company_name' => '샘플 상호',
        'registration_date' => '2026-01-01',
        'business_number' => '123-45-67890',
        'business_type' => '서비스업',
        'business_category' => '소프트웨어',
        'business_status' => '정상',
        'address' => '서울시 중구 세종대로 1',
        'address_detail' => '101호',
        'phone' => '02-1234-5678',
        'fax' => '02-1234-0000',
        'email' => 'sample@example.com',
        'ceo_name' => '홍길동',
        'ceo_phone' => '010-1234-5678',
        'manager_name' => '담당자',
        'manager_phone' => '010-2222-3333',
        'bank_name' => '국민은행',
        'account_number' => '123-456-789012',
        'account_holder' => '샘플 거래처',
        'trade_category' => '매입',
        'default_account_text' => '외상매입금',
        'item_category' => '자재',
        'client_category' => '일반',
        'client_type' => '법인',
        'tax_type' => '과세',
        'payment_term' => '익월말',
        'client_grade' => 'A',
        'homepage' => 'https://example.com',
        'note' => '비고 예시',
        'memo' => '메모 예시',
        'is_active' => '사용',
    ];

    private ClientModel $model;
    private $logger;

    public function __construct(ClientModel $model)
    {
        $this->model = $model;
        $this->logger = LoggerFactory::getLogger('service-system.ClientExcelService');
    }

    public function downloadTemplate(?string $columnsCsv = null): void
    {
        $columns = $this->resolveColumns('template', $columnsCsv);
        $headers = $this->buildHeaders($columns);
        $rows = [$this->buildTemplateSampleRow($columns)];

        $this->writeSpreadsheet($headers, $rows, '거래처 업로드', 'client_template.xlsx');
    }

    public function saveFromExcelFile(string $filePath, callable $save, ?string $columnsCsv = null): array
    {
        $columns = $this->resolveColumns('template', $columnsCsv);
        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, false, false, false);

        if (empty($rows) || count($rows) < 2) {
            return ['success' => false, 'message' => '업로드할 데이터가 없습니다.'];
        }

        $headerRow = array_map(fn($value) => trim((string) $value), array_shift($rows));
        $headerMap = $this->buildHeaderIndexMap($headerRow, $columns);
        $missingRequired = $this->findMissingRequiredColumns($columns, $headerMap);

        if ($missingRequired !== []) {
            return [
                'success' => false,
                'message' => '필수 컬럼이 누락되었습니다: ' . implode(', ', $missingRequired),
            ];
        }

        $count = 0;

        foreach ($rows as $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $payload = $this->buildUploadPayload($row, $headerMap, $columns);
            if (($payload['client_name'] ?? '') === '') {
                continue;
            }

            $result = $save($payload, 'SYSTEM');
            if (!empty($result['success'])) {
                $count++;
            }
        }

        return ['success' => true, 'message' => "{$count}건 업로드되었습니다."];
    }

    public function downloadExcel(?string $columnsCsv = null): void
    {
        $columns = $this->resolveColumns('download', $columnsCsv);
        $clients = $this->model->getList();
        $rows = [];

        foreach ($clients as $client) {
            $rows[] = $this->buildDownloadRow($client, $columns);
        }

        $this->writeSpreadsheet(
            $this->buildHeaders($columns),
            $rows,
            '거래처 목록',
            'client_list.xlsx'
        );
    }

    public function downloadMigrationTemplate(?string $columnsCsv = null): void
    {
        $this->downloadTemplate($columnsCsv);
    }

    public function saveFromMigrationExcelFile(string $filePath, callable $save, ?string $columnsCsv = null): array
    {
        return $this->saveFromExcelFile($filePath, $save, $columnsCsv);
    }

    public function downloadMigrationExcel(?string $columnsCsv = null): void
    {
        $this->downloadExcel($columnsCsv);
    }

    public function getClientMigrationHeaders(?string $columnsCsv = null): array
    {
        return $this->buildHeaders($this->resolveColumns('template', $columnsCsv));
    }

    public function getClientMigrationHeaderMap(?string $columnsCsv = null): array
    {
        return $this->buildUploadLookup($this->resolveColumns('template', $columnsCsv));
    }

    public function normalizeMigrationExcelDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? $value : date('Y-m-d', $timestamp);
    }

    public function parseMigrationExcelActiveValue(mixed $value): int
    {
        $normalized = mb_strtolower(trim((string) $value), 'UTF-8');
        return in_array($normalized, ['1', 'true', 'yes', 'use', 'active', 'y', '사용'], true) ? 1 : 0;
    }

    private function resolveColumns(string $type, ?string $columnsCsv = null): array
    {
        $columnsByKey = [];
        foreach (self::COLUMN_DEFINITIONS as $column) {
            $columnsByKey[$column['key']] = $column;
        }

        $requestedKeys = $this->parseColumnsCsv($columnsCsv);
        $selectedKeys = [];

        if ($requestedKeys === []) {
            foreach (self::COLUMN_DEFINITIONS as $column) {
                if ($column['required'] || $column[$type . '_default']) {
                    $selectedKeys[] = $column['key'];
                }
            }
        } else {
            foreach ($requestedKeys as $key) {
                if (isset($columnsByKey[$key])) {
                    $selectedKeys[] = $key;
                }
            }

            foreach (self::COLUMN_DEFINITIONS as $column) {
                if ($column['required'] && !in_array($column['key'], $selectedKeys, true)) {
                    $selectedKeys[] = $column['key'];
                }
            }
        }

        if ($selectedKeys === []) {
            return $this->resolveColumns($type, '');
        }

        $selectedColumns = [];
        foreach ($selectedKeys as $key) {
            $selectedColumns[] = $columnsByKey[$key];
        }

        return $this->decorateColumns($selectedColumns);
    }

    private function parseColumnsCsv(?string $columnsCsv): array
    {
        $resolved = trim((string) $columnsCsv);
        if ($resolved === '') {
            return [];
        }

        $keys = array_map('trim', explode(',', $resolved));
        $keys = array_values(array_filter($keys, static fn($key) => $key !== ''));

        return array_values(array_unique($keys));
    }

    private function decorateColumns(array $columns): array
    {
        $labelCounts = [];
        foreach ($columns as $column) {
            $labelCounts[$column['label']] = ($labelCounts[$column['label']] ?? 0) + 1;
        }

        return array_map(function (array $column) use ($labelCounts): array {
            $header = ($labelCounts[$column['label']] ?? 0) > 1
                ? sprintf('%s [%s]', $column['label'], $column['key'])
                : $column['label'];

            return $column + [
                'alias_of' => $column['alias_of'] ?? null,
                'source_key' => $column['source_key'] ?? $column['key'],
                'payload_key' => $column['payload_key'] ?? $column['key'],
                'header' => $header,
            ];
        }, $columns);
    }

    private function buildHeaders(array $columns): array
    {
        return array_map(static fn(array $column): string => $column['header'], $columns);
    }

    private function buildTemplateSampleRow(array $columns): array
    {
        $row = [];

        foreach ($columns as $column) {
            $sourceKey = $column['source_key'];
            $row[] = self::SAMPLE_ROW[$column['key']]
                ?? self::SAMPLE_ROW[$sourceKey]
                ?? '';
        }

        return $row;
    }

    private function buildDownloadRow(array $record, array $columns): array
    {
        $row = [];

        foreach ($columns as $column) {
            $row[] = $this->exportCellValue($record, $column);
        }

        return $row;
    }

    private function exportCellValue(array $record, array $column): mixed
    {
        $sourceKey = $column['source_key'];
        $value = $record[$sourceKey] ?? $record[$column['key']] ?? '';

        if ($column['key'] === 'is_active') {
            return !empty($value) ? '사용' : '미사용';
        }

        return $value;
    }

    private function buildHeaderIndexMap(array $headerRow, array $columns): array
    {
        $lookup = $this->buildUploadLookup($columns);
        $indexMap = [];

        foreach ($headerRow as $index => $header) {
            $key = $this->resolveUploadHeaderKey((string) $header, $lookup);
            if ($key !== null && !array_key_exists($key, $indexMap)) {
                $indexMap[$key] = $index;
            }
        }

        return $indexMap;
    }

    private function buildUploadLookup(array $columns): array
    {
        $lookup = [];

        foreach ($columns as $column) {
            $this->registerLookupAlias($lookup, $column['header'], $column['key']);
            $this->registerLookupAlias($lookup, $column['label'], $column['key']);
            $this->registerLookupAlias($lookup, $column['key'], $column['key']);

            if (!empty($column['alias_of'])) {
                $this->registerLookupAlias($lookup, $column['alias_of'], $column['key']);
                $this->registerLookupAlias($lookup, sprintf('%s [%s]', $column['label'], $column['alias_of']), $column['key']);
            }
        }

        return $lookup;
    }

    private function registerLookupAlias(array &$lookup, string $token, string $key): void
    {
        $trimmed = trim($token);
        if ($trimmed === '') {
            return;
        }

        if (!isset($lookup[$trimmed])) {
            $lookup[$trimmed] = $key;
        }

        $normalized = $this->normalizeHeaderToken($trimmed);
        if ($normalized !== '' && !isset($lookup[$normalized])) {
            $lookup[$normalized] = $key;
        }
    }

    private function resolveUploadHeaderKey(string $header, array $lookup): ?string
    {
        $trimmed = trim($header);
        if ($trimmed === '') {
            return null;
        }

        if (isset($lookup[$trimmed])) {
            return $lookup[$trimmed];
        }

        $normalized = $this->normalizeHeaderToken($trimmed);
        if ($normalized !== '' && isset($lookup[$normalized])) {
            return $lookup[$normalized];
        }

        if (preg_match('/\[(?<key>[a-z0-9_]+)\]\s*$/i', $trimmed, $matches) === 1) {
            $key = trim((string) ($matches['key'] ?? ''));
            if ($key !== '') {
                return $key;
            }
        }

        return null;
    }

    private function normalizeHeaderToken(string $token): string
    {
        $normalized = mb_strtolower(trim($token), 'UTF-8');
        return preg_replace('/[\s_\-]+/u', '', $normalized) ?? '';
    }

    private function findMissingRequiredColumns(array $columns, array $headerMap): array
    {
        $missing = [];

        foreach ($columns as $column) {
            if ($column['required'] && !array_key_exists($column['key'], $headerMap)) {
                $missing[] = $column['label'];
            }
        }

        return $missing;
    }

    private function buildUploadPayload(array $row, array $headerMap, array $columns): array
    {
        $payload = [];

        foreach ($columns as $column) {
            if (!array_key_exists($column['key'], $headerMap)) {
                continue;
            }

            $rawValue = $row[$headerMap[$column['key']]] ?? '';
            $payload[$column['payload_key']] = $this->normalizeUploadValue($column['payload_key'], $rawValue);
        }

        if (($payload['registration_date'] ?? '') === '') {
            $payload['registration_date'] = date('Y-m-d');
        }

        return $payload;
    }

    private function normalizeUploadValue(string $key, mixed $value): mixed
    {
        if ($key === 'registration_date') {
            return $this->normalizeMigrationExcelDate($value) ?? '';
        }

        return trim((string) $value);
    }

    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function writeSpreadsheet(array $headers, array $rows, string $title, string $filename): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($title);
        $sheet->fromArray($headers, null, 'A1');

        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');
        }

        for ($index = 1; $index <= count($headers); $index++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        exit;
    }
}
