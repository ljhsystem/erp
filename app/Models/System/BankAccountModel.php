<?php
namespace App\Models\System;

use PDO;
use Core\Helpers\ActorHelper;
use Core\Database;

class BankAccountModel
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
                a.*,
                a.created_by AS created_by_name,
                a.updated_by AS updated_by_name,
                a.deleted_by AS deleted_by_name
            FROM system_bank_accounts a
            WHERE a.deleted_at IS NULL
        ";

        $params = [];

        $fieldMap = [

            'id'              => ['col'=>'a.id','type'=>'exact'],
            'sort_no'         => ['col'=>'a.sort_no','type'=>'exact'],
            'account_name'    => ['col'=>'a.account_name','type'=>'like'],
            'bank_name'       => ['col'=>'a.bank_name','type'=>'like'],
            'account_number'  => ['col'=>'a.account_number','type'=>'like'],
            'account_holder'  => ['col'=>'a.account_holder','type'=>'like'],
            'account_type'    => ['col'=>'a.account_type','type'=>'like'],
            'currency'        => ['col'=>'a.currency','type'=>'like'],

            'bank_file'       => ['col'=>'a.bank_file','type'=>'like'],

            'note'            => ['col'=>'a.note','type'=>'like'],
            'memo'            => ['col'=>'a.memo','type'=>'like'],

            'is_active'       => ['col'=>'a.is_active','type'=>'exact'],

            'created_at'      => ['col'=>'a.created_at','type'=>'date'],
            'created_by'      => ['col'=>'a.created_by','type'=>'like'],
            'created_by_name' => ['col'=>'a.created_by','type'=>'like'],
            'updated_at'      => ['col'=>'a.updated_at','type'=>'date'],
            'updated_by'      => ['col'=>'a.updated_by','type'=>'like'],
            'updated_by_name' => ['col'=>'a.updated_by','type'=>'like'],
            'deleted_at'      => ['col'=>'a.deleted_at','type'=>'date'],
            'deleted_by'      => ['col'=>'a.deleted_by','type'=>'like'],
            'deleted_by_name' => ['col'=>'a.deleted_by','type'=>'like'],
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

                'a.account_name',
                'a.bank_name',
                'a.account_number',
                'a.account_holder',
                'a.account_type',
                'a.currency',
                'a.note',
                'a.memo',
                'a.created_by',
                'a.updated_by',
                'a.deleted_by',
                'a.created_by',
                'a.updated_by',
                'a.deleted_by'
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

        $sql .= " ORDER BY a.sort_no ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ActorHelper::enrichActorNames($rows, [
            'created_by_name' => 'created_by_name',
            'updated_by_name' => 'updated_by_name',
            'deleted_by_name' => 'deleted_by_name',
        ]);
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                a.*,

                a.created_by AS created_by_name,
                a.updated_by AS updated_by_name,
                a.deleted_by AS deleted_by_name

            FROM system_bank_accounts a

            WHERE a.id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return ActorHelper::enrichActorNamesRow($row, [
            'created_by_name' => 'created_by_name',
            'updated_by_name' => 'updated_by_name',
            'deleted_by_name' => 'deleted_by_name',
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
                id,
                sort_no,
                account_name,
                bank_name,
                account_number,
                account_holder

            FROM system_bank_accounts

            WHERE deleted_at IS NULL
            AND (
                bank_name LIKE :k1
                OR account_number LIKE :k2
                OR account_holder LIKE :k3
                OR account_name LIKE :k4
            )

            ORDER BY
                CASE
                    WHEN account_name LIKE :prefix THEN 0
                    ELSE 1
                END,
                account_name ASC

            LIMIT {$limit}
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(':k1', $like, PDO::PARAM_STR);
        $stmt->bindValue(':k2', $like, PDO::PARAM_STR);
        $stmt->bindValue(':k3', $like, PDO::PARAM_STR);
        $stmt->bindValue(':k4', $like, PDO::PARAM_STR);
        $stmt->bindValue(':prefix', $prefix, PDO::PARAM_STR);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO system_bank_accounts (
                id,
                sort_no,
                account_name,
                bank_name,
                account_number,
                account_holder,
                account_type,
                currency,
                bank_file,
                is_active,
                note,
                memo,
                created_by,
                updated_by
            )
            VALUES (
                :id,
                :sort_no,
                :account_name,
                :bank_name,
                :account_number,
                :account_holder,
                :account_type,
                :currency,
                :bank_file,
                :is_active,
                :note,
                :memo,
                :created_by,
                :updated_by
            )
    ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id' => $data['id'],
            'sort_no' => $data['sort_no'] ?? null,
            'account_name' => $data['account_name'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'account_holder' => $data['account_holder'] ?? null,
            'account_type' => $data['account_type'] ?? null,
            'currency' => $data['currency'] ?? 'KRW',
            'bank_file' => $data['bank_file'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
            'note' => $data['note'] ?? null,
            'memo' => $data['memo'] ?? null,
            'created_by' => $data['created_by'],
            'updated_by' => $data['updated_by'],
        ]);
    }

    public function updateById(string $id, array $data): bool
    {
        $sql = "
                UPDATE system_bank_accounts SET
                    account_name = :account_name,
                    bank_name = :bank_name,
                    account_number = :account_number,
                    account_holder = :account_holder,
                    account_type = :account_type,
                    currency = :currency,
                    bank_file = :bank_file,
                    is_active = :is_active,
                    note = :note,
                    memo = :memo,
                    updated_by = :updated_by
                WHERE id = :id
        ";

        $params = [
            'id' => $id,
            'account_name' => $data['account_name'] ?? '',
            'bank_name' => trim((string)($data['bank_name'] ?? '')),
            'account_number' => trim((string)($data['account_number'] ?? '')),
            'account_holder' => trim((string)($data['account_holder'] ?? '')),
            'account_type' => $data['account_type'] ?? null,
            'currency' => $data['currency'] ?? 'KRW',
            'bank_file' => $data['bank_file'] ?? null,
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
            UPDATE system_bank_accounts
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
                a.*,

                a.created_by AS created_by_name,
                a.updated_by AS updated_by_name,
                a.deleted_by AS deleted_by_name

            FROM system_bank_accounts a

            WHERE a.deleted_at IS NOT NULL
            ORDER BY a.deleted_at DESC
        ");

        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ActorHelper::enrichActorNames($rows, [
            'created_by_name' => 'created_by_name',
            'updated_by_name' => 'updated_by_name',
            'deleted_by_name' => 'deleted_by_name',
        ]);
    }

    public function restoreById(string $id, string $actor): bool
    {
        $sql = "
            UPDATE system_bank_accounts
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
            DELETE FROM system_bank_accounts
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $id
        ]);

        return $stmt->rowCount() > 0;
    }

    public function updateSortNo(string $id, string $newSortNo): bool
    {
        $sql = "UPDATE system_bank_accounts SET sort_no = :newSortNo WHERE id = :id";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'newSortNo' => (int)$newSortNo,
            'id' => $id
        ]);
    }


}
