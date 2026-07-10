<?php

namespace App\Models\Ledger;

use Core\Database;
use PDO;

class TransactionSettlementModel
{
    protected string $table = 'ledger_transaction_settlements';

    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getByTransactionId(string $transactionId): array
    {
        if (!$this->tableExists()) {
            return [];
        }

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

    private function tableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        $stmt = $this->db->prepare("
            SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
            LIMIT 1
        ");
        $stmt->execute([':table_name' => $this->table]);
        $exists = (bool) $stmt->fetchColumn();

        return $exists;
    }

    public function insert(array $data): bool
    {
        $allowed = [
            'id',
            'sort_no',
            'transaction_id',
            'transaction_item_id',
            'settlement_type',
            'amount_sign',
            'amount',
            'currency',
            'exchange_rate',
            'settlement_description',
            'meta_json',
            'created_at',
            'created_by',
            'updated_at',
            'updated_by',
            'deleted_at',
            'deleted_by',
        ];

        $payload = $this->filterData($data, $allowed);

        if (!isset($payload['id'], $payload['transaction_id'], $payload['sort_no'], $payload['settlement_type'], $payload['amount_sign'], $payload['amount'])) {
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
