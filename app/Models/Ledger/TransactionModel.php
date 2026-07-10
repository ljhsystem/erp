<?php

namespace App\Models\Ledger;

use Core\Helpers\ActorHelper;
use Core\Database;
use PDO;

class TransactionModel
{
    protected string $table = 'ledger_transactions';

    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getList(array $filters = []): array
    {
        $lineStatusJoin = $this->lineStatusJoinSql();
        $hasOperationType = $this->tableColumnExists($this->table, 'operation_type');
        $hasSourceType = $this->tableColumnExists($this->table, 'source_type');
        $hasImportType = $this->tableColumnExists($this->table, 'import_type');
        $operationTypeSelect = $hasOperationType
            ? "COALESCE(ot.code_name, '') AS operation_type_name,
                COALESCE(NULLIF(ot.code_name, ''), '') AS operation_type,
                t.operation_type AS operation_type_code,"
            : "'' AS operation_type_name,
                '' AS operation_type,
                '' AS operation_type_code,";
        $sourceTypeSelect = $hasSourceType
            ? "COALESCE(st.code_name, '') AS source_type_name,"
            : "'' AS source_type_name,";
        $importTypeSelect = $hasImportType
            ? "COALESCE(it.code_name, '') AS import_type_name,"
            : "'' AS import_type_name,";
        $sql = "
            SELECT
                t.*,
                COALESCE(bu.code_name, '') AS business_unit_name,
                COALESCE(td.code_name, '') AS transaction_direction_name,
                {$operationTypeSelect}
                {$sourceTypeSelect}
                {$importTypeSelect}
                COALESCE(cur.code_name, '') AS currency_name,
                COALESCE(NULLIF(bu.code_name, ''), '') AS business_unit,
                COALESCE(NULLIF(td.code_name, ''), '') AS transaction_direction,
                COALESCE(NULLIF(cur.code_name, ''), '') AS currency,
                t.business_unit AS business_unit_code,
                t.transaction_direction AS transaction_direction_code,
                t.currency AS currency_code,
                COALESCE(sc.client_name, '') AS client_name,
                COALESCE(sp.project_name, '') AS project_name,
                COALESCE(sba.account_name, '') AS bank_account_name,
                COALESCE(scd.card_name, '') AS card_name,
                COALESCE(swt.team_name, '') AS team_name,
                COALESCE(ue.employee_name, '') AS employee_name,
                COALESCE(NULLIF(sc.client_name, ''), '') AS client_id,
                COALESCE(NULLIF(sp.project_name, ''), '') AS project_id,
                COALESCE(NULLIF(sba.account_name, ''), '') AS bank_account_id,
                COALESCE(NULLIF(scd.card_name, ''), '') AS card_id,
                COALESCE(NULLIF(swt.team_name, ''), '') AS team_id,
                COALESCE(NULLIF(ue.employee_name, ''), '') AS employee_id,
                t.client_id AS client_uuid,
                t.project_id AS project_uuid,
                t.bank_account_id AS bank_account_uuid,
                t.card_id AS card_uuid,
                t.team_id AS team_uuid,
                t.employee_id AS employee_uuid,
                COALESCE(tls.item_count, 0) AS transaction_line_count,
                COALESCE(tls.incomplete_count, 0) AS transaction_line_incomplete_count,
                t.created_by AS created_by_name,
                t.updated_by AS updated_by_name,
                t.deleted_by AS deleted_by_name,
                CASE
                    WHEN COALESCE(tls.item_count, 0) = 0 THEN 'NONE'
                    WHEN COALESCE(tls.incomplete_count, 0) > 0 THEN 'INCOMPLETE'
                    ELSE 'COMPLETE'
                END AS transaction_line_status
            FROM {$this->table} t
            LEFT JOIN system_codes bu
                ON bu.deleted_at IS NULL
               AND bu.is_active = 1
               AND bu.code_group = 'BUSINESS_UNIT'
               AND bu.code = t.business_unit
            LEFT JOIN system_codes td
                ON td.deleted_at IS NULL
               AND td.is_active = 1
               AND td.code_group = 'TRANSACTION_DIRECTION'
               AND td.code = t.transaction_direction
             " . ($hasOperationType ? "
             LEFT JOIN system_codes ot
                 ON ot.deleted_at IS NULL
                AND ot.is_active = 1
                AND ot.code_group = 'OPERATION_TYPE'
                AND ot.code = t.operation_type
             " : "") . ($hasSourceType ? "
             LEFT JOIN system_codes st
                 ON st.deleted_at IS NULL
                AND st.is_active = 1
                AND st.code_group = 'SOURCE_TYPE'
                AND st.code = t.source_type
             " : "") . ($hasImportType ? "
             LEFT JOIN system_codes it
                 ON it.deleted_at IS NULL
                AND it.is_active = 1
                AND it.code_group = 'IMPORT_TYPE'
                AND it.code = t.import_type
             " : "") . "
             LEFT JOIN system_codes cur
                 ON cur.deleted_at IS NULL
                AND cur.is_active = 1
               AND cur.code_group = 'CURRENCY'
               AND cur.code = t.currency
            LEFT JOIN system_clients sc
                ON t.client_id = sc.id
            LEFT JOIN system_projects sp
                ON t.project_id = sp.id
            LEFT JOIN system_bank_accounts sba
                ON t.bank_account_id = sba.id
            LEFT JOIN system_cards scd
                ON t.card_id = scd.id
            LEFT JOIN system_work_teams swt
                ON t.team_id = swt.id
            LEFT JOIN user_employees ue
                ON t.employee_id = ue.id
            {$lineStatusJoin}
            WHERE t.deleted_at IS NULL
        ";

        $params = [];

        if (!empty($filters['business_unit'])) {
            $sql .= " AND t.business_unit = :business_unit";
            $params[':business_unit'] = $filters['business_unit'];
        }

        if (!empty($filters['status'])) {
            $status = strtolower(trim((string) $filters['status']));
            if (in_array($status, ['draft', 'completed', 'closed', 'cancelled'], true)) {
                $sql .= " AND t.status = :status";
                $params[':status'] = $status;
            }
        }

        if (!empty($filters['match_status'])) {
            $sql .= " AND t.match_status = :match_status";
            $params[':match_status'] = $filters['match_status'];
        }

        if (!empty($filters['project_id'])) {
            $sql .= " AND t.project_id = :project_id";
            $params[':project_id'] = $filters['project_id'];
        }

        if (!empty($filters['client_id'])) {
            $sql .= " AND t.client_id = :client_id";
            $params[':client_id'] = $filters['client_id'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND t.transaction_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND t.transaction_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($filters['updated_from'])) {
            $sql .= " AND t.updated_at >= :updated_from";
            $params[':updated_from'] = $filters['updated_from'];
        }

        if (!empty($filters['updated_to'])) {
            $sql .= " AND t.updated_at <= :updated_to";
            $params[':updated_to'] = $filters['updated_to'];
        }

        if (!empty($filters['search_conditions']) && is_array($filters['search_conditions'])) {
            $fieldMap = [
                'sort_no' => 't.sort_no',
                'business_unit' => "(COALESCE(bu.code_name, '') LIKE %s OR t.business_unit LIKE %s)",
                'business_unit_name' => 'bu.code_name',
                'transaction_direction' => "(COALESCE(td.code_name, '') LIKE %s OR t.transaction_direction LIKE %s)",
                'transaction_direction_name' => 'td.code_name',
                'transaction_date' => 't.transaction_date',
                'bank_account_id' => "(COALESCE(sba.account_name, '') LIKE %s OR t.bank_account_id LIKE %s)",
                'bank_account_name' => 'sba.account_name',
                'card_id' => "(COALESCE(scd.card_name, '') LIKE %s OR t.card_id LIKE %s)",
                'card_name' => 'scd.card_name',
                'team_id' => "(COALESCE(swt.team_name, '') LIKE %s OR t.team_id LIKE %s)",
                'team_name' => 'swt.team_name',
                'employee_id' => "(COALESCE(ue.employee_name, '') LIKE %s OR t.employee_id LIKE %s)",
                'employee_name' => 'ue.employee_name',
                'project_id' => "(COALESCE(sp.project_name, '') LIKE %s OR t.project_id LIKE %s)",
                'project_name' => 'sp.project_name',
                'client_id' => "(COALESCE(sc.client_name, '') LIKE %s OR t.client_id LIKE %s)",
                'client_name' => 'sc.client_name',
                'foreign_amount' => 't.transaction_foreign_amount',
                'supply_amount' => 't.transaction_supply_amount',
                'settlement_amount' => 't.transaction_settlement_amount',
                'final_amount' => 't.transaction_final_amount',
                'transaction_foreign_amount' => 't.transaction_foreign_amount',
                'transaction_supply_amount' => 't.transaction_supply_amount',
                'transaction_settlement_amount' => 't.transaction_settlement_amount',
                'transaction_final_amount' => 't.transaction_final_amount',
                'transaction_description' => 't.transaction_description',
                'currency' => "(COALESCE(cur.code_name, '') LIKE %s OR t.currency LIKE %s)",
                'currency_name' => 'cur.code_name',
                'transaction_exchange_rate' => 't.transaction_exchange_rate',
                'status' => 't.status',
                'match_status' => 't.match_status',
                'transaction_note' => 't.transaction_note',
                'transaction_memo' => 't.transaction_memo',
                'created_at' => 't.created_at',
                'created_by' => 't.created_by',
                'updated_at' => 't.updated_at',
                'updated_by' => 't.updated_by',
                'deleted_at' => 't.deleted_at',
                'deleted_by' => 't.deleted_by',
            ];
            if ($hasOperationType) {
                $fieldMap['operation_type'] = "(COALESCE(ot.code_name, '') LIKE %s OR t.operation_type LIKE %s)";
                $fieldMap['operation_type_name'] = 'ot.code_name';
            }

            foreach ($filters['search_conditions'] as $index => $condition) {
                if (!is_array($condition)) {
                    continue;
                }

                $field = (string) ($condition['field'] ?? '');
                $value = trim((string) ($condition['value'] ?? ''));
                if ($value === '' || !isset($fieldMap[$field])) {
                    continue;
                }

                $param = ':search_' . $index;
                $fieldExpr = $fieldMap[$field];
                if (str_contains($fieldExpr, '%s')) {
                    $sql .= ' AND ' . sprintf($fieldExpr, $param, $param);
                } else {
                    $sql .= " AND {$fieldExpr} LIKE {$param}";
                }
                $params[$param] = '%' . $value . '%';
            }
        }

        $sql .= " ORDER BY t.sort_no ASC, t.transaction_date ASC, t.created_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $rows = ActorHelper::enrichActorNames($rows, [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
            'deleted_by_name' => 'deleted_by',
        ]);

