<?php
// 경로: PROJECT_ROOT . '/app/Models/System/CardModel.php'

namespace App\Models\System;

use PDO;

class CardModel
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
            SELECT *
            FROM system_cards
            WHERE deleted_at IS NULL
            ORDER BY code ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =========================================================
     * 단건
     * ========================================================= */
    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM system_cards
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* =========================================================
     * 생성
     * ========================================================= */
    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO system_cards (
                id, code,
                alias,
                card_name,
                card_number,
                card_company,
                owner_name,
                valid_thru,
                is_active,
                created_by,
                updated_by
            ) VALUES (
                :id, :code,
                :alias,
                :card_name,
                :card_number,
                :card_company,
                :owner_name,
                :valid_thru,
                :is_active,
                :created_by,
                :updated_by
            )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id' => $data['id'],
            'code' => $data['code'],
            'alias' => $data['alias'] ?? '',
            'card_name' => $data['card_name'] ?? null,
            'card_number' => $data['card_number'] ?? null,
            'card_company' => $data['card_company'] ?? null,
            'owner_name' => $data['owner_name'] ?? null,
            'valid_thru' => $data['valid_thru'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
            'created_by' => $data['created_by'],
            'updated_by' => $data['updated_by'],
        ]);
    }

    /* =========================================================
     * 수정
     * ========================================================= */
    public function updateById(string $id, array $data): bool
    {
        $sql = "
            UPDATE system_cards SET
                alias = :alias,
                card_name = :card_name,
                card_number = :card_number,
                card_company = :card_company,
                owner_name = :owner_name,
                valid_thru = :valid_thru,
                is_active = :is_active,
                updated_by = :updated_by
            WHERE id = :id
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id' => $id,
            'alias' => $data['alias'] ?? '',
            'card_name' => $data['card_name'] ?? null,
            'card_number' => $data['card_number'] ?? null,
            'card_company' => $data['card_company'] ?? null,
            'owner_name' => $data['owner_name'] ?? null,
            'valid_thru' => $data['valid_thru'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
            'updated_by' => $data['updated_by'],
        ]);
    }

    /* =========================================================
     * 삭제 (soft)
     * ========================================================= */
    public function deleteById(string $id, string $actor): bool
    {
        $stmt = $this->db->prepare("
            UPDATE system_cards
            SET deleted_at = NOW(), deleted_by = :actor
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
    public function getDeleted(): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM system_cards
            WHERE deleted_at IS NOT NULL
            ORDER BY deleted_at DESC
        ");

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =========================================================
     * 복원
     * ========================================================= */
    public function restoreById(string $id, string $actor): bool
    {
        $stmt = $this->db->prepare("
            UPDATE system_cards
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

    /* =========================================================
     * 영구삭제
     * ========================================================= */
    public function hardDeleteById(string $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM system_cards
            WHERE id = :id
        ");

        return $stmt->execute(['id' => $id]);
    }

    /* =========================================================
     * 코드 변경 (reorder)
     * ========================================================= */
    public function updateCode(string $id, int $newCode): bool
    {
        $stmt = $this->db->prepare("
            UPDATE system_cards
            SET code = :code
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'code' => $newCode
        ]);
    }
}