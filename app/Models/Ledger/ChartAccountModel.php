<?php

namespace App\Models\Ledger;

use Core\Database;
use PDO;

class ChartAccountModel
{
    private PDO $db;
    private array $columnExistsCache = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getAll(): array
    {
        $levelExpr = $this->accountLevelExpr('a');
        $postableExpr = $this->postableExpr('a');
        $postingExpr = $this->postingExpr('a');
        $parentPathExpr = $this->columnExists('ledger_accounts', 'full_path') ? 'p.full_path' : 'p.account_name';
        $orderExpr = $this->treeOrderExpr('a');
        $subAccountNameExpr = $this->subAccountNameExpr('sa');

        $sql = "
            SELECT
                a.*,
                {$levelExpr} AS level,
                {$levelExpr} AS account_level,
                {$postableExpr} AS is_postable,
                {$postingExpr} AS is_posting,
                p.account_name AS parent_name,
                {$parentPathExpr} AS parent_path,
                CASE
                    WHEN COUNT(sa.id) > 0 THEN 1
                    ELSE 0
                END AS has_sub_account,
                GROUP_CONCAT(DISTINCT {$subAccountNameExpr} ORDER BY {$subAccountNameExpr} SEPARATOR ', ') AS sub_account_names
            FROM ledger_accounts a
            LEFT JOIN ledger_accounts p
                ON a.parent_id = p.id
            LEFT JOIN ledger_sub_accounts sa
                ON sa.account_id = a.id
            WHERE a.deleted_at IS NULL
            GROUP BY a.id
            ORDER BY {$orderExpr} ASC
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
        $values = $this->filterExistingAccountColumns([
            'id' => $data['id'],
            'account_code' => $data['account_code'],
            'account_name' => $data['account_name'],
            'parent_id' => $data['parent_id'] ?? null,
            'account_group' => $data['account_group'],
            'account_category' => $data['account_category'] ?? $data['account_group'],
            'normal_balance' => $data['normal_balance'] ?? 'debit',
            'level' => $data['level'] ?? 1,
            'account_level' => $data['account_level'] ?? $data['level'] ?? 1,
            'is_posting' => $data['is_posting'] ?? 1,
            'is_postable' => $data['is_postable'] ?? (($data['is_posting'] ?? 1) ? 'Y' : 'N'),
            'allow_sub_account' => $data['allow_sub_account'] ?? 0,
            'status' => $data['status'] ?? (($data['is_active'] ?? 1) ? 'active' : 'inactive'),
            'full_path' => $data['full_path'] ?? null,
            'path_ids' => $data['path_ids'] ?? null,
            'tree_sort' => $data['tree_sort'] ?? null,
            'note' => $data['note'] ?? null,
            'memo' => $data['memo'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
            'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
            'created_by' => $data['created_by'],
            'updated_at' => $data['updated_at'] ?? date('Y-m-d H:i:s'),
            'updated_by' => $data['updated_by'] ?? $data['created_by'],
        ]);

        $columns = array_keys($values);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $stmt = $this->db->prepare("
            INSERT INTO ledger_accounts (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $placeholders) . ")
        ");

        $result = $stmt->execute($this->namedParams($values));

        if ($result) {
            return true;
        }

        $error = $stmt->errorInfo();
        throw new \RuntimeException($error[2] ?? 'ledger_accounts insert failed');
    }

    public function update(string $id, array $data): bool
    {
        $values = $this->filterExistingAccountColumns([
            'account_code' => $data['account_code'],
            'account_name' => $data['account_name'],
            'parent_id' => $data['parent_id'] ?? null,
            'account_group' => $data['account_group'],
            'account_category' => $data['account_category'] ?? $data['account_group'],
            'normal_balance' => $data['normal_balance'] ?? 'debit',
            'level' => $data['level'] ?? 1,
            'account_level' => $data['account_level'] ?? $data['level'] ?? 1,
            'is_posting' => $data['is_posting'] ?? 1,
            'is_postable' => $data['is_postable'] ?? (($data['is_posting'] ?? 1) ? 'Y' : 'N'),
            'allow_sub_account' => $data['allow_sub_account'] ?? 0,
            'status' => $data['status'] ?? (($data['is_active'] ?? 1) ? 'active' : 'inactive'),
            'note' => $data['note'] ?? null,
            'memo' => $data['memo'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
            'updated_by' => $data['updated_by'],
        ]);

        $assignments = array_map(static fn (string $column): string => "{$column} = :{$column}", array_keys($values));
        $stmt = $this->db->prepare("
            UPDATE ledger_accounts
            SET " . implode(', ', $assignments) . "
            WHERE id = :id
        ");

        $params = $this->namedParams($values);
        $params[':id'] = $id;

        return $stmt->execute($params);
    }

    public function updateStatus(array|string $ids, int $isActive, string $actor): bool
    {
        $ids = array_values(array_filter(array_map('strval', (array) $ids)));
        if (empty($ids)) {
            return false;
        }

        $placeholders = [];
        $params = [
            ':is_active' => $isActive === 1 ? 1 : 0,
            ':updated_by' => $actor,
        ];

        foreach ($ids as $index => $id) {
            $key = ':id_' . $index;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        $statusSet = $this->columnExists('ledger_accounts', 'status')
            ? ', status = :status'
            : '';

        $stmt = $this->db->prepare("
            UPDATE ledger_accounts
            SET is_active = :is_active,
                updated_by = :updated_by
                {$statusSet}
            WHERE id IN (" . implode(', ', $placeholders) . ")
              AND deleted_at IS NULL
        ");

        if ($statusSet !== '') {
            $params[':status'] = $isActive === 1 ? 'active' : 'inactive';
        }

        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function getTree(): array
    {
        $levelExpr = $this->accountLevelExpr();
        $postableExpr = $this->postableExpr();
        $postingExpr = $this->postingExpr();
        $fullPathExpr = $this->columnExists('ledger_accounts', 'full_path') ? 'full_path' : 'account_name';
        $pathIdsExpr = $this->columnExists('ledger_accounts', 'path_ids') ? 'path_ids' : "CONCAT('/', id, '/')";
        $treeSortExpr = $this->treeOrderExpr();

        $stmt = $this->db->query("
            SELECT
                *,
                {$levelExpr} AS account_level,
                {$postableExpr} AS is_postable,
                {$postingExpr} AS is_posting,
                {$fullPathExpr} AS full_path,
                {$pathIdsExpr} AS path_ids,
                {$treeSortExpr} AS tree_sort
            FROM ledger_accounts
            WHERE deleted_at IS NULL
            ORDER BY {$treeSortExpr} ASC
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
        $statusSet = $this->columnExists('ledger_accounts', 'status') ? "status = 'deleted'," : '';
        $stmt = $this->db->prepare("
            UPDATE ledger_accounts
            SET is_active = 0,
                {$statusSet}
                deleted_at = NOW(),
                deleted_by = :user
            WHERE id = :id
              AND deleted_at IS NULL
        ");

        $stmt->execute([
            ':id' => $id,
            ':user' => $actor,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function restore(string $id, string $actor): bool
    {
        $statusSet = $this->columnExists('ledger_accounts', 'status') ? "status = 'active'," : '';
        $stmt = $this->db->prepare("
            UPDATE ledger_accounts
            SET is_active = 1,
                {$statusSet}
                deleted_at = NULL,
                deleted_by = NULL,
                updated_by = :actor
            WHERE id = :id
              AND deleted_at IS NOT NULL
        ");

        $stmt->execute([
            ':id' => $id,
            ':actor' => $actor,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function hardDelete(string $id, string $actor): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM ledger_accounts
            WHERE id = :id
        ");

        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM ledger_accounts
            WHERE account_code = :code
              AND deleted_at IS NULL
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
        $levelExpr = $this->accountLevelExpr('a');
        $postableExpr = $this->postableExpr('a');
        $postingExpr = $this->postingExpr('a');
        $parentPathExpr = $this->columnExists('ledger_accounts', 'full_path') ? 'p.full_path' : 'p.account_name';
        $fullPathExpr = $this->columnExists('ledger_accounts', 'full_path') ? 'a.full_path' : 'a.account_name';
        $pathIdsExpr = $this->columnExists('ledger_accounts', 'path_ids') ? 'a.path_ids' : "CONCAT('/', a.id, '/')";
        $treeSortExpr = $this->treeOrderExpr('a');
        $subAccountNameExpr = $this->subAccountNameExpr('sa');

        $sql = "
            SELECT
                a.*,
                {$levelExpr} AS level,
                {$levelExpr} AS account_level,
                {$postableExpr} AS is_postable,
                {$postingExpr} AS is_posting,
                {$fullPathExpr} AS full_path,
                {$pathIdsExpr} AS path_ids,
                {$treeSortExpr} AS tree_sort,
                p.account_name AS parent_name,
                {$parentPathExpr} AS parent_path,
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
                END AS has_sub_account,
                GROUP_CONCAT(DISTINCT {$subAccountNameExpr} ORDER BY {$subAccountNameExpr} SEPARATOR ', ') AS sub_account_names
            FROM ledger_accounts a
            LEFT JOIN ledger_accounts p
                ON a.parent_id = p.id
            LEFT JOIN ledger_sub_accounts sa
                ON sa.account_id = a.id
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
                case 'account_level':
                    $sql .= " AND {$levelExpr} = {$key}";
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
                case 'is_postable':
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

                    if ($field === 'is_postable') {
                        $sql .= " AND {$postableExpr} = {$key}";
                        $params[$key] = ((int) $normalized) === 1 ? 'Y' : 'N';
                    } elseif ($field === 'is_posting') {
                        $sql .= " AND {$postableExpr} = {$key}";
                        $params[$key] = ((int) $normalized) === 1 ? 'Y' : 'N';
                    } else {
                        $sql .= " AND a.{$field} = {$key}";
                        $params[$key] = (int) $normalized;
                    }
                    break;
            }
        }

        $sql .= " GROUP BY a.id ORDER BY {$treeSortExpr} ASC";

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

    public function getDescendantIds(string $id, bool $includeSelf = false): array
    {
        $ids = [];
        $frontier = [$id];
        $seen = [$id => true];

        while (!empty($frontier)) {
            $placeholders = [];
            $params = [];
            foreach (array_values($frontier) as $index => $parentId) {
                $key = ':parent_' . $index;
                $placeholders[] = $key;
                $params[$key] = $parentId;
            }

            $stmt = $this->db->prepare("
                SELECT id
                FROM ledger_accounts
                WHERE parent_id IN (" . implode(', ', $placeholders) . ")
                  AND deleted_at IS NULL
            ");
            $stmt->execute($params);
            $children = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

            $frontier = [];
            foreach ($children as $childId) {
                $childId = (string) $childId;
                if (isset($seen[$childId])) {
                    continue;
                }

                $seen[$childId] = true;
                $ids[] = $childId;
                $frontier[] = $childId;
            }
        }

        if ($includeSelf) {
            array_unshift($ids, $id);
        }

        return array_values(array_unique(array_map('strval', $ids)));
    }

    public function isDescendantOf(string $candidateParentId, string $id): bool
    {
        return in_array($candidateParentId, $this->getDescendantIds($id), true);
    }

    public function hasVoucherUsage(string $id): bool
    {
        $account = $this->getById($id);
        if (!$account) {
            return false;
        }

        $checks = [];
        $params = [];

        if ($this->columnExists('ledger_voucher_lines', 'account_id')) {
            $checks[] = 'account_id = :id';
            $params[':id'] = $id;
        }

        if ($this->columnExists('ledger_voucher_lines', 'account_code')) {
            $checks[] = 'account_code = :code';
            $params[':code'] = $account['account_code'] ?? '';
        }

        if (empty($checks)) {
            return false;
        }

        $deletedWhere = $this->columnExists('ledger_voucher_lines', 'deleted_at')
            ? 'deleted_at IS NULL AND'
            : '';

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM ledger_voucher_lines
            WHERE {$deletedWhere} (" . implode(' OR ', $checks) . ")
        ");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function refreshHierarchyMetadata(): void
    {
        $stmt = $this->db->query("
            SELECT id, parent_id, account_name, sort_no
            FROM ledger_accounts
            WHERE deleted_at IS NULL
            ORDER BY parent_id IS NOT NULL, parent_id, sort_no, account_code, account_name
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (empty($rows)) {
            return;
        }

        $childrenByParent = [];
        foreach ($rows as $row) {
            $parentKey = (string) ($row['parent_id'] ?? '');
            $childrenByParent[$parentKey][] = $row;
        }

        $updates = [];
        $walk = function (?string $parentId, int $level, string $pathIds, string $fullPath, string $treeSort) use (&$walk, &$childrenByParent, &$updates): void {
            $parentKey = (string) ($parentId ?? '');
            foreach ($childrenByParent[$parentKey] ?? [] as $index => $row) {
                $id = (string) $row['id'];
                if (strpos($pathIds, '/' . $id . '/') !== false) {
                    continue;
                }

                $sortPart = str_pad((string) ((int) ($row['sort_no'] ?? 0)), 10, '0', STR_PAD_LEFT) . '-' . str_pad((string) $index, 5, '0', STR_PAD_LEFT);
                $nextPathIds = $pathIds . $id . '/';
                $nextFullPath = $fullPath === '' ? (string) $row['account_name'] : $fullPath . ' > ' . $row['account_name'];
                $nextTreeSort = $treeSort === '' ? $sortPart : $treeSort . '/' . $sortPart;

                $updates[] = [
                    'id' => $id,
                    'level' => $level,
                    'account_level' => $level,
                    'path_ids' => $nextPathIds,
                    'full_path' => $nextFullPath,
                    'tree_sort' => $nextTreeSort,
                ];

                $walk($id, $level + 1, $nextPathIds, $nextFullPath, $nextTreeSort);
            }
        };
        $walk(null, 1, '/', '', '');

        $metadataColumns = [];
        foreach (['level', 'account_level', 'path_ids', 'full_path', 'tree_sort'] as $column) {
            if ($this->columnExists('ledger_accounts', $column)) {
                $metadataColumns[] = $column;
            }
        }
        if (empty($metadataColumns)) {
            return;
        }

        $assignments = array_map(static fn (string $column): string => "{$column} = :{$column}", $metadataColumns);
        $updateStmt = $this->db->prepare("
            UPDATE ledger_accounts
            SET " . implode(', ', $assignments) . "
            WHERE id = :id
        ");

        foreach ($updates as $update) {
            $values = [];
            foreach ($metadataColumns as $column) {
                $values[$column] = $update[$column];
            }
            $params = $this->namedParams($values);
            $params[':id'] = $update['id'];
            $updateStmt->execute($params);
        }
    }

    public function refreshPostableFlags(): void
    {
        $hasPostableColumn = $this->columnExists('ledger_accounts', 'is_postable');
        $postableSelect = $hasPostableColumn ? ', is_postable' : '';
        $stmt = $this->db->query("
            SELECT id, parent_id, is_posting{$postableSelect}
            FROM ledger_accounts
            WHERE deleted_at IS NULL
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $parentsWithChildren = [];
        foreach ($rows as $row) {
            if (!empty($row['parent_id'])) {
                $parentsWithChildren[(string) $row['parent_id']] = true;
            }
        }

        $flagColumns = ['is_posting'];
        if ($hasPostableColumn) {
            $flagColumns[] = 'is_postable';
        }
        $flagAssignments = array_map(static fn (string $column): string => "{$column} = :{$column}", $flagColumns);
        $updateStmt = $this->db->prepare("
            UPDATE ledger_accounts
            SET " . implode(', ', $flagAssignments) . "
            WHERE id = :id
        ");

        foreach ($rows as $row) {
            $hasChildren = isset($parentsWithChildren[(string) $row['id']]);
            $currentPostable = $hasPostableColumn
                ? strtoupper((string) ($row['is_postable'] ?? 'N'))
                : (((int) ($row['is_posting'] ?? 1)) === 1 ? 'Y' : 'N');
            $nextPostable = $hasChildren ? 'N' : ($currentPostable === 'Y' ? 'Y' : 'N');

            $values = [
                'is_posting' => $nextPostable === 'Y' ? 1 : 0,
            ];
            if ($hasPostableColumn) {
                $values['is_postable'] = $nextPostable;
            }

            $params = $this->namedParams($values);
            $params[':id'] = $row['id'];
            $updateStmt->execute($params);
        }
    }

    public function sumDescendantVoucherLines(string $id): array
    {
        $ids = $this->getDescendantIds($id, true);
        if (empty($ids)) {
            return ['debit_total' => 0, 'credit_total' => 0];
        }

        $idPlaceholders = [];
        $params = [];
        foreach ($ids as $index => $accountId) {
            $key = ':id_' . $index;
            $idPlaceholders[] = $key;
            $params[$key] = $accountId;
        }

        $accountStmt = $this->db->prepare("
            SELECT id, account_code
            FROM ledger_accounts
            WHERE id IN (" . implode(', ', $idPlaceholders) . ")
              AND deleted_at IS NULL
        ");
        $accountStmt->execute($params);
        $accounts = $accountStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $conditions = [];
        $queryParams = [];
        if ($this->columnExists('ledger_voucher_lines', 'account_id')) {
            $accountIdKeys = [];
            foreach ($accounts as $index => $account) {
                $key = ':account_id_' . $index;
                $accountIdKeys[] = $key;
                $queryParams[$key] = $account['id'];
            }
            if (!empty($accountIdKeys)) {
                $conditions[] = 'account_id IN (' . implode(', ', $accountIdKeys) . ')';
            }
        }
        if ($this->columnExists('ledger_voucher_lines', 'account_code')) {
            $codeKeys = [];
            foreach ($accounts as $index => $account) {
                if (($account['account_code'] ?? '') === '') {
                    continue;
                }
                $key = ':account_code_' . $index;
                $codeKeys[] = $key;
                $queryParams[$key] = $account['account_code'];
            }
            if (!empty($codeKeys)) {
                $conditions[] = 'account_code IN (' . implode(', ', $codeKeys) . ')';
            }
        }
        if (empty($conditions)) {
            return ['debit_total' => 0, 'credit_total' => 0];
        }

        $deletedWhere = $this->columnExists('ledger_voucher_lines', 'deleted_at')
            ? 'deleted_at IS NULL AND'
            : '';

        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(debit), 0) AS debit_total,
                COALESCE(SUM(credit), 0) AS credit_total
            FROM ledger_voucher_lines
            WHERE {$deletedWhere} (" . implode(' OR ', $conditions) . ")
        ");

        $stmt->execute($queryParams);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['debit_total' => 0, 'credit_total' => 0];
    }

    private function filterExistingAccountColumns(array $values): array
    {
        $filtered = [];
        foreach ($values as $column => $value) {
            if ($this->columnExists('ledger_accounts', $column)) {
                $filtered[$column] = $value;
            }
        }

        return $filtered;
    }

    private function namedParams(array $values): array
    {
        $params = [];
        foreach ($values as $column => $value) {
            $params[':' . $column] = $value;
        }

        return $params;
    }

    private function columnExists(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, $this->columnExistsCache)) {
            return $this->columnExistsCache[$cacheKey];
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
        ");

        $stmt->execute([
            ':table_name' => $table,
            ':column_name' => $column,
        ]);

        $exists = (int) $stmt->fetchColumn() > 0;
        $this->columnExistsCache[$cacheKey] = $exists;

        return $exists;
    }

    private function qualify(string $alias, string $column): string
    {
        return $alias !== '' ? "{$alias}.{$column}" : $column;
    }

    private function accountLevelExpr(string $alias = ''): string
    {
        return $this->columnExists('ledger_accounts', 'account_level')
            ? $this->qualify($alias, 'account_level')
            : $this->qualify($alias, 'level');
    }

    private function postableExpr(string $alias = ''): string
    {
        if ($this->columnExists('ledger_accounts', 'is_postable')) {
            return $this->qualify($alias, 'is_postable');
        }

        return "CASE WHEN COALESCE(" . $this->qualify($alias, 'is_posting') . ", 1) = 1 THEN 'Y' ELSE 'N' END";
    }

    private function subAccountNameExpr(string $alias = ''): string
    {
        $parts = [];
        foreach (['sub_name', 'sub_code', 'ref_type'] as $column) {
            if ($this->columnExists('ledger_sub_accounts', $column)) {
                $parts[] = 'NULLIF(' . $this->qualify($alias, $column) . ", '')";
            }
        }

        if (empty($parts)) {
            return 'NULL';
        }

        return 'COALESCE(' . implode(', ', $parts) . ')';
    }

    private function postingExpr(string $alias = ''): string
    {
        if ($this->columnExists('ledger_accounts', 'is_postable')) {
            return "CASE WHEN " . $this->qualify($alias, 'is_postable') . " = 'Y' THEN 1 ELSE 0 END";
        }

        return $this->qualify($alias, 'is_posting');
    }

    private function treeOrderExpr(string $alias = ''): string
    {
        if ($this->columnExists('ledger_accounts', 'tree_sort')) {
            return 'COALESCE(' . $this->qualify($alias, 'tree_sort') . ', LPAD(COALESCE(' . $this->qualify($alias, 'sort_no') . ', 0), 10, \'0\'))';
        }

        return 'LPAD(COALESCE(' . $this->qualify($alias, 'sort_no') . ', 0), 10, \'0\')';
    }
}
