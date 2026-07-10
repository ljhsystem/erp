<?php

namespace App\Services\Ledger;

use Core\Helpers\ColumnPolicyRequestHelper;
use Core\Helpers\ExcelValueFormatterHelper;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ChartAccountExcelService
{
    private const COLUMN_DEFINITIONS = [
        ['key' => 'account_code', 'label' => '계정코드', 'required' => true, 'template_default' => true, 'download_default' => true],
        ['key' => 'account_name', 'label' => '계정과목명', 'required' => true, 'template_default' => true, 'download_default' => true],
        ['key' => 'parent_code', 'label' => '상위계정코드', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'account_group', 'label' => '계정구분', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'normal_balance', 'label' => '정상잔액', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'is_posting', 'label' => '전표입력', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'is_active', 'label' => '사용여부', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'note', 'label' => '비고', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'memo', 'label' => '메모', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'sub_account_names', 'label' => '보조계정 대상', 'required' => false, 'template_default' => true, 'download_default' => true],
    ];

    private const SAMPLE_ROWS = [
        [
            'account_code' => '1000',
            'account_name' => '현금',
            'parent_code' => '',
            'account_group' => '자산',
            'normal_balance' => '차변',
            'is_posting' => '가능',
            'is_active' => '사용',
            'note' => '현금 계정',
            'memo' => '샘플 메모',
            'sub_account_names' => '',
        ],
        [
            'account_code' => '1100',
            'account_name' => '보통예금',
            'parent_code' => '1000',
            'account_group' => '자산',
            'normal_balance' => '차변',
            'is_posting' => '가능',
            'is_active' => '사용',
            'note' => '은행 예금 계정',
            'memo' => '',
            'sub_account_names' => '',
        ],
    ];

    public function __construct(private readonly ChartAccountService $service)
    {
    }

    public function createTemplateSpreadsheet(?string $columnsCsv = null): Spreadsheet
    {
        $columns = $this->resolveColumns('template', $columnsCsv);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('계정과목 업로드');

        $headers = $this->buildHeaders($columns);
        $rows = array_map(fn(array $row): array => $this->buildRow($row, $columns), self::SAMPLE_ROWS);

        ExcelValueFormatterHelper::writeTable($sheet, $headers, $rows, 'A1', $columns, [
            'showRequiredAsterisk' => true,
        ]);

        $this->autoSize($sheet, count($headers));

        return $spreadsheet;
    }

    public function createExportSpreadsheet(?string $columnsCsv = null): Spreadsheet
    {
        $columns = $this->resolveColumns('download', $columnsCsv);
        $accounts = ExcelValueFormatterHelper::sortRowsBySortNo($this->service->getAll());
        $accountMap = [];

        foreach ($accounts as $account) {
            if (!empty($account['id'])) {
                $accountMap[(string) $account['id']] = $account;
            }
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('계정과목 목록');

        $headers = $this->buildHeaders($columns);
        $rows = [];
        foreach ($accounts as $account) {
            $exportRow = $account;
            $parentId = (string) ($account['parent_id'] ?? '');
            $exportRow['parent_code'] = ($parentId !== '' && isset($accountMap[$parentId]))
                ? (string) ($accountMap[$parentId]['account_code'] ?? '')
                : '';
            $rows[] = $this->buildRow($exportRow, $columns);
        }

        ExcelValueFormatterHelper::writeTable($sheet, $headers, $rows, 'A1', $columns);
        $this->autoSize($sheet, count($headers));

        return $spreadsheet;
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
        }

        if ($selectedKeys === []) {
            return $this->resolveColumns($type, '');
        }

        $selectedColumns = array_map(static function (string $key) use ($columnsByKey): array {
            $column = $columnsByKey[$key];
            $column['header'] = $column['label'];
            return $column;
        }, $selectedKeys);

        return $this->decorateColumns($selectedColumns);
    }

    private function parseColumnsCsv(?string $columnsCsv): array
    {
        $resolved = trim((string) $columnsCsv);
        if ($resolved === '') {
            return [];
        }

        $keys = array_map('trim', explode(',', $resolved));
        $keys = array_values(array_filter($keys, static fn(string $key): bool => $key !== ''));

        return array_values(array_unique($keys));
    }

    private function buildHeaders(array $columns): array
    {
        return array_map(static fn(array $column): string => $column['header'], $columns);
    }

    private function decorateColumns(array $columns): array
    {
        $displayNameMap = ColumnPolicyRequestHelper::displayNameMap($_REQUEST['column_display_name'] ?? null);
        $requirementPolicyMap = ColumnPolicyRequestHelper::requirementPolicyMap($_REQUEST['column_requirement_policy'] ?? null);

        return array_map(static function (array $column) use ($displayNameMap, $requirementPolicyMap): array {
            $label = ColumnPolicyRequestHelper::displayNameForColumn($column, $displayNameMap, (string) ($column['label'] ?? ''));
            $policy = ColumnPolicyRequestHelper::requirementPolicyForColumn(
                $column,
                $requirementPolicyMap,
                !empty($column['required']) ? 'required' : 'none'
            );

            return $column + [
                'label' => $label,
                'header' => $label,
                'required' => $policy === 'required',
                'requirement_policy' => $policy,
                'source_key' => $column['source_key'] ?? $column['key'],
                'payload_key' => $column['payload_key'] ?? $column['key'],
            ];
        }, $columns);
    }

    private function buildRow(array $source, array $columns): array
    {
        $row = [];
        foreach ($columns as $column) {
            $row[] = $this->exportCellValue($source, $column['key']);
        }

        return $row;
    }

    private function exportCellValue(array $source, string $key): mixed
    {
        $value = $source[$key] ?? '';

        return match ($key) {
            'normal_balance' => ($value === 'credit') ? '대변' : (($value === 'debit') ? '차변' : $value),
            'is_posting' => ((int) ($value ?? 0)) === 1 ? '가능' : (((string) $value === '가능') ? '가능' : '불가'),
            'is_active' => ((int) ($value ?? 0)) === 1 ? '사용' : (((string) $value === '사용') ? '사용' : '미사용'),
            default => $value,
        };
    }

    private function autoSize($sheet, int $columnCount): void
    {
        for ($index = 1; $index <= $columnCount; $index++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }
    }
}
