<?php

namespace App\Models\Ledger;

use Core\Helpers\ActorHelper;
use Core\Database;
use PDO;

class TransactionModel
{
    protected string $table = 'ledger_transactions';

    private PDO $db;
    private int $lastFilteredCount = 0;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getList(array $filters = []): array
    {
        $orderField = trim((string) ($filters['_order_field'] ?? ''));
        $orderDirection = strtolower(trim((string) ($filters['_order_direction'] ?? ''))) === 'asc'
            ? 'ASC'
            : 'DESC';
        $hasOperationType = $this->tableColumnExists($this->table, 'operation_type');
        $operationTypeSelect = $hasOperationType
            ? "COALESCE(ot.code_name, '') AS operation_type_name,
                COALESCE(NULLIF(ot.code_name, ''), '') AS operation_type,
                t.operation_type AS operation_type_code,"
            : "'' AS operation_type_name,
                '' AS operation_type,
                '' AS operation_type_code,";
        $selectSql = "
            SELECT
                t.id,
                t.sort_no,
                t.business_unit AS business_unit_code,
                t.transaction_direction AS transaction_direction_code,
                t.currency AS currency_code,
                t.client_id AS client_uuid,
                t.project_id AS project_uuid,
                t.bank_account_id AS bank_account_uuid,
                t.card_id AS card_uuid,
                t.team_id AS team_uuid,
                t.employee_id AS employee_uuid,
                t.transaction_date,
                t.transaction_description,
                t.transaction_exchange_rate,
                t.transaction_foreign_amount,
                t.transaction_supply_amount,
                t.transaction_settlement_amount,
                t.transaction_final_amount,
                t.transaction_note,
                t.transaction_memo,
                t.status,
                t.created_at,
                t.created_by,
                t.updated_at,
                t.updated_by,
                t.deleted_at,
                t.deleted_by,
                COALESCE(bu.code_name, '') AS business_unit_name,
                COALESCE(td.code_name, '') AS transaction_direction_name,
                {$operationTypeSelect}
                COALESCE(cur.code_name, '') AS currency_name,
                COALESCE(NULLIF(bu.code_name, ''), '') AS business_unit,
                COALESCE(NULLIF(td.code_name, ''), '') AS transaction_direction,
                COALESCE(NULLIF(cur.code_name, ''), '') AS currency,
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
                COALESCE(NULLIF(ue.employee_name, ''), '') AS employee_id
        ";
        $fromSql = "
            FROM {$this->table} t
            LEFT JOIN system_codes bu
                ON bu.is_active = 1
               AND bu.code_group = 'BUSINESS_UNIT'
               AND bu.code = t.business_unit
            LEFT JOIN system_codes td
                ON td.is_active = 1
               AND td.code_group = 'TRANSACTION_DIRECTION'
               AND td.code = t.transaction_direction
             " . ($hasOperationType ? "
             LEFT JOIN system_codes ot
                 ON ot.is_active = 1
                AND ot.code_group = 'OPERATION_TYPE'
                AND ot.code = t.operation_type
             " : "") . "
             LEFT JOIN system_codes cur
                 ON cur.is_active = 1
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
        ";
        $whereSql = " WHERE t.deleted_at IS NULL";

        $params = [];

        if (!empty($filters['business_unit'])) {
            $whereSql .= " AND t.business_unit = :business_unit";
            $params[':business_unit'] = $filters['business_unit'];
        }

        if (!empty($filters['status'])) {
            $status = strtolower(trim((string) $filters['status']));
            if (in_array($status, ['draft', 'completed', 'closed', 'cancelled'], true)) {
                $whereSql .= " AND t.status = :status";
                $params[':status'] = $status;
            }
        }

        if (!empty($filters['project_id'])) {
            $whereSql .= " AND t.project_id = :project_id";
            $params[':project_id'] = $filters['project_id'];
        }

        if (!empty($filters['client_id'])) {
            $whereSql .= " AND t.client_id = :client_id";
            $params[':client_id'] = $filters['client_id'];
        }

        if (!empty($filters['date_from'])) {
            $whereSql .= " AND t.transaction_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereSql .= " AND t.transaction_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($filters['updated_from'])) {
            $whereSql .= " AND t.updated_at >= :updated_from";
            $params[':updated_from'] = $filters['updated_from'];
        }

        if (!empty($filters['updated_to'])) {
            $whereSql .= " AND t.updated_at <= :updated_to";
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
                    $whereSql .= ' AND ' . sprintf($fieldExpr, $param, $param);
                } else {
                    $whereSql .= " AND {$fieldExpr} LIKE {$param}";
                }
                $params[$param] = '%' . $value . '%';
            }
        }

        $orderFieldMap = [
            'sort_no' => 't.sort_no',
            'transaction_date' => 't.transaction_date',
            'business_unit' => "COALESCE(bu.code_name, t.business_unit, '')",
            'business_unit_name' => "COALESCE(bu.code_name, '')",
            'transaction_direction' => "COALESCE(td.code_name, t.transaction_direction, '')",
            'transaction_direction_name' => "COALESCE(td.code_name, '')",
            'operation_type' => $hasOperationType
                ? "COALESCE(ot.code_name, t.operation_type, '')"
                : "''",
            'operation_type_name' => $hasOperationType ? "COALESCE(ot.code_name, '')" : "''",
            'client_id' => "COALESCE(sc.client_name, t.client_id, '')",
            'client_name' => "COALESCE(sc.client_name, '')",
            'project_id' => "COALESCE(sp.project_name, t.project_id, '')",
            'project_name' => "COALESCE(sp.project_name, '')",
            'bank_account_id' => "COALESCE(sba.account_name, t.bank_account_id, '')",
            'bank_account_name' => "COALESCE(sba.account_name, '')",
            'card_id' => "COALESCE(scd.card_name, t.card_id, '')",
            'card_name' => "COALESCE(scd.card_name, '')",
            'team_id' => "COALESCE(swt.team_name, t.team_id, '')",
            'team_name' => "COALESCE(swt.team_name, '')",
            'employee_id' => "COALESCE(ue.employee_name, t.employee_id, '')",
            'employee_name' => "COALESCE(ue.employee_name, '')",
            'transaction_foreign_amount' => 't.transaction_foreign_amount',
            'transaction_supply_amount' => 't.transaction_supply_amount',
            'transaction_settlement_amount' => 't.transaction_settlement_amount',
            'transaction_final_amount' => 't.transaction_final_amount',
            'currency' => "COALESCE(cur.code_name, t.currency, '')",
            'currency_name' => "COALESCE(cur.code_name, '')",
            'exchange_rate' => 't.transaction_exchange_rate',
            'description' => 't.transaction_description',
            'status' => 't.status',
            'note' => 't.transaction_note',
            'memo' => 't.transaction_memo',
            'created_at' => 't.created_at',
            'created_by' => 't.created_by',
            'updated_at' => 't.updated_at',
            'updated_by' => 't.updated_by',
            'deleted_at' => 't.deleted_at',
            'deleted_by' => 't.deleted_by',
        ];
        $sql = $selectSql . $fromSql . $whereSql;
        $countSql = 'SELECT COUNT(DISTINCT t.id)' . $fromSql . $whereSql;
        if (isset($orderFieldMap[$orderField])) {
            $sql .= " ORDER BY {$orderFieldMap[$orderField]} {$orderDirection}, t.created_at DESC, t.id DESC";
        } else {
            $sql .= " ORDER BY t.transaction_date DESC, t.created_at DESC, t.id DESC";
        }
        if (isset($filters['_length'])) {
            $length = max(10, min(100, (int) $filters['_length']));
            $start = max(0, (int) ($filters['_start'] ?? 0));
            $sql .= " LIMIT {$start}, {$length}";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($params === []) {
            $this->lastFilteredCount = $this->activeTotalCount();
        } else {
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $this->lastFilteredCount = (int) $countStmt->fetchColumn();
        }
        $rows = ActorHelper::enrichActorNames($rows, [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
            'deleted_by_name' => 'deleted_by',
        ]);

        return $this->stripHeaderTaxTypeRows($rows);
    }

    public function lastFilteredCount(): int
    {
        return $this->lastFilteredCount;
    }

    public function activeTotalCount(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM ledger_transactions WHERE deleted_at IS NULL')->fetchColumn();
    }

    public function activeExists(string $id): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM ledger_transactions WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    public function updateSortNo(string $id, int $sortNo): void
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET sort_no = :sort_no
            WHERE id = :id
              AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':sort_no' => $sortNo,
            ':id' => $id,
        ]);
        if ($stmt->rowCount() !== 1 && !$this->activeExists($id)) {
            throw new \RuntimeException('정렬할 거래를 찾을 수 없습니다.');
        }
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
            ORDER BY t.sort_no ASC, t.transaction_date ASC, t.created_at ASC
        ");
        $stmt->execute();

        return $this->stripHeaderTaxTypeRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function getById(string $id): ?array
    {
        $hasOperationType = $this->tableColumnExists($this->table, 'operation_type');
        $operationTypeSelect = $hasOperationType
            ? "COALESCE(ot.code_name, '') AS operation_type_name,"
            : "'' AS operation_type_name,";
        $stmt = $this->db->prepare("
            SELECT
                t.*,
                COALESCE(bu.code_name, '') AS business_unit_name,
                COALESCE(td.code_name, '') AS transaction_direction_name,
                {$operationTypeSelect}
                COALESCE(cur.code_name, '') AS currency_name,
                COALESCE(sc.client_name, '') AS client_name,
                COALESCE(sp.project_name, '') AS project_name,
                COALESCE(sba.account_name, '') AS bank_account_name,
                COALESCE(scd.card_name, '') AS card_name,
                COALESCE(swt.team_name, '') AS team_name,
                COALESCE(ue.employee_name, '') AS employee_name
            FROM {$this->table} t
            LEFT JOIN system_codes bu
                ON bu.is_active = 1
               AND bu.code_group = 'BUSINESS_UNIT'
               AND bu.code = t.business_unit
            LEFT JOIN system_codes td
                ON td.is_active = 1
               AND td.code_group = 'TRANSACTION_DIRECTION'
               AND td.code = t.transaction_direction
            " . ($hasOperationType ? "
            LEFT JOIN system_codes ot
                ON ot.is_active = 1
               AND ot.code_group = 'OPERATION_TYPE'
               AND ot.code = t.operation_type
            " : "") . "
            LEFT JOIN system_codes cur
                ON cur.is_active = 1
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

    public function getByIdForUpdate(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ledger_transactions WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getTrashList(): array
    {
        $hasOperationType = $this->tableColumnExists($this->table, 'operation_type');
        $operationSelect = $hasOperationType
            ? "COALESCE(ot.code_name, '') AS operation_type_name, COALESCE(NULLIF(ot.code_name, ''), '') AS operation_type,"
            : "'' AS operation_type_name, '' AS operation_type,";
        $operationJoin = $hasOperationType
            ? "LEFT JOIN system_codes ot ON ot.is_active = 1 AND ot.code_group = 'OPERATION_TYPE' AND ot.code = t.operation_type"
            : '';
        $stmt = $this->db->query("SELECT t.*, COALESCE(bu.code_name, '') AS business_unit_name,
            COALESCE(td.code_name, '') AS transaction_direction_name, {$operationSelect}
            COALESCE(cur.code_name, '') AS currency_name,
            COALESCE(NULLIF(bu.code_name, ''), '') AS business_unit,
            COALESCE(NULLIF(td.code_name, ''), '') AS transaction_direction,
            COALESCE(NULLIF(cur.code_name, ''), '') AS currency,
            COALESCE(sc.client_name, '') AS client_name, COALESCE(sp.project_name, '') AS project_name,
            COALESCE(sba.account_name, '') AS bank_account_name, COALESCE(scd.card_name, '') AS card_name,
            COALESCE(swt.team_name, '') AS team_name, COALESCE(ue.employee_name, '') AS employee_name,
            COALESCE(NULLIF(sc.client_name, ''), '') AS client_id, COALESCE(NULLIF(sp.project_name, ''), '') AS project_id,
            COALESCE(NULLIF(sba.account_name, ''), '') AS bank_account_id, COALESCE(NULLIF(scd.card_name, ''), '') AS card_id,
            COALESCE(NULLIF(swt.team_name, ''), '') AS team_id, COALESCE(NULLIF(ue.employee_name, ''), '') AS employee_id
            FROM {$this->table} t
            LEFT JOIN system_codes bu ON bu.is_active = 1 AND bu.code_group = 'BUSINESS_UNIT' AND bu.code = t.business_unit
            LEFT JOIN system_codes td ON td.is_active = 1 AND td.code_group = 'TRANSACTION_DIRECTION' AND td.code = t.transaction_direction
            {$operationJoin}
            LEFT JOIN system_codes cur ON cur.is_active = 1 AND cur.code_group = 'CURRENCY' AND cur.code = t.currency
            LEFT JOIN system_clients sc ON t.client_id = sc.id LEFT JOIN system_projects sp ON t.project_id = sp.id
            LEFT JOIN system_bank_accounts sba ON t.bank_account_id = sba.id LEFT JOIN system_cards scd ON t.card_id = scd.id
            LEFT JOIN system_work_teams swt ON t.team_id = swt.id LEFT JOIN user_employees ue ON t.employee_id = ue.id
            WHERE t.deleted_at IS NOT NULL ORDER BY t.deleted_at DESC, t.transaction_date DESC");
        return $this->stripHeaderTaxTypeRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function getDeletedIds(): array
    {
        $stmt = $this->db->query("SELECT id FROM {$this->table} WHERE deleted_at IS NOT NULL");
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
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
