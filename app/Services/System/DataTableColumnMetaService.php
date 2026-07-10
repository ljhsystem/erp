<?php

namespace App\Services\System;

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
        'approval-template' => ['table' => 'user_approval_templates'],
        'approval-template-step' => ['table' => 'user_approval_template_steps'],
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
        'funds-bank-transaction' => ['table' => 'ledger_evidence_bank_transaction'],
        'evidence-bank-transaction' => ['table' => 'ledger_evidence_bank_transaction'],
        'evidence-tax-invoice' => ['table' => 'ledger_evidence_tax_invoice'],
        'evidence-tax-invoice-manual' => ['table' => 'ledger_evidence_tax_invoice_manual'],
        'evidence-cash-receipt' => ['table' => 'ledger_evidence_cash_receipt'],
        'evidence-card' => ['table' => 'ledger_evidence_card_hometax'],
        'evidence-card-hometax' => ['table' => 'ledger_evidence_card_hometax'],
        'evidence-card-statement' => ['table' => 'ledger_evidence_card_statement'],
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function columnsForDomain(string $domain): array
    {
        $resolvedDomain = trim($domain);
        if ($resolvedDomain === '' || !isset(self::DOMAIN_MAP[$resolvedDomain])) {
            return [];
        }

        $config = self::DOMAIN_MAP[$resolvedDomain];
        if (($config['composite'] ?? '') === 'employee') {
            return $this->columnsForEmployeeDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'permission-assignment') {
            return $this->columnsForPermissionAssignmentDomain($resolvedDomain);
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
                'ledger_transaction_links',
            ]);
        }
        return $this->columnsForTable((string) ($config['table'] ?? ''), $resolvedDomain);
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
                'label_overrides' => [],
            ],
            [
                'table' => 'auth_users',
                'key_map' => [
                    'id' => 'auth_user_id',
                    'created_at' => 'user_created_at',
                    'created_by' => 'user_created_by',
                    'updated_at' => 'user_updated_at',
                    'updated_by' => 'user_updated_by',
                    'deleted_at' => 'deleted_at',
                    'deleted_by' => 'deleted_by',
                ],
                'label_overrides' => [
                    'auth_user_id' => '사용자계정ID',
                    'user_created_at' => '생성일시',
                    'user_created_by' => '생성자',
                    'user_updated_at' => '수정일시',
                    'user_updated_by' => '수정자',
                    'approved_by' => '승인자',
                    'password_updated_by' => '비밀번호변경자',
                    'deleted_at' => '비활성화일시',
                    'deleted_by' => '비활성화처리자',
                ],
            ],
        ];

        $merged = [];
        $sequence = 1;

        foreach ($definitions as $definition) {
            $tableName = (string) ($definition['table'] ?? '');
            $keyMap = (array) ($definition['key_map'] ?? []);
            $labelOverrides = (array) ($definition['label_overrides'] ?? []);
            $tableComment = $this->queryTableComment($tableName);

            foreach ($this->queryTableColumns($tableName) as $row) {
                $sourceColumn = (string) ($row['COLUMN_NAME'] ?? '');
                if ($sourceColumn === '') {
                    continue;
                }

                $key = (string) ($keyMap[$sourceColumn] ?? $sourceColumn);
                $label = (string) ($labelOverrides[$key]
                    ?? $this->columnLabel($sourceColumn, (string) ($row['COLUMN_COMMENT'] ?? '')));

                $merged[$key] = $this->normalizeColumnMeta(
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
        }

        return array_values($merged);
    }

    private function columnsForPermissionAssignmentDomain(string $domain): array
    {
        $merged = [];
        $sequence = 1;
        $duplicateKeyMap = [
            'auth_role_permissions' => [
                'id' => 'role_permission_id',
                'created_at' => 'role_permission_created_at',
                'created_by' => 'role_permission_created_by',
            ],
        ];
        $labelOverrides = [
            'role_permission_id' => '권한부여',
            'role_permission_created_at' => '권한부여 생성일시',
            'role_permission_created_by' => '권한부여 생성자',
        ];

        foreach (['auth_permissions', 'auth_role_permissions'] as $tableName) {
            $keyMap = $duplicateKeyMap[$tableName] ?? [];
            $tableComment = $this->queryTableComment($tableName);

            foreach ($this->queryTableColumns($tableName) as $row) {
                $sourceColumn = (string) ($row['COLUMN_NAME'] ?? '');
                if ($sourceColumn === '') {
                    continue;
                }

                $key = (string) ($keyMap[$sourceColumn] ?? $sourceColumn);
                $label = (string) ($labelOverrides[$key]
                    ?? $this->columnLabel($sourceColumn, (string) ($row['COLUMN_COMMENT'] ?? '')));

                $merged[] = $this->normalizeColumnMeta(
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
        }

        return $merged;
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

        foreach (['ledger_transaction_items', 'ledger_transaction_lines'] as $tableName) {
            if ($this->tableExists($tableName) && $this->tableColumnExists($tableName, 'transaction_id')) {
                $tables[] = $tableName;
                break;
            }
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
            'item_tax_type' => 'tax_type',
            'item_description' => 'description',
        ], [
            'specification' => '규격',
            'unit_name' => '단위',
            'quantity' => '수량',
            'unit_price' => '단가',
            'foreign_unit_price' => '외화단가',
            'foreign_amount' => '외화금액',
            'amount' => '공급가액',
            'tax_type' => '세금구분',
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
        foreach (['ledger_transaction_items', 'ledger_transaction_lines'] as $tableName) {
            if ($this->tableExists($tableName) && $this->tableColumnExists($tableName, 'transaction_id')) {
                return $tableName;
            }
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
        string $sourceTitle = ''
    ): array {
        $visibilitySource = $sourceTitle !== '' ? $sourceTitle : $column;

        $isSystemManaged = $this->isSystemManagedColumn($visibilitySource);

        return [
            'domain' => $domain,
            'table' => $table,
            'table_comment' => $tableComment !== '' ? $tableComment : $table,
            'key' => $column,
            'label' => $label !== '' ? $label : $column,
            'source_title' => $sourceTitle !== '' ? $sourceTitle : $column,
            'data_type' => $dataType,
            'is_nullable' => strtoupper($isNullable) === 'NO' ? 'NO' : 'YES',
            'ordinal_position' => max(0, $ordinalPosition),
            'column_type' => 'physical',
            'required' => !$isSystemManaged && strtoupper($isNullable) === 'NO',
            'settings_visible' => !$isSystemManaged,
            'settings_order' => max(0, $ordinalPosition),
        ];
    }

    private function isSystemManagedColumn(string $columnName): bool
    {
        $normalized = trim($columnName);
        if ($normalized === '' || $normalized === 'sort_no') {
            return false;
        }

        $exactMatches = [
            'id',
            'created_at',
            'created_by',
            'updated_at',
            'updated_by',
            'deleted_at',
            'deleted_by',
            'deleted_reason',
            'restore_at',
            'restore_by',
            'restored_at',
            'restored_by',
            'version',
            'row_version',
        ];

        if (in_array($normalized, $exactMatches, true)) {
            return true;
        }

        return preg_match('/^(created|updated|deleted|restore|restored|approved|rejected|processed|uploaded|password_updated)_(at|by)$/', $normalized) === 1;
    }

    private function queryTableColumns(string $tableName): array
    {
        if ($tableName === '') {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT COLUMN_NAME, COLUMN_COMMENT, DATA_TYPE, IS_NULLABLE, ORDINAL_POSITION
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
            ORDER BY ORDINAL_POSITION ASC
        ");
        $stmt->execute([':table_name' => $tableName]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function queryTableComment(string $tableName): string
    {
        if ($tableName === '') {
            return '';
        }

        static $cache = [];
        if (array_key_exists($tableName, $cache)) {
            return $cache[$tableName];
        }

        $stmt = $this->pdo->prepare("
            SELECT TABLE_COMMENT
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
            LIMIT 1
        ");
        $stmt->execute([':table_name' => $tableName]);

        $cache[$tableName] = trim((string) ($stmt->fetchColumn() ?: ''));
        return $cache[$tableName];
    }

    private function tableExists(string $tableName): bool
    {
        if ($tableName === '') {
            return false;
        }

        static $cache = [];
        if (array_key_exists($tableName, $cache)) {
            return $cache[$tableName];
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
            LIMIT 1
        ");
        $stmt->execute([':table_name' => $tableName]);

        $cache[$tableName] = (bool) $stmt->fetchColumn();
        return $cache[$tableName];
    }

    private function tableColumnExists(string $tableName, string $columnName): bool
    {
        if ($tableName === '' || $columnName === '') {
            return false;
        }

        static $cache = [];
        $cacheKey = $tableName . '.' . $columnName;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
            LIMIT 1
        ");
        $stmt->execute([
            ':table_name' => $tableName,
            ':column_name' => $columnName,
        ]);

        $cache[$cacheKey] = (bool) $stmt->fetchColumn();
        return $cache[$cacheKey];
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
            'debit_account_id' => '차변계정 ID',
            'credit_account_id' => '대변계정 ID',
            'vat_account_id' => '부가세계정 ID',
            'usage_count' => '확정 사용 횟수',
            'last_used_at' => '최종 확정 사용일시',
            'confidence_score' => '규칙 신뢰도',
        ];

        if (isset($fallbackLabels[$columnName])) {
            return $fallbackLabels[$columnName];
        }

        return $columnName;
    }
}