        return $this->stripHeaderTaxTypeRows($rows);
    }

    private function lineStatusJoinSql(): string
    {
        $source = $this->resolveLineStatusSource();
        if ($source === null) {
            return "
                LEFT JOIN (
                    SELECT NULL AS transaction_id, 0 AS item_count, 0 AS incomplete_count
                ) tls ON tls.transaction_id = t.id
            ";
        }

        $where = $source['has_deleted_at']
            ? 'WHERE deleted_at IS NULL'
            : '';
        $itemNameExpr = $source['has_item_name']
            ? "TRIM(COALESCE(item_name, '')) = ''"
            : '0 = 1';
        $amountExpr = $source['amount_expr'];

        return "
            LEFT JOIN (
                SELECT
                    transaction_id,
                    COUNT(*) AS item_count,
                    SUM(CASE
                        WHEN {$itemNameExpr} OR {$amountExpr} = 0 THEN 1
                        ELSE 0
                    END) AS incomplete_count
                FROM {$source['table']}
                {$where}
                GROUP BY transaction_id
            ) tls
                ON tls.transaction_id = t.id
        ";
    }

    private function resolveLineStatusSource(): ?array
    {
        foreach (['ledger_transaction_items', 'ledger_transaction_lines'] as $tableName) {
            if (!$this->tableExists($tableName) || !$this->tableColumnExists($tableName, 'transaction_id')) {
                continue;
            }

            return [
                'table' => $tableName,
                'has_deleted_at' => $this->tableColumnExists($tableName, 'deleted_at'),
                'has_item_name' => $this->tableColumnExists($tableName, 'item_name'),
                'amount_expr' => $this->lineAmountExpression($tableName),
            ];
        }

        return null;
    }

    private function lineAmountExpression(string $tableName): string
    {
        if ($this->tableColumnExists($tableName, 'item_supply_amount')) {
            return 'COALESCE(item_supply_amount, 0)';
        }
        if ($this->tableColumnExists($tableName, 'amount')) {
            return 'COALESCE(amount, 0)';
        }
        if ($this->tableColumnExists($tableName, 'total_amount')) {
            return 'COALESCE(total_amount, 0)';
        }
        if ($this->tableColumnExists($tableName, 'supply_amount') || $this->tableColumnExists($tableName, 'vat_amount')) {
            $supply = $this->tableColumnExists($tableName, 'supply_amount') ? 'COALESCE(supply_amount, 0)' : '0';
            $vat = $this->tableColumnExists($tableName, 'vat_amount') ? 'COALESCE(vat_amount, 0)' : '0';

            return "({$supply} + {$vat})";
        }

        return '1';
    }

    private function tableExists(string $tableName): bool
    {
        static $cache = [];
        if (array_key_exists($tableName, $cache)) {
            return $cache[$tableName];
        }

        $stmt = $this->db->prepare("
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
        static $cache = [];
        $key = $tableName . '.' . $columnName;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $stmt = $this->db->prepare("
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
        $cache[$key] = (bool) $stmt->fetchColumn();

        return $cache[$key];
    }

    public function getUnpostedList(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                t.*,
                COALESCE(sc.client_name, '') AS client_name,
                COALESCE(sp.project_name, '') AS project_name
            FROM {$this->table} t
            LEFT JOIN system_clients sc
                ON t.client_id = sc.id
            LEFT JOIN system_projects sp
                ON t.project_id = sp.id
            WHERE t.deleted_at IS NULL
              AND t.status = 'draft'
              AND t.match_status = 'none'
            ORDER BY t.sort_no ASC, t.transaction_date ASC, t.created_at ASC
        ");
        $stmt->execute();

        return $this->stripHeaderTaxTypeRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function getById(string $id): ?array
    {
        $hasOperationType = $this->tableColumnExists($this->table, 'operation_type');
        $hasSourceType = $this->tableColumnExists($this->table, 'source_type');
        $hasImportType = $this->tableColumnExists($this->table, 'import_type');
        $operationTypeSelect = $hasOperationType
            ? "COALESCE(ot.code_name, '') AS operation_type_name,"
            : "'' AS operation_type_name,";
        $sourceTypeSelect = $hasSourceType
            ? "COALESCE(st.code_name, '') AS source_type_name,"
            : "'' AS source_type_name,";
        $importTypeSelect = $hasImportType
            ? "COALESCE(it.code_name, '') AS import_type_name,"
            : "'' AS import_type_name,";
        $stmt = $this->db->prepare("
            SELECT
                t.*,
                COALESCE(bu.code_name, '') AS business_unit_name,
                COALESCE(td.code_name, '') AS transaction_direction_name,
                {$operationTypeSelect}
                {$sourceTypeSelect}
                {$importTypeSelect}
                COALESCE(cur.code_name, '') AS currency_name,
                COALESCE(sc.client_name, '') AS client_name,
                COALESCE(sp.project_name, '') AS project_name,
                COALESCE(sba.account_name, '') AS bank_account_name,
                COALESCE(scd.card_name, '') AS card_name,
                COALESCE(swt.team_name, '') AS team_name,
                COALESCE(ue.employee_name, '') AS employee_name,
                t.created_by AS created_by_name,
                t.updated_by AS updated_by_name,
                t.deleted_by AS deleted_by_name
            FROM {$this->table} t
            LEFT JOIN system_codes bu
                ON bu.deleted_at IS NULL
               AND bu.is_active = 1
               AND bu.code_group = 'BUSINESS_UNIT'
               AND bu.code = t.business_unit
            LEFT JOIN system_codes td
                ON td.deleted_at IS NULL
               AND td.is_active = 1
               AND td.code_group = 'TRANSACTION_DIRECTION'
               AND td.code = t.transaction_direction
            " . ($hasOperationType ? "
            LEFT JOIN system_codes ot
                ON ot.deleted_at IS NULL
               AND ot.is_active = 1
               AND ot.code_group = 'OPERATION_TYPE'
               AND ot.code = t.operation_type
            " : "") . ($hasSourceType ? "
            LEFT JOIN system_codes st
                ON st.deleted_at IS NULL
               AND st.is_active = 1
               AND st.code_group = 'SOURCE_TYPE'
               AND st.code = t.source_type
            " : "") . ($hasImportType ? "
            LEFT JOIN system_codes it
                ON it.deleted_at IS NULL
               AND it.is_active = 1
               AND it.code_group = 'IMPORT_TYPE'
               AND it.code = t.import_type
            " : "") . "
            LEFT JOIN system_codes cur
                ON cur.deleted_at IS NULL
               AND cur.is_active = 1
               AND cur.code_group = 'CURRENCY'
               AND cur.code = t.currency
            LEFT JOIN system_clients sc
                ON t.client_id = sc.id
            LEFT JOIN system_projects sp
                ON t.project_id = sp.id
            LEFT JOIN system_bank_accounts sba
                ON t.bank_account_id = sba.id
            LEFT JOIN system_cards scd
                ON t.card_id = scd.id
            LEFT JOIN system_work_teams swt
                ON t.team_id = swt.id
            LEFT JOIN user_employees ue
                ON t.employee_id = ue.id
            WHERE t.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row !== null) {
            unset($row['tax_type']);
            $row = ActorHelper::enrichActorNamesRow($row, [
                'created_by_name' => 'created_by',
                'updated_by_name' => 'updated_by',
                'deleted_by_name' => 'deleted_by',
            ]);
        }

        return $row;
    }

    public function insert(array $data): bool
    {
        $allowed = [
            'id',
            'sort_no',
            'business_unit',
            'transaction_direction',
            'currency',
            'client_id',
            'project_id',
            'bank_account_id',
            'card_id',
            'team_id',
            'employee_id',
            'transaction_date',
            'transaction_description',
            'transaction_exchange_rate',
            'transaction_foreign_amount',
            'transaction_supply_amount',
            'transaction_settlement_amount',
            'transaction_final_amount',
            'transaction_note',
            'transaction_memo',
            'status',
            'match_status',
            'created_at',
            'created_by',
            'updated_at',
            'updated_by',
            'deleted_at',
            'deleted_by',
        ];
        if ($this->tableColumnExists($this->table, 'operation_type')) {
            $allowed[] = 'operation_type';
        }

        $payload = $this->filterData($data, $allowed);

        if (!isset($payload['id'], $payload['business_unit'], $payload['transaction_date'])) {
            return false;
        }

        $columns = array_keys($payload);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->db->prepare($sql);

        return $stmt->execute($this->bindParams($payload));
    }

    public function update(string $id, array $data): bool
    {
        $allowed = [
            'business_unit',
            'transaction_direction',
            'transaction_date',
            'client_id',
            'project_id',
            'bank_account_id',
            'card_id',
            'team_id',
            'employee_id',
            'currency',
            'transaction_exchange_rate',
            'transaction_foreign_amount',
            'transaction_supply_amount',
            'transaction_settlement_amount',
            'transaction_final_amount',
            'transaction_description',
            'status',
            'match_status',
            'transaction_note',
            'transaction_memo',
            'updated_at',
            'updated_by',
            'deleted_at',
            'deleted_by',
        ];
        if ($this->tableColumnExists($this->table, 'operation_type')) {
            $allowed[] = 'operation_type';
        }

        $payload = $this->filterData($data, $allowed);

        if ($payload === []) {
            return false;
        }

        $set = [];
        foreach (array_keys($payload) as $column) {
            $set[] = "{$column} = :{$column}";
        }

        $sql = "
            UPDATE {$this->table}
            SET " . implode(', ', $set) . "
            WHERE id = :id
        ";

        $params = $this->bindParams($payload);
        $params[':id'] = $id;

        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    public function softDelete(string $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET deleted_at = NOW()
            WHERE id = :id
              AND deleted_at IS NULL
        ");

        return $stmt->execute([':id' => $id]);
    }

    public function restore(string $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET deleted_at = NULL,
                deleted_by = NULL
            WHERE id = :id
        ");

        return $stmt->execute([':id' => $id]);
    }

    public function hardDelete(string $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM {$this->table}
            WHERE id = :id
        ");

        return $stmt->execute([':id' => $id]);
    }

    public function updateSortNo(string $id, string|int $newSortNo): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET sort_no = :sort_no
            WHERE id = :id
        ");

        return $stmt->execute([
            ':sort_no' => (int) $newSortNo,
            ':id' => $id,
        ]);
    }

    private function filterData(array $data, array $allowed): array
    {
        $payload = [];

        foreach ($allowed as $column) {
            if (array_key_exists($column, $data)) {
                $payload[$column] = $data[$column];
            }
        }

        return $payload;
    }

    private function bindParams(array $data): array
    {
        $params = [];

        foreach ($data as $column => $value) {
            $params[':' . $column] = $value;
        }

        return $params;
    }

    private function stripHeaderTaxTypeRows(array $rows): array
    {
        foreach ($rows as &$row) {
            unset($row['tax_type']);
        }
        unset($row);

        return $rows;
    }
}
