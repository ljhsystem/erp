<?php
// 경로: PROJECT_ROOT . '/app/models/ledger/LedgerSubAccountModel.php'
// 설명:
//
namespace App\Models\Ledger;

use PDO;

class LedgerSubAccountModel
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?: \Core\Database::getInstance()->getConnection();
    }

    /* =========================================================
     * 계정별 보조계정 조회
     * ========================================================= */
    public function getByAccountId(string $accountId): array
    {
        $sql = "
        SELECT *
        FROM ledger_sub_accounts
        WHERE account_id = :account_id
        ORDER BY CAST(sub_code AS UNSIGNED)
        ";
    
        $stmt = $this->db->prepare($sql);
    
        $stmt->execute([
            ':account_id' => $accountId
        ]);
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =========================================================
     * 보조계정 생성
     * ========================================================= */
    public function create(array $data): bool
    {
        $sql = "
        INSERT INTO ledger_sub_accounts (
            id,
            account_id,
            sub_code,
            sub_name,
            note,
            memo,
            created_by
        )
        VALUES (
            :id,
            :account_id,
            :sub_code,
            :sub_name,
            :note,
            :memo,
            :created_by
        )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id' => $data['id'],
            ':account_id' => $data['account_id'],
            ':sub_code' => $data['sub_code'],
            ':sub_name' => $data['sub_name'],
            ':note' => $data['note'] ?? null,
            ':memo' => $data['memo'] ?? null,
            ':created_by' => $data['created_by'] ?? null
        ]);
    }

    public function update(string $id, array $data): bool
    {
        $sql = "
        UPDATE ledger_sub_accounts
        SET
            sub_name = :sub_name,
            note = :note,
            memo = :memo   -- 🔥 추가
        WHERE id = :id
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id' => $id,
            ':sub_name' => $data['sub_name'],
            ':note' => $data['note'],
            ':memo' => $data['memo']   // 🔥 추가
        ]);
    }

    /* =========================================================
     * 보조계정 삭제 (Soft Delete)
     * ========================================================= */
    public function delete(string $id): bool
    {
        $sql = "
        DELETE FROM ledger_sub_accounts
        WHERE id = :id
        ";
    
        $stmt = $this->db->prepare($sql);
    
        return $stmt->execute([
            ':id' => $id
        ]);
    }

    /* =========================================================
        * 중복 체크
        * ========================================================= */
        public function findByAccountAndName(string $accountId, string $subName): ?array
        {
            $stmt = $this->db->prepare("
                SELECT id
                FROM ledger_sub_accounts
                WHERE account_id = :account_id
                AND sub_name = :sub_name
                LIMIT 1
            ");

            $stmt->execute([
                ':account_id' => $accountId,
                ':sub_name'   => $subName
            ]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        /* =========================================================
        * 다음 sub_code 조회
        * ========================================================= */
        public function getNextSubCode(string $accountId): string
        {
            $stmt = $this->db->prepare("
                SELECT COALESCE(MAX(CAST(sub_code AS UNSIGNED)), 0) AS max_code
                FROM ledger_sub_accounts
                WHERE account_id = :account_id
            ");

            $stmt->execute([
                ':account_id' => $accountId
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return (string)(((int)$row['max_code']) + 1);
        }

        
    
}