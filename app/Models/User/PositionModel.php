<?php
// 경로: PROJECT_ROOT/app/Models/User/PositionModel.php
namespace App\Models\User;

use PDO;
use Core\Database;

class PositionModel
{
    // PDO 보관
    private PDO $db;

    // 생성자 – 외부에서 PDO 주입 또는 자동 연결
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    /* ============================================================
     * 1) 전체 직책 목록 조회
     * ============================================================ */
    public function getAll(array $filters = []): array
    {
        $sql = "
            SELECT
                id,
                sort_no,
                position_name,
                level_rank,
                description,
                is_active,
                created_at,
                created_by,
                updated_at,
                updated_by
            FROM user_positions
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters)) {
            $fieldMap = [
                'id'            => ['expr' => 'id', 'type' => 'exact'],
                'sort_no'          => ['expr' => 'sort_no', 'type' => 'like'],
                'position_name' => ['expr' => 'position_name', 'type' => 'like'],
                'level_rank'    => ['expr' => 'level_rank', 'type' => 'exact'],
                'description'   => ['expr' => 'description', 'type' => 'like'],
                'is_active'     => ['expr' => 'is_active', 'type' => 'exact'],
                'created_at'    => ['expr' => 'created_at', 'type' => 'datetime'],
                'created_by'    => ['expr' => 'created_by', 'type' => 'like'],
                'updated_at'    => ['expr' => 'updated_at', 'type' => 'datetime'],
                'updated_by'    => ['expr' => 'updated_by', 'type' => 'like'],
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
                    foreach (['id', 'sort_no', 'position_name', 'level_rank', 'description', 'created_by', 'updated_by'] as $expr) {
                        $orParts[] = "{$expr} LIKE ?";
                        $params[] = '%' . $keyword . '%';
                    }
                }

                if ($orParts) {
                    $sql .= " AND (" . implode(' OR ', $orParts) . ")";
                }
            }
        }

        $sql .= " ORDER BY sort_no ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* ============================================================
     * 3) 직책명 중복 검사
     * ============================================================ */
    public function existsByName(string $name, ?string $excludeId = null): bool
    {
        if ($excludeId) {
            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM user_positions
                WHERE position_name = :name AND id <> :id
            ");
            $stmt->execute([':name' => $name, ':id' => $excludeId]);
        } else {
            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM user_positions
                WHERE position_name = :name
            ");
            $stmt->execute([':name' => $name]);
        }

        return $stmt->fetchColumn() > 0;
    }

    /* ============================================================
     * 4) 생성 (UUID 및 sort_no 생성은 서비스에서 처리)
     * ============================================================ */
    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO user_positions
            (id, sort_no, position_name, level_rank, description, is_active, created_by, created_at)
            VALUES
            (:id, :sort_no, :position_name, :level_rank, :description, :is_active, :created_by, NOW())
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id'            => $data['id'],
            ':sort_no'          => $data['sort_no'],
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

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /* ============================================================
     * 6) 삭제
     * ============================================================ */
    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM user_positions WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function updateSortNo(string $id, int $sort_no): bool
    {
        $stmt = $this->db->prepare("
            UPDATE user_positions
            SET sort_no = ?, updated_at = NOW()
            WHERE id = ?
        ");

        return $stmt->execute([$sort_no, $id]);
    }
}
