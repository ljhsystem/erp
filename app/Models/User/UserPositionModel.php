<?php
// 경로: PROJECT_ROOT/app/models/user/UserPositionModel.php
namespace App\Models\User;

use PDO;

class UserPositionModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ============================================================
     * 1) 전체 직책 목록 조회
     * ============================================================ */
    public function getAll(): array
    {
        $sql = "
            SELECT
                id,
                code,
                position_name,
                level_rank,
                description,
                is_active,
                created_at,
                created_by,
                updated_at,
                updated_by
            FROM user_positions
            ORDER BY level_rank ASC, code ASC
        ";

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ============================================================
     * 2) 단일 조회
     * ============================================================ */
    public function getById(string $id): ?array
    {
        $sql = "
            SELECT *
            FROM user_positions
            WHERE id = ?
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* ============================================================
     * 3) 직책명 중복 검사
     * ============================================================ */
    public function existsByName(string $name, ?string $excludeId = null): bool
    {
        if ($excludeId) {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM user_positions
                WHERE position_name = :name AND id <> :id
            ");
            $stmt->execute([':name' => $name, ':id' => $excludeId]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM user_positions
                WHERE position_name = :name
            ");
            $stmt->execute([':name' => $name]);
        }

        return $stmt->fetchColumn() > 0;
    }

    /* ============================================================
     * 4) 생성 (UUID 및 Code 생성은 서비스에서 처리)
     * ============================================================ */
    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO user_positions
            (id, code, position_name, level_rank, description, is_active, created_by, created_at)
            VALUES
            (:id, :code, :position_name, :level_rank, :description, :is_active, :created_by, NOW())
        ";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':id'            => $data['id'],
            ':code'          => $data['code'],
            ':position_name' => $data['position_name'],
            ':level_rank'    => $data['level_rank'],
            ':description'   => $data['description'] ?? null,
            ':is_active'     => $data['is_active'] ?? 1,
            ':created_by'    => $data['created_by'] ?? null
        ]);
    }

    /* ============================================================
     * 5) 수정
     * ============================================================ */
    public function update(string $id, array $data): bool
    {
        $set = [];
        $params = [];

        foreach ($data as $k => $v) {
            if ($k === 'description' && $v === '') {
                $v = null;
            }
            $set[] = "$k = :$k";
            $params[$k] = $v;
        }

        $set[] = "updated_at = NOW()";
        $params['id'] = $id;

        $sql = "UPDATE user_positions SET " . implode(', ', $set) . " WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /* ============================================================
     * 6) 삭제
     * ============================================================ */
    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM user_positions WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
