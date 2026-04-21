<?php

namespace App\Models\Ledger;

use Core\Database;
use PDO;

class SubChartAccountModel
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getByAccountId(string $accountId, ?string $subType = null): array
    {
        $sql = "
            SELECT *
            FROM ledger_sub_accounts
            WHERE account_id = :account_id
        ";

        $params = [
            ':account_id' => $accountId,
        ];

        $sql .= " ORDER BY CAST(sub_code AS UNSIGNED)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(array $data): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO ledger_sub_accounts (
                id,
                account_id,
                sub_code,
                sub_name,
                note,
                memo,
                created_by,
                updated_by
            ) VALUES (
                :id,
                :account_id,
                :sub_code,
                :sub_name,
                :note,
                :memo,
                :created_by,
                :updated_by
            )
        ");

        return $stmt->execute([
            ':id' => $data['id'],
            ':account_id' => $data['account_id'],
            ':sub_code' => $data['sub_code'],
            ':sub_name' => $data['sub_name'],
            ':note' => $data['note'] ?? null,
            ':memo' => $data['memo'] ?? null,
            ':created_by' => $data['created_by'] ?? null,
            ':updated_by' => $data['updated_by'] ?? $data['created_by'] ?? null,
        ]);
    }

    public function update(string $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE ledger_sub_accounts
            SET
                sub_name = :sub_name,
                note = :note,
                memo = :memo,
                updated_by = :updated_by
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id' => $id,
            ':sub_name' => $data['sub_name'] ?? '',
            ':note' => $data['note'] ?? null,
            ':memo' => $data['memo'] ?? null,
            ':updated_by' => $data['updated_by'] ?? null,
        ]);
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

    public function findByAccountAndName(string $accountId, string $subName, ?string $subType = null): ?array
    {
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

    public function getNextSubCode(string $accountId): string
    {
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
}
