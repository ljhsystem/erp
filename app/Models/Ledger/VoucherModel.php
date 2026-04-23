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
                v.ref_type AS type,
                COALESCE(voucher_line_accounts.account_code, '') AS account_code,
                CASE
                    WHEN transaction_links.voucher_id IS NULL THEN 'unlinked'
                    ELSE 'linked'
                END AS linked_status
            FROM {$this->table} v
            LEFT JOIN (
                SELECT
                    voucher_id,
                    GROUP_CONCAT(DISTINCT account_code ORDER BY line_no SEPARATOR ', ') AS account_code
                FROM ledger_voucher_lines
                WHERE deleted_at IS NULL
                GROUP BY voucher_id
            ) voucher_line_accounts
                ON voucher_line_accounts.voucher_id = v.id
            LEFT JOIN (
                SELECT voucher_id
                FROM ledger_transaction_links
                WHERE deleted_at IS NULL
                  AND is_active = 1
                GROUP BY voucher_id
            ) transaction_links
                ON transaction_links.voucher_id = v.id
            WHERE v.deleted_at IS NULL
        ";

        $params = [];

        $isFilterList = $filters === [] || array_keys($filters) === range(0, count($filters) - 1);

        if (!$isFilterList) {
            if (!empty($filters['status'])) {
                $sql .= " AND v.status = :status";
                $params[':status'] = $filters['status'];
            }

            if (!empty($filters['ref_type'])) {
                $sql .= " AND v.ref_type = :ref_type";
                $params[':ref_type'] = $filters['ref_type'];
            }

            if (!empty($filters['ref_id'])) {
                $sql .= " AND v.ref_id = :ref_id";
                $params[':ref_id'] = $filters['ref_id'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND v.voucher_date >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND v.voucher_date <= :date_to";
                $params[':date_to'] = $filters['date_to'];
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
                case 'ref_type':
                    $sql .= " AND v.ref_type = {$key}";
                    $params[$key] = $this->normalizeTypeFilter($rawValue);
                    break;

                case 'summary_text':
                    $sql .= " AND v.summary_text LIKE {$likeKey}";
                    $params[$likeKey] = "%{$rawValue}%";
                    break;

                case 'account_code':
                    $sql .= " AND voucher_line_accounts.account_code LIKE {$likeKey}";
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
            '확정', 'posted' => 'posted',
            '마감', 'locked' => 'locked',
            '삭제', 'deleted' => 'deleted',
            default => $value,
        };
    }

    private function normalizeTypeFilter(string $value): string
    {
        $normalized = mb_strtoupper(trim($value), 'UTF-8');

        return match ($normalized) {
            '수동전표' => 'MANUAL',
            '자동전표' => 'AUTO',
            '조정전표' => 'ADJUST',
            '결산전표' => 'CLOSING',
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
            SELECT *
            FROM {$this->table}
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insert(array $data): bool
    {
        $allowed = [
            'id',
            'sort_no',
            'voucher_no',
            'voucher_date',
            'ref_type',
            'ref_id',
            'status',
            'summary_text',
            'note',
            'memo',
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
            'ref_type',
            'ref_id',
            'status',
            'summary_text',
            'note',
            'memo',
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
