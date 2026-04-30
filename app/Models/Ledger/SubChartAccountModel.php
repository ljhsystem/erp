<?php

namespace App\Models\Ledger;

use Core\Database;
use PDO;

class SubChartAccountModel
{
    private PDO $db;
    private ?array $tableColumns = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getByAccountId(string $accountId, ?string $subType = null): array
    {
        $orderBy = $this->hasColumn('sub_name')
            ? 'created_at ASC, sub_name ASC'
            : 'created_at ASC, ref_type ASC';

        $sql = "
            SELECT *
            FROM ledger_sub_accounts
            WHERE account_id = :account_id
        ";

        $params = [
            ':account_id' => $accountId,
        ];

        $sql .= " ORDER BY {$orderBy}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(array $data): bool
    {
        $fields = [
            'id' => ':id',
            'account_id' => ':account_id',
            'note' => ':note',
            'memo' => ':memo',
            'created_by' => ':created_by',
            'updated_by' => ':updated_by',
        ];

        $params = [
            ':id' => $data['id'],
            ':account_id' => $data['account_id'],
            ':note' => $data['note'] ?? null,
            ':memo' => $data['memo'] ?? null,
            ':created_by' => $data['created_by'] ?? null,
            ':updated_by' => $data['updated_by'] ?? $data['created_by'] ?? null,
        ];

        if ($this->hasColumn('ref_type')) {
            $fields['ref_type'] = ':ref_type';
            $params[':ref_type'] = $data['ref_type'] ?? $data['sub_code'] ?? '';
        }

        if ($this->hasColumn('sub_code')) {
            $fields['sub_code'] = ':sub_code';
            $params[':sub_code'] = $data['sub_code'] ?? $data['ref_type'] ?? '';
        }

        if ($this->hasColumn('sub_name')) {
            $fields['sub_name'] = ':sub_name';
            $params[':sub_name'] = $data['sub_name'] ?? $data['ref_type'] ?? $data['sub_code'] ?? '';
        }

        if ($this->hasColumn('custom_group_code')) {
            $fields['custom_group_code'] = ':custom_group_code';
            $params[':custom_group_code'] = $data['custom_group_code'] ?? null;
        }

        if ($this->hasColumn('is_required')) {
            $fields['is_required'] = ':is_required';
            $params[':is_required'] = (int) ($data['is_required'] ?? 0);
        }

        $columns = implode(",\n                ", array_keys($fields));
        $placeholders = implode(",\n                ", array_values($fields));

        $stmt = $this->db->prepare("
            INSERT INTO ledger_sub_accounts (
                {$columns}
            ) VALUES (
                {$placeholders}
            )
        ");

        return $stmt->execute($params);
    }

    public function update(string $id, array $data): bool
    {
        $sets = [
            'note = :note',
            'memo = :memo',
            'updated_by = :updated_by',
        ];

        $params = [
            ':id' => $id,
            ':note' => $data['note'] ?? null,
            ':memo' => $data['memo'] ?? null,
            ':updated_by' => $data['updated_by'] ?? null,
        ];

        if ($this->hasColumn('ref_type')) {
            $sets[] = 'ref_type = :ref_type';
            $params[':ref_type'] = $data['ref_type'] ?? $data['sub_code'] ?? '';
        }

        if ($this->hasColumn('sub_code')) {
            $sets[] = 'sub_code = :sub_code';
            $params[':sub_code'] = $data['sub_code'] ?? $data['ref_type'] ?? '';
        }

        if ($this->hasColumn('sub_name')) {
            $sets[] = 'sub_name = :sub_name';
            $params[':sub_name'] = $data['sub_name'] ?? $data['ref_type'] ?? $data['sub_code'] ?? '';
        }

        if ($this->hasColumn('custom_group_code')) {
            $sets[] = 'custom_group_code = :custom_group_code';
            $params[':custom_group_code'] = $data['custom_group_code'] ?? null;
        }

        if ($this->hasColumn('is_required')) {
            $sets[] = 'is_required = :is_required';
            $params[':is_required'] = (int) ($data['is_required'] ?? 0);
        }

        $setSql = implode(",\n                ", $sets);

        $stmt = $this->db->prepare("
            UPDATE ledger_sub_accounts
            SET
                {$setSql}
            WHERE id = :id
        ");

        return $stmt->execute($params);
    }

    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM ledger_sub_accounts
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id' => $id,
        ]);
    }

    public function deleteByAccountId(string $accountId, ?string $subType = null): bool
    {
        $sql = "
            DELETE FROM ledger_sub_accounts
            WHERE account_id = :account_id
        ";

        $params = [
            ':account_id' => $accountId,
        ];

        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    public function findByAccountAndName(string $accountId, string $subName, ?string $subType = null): ?array
    {
        if (!$this->hasColumn('sub_name')) {
            return $this->findByAccountAndSubCode($accountId, $subName);
        }

        $sql = "
            SELECT id
            FROM ledger_sub_accounts
            WHERE account_id = :account_id
              AND sub_name = :sub_name
        ";

        $params = [
            ':account_id' => $accountId,
            ':sub_name' => $subName,
        ];

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByAccountAndSubCode(string $accountId, string $subCode, ?string $excludeId = null): ?array
    {
        $targetColumn = $this->hasColumn('ref_type') ? 'ref_type' : 'sub_code';

        $sql = "
            SELECT id
            FROM ledger_sub_accounts
            WHERE account_id = :account_id
              AND {$targetColumn} = :sub_code
        ";

        $params = [
            ':account_id' => $accountId,
            ':sub_code' => $subCode,
        ];

        if ($excludeId !== null && $excludeId !== '') {
            $sql .= " AND id <> :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getNextSubCode(string $accountId): string
    {
        if (!$this->hasColumn('sub_code')) {
            return '';
        }

        $stmt = $this->db->prepare("
            SELECT COALESCE(MAX(CAST(sub_code AS UNSIGNED)), 0) AS max_code
            FROM ledger_sub_accounts
            WHERE account_id = :account_id
        ");

        $stmt->execute([
            ':account_id' => $accountId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (string) (((int) ($row['max_code'] ?? 0)) + 1);
    }

    public function countByAccountId(string $accountId, ?string $subType = null): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM ledger_sub_accounts
            WHERE account_id = :account_id
        ";

        $params = [
            ':account_id' => $accountId,
        ];

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function getAccountIdById(string $id): ?string
    {
        $stmt = $this->db->prepare("
            SELECT account_id
            FROM ledger_sub_accounts
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $id,
        ]);

        $value = $stmt->fetchColumn();

        return $value !== false ? (string) $value : null;
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM ledger_sub_accounts
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $id,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function hasColumn(string $column): bool
    {
        if ($this->tableColumns === null) {
            $stmt = $this->db->query('SHOW COLUMNS FROM ledger_sub_accounts');
            $this->tableColumns = [];

            foreach (($stmt?->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
                if (!empty($row['Field'])) {
                    $this->tableColumns[$row['Field']] = true;
                }
            }
        }

        return isset($this->tableColumns[$column]);
    }
}
