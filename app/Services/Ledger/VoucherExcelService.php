<?php

namespace App\Services\Ledger;

use App\Models\Ledger\VoucherModel;
use App\Services\System\DataTableColumnMetaService;
use Core\Helpers\ActorHelper;
use Core\Helpers\ColumnPolicyRequestHelper;
use Core\Helpers\ExcelValueFormatterHelper;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PDO;

class VoucherExcelService
{
    private VoucherModel $voucherModel;
    private DataTableColumnMetaService $metaService;

    public function __construct(private readonly PDO $pdo)
    {
        $this->voucherModel = new VoucherModel($pdo);
        $this->metaService = new DataTableColumnMetaService($pdo);
    }

    public function createTemplateSpreadsheet(?string $columnsCsv = null): Spreadsheet
    {
        $columns = $this->resolveColumns('template', $columnsCsv);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('전표입력 양식');

        $headers = $this->buildHeaders($columns);
        $rows = array_map(fn(array $row): array => $this->buildRow($row, $columns), $this->sampleRows());

        ExcelValueFormatterHelper::writeTable($sheet, $headers, $rows, 'A1', $columns, [
            'showRequiredAsterisk' => true,
        ]);
        $this->autoSize($sheet, count($headers));

        return $spreadsheet;
    }

    public function createExportSpreadsheet(?string $columnsCsv = null): Spreadsheet
    {
        $columns = $this->resolveColumns('download', $columnsCsv);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('전표입력 목록');

        $headers = $this->buildHeaders($columns);
        $rows = [];
        foreach (ExcelValueFormatterHelper::sortRowsBySortNo($this->voucherModel->getList([])) as $row) {
            $rows[] = $this->buildRow($this->decorateExportRow($row), $columns);
        }

        ExcelValueFormatterHelper::writeTable($sheet, $headers, $rows, 'A1', $columns);
        $this->autoSize($sheet, count($headers));

        return $spreadsheet;
    }

    public function importFromExcelFile(string $filePath): array
    {
        $columns = $this->resolveColumns('template', $this->requestString('excel_template_columns'));
        $rows = IOFactory::load($filePath)->getActiveSheet()->toArray(null, true, true, true);
        $headerMap = $this->headerMap(array_shift($rows) ?: []);

        $saved = 0;
        foreach ($rows as $rowIndex => $row) {
            $rowValues = [];
            $hasAnyValue = false;
            foreach ($columns as $column) {
                $value = $this->cell($row, $headerMap, $this->headerAliases($column));
                $rowValues[$column['key']] = $value;
                if (trim((string) $value) !== '') {
                    $hasAnyValue = true;
                }
            }

            if (!$hasAnyValue) {
                continue;
            }

            $voucher = $this->resolveTargetVoucher($rowValues);
            if (!$voucher) {
                $lineNo = $rowIndex + 2;
                throw new \RuntimeException("{$lineNo}행의 전표를 찾을 수 없습니다. ID 또는 전표번호를 확인해 주세요.");
            }

            if (!VoucherStatus::isDraft($voucher['status'] ?? null)) {
                $lineNo = $rowIndex + 2;
                throw new \RuntimeException("{$lineNo}행은 작성중 상태 전표만 수정할 수 있습니다.");
            }

            $payload = $this->buildImportPayload($rowValues);
            if ($payload === []) {
                continue;
            }

            $payload['updated_at'] = date('Y-m-d H:i:s');
            $payload['updated_by'] = ActorHelper::user();
            $this->voucherModel->update((string) $voucher['id'], $payload);
            $saved++;
        }

        return [
            'success' => true,
            'message' => "전표 {$saved}건이 업로드되었습니다.",
        ];
    }

