<?php

namespace App\Models\Ledger;

use Core\Database;
use PDO;

class TransactionItemModel
{
    protected string $table = 'ledger_transaction_items';

    public function countByTransactionIds(array $transactionIds): int
    {
        $transactionIds = array_values(array_filter(array_map('strval', $transactionIds)));
        if ($transactionIds === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE transaction_id IN ({$placeholders})");
        $stmt->execute($transactionIds);
        return (int) $stmt->fetchColumn();
    }

    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getList(array $filters = []): array
    {
        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE 1 = 1
        ";

        if ($this->usesSoftDelete()) {
            $sql .= " AND deleted_at IS NULL";
        }

        $params = [];

        if (!empty($filters['transaction_id'])) {
            $sql .= " AND transaction_id = :transaction_id";
            $params[':transaction_id'] = $filters['transaction_id'];
        }

        $sql .= " ORDER BY transaction_id ASC, sort_no ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM {$this->table}
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getByTransactionId(string $transactionId): array
    {
        $where = 'transaction_id = :transaction_id';
        if ($this->usesSoftDelete()) {
            $where .= ' AND deleted_at IS NULL';
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM {$this->table}
            WHERE {$where}
            ORDER BY sort_no ASC, created_at ASC
        ");
        $stmt->execute([':transaction_id' => $transactionId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function insert(array $data): bool
    {
        $allowed = [
            'id',
            'sort_no',
            'transaction_id',
            'item_date',
            'item_name',
            'item_specification',
            'item_unit_name',
            'item_quantity',
            'item_unit_price',
            'item_foreign_unit_price',
            'item_foreign_amount',
            'item_supply_amount',
            'item_description',
            'created_at',
            'created_by',
            'updated_at',
            'updated_by',
            'deleted_at',
            'deleted_by',
        ];

        $payload = $this->filterData($data, $allowed);

        if (!isset($payload['id'], $payload['transaction_id'], $payload['sort_no'], $payload['item_date'], $payload['item_name'])) {
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
            'transaction_id',
            'sort_no',
            'item_date',
            'item_name',
            'item_specification',
            'item_unit_name',
            'item_quantity',
            'item_unit_price',
            'item_foreign_unit_price',
            'item_foreign_amount',
            'item_supply_amount',
            'item_description',
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
        if (!$this->usesSoftDelete()) {
            return $this->hardDelete($id);
        }

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
        if (!$this->usesSoftDelete()) {
            return false;
        }

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

    public function hardDeleteByTransactionId(string $transactionId): int
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE transaction_id = :transaction_id");
        $stmt->execute([':transaction_id' => $transactionId]);
        return $stmt->rowCount();
    }

    private function filterData(array $data, array $allowed): array
    {
        $payload = [];

        foreach ($allowed as $column) {
            if (array_key_exists($column, $data) && $this->tableColumnExists($column)) {
                $payload[$column] = $data[$column];
            }
        }

        return $payload;
    }

    private function tableColumnExists(string $columnName): bool
    {
        static $cache = [];
        if (array_key_exists($columnName, $cache)) {
            return $cache[$columnName];
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
            ':table_name' => $this->table,
            ':column_name' => $columnName,
        ]);

        $cache[$columnName] = (bool) $stmt->fetchColumn();
        return $cache[$columnName];
    }

    private function usesSoftDelete(): bool
    {
        return $this->tableColumnExists('deleted_at');
    }

    private function bindParams(array $data): array
    {
        $params = [];

        foreach ($data as $column => $value) {
            $params[':' . $column] = $value;
        }

        return $params;
    }
}
