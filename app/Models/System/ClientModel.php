<?php
// 寃쎈줈: PROJECT_ROOT . '/app/Models/System/ClientModel.php'
namespace App\Models\System;

use PDO;
use Core\Database;

class ClientModel
{
    // PDO 蹂닿?
    private PDO $db;

    // ?앹꽦?????몃??먯꽌 PDO 주입 ?는 ?동 ?결
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    /* -------------------------------------------------------------
     * 嫄곕옒泥??체 紐⑸줉
     * ------------------------------------------------------------- */
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
            FROM system_clients c
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
         * ? ?체 컬럼 留?(?꾨? ?ы븿)
         * ========================================================= */
        $fieldMap = [

            // 湲곕낯
            'sort_no'              => ['col'=>'c.sort_no','type'=>'exact'],
            'client_name'       => ['col'=>'c.client_name','type'=>'like'],
            'company_name'      => ['col'=>'c.company_name','type'=>'like'],

            // ?ъ뾽??
            'business_number'   => ['col'=>'c.business_number','type'=>'like'],
            'rrn'               => ['col'=>'c.rrn','type'=>'like'],
            'business_type'     => ['col'=>'c.business_type','type'=>'like'],
            'business_category' => ['col'=>'c.business_category','type'=>'like'],
            'business_status'   => ['col'=>'c.business_status','type'=>'like'],

            // ?몃Ъ
            'ceo_name'          => ['col'=>'c.ceo_name','type'=>'like'],
            'ceo_phone'         => ['col'=>'c.ceo_phone','type'=>'like'],
            'manager_name'      => ['col'=>'c.manager_name','type'=>'like'],
            'manager_phone'     => ['col'=>'c.manager_phone','type'=>'like'],

            // ?곕씫泥?
            'phone'             => ['col'=>'c.phone','type'=>'like'],
            'fax'               => ['col'=>'c.fax','type'=>'like'],
            'email'             => ['col'=>'c.email','type'=>'like'],
            'homepage'          => ['col'=>'c.homepage','type'=>'like'],

            // 二쇱냼
            'address'           => ['col'=>'c.address','type'=>'like'],
            'address_detail'    => ['col'=>'c.address_detail','type'=>'like'],

            // 遺꾨쪟
            'client_type'       => ['col'=>'c.client_type','type'=>'like'],
            'client_category'   => ['col'=>'c.client_category','type'=>'like'],
            'trade_category'    => ['col'=>'c.trade_category','type'=>'like'],
            'tax_type'          => ['col'=>'c.tax_type','type'=>'like'],
            'payment_term'      => ['col'=>'c.payment_term','type'=>'like'],
            'item_category'     => ['col'=>'c.item_category','type'=>'like'],

            // 계좌
            'bank_name'         => ['col'=>'c.bank_name','type'=>'like'],
            'account_number'    => ['col'=>'c.account_number','type'=>'like'],
            'account_holder'    => ['col'=>'c.account_holder','type'=>'like'],

            // ?뚯씪
            'bank_file'         => ['col'=>'c.bank_file','type'=>'like'],
            'business_certificate' => ['col'=>'c.business_certificate','type'=>'like'],

            // 湲고?
            'note'              => ['col'=>'c.note','type'=>'like'],
            'memo'              => ['col'=>'c.memo','type'=>'like'],

            // ?곹깭
            'is_active'         => ['col'=>'c.is_active','type'=>'exact'],

            // ?좎쭨
            'registration_date' => ['col'=>'c.registration_date','type'=>'date'],
            'created_at'        => ['col'=>'c.created_at','type'=>'date'],
            'updated_at'        => ['col'=>'c.updated_at','type'=>'date'],
        ];

        $globalSearch = [];

