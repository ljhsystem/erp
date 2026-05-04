<?php

namespace App\Models\Ledger;

use Core\Database;
use PDO;

class TransactionLinkModel
{
    protected string $table = 'ledger_transaction_links';

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
              AND is_active = 1
        ";

        $params = [];

        if (!empty($filters['transaction_id'])) {
            $sql .= " AND transaction_id = :transaction_id";
            $params[':transaction_id'] = $filters['transaction_id'];
        }

        if (!empty($filters['voucher_id'])) {
            $sql .= " AND voucher_id = :voucher_id";
            $params[':voucher_id'] = $filters['voucher_id'];
        }

        if (!empty($filters['link_type'])) {
            $sql .= " AND link_type = :link_type";
            $params[':link_type'] = $filters['link_type'];
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $sql .= " AND is_active = :is_active";
            $params[':is_active'] = (int) $filters['is_active'];
        }

        $sql .= " ORDER BY created_at DESC";

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
        $stmt = $this->db->prepare("
            SELECT *
            FROM {$this->table}
            WHERE transaction_id = :transaction_id
              AND deleted_at IS NULL
              AND is_active = 1
            ORDER BY created_at ASC
        ");
        $stmt->execute([':transaction_id' => $transactionId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getByVoucherId(string $voucherId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM {$this->table}
            WHERE voucher_id = :voucher_id
              AND deleted_at IS NULL
              AND is_active = 1
            ORDER BY created_at ASC
        ");
        $stmt->execute([':voucher_id' => $voucherId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function existsLink(string $transactionId, string $voucherId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS cnt
            FROM {$this->table}
            WHERE transaction_id = :transaction_id
              AND voucher_id = :voucher_id
              AND deleted_at IS NULL
              AND is_active = 1
        ");
        $stmt->execute([
            ':transaction_id' => $transactionId,
            ':voucher_id' => $voucherId,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function findInactiveLink(string $transactionId, string $voucherId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM {$this->table}
            WHERE transaction_id = :transaction_id
              AND voucher_id = :voucher_id
              AND is_active = 0
            ORDER BY updated_at DESC, created_at DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':transaction_id' => $transactionId,
            ':voucher_id' => $voucherId,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function restoreLink(string $id, string $actor): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET is_active = 1,
                deleted_at = NULL,
                deleted_by = NULL,
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id' => $id,
            ':updated_by' => $actor,
        ]);
    }

    public function softDeleteByTransactionAndVoucher(string $transactionId, string $voucherId, string $actor): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET is_active = 0,
                deleted_at = NOW(),
                deleted_by = :deleted_by,
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE transaction_id = :transaction_id
              AND voucher_id = :voucher_id
              AND is_active = 1
              AND deleted_at IS NULL
        ");

        return $stmt->execute([
            ':transaction_id' => $transactionId,
            ':voucher_id' => $voucherId,
            ':deleted_by' => $actor,
            ':updated_by' => $actor,
        ]);
    }

    public function countActiveByTransactionId(string $transactionId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS cnt
            FROM {$this->table}
            WHERE transaction_id = :transaction_id
              AND is_active = 1
              AND deleted_at IS NULL
        ");
        $stmt->execute([':transaction_id' => $transactionId]);

        return (int) $stmt->fetchColumn();
    }

    public function insertOrRestore(
        string $transactionId,
        string $voucherId,
        mixed $matchAmount,
        string $linkType,
        string $actor
    ): bool {
        if ($this->existsLink($transactionId, $voucherId)) {
            return true;
        }

        $inactive = $this->findInactiveLink($transactionId, $voucherId);
        if ($inactive) {
            $updated = $this->update((string) $inactive['id'], [
                'match_amount' => $matchAmount,
                'link_type' => $linkType,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $actor,
            ]);

            return $updated && $this->restoreLink((string) $inactive['id'], $actor);
        }

        $timestamp = date('Y-m-d H:i:s');

        return $this->insert([
            'id' => \Core\Helpers\UuidHelper::generate(),
            'transaction_id' => $transactionId,
            'voucher_id' => $voucherId,
            'match_amount' => $matchAmount,
            'link_type' => $linkType,
            'is_active' => 1,
            'created_at' => $timestamp,
            'created_by' => $actor,
            'updated_at' => $timestamp,
            'updated_by' => $actor,
        ]);
    }

    public function insert(array $data): bool
    {
        $allowed = [
            'id',
            'transaction_id',
            'voucher_id',
            'match_amount',
            'link_type',
            'is_active',
            'created_at',
            'created_by',
            'updated_at',
            'updated_by',
            'deleted_at',
            'deleted_by',
        ];

        $payload = $this->filterData($data, $allowed);

        if (!isset($payload['id'], $payload['transaction_id'], $payload['voucher_id'])) {
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
            'voucher_id',
            'match_amount',
            'link_type',
            'is_active',
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
            SET is_active = 0,
                deleted_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
              AND deleted_at IS NULL
              AND is_active = 1
        ");

        return $stmt->execute([':id' => $id]);
    }

    public function restore(string $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET is_active = 1,
                deleted_at = NULL,
                deleted_by = NULL,
                updated_at = NOW()
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
}
