<?php

namespace App\Models\Ledger;

use Core\Database;
use PDO;

class VoucherModel
{
    protected string $table = 'ledger_vouchers';

    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getList(array $filters = []): array
    {
        $sql = "
            SELECT
                v.*,
                v.voucher_no AS voucher_no,
                v.source_type AS type,
                COALESCE(voucher_line_accounts.account_label, '') AS account_label,
                COALESCE(voucher_line_accounts.debit_total, 0) AS debit_total,
                COALESCE(voucher_line_accounts.credit_total, 0) AS credit_total,
                COALESCE(voucher_line_accounts.line_count, 0) AS line_count,
                COALESCE(voucher_payments.payment_total, 0) AS payment_total,
                COALESCE(voucher_payments.payment_count, 0) AS payment_count,
                transaction_links.transaction_id,
                transaction_links.match_status,
                COALESCE(linked_clients.client_name, source_clients.client_name, '') AS client_name,
                reversal_vouchers.id AS reversal_voucher_id,
                reversal_vouchers.voucher_no AS reversal_voucher_no,
                original_vouchers.voucher_no AS original_voucher_no,
                CASE
                    WHEN transaction_links.voucher_id IS NULL THEN 'unlinked'
                    ELSE 'linked'
                END AS linked_status
            FROM {$this->table} v
            LEFT JOIN (
                SELECT
                    l.voucher_id,
                    GROUP_CONCAT(
                        DISTINCT CONCAT(a.account_code, ' ', a.account_name)
                        ORDER BY l.line_no
                        SEPARATOR ', '
                    ) AS account_label,
                    SUM(COALESCE(l.debit, 0)) AS debit_total,
                    SUM(COALESCE(l.credit, 0)) AS credit_total,
                    COUNT(l.id) AS line_count
                FROM ledger_voucher_lines l
                LEFT JOIN ledger_accounts a
                    ON a.id = l.account_id
                GROUP BY l.voucher_id
            ) voucher_line_accounts
                ON voucher_line_accounts.voucher_id = v.id
            LEFT JOIN (
                SELECT
                    p.voucher_id,
                    SUM(COALESCE(p.amount, 0)) AS payment_total,
                    COUNT(p.id) AS payment_count
                FROM ledger_voucher_payments p
                GROUP BY p.voucher_id
            ) voucher_payments
                ON voucher_payments.voucher_id = v.id
            LEFT JOIN (
                SELECT
                    l.voucher_id,
                    MIN(l.transaction_id) AS transaction_id,
                    CASE
                        WHEN SUM(CASE WHEN t.match_status = 'matched' THEN 1 ELSE 0 END) > 0 THEN 'matched'
                        WHEN COUNT(t.id) > 0 THEN MIN(t.match_status)
                        ELSE NULL
                    END AS match_status
                FROM ledger_transaction_links l
                LEFT JOIN ledger_transactions t
                    ON t.id = l.transaction_id
                   AND t.deleted_at IS NULL
                WHERE l.deleted_at IS NULL
                  AND l.is_active = 1
                GROUP BY l.voucher_id
            ) transaction_links
                ON transaction_links.voucher_id = v.id
            LEFT JOIN (
                SELECT
                    l.voucher_id,
                    MAX(sc.client_name) AS client_name
                FROM ledger_transaction_links l
                INNER JOIN ledger_transactions t
                    ON t.id = l.transaction_id
                   AND t.deleted_at IS NULL
                LEFT JOIN system_clients sc
                    ON sc.id = t.client_id
                WHERE l.deleted_at IS NULL
                  AND l.is_active = 1
                GROUP BY l.voucher_id
            ) linked_clients
                ON linked_clients.voucher_id = v.id
            LEFT JOIN system_clients source_clients
                ON source_clients.id = v.source_id
               AND v.source_type = 'CLIENT'
            LEFT JOIN {$this->table} reversal_vouchers
                ON reversal_vouchers.reversal_of = v.id
               AND reversal_vouchers.is_reversal = 1
               AND reversal_vouchers.deleted_at IS NULL
            LEFT JOIN {$this->table} original_vouchers
                ON original_vouchers.id = v.reversal_of
            WHERE v.deleted_at IS NULL
        ";

        $params = [];

        $isFilterList = $filters === [] || array_keys($filters) === range(0, count($filters) - 1);

        if (!$isFilterList) {
            if (!empty($filters['status'])) {
                $sql .= " AND v.status = :status";
                $params[':status'] = $filters['status'];
            }

            if (!empty($filters['source_type'])) {
                $sql .= " AND v.source_type = :source_type";
                $params[':source_type'] = $this->normalizeSourceTypeFilter((string) $filters['source_type']);
            }

            if (!empty($filters['source_id'])) {
                $sql .= " AND v.source_id = :source_id";
                $params[':source_id'] = $filters['source_id'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND v.voucher_date >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND v.voucher_date <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }

            if (!empty($filters['keyword'])) {
                $sql .= " AND (
                    v.voucher_no LIKE :keyword
                    OR v.summary_text LIKE :keyword
                    OR COALESCE(linked_clients.client_name, source_clients.client_name, '') LIKE :keyword
                )";
                $params[':keyword'] = '%' . $filters['keyword'] . '%';
            }
        }

        foreach ($filters as $index => $filter) {
            if (!is_array($filter)) {
                continue;
            }

            $field = trim((string) ($filter['field'] ?? ''));
            $value = $filter['value'] ?? '';

            if ($field === '' || $value === '' || $value === []) {
                continue;
            }

            if (is_array($value)) {
                $start = trim((string) ($value['start'] ?? ''));
                $end = trim((string) ($value['end'] ?? ''));

                if ($start === '' || $end === '') {
                    continue;
                }

                $column = match ($field) {
                    'voucher_date' => 'v.voucher_date',
                    'created_at' => 'v.created_at',
                    'updated_at' => 'v.updated_at',
                    default => null,
                };

                if ($column === null) {
                    continue;
                }

                $startKey = ":filter_start_{$index}";
                $endKey = ":filter_end_{$index}";
                $sql .= " AND {$column} BETWEEN {$startKey} AND {$endKey}";
                $params[$startKey] = $start;
                $params[$endKey] = $end;
                continue;
            }

            $rawValue = trim((string) $value);
            if ($rawValue === '') {
                continue;
            }

            $key = ":filter_{$index}";
            $likeKey = ":filter_like_{$index}";

            switch ($field) {
                case 'voucher_no':
                    $sql .= " AND v.voucher_no LIKE {$likeKey}";
                    $params[$likeKey] = "%{$rawValue}%";
                    break;

                case 'sort_no':
                    $sql .= " AND v.sort_no LIKE {$likeKey}";
                    $params[$likeKey] = "%{$rawValue}%";
                    break;

                case 'voucher_date':
                    $sql .= " AND v.voucher_date LIKE {$likeKey}";
                    $params[$likeKey] = "%{$rawValue}%";
                    break;

                case 'status':
                    $sql .= " AND v.status = {$key}";
                    $params[$key] = $this->normalizeStatusFilter($rawValue);
                    break;

                case 'type':
                case 'source_type':
                    $sql .= " AND v.source_type = {$key}";
                    $params[$key] = $this->normalizeSourceTypeFilter($rawValue);
                    break;

                case 'summary_text':
                    $sql .= " AND v.summary_text LIKE {$likeKey}";
                    $params[$likeKey] = "%{$rawValue}%";
                    break;

                case 'account_label':
                    $sql .= " AND voucher_line_accounts.account_label LIKE {$likeKey}";
                    $params[$likeKey] = "%{$rawValue}%";
                    break;

                case 'source_id':
                    $sql .= " AND v.source_id LIKE {$likeKey}";
                    $params[$likeKey] = "%{$rawValue}%";
                    break;

                case 'debit_total':
                    $sql .= " AND voucher_line_accounts.debit_total LIKE {$likeKey}";
                    $params[$likeKey] = "%{$rawValue}%";
                    break;

                case 'credit_total':
                    $sql .= " AND voucher_line_accounts.credit_total LIKE {$likeKey}";
                    $params[$likeKey] = "%{$rawValue}%";
                    break;

                case 'payment_total':
                    $sql .= " AND voucher_payments.payment_total LIKE {$likeKey}";
                    $params[$likeKey] = "%{$rawValue}%";
                    break;

                case 'line_count':
                    $sql .= " AND voucher_line_accounts.line_count LIKE {$likeKey}";
                    $params[$likeKey] = "%{$rawValue}%";
                    break;

                case 'payment_count':
                    $sql .= " AND voucher_payments.payment_count LIKE {$likeKey}";
                    $params[$likeKey] = "%{$rawValue}%";
                    break;

                case 'linked':
                case 'linked_status':
                    if ($this->normalizeLinkedFilter($rawValue) === 'linked') {
                        $sql .= " AND transaction_links.voucher_id IS NOT NULL";
                    } else {
                        $sql .= " AND transaction_links.voucher_id IS NULL";
                    }
                    break;

                case 'created_at':
                case 'updated_at':
                    $sql .= " AND v.{$field} LIKE {$likeKey}";
                    $params[$likeKey] = "%{$rawValue}%";
                    break;
            }
        }

        $sql .= " ORDER BY v.sort_no ASC, v.voucher_date ASC, v.created_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function normalizeStatusFilter(string $value): string
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');

        return match ($normalized) {
            '임시', '임시저장', 'draft' => 'draft',
            '확정', 'confirmed' => 'confirmed',
            'posted' => 'posted',
            '마감', 'closed' => 'closed',
            '삭제', 'deleted' => 'deleted',
            default => $value,
        };
    }

