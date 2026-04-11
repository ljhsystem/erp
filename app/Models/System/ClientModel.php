<?php
// 경로: PROJECT_ROOT . '/app/Models/System/ClientModel.php'
namespace App\Models\System;

use PDO;
use Core\Database;

class ClientModel
{
    // PDO 보관
    private PDO $db;

    // 생성자 – 외부에서 PDO 주입 또는 자동 연결
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    /* -------------------------------------------------------------
     * 거래처 전체 목록
     * ------------------------------------------------------------- */
    public function getList(array $filters = []): array
    {
        $sql = "
            SELECT c.*
            FROM system_clients c
            WHERE c.deleted_at IS NULL
        ";
    
        $params = [];
    
        /* =========================================================
         * 🔥 전체 컬럼 맵 (전부 포함)
         * ========================================================= */
        $fieldMap = [
    
            // 기본
            'code'              => ['col'=>'c.code','type'=>'exact'],
            'client_name'       => ['col'=>'c.client_name','type'=>'like'],
            'company_name'      => ['col'=>'c.company_name','type'=>'like'],
    
            // 사업자
            'business_number'   => ['col'=>'c.business_number','type'=>'like'],
            'rrn'               => ['col'=>'c.rrn','type'=>'like'],
            'business_type'     => ['col'=>'c.business_type','type'=>'like'],
            'business_category' => ['col'=>'c.business_category','type'=>'like'],
            'business_status'   => ['col'=>'c.business_status','type'=>'like'],
    
            // 인물
            'ceo_name'          => ['col'=>'c.ceo_name','type'=>'like'],
            'ceo_phone'         => ['col'=>'c.ceo_phone','type'=>'like'],
            'manager_name'      => ['col'=>'c.manager_name','type'=>'like'],
            'manager_phone'     => ['col'=>'c.manager_phone','type'=>'like'],
    
            // 연락처
            'phone'             => ['col'=>'c.phone','type'=>'like'],
            'fax'               => ['col'=>'c.fax','type'=>'like'],
            'email'             => ['col'=>'c.email','type'=>'like'],
            'homepage'          => ['col'=>'c.homepage','type'=>'like'],
    
            // 주소
            'address'           => ['col'=>'c.address','type'=>'like'],
            'address_detail'    => ['col'=>'c.address_detail','type'=>'like'],
    
            // 분류
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
    
            // 파일
            'bank_file'         => ['col'=>'c.bank_file','type'=>'like'],
            'business_certificate' => ['col'=>'c.business_certificate','type'=>'like'],
    
            // 기타
            'note'              => ['col'=>'c.note','type'=>'like'],
            'memo'              => ['col'=>'c.memo','type'=>'like'],
    
            // 상태
            'is_active'         => ['col'=>'c.is_active','type'=>'exact'],
    
            // 날짜
            'registration_date' => ['col'=>'c.registration_date','type'=>'date'],
            'created_at'        => ['col'=>'c.created_at','type'=>'date'],
            'updated_at'        => ['col'=>'c.updated_at','type'=>'date'],
        ];
    
        $globalSearch = [];
    
        /* =========================================================
         * 🔥 필터 처리
         * ========================================================= */
        foreach ($filters as $f) {
    
            $field = $f['field'] ?? '';
            $value = $f['value'] ?? '';
    
            if ($value === '' || $value === null) continue;
    
            // 🔥 전체검색
            if ($field === '') {
                $globalSearch[] = $value;
                continue;
            }
    
            if (!isset($fieldMap[$field])) continue;
    
            $col  = $fieldMap[$field]['col'];
            $type = $fieldMap[$field]['type'];
    
            // 날짜
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
         * 🔥 전체검색 (모든 텍스트 컬럼)
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
    
        $sql .= " ORDER BY c.registration_date DESC, c.code ASC";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /* -------------------------------------------------------------
     * 거래처 단일 조회 (code 기준)
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


/* =========================================================
 * 거래처 검색 (Model - RAW 데이터만 반환)
 * ========================================================= */
public function searchPicker(string $keyword = '', int $limit = 20): array
{
    $limit = max(1, min(100, (int)$limit));

    $keyword = trim($keyword);
    $like = '%' . $keyword . '%';
    $prefix = $keyword . '%';

    $sql = "
        SELECT
            c.id,
            c.code,
            c.client_name,
            c.business_number,
            c.company_name,
            c.ceo_name,
            c.phone,
            c.email

        FROM system_clients c

        WHERE c.deleted_at IS NULL
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

    $stmt->bindValue(':k1', $like, PDO::PARAM_STR);
    $stmt->bindValue(':k2', $like, PDO::PARAM_STR);
    $stmt->bindValue(':k3', $like, PDO::PARAM_STR);
    $stmt->bindValue(':k4', $like, PDO::PARAM_STR);
    $stmt->bindValue(':k5', $like, PDO::PARAM_STR);
    $stmt->bindValue(':prefix', $prefix, PDO::PARAM_STR);

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}


    /* -------------------------------------------------------------
     * 거래처 생성
     * ------------------------------------------------------------- */
    public function create(array $data): bool
    {
        $sql = "
        INSERT INTO system_clients (
            id, code, client_name, company_name, registration_date,
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
            note, memo,
            created_by, updated_by
        ) VALUES (
            :id, :code, :client_name, :company_name, :registration_date,
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
            :note, :memo,
            :created_by, :updated_by
        )";

        $stmt = $this->db->prepare($sql);
        if (empty($data['created_by'])) {
            throw new \Exception('created_by 없음');
        }
        return $stmt->execute([
            'id' => $data['id'],
            'code' => $data['code'] ?? null,
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

            'created_by' => $data['created_by'],
            'updated_by' => $data['updated_by'] ?? $data['created_by']
        ]);
    }

    /* -------------------------------------------------------------
     * 거래처 수정 (id 기준)
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

                updated_by = :updated_by
    
            WHERE id = :id
        ";

        if (empty($data['updated_by'])) {
            throw new \Exception('updated_by 없음');
        }

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

            'updated_by' => $data['updated_by']
        ];

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /* -------------------------------------------------------------
    * 거래처 삭제 (id 기준)
    * ------------------------------------------------------------- */
    public function deleteById(string $id, string $actor): bool
    {
        $sql = "
            UPDATE system_clients
            SET 
                deleted_at = NOW(),
                deleted_by = :actor
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
    * 거래처 휴지통 목록
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
    * 거래처 복원 (id 기준)
    * ------------------------------------------------------------- */
    public function restoreById(string $id, string $actor): bool
    {
        $sql = "
            UPDATE system_clients
            SET                 
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
    * 거래처 영구삭제
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
    * ID 기준 code 수정
    * ------------------------------------------------------------- */
    public function updateCode(string $id, string $newCode): bool
    {
        $sql = "UPDATE system_clients SET code = :newCode WHERE id = :id";
        $stmt = $this->db->prepare($sql);

        $ok = $stmt->execute([
            'newCode' => (int)$newCode,
            'id' => $id
        ]);

        if (!$ok) {
            throw new \Exception('쿼리 실행 실패');
        }

        return true;
    }



}
