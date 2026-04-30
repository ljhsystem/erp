<?php

namespace App\Models\Ledger;

use Core\Database;
use PDO;

class VoucherPaymentModel
{
    protected string $table = 'ledger_voucher_payments';

    private PDO $db;
    private ?array $tableColumns = null;

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

        $params = [];

        if (!empty($filters['voucher_id'])) {
            $sql .= " AND voucher_id = :voucher_id";
            $params[':voucher_id'] = $filters['voucher_id'];
        }

        if (!empty($filters['payment_type'])) {
            $sql .= " AND payment_type = :payment_type";
            $params[':payment_type'] = $filters['payment_type'];
        }

        if (!empty($filters['payment_id'])) {
            $sql .= " AND payment_id = :payment_id";
            $params[':payment_id'] = $filters['payment_id'];
        }

        $sql .= " ORDER BY voucher_id ASC, created_at DESC";

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

    public function getByVoucherId(string $voucherId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM {$this->table}
            WHERE voucher_id = :voucher_id
            ORDER BY created_at ASC
        ");
        $stmt->execute([':voucher_id' => $voucherId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function insert(array $data): bool
    {
        $allowed = [
            'id',
            'sort_no',
            'voucher_id',
            'payment_type',
            'payment_id',
            'amount',
            'created_at',
            'created_by',
        ];
        if ($this->hasColumn('direction')) {
            $allowed[] = 'direction';
            $data['direction'] = strtoupper(trim((string) ($data['direction'] ?? $data['payment_direction'] ?? 'OUT'))) ?: 'OUT';
        }
        if ($this->hasColumn('payment_direction')) {
            $allowed[] = 'payment_direction';
            $data['payment_direction'] = strtoupper(trim((string) ($data['payment_direction'] ?? $data['direction'] ?? 'OUT'))) ?: 'OUT';
        }

        $payload = $this->filterData($data, $allowed);

        if (!isset($payload['id'], $payload['voucher_id'], $payload['payment_type'], $payload['payment_id'], $payload['amount'])) {
            return false;
        }

        $columns = array_keys($payload);
        $placeholders = array_map(static fn(string $column) => ':' . $column, $columns);

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
            'sort_no',
            'voucher_id',
            'payment_type',
            'payment_id',
            'amount',
            'created_at',
            'created_by',
        ];
        if ($this->hasColumn('direction')) {
            $allowed[] = 'direction';
            $data['direction'] = strtoupper(trim((string) ($data['direction'] ?? $data['payment_direction'] ?? 'OUT'))) ?: 'OUT';
        }
        if ($this->hasColumn('payment_direction')) {
            $allowed[] = 'payment_direction';
            $data['payment_direction'] = strtoupper(trim((string) ($data['payment_direction'] ?? $data['direction'] ?? 'OUT'))) ?: 'OUT';
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

    public function hardDelete(string $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM {$this->table}
            WHERE id = :id
        ");

        return $stmt->execute([':id' => $id]);
    }

    public function purgeByVoucherId(string $voucherId): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM {$this->table}
            WHERE voucher_id = :voucher_id
        ");

        $stmt->execute([':voucher_id' => $voucherId]);
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

    private function hasColumn(string $column): bool
    {
        if ($this->tableColumns === null) {
            $stmt = $this->db->query("SHOW COLUMNS FROM {$this->table}");
            $this->tableColumns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'Field');
        }

        return in_array($column, $this->tableColumns, true);
    }
}
