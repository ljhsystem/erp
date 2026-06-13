<?php

namespace App\Services\Ledger;

use PDO;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EvidenceTemplateDropdownService
{
    private array $systemFieldOptionsByDataType = [];

    public function __construct(
        private PDO $pdo,
        private array $bankVoucherLineFields,
        private array $callbacks = []
    ) {
    }

    public function applyBankTemplateDropdowns(Spreadsheet $spreadsheet, array $sheetSpecs): void
    {
        $headerSheetIndex = null;
        $lineSheetIndex = null;
        $accountColumnIndex = null;
        $rowTypeColumnIndex = null;
        $businessRefColumns = [
            'CLIENT' => null,
            'PROJECT' => null,
            'EMPLOYEE' => null,
            'ACCOUNT' => null,
            'CARD' => null,
        ];
        $businessRefFields = [
            'client_id' => 'CLIENT',
            'client_name' => 'CLIENT',
            'client_company_name' => 'CLIENT',
            'counterparty_name' => 'CLIENT',
            'project_id' => 'PROJECT',
            'project_name' => 'PROJECT',
            'project_code' => 'PROJECT',
            'employee_id' => 'EMPLOYEE',
            'employee_name' => 'EMPLOYEE',
            'user_name' => 'EMPLOYEE',
            'bank_account_id' => 'ACCOUNT',
            'bank_account_name' => 'ACCOUNT',
            'bank_account' => 'ACCOUNT',
            'account_name' => 'ACCOUNT',
            'payment_account_name' => 'ACCOUNT',
            'card_id' => 'CARD',
            'card_name' => 'CARD',
            'card_number' => 'CARD',
        ];
        $businessRefFieldPriority = [
            'client_name' => 10,
            'client_id' => 20,
            'client_company_name' => 30,
            'counterparty_name' => 90,
            'project_name' => 10,
            'project_id' => 20,
            'project_code' => 30,
            'employee_name' => 10,
            'employee_id' => 20,
            'user_name' => 30,
            'bank_account_name' => 10,
            'bank_account_id' => 20,
            'bank_account' => 30,
            'account_name' => 40,
            'payment_account_name' => 50,
            'card_name' => 10,
            'card_id' => 20,
            'card_number' => 30,
        ];
        $businessRefColumnPriorities = [
            'CLIENT' => PHP_INT_MAX,
            'PROJECT' => PHP_INT_MAX,
            'EMPLOYEE' => PHP_INT_MAX,
            'ACCOUNT' => PHP_INT_MAX,
            'CARD' => PHP_INT_MAX,
        ];

        foreach ($sheetSpecs as $index => $spec) {
            if ($headerSheetIndex === null) {
                $headerSheetIndex = $index;
                $fields = is_array($spec['fields'] ?? null) ? array_values($spec['fields']) : [];
                foreach ($fields as $fieldIndex => $field) {
                    $field = (string) $field;
                    $refType = $businessRefFields[$field] ?? null;
                    $priority = $businessRefFieldPriority[$field] ?? 100;
                    if ($refType !== null && $priority < $businessRefColumnPriorities[$refType]) {
                        $businessRefColumns[$refType] = $fieldIndex + 1;
                        $businessRefColumnPriorities[$refType] = $priority;
                    }
                }
            }

            if (($spec['title'] ?? '') !== 'VoucherLines') {
                continue;
            }
            $headers = is_array($spec['headers'] ?? null) ? array_values($spec['headers']) : [];
            $fields = is_array($spec['fields'] ?? null) ? array_values($spec['fields']) : [];
            $lineSheetIndex = $index;
            foreach ($headers as $headerIndex => $header) {
                $field = (string) ($fields[$headerIndex] ?? '');
                if ($field === 'account_id' || trim((string) $header) === 'Account') {
                    $accountColumnIndex = $headerIndex + 1;
                }
                if ($field === 'line_row_type' || trim((string) $header) === 'RowType') {
                    $rowTypeColumnIndex = $headerIndex + 1;
                }
            }
            break;
        }

        if ($lineSheetIndex === null || $spreadsheet->getSheetCount() <= $lineSheetIndex) {
            return;
        }

        $accountOptions = $this->accountDropdownOptions();
        $businessRefOptions = [
            'CLIENT' => $this->businessRefDropdownOptions('CLIENT'),
            'PROJECT' => $this->businessRefDropdownOptions('PROJECT'),
            'EMPLOYEE' => $this->businessRefDropdownOptions('EMPLOYEE'),
            'ACCOUNT' => $this->businessRefDropdownOptions('ACCOUNT'),
            'CARD' => $this->businessRefDropdownOptions('CARD'),
        ];
        $rowTypeOptions = ['A', 'B'];
        if ($accountOptions === [] && $rowTypeColumnIndex === null && !array_filter($businessRefOptions)) {
            return;
        }

        $referenceSheet = $spreadsheet->createSheet();
        $referenceSheet->setTitle('_refs');
        foreach ($accountOptions as $rowIndex => $option) {
            $referenceSheet->setCellValue('A' . ($rowIndex + 1), $option);
        }
        foreach ($rowTypeOptions as $rowIndex => $option) {
            $referenceSheet->setCellValue('B' . ($rowIndex + 1), $option);
        }
        $businessRefListColumns = [
            'CLIENT' => 'C',
            'PROJECT' => 'D',
            'EMPLOYEE' => 'E',
            'ACCOUNT' => 'F',
            'CARD' => 'G',
        ];
        foreach ($businessRefListColumns as $refType => $column) {
            foreach ($businessRefOptions[$refType] as $rowIndex => $option) {
                $referenceSheet->setCellValue($column . ($rowIndex + 1), $option);
            }
        }
        $referenceSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        $lineSheet = $spreadsheet->getSheet($lineSheetIndex);
        if ($accountColumnIndex !== null && $accountOptions !== []) {
            $this->applyListValidation(
                $lineSheet,
                Coordinate::stringFromColumnIndex($accountColumnIndex),
                "'_?????????????????????????????????썹땟戮녹??諭?????⑸㎦???????????븐뼐?????????????????饔낅떽???????????!\$A\$1:\$A\$" . count($accountOptions),
                'List selection error',
                '?????????????????????????????????썹땟戮녹??諭?????⑸㎦???????????븐뼐?????????????????饔낅떽??????????????????????????????????????????됰Ŧ?????????????????????대첐???????????????????????????????????????????????????????????????????????癲??????????????????????筌????'
            );
        }
        if ($rowTypeColumnIndex !== null) {
            $this->applyListValidation(
                $lineSheet,
                Coordinate::stringFromColumnIndex($rowTypeColumnIndex),
                "'_?????????????????????????????????썹땟戮녹??諭?????⑸㎦???????????븐뼐?????????????????饔낅떽???????????!\$B\$1:\$B\$" . count($rowTypeOptions),
                'List selection error',
                '????????????????????????????????怨뺤떪????????????????遺얘턁??????嶺뚮ㅎ?볟퐲??????????거?????????????????????????????????癲??????????????????????筌????'
            );
        }

        if ($headerSheetIndex !== null && $spreadsheet->getSheetCount() > $headerSheetIndex) {
            $headerSheet = $spreadsheet->getSheet($headerSheetIndex);
            foreach ($businessRefColumns as $refType => $columnIndex) {
                if ($columnIndex === null || $businessRefOptions[$refType] === []) {
                    continue;
                }
                $listColumn = $businessRefListColumns[$refType];
                $this->applyListValidation(
                    $headerSheet,
                    Coordinate::stringFromColumnIndex($columnIndex),
                    "'" . $referenceSheet->getTitle() . "'!$" . $listColumn . '$1:$' . $listColumn . '$' . count($businessRefOptions[$refType]),
                    'List selection error',
                    'Select a value from the list.'
                );
            }
        }
    }

    public function applySimpleBankTemplateDropdowns(Spreadsheet $spreadsheet, Worksheet $sheet, array $headers): void
    {
        $targetColumns = [
            '????????????????????怨뺤떪?????' => 'BUSINESS_UNIT',
            '???????????????????????????' => 'TRANSACTION_TYPE',
            '???????????????????????????' => 'CLIENT',
            '???????????????????????' => 'CLIENT',
            '???????????熬곣뫖利당춯??쎾퐲???????????????????꿔꺂?㏘틠??怨몄젦??????????????????????' => 'PROJECT',
            '???????????熬곣뫖利당춯??쎾퐲???????????????????꿔꺂?㏘틠??怨몄젦????????????????????' => 'PROJECT',
            '????????????????????????' => 'EMPLOYEE',
            '????????????????' => 'EMPLOYEE',
            '??????????????????????????????????怨뚰뇠???????????????????蹂㏓??嶺뚮㉡?????' => 'ACCOUNT',
            '??????????????' => 'ACCOUNT',
            '?????????釉먮폁???????????곕츥????브???????????????됰Ŧ?????????????????????대첐??' => 'CARD',
            '?????????釉먮폁???????????곕츥????브??????' => 'CARD',
        ];
        $listColumns = [
            'BUSINESS_UNIT' => 'A',
            'TRANSACTION_TYPE' => 'B',
            'CLIENT' => 'C',
            'PROJECT' => 'D',
            'EMPLOYEE' => 'E',
            'ACCOUNT' => 'F',
            'CARD' => 'G',
        ];
        $options = [
            'BUSINESS_UNIT' => $this->codeDropdownOptions('BUSINESS_UNIT', ['HQ', 'CONSTRUCTION', 'ECOMMERCE']),
            'TRANSACTION_TYPE' => $this->codeDropdownOptions('TRANSACTION_TYPE', ['GENERAL', 'PURCHASE', 'SALES']),
            'CLIENT' => $this->businessRefDropdownOptions('CLIENT'),
            'PROJECT' => $this->businessRefDropdownOptions('PROJECT'),
            'EMPLOYEE' => $this->businessRefDropdownOptions('EMPLOYEE'),
            'ACCOUNT' => $this->businessRefDropdownOptions('ACCOUNT'),
            'CARD' => $this->businessRefDropdownOptions('CARD'),
        ];

        $targetHeaderColumns = [];
        foreach (array_values($headers) as $headerIndex => $header) {
            $cleanHeader = preg_replace('/\s*\*$/u', '', trim((string) $header)) ?? trim((string) $header);
            $refType = $targetColumns[$cleanHeader] ?? null;
            if ($refType !== null && $options[$refType] !== []) {
                $targetHeaderColumns[] = [$headerIndex + 1, $refType];
            }
        }
        if ($targetHeaderColumns === []) {
            return;
        }

        $referenceSheet = $spreadsheet->createSheet();
        $referenceSheet->setTitle('_??????????????????????????????????밸븶筌믩끃??獄???????멥렑???????????釉먮폁?????????????????耀붾굝????????????');
        foreach ($listColumns as $refType => $listColumn) {
            foreach ($options[$refType] as $rowIndex => $option) {
                $referenceSheet->setCellValue($listColumn . ($rowIndex + 1), $option);
            }
        }
        $referenceSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        foreach ($targetHeaderColumns as [$columnIndex, $refType]) {
            $listColumn = $listColumns[$refType];
            $this->applyListValidation(
                $sheet,
                Coordinate::stringFromColumnIndex($columnIndex),
                "'_?????????????????????????????????썹땟戮녹??諭?????⑸㎦???????????븐뼐?????????????????饔낅떽???????????!$" . $listColumn . '$1:$' . $listColumn . '$' . count($options[$refType]),
                '??????????????????????????????????밸븶筌믩끃??獄???????멥렑???????????釉먮폁?????????????????耀붾굝???????????????????????????????????????????',
                '?????????????????????????????????썹땟戮녹??諭?????⑸㎦???????????븐뼐?????????????????饔낅떽?????????????????????????????????????????????????????????癲??????????????????????筌????'
            );
        }
    }

    public function applyTemplateDropdowns(Spreadsheet $spreadsheet, Worksheet $sheet, array $fields, array $headers, string $dataType): void
    {
        $fields = array_values($fields);
        if ($fields === []) {
            if ($this->call('looksLikeBankTemplateHeaders', $headers)) {
                $this->applySimpleBankTemplateDropdowns($spreadsheet, $sheet, $headers);
            }
            return;
        }

        $fieldOptions = $this->systemFieldOptionsByValue($dataType);

        $targetColumns = [];
        $lists = [];
        foreach ($fields as $index => $field) {
            $field = trim((string) $field);
            if ($this->shouldSkipTemplateDropdown($dataType, $field)) {
                continue;
            }

            $option = $fieldOptions[$field] ?? null;
            if (!is_array($option)) {
                continue;
            }

            $listKey = $this->templateDropdownListKey($option);
            if ($listKey === '') {
                continue;
            }

            if (!array_key_exists($listKey, $lists)) {
                $lists[$listKey] = $this->templateDropdownOptionsForField($option);
            }
            if ($lists[$listKey] === []) {
                continue;
            }

            $targetColumns[] = [$index + 1, $listKey];
        }

        if ($targetColumns === []) {
            return;
        }

        $referenceSheet = $spreadsheet->createSheet();
        $referenceSheet->setTitle($this->call('uniqueSheetTitle', $spreadsheet, '_?????????????????????????????????썹땟戮녹??諭?????⑸㎦???????????븐뼐?????????????????饔낅떽???????????'));

        $listColumns = [];
        $listIndex = 1;
        foreach ($lists as $listKey => $options) {
            if ($options === []) {
                continue;
            }
            $listColumn = Coordinate::stringFromColumnIndex($listIndex);
            $listColumns[$listKey] = [$listColumn, count($options)];
            foreach ($options as $rowIndex => $option) {
                $referenceSheet->setCellValue($listColumn . ($rowIndex + 1), $option);
            }
            $listIndex++;
        }
        $referenceSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        $quotedSheet = "'" . str_replace("'", "''", $referenceSheet->getTitle()) . "'";
        foreach ($targetColumns as [$columnIndex, $listKey]) {
            if (!isset($listColumns[$listKey])) {
                continue;
            }
            [$listColumn, $rowCount] = $listColumns[$listKey];
            $this->applyListValidation(
                $sheet,
                Coordinate::stringFromColumnIndex($columnIndex),
                "{$quotedSheet}!$" . $listColumn . '$1:$' . $listColumn . '$' . $rowCount,
                '??????????????????????????????????밸븶筌믩끃??獄???????멥렑???????????釉먮폁?????????????????耀붾굝???????????????????????????????????????????',
                '?????????????????????????????????썹땟戮녹??諭?????⑸㎦???????????븐뼐?????????????????饔낅떽?????????????????????????????????????????????????????????癲??????????????????????筌????'
            );
        }
    }

    public function shouldSkipTemplateDropdown(string $dataType, string $field): bool
    {
        $dataType = $this->call('normalizeDataType', $dataType);
        if ($dataType === 'CARD_HOMETAX') {
            return in_array($field, [
                'client_name',
                'card_number',
                'merchant_business_number',
                'merchant_company_name',
                'merchant_business_type',
                'merchant_business_category',
            ], true);
        }

        if (in_array($dataType, ['CARD_STATEMENT', 'CARD_APPROVAL'], true)) {
            return in_array($field, [
                'card_number',
                'payment_account_number',
                'payment_bank_name',
                'merchant_business_number',
                'merchant_company_name',
                'merchant_business_category',
                'merchant_address1',
                'merchant_address2',
                'merchant_phone',
            ], true);
        }

        if ($dataType !== 'TAX_INVOICE' && !$this->call('isManualTaxInvoiceDataType', $dataType)) {
            return false;
        }

        return in_array($field, [
            'supplier_business_number',
            'supplier_company_name',
            'supplier_ceo_name',
            'supplier_address',
            'supplier_email',
            'customer_business_number',
            'customer_company_name',
            'customer_ceo_name',
            'customer_address',
            'customer_email_1',
        ], true);
    }

    public function templateDropdownListKey(array $fieldOption): string
    {
        $table = trim((string) ($fieldOption['table'] ?? ''));
        $column = trim((string) ($fieldOption['column'] ?? ''));
        if ($table === '' || $column === '') {
            return '';
        }

        if ($table === 'system_codes') {
            $codeGroup = trim((string) ($fieldOption['code_group'] ?? ''));
            return $codeGroup !== '' ? 'code:' . $codeGroup : '';
        }

        if (in_array($table, [
            'system_clients',
            'system_projects',
            'user_employees',
            'system_bank_accounts',
            'system_cards',
            'system_company',
            'user_departments',
        ], true)) {
            return $table . ':' . $column;
        }

        return $table . ':' . $column;
    }

    public function templateDropdownOptionsForField(array $fieldOption): array
    {
        $table = trim((string) ($fieldOption['table'] ?? ''));
        $column = trim((string) ($fieldOption['column'] ?? ''));
        if ($table === '' || $column === '') {
            return [];
        }

        if ($table === 'system_codes') {
            $codeGroup = trim((string) ($fieldOption['code_group'] ?? ''));
            return $codeGroup !== '' ? $this->codeDropdownOptions($codeGroup) : [];
        }

        return $this->tableColumnDropdownOptions($table, $column);
    }

    public function tableColumnDropdownOptions(string $table, string $column): array
    {
        if (!$this->call('tableExists', $table) || !$this->call('tableColumnExists', $table, $column)) {
            return [];
        }

        $tableSql = '`' . str_replace('`', '``', $table) . '`';
        $columnSql = '`' . str_replace('`', '``', $column) . '`';
        $where = [];
        if ($this->call('tableColumnExists', $table, 'deleted_at')) {
            $where[] = 'deleted_at IS NULL';
        }
        if ($this->call('tableColumnExists', $table, 'is_active')) {
            $where[] = 'COALESCE(is_active, 1) = 1';
        }

        try {
            $stmt = $this->pdo->query(
                "SELECT DISTINCT {$columnSql} AS dropdown_value FROM {$tableSql}"
                . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
                . " ORDER BY {$columnSql} ASC"
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }

        $options = [];
        foreach ($rows as $row) {
            $value = trim((string) ($row['dropdown_value'] ?? ''));
            if ($value !== '') {
                $options[] = $value;
            }
        }

        return array_values(array_unique($options));
    }

    public function systemFieldOptionsByValue(string $dataType): array
    {
        $dataType = $this->call('normalizeDataType', $dataType);
        if (isset($this->systemFieldOptionsByDataType[$dataType])) {
            return $this->systemFieldOptionsByDataType[$dataType];
        }

        $options = [];
        foreach ($this->call('fieldOptions', $dataType) as $option) {
            $value = trim((string) ($option['value'] ?? ''));
            if ($value !== '') {
                $options[$value] = $option;
            }
        }

        return $this->systemFieldOptionsByDataType[$dataType] = $options;
    }

    public function applyListValidation(Worksheet $sheet, string $column, string $formula, string $errorTitle, string $error): void
    {
        $range = "{$column}2:{$column}1048576";
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle($errorTitle);
        $validation->setError($error);
        $validation->setFormula1($formula);
        $validation->setSqref($range);

        $sheet->setDataValidation($range, $validation);
    }

    public function codeDropdownOptions(string $codeGroup, array $fallback = []): array
    {
        if (!$this->call('tableExists', 'system_codes')) {
            return $fallback;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT code, code_name
                FROM system_codes
                WHERE code_group = :code_group
                  AND COALESCE(is_active, 1) = 1
                  AND deleted_at IS NULL
                ORDER BY sort_no ASC, code ASC
            ");
            $stmt->execute([':code_group' => $codeGroup]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return $fallback;
        }

        $options = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row['code_name'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($row['code'] ?? ''));
            }
            if ($label === '') {
                continue;
            }
            $options[] = $label;
        }

        return $options !== [] ? array_values(array_unique($options)) : $fallback;
    }

    public function accountDropdownOptions(): array
    {
        try {
            $where = 'deleted_at IS NULL';
            if ($this->call('tableColumnExists', 'ledger_accounts', 'status')) {
                $where .= " AND COALESCE(status, 'active') <> 'deleted'";
            }
            if ($this->call('tableColumnExists', 'ledger_accounts', 'is_postable')) {
                $where .= " AND COALESCE(is_postable, 'Y') = 'Y'";
            } elseif ($this->call('tableColumnExists', 'ledger_accounts', 'is_posting')) {
                $where .= " AND COALESCE(is_posting, 1) = 1";
            }

            $stmt = $this->pdo->query("
                SELECT account_code, account_name
                FROM ledger_accounts
                WHERE {$where}
                ORDER BY account_code ASC, account_name ASC
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }

        $options = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row['account_code'] ?? ''));
            $name = trim((string) ($row['account_name'] ?? ''));
            $label = trim($code . ($code !== '' && $name !== '' ? ' ' : '') . $name);
            if ($label !== '') {
                $options[] = $label;
            }
        }

        return array_values(array_unique($options));
    }

    public function businessRefDropdownOptions(string $refType): array
    {
        $config = match ($this->call('normalizeVoucherRefType', $refType)) {
            'CLIENT' => ['system_clients', ['client_name', 'company_name', 'business_number'], ['client_name', 'company_name']],
            'PROJECT' => ['system_projects', ['project_name', 'project_code'], ['project_name', 'project_code']],
            'EMPLOYEE' => ['user_employees', ['employee_name', 'name'], ['employee_name', 'name']],
            'ACCOUNT' => ['system_bank_accounts', ['account_name', 'account_number', 'bank_name'], ['account_name', 'bank_name', 'account_number']],
            'CARD' => ['system_cards', ['card_name', 'card_number'], ['card_name', 'card_number']],
            default => null,
        };
        if ($config === null) {
            return [];
        }

        [$table, $labelColumns, $orderColumns] = $config;
        if (!$this->call('tableExists', $table)) {
            return [];
        }

        $selects = [];
        foreach ($labelColumns as $column) {
            if ($this->call('tableColumnExists', $table, $column)) {
                $selects[] = $column;
            }
        }
        if ($selects === []) {
            return [];
        }

        $orderBy = [];
        foreach ($orderColumns as $column) {
            if ($this->call('tableColumnExists', $table, $column)) {
                $orderBy[] = $column . ' ASC';
            }
        }

        $where = $this->call('tableColumnExists', $table, 'deleted_at') ? 'WHERE deleted_at IS NULL' : '';
        if ($this->call('tableColumnExists', $table, 'is_active')) {
            $where .= ($where === '' ? 'WHERE' : ' AND') . ' COALESCE(is_active, 1) = 1';
        }

        try {
            $stmt = $this->pdo->query(
                'SELECT ' . implode(', ', $selects)
                . ' FROM ' . $table . ' '
                . $where
                . ($orderBy !== [] ? ' ORDER BY ' . implode(', ', $orderBy) : '')
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }

        $options = [];
        foreach ($rows as $row) {
            $label = '';
            foreach ($selects as $column) {
                $value = trim((string) ($row[$column] ?? ''));
                if ($value !== '') {
                    $label = $value;
                    break;
                }
            }
            if ($label !== '') {
                $options[] = $label;
            }
        }

        return array_values(array_unique($options));
    }

    public function splitBankFormatColumns(array $columns, bool $ensureLineRowType = false): array
    {
        $lineFields = array_flip($this->bankVoucherLineFields);
        $headerColumns = [];
        $lineColumns = [];

        foreach ($columns as $column) {
            $field = (string) ($column['system_field_name'] ?? '');
            if (isset($lineFields[$field])) {
                $lineColumns[] = $column;
            } else {
                $headerColumns[] = $column;
            }
        }

        if ($lineColumns === []) {
            $lineColumns = [
                ['excel_column_name' => '?????????????썹땟戮녹??諭?????⑸㎦??????????????????????癲????????????', 'system_field_name' => 'header_row_no', 'is_required' => 1],
                ['excel_column_name' => '????????????', 'system_field_name' => 'account_id', 'is_required' => 1],
                ['excel_column_name' => '?????????????????????곕춴????????????????轅붽틓???????', 'system_field_name' => 'debit', 'is_required' => 0],
                ['excel_column_name' => '???????????????곕춴????', 'system_field_name' => 'credit', 'is_required' => 0],
                ['excel_column_name' => '???????????????????????????', 'system_field_name' => 'line_summary', 'is_required' => 0],
            ];
        } elseif ($ensureLineRowType && !$this->columnsContainSystemField($lineColumns, 'line_row_type')) {
            $insertAt = 1;
            foreach (array_values($lineColumns) as $index => $column) {
                if ((string) ($column['system_field_name'] ?? '') === 'header_row_no') {
                    $insertAt = $index + 1;
                    break;
                }
            }
            array_splice($lineColumns, $insertAt, 0, [[
                'excel_column_name' => '?????',
                'system_field_name' => 'line_row_type',
                'is_required' => 1,
            ]]);
        }
        if (!$ensureLineRowType) {
            $lineColumns = array_values(array_filter(
                $lineColumns,
                static fn(array $column): bool => (string) ($column['system_field_name'] ?? '') !== 'line_row_type'
            ));
        }

        return [$headerColumns, $lineColumns];
    }

    public function columnsContainSystemField(array $columns, string $field): bool
    {
        foreach ($columns as $column) {
            if ((string) ($column['system_field_name'] ?? '') === $field) {
                return true;
            }
        }

        return false;
    }

    private function call(string $name, mixed ...$args): mixed
    {
        if (!isset($this->callbacks[$name])) {
            throw new \RuntimeException('Missing callback: ' . $name);
        }

        return ($this->callbacks[$name])(...$args);
    }
}