    private function resolveColumns(string $type, ?string $columnsCsv = null): array
    {
        $columnsByKey = [];
        foreach ($this->columnDefinitions() as $column) {
            $columnsByKey[$column['key']] = $column;
        }

        $requestedKeys = $this->parseColumnsCsv($columnsCsv);
        $selectedKeys = [];
        if ($requestedKeys === []) {
            foreach ($this->columnDefinitions() as $column) {
                if (!empty($column['required']) || !empty($column[$type . '_default'])) {
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

    private function columnDefinitions(): array
    {
        $metaRows = $this->metaService->columnsForDomain('voucher-header');
        $templateDefaults = [
            'id',
            'voucher_no',
            'voucher_date',
            'summary',
            'reject_reason',
        ];
        $downloadDefaults = [
            'sort_no',
            'voucher_no',
            'voucher_date',
            'status',
            'summary',
            'reject_reason',
            'created_at',
            'created_by',
            'updated_at',
            'updated_by',
        ];

        $columns = [];
        foreach ($metaRows as $meta) {
            $key = trim((string) ($meta['column'] ?? ''));
            if ($key === '') {
                continue;
            }
            $columns[] = [
                'key' => $key,
                'label' => (string) ($meta['label'] ?? $key),
                'required' => in_array($key, ['voucher_no', 'voucher_date'], true),
                'template_default' => in_array($key, $templateDefaults, true),
                'download_default' => in_array($key, $downloadDefaults, true),
            ];
        }

        return $columns;
    }

    private function decorateColumns(array $columns): array
    {
        $displayName = ColumnPolicyRequestHelper::displayNameMap(
            $this->requestString('excel_' . (str_contains($this->requestString('excel_template_columns'), ',') ? 'template' : 'download') . '_display_name')
        );
        $requirementMap = ColumnPolicyRequestHelper::requirementPolicyMap(
            $this->requestString('excel_template_requirement')
        );

        return array_map(static function (array $column) use ($displayName, $requirementMap): array {
            $key = (string) ($column['key'] ?? '');
            $column['header'] = $displayName[$key] ?? $column['label'];
            if (isset($requirementMap[$key])) {
                $column['required'] = $requirementMap[$key] === 'required';
            }
            return $column;
        }, $columns);
    }

    private function buildHeaders(array $columns): array
    {
        return array_map(static fn(array $column): string => (string) ($column['header'] ?? $column['label'] ?? $column['key']), $columns);
    }

    private function buildRow(array $row, array $columns): array
    {
        $result = [];
        foreach ($columns as $column) {
            $result[] = $row[$column['key']] ?? '';
        }
        return $result;
    }

    private function sampleRows(): array
    {
        return [[
            'id' => '',
            'sort_no' => '',
            'voucher_no' => '20260707-0001',
            'voucher_date' => date('Y-m-d'),
            'status' => VoucherStatus::DRAFT,
            'summary' => '전표 적요 예시',
            'reject_reason' => '',
        ]];
    }

    private function decorateExportRow(array $row): array
    {
        $row['created_by'] = $row['created_by_name'] ?? ($row['created_by'] ?? '');
        $row['updated_by'] = $row['updated_by_name'] ?? ($row['updated_by'] ?? '');
        $row['deleted_by'] = $row['deleted_by_name'] ?? ($row['deleted_by'] ?? '');
        $row['summary_account_id'] = $row['summary_account_name'] ?? ($row['summary_account_id'] ?? '');
        $row['summary_client_id'] = $row['summary_client_name'] ?? ($row['summary_client_id'] ?? '');
        $row['summary_project_id'] = $row['summary_project_name'] ?? ($row['summary_project_id'] ?? '');
        $row['summary_bank_account_id'] = $row['summary_bank_account_name'] ?? ($row['summary_bank_account_id'] ?? '');
        $row['summary_card_id'] = $row['summary_card_name'] ?? ($row['summary_card_id'] ?? '');
        $row['summary_employee_id'] = $row['summary_employee_name'] ?? ($row['summary_employee_id'] ?? '');
        return $row;
    }

    private function buildImportPayload(array $rowValues): array
    {
        $payload = [];
        foreach (['voucher_no', 'voucher_date', 'summary', 'reject_reason'] as $field) {
            if (!array_key_exists($field, $rowValues)) {
                continue;
            }
            $value = trim((string) ($rowValues[$field] ?? ''));
            if ($field === 'voucher_date' && $value === '') {
                continue;
            }
            $payload[$field] = $value === '' ? null : $value;
        }

        return $payload;
    }

    private function resolveTargetVoucher(array $rowValues): ?array
    {
        $id = trim((string) ($rowValues['id'] ?? ''));
        if ($id !== '') {
            return $this->voucherModel->getById($id);
        }

        $voucherNo = trim((string) ($rowValues['voucher_no'] ?? ''));
        if ($voucherNo !== '') {
            return $this->voucherModel->getByVoucherNo($voucherNo);
        }

        return null;
    }

    private function parseColumnsCsv(?string $csv): array
    {
        if ($csv === null || trim($csv) === '') {
            return [];
        }
        return array_values(array_filter(array_map(static fn(string $item): string => trim($item), explode(',', $csv))));
    }

    private function requestString(string $key): string
    {
        return trim((string) ($_REQUEST[$key] ?? ''));
    }

    private function headerMap(array $headerRow): array
    {
        $headerMap = [];
        foreach ($headerRow as $column => $label) {
            $normalized = trim((string) $label);
            if ($normalized !== '') {
                $headerMap[$normalized] = $column;
            }
        }
        return $headerMap;
    }

    private function hasHeader(array $headerMap, array $column): bool
    {
        foreach ($this->headerAliases($column) as $header) {
            if (isset($headerMap[$header])) {
                return true;
            }
        }
        return false;
    }

    private function headerAliases(array $column): array
    {
        return array_values(array_unique(array_filter([
            $column['header'] ?? null,
            $column['label'] ?? null,
            $column['key'] ?? null,
        ])));
    }

    private function cell(array $row, array $headerMap, array $headers): string
    {
        foreach ($headers as $header) {
            if (isset($headerMap[$header])) {
                return trim((string) ($row[$headerMap[$header]] ?? ''));
            }
        }
        return '';
    }

    private function autoSize($sheet, int $columnCount): void
    {
        for ($index = 1; $index <= $columnCount; $index++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }
    }
}
