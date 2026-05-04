<?php
// 경로: PROJECT_ROOT . '/app/Models/System/BankAccountModel.php'

namespace App\Models\System;

use PDO;
use Core\Database;

class BankAccountModel
{
    // PDO 연결 객체
    private PDO $db;

    // 생성자에서 PDO 주입 또는 기본 연결 사용
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    /* =========================================================
    * 계좌 목록 조회
    * ========================================================= */
    public function getList(array $filters = []): array
    {
        $sql = "
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
            WHERE a.deleted_at IS NULL
        ";

        $params = [];

        /* =========================================================
         * 검색 필드 매핑
         * ========================================================= */
        $fieldMap = [

            // 기본 정보
            'id'              => ['col'=>'a.id','type'=>'exact'],
            'sort_no'         => ['col'=>'a.sort_no','type'=>'exact'],
            'account_name'    => ['col'=>'a.account_name','type'=>'like'],
            'bank_name'       => ['col'=>'a.bank_name','type'=>'like'],
            'account_number'  => ['col'=>'a.account_number','type'=>'like'],
            'account_holder'  => ['col'=>'a.account_holder','type'=>'like'],
            'account_type'    => ['col'=>'a.account_type','type'=>'like'],
            'currency'        => ['col'=>'a.currency','type'=>'like'],

            // 첨부 파일
            'bank_file'       => ['col'=>'a.bank_file','type'=>'like'],

            // 기타
            'note'            => ['col'=>'a.note','type'=>'like'],
            'memo'            => ['col'=>'a.memo','type'=>'like'],

            // 상태
            'is_active'       => ['col'=>'a.is_active','type'=>'exact'],

            // 날짜/감사 정보
            'created_at'      => ['col'=>'a.created_at','type'=>'date'],
            'created_by'      => ['col'=>'a.created_by','type'=>'like'],
            'created_by_name' => ['col'=>"COALESCE(CONCAT('USER:', p1.employee_name), a.created_by)",'type'=>'like'],
            'updated_at'      => ['col'=>'a.updated_at','type'=>'date'],
            'updated_by'      => ['col'=>'a.updated_by','type'=>'like'],
            'updated_by_name' => ['col'=>"COALESCE(CONCAT('USER:', p2.employee_name), a.updated_by)",'type'=>'like'],
            'deleted_at'      => ['col'=>'a.deleted_at','type'=>'date'],
            'deleted_by'      => ['col'=>'a.deleted_by','type'=>'like'],
            'deleted_by_name' => ['col'=>"COALESCE(CONCAT('USER:', p3.employee_name), a.deleted_by)",'type'=>'like'],
        ];

        $globalSearch = [];

        /* =========================================================
         * 필터 처리
         * ========================================================= */
        foreach ($filters as $f) {

            $field = $f['field'] ?? '';
            $value = $f['value'] ?? '';

            if ($value === '' || $value === null) continue;

            // 전체 검색
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
         * 전체 검색(텍스트 컬럼)
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
                'a.memo',
                'a.created_by',
                'a.updated_by',
                'a.deleted_by',
                "COALESCE(CONCAT('USER:', p1.employee_name), a.created_by)",
                "COALESCE(CONCAT('USER:', p2.employee_name), a.updated_by)",
                "COALESCE(CONCAT('USER:', p3.employee_name), a.deleted_by)"
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

    /* =========================================================
    * 계좌 생성
    * ========================================================= */
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
    * ID 기준 sort_no 수정
    * ------------------------------------------------------------- */

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
