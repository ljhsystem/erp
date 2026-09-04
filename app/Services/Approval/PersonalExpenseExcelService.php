<?php

namespace App\Services\Approval;

use Core\Helpers\ColumnPolicyRequestHelper;
use Core\Helpers\ExcelValueFormatterHelper;
use Core\LoggerFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Psr\Log\LoggerInterface;

class PersonalExpenseExcelService
{
    private LoggerInterface $logger;
    private const COLUMNS = [
        ['key' => 'sort_no', 'label' => '순번', 'required' => false],
        ['key' => 'expense_date', 'label' => '지출일자', 'required' => true],
        ['key' => 'expense_category', 'label' => '경비구분 코드', 'required' => false],
        ['key' => 'payment_method', 'label' => '지출수단 코드', 'required' => true],
        ['key' => 'receipt_type', 'label' => '증빙종류 코드', 'required' => false],
        ['key' => 'project_id', 'label' => '프로젝트 ID', 'required' => false],
        ['key' => 'client_id', 'label' => '거래처 ID', 'required' => false],
        ['key' => 'merchant_name', 'label' => '가맹점명', 'required' => true],
        ['key' => 'merchant_business_no', 'label' => '사업자등록번호', 'required' => false],
        ['key' => 'merchant_representative', 'label' => '대표자', 'required' => false],
        ['key' => 'merchant_address', 'label' => '기본주소', 'required' => false],
        ['key' => 'merchant_address_detail', 'label' => '상세주소', 'required' => false],
        ['key' => 'merchant_phone', 'label' => '전화번호', 'required' => false],
        ['key' => 'item_name', 'label' => '품명', 'required' => true],
        ['key' => 'item_specification', 'label' => '규격', 'required' => false],
        ['key' => 'item_unit_name', 'label' => '단위', 'required' => false],
        ['key' => 'item_quantity', 'label' => '수량', 'required' => true],
        ['key' => 'item_unit_price', 'label' => '단가', 'required' => true],
        ['key' => 'item_supply_amount', 'label' => '공급가액', 'required' => false],
        ['key' => 'item_vat_amount', 'label' => '부가세', 'required' => true],
        ['key' => 'item_total_amount', 'label' => '합계금액', 'required' => false],
        ['key' => 'item_description', 'label' => '품목 비고', 'required' => false],
        ['key' => 'item_memo', 'label' => '품목 메모', 'required' => false],
    ];

    public function __construct(private readonly PersonalExpenseService $expenseService)
    {
        $this->logger = LoggerFactory::getLogger('service-approval-personal-expense-excel');
    }

    public function createTemplate(?string $columnsCsv = null): Spreadsheet
    {
        return $this->logged('PERSONAL_EXPENSE_EXCEL_TEMPLATE', 'template', [], fn(): Spreadsheet => $this->createTemplateInternal($columnsCsv));
    }

    private function createTemplateInternal(?string $columnsCsv = null): Spreadsheet
    {
        $columns = $this->columns('template', $columnsCsv);
        return $this->spreadsheet('개인경비 아이템 양식', $columns, [[
            'sort_no' => 1,
            'expense_date' => date('Y-m-d'),
            'payment_method' => 'PERSONAL_CARD',
            'merchant_name' => '신규 가맹점명',
            'item_name' => '품목명',
            'item_quantity' => 1,
            'item_unit_price' => 10000,
            'item_supply_amount' => 10000,
            'item_vat_amount' => 1000,
            'item_total_amount' => 11000,
        ]], true);
    }

    public function createDownload(array $rows, ?string $columnsCsv = null): Spreadsheet
    {
        return $this->logged('PERSONAL_EXPENSE_EXCEL_DOWNLOAD', 'download', ['row_count' => count($rows)], fn(): Spreadsheet => $this->createDownloadInternal($rows, $columnsCsv));
    }

    private function createDownloadInternal(array $rows, ?string $columnsCsv = null): Spreadsheet
    {
        $columns = $this->columns('download', $columnsCsv);
        $normalized = [];
        foreach (array_values($rows) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            unset($row['id'], $row['personal_expense_id'], $row['created_at'], $row['created_by'], $row['updated_at'], $row['updated_by']);
            $row['sort_no'] = $index + 1;
            $normalized[] = $row;
        }
        return $this->spreadsheet('개인경비 아이템', $columns, $normalized, false);
    }

    public function import(string $filePath, ?string $documentId = null): array
    {
        return $this->logged('PERSONAL_EXPENSE_EXCEL_IMPORT', 'import', ['document_id' => $documentId], fn(): array => $this->importInternal($filePath, $documentId));
    }

