<?php
// 寃쎈줈: PROJECT_ROOT . '/app/Models/System/CoverImageModel.php'
namespace App\Models\System;

use PDO;
use Core\Database;

class CoverImageModel
{
    // PDO 蹂닿?
    private PDO $db;

    // ?앹꽦?????몃??먯꽌 PDO 주입 ?는 ?동 ?결
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }


    /* =============================================================
     * ?체 紐⑸줉 議고쉶 (愿由ъ옄??
     *  - 湲곕낯? ?쒖꽦 ?곗씠?곕쭔 議고쉶
     * ============================================================= */
    public function getList(array $filters = []): array
    {
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
            WHERE c.deleted_at IS NULL
        ";

        $params = [];

        /* =========================================================
        * ? ?체 컬럼 留?
        * ========================================================= */
        $fieldMap = [

            'sort_no'        => ['col'=>'c.sort_no','type'=>'exact'],
            'year'        => ['col'=>'c.year','type'=>'exact'],

            'title'       => ['col'=>'c.title','type'=>'like'],
            'alt'         => ['col'=>'c.alt','type'=>'like'],
            'description' => ['col'=>'c.description','type'=>'like'],
            'src'         => ['col'=>'c.src','type'=>'like'],

            'is_active'   => ['col'=>'c.is_active','type'=>'exact'],

            'created_at'  => ['col'=>'c.created_at','type'=>'date'],
            'updated_at'  => ['col'=>'c.updated_at','type'=>'date'],
        ];

        $globalSearch = [];

        /* =========================================================
        * ?뵦 ?꾪꽣 泥섎━
        * ========================================================= */
        foreach ($filters as $i => $f) {

            $field = $f['field'] ?? '';
            $value = $f['value'] ?? '';

            if ($value === '' || $value === null) continue;

            // ?뵦 year_start
            if ($field === 'year_start') {
                $sql .= " AND CAST(c.year AS UNSIGNED) >= ?";
                $params[] = (int)$value;
                continue;
            }

            // ?뵦 year_end
            if ($field === 'year_end') {
                $sql .= " AND CAST(c.year AS UNSIGNED) <= ?";
                $params[] = (int)$value;
                continue;
            }

            // ? ?체??
            if ($field === '') {
                $globalSearch[] = $value;
                continue;
            }

            if (!isset($fieldMap[$field])) continue;

            $col  = $fieldMap[$field]['col'];
            $type = $fieldMap[$field]['type'];

            // ?좎쭨
            if ($type === 'date') {

                if (is_array($value)) {
                    $sql .= " AND DATE($col) BETWEEN ? AND ?";
                    $params[] = $value['start'];
                    $params[] = $value['end'];
                } else {
                    $sql .= " AND DATE($col) = ?";
                    $params[] = $value;
                }
                continue;
            }

            // LIKE
            if ($type === 'like') {
                $sql .= " AND $col LIKE ?";
                $params[] = "%{$value}%";
                continue;
            }

            // EXACT
            if ($type === 'exact') {
                $sql .= " AND $col = ?";
                $params[] = $value;
                continue;
            }
        }

        /* =========================================================
        * ? ?체??(?스??컬럼)
        * ========================================================= */
        if (!empty($globalSearch)) {

            $searchCols = [
                'c.year',
                'c.title',
                'c.alt',
                'c.description',
                'c.src'
            ];

            $sql .= " AND (";

            $first = true;

            foreach ($globalSearch as $keyword) {

                if (!$first) $sql .= " OR ";

                $sql .= "(";

                $colFirst = true;

                foreach ($searchCols as $col) {

                    if (!$colFirst) $sql .= " OR ";

                    $sql .= "$col LIKE ?";
                    $params[] = "%{$keyword}%";

                    $colFirst = false;
                }

                $sql .= ")";
                $first = false;
            }

            $sql .= ")";
        }

        $sql .= " ORDER BY c.sort_no DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =============================================================
    * 공개 ?이吏??紐⑸줉 議고쉶 (About, Home ??
    *  - ?쒖꽦 ?곗씠?곕쭔 議고쉶
    *  - 肄붾뱶 湲곗? ?뺣젹
    * ============================================================= */
    public function getPublicList(): array
    {
        $sql = "
            SELECT
                id,
                sort_no,
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
              AND is_active = 1
              AND src IS NOT NULL
              AND src <> ''
            ORDER BY sort_no DESC
        ";

        $stmt = $this->db->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }


    /* =============================================================
     * ?④굔 議고쉶
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
     * ?앹꽦
     * ============================================================= */
    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO system_coverimage_assets (
                id,
                sort_no,
                year,
                title,
                alt,
                description,
                src,
                is_active,
                created_by,
                updated_by
            ) VALUES (
                :id,
                :sort_no,
                :year,
                :title,
                :alt,
                :description,
                :src,
                :is_active,
                :created_by,
                :updated_by
            )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id'          => $data['id'],
            ':sort_no'        => $data['sort_no'],
            ':year'        => $data['year'],
            ':title'       => $data['title'],
            ':alt'         => $data['alt'],
            ':description' => $data['description'],
            ':src'         => $data['src'],
            ':is_active'   => $data['is_active'] ?? 1,
            ':created_by'  => $data['created_by'],
            ':updated_by'  => $data['updated_by'],
        ]);
    }

    /* =============================================================
     * ?섏젙
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
     * ?댁????대룞 (?뚰봽?몄궘??
     * ============================================================= */
    public function deleteById(string $id, ?string $deletedBy): bool
    {
        $sql = "
            UPDATE system_coverimage_assets
            SET
                is_active = 0,
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
     * ?댁???紐⑸줉 議고쉶
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
     * ?댁???蹂듭썝
     * ============================================================= */
    public function restoreById(string $id, ?string $updatedBy): bool
    {
        $sql = "
            UPDATE system_coverimage_assets
            SET
                is_active = 1,
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
     * ?섎뱶??젣
     * ============================================================= */
     public function hardDeleteById(string $id): bool
     {
         $sql = "DELETE FROM system_coverimage_assets WHERE id = :id";

         $stmt = $this->db->prepare($sql);

         return $stmt->execute([
             ':id' => $id
         ]);
     }



    public function updateSortNo(string $id, string $newSortNo): bool
    {
        $sql = "UPDATE system_coverimage_assets SET sort_no = :newSortNo WHERE id = :id";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'newSortNo' => (int)$newSortNo,
            'id' => $id
        ]);
    }


}
