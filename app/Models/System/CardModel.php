<?php
namespace App\Models\System;

use Core\Helpers\ActorHelper;
use PDO;
use Core\Database;

class CardModel
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
                cl.client_name,
                b.account_name
            FROM system_cards c
            LEFT JOIN system_clients cl ON c.client_id = cl.id
            LEFT JOIN system_bank_accounts b ON c.account_id = b.id
            WHERE c.deleted_at IS NULL
        ";

        $params = [];

        $fieldMap = [

            'sort_no'            => ['col'=>'c.sort_no','type'=>'exact'],
            'card_name'       => ['col'=>'c.card_name','type'=>'like'],
            'card_number'     => ['col'=>'c.card_number','type'=>'like'],

            'client_id'       => ['col'=>'c.client_id','type'=>'exact'],
            'account_id'      => ['col'=>'c.account_id','type'=>'exact'],
            'client_name'     => ['col'=>'cl.client_name','type'=>'like'],
            'account_name'    => ['col'=>'b.account_name','type'=>'like'],

            'expiry_year'     => ['col'=>'c.expiry_year','type'=>'exact'],
            'expiry_month'    => ['col'=>'c.expiry_month','type'=>'exact'],

            'limit_amount'    => ['col'=>'c.limit_amount','type'=>'exact'],

            'card_file'       => ['col'=>'c.card_file','type'=>'like'],
            'note'            => ['col'=>'c.note','type'=>'like'],
            'memo'            => ['col'=>'c.memo','type'=>'like'],

            'is_active'       => ['col'=>'c.is_active','type'=>'exact'],

            'created_at'      => ['col'=>'c.created_at','type'=>'date'],
            'updated_at'      => ['col'=>'c.updated_at','type'=>'date'],
            'created_by_name' => ['col'=>'c.created_by','type'=>'like'],
            'updated_by_name' => ['col'=>'c.updated_by','type'=>'like'],
            'deleted_by_name' => ['col'=>'c.deleted_by','type'=>'like'],
        ];

        $globalSearch = [];

        foreach ($filters as $f) {

            $field = $f['field'] ?? '';
            $value = $f['value'] ?? '';

            if ($value === '' || $value === null) continue;

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

                'c.card_name',
                'c.card_number',
                'c.note',
                'c.memo',

                'cl.client_name',
                'b.account_name',
                'c.created_by',
                'c.updated_by',
                'c.deleted_by'
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

        $sql .= " ORDER BY c.sort_no ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ActorHelper::enrichActorNames($rows, [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
            'deleted_by_name' => 'deleted_by',
        ]);
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.*,
                cl.client_name,
                b.account_name

            FROM system_cards c

            LEFT JOIN system_clients cl ON c.client_id = cl.id
            LEFT JOIN system_bank_accounts b ON c.account_id = b.id

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

    public function searchPicker(string $keyword = '', int $limit = 20): array
    {
        $limit = max(1, min(100, (int)$limit));

        $keyword = trim($keyword);
        $like = '%' . $keyword . '%';
        $prefix = $keyword . '%';

        $sql = "
            SELECT
                c.id,
                c.sort_no,
                c.card_name,
                c.card_number,
                cl.client_name

            FROM system_cards c

            LEFT JOIN system_clients cl ON c.client_id = cl.id

            WHERE c.deleted_at IS NULL
            AND (
                c.card_name LIKE :k1
                OR c.card_number LIKE :k2
                OR COALESCE(cl.client_name, '') LIKE :k3
            )

            ORDER BY
                CASE
                    WHEN c.card_name LIKE :prefix THEN 0
                    ELSE 1
                END,
                c.card_name ASC

            LIMIT {$limit}
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(':k1', $like, PDO::PARAM_STR);
        $stmt->bindValue(':k2', $like, PDO::PARAM_STR);
        $stmt->bindValue(':k3', $like, PDO::PARAM_STR);
        $stmt->bindValue(':prefix', $prefix, PDO::PARAM_STR);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO system_cards (
                id,
                sort_no,
                card_name,
                card_number,
                client_id,
                account_id,
                expiry_year,
                expiry_month,
                limit_amount,
                card_file,
                note,
                memo,
                is_active,
                created_by,
                updated_by
            )
            VALUES (
                :id,
                :sort_no,
                :card_name,
                :card_number,
                :client_id,
                :account_id,
                :expiry_year,
                :expiry_month,
                :limit_amount,
                :card_file,
                :note,
                :memo,
                :is_active,
                :created_by,
                :updated_by
            )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id' => $data['id'],
            'sort_no' => $data['sort_no'] ?? null,

            'card_name' => trim((string)($data['card_name'] ?? '')),
            'card_number' => trim((string)($data['card_number'] ?? '')),

            'client_id' => $data['client_id'] ?? null,
            'account_id' => $data['account_id'] ?? null,

            'expiry_year' => $data['expiry_year'] ?? null,
            'expiry_month' => $data['expiry_month'] ?? null,

            'limit_amount' => $data['limit_amount'] ?? 0,

            'card_file' => $data['card_file'] ?? null,

            'note' => $data['note'] ?? null,
            'memo' => $data['memo'] ?? null,

            'is_active' => $data['is_active'] ?? 1,

            'created_by' => $data['created_by'],
            'updated_by' => $data['updated_by'],
        ]);
    }

    public function updateById(string $id, array $data): bool
    {
        $sql = "
            UPDATE system_cards SET
                card_name = :card_name,
                card_number = :card_number,
                client_id = :client_id,
                account_id = :account_id,
                expiry_year = :expiry_year,
                expiry_month = :expiry_month,
                limit_amount = :limit_amount,
                card_file = :card_file,
                is_active = :is_active,
                note = :note,
                memo = :memo,
                updated_by = :updated_by
            WHERE id = :id
        ";

        $params = [
            'id' => $id,

            'card_name' => trim((string)($data['card_name'] ?? '')),
            'card_number' => trim((string)($data['card_number'] ?? '')),

            'client_id' => $data['client_id'] ?? null,
            'account_id' => $data['account_id'] ?? null,

            'expiry_year' => $data['expiry_year'] ?? null,
            'expiry_month' => $data['expiry_month'] ?? null,

            'limit_amount' => $data['limit_amount'] ?? 0,

            'card_file' => $data['card_file'] ?? null,

            'is_active' => $data['is_active'] ?? 1,

            'note' => $data['note'] ?? null,
            'memo' => $data['memo'] ?? null,

            'updated_by' => $data['updated_by']
        ];

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteById(string $id, string $actor): bool
    {
        $sql = "
            UPDATE system_cards
            SET
                is_active = 0,
                deleted_at = NOW(),
                deleted_by = :deleted_by,
                updated_by = :updated_by
            WHERE id = :id
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':id' => $id,
            ':deleted_by' => $actor,
            ':updated_by' => $actor
        ]);

        return $stmt->rowCount() > 0;
    }

    public function getDeleted(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.*,
                cl.client_name,
                b.account_name

            FROM system_cards c

            LEFT JOIN system_clients cl ON c.client_id = cl.id
            LEFT JOIN system_bank_accounts b ON c.account_id = b.id

            WHERE c.deleted_at IS NOT NULL
            ORDER BY c.deleted_at DESC
        ");

        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ActorHelper::enrichActorNames($rows, [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
            'deleted_by_name' => 'deleted_by',
        ]);
    }

    public function restoreById(string $id, string $actor): bool
    {
        $sql = "
            UPDATE system_cards
            SET
                is_active = 1,
                deleted_at = NULL,
                deleted_by = NULL,
                updated_by = :actor
            WHERE id = :id
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id' => $id,
            ':actor' => $actor
        ]);
    }

    public function hardDeleteById(string $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM system_cards
            WHERE id = :id AND deleted_at IS NOT NULL
        ");
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function updateSortNo(string $id, string $newSortNo): bool
    {
        $sql = "UPDATE system_cards SET sort_no = :newSortNo WHERE id = :id";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'newSortNo' => (int)$newSortNo,
            'id' => $id
        ]);
    }


}
