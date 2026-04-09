<?php
// 경로: PROJECT_ROOT . '/app/Models/System/BankAccountModel.php'

namespace App\Models\System;

use PDO;
use Exception;

class BankAccountModel
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?: \Core\Database::getInstance()->getConnection();
    }

    /* =========================================================
     * 목록
     * ========================================================= */
    public function getList(array $filters = []): array
    {
        $sql = "
            SELECT a.*
            FROM system_bank_accounts a
            WHERE a.deleted_at IS NULL
        ";

        $params = [];

        // 🔥 필터
        foreach ($filters as $f) {

            $field = $f['field'] ?? '';
            $value = $f['value'] ?? '';

            if (!$field || $value === '') continue;

            $sql .= " AND a.$field LIKE ?";
            $params[] = "%{$value}%";
        }

        $sql .= " ORDER BY a.code ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =========================================================
     * 상세
     * ========================================================= */
    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM system_bank_accounts
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* =========================================================
     * 생성
     * ========================================================= */
    public function insert(array $data, string $actor): string
    {
        $id = $data['id'];

        $sql = "
            INSERT INTO system_bank_accounts (
                id, code,
                bank_name,
                account_number,
                account_holder,
                note,
                created_by, updated_by
            ) VALUES (
                :id, :code,
                :bank_name,
                :account_number,
                :account_holder,
                :note,
                :created_by, :updated_by
            )
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id' => $id,
            'code' => $data['code'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'account_holder' => $data['account_holder'] ?? null,
            'note' => $data['note'] ?? null,
            'created_by' => $actor,
            'updated_by' => $actor
        ]);

        return $id;
    }

    /* =========================================================
     * 수정
     * ========================================================= */
    public function update(string $id, array $data, string $actor): bool
    {
        $sql = "
            UPDATE system_bank_accounts SET
                bank_name = :bank_name,
                account_number = :account_number,
                account_holder = :account_holder,
                note = :note,
                updated_by = :updated_by
            WHERE id = :id
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id' => $id,
            'bank_name' => $data['bank_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'account_holder' => $data['account_holder'] ?? null,
            'note' => $data['note'] ?? null,
            'updated_by' => $actor
        ]);
    }

    /* =========================================================
     * soft delete
     * ========================================================= */
    public function softDelete(string $id, string $actor): bool
    {
        $stmt = $this->db->prepare("
            UPDATE system_bank_accounts
            SET deleted_at = NOW(),
                deleted_by = :actor
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'actor' => $actor
        ]);
    }

    /* =========================================================
     * 휴지통
     * ========================================================= */
    public function getTrashList(): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM system_bank_accounts
            WHERE deleted_at IS NOT NULL
            ORDER BY deleted_at DESC
        ");

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function restore(string $id, string $actor): bool
    {
        $stmt = $this->db->prepare("
            UPDATE system_bank_accounts
            SET deleted_at = NULL,
                deleted_by = NULL,
                updated_by = :actor
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'actor' => $actor
        ]);
    }

    public function restoreAll(string $actor): int
    {
        $stmt = $this->db->prepare("
            UPDATE system_bank_accounts
            SET deleted_at = NULL,
                deleted_by = NULL,
                updated_by = :actor
            WHERE deleted_at IS NOT NULL
        ");

        $stmt->execute(['actor' => $actor]);

        return $stmt->rowCount();
    }

    /* =========================================================
     * 완전삭제
     * ========================================================= */
    public function hardDelete(string $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM system_bank_accounts
            WHERE id = :id
        ");

        return $stmt->execute(['id' => $id]);
    }

    public function hardDeleteAll(): int
    {
        $stmt = $this->db->prepare("
            DELETE FROM system_bank_accounts
        ");

        $stmt->execute();

        return $stmt->rowCount();
    }

    /* =========================================================
     * reorder
     * ========================================================= */
    public function updateCodeTemp(string $id, int $code): void
    {
        $stmt = $this->db->prepare("
            UPDATE system_bank_accounts
            SET code = :code
            WHERE id = :id
        ");

        $stmt->execute(['code' => $code, 'id' => $id]);
    }

    public function updateCode(string $id, int $code): void
    {
        $stmt = $this->db->prepare("
            UPDATE system_bank_accounts
            SET code = :code
            WHERE id = :id
        ");

        $stmt->execute(['code' => $code, 'id' => $id]);
    }

    /* =========================================================
     * 엑셀 업서트
     * ========================================================= */
    public function upsertFromExcel(array $row, string $actor): void
    {
        $sql = "
            INSERT INTO system_bank_accounts (
                id, code, bank_name, account_number, account_holder,
                created_by, updated_by
            ) VALUES (
                :id, :code, :bank_name, :account_number, :account_holder,
                :created_by, :updated_by
            )
            ON DUPLICATE KEY UPDATE
                bank_name = VALUES(bank_name),
                account_number = VALUES(account_number),
                account_holder = VALUES(account_holder),
                updated_by = :updated_by2
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id' => $row['id'],
            'code' => $row['code'] ?? null,
            'bank_name' => $row['bank_name'] ?? null,
            'account_number' => $row['account_number'] ?? null,
            'account_holder' => $row['account_holder'] ?? null,
            'created_by' => $actor,
            'updated_by' => $actor,
            'updated_by2' => $actor
        ]);
    }
}