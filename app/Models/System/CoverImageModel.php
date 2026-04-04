<?php
// 경로: PROJECT_ROOT . '/app/Models/System/CoverImageModel.php'
namespace App\Models\System;

use PDO;

class CoverImageModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \Core\Database::getInstance()->getConnection();
    }


    /* =============================================================
     * 2. 전체 목록 조회 (관리자용)
     *  - 기본은 활성 데이터만 조회
     * ============================================================= */
    public function getList(array $filters = []): array
    {
        $where  = ["c.deleted_at IS NULL"];
        $params = [];

        foreach ($filters as $i => $filter) {
            $field = $filter['field'] ?? '';
            $value = $filter['value'] ?? null;

            if ($field === '' || $value === null || $value === '') continue;

            // 🔥 year_start
            if ($field === 'year_start') {
                $where[] = "CAST(c.year AS UNSIGNED) >= :year_start_{$i}";
                $params[":year_start_{$i}"] = (int)$value;
                continue;
            }

            // 🔥 year_end
            if ($field === 'year_end') {
                $where[] = "CAST(c.year AS UNSIGNED) <= :year_end_{$i}";
                $params[":year_end_{$i}"] = (int)$value;
                continue;
            }

            if (in_array($field, ['code','year','title','alt','description'], true)) {
                $where[] = "c.{$field} LIKE :keyword_{$i}";
                $params[":keyword_{$i}"] = '%' . trim((string)$value) . '%';
            }
        }

        $sql = "
            SELECT
                c.*,

            CASE 
                WHEN c.created_by LIKE 'SYSTEM:%' THEN c.created_by
                WHEN p1.employee_name IS NOT NULL THEN CONCAT('USER:', p1.employee_name)
                ELSE c.created_by
            END AS created_by_name,

            CASE 
                WHEN c.updated_by LIKE 'SYSTEM:%' THEN c.updated_by
                WHEN p2.employee_name IS NOT NULL THEN CONCAT('USER:', p2.employee_name)
                ELSE c.updated_by
            END AS updated_by_name,

            CASE 
                WHEN c.deleted_by LIKE 'SYSTEM:%' THEN c.deleted_by
                WHEN p3.employee_name IS NOT NULL THEN CONCAT('USER:', p3.employee_name)
                ELSE c.deleted_by
            END AS deleted_by_name

            FROM system_coverimage_assets c

            LEFT JOIN user_employees p1
                ON c.created_by NOT LIKE 'SYSTEM:%'
            AND p1.user_id = REPLACE(c.created_by, 'USER:', '')

            LEFT JOIN user_employees p2
                ON c.updated_by NOT LIKE 'SYSTEM:%'
            AND p2.user_id = REPLACE(c.updated_by, 'USER:', '')

            LEFT JOIN user_employees p3
                ON c.deleted_by NOT LIKE 'SYSTEM:%'
            AND p3.user_id = REPLACE(c.deleted_by, 'USER:', '')

            WHERE " . implode(" AND ", $where) . "

            ORDER BY c.code ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /* =============================================================
     * 4. 단건 조회
     * ============================================================= */
    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.*,

            CASE 
                WHEN c.created_by LIKE 'SYSTEM:%' THEN c.created_by
                WHEN p1.employee_name IS NOT NULL THEN CONCAT('USER:', p1.employee_name)
                ELSE c.created_by
            END AS created_by_name,

            CASE 
                WHEN c.updated_by LIKE 'SYSTEM:%' THEN c.updated_by
                WHEN p2.employee_name IS NOT NULL THEN CONCAT('USER:', p2.employee_name)
                ELSE c.updated_by
            END AS updated_by_name,

            CASE 
                WHEN c.deleted_by LIKE 'SYSTEM:%' THEN c.deleted_by
                WHEN p3.employee_name IS NOT NULL THEN CONCAT('USER:', p3.employee_name)
                ELSE c.deleted_by
            END AS deleted_by_name

            FROM system_coverimage_assets c

            LEFT JOIN user_employees p1
                ON c.created_by NOT LIKE 'SYSTEM:%'
            AND p1.user_id = REPLACE(c.created_by, 'USER:', '')

            LEFT JOIN user_employees p2
                ON c.updated_by NOT LIKE 'SYSTEM:%'
            AND p2.user_id = REPLACE(c.updated_by, 'USER:', '')

            LEFT JOIN user_employees p3
                ON c.deleted_by NOT LIKE 'SYSTEM:%'
            AND p3.user_id = REPLACE(c.deleted_by, 'USER:', '')

            WHERE c.id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* =============================================================
     * 5. 생성
     * ============================================================= */
    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO system_coverimage_assets (
                id,
                code,
                year,
                title,
                alt,
                description,
                src,
                created_by,
                updated_by
            ) VALUES (
                :id,
                :code,
                :year,
                :title,
                :alt,
                :description,
                :src,
                :created_by,
                :updated_by
            )
        ";

        return $this->db->prepare($sql)->execute($data);
    }

    /* =============================================================
     * 6. 수정
     * ============================================================= */
    public function updateById(string $id, array $data): bool
    {
        $set = [];
        $params = ['id' => $id];

        foreach ($data as $key => $value) {
            $set[] = "{$key} = :{$key}";
            $params[$key] = $value;
        }

        $sql = "
            UPDATE system_coverimage_assets
            SET " . implode(', ', $set) . ",
                updated_at = NOW()
            WHERE id = :id
        ";

        return $this->db->prepare($sql)->execute($params);
    }


    /* =============================================================
     * 7. 휴지통 이동 (소프트삭제)
     * ============================================================= */
    public function deleteById(string $id, ?string $deletedBy): bool
    {
        $sql = "
            UPDATE system_coverimage_assets
            SET
                deleted_at = NOW(),
                deleted_by = :deleted_by,
                updated_at = NOW(),
                updated_by = :updated_by    
            WHERE id = :id
        ";
    
        $stmt = $this->db->prepare($sql);
    
        return $stmt->execute([
            ':id'         => $id,
            ':deleted_by' => $deletedBy,
            ':updated_by' => $deletedBy,
        ]);
    }



    /* =============================================================
     * 3. 휴지통 목록 조회
     * ============================================================= */
    public function getDeleted(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.*,

            CASE 
                WHEN c.created_by LIKE 'SYSTEM:%' THEN c.created_by
                WHEN p1.employee_name IS NOT NULL THEN CONCAT('USER:', p1.employee_name)
                ELSE c.created_by
            END AS created_by_name,

            CASE 
                WHEN c.updated_by LIKE 'SYSTEM:%' THEN c.updated_by
                WHEN p2.employee_name IS NOT NULL THEN CONCAT('USER:', p2.employee_name)
                ELSE c.updated_by
            END AS updated_by_name,

            CASE 
                WHEN c.deleted_by LIKE 'SYSTEM:%' THEN c.deleted_by
                WHEN p3.employee_name IS NOT NULL THEN CONCAT('USER:', p3.employee_name)
                ELSE c.deleted_by
            END AS deleted_by_name

            FROM system_coverimage_assets c

            LEFT JOIN user_employees p1
                ON c.created_by NOT LIKE 'SYSTEM:%'
            AND p1.user_id = REPLACE(c.created_by, 'USER:', '')

            LEFT JOIN user_employees p2
                ON c.updated_by NOT LIKE 'SYSTEM:%'
            AND p2.user_id = REPLACE(c.updated_by, 'USER:', '')

            LEFT JOIN user_employees p3
                ON c.deleted_by NOT LIKE 'SYSTEM:%'
            AND p3.user_id = REPLACE(c.deleted_by, 'USER:', '')

            WHERE c.deleted_at IS NOT NULL
            ORDER BY c.deleted_at DESC
        ");

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }






    /* =============================================================
     * 8. 휴지통 복원
     * ============================================================= */
    public function restoreById(string $id, ?string $updatedBy): bool
    {
        $sql = "
            UPDATE system_coverimage_assets
            SET
                deleted_at = NULL,
                deleted_by = NULL,
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE id = :id
        ";
    
        $stmt = $this->db->prepare($sql);
    
        return $stmt->execute([
            ':id'         => $id,
            ':updated_by' => $updatedBy,
        ]);
    }
    

    /* =============================================================
     * 9. 하드삭제
     * ============================================================= */
     public function hardDeleteById(string $id): bool
     {
         $sql = "DELETE FROM system_coverimage_assets WHERE id = :id";
     
         $stmt = $this->db->prepare($sql);
     
         return $stmt->execute([
             ':id' => $id
         ]);
     }







     public function hardDeleteBulkByIds(array $ids): bool
     {
         if (empty($ids)) return false;
     
         $placeholders = implode(',', array_fill(0, count($ids), '?'));
     
         $sql = "DELETE FROM system_coverimage_assets WHERE id IN ($placeholders)";
     
         $stmt = $this->db->prepare($sql);
         $stmt->execute($ids);
     
         return $stmt->rowCount() > 0;
     }
     
     public function hardDeleteAllDeleted(): bool
     {
         $stmt = $this->db->prepare("
             DELETE FROM system_coverimage_assets
             WHERE deleted_at IS NOT NULL
         ");
     
         $stmt->execute();
     
         return true;
     }



     public function updateCode(string $id, string $newCode): bool
     {
         $sql = "UPDATE system_coverimage_assets SET code = :newCode WHERE id = :id";
         $stmt = $this->db->prepare($sql);
 
         $ok = $stmt->execute(['newCode' => $newCode, 'id' => $id]);
 
         if (!$ok) {
             throw new \Exception('쿼리 실행 실패');
         }
         if ($stmt->rowCount() === 0) {
             throw new \Exception('업데이트된 행이 없습니다.');
         }
 
         return true;
     }


    /* =============================================================
    * 1. 공개 페이지용 목록 조회 (About, Home 등)
    *  - 활성 데이터만 조회
    *  - 코드 기준 정렬
    * ============================================================= */
    public function getPublicList(): array
    {
        $sql = "
            SELECT
                id,
                code,
                year,
                title,
                alt,
                description,
                src,        
                created_at,
                created_by,
                updated_at,
                updated_by,
                deleted_at,
                deleted_by
            FROM system_coverimage_assets
            WHERE deleted_at IS NULL
            ORDER BY code ASC
        ";

        $stmt = $this->db->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }


}