<?php

namespace App\Services\Ledger;

use Core\Helpers\ColumnPolicyRequestHelper;
use Core\Helpers\ExcelValueFormatterHelper;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PDO;

class JournalRuleExcelService
{
    private const COLUMN_DEFINITIONS = [
        ['key' => 'rule_code', 'label' => '규칙코드', 'required' => true, 'template_default' => true, 'download_default' => true],
        ['key' => 'rule_name', 'label' => '규칙명', 'required' => true, 'template_default' => true, 'download_default' => true],
        ['key' => 'business_unit', 'label' => '사업구분', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'transaction_type', 'label' => '거래유형', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'transaction_direction', 'label' => '거래구분', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'client_type', 'label' => '거래처구분', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'import_type', 'label' => '자료유형', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'debit_account_code', 'label' => '차변계정', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'credit_account_code', 'label' => '대변계정', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'vat_account_code', 'label' => '부가세계정', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'is_active', 'label' => '사용여부', 'required' => false, 'template_default' => true, 'download_default' => true],
        ['key' => 'description', 'label' => '설명/적요', 'required' => false, 'template_default' => true, 'download_default' => true],
    ];

    private const SAMPLE_ROWS = [
        [
            'rule_code' => 'CONST_MATERIAL_TAX',
            'rule_name' => '공사 재료비 매입',
            'business_unit' => 'CONSTRUCTION',
            'transaction_type' => 'GENERAL',
            'transaction_direction' => 'PURCHASE',
            'client_type' => 'SUPPLIER',
            'import_type' => 'TAX_INVOICE',
            'debit_account_code' => '5100',
            'credit_account_code' => '2100',
            'vat_account_code' => '1350',
            'is_active' => '사용',
            'description' => '공사-구매재료 / 미지급금',
        ],
        [
            'rule_code' => 'CONST_LABOR_TAX',
            'rule_name' => '공사 노무비 매입',
            'business_unit' => 'CONSTRUCTION',
            'transaction_type' => 'GENERAL',
            'transaction_direction' => 'PURCHASE',
            'client_type' => 'SUPPLIER',
            'import_type' => 'TAX_INVOICE',
            'debit_account_code' => '5200',
            'credit_account_code' => '2100',
            'vat_account_code' => '',
            'is_active' => '사용',
            'description' => '공사-노무비 / 미지급금',
        ],
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly JournalRuleService $service
    ) {
    }

    public function createTemplateSpreadsheet(?string $columnsCsv = null): Spreadsheet
    {
        $columns = $this->resolveColumns('template', $columnsCsv);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('분개규칙 양식');

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
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('분개규칙 목록');

        $headers = $this->buildHeaders($columns);
        $rows = [];
        foreach (ExcelValueFormatterHelper::sortRowsBySortNo($this->service->getList([])) as $row) {
            $rows[] = $this->buildRow($row, $columns);
        }

        ExcelValueFormatterHelper::writeTable($sheet, $headers, $rows, 'A1', $columns);
        $this->autoSize($sheet, count($headers));

        return $spreadsheet;
    }

