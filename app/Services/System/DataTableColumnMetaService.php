<?php

namespace App\Services\System;

use App\Models\System\SystemSchemaModel;
use PDO;

class DataTableColumnMetaService
{
    private const DOMAIN_MAP = [
        'client' => ['table' => 'system_clients'],
        'project' => ['table' => 'system_projects'],
        'bank-account' => ['table' => 'system_bank_accounts'],
        'card' => ['table' => 'system_cards'],
        'work-team' => ['table' => 'system_work_teams'],
        'cover' => ['table' => 'system_coverimage_assets'],
        'employee' => ['composite' => 'employee'],
        'department' => ['table' => 'user_departments'],
        'position' => ['table' => 'user_positions'],
        'role' => ['table' => 'auth_roles'],
        'permission' => ['table' => 'auth_permissions'],
        'permission-assignment' => ['composite' => 'permission-assignment'],
        'individual-permission-users' => ['composite' => 'individual-permission-users'],
        'approval-template' => ['table' => 'user_approval_templates'],
        'approval-template-step' => ['table' => 'user_approval_template_steps'],
        'statutory-standard' => ['table' => 'system_statutory_standards'],
        'statutory-standard-source' => ['table' => 'system_statutory_standard_sources'],
        'personal-expense' => ['table' => 'approval_personal_expenses'],
        'personal-expense-item' => ['composite' => 'personal-expense-item'],
        'employment-contract' => ['composite' => 'employment-contract'],
        'personnel-action' => ['composite' => 'personnel-action'],
        'job-assignment' => ['composite' => 'job-assignment'],
        'employment-contract-weekly-schedule' => ['table' => 'institution_employment_contracts_weekly_schedules'],
        'employment-contract-work-schedule-policy' => ['table' => 'institution_employment_contracts_work_schedule_policies'],
        'employment-contract-component' => ['table' => 'institution_employment_contracts_components'],
        'code' => ['table' => 'system_codes'],
        'account-subject-main' => ['table' => 'ledger_accounts'],
        'account-subject-sub' => ['table' => 'ledger_accounts_sub'],
        'transaction' => ['composite' => 'transaction'],
        'transaction-header' => ['table' => 'ledger_transactions'],
        'transaction-item' => ['composite' => 'transaction-item'],
        'transaction-settlement' => ['composite' => 'transaction-settlement'],
        'voucher' => ['composite' => 'voucher'],
        'voucher-header' => ['table' => 'ledger_vouchers'],
        'ledger-journal-rule' => ['table' => 'ledger_journal_rules'],
        'evidence-metadata' => ['table' => 'ledger_evidence_metadata'],
        'evidence-create' => ['composite' => 'evidence-create'],
        'funds-bank-transaction' => ['table' => 'ledger_evidence_bank_transaction'],
        'evidence-bank-transaction' => ['table' => 'ledger_evidence_bank_transaction'],
        'evidence-tax-invoice' => ['table' => 'ledger_evidence_tax_invoice'],
        'evidence-tax-invoice-manual' => ['table' => 'ledger_evidence_tax_invoice_manual'],
        'evidence-cash-receipt' => ['table' => 'ledger_evidence_cash_receipt'],
        'evidence-card' => ['table' => 'ledger_evidence_card_hometax'],
        'evidence-card-hometax' => ['table' => 'ledger_evidence_card_hometax'],
        'evidence-card-statement' => ['table' => 'ledger_evidence_card_statement'],
        'evidence-employee-expense-personal' => ['table' => 'ledger_evidence_employee_personal_expense'],
    ];

    private SystemSchemaModel $schemaModel;

    public function __construct(PDO $pdo)
    {
        $this->schemaModel = new SystemSchemaModel($pdo);
    }

