<?php
// 경로: PROJECT_ROOT/app/Models/User/DepartmentModel.php
namespace App\Models\User;

use PDO;
use Core\Database;

class DepartmentModel
{
    // PDO 보관
    private PDO $db;

    // 생성자 – 외부에서 PDO 주입 또는 자동 연결
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    /* ============================================================
     * 1) 전체 부서 조회
     * ============================================================ */
    public function getAll(array $filters = []): array
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
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters)) {
            $fieldMap = [
                'id'           => ['expr' => 'd.id', 'type' => 'exact'],
                'code'         => ['expr' => 'd.code', 'type' => 'like'],
                'dept_name'    => ['expr' => 'd.dept_name', 'type' => 'like'],
                'manager_id'   => ['expr' => 'd.manager_id', 'type' => 'exact'],
                'manager_name' => ['expr' => 'p.employee_name', 'type' => 'like'],
                'description'  => ['expr' => 'd.description', 'type' => 'like'],
                'is_active'    => ['expr' => 'd.is_active', 'type' => 'exact'],
                'created_at'   => ['expr' => 'd.created_at', 'type' => 'datetime'],
                'updated_at'   => ['expr' => 'd.updated_at', 'type' => 'datetime'],
            ];

            $globalSearchValues = [];

            foreach ($filters as $f) {
                $field = $f['field'] ?? '';
                $value = $f['value'] ?? '';

                if ($value === '' || $value === null) {
                    continue;
                }

                if ($field === '') {
                    $globalSearchValues[] = $value;
                    continue;
                }

                if (!isset($fieldMap[$field])) {
                    continue;
                }

                $expr = $fieldMap[$field]['expr'];
                $type = $fieldMap[$field]['type'];

                if ($type === 'datetime') {
                    if (is_array($value) && isset($value['start'], $value['end'])) {
                        $start = trim((string)($value['start'] ?? ''));
                        $end   = trim((string)($value['end'] ?? ''));

                        if ($start !== '' && $end !== '') {
                            $sql .= " AND {$expr} BETWEEN ? AND ?";
                            $params[] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)
                                ? $start . ' 00:00:00'
                                : $start;
                            $params[] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)
                                ? $end . ' 23:59:59'
                                : $end;
                        }
                    } else {
                        $stringValue = trim((string)$value);

                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $stringValue)) {
                            $sql .= " AND {$expr} BETWEEN ? AND ?";
                            $params[] = $stringValue . ' 00:00:00';
                            $params[] = $stringValue . ' 23:59:59';
                        } else {
                            $sql .= " AND {$expr} = ?";
                            $params[] = $stringValue;
                        }
                    }

                    continue;
                }

                if ($type === 'exact') {
                    $sql .= " AND {$expr} = ?";
                    $params[] = $value;
                    continue;
                }

                $keywords = array_filter(array_map('trim', explode(',', (string)$value)));

                if (!$keywords) {
                    continue;
                }

                $parts = [];
                foreach ($keywords as $keyword) {
                    $parts[] = "{$expr} LIKE ?";
                    $params[] = '%' . $keyword . '%';
                }

                $sql .= " AND (" . implode(' OR ', $parts) . ")";
            }

            foreach ($globalSearchValues as $value) {
                $keywords = array_filter(array_map('trim', explode(',', (string)$value)));

                if (!$keywords) {
                    continue;
                }

                $orParts = [];
                foreach ($keywords as $keyword) {
                    foreach (['d.code', 'd.dept_name', 'p.employee_name', 'd.description'] as $expr) {
                        $orParts[] = "{$expr} LIKE ?";
                        $params[] = '%' . $keyword . '%';
                    }
                }

                if ($orParts) {
                    $sql .= " AND (" . implode(' OR ', $orParts) . ")";
                }
            }
        }

        $sql .= " ORDER BY d.code ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ============================================================
     * 2) 단일 조회
     * ============================================================ */
    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
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

        $stmt = $this->db->prepare($sql);

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

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /* ============================================================
     * 5) 삭제
     * ============================================================ */
    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM user_departments WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /* ============================================================
     * 6) 부서장 지정
     * ============================================================ */
    public function assignManager(string $deptId, ?string $managerId): bool
    {
        $stmt = $this->db->prepare("
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
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM user_departments
                WHERE dept_name = ? AND id <> ?
            ");
            $stmt->execute([$deptName, $excludeId]);
        } else {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM user_departments
                WHERE dept_name = ?
            ");
            $stmt->execute([$deptName]);
        }

        return $stmt->fetchColumn() > 0;
    }

    public function updateCode(string $id, int $code): bool
    {
        $stmt = $this->db->prepare("
            UPDATE user_departments
            SET code = ?, updated_at = NOW()
            WHERE id = ?
        ");

        return $stmt->execute([$code, $id]);
    }
}