        /* =========================================================
         * ?뵦 ?꾪꽣 泥섎━
         * ========================================================= */
        foreach ($filters as $f) {

            $field = $f['field'] ?? '';
            $value = $f['value'] ?? '';

            if ($value === '' || $value === null) continue;

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
         * ? ?체??(紐⑤뱺 ?띿뒪??而щ읆)
         * ========================================================= */
        if (!empty($globalSearch)) {

            $searchCols = [

                'c.client_name','c.company_name',
                'c.business_number','c.rrn',
                'c.ceo_name','c.manager_name',
                'c.phone','c.email',
                'c.address','c.address_detail',
                'c.business_type','c.business_category',
                'c.bank_name','c.account_number',
                'c.account_holder',
                'c.note','c.memo'
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

        $sql .= " ORDER BY c.sort_no DESC, c.registration_date DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /* -------------------------------------------------------------
     * 嫄곕옒泥??⑥씪 議고쉶 (sort_no 湲곗?)
     * ------------------------------------------------------------- */
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

            FROM system_clients c

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

        $row = $stmt->fetch(PDO::FETCH_ASSOC);


        return $row ?: null;
    }

    public function findIdByBusinessNumber(string $businessNumber): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', $businessNumber);

        if ($digits === '') {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT id
            FROM system_clients
            WHERE business_number = :business_number
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            'business_number' => $digits,
        ]);

        $id = $stmt->fetchColumn();

        return $id !== false ? (string)$id : null;
    }


/* =========================================================
 * 嫄곕옒泥?寃??(Model - RAW ?곗씠?곕쭔 諛섑솚)
 * ========================================================= */
public function searchPicker(string $keyword = '', int $limit = 20, array $options = []): array
{
    $limit = max(1, min(100, (int)$limit));

    $keyword = trim($keyword);
    $like = '%' . $keyword . '%';
    $prefix = $keyword . '%';

    $sql = "
        SELECT
            c.id,
            c.sort_no,
            c.client_name,
            c.business_number,
            c.company_name,
            c.ceo_name,
            c.phone,
            c.email,
            c.client_type,
            c.is_active

        FROM system_clients c

        WHERE c.deleted_at IS NULL
    ";

    $params = [
        ':k1' => $like,
        ':k2' => $like,
        ':k3' => $like,
        ':k4' => $like,
        ':k5' => $like,
        ':prefix' => $prefix,
    ];

    if (!empty($options['client_type'])) {
        $sql .= " AND c.client_type = :client_type";
        $params[':client_type'] = trim((string)$options['client_type']);
    }

    if (array_key_exists('is_active', $options) && $options['is_active'] !== '' && $options['is_active'] !== null) {
        $sql .= " AND c.is_active = :is_active";
        $params[':is_active'] = (int)$options['is_active'];
    }

    $sql .= "
        AND (
            c.client_name LIKE :k1
            OR c.company_name LIKE :k2
            OR c.business_number LIKE :k3
            OR c.ceo_name LIKE :k4
            OR c.phone LIKE :k5
        )

        ORDER BY
            CASE
                WHEN c.client_name LIKE :prefix THEN 0
                ELSE 1
            END,
            c.client_name ASC

        LIMIT {$limit}
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}