    private function normalizeSourceTypeFilter(string $value): string
    {
        $normalized = mb_strtoupper(trim($value), 'UTF-8');

        return match ($normalized) {
            '홈택스', 'TAX' => 'TAX',
            '카드사', '카드', 'CARD' => 'CARD',
            '은행', 'BANK' => 'BANK',
            '수기입력', '수기', 'MANUAL' => 'MANUAL',
            '거래', 'TRANSACTION' => 'TRANSACTION',
            default => $normalized,
        };
    }

    private function normalizeLinkedFilter(string $value): string
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');

        return in_array($normalized, ['linked', '연결', '연결됨', 'y', 'yes', '1'], true)
            ? 'linked'
            : 'unlinked';
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                v.*,
                TRIM(BOTH ' / ' FROM CONCAT_WS(' / ', cd.dept_name, cp.position_name, ce.employee_name)) AS created_actor_label,
                TRIM(BOTH ' / ' FROM CONCAT_WS(' / ', ud.dept_name, up.position_name, ue.employee_name)) AS updated_actor_label
            FROM {$this->table} v
            LEFT JOIN user_employees ce
                ON v.created_by NOT LIKE 'SYSTEM:%'
               AND ce.user_id = REPLACE(v.created_by, 'USER:', '')
            LEFT JOIN user_departments cd
                ON ce.department_id = cd.id
            LEFT JOIN user_positions cp
                ON ce.position_id = cp.id
            LEFT JOIN user_employees ue
                ON v.updated_by NOT LIKE 'SYSTEM:%'
               AND ue.user_id = REPLACE(v.updated_by, 'USER:', '')
            LEFT JOIN user_departments ud
                ON ue.department_id = ud.id
            LEFT JOIN user_positions up
                ON ue.position_id = up.id
            WHERE v.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function searchSummaryTexts(string $keyword, int $limit = 10): array
    {
        $limit = max(1, min($limit, 20));
        $stmt = $this->db->prepare("
            SELECT
                TRIM(summary_text) AS summary_text,
                COUNT(*) AS used_count,
                MAX(created_at) AS last_used_at
            FROM {$this->table}
            WHERE deleted_at IS NULL
              AND summary_text IS NOT NULL
              AND TRIM(summary_text) <> ''
              AND summary_text LIKE :keyword
            GROUP BY TRIM(summary_text)
            ORDER BY used_count DESC, last_used_at DESC
            LIMIT {$limit}
        ");
        $stmt->execute([
            ':keyword' => '%' . $keyword . '%',
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function insert(array $data): bool
    {
        $allowed = [
            'id',
            'sort_no',
            'voucher_no',
            'voucher_date',
            'source_type',
            'source_id',
            'status',
            'voucher_amount',
            'summary_text',
            'note',
            'memo',
            'reject_reason',
            'is_reversal',
            'reversal_of',
            'need_transaction',
            'created_at',
            'created_by',
            'updated_at',
            'updated_by',
            'deleted_at',
            'deleted_by',
        ];

        $payload = $this->filterData($data, $allowed);

        if (!isset($payload['id'])) {
            return false;
        }

        $columns = array_keys($payload);
        $placeholders = array_map(static fn(string $column) => ':' . $column, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->db->prepare($sql);

        return $stmt->execute($this->bindParams($payload));
    }

    public function update(string $id, array $data): bool
    {
        $allowed = [
            'voucher_date',
            'voucher_no',
            'source_type',
            'source_id',
            'status',
            'voucher_amount',
            'summary_text',
            'note',
            'memo',
            'reject_reason',
            'is_reversal',
            'reversal_of',
            'need_transaction',
            'updated_at',
            'updated_by',
            'deleted_at',
            'deleted_by',
        ];

        $payload = $this->filterData($data, $allowed);

        if ($payload === []) {
            return false;
        }

        $set = [];
        foreach (array_keys($payload) as $column) {
            $set[] = "{$column} = :{$column}";
        }

        $sql = "
            UPDATE {$this->table}
            SET " . implode(', ', $set) . "
            WHERE id = :id
        ";

        $params = $this->bindParams($payload);
        $params[':id'] = $id;

        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    public function softDelete(string $id, ?string $actor = null): bool
    {
        $sql = "
            UPDATE {$this->table}
            SET status = 'deleted',
                deleted_at = NOW(),
                deleted_by = :deleted_by
            WHERE id = :id
              AND deleted_at IS NULL
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id' => $id,
            ':deleted_by' => $actor,
        ]);
    }

    public function restore(string $id, ?string $actor = null): bool
    {
        $sql = "
            UPDATE {$this->table}
            SET status = 'draft',
                deleted_at = NULL,
                deleted_by = NULL,
                updated_by = :updated_by
            WHERE id = :id
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id' => $id,
            ':updated_by' => $actor,
        ]);
    }

    public function hardDelete(string $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM {$this->table}
            WHERE id = :id
        ");

        return $stmt->execute([':id' => $id]);
    }

    public function updateSortNo(string $id, string|int $newSortNo): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET sort_no = :sort_no
            WHERE id = :id
        ");

        return $stmt->execute([
            ':sort_no' => (int) $newSortNo,
            ':id' => $id,
        ]);
    }

    public function create(array $data): bool
    {
        return $this->insert($data);
    }

    public function findActiveReversalOf(string $voucherId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM {$this->table}
            WHERE reversal_of = :voucher_id
              AND is_reversal = 1
              AND deleted_at IS NULL
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([':voucher_id' => $voucherId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function purge(string $id): bool
    {
        return $this->hardDelete($id);
    }

    private function filterData(array $data, array $allowed): array
    {
        $payload = [];

        foreach ($allowed as $column) {
            if (array_key_exists($column, $data)) {
                $payload[$column] = $data[$column];
            }
        }

        return $payload;
    }

    private function bindParams(array $data): array
    {
        $params = [];

        foreach ($data as $column => $value) {
            $params[':' . $column] = $value;
        }

        return $params;
    }
}
