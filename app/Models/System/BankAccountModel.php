<?php
// 경로: PROJECT_ROOT . '/app/Models/System/BankAccountModel.php'

namespace App\Models\System;

use PDO;
use Core\Database;

class BankAccountModel
{
    // PDO 보관
    private PDO $db;

    // 생성자 – 외부에서 PDO 주입 또는 자동 연결
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    /* =========================================================
    * 계좌 전체 목록
    * ========================================================= */
    public function getList(array $filters = []): array
    {
        $sql = "
            SELECT
                a.*
            FROM system_bank_accounts a
            WHERE a.deleted_at IS NULL
        ";
    
        $params = [];
    
        /* =========================================================
         * 🔥 전체 컬럼 맵 (빠짐없이)
         * ========================================================= */
        $fieldMap = [
    
            // 기본
            'code'            => ['col'=>'a.code','type'=>'exact'],
            'account_name'    => ['col'=>'a.account_name','type'=>'like'],
            'bank_name'       => ['col'=>'a.bank_name','type'=>'like'],
            'account_number'  => ['col'=>'a.account_number','type'=>'like'],
            'account_holder'  => ['col'=>'a.account_holder','type'=>'like'],
            'account_type'    => ['col'=>'a.account_type','type'=>'like'],
            'currency'        => ['col'=>'a.currency','type'=>'like'],
    
            // 파일
            'bank_file'       => ['col'=>'a.bank_file','type'=>'like'],
    
            // 메모
            'note'            => ['col'=>'a.note','type'=>'like'],
            'memo'            => ['col'=>'a.memo','type'=>'like'],
    
            // 상태
            'is_active'       => ['col'=>'a.is_active','type'=>'exact'],
    
            // 날짜
            'created_at'      => ['col'=>'a.created_at','type'=>'date'],
            'updated_at'      => ['col'=>'a.updated_at','type'=>'date'],
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
    
                'a.account_name',
                'a.bank_name',
                'a.account_number',
                'a.account_holder',
                'a.account_type',
                'a.currency',
                'a.note',
                'a.memo'
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
    
        $sql .= " ORDER BY a.code ASC";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =========================================================
    * 계좌 단일 조회 (id 기준)
    * ========================================================= */
    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                a.*,

                CASE 
                    WHEN a.created_by LIKE 'SYSTEM:%' THEN a.created_by
                    WHEN p1.employee_name IS NOT NULL THEN CONCAT('USER:', p1.employee_name)
                    ELSE a.created_by
                END AS created_by_name,

                CASE 
                    WHEN a.updated_by LIKE 'SYSTEM:%' THEN a.updated_by
                    WHEN p2.employee_name IS NOT NULL THEN CONCAT('USER:', p2.employee_name)
                    ELSE a.updated_by
                END AS updated_by_name,

                CASE 
                    WHEN a.deleted_by LIKE 'SYSTEM:%' THEN a.deleted_by
                    WHEN p3.employee_name IS NOT NULL THEN CONCAT('USER:', p3.employee_name)
                    ELSE a.deleted_by
                END AS deleted_by_name

            FROM system_bank_accounts a

            LEFT JOIN user_employees p1
                ON a.created_by NOT LIKE 'SYSTEM:%'
                AND p1.user_id = REPLACE(a.created_by, 'USER:', '')

            LEFT JOIN user_employees p2
                ON a.updated_by NOT LIKE 'SYSTEM:%'
                AND p2.user_id = REPLACE(a.updated_by, 'USER:', '')

            LEFT JOIN user_employees p3
                ON a.deleted_by NOT LIKE 'SYSTEM:%'
                AND p3.user_id = REPLACE(a.deleted_by, 'USER:', '')

            WHERE a.id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /* =========================================================
    * 계좌 검색 (Model - RAW 데이터 반환)
    * ========================================================= */
    public function searchPicker(string $keyword = '', int $limit = 20): array
    {
        $limit = max(1, min(100, (int)$limit));

        $keyword = trim($keyword);
        $like = '%' . $keyword . '%';
        $prefix = $keyword . '%';

        $sql = "
            SELECT 
                id,
                code,
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

    /* =========================================================
    * 생성
    * ========================================================= */
    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO system_bank_accounts (
                id,
                code,
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
                :code,
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
            'code' => $data['code'] ?? null,        
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
    /* =========================================================
    * 계좌 수정 (id 기준)
    * ========================================================= */
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

        if (empty($data['updated_by'])) {
            throw new \Exception('updated_by 없음');
        }

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
    /* -------------------------------------------------------------
    * 계좌 삭제 (id 기준)
    * ------------------------------------------------------------- */
    public function deleteById(string $id, string $actor): bool
    {
        $sql = "
            UPDATE system_bank_accounts
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
    * 계좌 휴지통 목록
    * ------------------------------------------------------------- */
    public function getDeleted(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                a.*,

                CASE 
                    WHEN a.created_by LIKE 'SYSTEM:%' THEN a.created_by
                    WHEN p1.employee_name IS NOT NULL THEN CONCAT('USER:', p1.employee_name)
                    ELSE a.created_by
                END AS created_by_name,

                CASE 
                    WHEN a.updated_by LIKE 'SYSTEM:%' THEN a.updated_by
                    WHEN p2.employee_name IS NOT NULL THEN CONCAT('USER:', p2.employee_name)
                    ELSE a.updated_by
                END AS updated_by_name,

                CASE 
                    WHEN a.deleted_by LIKE 'SYSTEM:%' THEN a.deleted_by
                    WHEN p3.employee_name IS NOT NULL THEN CONCAT('USER:', p3.employee_name)
                    ELSE a.deleted_by
                END AS deleted_by_name

            FROM system_bank_accounts a

            LEFT JOIN user_employees p1
                ON a.created_by NOT LIKE 'SYSTEM:%'
                AND p1.user_id = REPLACE(a.created_by, 'USER:', '')

            LEFT JOIN user_employees p2
                ON a.updated_by NOT LIKE 'SYSTEM:%'
                AND p2.user_id = REPLACE(a.updated_by, 'USER:', '')

            LEFT JOIN user_employees p3
                ON a.deleted_by NOT LIKE 'SYSTEM:%'
                AND p3.user_id = REPLACE(a.deleted_by, 'USER:', '')

            WHERE a.deleted_at IS NOT NULL
            ORDER BY a.deleted_at DESC
        ");

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* -------------------------------------------------------------
    * 계좌 복원 (id 기준)
    * ------------------------------------------------------------- */
    public function restoreById(string $id, string $actor): bool
    {
        $sql = "
            UPDATE system_bank_accounts
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
    * 계좌 영구삭제 (id 기준)
    * ------------------------------------------------------------- */
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

    /* -------------------------------------------------------------
    * 계좌 순서 변경 (충돌 방지)
    * ------------------------------------------------------------- */

    public function updateCode(string $id, string $newCode): bool
    {
        $sql = "UPDATE system_bank_accounts SET code = :newCode WHERE id = :id";
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