    public function importFromExcelFile(string $filePath): array
    {
        $columns = $this->resolveColumns('template', $_REQUEST['excel_template_columns'] ?? null);
        $requiredColumns = array_values(array_filter($columns, static fn(array $column): bool => !empty($column['required'])));

        $rows = IOFactory::load($filePath)->getActiveSheet()->toArray(null, true, true, true);
        $headerMap = $this->headerMap(array_shift($rows) ?: []);
        $missingHeaders = [];

        foreach ($requiredColumns as $column) {
            if (!$this->hasHeader($headerMap, $column)) {
                $missingHeaders[] = (string) ($column['header'] ?? $column['label'] ?? $column['key']);
            }
        }

        if ($missingHeaders !== []) {
            throw new \RuntimeException('필수 컬럼이 누락되었습니다. ' . implode(', ', $missingHeaders));
        }

        $saved = 0;

        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 2;
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

            $missingRequired = [];
            foreach ($requiredColumns as $column) {
                $value = $rowValues[$column['key']] ?? '';
                if (trim((string) $value) === '') {
                    $missingRequired[] = (string) ($column['header'] ?? $column['label'] ?? $column['key']);
                }
            }

            if ($missingRequired !== []) {
                throw new \RuntimeException("{$rowNumber}행 필수값이 누락되었습니다. " . implode(', ', $missingRequired));
            }

            $ruleCode = strtoupper(trim((string) ($rowValues['rule_code'] ?? '')));
            if ($ruleCode === '') {
                continue;
            }

            $payload = [
                'rule_code' => $ruleCode,
                'rule_name' => trim((string) ($rowValues['rule_name'] ?? '')),
                'business_unit' => $this->resolveCode('BUSINESS_UNIT', (string) ($rowValues['business_unit'] ?? '')),
                'transaction_type' => $this->resolveCode('TRANSACTION_TYPE', (string) ($rowValues['transaction_type'] ?? '')),
                'transaction_direction' => $this->resolveCode('TRANSACTION_DIRECTION', (string) ($rowValues['transaction_direction'] ?? '')),
                'client_type' => $this->resolveCode('CLIENT_TYPE', (string) ($rowValues['client_type'] ?? '')),
                'import_type' => $this->resolveCode('IMPORT_TYPE', (string) ($rowValues['import_type'] ?? '')),
                'debit_account_id' => $this->resolveAccountId((string) ($rowValues['debit_account_code'] ?? '')),
                'credit_account_id' => $this->resolveAccountId((string) ($rowValues['credit_account_code'] ?? '')),
                'vat_account_id' => $this->resolveAccountId((string) ($rowValues['vat_account_code'] ?? ''), false),
                'is_active' => $this->truthy($rowValues['is_active'] ?? '') ? '1' : '0',
                'description' => trim((string) ($rowValues['description'] ?? '')),
            ];

            $existing = $this->service->getList([['field' => 'rule_code', 'value' => $ruleCode]]);
            if (!empty($existing[0]['id'])) {
                $payload['id'] = $existing[0]['id'];
            }

            $result = $this->service->save($payload);
            if (empty($result['success'])) {
                throw new \RuntimeException((string) ($result['message'] ?? "{$ruleCode} 저장에 실패했습니다."));
            }

            $saved++;
        }

        return [
            'success' => true,
            'message' => "분개규칙 {$saved}건이 업로드되었습니다.",
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

        if ($key === 'is_active') {
            return ((int) ($value ?? 0)) === 1 ? '사용' : (((string) $value === '사용') ? '사용' : '미사용');
        }

        return $value;
    }

    private function autoSize($sheet, int $columnCount): void
    {
        for ($index = 1; $index <= $columnCount; $index++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }
    }

    private function headerMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $column => $label) {
            $key = trim((string) $label);
            if ($key !== '') {
                $map[$key] = $column;
            }
        }

        return $map;
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
        $aliases = [
            (string) ($column['header'] ?? ''),
            (string) ($column['label'] ?? ''),
            (string) ($column['key'] ?? ''),
        ];

        return array_values(array_unique(array_filter(array_map('trim', $aliases), static fn(string $value): bool => $value !== '')));
    }

    private function cell(array $row, array $headerMap, array $names): mixed
    {
        foreach ($names as $name) {
            if (isset($headerMap[$name])) {
                return $row[$headerMap[$name]] ?? '';
            }
        }

        return '';
    }

    private function resolveCode(string $group, string $value): string
    {
        $raw = trim($value);
        $upper = strtoupper($raw);
        if ($upper === '') {
            return '';
        }

        $stmt = $this->pdo->prepare("
            SELECT code
            FROM system_codes
            WHERE deleted_at IS NULL
              AND is_active = 1
              AND code_group = :code_group
              AND (UPPER(code) = :upper OR code_name = :raw)
            LIMIT 1
        ");
        $stmt->execute([
            ':code_group' => $group,
            ':upper' => $upper,
            ':raw' => $raw,
        ]);

        return (string) ($stmt->fetchColumn() ?: $upper);
    }

    private function resolveAccountId(string $value, bool $required = true): ?string
    {
        $value = trim($value);
        if ($value === '') {
            if ($required) {
                throw new \InvalidArgumentException('계정은 필수입니다.');
            }
            return null;
        }

        $code = preg_split('/\s+/', $value)[0] ?? $value;
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM ledger_accounts
            WHERE deleted_at IS NULL
              AND (id = :id_value OR account_code = :code OR account_name = :name_value)
            LIMIT 1
        ");
        $stmt->execute([
            ':id_value' => $value,
            ':code' => $code,
            ':name_value' => $value,
        ]);

        $id = $stmt->fetchColumn();
        if (!$id && $required) {
            throw new \InvalidArgumentException("계정을 찾을 수 없습니다: {$value}");
        }

        return $id ? (string) $id : null;
    }

    private function truthy(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'y', 'yes', '사용', 'active'], true);
    }
}
