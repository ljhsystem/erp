<?php

namespace App\Models\Ledger;

use App\Services\Ledger\VoucherStatus;
use Core\Helpers\ActorHelper;
use PDO;

final class VoucherReviewQueryModel
{
    private const ORDER_COLUMNS = [
        'sort_no' => 'v.sort_no',
        'voucher_no' => 'v.voucher_no',
        'voucher_date' => 'v.voucher_date',
        'status' => 'v.status',
        'summary' => 'v.summary',
        'summary_text' => 'v.summary',
        'debit_total' => 'v.debit_total',
        'credit_total' => 'v.credit_total',
        'line_count' => 'v.line_count',
        'created_at' => 'v.created_at',
        'updated_at' => 'v.updated_at',
    ];

    public function __construct(private PDO $db)
    {
    }

    public function getPage(array $request, array $filters): array
    {
        $start = max(0, (int) ($request['start'] ?? 0));
        $length = max(10, min(100, (int) ($request['length'] ?? 100)));
        $statuses = $this->reviewStatuses($filters);
        [$scopeWhere, $scopeParams] = $this->scopeWhere($statuses);

        $recordsTotal = $this->countRows($scopeWhere, $scopeParams);
        [$filterWhere, $filterParams, $requiresSearchJoins] = $this->filterWhere($filters, $request);
        $recordsFiltered = $filterWhere === ''
            ? $recordsTotal
            : $this->countRows(
                $scopeWhere . $filterWhere,
                $scopeParams + $filterParams,
                $requiresSearchJoins
            );

        $rows = $this->fetchRows(
            $scopeWhere . $filterWhere,
            $scopeParams + $filterParams,
            $this->orderBy($request),
            $start,
            $length
        );

        return [
            'records_total' => $recordsTotal,
            'records_filtered' => $recordsFiltered,
            'rows' => ActorHelper::enrichActorNames($rows, [
                'created_by_name' => 'created_by',
                'updated_by_name' => 'updated_by',
            ]),
        ];
    }

    private function reviewStatuses(array $filters): array
    {
        $allowed = VoucherStatus::reviewListValues();
        $statuses = array_values(array_filter(array_map(
            static fn(mixed $value): string => (string) VoucherStatus::normalize($value, ''),
            (array) ($filters['statuses'] ?? $allowed)
        ), static fn(string $value): bool => in_array($value, $allowed, true)));

        return $statuses === [] ? $allowed : array_values(array_unique($statuses));
    }

    private function scopeWhere(array $statuses): array
    {
        $params = [];
        $placeholders = [];
        foreach ($statuses as $index => $status) {
            $key = ':scope_status_' . $index;
            $placeholders[] = $key;
            $params[$key] = $status;
        }

        return [
            ' WHERE v.deleted_at IS NULL AND v.status IN (' . implode(', ', $placeholders) . ')',
            $params,
        ];
    }

    private function filterWhere(array $filters, array $request): array
    {
        $where = '';
        $params = [];
        $requiresSearchJoins = false;
        $normalized = $this->normalizeFilters($filters);
        $globalKeyword = trim((string) ($request['search']['value'] ?? ''));
        if ($globalKeyword !== '' && empty($normalized['keyword'])) {
            $normalized['keyword'] = $globalKeyword;
        }

        $status = (string) VoucherStatus::normalize($normalized['status'] ?? '', '');
        if ($status !== '' && in_array($status, VoucherStatus::reviewListValues(), true)) {
            $where .= ' AND v.status = :filter_status';
            $params[':filter_status'] = $status;
        }

        $dateColumn = ($normalized['date_field'] ?? '') === 'updated_at' ? 'v.updated_at' : 'v.voucher_date';
        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $operator) {
            $value = trim((string) ($normalized[$key] ?? ''));
            if ($value !== '') {
                $where .= " AND {$dateColumn} {$operator} :{$key}";
                $params[':' . $key] = $value;
            }
        }

