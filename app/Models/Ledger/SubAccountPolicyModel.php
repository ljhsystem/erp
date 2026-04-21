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
                sub_account_type,
                is_required,
                is_multiple,
                sort_order,
                custom_group_code,
                note
            FROM ledger_account_sub_policies
            WHERE account_id = :account_id
              AND deleted_at IS NULL
            ORDER BY sort_order ASC, created_at ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':account_id' => $accountId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function deleteByAccountId(string $accountId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM ledger_account_sub_policies
            WHERE account_id = :account_id
        ");

        return $stmt->execute([
            ':account_id' => $accountId,
        ]);
    }

    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO ledger_account_sub_policies (
                id,
                account_id,
                sub_account_type,
                is_required,
                is_multiple,
                sort_order,
                custom_group_code,
                note,
                created_by,
                updated_by
            ) VALUES (
                :id,
                :account_id,
                :sub_account_type,
                :is_required,
                :is_multiple,
                :sort_order,
                :custom_group_code,
                :note,
                :created_by,
                :updated_by
            )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id' => $data['id'],
            ':account_id' => $data['account_id'],
            ':sub_account_type' => $data['sub_account_type'],
            ':is_required' => $data['is_required'],
            ':is_multiple' => $data['is_multiple'],
            ':sort_order' => $data['sort_order'],
            ':custom_group_code' => $data['custom_group_code'] ?? null,
            ':note' => $data['note'] ?? null,
            ':created_by' => $data['created_by'] ?? null,
            ':updated_by' => $data['updated_by'] ?? null,
        ]);
    }

    public function countByAccountId(string $accountId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM ledger_account_sub_policies
            WHERE account_id = :account_id
              AND deleted_at IS NULL
        ");

        $stmt->execute([
            ':account_id' => $accountId,
        ]);

        return (int) $stmt->fetchColumn();
    }
}
