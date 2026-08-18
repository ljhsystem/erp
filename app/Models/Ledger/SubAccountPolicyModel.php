<?php

namespace App\Models\Ledger;

use Core\Database;
use PDO;

class SubAccountPolicyModel
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getByAccountId(string $accountId): array
    {
        $sql = "
            SELECT
                id,
                account_id,
                ref_target,
                ref_target AS sub_account_type,
                is_required,
                0 AS is_multiple,
                sort_no AS sort_order,
                NULL AS custom_group_code,
                NULL AS note
            FROM ledger_accounts_sub
            WHERE account_id = :account_id
            ORDER BY sort_no ASC, created_at ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':account_id' => $accountId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getGroupedByAccountIds(array $accountIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn(mixed $id): string => trim((string) $id),
            $accountIds
        ))));
        if ($ids === []) {
            return [];
        }

        $stmt = $this->db->prepare("SELECT id, account_id, ref_target,
                ref_target AS sub_account_type, is_required, 0 AS is_multiple,
                sort_no AS sort_order, NULL AS custom_group_code, NULL AS note
            FROM ledger_accounts_sub
            WHERE account_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
            ORDER BY account_id ASC, sort_no ASC, created_at ASC");
        $stmt->execute($ids);

        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $grouped[(string) $row['account_id']][] = $row;
        }
        return $grouped;
    }

    public function deleteByAccountId(string $accountId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM ledger_accounts_sub
            WHERE account_id = :account_id
        ");

        return $stmt->execute([
            ':account_id' => $accountId,
        ]);
    }

    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO ledger_accounts_sub (
                id,
                account_id,
                ref_target,
                is_required,
                sort_no,
                created_by,
                updated_by
            ) VALUES (
                :id,
                :account_id,
                :ref_target,
                :is_required,
                :sort_no,
                :created_by,
                :updated_by
            )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id' => $data['id'],
            ':account_id' => $data['account_id'],
            ':ref_target' => $data['ref_target'],
            ':is_required' => $data['is_required'],
            ':sort_no' => $data['sort_order'],
            ':created_by' => $data['created_by'] ?? null,
            ':updated_by' => $data['updated_by'] ?? null,
        ]);
    }

    public function countByAccountId(string $accountId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM ledger_accounts_sub
            WHERE account_id = :account_id
        ");

        $stmt->execute([
            ':account_id' => $accountId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function tableExists(): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ledger_accounts_sub' LIMIT 1");
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }
}
