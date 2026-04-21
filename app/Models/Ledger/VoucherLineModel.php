<?php

namespace App\Models\Ledger;

use Core\Database;
use PDO;

class VoucherLineModel
{
    protected string $table = 'ledger_voucher_lines';

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
            WHERE deleted_at IS NULL
        ";

        $params = [];

        if (!empty($filters['voucher_id'])) {
            $sql .= " AND voucher_id = :voucher_id";
            $params[':voucher_id'] = $filters['voucher_id'];
        }

        if (!empty($filters['account_code'])) {
            $sql .= " AND account_code = :account_code";
            $params[':account_code'] = $filters['account_code'];
        }

        $sql .= " ORDER BY voucher_id ASC, line_no ASC, code DESC";

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
              AND deleted_at IS NULL
            ORDER BY line_no ASC, code ASC
        ");
        $stmt->execute([':voucher_id' => $voucherId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function insert(array $data): bool
    {
        $allowed = [
            'id',
            'code',
            'voucher_id',
            'line_no',
            'account_code',
            'debit',
            'credit',
            'line_summary',
            'created_at',
            'created_by',
            'updated_at',
            'updated_by',
            'deleted_at',
            'deleted_by',
        ];

        $payload = $this->filterData($data, $allowed);

        if (!isset($payload['id'], $payload['code'], $payload['voucher_id'], $payload['line_no'], $payload['account_code'])) {
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
            'voucher_id',
            'line_no',
            'account_code',
            'debit',
            'credit',
            'line_summary',
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

    public function softDelete(string $id, ?string $actor = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET deleted_at = NOW(),
                deleted_by = :deleted_by
            WHERE id = :id
              AND deleted_at IS NULL
        ");

        return $stmt->execute([
            ':id' => $id,
            ':deleted_by' => $actor,
        ]);
    }

    public function restore(string $id, ?string $actor = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET deleted_at = NULL,
                deleted_by = NULL,
                updated_by = :updated_by
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id' => $id,
            ':updated_by' => $actor,
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

    public function softDeleteByVoucherId(string $voucherId, ?string $actor = null): void
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET deleted_at = NOW(),
                deleted_by = :deleted_by
            WHERE voucher_id = :voucher_id
              AND deleted_at IS NULL
        ");

        $stmt->execute([
            ':voucher_id' => $voucherId,
            ':deleted_by' => $actor,
        ]);
    }

    public function restoreByVoucherId(string $voucherId, ?string $actor = null): void
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET deleted_at = NULL,
                deleted_by = NULL,
                updated_by = :updated_by
            WHERE voucher_id = :voucher_id
        ");

        $stmt->execute([
            ':voucher_id' => $voucherId,
            ':updated_by' => $actor,
        ]);
    }

    public function purgeByVoucherId(string $voucherId): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM {$this->table}
            WHERE voucher_id = :voucher_id
        ");

        $stmt->execute([':voucher_id' => $voucherId]);
    }

    public function replace(string $voucherId, array $lines, ?string $actor = null): void
    {
        $this->softDeleteByVoucherId($voucherId, $actor);

        foreach ($lines as $line) {
            $line['voucher_id'] = $voucherId;
            if (!isset($line['created_by'])) {
                $line['created_by'] = $actor;
            }
            if (!isset($line['updated_by'])) {
                $line['updated_by'] = $actor;
            }
            $this->insert($line);
        }
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
}
