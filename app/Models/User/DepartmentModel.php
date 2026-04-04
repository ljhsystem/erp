<?php
// 경로: PROJECT_ROOT/app/Models/User/DepartmentModel.php
namespace App\Models\User;

use PDO;

class DepartmentModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ============================================================
     * 1) 전체 부서 조회
     * ============================================================ */
    public function getAll(): array
    {
        $sql = "
            SELECT 
                d.id,
                d.code,
                d.dept_name,
                d.manager_id,
                p.employee_name AS manager_name,
                d.description,
                d.is_active,
                d.created_at,
                d.created_by,
                d.updated_at,
                d.updated_by
            FROM user_departments d
            LEFT JOIN auth_users au ON au.id = d.manager_id
            LEFT JOIN user_employees p ON p.user_id = au.id
            ORDER BY d.code ASC
        ";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ============================================================
     * 2) 단일 조회
     * ============================================================ */
    public function getById(string $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                d.id,
                d.code,
                d.dept_name,
                d.manager_id,
                p.employee_name AS manager_name,
                d.description,
                d.is_active,
                d.created_at,
                d.created_by,
                d.updated_at,
                d.updated_by
            FROM user_departments d
            LEFT JOIN auth_users au ON au.id = d.manager_id
            LEFT JOIN user_employees p ON p.user_id = au.id
            WHERE d.id = ?
        ");

        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* ============================================================
     * 3) 부서 생성 (UUID/CODE는 Service에서 처리)
     * ============================================================ */
    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO user_departments 
            (id, code, dept_name, manager_id, description, is_active, created_by, created_at)
            VALUES
            (:id, :code, :dept_name, :manager_id, :description, :is_active, :created_by, NOW())
        ";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':id'          => $data['id'],
            ':code'        => $data['code'],
            ':dept_name'   => $data['dept_name'],
            ':manager_id'  => $data['manager_id'] ?? null,
            ':description' => $data['description'] ?? null,
            ':is_active'   => $data['is_active'] ?? 1,
            ':created_by'  => $data['created_by'] ?? null,
        ]);
    }

    /* ============================================================
     * 4) 부서 수정
     * ============================================================ */
    public function update(string $id, array $data): bool
    {
        $set = [];
        $params = [];

        foreach ($data as $k => $v) {
            $set[] = "$k = :$k";
            $params[$k] = $v;
        }

        $set[] = "updated_at = NOW()";
        $params['id'] = $id;

        $sql = "UPDATE user_departments SET " . implode(', ', $set) . " WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /* ============================================================
     * 5) 삭제
     * ============================================================ */
    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM user_departments WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /* ============================================================
     * 6) 부서장 지정
     * ============================================================ */
    public function assignManager(string $deptId, ?string $managerId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE user_departments
            SET manager_id = :manager_id,
                updated_at = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':manager_id' => $managerId,
            ':id'         => $deptId
        ]);
    }

    /* ============================================================
     * 7) 중복 검사
     * ============================================================ */
    public function existsByName(string $deptName, ?string $excludeId = null): bool
    {
        if ($excludeId) {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM user_departments
                WHERE dept_name = ? AND id <> ?
            ");
            $stmt->execute([$deptName, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM user_departments
                WHERE dept_name = ?
            ");
            $stmt->execute([$deptName]);
        }

        return $stmt->fetchColumn() > 0;
    }
}
