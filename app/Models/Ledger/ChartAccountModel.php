<?php

namespace App\Models\Ledger;

use Core\Database;
use PDO;

class ChartAccountModel
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getAll(): array
    {
        $sql = "
            SELECT
                a.*,
                p.account_name AS parent_name,
                CASE
                    WHEN COUNT(sa.id) > 0 THEN 1
                    ELSE 0
                END AS has_sub_account
            FROM ledger_accounts a
            LEFT JOIN ledger_accounts p
                ON a.parent_id = p.id
            LEFT JOIN ledger_sub_accounts sa
                ON sa.account_id = a.id
            WHERE a.deleted_at IS NULL
            GROUP BY a.id
            ORDER BY a.sort_no ASC
        ";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM ledger_accounts
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getByAccountCode(string $accountCode): ?array
    {
        $sql = "
            SELECT
                a.*,
                p.account_name AS parent_name,
                CASE
                    WHEN p1.employee_name IS NOT NULL
                        THEN CONCAT('USER:', p1.employee_name)
                    ELSE a.created_by
                END AS created_by_name,
                CASE
                    WHEN p2.employee_name IS NOT NULL
                        THEN CONCAT('USER:', p2.employee_name)
                    ELSE a.updated_by
                END AS updated_by_name
            FROM ledger_accounts a
            LEFT JOIN ledger_accounts p
                ON a.parent_id = p.id
            LEFT JOIN user_employees p1
                ON p1.user_id = CASE
                    WHEN a.created_by LIKE 'USER:%' THEN REPLACE(a.created_by, 'USER:', '')
                    WHEN a.created_by LIKE 'SYSTEM:%' THEN REPLACE(a.created_by, 'SYSTEM:', '')
                    WHEN a.created_by LIKE 'SYSTEM(%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(a.created_by, '(', -1), ')', 1)
                    WHEN a.created_by LIKE 'USER(%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(a.created_by, '(', -1), ')', 1)
                    ELSE NULL
                END
            LEFT JOIN user_employees p2
                ON p2.user_id = CASE
                    WHEN a.updated_by LIKE 'USER:%' THEN REPLACE(a.updated_by, 'USER:', '')
                    WHEN a.updated_by LIKE 'SYSTEM:%' THEN REPLACE(a.updated_by, 'SYSTEM:', '')
                    WHEN a.updated_by LIKE 'SYSTEM(%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(a.updated_by, '(', -1), ')', 1)
                    WHEN a.updated_by LIKE 'USER(%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(a.updated_by, '(', -1), ')', 1)
                    ELSE NULL
                END
            WHERE a.account_code = :account_code
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':account_code' => $accountCode]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getDetailByAccountCode(string $accountCode): ?array
    {
        $sql = "
            SELECT
                a.*,
                p.account_name AS parent_name,
                CASE
                    WHEN p1.employee_name IS NOT NULL
                        THEN CONCAT('USER:', p1.employee_name)
                    ELSE a.created_by
                END AS created_by_name,
                CASE
                    WHEN p2.employee_name IS NOT NULL
                        THEN CONCAT('USER:', p2.employee_name)
                    ELSE a.updated_by
                END AS updated_by_name,
                CASE
                    WHEN p3.employee_name IS NOT NULL
                        THEN CONCAT('USER:', p3.employee_name)
                    ELSE a.deleted_by
                END AS deleted_by_name
            FROM ledger_accounts a
            LEFT JOIN ledger_accounts p
                ON a.parent_id = p.id
            LEFT JOIN user_employees p1
                ON p1.user_id = CASE
                    WHEN a.created_by LIKE 'USER:%' THEN REPLACE(a.created_by, 'USER:', '')
                    WHEN a.created_by LIKE 'SYSTEM:%' THEN REPLACE(a.created_by, 'SYSTEM:', '')
                    WHEN a.created_by LIKE 'SYSTEM(%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(a.created_by, '(', -1), ')', 1)
                    WHEN a.created_by LIKE 'USER(%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(a.created_by, '(', -1), ')', 1)
                    ELSE NULL
                END
            LEFT JOIN user_employees p2
                ON p2.user_id = CASE
                    WHEN a.updated_by LIKE 'USER:%' THEN REPLACE(a.updated_by, 'USER:', '')
                    WHEN a.updated_by LIKE 'SYSTEM:%' THEN REPLACE(a.updated_by, 'SYSTEM:', '')
                    WHEN a.updated_by LIKE 'SYSTEM(%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(a.updated_by, '(', -1), ')', 1)
                    WHEN a.updated_by LIKE 'USER(%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(a.updated_by, '(', -1), ')', 1)
                    ELSE NULL
                END
            LEFT JOIN user_employees p3
                ON p3.user_id = CASE
                    WHEN a.deleted_by LIKE 'USER:%' THEN REPLACE(a.deleted_by, 'USER:', '')
                    WHEN a.deleted_by LIKE 'SYSTEM:%' THEN REPLACE(a.deleted_by, 'SYSTEM:', '')
                    WHEN a.deleted_by LIKE 'SYSTEM(%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(a.deleted_by, '(', -1), ')', 1)
                    WHEN a.deleted_by LIKE 'USER(%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(a.deleted_by, '(', -1), ')', 1)
                    ELSE NULL
                END
            WHERE a.account_code = :account_code
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':account_code' => $accountCode]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): bool
    {
        $stmt = $this->db->prepare("
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
                created_at,
                created_by,
                updated_at,
                updated_by
            ) VALUES (
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
                :created_at,
                :created_by,
                :updated_at,
                :updated_by
            )
        ");

        $result = $stmt->execute([
            ':id' => $data['id'],
            ':account_code' => $data['account_code'],
            ':account_name' => $data['account_name'],
            ':parent_id' => $data['parent_id'] ?? null,
            ':account_group' => $data['account_group'],
            ':normal_balance' => $data['normal_balance'] ?? 'debit',
            ':level' => $data['level'] ?? 1,
            ':is_posting' => $data['is_posting'] ?? 1,
            ':allow_sub_account' => $data['allow_sub_account'] ?? 0,
            ':note' => $data['note'] ?? null,
            ':memo' => $data['memo'] ?? null,
            ':is_active' => $data['is_active'] ?? 1,
            ':created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
            ':created_by' => $data['created_by'],
            ':updated_at' => $data['updated_at'] ?? date('Y-m-d H:i:s'),
            ':updated_by' => $data['updated_by'] ?? $data['created_by'],
        ]);

        if ($result) {
            return true;
        }

        $error = $stmt->errorInfo();
        throw new \RuntimeException($error[2] ?? 'ledger_accounts insert failed');
    }

    public function update(string $id, array $data): bool
    {
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

        return $stmt->execute([
            ':id' => $id,
            ':account_code' => $data['account_code'],
            ':account_name' => $data['account_name'],
            ':parent_id' => $data['parent_id'] ?? null,
            ':account_group' => $data['account_group'],
            ':normal_balance' => $data['normal_balance'] ?? 'debit',
            ':level' => $data['level'] ?? 1,
            ':is_posting' => $data['is_posting'] ?? 1,
            ':allow_sub_account' => $data['allow_sub_account'] ?? 0,
            ':note' => $data['note'] ?? null,
            ':memo' => $data['memo'] ?? null,
            ':is_active' => $data['is_active'] ?? 1,
            ':updated_by' => $data['updated_by'],
        ]);
    }

    public function getTree(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM ledger_accounts
            WHERE deleted_at IS NULL
            ORDER BY sort_no ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
                    WHEN p.employee_name IS NOT NULL THEN CONCAT('USER:', p.employee_name)
                    ELSE c.deleted_by
                END AS deleted_by_name
            FROM ledger_accounts c
            LEFT JOIN user_employees p
                ON p.user_id = CASE
                    WHEN c.deleted_by LIKE 'USER:%' THEN REPLACE(c.deleted_by, 'USER:', '')
                    WHEN c.deleted_by LIKE 'SYSTEM:%' THEN REPLACE(c.deleted_by, 'SYSTEM:', '')
                    WHEN c.deleted_by LIKE 'SYSTEM(%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(c.deleted_by, '(', -1), ')', 1)
                    WHEN c.deleted_by LIKE 'USER(%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(c.deleted_by, '(', -1), ')', 1)
                    ELSE NULL
                END
            WHERE c.deleted_at IS NOT NULL
            ORDER BY c.deleted_at DESC
        ";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getChildren(string $parentId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM ledger_accounts
            WHERE parent_id = :parent_id
              AND deleted_at IS NULL
            ORDER BY sort_no ASC
        ");

        $stmt->execute([':parent_id' => $parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function hasChildren(string $id): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM ledger_accounts
            WHERE parent_id = :id
              AND deleted_at IS NULL
        ");

        $stmt->execute([':id' => $id]);
        return (int) $stmt->fetchColumn() > 0;
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
            ':user' => $actor,
        ]);
    }

    public function restore(string $id, string $actor): bool
    {
        $stmt = $this->db->prepare("
            UPDATE ledger_accounts
            SET is_active = 1,
                deleted_at = NULL,
                deleted_by = NULL,
                updated_by = :actor
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id' => $id,
            ':actor' => $actor,
        ]);
    }

    public function hardDelete(string $id, string $actor): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM ledger_accounts
            WHERE id = :id
        ");

        return $stmt->execute([':id' => $id]);
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM ledger_accounts
            WHERE account_code = :code
            LIMIT 1
        ");

        $stmt->execute([':code' => $code]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function updateOrder(string $id, int $sortNo): bool
    {
        $stmt = $this->db->prepare("
            UPDATE ledger_accounts
            SET sort_no = :sort_no
            WHERE id = :id
        ");

        return $stmt->execute([
            ':sort_no' => $sortNo,
            ':id' => $id,
        ]);
    }

    public function getList(array $filters = []): array
    {
        $sql = "
            SELECT
                a.*,
                p.account_name AS parent_name,
                CASE
                    WHEN p1.employee_name IS NOT NULL
                        THEN CONCAT('USER:', p1.employee_name)
                    ELSE a.created_by
                END AS created_by_name,
                CASE
                    WHEN p2.employee_name IS NOT NULL
                        THEN CONCAT('USER:', p2.employee_name)
                    ELSE a.updated_by
                END AS updated_by_name,
                CASE
                    WHEN p3.employee_name IS NOT NULL
                        THEN CONCAT('USER:', p3.employee_name)
                    ELSE a.deleted_by
                END AS deleted_by_name,
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM ledger_sub_accounts sa
                        WHERE sa.account_id = a.id
                    ) THEN 1
                    ELSE 0
                END AS has_sub_account
            FROM ledger_accounts a
            LEFT JOIN ledger_accounts p
                ON a.parent_id = p.id
            LEFT JOIN user_employees p1
                ON p1.user_id = CASE
                    WHEN a.created_by LIKE 'USER:%' THEN REPLACE(a.created_by, 'USER:', '')
                    WHEN a.created_by LIKE 'SYSTEM:%' THEN REPLACE(a.created_by, 'SYSTEM:', '')
                    WHEN a.created_by LIKE 'SYSTEM(%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(a.created_by, '(', -1), ')', 1)
                    WHEN a.created_by LIKE 'USER(%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(a.created_by, '(', -1), ')', 1)
                    ELSE NULL
                END
            LEFT JOIN user_employees p2
                ON p2.user_id = CASE
                    WHEN a.updated_by LIKE 'USER:%' THEN REPLACE(a.updated_by, 'USER:', '')
                    WHEN a.updated_by LIKE 'SYSTEM:%' THEN REPLACE(a.updated_by, 'SYSTEM:', '')
                    WHEN a.updated_by LIKE 'SYSTEM(%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(a.updated_by, '(', -1), ')', 1)
                    WHEN a.updated_by LIKE 'USER(%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(a.updated_by, '(', -1), ')', 1)
                    ELSE NULL
                END
            LEFT JOIN user_employees p3
                ON p3.user_id = CASE
                    WHEN a.deleted_by LIKE 'USER:%' THEN REPLACE(a.deleted_by, 'USER:', '')
                    WHEN a.deleted_by LIKE 'SYSTEM:%' THEN REPLACE(a.deleted_by, 'SYSTEM:', '')
                    WHEN a.deleted_by LIKE 'SYSTEM(%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(a.deleted_by, '(', -1), ')', 1)
                    WHEN a.deleted_by LIKE 'USER(%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(a.deleted_by, '(', -1), ')', 1)
                    ELSE NULL
                END
            WHERE a.deleted_at IS NULL
        ";

        $params = [];

        foreach ($filters as $i => $filter) {
            $field = $filter['field'] ?? '';
            $value = $filter['value'] ?? '';

            if ($field === '' || $value === '') {
                continue;
            }

            if (is_array($value)) {
                $startKey = ":start{$i}";
                $endKey = ":end{$i}";

                if ($field === 'created_at' || $field === 'updated_at') {
                    $sql .= "
                        AND a.{$field} >= {$startKey}
                        AND a.{$field} <= {$endKey}
                    ";
                    $params[$startKey] = $value['start'];
                    $params[$endKey] = $value['end'];
                }

                continue;
            }

            $key = ":v{$i}";

            switch ($field) {
                case 'sort_no':
                    $sql .= " AND a.sort_no LIKE {$key}";
                    $params[$key] = "%{$value}%";
                    break;

                case 'account_code':
                case 'account_name':
                case 'account_group':
                case 'note':
                case 'memo':
                    $sql .= " AND a.{$field} LIKE {$key}";
                    $params[$key] = "%{$value}%";
                    break;

                case 'parent_name':
                    $sql .= " AND p.account_name LIKE {$key}";
                    $params[$key] = "%{$value}%";
                    break;

                case 'created_by_name':
                    $sql .= " AND (p1.employee_name LIKE {$key} OR a.created_by LIKE {$key})";
                    $params[$key] = "%{$value}%";
                    break;

                case 'updated_by_name':
                    $sql .= " AND (p2.employee_name LIKE {$key} OR a.updated_by LIKE {$key})";
                    $params[$key] = "%{$value}%";
                    break;

                case 'level':
                    $sql .= " AND a.level = {$key}";
                    $params[$key] = (int) $value;
                    break;

                case 'normal_balance':
                    $normalized = trim((string) $value);
                    if ($normalized === '차변') {
                        $normalized = 'debit';
                    } elseif ($normalized === '대변') {
                        $normalized = 'credit';
                    }
                    if (in_array($normalized, ['차변', 'debit'], true)) {
                        $normalized = 'debit';
                    } elseif (in_array($normalized, ['대변', 'credit'], true)) {
                        $normalized = 'credit';
                    }

                    $sql .= " AND a.normal_balance = {$key}";
                    $params[$key] = $normalized;
                    break;

                case 'is_posting':
                case 'is_active':
                case 'allow_sub_account':
                    $normalized = trim((string) $value);
                    if (in_array($normalized, ['가능', '사용', '허용'], true)) {
                        $normalized = '1';
                    } elseif (in_array($normalized, ['불가', '미사용', '미허용'], true)) {
                        $normalized = '0';
                    }
                    if (in_array($normalized, ['가능', '사용', '허용', '1', 'true'], true)) {
                        $normalized = 1;
                    } elseif (in_array($normalized, ['불가', '미사용', '미허용', '0', 'false'], true)) {
                        $normalized = 0;
                    }

                    $sql .= " AND a.{$field} = {$key}";
                    $params[$key] = (int) $normalized;
                    break;
            }
        }

        $sql .= " ORDER BY a.sort_no ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function updateAllowSubAccount(string $id, int $allowSubAccount): bool
    {
        $stmt = $this->db->prepare("
            UPDATE ledger_accounts
            SET allow_sub_account = :allow_sub_account
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id' => $id,
            ':allow_sub_account' => $allowSubAccount,
        ]);
    }
}
