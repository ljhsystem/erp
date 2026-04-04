<?php
// 경로: PROJECT_ROOT . '/app/Models/System/ClientModel.php'
namespace App\Models\System;

use PDO;

class ClientModel
{
    // 2. PDO 보관
    private PDO $db;

    // 3. 생성자 – 외부에서 PDO 주입 또는 자동 연결
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?: \Core\Database::getInstance()->getConnection();
    }

    /* -------------------------------------------------------------
     * 7. 거래처 전체 목록
     * ------------------------------------------------------------- */
    public function getList(): array
    {
        $stmt = $this->db->query("
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
            ORDER BY c.code ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }



    /* -------------------------------------------------------------
     * 8. 거래처 단일 조회 (code 기준)
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

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* -------------------------------------------------------------
     * 거래처 검색 (필터 기능 포함)
     * ------------------------------------------------------------- */
    public function search(array $filters = []): array
    {
        $sql = "SELECT * FROM system_clients WHERE deleted_at IS NULL";
        $params = [];

        $allowed = [
            'code',
            'client_name',
            'company_name',
            'ceo_name',
            'business_number',
            'business_status',
            'phone',
            'email',
            'registration_date',
            'note',
            'memo',
            'address',
            'address_detail',
            'client_type',
            'tax_type',
            'client_category',

            // 🔥 추가
            'created_at',
            'updated_at'
        ];

        $likeFields = [
            'client_name',
            'company_name',
            'ceo_name',
            'business_status',
            'email',
            'note',
            'address',
            'address_detail'
        ];

        $supportedDateFields = [
            'registration_date',
            'created_at',
            'updated_at'
        ];
        $unsupportedDateFields = ['deal_date', 'occur_date', 'issue_date'];

        foreach ($filters as $f) {
            $field = $f['field'] ?? '';
            $value = $f['value'] ?? '';

            if (in_array($field, $unsupportedDateFields, true)) {
                return [];
            }

            if (!in_array($field, $allowed, true)) continue;

            if ($value === '' || $value === null) continue;

            if (in_array($field, $supportedDateFields, true)) {

                // 기간 검색
                if (is_array($value) && isset($value['start'], $value['end'])) {
                    $sql .= " AND $field BETWEEN ? AND ?";
                    $params[] = $value['start'];
                    $params[] = $value['end'];
                    continue;
                }

                // 🔥 단일 날짜 검색 추가
                $sql .= " AND $field = ?";
                $params[] = $value;
                continue;
            }

            if (in_array($field, $likeFields, true)) {
                $sql .= " AND {$field} LIKE ?";
                $params[] = "%{$value}%";
                continue;
            }

            $sql .= " AND {$field} = ?";
            $params[] = $value;
        }

        $sql .= " ORDER BY registration_date DESC, code ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function searchPicker(string $keyword): array
    {
        $stmt = $this->db->prepare("
            SELECT id, code, client_name, business_number
            FROM system_clients
            WHERE deleted_at IS NULL
            AND client_name LIKE ?
            ORDER BY client_name
            LIMIT 20
        ");

        $stmt->execute(["%{$keyword}%"]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /* -------------------------------------------------------------
     * 4. 거래처 생성
     * ------------------------------------------------------------- */
    public function create(array $data): bool
    {
        $sql = "
        INSERT INTO system_clients (
            id, code, client_name, company_name, registration_date,
            business_number, corporation_number,
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
            :business_number, :corporation_number,
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
            'corporation_number' => $data['corporation_number'] ?? null,

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

    public function updateById(string $id, array $data): bool
    {
        $sql = "
            UPDATE system_clients SET
                client_name = :client_name,
                company_name = :company_name,
                registration_date = :registration_date,
    
                business_number = :business_number,
                corporation_number = :corporation_number,
    
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
            'corporation_number' => $data['corporation_number'] ?? null,

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
    * 6. 거래처 삭제 (id 기준)
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
    * 선택 복원
    * ------------------------------------------------------------- */
    public function restoreBulkByIds(array $ids, string $actor): bool
    {
        if (empty($ids)) return false;

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "
            UPDATE system_clients
            SET
                deleted_at = NULL,
                deleted_by = NULL,
                updated_by = ?
            WHERE id IN ($placeholders)
        ";

        $stmt = $this->db->prepare($sql);

        $params = array_merge([$actor], $ids);

        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /* -------------------------------------------------------------
    * 선택 완전삭제
    * ------------------------------------------------------------- */
    public function hardDeleteBulkByIds(array $ids): bool
    {
        if (empty($ids)) return false;

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "
            DELETE FROM system_clients
            WHERE id IN ($placeholders)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($ids);

        return $stmt->rowCount() > 0;
    }

    /* -------------------------------------------------------------
    * 전체 완전삭제
    * ------------------------------------------------------------- */
    public function hardDeleteAllDeleted(): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM system_clients
            WHERE deleted_at IS NOT NULL
        ");

        $stmt->execute();

        return true;
    }

    /* -------------------------------------------------------------
     * 9. ID 기준 코드 변경
     * ------------------------------------------------------------- */
    public function updateCode(string $id, string $newCode): bool
    {
        $sql = "UPDATE system_clients SET code = :newCode WHERE id = :id";
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


    /* -------------------------------------------------------------
    * 11. 사업자번호 기준 Upsert (엑셀 업로드용)
    * ------------------------------------------------------------- */
    public function saveFromExcel(array $data): bool
    {
        $sql = "
            INSERT INTO system_clients (
                id, code, client_name, company_name, registration_date,
                business_number, business_status,
                phone, email, ceo_name,client_type,
                tax_type,payment_term,
                note, created_by, updated_by
            ) VALUES (
                :id, :code, :client_name, :company_name, :registration_date,
                :business_number, :business_status,
                :phone, :email, :ceo_name,
                :client_type,
                :tax_type,
                :payment_term,
                :note, :created_by, :updated_by
            )
            ON DUPLICATE KEY UPDATE
                client_name = VALUES(client_name),
                company_name = VALUES(company_name),
                phone = VALUES(phone),
                email = VALUES(email),
                ceo_name = VALUES(ceo_name),
                client_type = VALUES(client_type),
                tax_type = VALUES(tax_type),
                payment_term = VALUES(payment_term),
                business_status = VALUES(business_status),
                note = VALUES(note),
                updated_by = VALUES(updated_by)
        ";

        $stmt = $this->db->prepare($sql);
        if (empty($data['created_by']) || empty($data['updated_by'])) {
            throw new \Exception('actor 없음');
        }
        return $stmt->execute([
            'id' => $data['id'],
            'code' => $data['code'] ?? null,
            'client_name' => $data['client_name'] ?? '',
            'company_name' => $data['company_name'] ?? null,
            'registration_date' => $data['registration_date'] ?? date('Y-m-d'),
            'business_number' => $data['business_number'] ?? null,
            'business_status' => $data['business_status'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'ceo_name' => $data['ceo_name'] ?? null,
            'client_type' => $data['client_type'] ?? null,
            'tax_type' => $data['tax_type'] ?? null,
            'payment_term' => $data['payment_term'] ?? null,
            'note' => $data['note'] ?? null,
            'created_by' => $data['created_by'],
            'updated_by' => $data['updated_by']
        ]);
    }



    public function findByName(string $name): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, client_name
            FROM system_clients
            WHERE client_name = :name
            AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            'name' => trim($name)
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
