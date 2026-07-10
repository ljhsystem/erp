<?php

namespace App\Models\Ledger;

use Core\Database;
use PDO;

class TransactionFileModel
{
    protected string $table = 'ledger_transaction_files';

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

        $stmt = $this->db->prepare("
            SELECT *
            FROM {$this->table}
            WHERE transaction_id = :transaction_id
            ORDER BY file_order ASC, created_at ASC
        ");
        $stmt->execute([':transaction_id' => $transactionId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(string $id): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM {$this->table}
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function insert(array $data): bool
    {
        $allowed = [
            'id',
            'transaction_id',
            'file_path',
            'file_name',
            'file_order',
            'file_size',
            'created_at',
            'created_by',
        ];

        $payload = $this->filterData($data, $allowed);
        if (!isset($payload['id'], $payload['transaction_id'], $payload['file_path'], $payload['file_order'], $payload['created_by'])) {
            return false;
        }

        $columns = array_keys($payload);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

        $stmt = $this->db->prepare(sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        ));

        return $stmt->execute($this->bindParams($payload));
    }

    public function updateOrder(string $id, int $fileOrder): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET file_order = :file_order
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id' => $id,
            ':file_order' => $fileOrder,
        ]);
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

    private function bindParams(array $data): array
    {
        $params = [];
        foreach ($data as $column => $value) {
            $params[':' . $column] = $value;
        }

        return $params;
    }
}
