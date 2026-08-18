<?php
namespace App\Models\System;

use Core\Helpers\ActorHelper;
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
                c.*
            FROM system_coverimage_assets c
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

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return ActorHelper::enrichActorNames($rows, [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
            'deleted_by_name' => 'deleted_by',
        ]);
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
                c.*

            FROM system_coverimage_assets c

            WHERE c.id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return ActorHelper::enrichActorNamesRow($row, [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
            'deleted_by_name' => 'deleted_by',
        ]);
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
                c.*

            FROM system_coverimage_assets c

            WHERE c.deleted_at IS NOT NULL
            ORDER BY c.deleted_at DESC
        ");

        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return ActorHelper::enrichActorNames($rows, [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
            'deleted_by_name' => 'deleted_by',
        ]);
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

    public function resequenceCodes(): void
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->query("
                SELECT id, deleted_at, sort_no
                FROM system_coverimage_assets
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->db->exec("
                UPDATE system_coverimage_assets
                SET sort_no = sort_no + 100000
            ");

            usort($rows, static function (array $a, array $b): int {
                $aDeleted = empty($a['deleted_at']) ? 0 : 1;
                $bDeleted = empty($b['deleted_at']) ? 0 : 1;

                return $aDeleted !== $bDeleted
                    ? $aDeleted <=> $bDeleted
                    : (int) $a['sort_no'] <=> (int) $b['sort_no'];
            });

            $sequence = 1;
            foreach ($rows as $row) {
                $stmt = $this->db->prepare("
                    UPDATE system_coverimage_assets
                    SET sort_no = :sort_no
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':sort_no' => $sequence++,
                    ':id' => $row['id'],
                ]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }


}
