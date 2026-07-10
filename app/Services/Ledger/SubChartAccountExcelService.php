<?php

namespace App\Services\Ledger;

use App\Models\Ledger\SubChartAccountModel;
use Core\Helpers\ColumnPolicyRequestHelper;
use Core\Helpers\ExcelValueFormatterHelper;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class SubChartAccountExcelService
{
    private const COLUMN_DEFINITIONS = [
        ['key' => 'account_code', 'label' => '계정코드', 'required' => true, 'template_default' => true, 'download_default' => true],
        ['key' => 'account_name', 'label' => '계정과목명', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'sub_code', 'label' => '보조계정코드', 'required' => true, 'template_default' => true, 'download_default' => true],
        ['key' => 'sub_name', 'label' => '보조계정 대상', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'is_required', 'label' => '필수구분', 'required' => false, 'template_default' => true, 'download_default' => true],
    ];

    private const SAMPLE_ROWS = [
        [
            'account_code' => '1100',
            'account_name' => '보통예금',
            'sub_code' => 'PROJECT',
            'sub_name' => '프로젝트',
            'is_required' => '필수',
        ],
        [
            'account_code' => '5100',
            'account_name' => '외주비',
            'sub_code' => 'CLIENT',
            'sub_name' => '거래처',
            'is_required' => '선택',
        ],
    ];

    public function __construct(
        private readonly CustomSubAccountService $service,
        private readonly ChartAccountService $accountService,
        private readonly SubChartAccountModel $model
    ) {
    }

    public function createTemplateSpreadsheet(?string $columnsCsv = null): Spreadsheet
    {
        $columns = $this->resolveColumns('template', $columnsCsv);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('보조계정 업로드');

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
        $rows = ExcelValueFormatterHelper::sortRowsBySortNo($this->model->getAllWithAccounts());

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('보조계정 목록');

        $headers = $this->buildHeaders($columns);
        $body = array_map(fn(array $row): array => $this->buildRow($row, $columns), $rows);

        ExcelValueFormatterHelper::writeTable($sheet, $headers, $body, 'A1', $columns);
        $this->autoSize($sheet, count($headers));

        return $spreadsheet;
    }

    public function saveFromExcelFile(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray('', true, true, false);
        if (count($rows) < 2) {
            return ['success' => false, 'message' => '업로드할 데이터가 없습니다.'];
        }

        $headerMap = $this->buildHeaderMap((array) array_shift($rows));
        $saved = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $lineNo = $index + 2;
            $accountCode = trim((string) ($row[$headerMap['account_code'] ?? -1] ?? ''));
            $subCode = strtoupper(trim((string) ($row[$headerMap['sub_code'] ?? -1] ?? '')));
            $requiredRaw = trim((string) ($row[$headerMap['is_required'] ?? -1] ?? ''));

            if ($accountCode === '' && $subCode === '') {
                continue;
            }

            if ($accountCode === '' || $subCode === '') {
                $errors[] = $lineNo . '행: 계정코드와 보조계정코드는 필수입니다.';
                continue;
            }

            $account = $this->accountService->findByCode($accountCode);
            if (!$account || empty($account['id'])) {
                $errors[] = $lineNo . '행: 계정코드를 찾을 수 없습니다.';
                continue;
            }

            $accountId = (string) $account['id'];
            $isRequired = $this->parseRequiredValue($requiredRaw);
            $existing = $this->model->findByAccountAndSubCode($accountId, $subCode);

            $result = $existing
                ? $this->service->update((string) ($existing['id'] ?? ''), [
                    'sub_code' => $subCode,
                    'is_required' => $isRequired,
                ])
                : $this->service->create([
                    'account_id' => $accountId,
                    'sub_code' => $subCode,
                    'is_required' => $isRequired,
                ]);

            if (!($result['success'] ?? false)) {
                $errors[] = $lineNo . '행: ' . ($result['message'] ?? '저장 중 오류가 발생했습니다.');
                continue;
            }

            $saved++;
        }

        return [
            'success' => $saved > 0 && $errors === [],
            'message' => $errors === []
                ? '보조계정 엑셀 업로드가 완료되었습니다.'
                : '일부 보조계정 엑셀 업로드에 실패했습니다.',
            'saved_count' => $saved,
            'error_count' => count($errors),
            'errors' => $errors,
        ];
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

        return array_values(array_unique(array_filter(array_map('trim', explode(',', $resolved)))));
    }

    private function buildHeaders(array $columns): array
    {
        return array_map(static fn(array $column): string => (string) $column['header'], $columns);
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
            $row[] = $this->exportCellValue($source, (string) $column['key']);
        }

        return $row;
    }

    private function exportCellValue(array $source, string $key): mixed
    {
        $value = $source[$key] ?? '';

        if ($key === 'is_required') {
            return (int) ($value ?? 0) === 1 ? '필수' : '선택';
        }

        return $value;
    }

    private function buildHeaderMap(array $headers): array
    {
        $map = [];
        $aliases = [
            '계정코드' => 'account_code',
            'account_code' => 'account_code',
            '계정과목명' => 'account_name',
            'account_name' => 'account_name',
            '보조계정코드' => 'sub_code',
            'sub_code' => 'sub_code',
            '보조계정 대상' => 'sub_name',
            'sub_name' => 'sub_name',
            '필수구분' => 'is_required',
            'is_required' => 'is_required',
        ];

        foreach ($headers as $index => $header) {
            $normalized = trim((string) $header);
            if ($normalized !== '' && isset($aliases[$normalized])) {
                $map[$aliases[$normalized]] = $index;
            }
        }

        return $map;
    }

    private function parseRequiredValue(string $value): int
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');
        if (in_array($normalized, ['1', 'y', 'yes', 'true', '필수', 'required'], true)) {
            return 1;
        }

        return 0;
    }

    private function autoSize($sheet, int $columnCount): void
    {
        for ($index = 1; $index <= $columnCount; $index++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }
    }
}
