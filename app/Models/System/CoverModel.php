<?php
namespace App\Models\System;

use PDO;
use Core\Database;

class CoverModel
{
    private PDO $db;

public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }


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

        foreach ($filters as $i => $f) {

            $field = $f['field'] ?? '';
            $value = $f['value'] ?? '';

            if ($value === '' || $value === null) continue;

            if ($field === 'year_start') {
                $sql .= " AND CAST(c.year AS UNSIGNED) >= ?";
                $params[] = (int)$value;
                continue;
            }

            if ($field === 'year_end') {
                $sql .= " AND CAST(c.year AS UNSIGNED) <= ?";
                $params[] = (int)$value;
                continue;
            }

            if ($field === '') {
                $globalSearch[] = $value;
                continue;
            }

            if (!isset($fieldMap[$field])) continue;

            $col  = $fieldMap[$field]['col'];
            $type = $fieldMap[$field]['type'];

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

            if ($type === 'like') {
                $sql .= " AND $col LIKE ?";
                $params[] = "%{$value}%";
                continue;
            }

            if ($type === 'exact') {
                $sql .= " AND $col = ?";
                $params[] = $value;
                continue;
            }
        }

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