    private function importInternal(string $filePath, ?string $documentId = null): array
    {
        if ($documentId !== null && trim($documentId) !== '') {
            $this->expenseService->assertExcelEditable(trim($documentId));
        }
        $columns = $this->columns('template', $this->requestString('excel_template_columns'));
        $required = array_values(array_filter($columns, static fn(array $column): bool => !empty($column['required'])));
        $sheetRows = IOFactory::load($filePath)->getActiveSheet()->toArray(null, true, true, true);
        $headerMap = $this->headerMap(array_shift($sheetRows) ?: []);
        $missing = [];
        foreach ($required as $column) {
            if (!$this->hasHeader($headerMap, $column)) {
                $missing[] = (string) $column['header'];
            }
        }
        if ($missing !== []) {
            throw new \RuntimeException('필수 컬럼이 누락되었습니다. ' . implode(', ', $missing));
        }

        $rows = [];
        foreach ($sheetRows as $rowIndex => $sheetRow) {
            $values = [];
            $hasValue = false;
            foreach ($columns as $column) {
                $value = $this->cell($sheetRow, $headerMap, $column);
                $values[$column['key']] = $value;
                $hasValue = $hasValue || trim((string) $value) !== '';
            }
            if (!$hasValue) {
                continue;
            }
            foreach ($required as $column) {
                if (trim((string) ($values[$column['key']] ?? '')) === '') {
                    throw new \RuntimeException(($rowIndex + 2) . '행의 필수값이 누락되었습니다. ' . $column['header']);
                }
            }
            unset($values['id'], $values['personal_expense_id']);
            $rows[] = $values;
        }
        if ($rows === []) {
            throw new \RuntimeException('업로드할 개인경비 아이템이 없습니다.');
        }

        $validated = $this->expenseService->validateExcelItems($rows);
        return [
            'success' => true,
            'message' => '개인경비 아이템 ' . count($validated) . '건을 불러왔습니다.',
            'data' => ['rows' => $validated, 'count' => count($validated)],
        ];
    }

    private function logged(string $eventCode, string $action, array $context, callable $operation): mixed
    {
        try {
            $result = $operation();
            $this->logger->info('개인경비 엑셀 처리를 완료했습니다.', ['event_code' => $eventCode, 'result' => 'SUCCESS', 'action' => $action] + $context);
            return $result;
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            $this->logger->warning('개인경비 엑셀 처리가 차단되었습니다.', ['event_code' => $eventCode . '_BLOCKED', 'result' => 'BLOCKED', 'action' => $action, 'error_code' => get_class($exception), 'error' => $exception] + $context);
            throw $exception;
        } catch (\Throwable $exception) {
            $this->logger->error('개인경비 엑셀 처리에 실패했습니다.', ['event_code' => $eventCode . '_FAILED', 'result' => 'FAILED', 'action' => $action, 'error_code' => get_class($exception), 'error' => $exception] + $context);
            throw $exception;
        }
    }

    private function columns(string $type, ?string $columnsCsv): array
    {
        $byKey = [];
        foreach (self::COLUMNS as $definition) {
            $byKey[$definition['key']] = $definition;
        }
        $requested = array_values(array_filter(array_map('trim', explode(',', (string) $columnsCsv))));
        $keys = $requested === [] ? array_keys($byKey) : array_values(array_filter($requested, static fn(string $key): bool => isset($byKey[$key])));
        if ($keys === []) {
            $keys = array_keys($byKey);
        }
        $displayNames = ColumnPolicyRequestHelper::displayNameMap($this->requestValue('column_display_name'));
        $requirements = ColumnPolicyRequestHelper::requirementPolicyMap($this->requestValue('column_requirement_policy'));
        return array_map(static function (string $key) use ($byKey, $displayNames, $requirements, $type): array {
            $column = $byKey[$key];
            $label = ColumnPolicyRequestHelper::displayNameForColumn($column, $displayNames, $column['label']);
            $required = $type === 'template'
                && ColumnPolicyRequestHelper::requirementPolicyForColumn($column, $requirements, !empty($column['required']) ? 'required' : 'none') === 'required';
            return array_merge($column, ['header' => $label, 'required' => $required, 'source_key' => $key]);
        }, $keys);
    }

    private function spreadsheet(string $title, array $columns, array $rows, bool $requiredAsterisk): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($title);
        $headers = array_column($columns, 'header');
        $body = [];
        foreach ($rows as $row) {
            $body[] = array_map(static fn(array $column): mixed => $row[$column['key']] ?? '', $columns);
        }
        ExcelValueFormatterHelper::writeTable($sheet, $headers, $body, 'A1', $columns, [
            'showRequiredAsterisk' => $requiredAsterisk,
        ]);
        for ($index = 1; $index <= count($headers); $index++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }
        return $spreadsheet;
    }

    private function headerMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $coordinate => $value) {
            $name = preg_replace('/\s*\*+\s*$/u', '', trim((string) $value)) ?? '';
            if ($name !== '') {
                $map[$name] = $coordinate;
            }
        }
        return $map;
    }

    private function hasHeader(array $headerMap, array $column): bool
    {
        return isset($headerMap[$column['header']]) || isset($headerMap[$column['label']]) || isset($headerMap[$column['key']]);
    }

    private function cell(array $row, array $headerMap, array $column): mixed
    {
        foreach ([$column['header'], $column['label'], $column['key']] as $alias) {
            if (isset($headerMap[$alias])) {
                return $row[$headerMap[$alias]] ?? null;
            }
        }
        return null;
    }

    private function requestValue(string $key): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? null;
    }

    private function requestString(string $key): ?string
    {
        $value = $this->requestValue($key);
        return is_scalar($value) ? trim((string) $value) : null;
    }
}
