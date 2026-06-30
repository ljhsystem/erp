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
        'ledger-account' => ['table' => 'ledger_accounts'],
        'transaction' => ['table' => 'ledger_transactions'],
        'ledger-journal-rule' => ['table' => 'ledger_journal_rules'],
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
                    ordinalPosition: (int) ($row['ORDINAL_POSITION'] ?? 0)
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
            'required' => strtoupper($isNullable) === 'NO',
            'settings_visible' => true,
            'settings_order' => max(0, $ordinalPosition),
        ];
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
