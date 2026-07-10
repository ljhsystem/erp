<?php

namespace App\Services\Ledger;

use Core\Helpers\ColumnPolicyRequestHelper;
use Core\Helpers\ExcelValueFormatterHelper;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PDO;

class TransactionExcelService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly TransactionCrudService $crudService
    ) {
    }

    public function createTemplateSpreadsheet(?string $columnsCsv = null): Spreadsheet
    {
        $columns = $this->resolveColumns('template', $columnsCsv);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('거래입력 양식');

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
        $sheet->setTitle('거래입력 목록');

        $headers = $this->buildHeaders($columns);
        $rows = [];
        foreach (ExcelValueFormatterHelper::sortRowsBySortNo($this->crudService->getList([])) as $row) {
            $rows[] = $this->buildRow($row, $columns);
        }

        ExcelValueFormatterHelper::writeTable($sheet, $headers, $rows, 'A1', $columns);
        $this->autoSize($sheet, count($headers));

        return $spreadsheet;
    }

    public function importFromExcelFile(string $filePath): array
    {
        $columns = $this->resolveColumns('template', $this->requestString('excel_template_columns'));
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

            $payload = $this->buildImportPayload($rowValues);
            $result = $this->crudService->save($payload, []);
            if (empty($result['success'])) {
                throw new \RuntimeException((string) ($result['message'] ?? "{$rowNumber}행 저장 중 오류가 발생했습니다."));
            }

            $saved++;
        }

        return [
            'success' => true,
            'message' => "거래 {$saved}건이 업로드되었습니다.",
        ];
    }

    public function createItemTemplateSpreadsheet(?string $columnsCsv = null): Spreadsheet
    {
        return $this->createGridTemplateSpreadsheet(
            '거래품목 업로드',
            $this->resolveItemColumns('template', $columnsCsv),
            $this->sampleItemRows()
        );
    }

    public function createItemExportSpreadsheet(array $rows = [], ?string $columnsCsv = null): Spreadsheet
    {
        $columns = $this->resolveItemColumns('download', $columnsCsv);
        $normalizedRows = array_map([$this, 'normalizeItemExportRow'], is_array($rows) ? $rows : []);

        return $this->createGridExportSpreadsheet('거래품목', $columns, $normalizedRows);
    }

    public function importItemsFromExcelFile(string $filePath): array
    {
        return $this->importGridRowsFromExcelFile(
            $filePath,
            $this->resolveItemColumns('template', $this->requestString('excel_template_columns')),
            [$this, 'buildItemImportRow'],
            '거래품목'
        );
    }

    public function createSettlementTemplateSpreadsheet(?string $columnsCsv = null): Spreadsheet
    {
        return $this->createGridTemplateSpreadsheet(
            '거래정산 업로드',
            $this->resolveSettlementColumns('template', $columnsCsv),
            $this->sampleSettlementRows()
        );
    }

    public function createSettlementExportSpreadsheet(array $rows = [], ?string $columnsCsv = null): Spreadsheet
    {
        $columns = $this->resolveSettlementColumns('download', $columnsCsv);
        $normalizedRows = array_map([$this, 'normalizeSettlementExportRow'], is_array($rows) ? $rows : []);

        return $this->createGridExportSpreadsheet('거래정산', $columns, $normalizedRows);
    }

    public function importSettlementsFromExcelFile(string $filePath): array
    {
        return $this->importGridRowsFromExcelFile(
            $filePath,
            $this->resolveSettlementColumns('template', $this->requestString('excel_template_columns')),
            [$this, 'buildSettlementImportRow'],
            '거래정산'
        );
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
        $columns = [
            ['key' => 'transaction_date', 'label' => '거래일자', 'required' => true, 'template_default' => true, 'download_default' => true],
            ['key' => 'business_unit', 'label' => '사업구분', 'required' => true, 'template_default' => true, 'download_default' => true],
            ['key' => 'transaction_direction', 'label' => '거래구분', 'required' => false, 'template_default' => true, 'download_default' => true],
            ['key' => 'currency', 'label' => '통화', 'required' => false, 'template_default' => true, 'download_default' => true],
            ['key' => 'transaction_exchange_rate', 'label' => '환율', 'required' => false, 'template_default' => true, 'download_default' => true],
            ['key' => 'client_id', 'label' => '거래처', 'required' => false, 'template_default' => true, 'download_default' => true],
            ['key' => 'project_id', 'label' => '프로젝트', 'required' => false, 'template_default' => true, 'download_default' => true],
            ['key' => 'bank_account_id', 'label' => '계좌', 'required' => false, 'template_default' => true, 'download_default' => true],
            ['key' => 'card_id', 'label' => '카드', 'required' => false, 'template_default' => true, 'download_default' => true],
            ['key' => 'team_id', 'label' => '팀', 'required' => false, 'template_default' => true, 'download_default' => true],
            ['key' => 'employee_id', 'label' => '직원', 'required' => false, 'template_default' => true, 'download_default' => true],
            ['key' => 'transaction_foreign_amount', 'label' => '외화금액', 'required' => false, 'template_default' => true, 'download_default' => true],
            ['key' => 'transaction_supply_amount', 'label' => '공급가액', 'required' => false, 'template_default' => true, 'download_default' => true],
            ['key' => 'transaction_settlement_amount', 'label' => '정산금액', 'required' => false, 'template_default' => true, 'download_default' => true],
            ['key' => 'transaction_final_amount', 'label' => '최종금액', 'required' => true, 'template_default' => true, 'download_default' => true],
            ['key' => 'transaction_description', 'label' => '적요', 'required' => false, 'template_default' => true, 'download_default' => true],
            ['key' => 'transaction_note', 'label' => '비고', 'required' => false, 'template_default' => true, 'download_default' => true],
            ['key' => 'transaction_memo', 'label' => '메모', 'required' => false, 'template_default' => true, 'download_default' => true],
            ['key' => 'status', 'label' => '상태', 'required' => false, 'template_default' => false, 'download_default' => true],
        ];

        if ($this->tableColumnExists('ledger_transactions', 'operation_type')) {
            array_splice($columns, 3, 0, [[
                'key' => 'operation_type',
                'label' => '업무유형',
                'required' => false,
                'template_default' => true,
                'download_default' => true,
            ]]);
        }

        return $columns;
    }

    private function itemColumnDefinitions(): array
    {
        return $this->fullItemColumnDefinitions();

        return [
            ['key' => 'item_date', 'label' => '품목일자', 'required' => true, 'template_default' => true, 'download_default' => true],
            ['key' => 'item_name', 'label' => '품목명', 'required' => true, 'template_default' => true, 'download_default' => true],
            ['key' => 'specification', 'label' => '규격', 'required' => false, 'template_default' => true, 'download_default' => true, 'source_key' => 'item_specification'],
            ['key' => 'unit_name', 'label' => '단위', 'required' => false, 'template_default' => true, 'download_default' => true, 'source_key' => 'item_unit_name'],
            ['key' => 'quantity', 'label' => '수량', 'required' => false, 'template_default' => true, 'download_default' => true, 'source_key' => 'item_quantity'],
            ['key' => 'unit_price', 'label' => '단가', 'required' => false, 'template_default' => true, 'download_default' => true, 'source_key' => 'item_unit_price'],
            ['key' => 'foreign_unit_price', 'label' => '외화단가', 'required' => false, 'template_default' => true, 'download_default' => true, 'source_key' => 'item_foreign_unit_price'],
            ['key' => 'foreign_amount', 'label' => '외화금액', 'required' => false, 'template_default' => true, 'download_default' => true, 'source_key' => 'item_foreign_amount'],
            ['key' => 'amount', 'label' => '공급가액', 'required' => false, 'template_default' => true, 'download_default' => true, 'source_key' => 'item_supply_amount'],
            ['key' => 'tax_type', 'label' => '세금구분', 'required' => false, 'template_default' => true, 'download_default' => true, 'source_key' => 'item_tax_type'],
            ['key' => 'description', 'label' => '적요', 'required' => false, 'template_default' => true, 'download_default' => true, 'source_key' => 'item_description'],
        ];
    }

    private function settlementColumnDefinitions(): array
    {
        return $this->fullSettlementColumnDefinitions();

        return [
            ['key' => 'settlement_type', 'label' => '정산유형', 'required' => true, 'template_default' => true, 'download_default' => true],
            ['key' => 'amount_sign', 'label' => '가감유형', 'required' => true, 'template_default' => true, 'download_default' => true],
            ['key' => 'amount', 'label' => '정산금액', 'required' => true, 'template_default' => true, 'download_default' => true],
            ['key' => 'description', 'label' => '적요', 'required' => false, 'template_default' => true, 'download_default' => true, 'source_key' => 'settlement_description'],
        ];
    }

    private function fullItemColumnDefinitions(): array
    {
        return [
            ['key' => 'id', 'label' => 'ID', 'required' => false, 'template_default' => false, 'download_default' => false],
            ['key' => 'sort_no', 'label' => '순번', 'required' => false, 'template_default' => false, 'download_default' => false],
            ['key' => 'transaction_id', 'label' => '거래ID', 'required' => false, 'template_default' => false, 'download_default' => false],
            ['key' => 'transaction_line_type', 'label' => '거래라인유형', 'required' => false, 'template_default' => false, 'download_default' => false],
            ['key' => 'item_date', 'label' => '품목일자', 'required' => true, 'template_default' => true, 'download_default' => true],
            ['key' => 'item_name', 'label' => '품목명', 'required' => true, 'template_default' => true, 'download_default' => true],
            ['key' => 'item_specification', 'label' => '규격', 'required' => false, 'template_default' => true, 'download_default' => true, 'source_key' => 'specification', 'payload_key' => 'specification'],
            ['key' => 'item_unit_name', 'label' => '단위', 'required' => false, 'template_default' => true, 'download_default' => true, 'source_key' => 'unit_name', 'payload_key' => 'unit_name'],
            ['key' => 'item_quantity', 'label' => '수량', 'required' => false, 'template_default' => true, 'download_default' => true, 'source_key' => 'quantity', 'payload_key' => 'quantity'],
            ['key' => 'item_unit_price', 'label' => '단가', 'required' => false, 'template_default' => true, 'download_default' => true, 'source_key' => 'unit_price', 'payload_key' => 'unit_price'],
            ['key' => 'item_foreign_unit_price', 'label' => '외화단가', 'required' => false, 'template_default' => true, 'download_default' => true, 'source_key' => 'foreign_unit_price', 'payload_key' => 'foreign_unit_price'],
            ['key' => 'item_foreign_amount', 'label' => '외화금액', 'required' => false, 'template_default' => true, 'download_default' => true, 'source_key' => 'foreign_amount', 'payload_key' => 'foreign_amount'],
            ['key' => 'item_supply_amount', 'label' => '공급가액', 'required' => false, 'template_default' => true, 'download_default' => true, 'source_key' => 'amount', 'payload_key' => 'amount'],
            ['key' => 'item_tax_type', 'label' => '세금구분', 'required' => false, 'template_default' => true, 'download_default' => true, 'source_key' => 'tax_type', 'payload_key' => 'tax_type'],
            ['key' => 'item_description', 'label' => '적요', 'required' => false, 'template_default' => true, 'download_default' => true, 'source_key' => 'description', 'payload_key' => 'description'],
            ['key' => 'created_at', 'label' => '생성일시', 'required' => false, 'template_default' => false, 'download_default' => false],
            ['key' => 'created_by', 'label' => '생성자', 'required' => false, 'template_default' => false, 'download_default' => false],
            ['key' => 'updated_at', 'label' => '수정일시', 'required' => false, 'template_default' => false, 'download_default' => false],
            ['key' => 'updated_by', 'label' => '수정자', 'required' => false, 'template_default' => false, 'download_default' => false],
            ['key' => 'deleted_at', 'label' => '삭제일시', 'required' => false, 'template_default' => false, 'download_default' => false],
            ['key' => 'deleted_by', 'label' => '삭제자', 'required' => false, 'template_default' => false, 'download_default' => false],
        ];
    }

    private function fullSettlementColumnDefinitions(): array
    {
        return [
            ['key' => 'id', 'label' => 'ID', 'required' => false, 'template_default' => false, 'download_default' => false],
            ['key' => 'sort_no', 'label' => '순번', 'required' => false, 'template_default' => false, 'download_default' => false],
            ['key' => 'transaction_id', 'label' => '거래ID', 'required' => false, 'template_default' => false, 'download_default' => false],
            ['key' => 'transaction_item_id', 'label' => '거래품목ID', 'required' => false, 'template_default' => false, 'download_default' => false],
            ['key' => 'settlement_type', 'label' => '정산유형', 'required' => true, 'template_default' => true, 'download_default' => true],
            ['key' => 'amount_sign', 'label' => '가감유형', 'required' => true, 'template_default' => true, 'download_default' => true],
            ['key' => 'amount', 'label' => '정산금액', 'required' => true, 'template_default' => true, 'download_default' => true],
            ['key' => 'currency', 'label' => '통화', 'required' => false, 'template_default' => false, 'download_default' => false],
            ['key' => 'exchange_rate', 'label' => '환율', 'required' => false, 'template_default' => false, 'download_default' => false],
            ['key' => 'settlement_description', 'label' => '적요', 'required' => false, 'template_default' => true, 'download_default' => true, 'source_key' => 'description', 'payload_key' => 'description'],
            ['key' => 'meta_json', 'label' => '메타JSON', 'required' => false, 'template_default' => false, 'download_default' => false],
            ['key' => 'created_at', 'label' => '생성일시', 'required' => false, 'template_default' => false, 'download_default' => false],
            ['key' => 'created_by', 'label' => '생성자', 'required' => false, 'template_default' => false, 'download_default' => false],
            ['key' => 'updated_at', 'label' => '수정일시', 'required' => false, 'template_default' => false, 'download_default' => false],
            ['key' => 'updated_by', 'label' => '수정자', 'required' => false, 'template_default' => false, 'download_default' => false],
            ['key' => 'deleted_at', 'label' => '삭제일시', 'required' => false, 'template_default' => false, 'download_default' => false],
            ['key' => 'deleted_by', 'label' => '삭제자', 'required' => false, 'template_default' => false, 'download_default' => false],
        ];
    }

    private function sampleRows(): array
    {
        $row = [
            'transaction_date' => date('Y-m-d'),
            'business_unit' => '건설업',
            'transaction_direction' => '비용',
            'currency' => '원화',
            'transaction_exchange_rate' => '',
            'client_id' => '샘플 거래처',
            'project_id' => '샘플 프로젝트',
            'bank_account_id' => '주거래 계좌',
            'card_id' => '법인카드',
            'team_id' => '관리팀',
            'employee_id' => '홍길동',
            'transaction_foreign_amount' => '',
            'transaction_supply_amount' => '100000',
            'transaction_settlement_amount' => '0',
            'transaction_final_amount' => '100000',
            'transaction_description' => '거래입력 엑셀 샘플',
            'transaction_note' => '비고 샘플',
            'transaction_memo' => '메모 샘플',
            'status' => 'draft',
        ];

        if ($this->tableColumnExists('ledger_transactions', 'operation_type')) {
            $row['operation_type'] = '일반';
        }

        return [$row];
    }

    private function sampleItemRows(): array
    {
        return [[
            'item_date' => date('Y-m-d'),
            'item_name' => '샘플 품목',
            'specification' => '기본 규격',
            'unit_name' => 'EA',
            'quantity' => 1,
            'unit_price' => 100000,
            'foreign_unit_price' => '',
            'foreign_amount' => '',
            'amount' => 100000,
            'tax_type' => '과세',
            'description' => '거래품목 샘플',
        ]];
    }

    private function sampleSettlementRows(): array
    {
        return [[
            'settlement_type' => '부가세',
            'amount_sign' => '가산',
            'amount' => 10000,
            'description' => '거래정산 샘플',
        ]];
    }

    private function buildImportPayload(array $rowValues): array
    {
        $currencyCode = $this->resolveCode('CURRENCY', (string) ($rowValues['currency'] ?? ''), false);
        $exchangeRate = $this->numberOrNull($rowValues['transaction_exchange_rate'] ?? null);
        $foreignAmount = $this->numberOrNull($rowValues['transaction_foreign_amount'] ?? null);
        $isImport = ($currencyCode !== null && $currencyCode !== 'KRW')
            || ($exchangeRate !== null && $exchangeRate > 0)
            || ($foreignAmount !== null && abs($foreignAmount) > 0);

        $payload = [
            'transaction_date' => trim((string) ($rowValues['transaction_date'] ?? '')) ?: date('Y-m-d'),
            'business_unit' => $this->resolveCode('BUSINESS_UNIT', (string) ($rowValues['business_unit'] ?? ''), true),
            'transaction_direction' => $this->resolveCode('TRANSACTION_DIRECTION', (string) ($rowValues['transaction_direction'] ?? ''), false),
            'client_id' => $this->resolveReference('system_clients', 'client_name', (string) ($rowValues['client_id'] ?? ''), false),
            'project_id' => $this->resolveReference('system_projects', 'project_name', (string) ($rowValues['project_id'] ?? ''), false),
            'bank_account_id' => $this->resolveReference('system_bank_accounts', 'account_name', (string) ($rowValues['bank_account_id'] ?? ''), false),
            'card_id' => $this->resolveReference('system_cards', 'card_name', (string) ($rowValues['card_id'] ?? ''), false),
            'team_id' => $this->resolveReference('system_work_teams', 'team_name', (string) ($rowValues['team_id'] ?? ''), false),
            'employee_id' => $this->resolveReference('user_employees', 'employee_name', (string) ($rowValues['employee_id'] ?? ''), false),
            'currency' => $currencyCode ?? 'KRW',
            'transaction_exchange_rate' => $exchangeRate,
            'transaction_foreign_amount' => $foreignAmount,
            'transaction_supply_amount' => $this->numberOrNull($rowValues['transaction_supply_amount'] ?? null) ?? 0,
            'transaction_settlement_amount' => $this->numberOrNull($rowValues['transaction_settlement_amount'] ?? null) ?? 0,
            'transaction_final_amount' => $this->numberOrNull($rowValues['transaction_final_amount'] ?? null) ?? 0,
            'transaction_description' => trim((string) ($rowValues['transaction_description'] ?? '')),
            'transaction_note' => trim((string) ($rowValues['transaction_note'] ?? '')),
            'transaction_memo' => trim((string) ($rowValues['transaction_memo'] ?? '')),
            'status' => trim((string) ($rowValues['status'] ?? 'draft')) ?: 'draft',
            'is_import' => $isImport ? 1 : 0,
            'items' => [],
            'settlements' => [],
        ];

        if ($this->tableColumnExists('ledger_transactions', 'operation_type')) {
            $payload['operation_type'] = $this->resolveCode('OPERATION_TYPE', (string) ($rowValues['operation_type'] ?? ''), false);
        }

        return $payload;
    }

    private function resolveItemColumns(string $type, ?string $columnsCsv = null): array
    {
        return $this->resolveGridColumns($this->itemColumnDefinitions(), $type, $columnsCsv);
    }

    private function resolveSettlementColumns(string $type, ?string $columnsCsv = null): array
    {
        return $this->resolveGridColumns($this->settlementColumnDefinitions(), $type, $columnsCsv);
    }

    private function resolveGridColumns(array $definitions, string $type, ?string $columnsCsv = null): array
    {
        $columnsByKey = [];
        foreach ($definitions as $column) {
            $columnsByKey[$column['key']] = $column;
        }

        $requestedKeys = $this->parseColumnsCsv($columnsCsv);
        $selectedKeys = [];

        if ($requestedKeys === []) {
            foreach ($definitions as $column) {
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
            return $this->resolveGridColumns($definitions, $type, '');
        }

        $selectedColumns = array_map(static function (string $key) use ($columnsByKey): array {
            $column = $columnsByKey[$key];
            $column['header'] = $column['label'];
            return $column;
        }, $selectedKeys);

        return $this->decorateColumns($selectedColumns);
    }

    private function createGridTemplateSpreadsheet(string $sheetTitle, array $columns, array $rows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetTitle);

        $headers = $this->buildHeaders($columns);
        $body = array_map(fn(array $row): array => $this->buildRow($row, $columns), $rows);

        ExcelValueFormatterHelper::writeTable($sheet, $headers, $body, 'A1', $columns, [
            'showRequiredAsterisk' => true,
        ]);
        $this->autoSize($sheet, count($headers));

        return $spreadsheet;
    }

    private function createGridExportSpreadsheet(string $sheetTitle, array $columns, array $rows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetTitle);

        $headers = $this->buildHeaders($columns);
        $body = array_map(fn(array $row): array => $this->buildRow($row, $columns), $rows);

        ExcelValueFormatterHelper::writeTable($sheet, $headers, $body, 'A1', $columns);
        $this->autoSize($sheet, count($headers));

        return $spreadsheet;
    }

    private function importGridRowsFromExcelFile(string $filePath, array $columns, callable $rowBuilder, string $label): array
    {
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

        $normalizedRows = [];
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
                throw new \RuntimeException("{$rowNumber}행의 필수값이 누락되었습니다. " . implode(', ', $missingRequired));
            }

            $normalizedRows[] = $rowBuilder($rowValues);
        }

        return [
            'success' => true,
            'message' => "{$label} " . count($normalizedRows) . '건을 불러왔습니다.',
            'data' => [
                'rows' => $normalizedRows,
                'count' => count($normalizedRows),
            ],
        ];
    }

    private function decorateColumns(array $columns): array
    {
        $displayNameMap = ColumnPolicyRequestHelper::displayNameMap($this->requestValue('column_display_name'));
        $requirementPolicyMap = ColumnPolicyRequestHelper::requirementPolicyMap($this->requestValue('column_requirement_policy'));

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

    private function normalizeItemExportRow(array $row): array
    {
        return [
            'item_date' => trim((string) ($row['item_date'] ?? '')),
            'item_name' => trim((string) ($row['item_name'] ?? '')),
            'specification' => trim((string) ($row['specification'] ?? $row['item_specification'] ?? '')),
            'unit_name' => trim((string) ($row['unit_name'] ?? $row['item_unit_name'] ?? '')),
            'quantity' => $this->numberOrNull($row['quantity'] ?? $row['item_quantity'] ?? null) ?? '',
            'unit_price' => $this->numberOrNull($row['unit_price'] ?? $row['item_unit_price'] ?? null) ?? '',
            'foreign_unit_price' => $this->numberOrNull($row['foreign_unit_price'] ?? $row['item_foreign_unit_price'] ?? null) ?? '',
            'foreign_amount' => $this->numberOrNull($row['foreign_amount'] ?? $row['item_foreign_amount'] ?? null) ?? '',
            'amount' => $this->numberOrNull($row['amount'] ?? $row['supply_amount'] ?? $row['item_supply_amount'] ?? null) ?? '',
            'tax_type' => trim((string) ($row['tax_type'] ?? $row['item_tax_type'] ?? '')),
            'description' => trim((string) ($row['description'] ?? $row['item_description'] ?? '')),
        ];
    }

    private function normalizeSettlementExportRow(array $row): array
    {
        return [
            'settlement_type' => trim((string) ($row['settlement_type'] ?? '')),
            'amount_sign' => trim((string) ($row['amount_sign'] ?? '')),
            'amount' => $this->numberOrNull($row['amount'] ?? null) ?? '',
            'description' => trim((string) ($row['description'] ?? $row['settlement_description'] ?? '')),
        ];
    }

    private function buildItemImportRow(array $rowValues): array
    {
        return [
            'item_date' => trim((string) $this->firstDefinedValue($rowValues, ['item_date'])) ?: date('Y-m-d'),
            'item_name' => trim((string) $this->firstDefinedValue($rowValues, ['item_name'])),
            'specification' => trim((string) $this->firstDefinedValue($rowValues, ['item_specification', 'specification'])),
            'unit_name' => trim((string) $this->firstDefinedValue($rowValues, ['item_unit_name', 'unit_name'])),
            'quantity' => $this->numberOrNull($this->firstDefinedValue($rowValues, ['item_quantity', 'quantity'])) ?? '',
            'unit_price' => $this->numberOrNull($this->firstDefinedValue($rowValues, ['item_unit_price', 'unit_price'])) ?? '',
            'foreign_unit_price' => $this->numberOrNull($this->firstDefinedValue($rowValues, ['item_foreign_unit_price', 'foreign_unit_price'])) ?? '',
            'foreign_amount' => $this->numberOrNull($this->firstDefinedValue($rowValues, ['item_foreign_amount', 'foreign_amount'])) ?? '',
            'amount' => $this->numberOrNull($this->firstDefinedValue($rowValues, ['item_supply_amount', 'amount', 'supply_amount'])) ?? '',
            'tax_type' => trim((string) $this->firstDefinedValue($rowValues, ['item_tax_type', 'tax_type'])),
            'description' => trim((string) $this->firstDefinedValue($rowValues, ['item_description', 'description'])),
        ];
    }

    private function buildSettlementImportRow(array $rowValues): array
    {
        return [
            'settlement_type' => trim((string) $this->firstDefinedValue($rowValues, ['settlement_type'])),
            'amount_sign' => trim((string) $this->firstDefinedValue($rowValues, ['amount_sign'])),
            'amount' => $this->numberOrNull($this->firstDefinedValue($rowValues, ['amount'])) ?? '',
            'description' => trim((string) $this->firstDefinedValue($rowValues, ['settlement_description', 'description'])),
        ];
    }

    private function buildHeaders(array $columns): array
    {
        return array_map(static fn(array $column): string => (string) $column['header'], $columns);
    }

    private function buildRow(array $source, array $columns): array
    {
        $row = [];
        foreach ($columns as $column) {
            $row[] = $this->exportCellValue($source, $column);
        }

        return $row;
    }

    private function exportCellValue(array $source, array $column): mixed
    {
        $key = (string) ($column['key'] ?? '');
        $sourceKey = (string) ($column['source_key'] ?? $key);

        return match ($key) {
            'business_unit' => $source['business_unit_name'] ?? $source['business_unit'] ?? $source['business_unit_code'] ?? '',
            'transaction_direction' => $source['transaction_direction_name'] ?? $source['transaction_direction'] ?? $source['transaction_direction_code'] ?? '',
            'operation_type' => $source['operation_type_name'] ?? $source['operation_type'] ?? $source['operation_type_code'] ?? '',
            'currency' => $source['currency_name'] ?? $source['currency'] ?? $source['currency_code'] ?? '',
            'client_id' => $source['client_name'] ?? $source['client_id'] ?? $source['client_uuid'] ?? '',
            'project_id' => $source['project_name'] ?? $source['project_id'] ?? $source['project_uuid'] ?? '',
            'bank_account_id' => $source['bank_account_name'] ?? $source['bank_account_id'] ?? $source['bank_account_uuid'] ?? '',
            'card_id' => $source['card_name'] ?? $source['card_id'] ?? $source['card_uuid'] ?? '',
            'team_id' => $source['team_name'] ?? $source['team_id'] ?? $source['team_uuid'] ?? '',
            'employee_id' => $source['employee_name'] ?? $source['employee_id'] ?? $source['employee_uuid'] ?? '',
            default => $source[$key] ?? $source[$sourceKey] ?? '',
        };
    }

    private function firstDefinedValue(array $rowValues, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $rowValues)) {
                return $rowValues[$key];
            }
        }

        return null;
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

    private function headerMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $columnIndex => $label) {
            $normalized = $this->normalizeHeader((string) $label);
            if ($normalized !== '') {
                $map[$normalized] = $columnIndex;
            }
        }

        return $map;
    }

    private function hasHeader(array $headerMap, array $column): bool
    {
        foreach ($this->headerAliases($column) as $alias) {
            if (isset($headerMap[$this->normalizeHeader($alias)])) {
                return true;
            }
        }

        return false;
    }

    private function cell(array $row, array $headerMap, array $aliases): string
    {
        foreach ($aliases as $alias) {
            $normalized = $this->normalizeHeader($alias);
            if ($normalized !== '' && isset($headerMap[$normalized])) {
                return trim((string) ($row[$headerMap[$normalized]] ?? ''));
            }
        }

        return '';
    }

    private function headerAliases(array $column): array
    {
        return array_values(array_unique(array_filter([
            (string) ($column['header'] ?? ''),
            (string) ($column['label'] ?? ''),
            (string) ($column['key'] ?? ''),
            (string) ($column['source_key'] ?? ''),
            (string) ($column['payload_key'] ?? ''),
        ], static fn(string $value): bool => trim($value) !== '')));
    }

    private function normalizeHeader(string $value): string
    {
        return preg_replace('/\s+/u', '', trim($value)) ?? '';
    }

    private function resolveCode(string $group, string $value, bool $required): ?string
    {
        $raw = trim($value);
        if ($raw === '') {
            return $required ? throw new \RuntimeException("{$group} 값이 필요합니다.") : null;
        }

        $stmt = $this->pdo->prepare("
            SELECT code
            FROM system_codes
            WHERE deleted_at IS NULL
              AND is_active = 1
              AND code_group = :code_group
              AND (UPPER(code) = :upper_code OR code_name = :code_name)
            ORDER BY sort_no ASC, code_name ASC
            LIMIT 1
        ");
        $stmt->execute([
            ':code_group' => $group,
            ':upper_code' => strtoupper($raw),
            ':code_name' => $raw,
        ]);

        $resolved = $stmt->fetchColumn();
        if ($resolved !== false) {
            return (string) $resolved;
        }

        if ($required) {
            throw new \RuntimeException("{$group} 기준정보를 찾을 수 없습니다: {$raw}");
        }

        return null;
    }

    private function resolveReference(string $table, string $nameColumn, string $value, bool $required): ?string
    {
        $raw = trim($value);
        if ($raw === '') {
            return $required ? throw new \RuntimeException("{$nameColumn} 값이 필요합니다.") : null;
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $nameColumn)) {
            throw new \RuntimeException('기준정보 조회 구성이 올바르지 않습니다.');
        }

        $sql = "
            SELECT id
            FROM {$table}
            WHERE deleted_at IS NULL
              AND (id = :id OR {$nameColumn} = :name)
            ORDER BY {$nameColumn} ASC
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $raw,
            ':name' => $raw,
        ]);

        $resolved = $stmt->fetchColumn();
        if ($resolved !== false) {
            return (string) $resolved;
        }

        if ($required) {
            throw new \RuntimeException("기준정보를 찾을 수 없습니다: {$raw}");
        }

        return null;
    }

    private function tableColumnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
            LIMIT 1
        ");
        $stmt->execute([
            ':table_name' => $table,
            ':column_name' => $column,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function requestString(string $key): ?string
    {
        $value = $this->requestValue($key);
        $resolved = trim((string) ($value ?? ''));
        return $resolved === '' ? null : $resolved;
    }

    private function requestValue(string $key): mixed
    {
        return $_REQUEST[$key] ?? null;
    }

    private function numberOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = str_replace(',', '', trim((string) $value));
        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function autoSize(object $sheet, int $columnCount): void
    {
        for ($index = 1; $index <= $columnCount; $index++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }
    }
}