    public function columnsForDomain(string $domain): array
    {
        $resolvedDomain = trim($domain);
        if ($resolvedDomain === '' || !isset(self::DOMAIN_MAP[$resolvedDomain])) {
            return [];
        }

        $config = self::DOMAIN_MAP[$resolvedDomain];
        if (in_array($resolvedDomain, ['statutory-standard', 'statutory-standard-source'], true)) {
            return $this->columnsForStatutoryTable((string) ($config['table'] ?? ''), $resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'employee') {
            return $this->columnsForEmployeeDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'permission-assignment') {
            return $this->columnsForPermissionAssignmentDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'individual-permission-users') {
            return $this->columnsForIndividualPermissionUsersDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'personal-expense-item') {
            return $this->columnsForPersonalExpenseItemDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'employment-contract') {
            return $this->columnsForEmploymentContractDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'personnel-action') {
            return $this->columnsForPersonnelActionDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'job-assignment') {
            return $this->columnsForJobAssignmentDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'transaction') {
            return $this->columnsForMergedTables($resolvedDomain, $this->transactionMetaTables());
        }
        if (($config['composite'] ?? '') === 'transaction-item') {
            return $this->columnsForTransactionItemDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'transaction-settlement') {
            return $this->columnsForTransactionSettlementDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'voucher') {
            return $this->columnsForMergedTables($resolvedDomain, [
                'ledger_vouchers',
                'ledger_voucher_lines',
            ]);
        }
        if (($config['composite'] ?? '') === 'evidence-create') {
            return $this->columnsForEvidenceCreateDomain($resolvedDomain);
        }
        return $this->columnsForTable((string) ($config['table'] ?? ''), $resolvedDomain);
    }

    private function columnsForEvidenceCreateDomain(string $domain): array
    {
        $columns = [];
        $projectionColumns = [
            ['source_type', '자료출처', 'varchar', false],
            ['import_type', '자료유형', 'varchar', false],
            ['business_unit', '사업구분', 'varchar', true],
            ['transaction_direction', '거래구분', 'varchar', true],
            ['operation_type', '업무유형', 'varchar', true],
            ['client_name', '거래처', 'varchar', true],
            ['project_name', '프로젝트', 'varchar', true],
            ['bank_account_name', '계좌', 'varchar', true],
            ['card_name', '카드', 'varchar', true],
            ['team_name', '팀', 'varchar', true],
            ['standard_date', '기준일', 'datetime', true],
            ['policy.IN_AMOUNT', '입금금액', 'decimal', true],
            ['policy.OUT_AMOUNT', '출금금액', 'decimal', true],
            ['policy.PRE_TAX_AMOUNT', '세전금액', 'decimal', true],
            ['policy.ADJUST_AMOUNT', '가감금액합계', 'decimal', true],
            ['policy.POST_TAX_AMOUNT', '세후금액', 'decimal', true],
            ['evidence_status', '증빙상태', 'varchar', true],
        ];

        foreach ($projectionColumns as $index => [$key, $label, $dataType, $nullable]) {
            $columns[] = $this->normalizeColumnMeta(
                domain: $domain,
                table: 'evidence_projection',
                tableComment: '증빙원본 증빙정책 Projection',
                column: $key,
                label: $label,
                dataType: $dataType,
                isNullable: $nullable ? 'YES' : 'NO',
                ordinalPosition: $index + 1,
                sourceTitle: $key
            );
        }

        return $columns;
    }

    public function hasDomain(string $domain): bool
    {
        return isset(self::DOMAIN_MAP[trim($domain)]);
    }

    public function supportedDomains(): array
    {
        return array_keys(self::DOMAIN_MAP);
    }

    private function columnsForTable(string $tableName, string $domain): array
    {
        if ($tableName === '') {
            return [];
        }

        $tableComment = $this->queryTableComment($tableName);

        return array_map(
            fn(array $row): array => $this->normalizeColumnMeta(
                domain: $domain,
                table: $tableName,
                tableComment: $tableComment,
                column: (string) ($row['COLUMN_NAME'] ?? ''),
                label: $this->columnLabel((string) ($row['COLUMN_NAME'] ?? ''), (string) ($row['COLUMN_COMMENT'] ?? '')),
                dataType: (string) ($row['DATA_TYPE'] ?? ''),
                isNullable: (string) ($row['IS_NULLABLE'] ?? 'YES'),
                ordinalPosition: (int) ($row['ORDINAL_POSITION'] ?? 0)
            ),
            $this->queryTableColumns($tableName)
        );
    }

    private function columnsForEmployeeDomain(string $domain): array
    {
        $definitions = [
            [
                'table' => 'user_employees',
                'key_map' => [],
            ],
            [
                'table' => 'auth_users',
                'key_map' => [
                    'id' => 'auth_user_id',
                    'created_at' => 'user_created_at',
                    'created_by' => 'user_created_by',
                    'updated_at' => 'user_updated_at',
                    'updated_by' => 'user_updated_by',
                ],
            ],
        ];

        $merged = [];
        $sequence = 1;

        foreach ($definitions as $definition) {
            $tableName = (string) ($definition['table'] ?? '');
            $keyMap = (array) ($definition['key_map'] ?? []);
            $tableComment = $this->queryTableComment($tableName);

            foreach ($this->queryTableColumns($tableName) as $row) {
                $sourceColumn = (string) ($row['COLUMN_NAME'] ?? '');
                if ($sourceColumn === '') {
                    continue;
                }

                $key = (string) ($keyMap[$sourceColumn] ?? $sourceColumn);
                if (isset($merged[$key])) {
                    throw new \RuntimeException(sprintf(
                        '직원 테이블 설정 메타데이터 키가 중복되었습니다: %s.%s (%s)',
                        $tableName,
                        $sourceColumn,
                        $key
                    ));
                }
                $label = $this->columnLabel($sourceColumn, (string) ($row['COLUMN_COMMENT'] ?? ''));

                $merged[$key] = $this->normalizeColumnMeta(
                    domain: $domain,
                    table: $tableName,
                    tableComment: $tableComment,
                    column: $key,
                    label: $label,
                    dataType: (string) ($row['DATA_TYPE'] ?? ''),
                    isNullable: (string) ($row['IS_NULLABLE'] ?? 'YES'),
                    ordinalPosition: $sequence++,
                    sourceTitle: $sourceColumn,
                    sourceOrdinalPosition: (int) ($row['ORDINAL_POSITION'] ?? 0),
                    columnDefault: $row['COLUMN_DEFAULT'] ?? null
                );
            }
        }

        return array_values($merged);
    }

    private function columnsForEmploymentContractDomain(string $domain): array
    {
        $keyMap = [
            'employee_id' => ['employee_name', '직원명'],
            'project_id' => ['project_name', '프로젝트명'],
            'previous_contract_id' => ['previous_contract_no', '이전 계약번호'],
            'current_approval_request_id' => ['approval_request_no', '결재요청 순번'],
        ];
        $tableName = 'institution_employment_contracts';
        $tableComment = $this->queryTableComment($tableName);
        $columns = array_values(array_filter(
            $this->queryTableColumns($tableName),
            static fn(array $row): bool => (string) ($row['COLUMN_NAME'] ?? '') !== 'employee_identifier_snapshot'
        ));

        return array_map(
            function (array $row) use ($domain, $keyMap, $tableName, $tableComment): array {
                $sourceColumn = (string) ($row['COLUMN_NAME'] ?? '');
                [$key, $label] = $keyMap[$sourceColumn]
                    ?? [$sourceColumn, $this->columnLabel($sourceColumn, (string) ($row['COLUMN_COMMENT'] ?? ''))];

                return $this->normalizeColumnMeta(
                    domain: $domain,
                    table: $tableName,
                    tableComment: $tableComment,
                    column: $key,
                    label: $label,
                    dataType: (string) ($row['DATA_TYPE'] ?? ''),
                    isNullable: (string) ($row['IS_NULLABLE'] ?? 'YES'),
                    ordinalPosition: (int) ($row['ORDINAL_POSITION'] ?? 0),
                    sourceTitle: $sourceColumn
                );
            },
            $columns
        );
    }

    private function columnsForPersonnelActionDomain(string $domain): array
    {
        $keyMap = [
            'current_approval_request_id' => ['approval_request_no', '결재요청 순번'],
            'original_action_id' => ['original_action_no', '원본 발령번호'],
        ];
        $tableName = 'institution_personnel_actions';
        $tableComment = $this->queryTableComment($tableName);

        return array_map(
            function (array $row) use ($domain, $keyMap, $tableName, $tableComment): array {
                $sourceColumn = (string) ($row['COLUMN_NAME'] ?? '');
                [$key, $label] = $keyMap[$sourceColumn]
                    ?? [$sourceColumn, $this->columnLabel($sourceColumn, (string) ($row['COLUMN_COMMENT'] ?? ''))];

                return $this->normalizeColumnMeta(
                    domain: $domain,
                    table: $tableName,
                    tableComment: $tableComment,
                    column: $key,
                    label: $label,
                    dataType: (string) ($row['DATA_TYPE'] ?? ''),
                    isNullable: (string) ($row['IS_NULLABLE'] ?? 'YES'),
                    ordinalPosition: (int) ($row['ORDINAL_POSITION'] ?? 0),
                    sourceTitle: $sourceColumn
                );
            },
            $this->queryTableColumns($tableName)
        );
    }

    private function columnsForJobAssignmentDomain(string $domain): array
    {
        $definitions = [
            ['user_employees', 'sort_no', 'sort_no', '순번'],
            ['auth_users', 'username', 'username', '아이디'],
            ['user_employees', 'employee_name', 'employee_name', '직원명'],
            ['institution_job_assignments_employment_status_histories', 'status_code', 'employment_status', '재직상태'],
            ['institution_job_assignments_department_histories', 'department_id', 'department_name', '부서'],
            ['institution_job_assignments_position_histories', 'position_id', 'position_name', '직위·직책'],
            ['institution_job_assignments_job_histories', 'job_id', 'job_name', '직무'],
            ['institution_job_assignments_project_histories', 'project_id', 'primary_project_name', '주 프로젝트'],
            ['institution_job_assignments_workplace_histories', 'workplace_name_snapshot', 'workplace_name', '근무지'],
            ['institution_job_assignments_job_histories', 'start_date', 'assignment_start_date', '배치 시작일'],
            ['institution_job_assignments_job_histories', 'end_date', 'assignment_end_date', '배치 종료일'],
            ['institution_job_assignments_project_histories', 'status_code', 'assignment_status', '배치상태'],
            ['institution_job_assignments_project_histories', 'updated_at', 'updated_at', '수정일시'],
        ];

        $columnsByTable = [];
        $tableComments = [];
        foreach (array_unique(array_column($definitions, 0)) as $tableName) {
            $tableComments[$tableName] = $this->queryTableComment($tableName);
            foreach ($this->queryTableColumns($tableName) as $row) {
                $columnsByTable[$tableName][(string) ($row['COLUMN_NAME'] ?? '')] = $row;
            }
        }

        $columns = [];
        foreach ($definitions as $index => [$tableName, $sourceColumn, $key, $label]) {
            $row = $columnsByTable[$tableName][$sourceColumn] ?? null;
            if (!is_array($row)) {
                continue;
            }

            $columns[] = $this->normalizeColumnMeta(
                domain: $domain,
                table: $tableName,
                tableComment: (string) ($tableComments[$tableName] ?? ''),
                column: $key,
                label: $label,
                dataType: (string) ($row['DATA_TYPE'] ?? ''),
                isNullable: (string) ($row['IS_NULLABLE'] ?? 'YES'),
                ordinalPosition: $index + 1,
                sourceTitle: $sourceColumn,
                sourceOrdinalPosition: (int) ($row['ORDINAL_POSITION'] ?? 0),
                columnDefault: $row['COLUMN_DEFAULT'] ?? null
            );
        }

        return $columns;
    }

    private function columnsForPermissionAssignmentDomain(string $domain): array
    {
        return $this->columnsForTable('auth_permissions', $domain);
    }

    private function columnsForIndividualPermissionUsersDomain(string $domain): array
    {
        $definitions = [
            'auth_users' => [
                'id' => ['user_id', null],
                'username' => ['username', null],
                'approved' => ['approved', null],
                'is_active' => ['is_active', null],
                'role_id' => ['role_id', null],
            ],
            'auth_roles' => [
                'role_key' => ['role_key', null],
                'role_name' => ['role_name', null],
                'is_active' => ['role_active', '역할 상태'],
            ],
            'user_employees' => [
                'sort_no' => ['sort_no', null],
                'employee_name' => ['employee_name', null],
                'employment_status' => ['employment_status', null],
                'doc_retire_date' => ['doc_retire_date', null],
                'real_retire_date' => ['real_retire_date', null],
            ],
            'auth_user_permission_profiles' => [
                'permission_mode' => ['permission_mode', null],
            ],
            'auth_user_permissions' => [
                'id' => ['user_permission_count', '개인권한 수'],
            ],
        ];

        $columns = [];
        $sequence = 1;
        foreach ($definitions as $tableName => $selectedColumns) {
            $tableComment = $this->queryTableComment($tableName);
            foreach ($this->queryTableColumns($tableName) as $row) {
                $sourceColumn = (string) ($row['COLUMN_NAME'] ?? '');
                if (!isset($selectedColumns[$sourceColumn])) {
                    continue;
                }

                [$key, $labelOverride] = $selectedColumns[$sourceColumn];
                $isAggregate = $key === 'user_permission_count';
                $columns[] = $this->normalizeColumnMeta(
                    domain: $domain,
                    table: $tableName,
                    tableComment: $tableComment,
                    column: $key,
                    label: $labelOverride
                        ?? $this->columnLabel($sourceColumn, (string) ($row['COLUMN_COMMENT'] ?? '')),
                    dataType: $isAggregate ? 'bigint' : (string) ($row['DATA_TYPE'] ?? ''),
                    isNullable: $isAggregate ? 'YES' : (string) ($row['IS_NULLABLE'] ?? 'YES'),
                    ordinalPosition: $sequence++,
                    sourceTitle: $sourceColumn,
                    sourceOrdinalPosition: (int) ($row['ORDINAL_POSITION'] ?? 0),
                    columnDefault: $isAggregate ? 0 : ($row['COLUMN_DEFAULT'] ?? null)
                );
            }
        }

        return $columns;
    }

    private function columnsForPersonalExpenseItemDomain(string $domain): array
    {
        $allowed = [
            'sort_no', 'expense_date', 'expense_category', 'payment_method', 'receipt_type',
            'project_id', 'client_id', 'merchant_name', 'merchant_business_no',
            'merchant_representative', 'merchant_address', 'merchant_address_detail',
            'merchant_phone', 'item_name', 'item_specification', 'item_unit_name',
            'item_quantity', 'item_unit_price', 'item_supply_amount', 'item_vat_amount',
            'item_total_amount', 'item_description', 'item_memo',
        ];
        $byKey = [];
        foreach ($this->columnsForTable('approval_personal_expense_items', $domain) as $column) {
            $byKey[(string) ($column['key'] ?? '')] = $column;
        }
        $columns = [];
        foreach ($allowed as $index => $key) {
            if (!isset($byKey[$key])) {
                continue;
            }
            $column = $byKey[$key];
            $column['ordinal_position'] = $index + 1;
            $columns[] = $column;
        }
        return $columns;
    }

    private function columnsForStatutoryTable(string $tableName, string $domain): array
    {
        $hidden = $domain === 'statutory-standard'
            ? ['id', 'created_at', 'created_by']
            : ['id', 'standard_id', 'file_path', 'file_size', 'mime_type', 'created_at', 'created_by', 'updated_at', 'updated_by'];
        return array_map(static function (array $column) use ($hidden): array {
            if (in_array((string) ($column['key'] ?? ''), $hidden, true)) {
                $column['settings_visible'] = false;
            }
            return $column;
        }, $this->columnsForTable($tableName, $domain));
    }

    /**
     * @param list<string> $tableNames
     */
    private function columnsForMergedTables(string $domain, array $tableNames): array
    {
        $merged = [];
        $sequence = 1;
        $usedKeys = [];

        foreach ($tableNames as $tableName) {
            $resolvedTableName = trim((string) $tableName);
            if ($resolvedTableName === '') {
                continue;
            }

            $tableComment = $this->queryTableComment($resolvedTableName);

            foreach ($this->queryTableColumns($resolvedTableName) as $row) {
                $sourceColumn = (string) ($row['COLUMN_NAME'] ?? '');
                if ($sourceColumn === '') {
                    continue;
                }

                $columnKey = $this->mergedTableColumnKey($resolvedTableName, $sourceColumn, $usedKeys);

                $merged[$resolvedTableName . '.' . $sourceColumn] = $this->normalizeColumnMeta(
                    domain: $domain,
                    table: $resolvedTableName,
                    tableComment: $tableComment,
                    column: $columnKey,
                    label: $this->columnLabel($sourceColumn, (string) ($row['COLUMN_COMMENT'] ?? '')),
                    dataType: (string) ($row['DATA_TYPE'] ?? ''),
                    isNullable: (string) ($row['IS_NULLABLE'] ?? 'YES'),
                    ordinalPosition: $sequence++,
                    sourceTitle: $sourceColumn
                );
            }
        }

        return array_values($merged);
    }

    /**
     * @return list<string>
     */
    private function transactionMetaTables(): array
    {
        $tables = ['ledger_transactions'];

        if ($this->tableExists('ledger_transaction_items')
            && $this->tableColumnExists('ledger_transaction_items', 'transaction_id')
        ) {
            $tables[] = 'ledger_transaction_items';
        }

        if ($this->tableExists('ledger_transaction_settlements')
            && $this->tableColumnExists('ledger_transaction_settlements', 'transaction_id')
        ) {
            $tables[] = 'ledger_transaction_settlements';
        }

        return $tables;
    }

    private function columnsForTransactionItemDomain(string $domain): array
    {
        $tableName = $this->transactionItemMetaTable();
        if ($tableName === '') {
            return [];
        }

        return $this->columnsForTable($tableName, $domain); /*
            'item_date' => 'item_date',
            'item_name' => 'item_name',
            'item_specification' => 'specification',
            'item_unit_name' => 'unit_name',
            'item_quantity' => 'quantity',
            'item_unit_price' => 'unit_price',
            'item_foreign_unit_price' => 'foreign_unit_price',
            'item_foreign_amount' => 'foreign_amount',
            'item_supply_amount' => 'amount',
            'item_description' => 'description',
        ], [
            'specification' => '규격',
            'unit_name' => '단위',
            'quantity' => '수량',
            'unit_price' => '단가',
            'foreign_unit_price' => '외화단가',
            'foreign_amount' => '외화금액',
            'amount' => '공급가액',
            'description' => '적요',
        ]); */
    }

    private function columnsForTransactionSettlementDomain(string $domain): array
    {
        $tableName = $this->transactionSettlementMetaTable();
        if ($tableName === '') {
            return [];
        }

        return $this->columnsForTable($tableName, $domain); /*
            'settlement_type' => 'settlement_type',
            'amount_sign' => 'amount_sign',
            'amount' => 'amount',
            'settlement_description' => 'description',
        ], [
            'settlement_type' => '정산유형',
            'amount_sign' => '가감유형',
            'amount' => '정산금액',
            'description' => '적요',
        ]); */
    }

    /**
     * @param array<string, string> $keyMap
     * @param array<string, string> $labelOverrides
     */
    private function columnsForMappedTable(string $domain, string $tableName, array $keyMap, array $labelOverrides = []): array
    {
        if ($tableName === '') {
            return [];
        }

        $sequence = 1;
        $tableComment = $this->queryTableComment($tableName);
        $columns = [];

        foreach ($this->queryTableColumns($tableName) as $row) {
            $sourceColumn = (string) ($row['COLUMN_NAME'] ?? '');
            if ($sourceColumn === '' || !isset($keyMap[$sourceColumn])) {
                continue;
            }

            $key = (string) $keyMap[$sourceColumn];
            $label = (string) ($labelOverrides[$key]
                ?? $this->columnLabel($sourceColumn, (string) ($row['COLUMN_COMMENT'] ?? '')));

            $columns[] = $this->normalizeColumnMeta(
                domain: $domain,
                table: $tableName,
                tableComment: $tableComment,
                column: $key,
                label: $label,
                dataType: (string) ($row['DATA_TYPE'] ?? ''),
                isNullable: (string) ($row['IS_NULLABLE'] ?? 'YES'),
                ordinalPosition: $sequence++,
                sourceTitle: $sourceColumn
            );
        }

        return $columns;
    }

    private function transactionItemMetaTable(): string
    {
        if ($this->tableExists('ledger_transaction_items')
            && $this->tableColumnExists('ledger_transaction_items', 'transaction_id')
        ) {
            return 'ledger_transaction_items';
        }

        return '';
    }

    private function transactionSettlementMetaTable(): string
    {
        if ($this->tableExists('ledger_transaction_settlements')
            && $this->tableColumnExists('ledger_transaction_settlements', 'transaction_id')
        ) {
            return 'ledger_transaction_settlements';
        }

        return '';
    }

    private function mergedTableColumnKey(string $tableName, string $sourceColumn, array &$usedKeys): string
    {
        $baseKey = trim($sourceColumn);
        if ($baseKey === '') {
            return '';
        }

        if (!isset($usedKeys[$baseKey])) {
            $usedKeys[$baseKey] = 1;
            return $baseKey;
        }

        $candidate = $tableName . '.' . $baseKey;
        if (!isset($usedKeys[$candidate])) {
            $usedKeys[$candidate] = 1;
            return $candidate;
        }

        $suffix = $usedKeys[$baseKey];
        do {
            $candidate = $tableName . '.' . $baseKey . '.' . $suffix;
            $suffix++;
        } while (isset($usedKeys[$candidate]));

        $usedKeys[$baseKey] = $suffix;
        $usedKeys[$candidate] = 1;

        return $candidate;
    }

    private function normalizeColumnMeta(
        string $domain,
        string $table,
        string $tableComment,
        string $column,
        string $label,
        string $dataType,
        string $isNullable,
        int $ordinalPosition,
        string $sourceTitle = '',
        int $sourceOrdinalPosition = 0,
        mixed $columnDefault = null
    ): array {
        $sensitive = preg_match('/(?:identifier|resident|registration)_.*(?:snapshot|encrypted|cipher)/i', $column) === 1;
        return [
            'domain' => $domain,
            'table' => $table,
            'table_comment' => $tableComment !== '' ? $tableComment : $table,
            'key' => $column,
            'label' => $label !== '' ? $label : $column,
            'source_title' => $sourceTitle !== '' ? $sourceTitle : $column,
            'source_column' => $sourceTitle !== '' ? $sourceTitle : $column,
            'data_type' => $dataType,
            'is_nullable' => strtoupper($isNullable) === 'NO' ? 'NO' : 'YES',
            'column_default' => $columnDefault,
            'ordinal_position' => max(0, $ordinalPosition),
            'source_ordinal_position' => max(0, $sourceOrdinalPosition ?: $ordinalPosition),
            'column_type' => 'physical',
            'required' => strtoupper($isNullable) === 'NO',
            'settings_visible' => !$sensitive,
            'settings_order' => max(0, $ordinalPosition),
        ];
    }

    private function queryTableColumns(string $tableName): array
    {
        if ($tableName === '') {
            return [];
        }

        return $this->schemaModel->getTableColumns($tableName);
    }

    private function queryTableComment(string $tableName): string
    {
        if ($tableName === '') {
            return '';
        }

        return trim($this->schemaModel->getTableComment($tableName));
    }

    private function tableExists(string $tableName): bool
    {
        if ($tableName === '') {
            return false;
        }

        return $this->schemaModel->tableExists($tableName);
    }

    private function tableColumnExists(string $tableName, string $columnName): bool
    {
        if ($tableName === '' || $columnName === '') {
            return false;
        }

        return $this->schemaModel->columnExists($tableName, $columnName);
    }

    private function columnLabel(string $columnName, string $comment): string
    {
        $normalizedComment = trim($comment);
        if ($normalizedComment !== '') {
            return $normalizedComment;
        }

        $fallbackLabels = [
            'sort_no' => '순번',
            'id' => 'ID',
            'standard_id' => '법정기준 ID',
            'standard_code' => '기준코드',
            'category_code' => '업무분류',
            'standard_name' => '기준명',
            'value_structure_code' => '값 구조',
            'calculation_purpose_code' => '계산 목적',
            'description' => '설명',
            'is_active' => '사용 여부',
            'revision_no' => '개정번호',
            'revision_title' => '개정 제목',
            'revision_reason' => '개정 사유',
            'status_code' => '상태',
            'effective_from' => '시행 시작일',
            'effective_to' => '시행 종료일',
            'published_date' => '공표일',
            'calculation_version' => '계산 버전',
            'request_key' => '요청 식별키',
            'reviewed_at' => '검토일시',
            'reviewed_by' => '검토자',
            'confirmed_at' => '시행 확정일시',
            'confirmed_by' => '시행 확정자',
            'created_at' => '생성일시',
            'created_by' => '생성자',
            'updated_at' => '수정일시',
            'updated_by' => '수정자',
            'deleted_at' => '삭제일시',
            'deleted_by' => '삭제자',
            'debit_account_id' => '차변계정 ID',
            'credit_account_id' => '대변계정 ID',
            'vat_account_id' => '부가세계정 ID',
            'usage_count' => '확정 사용 횟수',
            'last_used_at' => '최종 확정 사용일시',
            'confidence_score' => '규칙 신뢰도',
            'rate_value' => '요율',
            'amount_value' => '금액',
            'employee_rate' => '근로자 부담률',
            'employer_rate' => '사업주 부담률',
            'additional_employer_rate' => '추가 사업주 부담률',
            'minimum_base_amount' => '기준금액 하한',
            'maximum_base_amount' => '기준금액 상한',
            'minimum_result_amount' => '결과금액 하한',
            'maximum_result_amount' => '결과금액 상한',
            'age_from' => '최소 연령',
            'age_to' => '최대 연령',
            'month_count_threshold' => '개월수 기준',
            'boolean_value' => '적용 여부',
            'date_value' => '날짜',
            'time_value' => '시간',
            'text_value' => '문자값',
            'rounding_method_code' => '끝수처리',
            'rounding_unit' => '처리 단위',
            'decimal_scale' => '소수 자릿수',
            'lower_bound_amount' => '과세표준 시작금액',
            'upper_bound_amount' => '과세표준 종료금액',
            'progressive_deduction_amount' => '누진공제액',
            'fixed_amount' => '고정세액',
            'income_from_amount' => '급여 시작액',
            'income_to_amount' => '급여 종료액',
            'dependent_count' => '부양가족 수',
            'child_count' => '자녀 수',
            'selection_rate_percent' => '선택비율(%)',
            'tax_amount' => '세액',
            'worker_type_code' => '근로자 유형',
            'industry_code' => '업종 코드',
            'business_size_code' => '사업 규모',
            'is_construction' => '건설업 여부',
            'workplace_scope_code' => '사업장 범위',
        ];

        if (isset($fallbackLabels[$columnName])) {
            return $fallbackLabels[$columnName];
        }

        return $columnName;
    }
}