        $keyword = trim((string) ($normalized['keyword'] ?? ''));
        if ($keyword !== '') {
            $requiresSearchJoins = true;
            $where .= " AND (
                v.voucher_no LIKE :keyword_voucher_no
                OR COALESCE(v.summary, '') LIKE :keyword_summary
                OR COALESCE(v.summary_line_summary, '') LIKE :keyword_line_summary
                OR COALESCE(a.account_name, '') LIKE :keyword_account
                OR COALESCE(c.client_name, '') LIKE :keyword_client
                OR COALESCE(p.project_name, '') LIKE :keyword_project
            )";
            foreach ([
                ':keyword_voucher_no',
                ':keyword_summary',
                ':keyword_line_summary',
                ':keyword_account',
                ':keyword_client',
                ':keyword_project',
            ] as $key) {
                $params[$key] = '%' . $keyword . '%';
            }
        }

        return [$where, $params, $requiresSearchJoins];
    }

    private function normalizeFilters(array $filters): array
    {
        $result = $filters;
        foreach ($filters as $filter) {
            if (!is_array($filter)) {
                continue;
            }
            $field = trim((string) ($filter['field'] ?? ''));
            $value = $filter['value'] ?? '';
            if ($field === '' || $value === '' || $value === []) {
                continue;
            }
            if (is_array($value)) {
                if (in_array($field, ['voucher_date', 'updated_at'], true)) {
                    $result['date_field'] = $field;
                    $result['date_from'] = trim((string) ($value['start'] ?? ''));
                    $result['date_to'] = trim((string) ($value['end'] ?? ''));
                }
                continue;
            }
            if ($field === 'voucher_date') {
                $result['date_from'] = trim((string) $value);
                $result['date_to'] = trim((string) $value);
            } elseif (in_array($field, ['status', 'keyword'], true)) {
                $result[$field] = trim((string) $value);
            } elseif (in_array($field, ['voucher_no', 'summary_text'], true)) {
                $result['keyword'] = trim((string) $value);
            }
        }

        return $result;
    }

    private function countRows(string $where, array $params, bool $withSearchJoins = false): int
    {
        $joins = $withSearchJoins ? $this->summaryJoins() : '';
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM ledger_vouchers v {$joins} {$where}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function fetchRows(
        string $where,
        array $params,
        string $orderBy,
        int $start,
        int $length
    ): array {
        $sql = "
            SELECT
                v.*,
                v.summary AS summary_text,
                COALESCE(a.account_name, '') AS summary_account_name,
                COALESCE(c.client_name, '') AS summary_client_name,
                COALESCE(p.project_name, '') AS summary_project_name,
                COALESCE(b.account_name, '') AS summary_bank_account_name,
                COALESCE(cd.card_name, '') AS summary_card_name,
                COALESCE(e.employee_name, '') AS summary_employee_name,
                COALESCE(ev.evidence_count, 0) AS evidence_count,
                rv.id AS reversal_voucher_id,
                rv.voucher_no AS reversal_voucher_no,
                ov.voucher_no AS original_voucher_no
            FROM ledger_vouchers v
            {$this->summaryJoins()}
            LEFT JOIN system_bank_accounts b
                ON b.id = v.summary_bank_account_id AND b.deleted_at IS NULL
            LEFT JOIN system_cards cd
                ON cd.id = v.summary_card_id AND cd.deleted_at IS NULL
            LEFT JOIN user_employees e
                ON e.id = v.summary_employee_id
            LEFT JOIN (
                SELECT target_id, COUNT(*) AS evidence_count
                FROM ledger_evidence_links
                WHERE target_type = 'VOUCHER' AND deleted_at IS NULL
                GROUP BY target_id
            ) ev ON ev.target_id = v.id
            LEFT JOIN ledger_vouchers rv
                ON rv.reversal_of = v.id
               AND rv.is_reversal = 1
               AND rv.deleted_at IS NULL
            LEFT JOIN ledger_vouchers ov
                ON ov.id = v.reversal_of
            {$where}
            {$orderBy}
            LIMIT :page_length OFFSET :page_start
        ";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':page_length', $length, PDO::PARAM_INT);
        $stmt->bindValue(':page_start', $start, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function summaryJoins(): string
    {
        return "
            LEFT JOIN ledger_accounts a
                ON a.id = v.summary_account_id AND a.deleted_at IS NULL
            LEFT JOIN system_clients c
                ON c.id = v.summary_client_id AND c.deleted_at IS NULL
            LEFT JOIN system_projects p
                ON p.id = v.summary_project_id AND p.deleted_at IS NULL
        ";
    }

    private function orderBy(array $request): string
    {
        $order = is_array($request['order'] ?? null) ? ($request['order'][0] ?? []) : [];
        $columns = is_array($request['columns'] ?? null) ? $request['columns'] : [];
        $index = filter_var($order['column'] ?? null, FILTER_VALIDATE_INT);
        $field = $index !== false && isset($columns[$index])
            ? trim((string) ($columns[$index]['data'] ?? ''))
            : '';
        $column = self::ORDER_COLUMNS[$field] ?? 'v.voucher_date';
        $direction = strtolower((string) ($order['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        return "ORDER BY {$column} {$direction}, v.created_at DESC, v.id DESC";
    }
}
