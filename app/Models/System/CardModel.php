<?php
// 경로: PROJECT_ROOT . '/app/Models/System/CardModel.php'

namespace App\Models\System;

use PDO;
use Core\Database;

class CardModel
{
    // PDO 보관
    private PDO $db;

    // 생성자 – 외부에서 PDO 주입 또는 자동 연결
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    /* =========================================================
    * 카드 전체 목록
    * ========================================================= */
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
    
        /* =========================================================
         * 🔥 전체 컬럼 맵
         * ========================================================= */
        $fieldMap = [
    
            // 기본
            'code'            => ['col'=>'c.code','type'=>'exact'],
            'card_name'       => ['col'=>'c.card_name','type'=>'like'],
            'card_number'     => ['col'=>'c.card_number','type'=>'like'],
            'card_type'       => ['col'=>'c.card_type','type'=>'like'],
    
            // 관계
            'client_id'       => ['col'=>'c.client_id','type'=>'exact'],
            'account_id'      => ['col'=>'c.account_id','type'=>'exact'],
            'client_name'     => ['col'=>'cl.client_name','type'=>'like'],
            'account_name'    => ['col'=>'b.account_name','type'=>'like'],
    
            // 유효기간
            'expiry_year'     => ['col'=>'c.expiry_year','type'=>'exact'],
            'expiry_month'    => ['col'=>'c.expiry_month','type'=>'exact'],
    
            // 금액
            'limit_amount'    => ['col'=>'c.limit_amount','type'=>'exact'],
    
            // 기타
            'currency'        => ['col'=>'c.currency','type'=>'like'],
            'card_file'       => ['col'=>'c.card_file','type'=>'like'],
            'note'            => ['col'=>'c.note','type'=>'like'],
            'memo'            => ['col'=>'c.memo','type'=>'like'],
    
            // 상태
            'is_active'       => ['col'=>'c.is_active','type'=>'exact'],
    
            // 날짜
            'created_at'      => ['col'=>'c.created_at','type'=>'date'],
            'updated_at'      => ['col'=>'c.updated_at','type'=>'date'],
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
         * 🔥 전체검색 (텍스트 컬럼 + 조인)
         * ========================================================= */
        if (!empty($globalSearch)) {
    
            $searchCols = [
    
                'c.card_name',
                'c.card_number',
                'c.card_type',
                'c.currency',
                'c.note',
                'c.memo',
    
                'cl.client_name',
                'b.account_name'
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
    
        $sql .= " ORDER BY c.code ASC";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /* =========================================================
    * 카드 단일 조회 (id 기준)
    * ========================================================= */
    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.*,
                cl.client_name,
                b.account_name,

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

            FROM system_cards c

            LEFT JOIN system_clients cl ON c.client_id = cl.id
            LEFT JOIN system_bank_accounts b ON c.account_id = b.id

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
    * 카드 검색 (Model - RAW 데이터 반환)
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
    /* =========================================================
    * 카드 생성
    * ========================================================= */
    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO system_cards (
                id,
                code,
                card_name,
                card_number,
                card_type,
                client_id,
                account_id,
                expiry_year,
                expiry_month,
                currency,
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
                :code,
                :card_name,
                :card_number,
                :card_type,
                :client_id,
                :account_id,
                :expiry_year,
                :expiry_month,
                :currency,
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
            'code' => $data['code'] ?? null,

            'card_name' => trim((string)($data['card_name'] ?? '')),
            'card_number' => trim((string)($data['card_number'] ?? '')),
            'card_type' => $data['card_type'] ?? 'corporate',

            'client_id' => $data['client_id'] ?? null,
            'account_id' => $data['account_id'] ?? null,

            'expiry_year' => $data['expiry_year'] ?? null,
            'expiry_month' => $data['expiry_month'] ?? null,

            'currency' => $data['currency'] ?? 'KRW',
            'limit_amount' => $data['limit_amount'] ?? 0,

            'card_file' => $data['card_file'] ?? null,

            'note' => $data['note'] ?? null,
            'memo' => $data['memo'] ?? null,

            'is_active' => $data['is_active'] ?? 1,

            'created_by' => $data['created_by'],
            'updated_by' => $data['updated_by'],
        ]);
    }
    /* =========================================================
    * 카드 수정 (id 기준)
    * ========================================================= */
    public function updateById(string $id, array $data): bool
    {
        $sql = "
            UPDATE system_cards SET
                card_name = :card_name,
                card_number = :card_number,
                card_type = :card_type,
                client_id = :client_id,
                account_id = :account_id,
                expiry_year = :expiry_year,
                expiry_month = :expiry_month,
                currency = :currency,
                limit_amount = :limit_amount,
                card_file = :card_file,
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

            'card_name' => trim((string)($data['card_name'] ?? '')),
            'card_number' => trim((string)($data['card_number'] ?? '')),
            'card_type' => $data['card_type'] ?? 'corporate',

            'client_id' => $data['client_id'] ?? null,
            'account_id' => $data['account_id'] ?? null,

            'expiry_year' => $data['expiry_year'] ?? null,
            'expiry_month' => $data['expiry_month'] ?? null,

            'currency' => $data['currency'] ?? 'KRW',
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
    /* -------------------------------------------------------------
    * 카드 삭제 (id 기준)
    * ------------------------------------------------------------- */
    public function deleteById(string $id, string $actor): bool
    {
        $sql = "
            UPDATE system_cards
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
    * 카드 휴지통 목록
    * ------------------------------------------------------------- */
    public function getDeleted(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.*,
                cl.client_name,
                b.account_name,

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

            FROM system_cards c

            LEFT JOIN system_clients cl ON c.client_id = cl.id
            LEFT JOIN system_bank_accounts b ON c.account_id = b.id

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
    * 카드 복원 (id 기준)
    * ------------------------------------------------------------- */
    public function restoreById(string $id, string $actor): bool
    {
        $sql = "
            UPDATE system_cards
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
    * 카드 영구삭제 (파일 포함)
    * ------------------------------------------------------------- */
    public function hardDeleteById(string $id): bool
    {
        // 1. 파일 경로 조회
        $stmt = $this->db->prepare("
            SELECT card_file
            FROM system_cards
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2. DB 삭제
        $stmt = $this->db->prepare("
            DELETE FROM system_cards
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);

        // 3. 파일 삭제
        if ($row && !empty($row['card_file'])) {

            $filePath = PROJECT_ROOT . '/public/uploads/' . str_replace('public://', '', $row['card_file']);

            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        return true;
    }

    /* -------------------------------------------------------------
    * 카드 순서 변경 (충돌 방지)
    * ------------------------------------------------------------- */
    public function updateCode(string $id, string $newCode): bool
    {
        $sql = "UPDATE system_cards SET code = :newCode WHERE id = :id";
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