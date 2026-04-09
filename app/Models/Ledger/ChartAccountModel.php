<?php
// 경로: PROJECT_ROOT . '/app/Models/Ledger/ChartAccountModel.php'
// 설명:
//  - 회계 계정과목 Model
//  - ledger_accounts 테이블 처리
//  - 계층구조 계정 지원

namespace App\Models\Ledger;

use PDO;

class ChartAccountModel
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?: \Core\Database::getInstance()->getConnection();
    }

    /* =========================================================
     * 전체 계정 조회
     * ========================================================= */

     public function getAll(): array
     {
         $sql = "
                SELECT 
                    a.*,

                    p.account_name AS parent_name,   -- 🔥 핵심

                    CASE 
                        WHEN COUNT(sa.id) > 0 THEN 1
                        ELSE 0
                    END AS has_sub_account

                FROM ledger_accounts a

                LEFT JOIN ledger_accounts p   -- 🔥 자기 자신 JOIN
                    ON a.parent_id = p.id

                LEFT JOIN ledger_sub_accounts sa
                    ON sa.account_id = a.id

                WHERE a.deleted_at IS NULL

                GROUP BY a.id

                ORDER BY CAST(a.code AS UNSIGNED)
                        ";
     
         $stmt = $this->db->query($sql);
     
         return $stmt->fetchAll(PDO::FETCH_ASSOC);
     }

    /* =========================================================
     * 단건 조회
     * ========================================================= */

    public function getById(string $id): ?array
    {
        $sql = "
        SELECT *
        FROM ledger_accounts
        WHERE id = :id
        AND deleted_at IS NULL
        LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':id' => $id
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /* =========================================================
     * 계정코드 조회
     * ========================================================= */

     public function getByAccountCode(string $accountCode): ?array
     {
         $sql = "
             SELECT
                 a.*,
                 p.account_name AS parent_name,
     
                 /* 🔥 이름 변환 */
                 CASE 
                     WHEN a.created_by LIKE 'SYSTEM%' 
                         THEN CONCAT('SYSTEM(', p1.employee_name, ')')
                     WHEN a.created_by LIKE 'USER%' 
                         THEN CONCAT('USER(', p1.employee_name, ')')
                     ELSE a.created_by
                 END AS created_by_name,
     
                 CASE 
                     WHEN a.updated_by LIKE 'SYSTEM%' 
                         THEN CONCAT('SYSTEM(', p2.employee_name, ')')
                     WHEN a.updated_by LIKE 'USER%' 
                         THEN CONCAT('USER(', p2.employee_name, ')')
                     ELSE a.updated_by
                 END AS updated_by_name
     
             FROM ledger_accounts a
     
             LEFT JOIN ledger_accounts p
                 ON a.parent_id = p.id
     
             LEFT JOIN user_employees p1
                 ON p1.user_id = SUBSTRING_INDEX(SUBSTRING_INDEX(a.created_by,'(', -1),')',1)
     
             LEFT JOIN user_employees p2
                 ON p2.user_id = SUBSTRING_INDEX(SUBSTRING_INDEX(a.updated_by,'(', -1),')',1)
     
             WHERE a.account_code = :account_code
             LIMIT 1
         ";
     
         $stmt = $this->db->prepare($sql);
         $stmt->execute([
             ':account_code' => $accountCode
         ]);
     
         return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
     }


     public function getDetailByAccountCode(string $accountCode): ?array
    {
        $sql = "
            SELECT
                a.*,
                p.account_name AS parent_name,

                CASE 
                    WHEN a.created_by LIKE 'SYSTEM%' 
                        THEN CONCAT('SYSTEM(', IFNULL(p1.employee_name, '?'), ')')
                    WHEN a.created_by LIKE 'USER%' 
                        THEN CONCAT('USER(', IFNULL(p1.employee_name, '?'), ')')
                    ELSE a.created_by
                END AS created_by_name,

                CASE 
                    WHEN a.updated_by LIKE 'SYSTEM%' 
                        THEN CONCAT('SYSTEM(', IFNULL(p2.employee_name, '?'), ')')
                    WHEN a.updated_by LIKE 'USER%' 
                        THEN CONCAT('USER(', IFNULL(p2.employee_name, '?'), ')')
                    ELSE a.updated_by
                END AS updated_by_name,

                CASE 
                    WHEN a.deleted_by LIKE 'SYSTEM%' 
                        THEN CONCAT('SYSTEM(', IFNULL(p3.employee_name, '?'), ')')
                    WHEN a.deleted_by LIKE 'USER%' 
                        THEN CONCAT('USER(', IFNULL(p3.employee_name, '?'), ')')
                    ELSE a.deleted_by
                END AS deleted_by_name

            FROM ledger_accounts a

            LEFT JOIN ledger_accounts p
                ON a.parent_id = p.id

            LEFT JOIN user_employees p1
                ON p1.user_id = SUBSTRING_INDEX(SUBSTRING_INDEX(a.created_by, '(', -1), ')', 1)

            LEFT JOIN user_employees p2
                ON p2.user_id = SUBSTRING_INDEX(SUBSTRING_INDEX(a.updated_by, '(', -1), ')', 1)

            LEFT JOIN user_employees p3
                ON p3.user_id = SUBSTRING_INDEX(SUBSTRING_INDEX(a.deleted_by, '(', -1), ')', 1)

            WHERE a.account_code = :account_code
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':account_code' => $accountCode
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* =========================================================
     * 계정 생성
     * ========================================================= */

     public function create(array $data): bool
     {
         // 🔥 부모 level 조회 (있으면)
         $level = 1;
     
         if (!empty($data['parent_id'])) {
             $parent = $this->getById($data['parent_id']);
             if ($parent) {
                 $level = (int)$parent['level'] + 1;
             }
         }
     
         $sql = "
         INSERT INTO ledger_accounts (
             id,
             account_code,
             account_name,
             parent_id,
             account_group,
             normal_balance,
             level,
             is_posting,
             allow_sub_account,
             note,
             memo,
             is_active,
             created_by,
             updated_by
         )
         VALUES (
             :id,
             :account_code,
             :account_name,
             :parent_id,
             :account_group,
             :normal_balance,
             :level,
             :is_posting,
             :allow_sub_account,
             :note,
             :memo,
             :is_active,
             :created_by,
             :updated_by
         )
         ";
     
         $stmt = $this->db->prepare($sql);
         if (empty($data['created_by'])) {
            throw new \Exception('created_by 없음');
        }
         return $stmt->execute([
             ':id' => $data['id'],
             ':account_code' => $data['account_code'],
             ':account_name' => $data['account_name'],
             ':parent_id' => $data['parent_id'] ?? null,
             ':account_group' => $data['account_group'],
             ':normal_balance' => $data['normal_balance'] ?? 'debit',
             ':level' => $level,
             ':is_posting' => $data['is_posting'] ?? 1,
             ':allow_sub_account' => 0, // 기본값 유지
             ':note' => $data['note'] ?? null,
             ':memo' => $data['memo'] ?? null,
             ':is_active' => $data['is_active'] ?? 1,
             ':created_by' => $data['created_by'],
             ':updated_by' => $data['updated_by'] ?? $data['created_by']
         ]);
     }

    /* =========================================================
     * 계정 수정
     * ========================================================= */

     public function update(string $id, array $data): bool
     {
         // 🔥 level 자동 계산
         $level = 1;
     
         if (!empty($data['parent_id'])) {
             $parent = $this->getById($data['parent_id']);
             if ($parent) {
                 $level = (int)$parent['level'] + 1;
             }
         }
     
         $sql = "
         UPDATE ledger_accounts
         SET
             account_code = :account_code,
             account_name = :account_name,
             parent_id = :parent_id,
             account_group = :account_group,
             normal_balance = :normal_balance,
             level = :level,
             is_posting = :is_posting,
             allow_sub_account = :allow_sub_account,
             note = :note,
             memo = :memo,
             is_active = :is_active,
             updated_by = :updated_by
         WHERE id = :id
         ";
     
         $stmt = $this->db->prepare($sql);
         if (empty($data['updated_by'])) {
            throw new \Exception('updated_by 없음');
        }
         return $stmt->execute([
             ':id' => $id,
             ':account_code' => $data['account_code'],
             ':account_name' => $data['account_name'],
             ':parent_id' => $data['parent_id'] ?? null,
             ':account_group' => $data['account_group'],
             ':normal_balance' => $data['normal_balance'] ?? 'debit',
             ':level' => $level,
             ':is_posting' => $data['is_posting'] ?? 1, 
             ':allow_sub_account' => $data['allow_sub_account'] ?? 0,
             ':note' => $data['note'] ?? null,
             ':memo' => $data['memo'] ?? null,
             ':is_active' => $data['is_active'] ?? 1,
             ':updated_by' => $data['updated_by']
         ]);
     }



    /* =========================================================
     * 계정 트리 조회
     * ========================================================= */

    public function getTree(): array
    {
        $sql = "
        SELECT *
        FROM ledger_accounts
        WHERE deleted_at IS NULL
        ORDER BY code
        ";

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTrashList(): array
    {
        $sql = "
            SELECT
                c.id,
                c.account_code,
                c.account_name,
                c.account_group,
                c.deleted_at,
                c.deleted_by,
    
                CASE 
                    WHEN c.deleted_by LIKE 'SYSTEM%' 
                        THEN CONCAT('SYSTEM(', IFNULL(p.employee_name,'?'), ')')
    
                    WHEN c.deleted_by LIKE 'USER%' 
                        THEN CONCAT('USER(', IFNULL(p.employee_name,'?'), ')')
    
                    ELSE c.deleted_by
                END AS deleted_by_name
    
            FROM ledger_accounts c
    
            LEFT JOIN user_employees p
            ON p.user_id = SUBSTRING_INDEX(
                SUBSTRING_INDEX(c.deleted_by, '(', -1),
                ')',
                1
            )
    
            WHERE c.deleted_at IS NOT NULL
    
            ORDER BY c.deleted_at DESC
        ";
    
        $stmt = $this->db->query($sql);
    
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /* =========================================================
     * 자식 계정 조회
     * ========================================================= */
    public function getChildren(string $parentId): array
    {
        $sql = "
        SELECT *
        FROM ledger_accounts
        WHERE parent_id = :parent_id
        AND deleted_at IS NULL
        ORDER BY code
        ";
    
        $stmt = $this->db->prepare($sql);
    
        $stmt->execute([
            ':parent_id' => $parentId
        ]);
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }



    public function hasChildren(string $id): bool
    {
        $sql = "
        SELECT COUNT(*)
        FROM ledger_accounts
        WHERE parent_id = :id
        AND deleted_at IS NULL
        ";
    
        $stmt = $this->db->prepare($sql);
    
        $stmt->execute([
            ':id' => $id
        ]);
    
        return (int)$stmt->fetchColumn() > 0;
    }

    public function softDelete(string $id, string $actor): bool
    {
        $stmt = $this->db->prepare("
            UPDATE ledger_accounts
            SET is_active = 0,
                deleted_at = NOW(),
                deleted_by = :user
            WHERE id = :id
        ");
    
        return $stmt->execute([
            ':id' => $id,
            ':user' => $actor
        ]);
    }

    public function restore(string $id, string $actor): bool
    {
        $stmt = $this->db->prepare("
            UPDATE ledger_accounts
            SET 
                is_active = 1,
                deleted_at = NULL,
                deleted_by = NULL,
                updated_by = :actor
            WHERE id = :id
        ");
    
        return $stmt->execute([
            ':id' => $id,
            ':actor' => $actor
        ]);
    }

    public function hardDelete(string $id, string $actor): bool
    {
        // 로그 남기고 싶으면 여기서 logger 쓰면 됨
        $stmt = $this->db->prepare("
            DELETE FROM ledger_accounts
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id' => $id
        ]);
    }
    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM ledger_accounts
            WHERE account_code = :code
            LIMIT 1
        ");

        $stmt->execute([':code' => $code]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    public function updateOrder(string $id, int $code): bool
    {
        $stmt = $this->db->prepare("
            UPDATE ledger_accounts
            SET code = :code
            WHERE id = :id
        ");
    
        return $stmt->execute([
            ':code' => $code,
            ':id' => $id
        ]);
    }

    public function getList(array $filters = []): array
    {
        $sql = "
            SELECT 
                a.*,
                p.account_name AS parent_name,
    
                CASE 
                    WHEN EXISTS (
                        SELECT 1 
                        FROM ledger_sub_accounts s 
                        WHERE s.account_id = a.id
                    ) THEN '사용중'
                    WHEN a.allow_sub_account = 1 THEN '가능'
                    ELSE '미사용'
                END AS sub_account_status
    
            FROM ledger_accounts a
    
            LEFT JOIN ledger_accounts p
                ON a.parent_id = p.id
    
            WHERE a.deleted_at IS NULL
        ";
    
        $params = [];
    
        foreach ($filters as $i => $f) {
    
            $field = $f['field'] ?? '';
            $value = $f['value'] ?? '';
    
            if (!$field || $value === '') continue;
    
            // 🔥 날짜 검색
            if (is_array($value)) {
    
                $startKey = ":start{$i}";
                $endKey   = ":end{$i}";
    
                if ($field === 'created_at' || $field === 'updated_at') {
    
                    $sql .= "
                        AND a.{$field} >= {$startKey}
                        AND a.{$field} < DATE_ADD({$endKey}, INTERVAL 1 DAY)
                    ";
    
                    $params[$startKey] = $value['start'];
                    $params[$endKey]   = $value['end'];
                }
    
                continue;
            }
    
            $key = ":v{$i}";
    
            switch ($field) {
    
                case 'account_code':
                case 'account_name':
                case 'account_group':
                    $sql .= " AND a.{$field} LIKE {$key}";
                    $params[$key] = "%{$value}%";
                    break;
    
                case 'parent_name':
                    $sql .= " AND p.account_name LIKE {$key}";
                    $params[$key] = "%{$value}%";
                    break;
            }
        }
    
        $sql .= " ORDER BY CAST(a.code AS UNSIGNED)";
    
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }


}