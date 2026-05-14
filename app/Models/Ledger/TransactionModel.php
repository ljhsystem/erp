<?php

namespace App\Models\Ledger;

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
        $sql = "
            SELECT
                t.*,
                COALESCE(sc.client_name, '') AS client_name,
                COALESCE(sp.project_name, '') AS project_name,
                COALESCE(tls.line_count, 0) AS transaction_line_count,
                COALESCE(tls.incomplete_count, 0) AS transaction_line_incomplete_count,
                CASE
                    WHEN COALESCE(tls.line_count, 0) = 0 THEN 'NONE'
                    WHEN COALESCE(tls.incomplete_count, 0) > 0 THEN 'INCOMPLETE'
                    ELSE 'COMPLETE'
                END AS transaction_line_status
            FROM {$this->table} t
            LEFT JOIN system_clients sc
                ON t.client_id = sc.id
            LEFT JOIN system_projects sp
                ON t.project_id = sp.id
            {$lineStatusJoin}
            WHERE t.deleted_at IS NULL
        ";

        $params = [];

        if (!empty($filters['business_unit'])) {
            $sql .= " AND t.business_unit = :business_unit";
            $params[':business_unit'] = $filters['business_unit'];
        }

        if (!empty($filters['transaction_type'])) {
            $sql .= " AND t.transaction_type = :transaction_type";
            $params[':transaction_type'] = $filters['transaction_type'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND t.status = :status";
            $params[':status'] = $filters['status'];
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
                'business_unit' => 't.business_unit',
                'transaction_type' => 't.transaction_type',
                'transaction_direction' => 't.transaction_direction',
                'import_type' => 't.import_type',
                'transaction_date' => 't.transaction_date',
                'project_id' => 't.project_id',
                'project_name' => 'sp.project_name',
                'client_id' => 't.client_id',
                'client_name' => 'sc.client_name',
                'base_amount' => 't.base_amount',
                'adjustment_amount' => 't.adjustment_amount',
                'supply_amount' => 't.supply_amount',
                'vat_amount' => 't.vat_amount',
                'total_amount' => 't.total_amount',
                'description' => 't.description',
                'currency' => 't.currency',
                'exchange_rate' => 't.exchange_rate',
                'status' => 't.status',
                'match_status' => 't.match_status',
                'note' => 't.note',
                'memo' => 't.memo',
                'created_at' => 't.created_at',
                'created_by' => 't.created_by',
                'updated_at' => 't.updated_at',
                'updated_by' => 't.updated_by',
                'deleted_at' => 't.deleted_at',
                'deleted_by' => 't.deleted_by',
            ];

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
                $sql .= " AND {$fieldMap[$field]} LIKE {$param}";
                $params[$param] = '%' . $value . '%';
            }
        }

        $sql .= " ORDER BY t.sort_no ASC, t.transaction_date ASC, t.created_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->stripHeaderTaxTypeRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function lineStatusJoinSql(): string
    {
        if (!$this->tableExists('ledger_transaction_lines') || !$this->tableColumnExists('ledger_transaction_lines', 'transaction_id')) {
            return "
                LEFT JOIN (
                    SELECT NULL AS transaction_id, 0 AS line_count, 0 AS incomplete_count
                ) tls ON tls.transaction_id = t.id
            ";
        }

        $where = $this->tableColumnExists('ledger_transaction_lines', 'deleted_at')
            ? 'WHERE deleted_at IS NULL'
            : '';
        $itemNameExpr = $this->tableColumnExists('ledger_transaction_lines', 'item_name')
            ? "TRIM(COALESCE(item_name, '')) = ''"
            : '0 = 1';
        $amountExpr = $this->lineAmountExpression();

        return "
            LEFT JOIN (
                SELECT
                    transaction_id,
                    COUNT(*) AS line_count,
                    SUM(CASE
                        WHEN {$itemNameExpr} OR {$amountExpr} = 0 THEN 1
                        ELSE 0
                    END) AS incomplete_count
                FROM ledger_transaction_lines
                {$where}
                GROUP BY transaction_id
            ) tls
                ON tls.transaction_id = t.id
        ";
    }

    private function lineAmountExpression(): string
    {
        if ($this->tableColumnExists('ledger_transaction_lines', 'amount')) {
            return 'COALESCE(amount, 0)';
        }
        if ($this->tableColumnExists('ledger_transaction_lines', 'total_amount')) {
            return 'COALESCE(total_amount, 0)';
        }
        if ($this->tableColumnExists('ledger_transaction_lines', 'supply_amount') || $this->tableColumnExists('ledger_transaction_lines', 'vat_amount')) {
            $supply = $this->tableColumnExists('ledger_transaction_lines', 'supply_amount') ? 'COALESCE(supply_amount, 0)' : '0';
            $vat = $this->tableColumnExists('ledger_transaction_lines', 'vat_amount') ? 'COALESCE(vat_amount, 0)' : '0';
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
            SELECT *
            FROM {$this->table}
            WHERE deleted_at IS NULL
              AND status = 'draft'
              AND match_status = 'none'
            ORDER BY sort_no ASC, transaction_date ASC, created_at ASC
        ");
        $stmt->execute();

        return $this->stripHeaderTaxTypeRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function getById(string $id): ?array
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
            WHERE t.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row !== null) {
            unset($row['tax_type']);
        }

        return $row;
    }

    public function insert(array $data): bool
    {
        $allowed = [
            'id',
            'sort_no',
            'transaction_date',
            'business_unit',
            'transaction_type',
            'transaction_direction',
            'import_type',
            'client_id',
            'project_id',
            'currency',
            'exchange_rate',
            'base_amount',
            'adjustment_amount',
            'supply_amount',
            'vat_amount',
            'total_amount',
            'description',
            'status',
            'match_status',
            'note',
            'memo',
            'created_at',
            'created_by',
            'updated_at',
            'updated_by',
            'deleted_at',
            'deleted_by',
        ];

        $payload = $this->filterData($data, $allowed);

        if (!isset($payload['id'], $payload['business_unit'], $payload['transaction_type'], $payload['transaction_date'])) {
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
            'transaction_type',
            'transaction_direction',
            'import_type',
            'transaction_date',
            'client_id',
            'project_id',
            'currency',
            'exchange_rate',
            'base_amount',
            'adjustment_amount',
            'supply_amount',
            'vat_amount',
            'total_amount',
            'description',
            'status',
            'match_status',
            'note',
            'memo',
            'updated_at',
            'updated_by',
            'deleted_at',
            'deleted_by',
        ];

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
            if (array_key_exists($column, $data) && $this->tableColumnExists($this->table, $column)) {
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