    /* -------------------------------------------------------------
     * 嫄곕옒泥??앹꽦
     * ------------------------------------------------------------- */
    public function create(array $data): bool
    {
        $sql = "
        INSERT INTO system_clients (
            id, sort_no, client_name, company_name, registration_date,
            business_number, rrn, rrn_image,
            business_type, business_category,
            business_status, business_certificate,
            address, address_detail, phone, fax, email,
            ceo_name, ceo_phone,
            manager_name, manager_phone,
            homepage,
            bank_name,
            account_number,
            account_holder,
            bank_file,
            trade_category,
            item_category,
            client_category, client_type, tax_type, payment_term, client_grade,
            note, memo, is_active,
            created_by, updated_by
        ) VALUES (
            :id, :sort_no, :client_name, :company_name, :registration_date,
            :business_number, :rrn, :rrn_image,
            :business_type, :business_category,
            :business_status, :business_certificate,
            :address, :address_detail, :phone, :fax, :email,
            :ceo_name, :ceo_phone,
            :manager_name, :manager_phone,
            :homepage,
            :bank_name,
            :account_number,
            :account_holder,
            :bank_file,
            :trade_category,
            :item_category,
            :client_category, :client_type, :tax_type, :payment_term, :client_grade,
            :note, :memo, :is_active,
            :created_by, :updated_by
        )";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $data['id'],
            'sort_no' => $data['sort_no'] ?? null,
            'client_name' => $data['client_name'] ?? '',
            'company_name' => $data['company_name'] ?? null,
            'registration_date' => $data['registration_date'] ?? date('Y-m-d'),

            'business_number' => $data['business_number'] ?? null,
            'rrn' => $data['rrn'] ?? null,
            'rrn_image' => $data['rrn_image'] ?? null,

            'business_type' => $data['business_type'] ?? null,
            'business_category' => $data['business_category'] ?? null,

            'business_status' => $data['business_status'] ?? null,
            'business_certificate' => $data['business_certificate'] ?? null,

            'address' => $data['address'] ?? null,
            'address_detail' => $data['address_detail'] ?? null,

            'phone' => $data['phone'] ?? null,
            'fax' => $data['fax'] ?? null,
            'email' => $data['email'] ?? null,

            'ceo_name' => $data['ceo_name'] ?? null,
            'ceo_phone' => $data['ceo_phone'] ?? null,

            'manager_name' => $data['manager_name'] ?? null,
            'manager_phone' => $data['manager_phone'] ?? null,

            'homepage' => $data['homepage'] ?? null,

            'bank_name' => $data['bank_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'account_holder' => $data['account_holder'] ?? null,
            'bank_file' => $data['bank_file'] ?? null,

            'trade_category' => $data['trade_category'] ?? null,

            'item_category' => $data['item_category'] ?? null,

            'client_category' => $data['client_category'] ?? null,
            'client_type' => $data['client_type'] ?? null,
            'tax_type' => $data['tax_type'] ?? null,
            'payment_term' => $data['payment_term'] ?? null,
            'client_grade' => $data['client_grade'] ?? null,

            'note' => $data['note'] ?? null,
            'memo' => $data['memo'] ?? null,
            'is_active' => (int)($data['is_active'] ?? 1),

            'created_by' => $data['created_by'],
            'updated_by' => $data['updated_by'] ?? $data['created_by']
        ]);
    }

    /* -------------------------------------------------------------
     * 嫄곕옒泥??섏젙 (id 湲곗?)
     * ------------------------------------------------------------- */
    public function updateById(string $id, array $data): bool
    {
        $sql = "
            UPDATE system_clients SET
                client_name = :client_name,
                company_name = :company_name,
                registration_date = :registration_date,

                business_number = :business_number,
                rrn = :rrn, rrn_image = :rrn_image,

                business_type = :business_type,
                business_category = :business_category,
                business_status = :business_status,
                business_certificate = :business_certificate,

                ceo_name = :ceo_name,
                ceo_phone = :ceo_phone,

                manager_name = :manager_name,
                manager_phone = :manager_phone,

                phone = :phone,
                fax = :fax,
                email = :email,

                address = :address,
                address_detail = :address_detail,

                homepage = :homepage,

                client_category = :client_category,

                bank_name = :bank_name,
                account_number = :account_number,
                account_holder = :account_holder,
                bank_file = :bank_file,

                trade_category = :trade_category,

                client_type = :client_type,
                tax_type = :tax_type,
                payment_term = :payment_term,

                item_category = :item_category,
                client_grade = :client_grade,

                note = :note,
                memo = :memo,
                is_active = :is_active,

                updated_by = :updated_by

            WHERE id = :id
        ";

        $params = [
            'id' => $id,
            'client_name' => trim((string)($data['client_name'] ?? '')),
            'company_name' => $data['company_name'] ?? null,
            'registration_date' => $data['registration_date'] ?? date('Y-m-d'),

            'business_number' => $data['business_number'] ?? null,
            'rrn' => $data['rrn'] ?? null,
            'rrn_image' => $data['rrn_image'] ?? null,

            'business_type' => $data['business_type'] ?? null,
            'business_category' => $data['business_category'] ?? null,
            'business_status' => $data['business_status'] ?? null,
            'business_certificate' => $data['business_certificate'] ?? null,

            'ceo_name' => $data['ceo_name'] ?? null,
            'ceo_phone' => $data['ceo_phone'] ?? null,

            'manager_name' => $data['manager_name'] ?? null,
            'manager_phone' => $data['manager_phone'] ?? null,

            'phone' => $data['phone'] ?? null,
            'fax' => $data['fax'] ?? null,
            'email' => $data['email'] ?? null,

            'address' => $data['address'] ?? null,
            'address_detail' => $data['address_detail'] ?? null,

            'homepage' => $data['homepage'] ?? null,

            'client_category' => $data['client_category'] ?? null,

            'bank_name' => $data['bank_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'account_holder' => $data['account_holder'] ?? null,
            'bank_file' => $data['bank_file'] ?? null,

            'trade_category' => $data['trade_category'] ?? null,

            'client_type' => $data['client_type'] ?? null,
            'tax_type' => $data['tax_type'] ?? null,
            'payment_term' => $data['payment_term'] ?? null,

            'item_category' => $data['item_category'] ?? null,

            'client_grade' => $data['client_grade'] ?? null,

            'note' => $data['note'] ?? null,
            'memo' => $data['memo'] ?? null,
            'is_active' => (int)($data['is_active'] ?? 1),

            'updated_by' => $data['updated_by']
        ];

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /* -------------------------------------------------------------
    * 嫄곕옒泥???젣 (id 湲곗?)
    * ------------------------------------------------------------- */
    public function deleteById(string $id, string $actor): bool
    {
        $sql = "
            UPDATE system_clients
            SET
                is_active = 0,
                deleted_at = NOW(),
                deleted_by = :actor,
                updated_by = :actor
            WHERE id = :id
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':id'    => $id,
            ':actor' => $actor
        ]);

        return $stmt->rowCount() > 0;
    }


    /* -------------------------------------------------------------
    * 嫄곕옒泥??댁???紐⑸줉
    * ------------------------------------------------------------- */
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

            FROM system_clients c

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

    /* -------------------------------------------------------------
    * 嫄곕옒泥?蹂듭썝 (id 湲곗?)
    * ------------------------------------------------------------- */
    public function restoreById(string $id, string $actor): bool
    {
        $sql = "
            UPDATE system_clients
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

    /* -------------------------------------------------------------
    * 嫄곕옒泥??곴뎄??젣
    * ------------------------------------------------------------- */
    public function hardDeleteById(string $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM system_clients
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id' => $id
        ]);
    }





    /* -------------------------------------------------------------
    * ID 湲곗? sort_no ?섏젙
    * ------------------------------------------------------------- */
    public function updateSortNo(string $id, string $newSortNo): bool
    {
        $sql = "UPDATE system_clients SET sort_no = :newSortNo WHERE id = :id";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'newSortNo' => (int)$newSortNo,
            'id' => $id
        ]);
    }



}
