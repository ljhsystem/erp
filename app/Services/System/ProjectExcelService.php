<?php

namespace App\Services\System;

use App\Models\System\ProjectModel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProjectExcelService
{
    private const COLUMN_DEFINITIONS = [
        ['key' => 'sort_no', 'label' => '순번', 'required' => false, 'template_default' => false, 'download_default' => true],
        ['key' => 'project_name', 'label' => '프로젝트명', 'required' => true, 'template_default' => true, 'download_default' => true],
        ['key' => 'construction_name', 'label' => '공사명', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'employee_name', 'label' => '담당직원', 'required' => false, 'template_default' => false, 'download_default' => true],
        ['key' => 'contractor_name', 'label' => '담당직원', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'client_name', 'label' => '발주처명', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'client_type', 'label' => '발주처분류', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'bid_type', 'label' => '입찰방법', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'site_agent', 'label' => '현장대리인', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'linked_client_name', 'label' => '거래처', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'contract_type', 'label' => '계약종류', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'contract_method', 'label' => '계약방식', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'director', 'label' => '감리관/부서', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'manager', 'label' => '팀장', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'contract_work_type', 'label' => '계약종류(기존)', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'housing_type', 'label' => '공사유형', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'work_type', 'label' => '공종', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'work_subtype', 'label' => '공종 세부분류', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'business_type', 'label' => '업종', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'work_detail_type', 'label' => '세부 공사종류', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'site_region_city', 'label' => '시도', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'site_region_district', 'label' => '시군구', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'site_region_address', 'label' => '주소', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'site_region_address_detail', 'label' => '상세주소', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'permit_date', 'label' => '허가일자', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'contract_date', 'label' => '계약일자', 'required' => false, 'template_default' => true, 'download_default' => false],
        ['key' => 'start_date', 'label' => '착공일자', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'completion_date', 'label' => '준공일자', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'end_date', 'label' => '준공일자', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'bid_notice_date', 'label' => '입찰공고일', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'initial_contract_amount', 'label' => '최초계약금액', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'permit_agency', 'label' => '허가기관', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'authorized_company_seal', 'label' => '사용인감명', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'note', 'label' => '비고', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'memo', 'label' => '메모', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'is_active', 'label' => '진행상황', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'created_at', 'label' => '등록일시', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'created_by_name', 'label' => '등록자', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'updated_at', 'label' => '수정일시', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'updated_by_name', 'label' => '수정자', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'deleted_at', 'label' => '삭제일시', 'required' => false, 'template_default' => false, 'download_default' => false],
        ['key' => 'deleted_by_name', 'label' => '삭제자', 'required' => false, 'template_default' => false, 'download_default' => false],
    ];

    private const SAMPLE_ROW = [
        'project_name' => '샘플 프로젝트',
        'construction_name' => '샘플 공사',
        'employee_name' => '홍길동',
        'contractor_name' => '홍길동',
        'client_name' => '샘플 발주처',
        'client_type' => '공공',
        'bid_type' => '제한경쟁',
        'site_agent' => '현장대리인',
        'linked_client_name' => '샘플 거래처',
        'contract_type' => '도급',
        'contract_method' => '수의계약',
        'director' => '건축부',
        'manager' => '관리팀',
        'contract_work_type' => '신축',
        'housing_type' => '공동주택',
        'work_type' => '건축',
        'work_subtype' => '철근콘크리트',
        'business_type' => '건설업',
        'work_detail_type' => '주거시설',
        'site_region_city' => '서울',
        'site_region_district' => '중구',
        'site_region_address' => '서울시 중구 세종대로 1',
        'site_region_address_detail' => '공사현장',
        'permit_date' => '2026-01-05',
        'contract_date' => '2026-01-10',
        'start_date' => '2026-02-01',
        'completion_date' => '2027-01-31',
        'end_date' => '2027-01-31',
        'bid_notice_date' => '2025-12-20',
        'initial_contract_amount' => '1500000000',
        'permit_agency' => '서울시청',
        'authorized_company_seal' => '샘플인감',
        'note' => '비고 예시',
        'memo' => '메모 예시',
        'is_active' => '진행중',
    ];

    public function __construct(
        private readonly ProjectModel $model
    ) {
    }

    public function downloadTemplate(?string $columnsCsv = null): void
    {
        $columns = $this->resolveColumns('template', $columnsCsv);
        $rows = [$this->buildTemplateSampleRow($columns)];

        $this->writeSpreadsheet(
            $this->buildHeaders($columns),
            $rows,
            '프로젝트 업로드',
            'project_template.xlsx'
        );
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
            if (($payload['project_name'] ?? '') === '') {
                continue;
            }

            $payload['is_active'] = $payload['is_active'] ?? 1;

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
        $projects = $this->model->getList();
        $rows = [];

        foreach ($projects as $project) {
            $rows[] = $this->buildDownloadRow($project, $columns);
        }

        $this->writeSpreadsheet(
            $this->buildHeaders($columns),
            $rows,
            '프로젝트 목록',
            'project_list.xlsx'
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

    public function getProjectMigrationHeaders(?string $columnsCsv = null): array
    {
        return $this->buildHeaders($this->resolveColumns('template', $columnsCsv));
    }

    public function getProjectMigrationHeaderMap(?string $columnsCsv = null): array
    {
        return $this->buildUploadLookup($this->resolveColumns('template', $columnsCsv));
    }

    public function normalizeProjectExcelDate(mixed $value): ?string
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

    public function parseProjectExcelActiveValue(mixed $value): int
    {
        $normalized = mb_strtolower(trim((string) $value), 'UTF-8');
        return in_array($normalized, ['1', 'true', 'yes', 'use', 'active', 'y', '진행중', '사용'], true) ? 1 : 0;
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
            return !empty($value) ? '진행중' : '종료';
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

        return $payload;
    }

    private function normalizeUploadValue(string $key, mixed $value): mixed
    {
        if (in_array($key, ['contract_date', 'start_date', 'completion_date', 'end_date', 'permit_date', 'bid_notice_date'], true)) {
            return $this->normalizeProjectExcelDate($value);
        }

        if ($key === 'initial_contract_amount') {
            return is_numeric($value) ? (float) $value : (float) str_replace(',', '', trim((string) $value));
        }

        if ($key === 'is_active') {
            return $this->parseProjectExcelActiveValue($value);
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
