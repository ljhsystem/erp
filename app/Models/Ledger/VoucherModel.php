<?php

namespace App\Models\Ledger;

use App\Services\Ledger\VoucherStatus;
use Core\Helpers\ActorHelper;
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
        $hasSeedRows = $this->hasTable('ledger_data_evidences');
        $hasEvidenceLinks = $this->hasTable('ledger_evidence_links');
        $hasEvidenceTransactionId = $hasSeedRows && $this->tableColumnExists('ledger_data_evidences', 'transaction_id');
        $hasEvidenceFormat = false;
        $seedSources = [];
        $seedIds = [];
        if ($hasEvidenceLinks) {
            $seedSources[] = 'evidence_linked_seed.source_type';
            $seedIds[] = 'evidence_linked_seed.evidence_id';
        }
        if ($hasEvidenceTransactionId) {
            $seedSources[] = 'linked_seed.source_type';
            $seedIds[] = 'linked_seed.evidence_id';
        }
        $importTypeExpr = $hasSeedRows && $seedSources !== []
            ? "COALESCE(" . implode(', ', $seedSources) . ")"
            : 'NULL';
        $evidenceIdExpr = $hasSeedRows && $seedIds !== []
            ? "COALESCE(" . implode(', ', $seedIds) . ")"
            : 'NULL';
        $evidenceLinkJoinSql = ($hasSeedRows && $hasEvidenceLinks) ? "
            LEFT JOIN (
                SELECT
                    el.target_id AS voucher_id,
                    MIN(e.id) AS evidence_id,
                    MIN(e.source_type) AS source_type
                FROM ledger_evidence_links el
                INNER JOIN ledger_data_evidences e
                    ON e.id = el.evidence_id
                   AND e.deleted_at IS NULL
                WHERE el.deleted_at IS NULL
                  AND el.target_type = 'VOUCHER'
                  AND el.target_id IS NOT NULL
                GROUP BY el.target_id
            ) evidence_linked_seed
                ON evidence_linked_seed.voucher_id = v.id
        " : "";
        $transactionSeedJoinSql = ($hasSeedRows && $hasEvidenceTransactionId) ? "
            LEFT JOIN (
                SELECT
                    l.voucher_id,
                    MIN(sr.id) AS evidence_id,
                    MIN(sr.source_type) AS source_type
                FROM ledger_transaction_links l
                INNER JOIN ledger_data_evidences sr
                    ON sr.transaction_id = l.transaction_id
                   AND sr.deleted_at IS NULL
                WHERE l.deleted_at IS NULL
                  AND l.is_active = 1
                GROUP BY l.voucher_id
            ) linked_seed
                ON linked_seed.voucher_id = v.id
        " : "";
        $seedJoinSql = $hasSeedRows ? "
            {$evidenceLinkJoinSql}
            {$transactionSeedJoinSql}
        " : "";
        $formatJoinSql = "";
        $formatNameSelect = "NULL";
        $evidenceBundleJoinSql = ($hasSeedRows && $hasEvidenceLinks) ? "
            LEFT JOIN (
                SELECT
                    el.target_id AS voucher_id,
                    COUNT(DISTINCT e.id) AS evidence_count,
                    GROUP_CONCAT(DISTINCT e.source_type ORDER BY e.source_type SEPARATOR ',') AS evidence_source_types,
                    {$formatNameSelect} AS evidence_format_names
                FROM ledger_evidence_links el
                INNER JOIN ledger_data_evidences e
                    ON e.id = el.evidence_id
                   AND e.deleted_at IS NULL
                {$formatJoinSql}
                WHERE el.deleted_at IS NULL
                  AND el.target_type = 'VOUCHER'
                  AND el.target_id IS NOT NULL
                GROUP BY el.target_id
            ) evidence_bundle
                ON evidence_bundle.voucher_id = v.id
        " : "";
        $evidenceCountExpr = ($hasSeedRows && $hasEvidenceLinks)
            ? "COALESCE(evidence_bundle.evidence_count, CASE WHEN {$evidenceIdExpr} IS NULL THEN 0 ELSE 1 END)"
            : "CASE WHEN {$evidenceIdExpr} IS NULL THEN 0 ELSE 1 END";
        $evidenceSourceTypesExpr = ($hasSeedRows && $hasEvidenceLinks)
            ? "evidence_bundle.evidence_source_types"
            : "NULL";
        $evidenceFormatNamesExpr = ($hasSeedRows && $hasEvidenceLinks)
            ? "evidence_bundle.evidence_format_names"
            : "NULL";

        $sql = "
            SELECT
                v.*,
                v.voucher_no AS voucher_no,
                'VOUCHER' AS type,
                CASE
                    WHEN v.status IN ('POSTED', 'CLOSED') THEN 'POSTED'
                    WHEN COALESCE(v.line_count, 0) = 0 THEN 'EMPTY'
                    WHEN COALESCE(v.debit_total, 0) <= 0
                      OR COALESCE(v.debit_total, 0) <> COALESCE(v.credit_total, 0) THEN 'UNBALANCED'
                    ELSE 'READY'
                END AS journal_status,
                COALESCE(voucher_line_accounts.account_label, '') AS account_label,
                COALESCE(v.debit_total, 0) AS debit_total,
                COALESCE(v.credit_total, 0) AS credit_total,
                COALESCE(v.line_count, 0) AS line_count,
                transaction_links.transaction_id AS transaction_id,
                transaction_links.match_status,
                {$importTypeExpr} AS import_type,
                {$evidenceIdExpr} AS evidence_id,
                {$evidenceCountExpr} AS evidence_count,
                {$evidenceSourceTypesExpr} AS evidence_source_types,
                {$evidenceFormatNamesExpr} AS evidence_format_names,
                CASE
                    WHEN {$evidenceIdExpr} IS NULL THEN 'unlinked'
                    ELSE 'linked'
                END AS evidence_link_status,
                COALESCE(summary_client.client_name, '') AS client_name,
                COALESCE(voucher_line_accounts.account_name, '') AS summary_account_name,
                COALESCE(summary_client.client_name, '') AS summary_client_name,
                COALESCE(summary_project.project_name, '') AS summary_project_name,
                COALESCE(summary_bank_account.account_name, '') AS summary_bank_account_name,
                COALESCE(summary_card.card_name, '') AS summary_card_name,
                COALESCE(summary_employee.employee_name, '') AS summary_employee_name,
                reversal_vouchers.id AS reversal_voucher_id,
                reversal_vouchers.voucher_no AS reversal_voucher_no,
                original_vouchers.voucher_no AS original_voucher_no,
                CASE
                    WHEN transaction_links.transaction_id IS NULL THEN 'unlinked'
                    ELSE 'linked'
                END AS linked_status
            FROM {$this->table} v
            {$evidenceBundleJoinSql}
            LEFT JOIN (
                SELECT
                    hv.id AS voucher_id,
                    COALESCE(NULLIF(CONCAT(a.account_code, ' ', a.account_name), ' '), '') AS account_label,
                    COALESCE(a.account_name, '') AS account_name
                FROM {$this->table} hv
                LEFT JOIN ledger_accounts a
                    ON a.id = hv.summary_account_id
                   AND a.deleted_at IS NULL
                WHERE hv.deleted_at IS NULL
            ) voucher_line_accounts
                ON voucher_line_accounts.voucher_id = v.id
            LEFT JOIN system_clients summary_client
                ON summary_client.id = v.summary_client_id
               AND summary_client.deleted_at IS NULL
            LEFT JOIN system_projects summary_project
                ON summary_project.id = v.summary_project_id
               AND summary_project.deleted_at IS NULL
            LEFT JOIN system_bank_accounts summary_bank_account
                ON summary_bank_account.id = v.summary_bank_account_id
               AND summary_bank_account.deleted_at IS NULL
            LEFT JOIN system_cards summary_card
                ON summary_card.id = v.summary_card_id
               AND summary_card.deleted_at IS NULL
            LEFT JOIN user_employees summary_employee
                ON summary_employee.id = v.summary_employee_id
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
            {$seedJoinSql}
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
            $statuses = array_values(array_filter(
                array_map(
                    static fn(mixed $status): string => (string) VoucherStatus::normalize($status, ''),
                    (array) ($filters['statuses'] ?? [])
                ),
                static fn(string $status): bool => $status !== ''
            ));
            if ($statuses !== []) {
                $statusPlaceholders = [];
                foreach ($statuses as $index => $status) {
                    $placeholder = ":list_status_{$index}";
                    $statusPlaceholders[] = $placeholder;
                    $params[$placeholder] = $status;
                }
                $sql .= ' AND v.status IN (' . implode(', ', $statusPlaceholders) . ')';
            }

            if (!empty($filters['status'])) {
                $sql .= " AND v.status = :status";
                $params[':status'] = $filters['status'];
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
                    v.voucher_no LIKE :keyword_voucher_no
                    OR v.summary LIKE :keyword_summary
                    OR COALESCE(summary_client.client_name, '') LIKE :keyword_client_name
                    OR COALESCE(summary_project.project_name, '') LIKE :keyword_project_name
                    OR COALESCE(voucher_line_accounts.account_label, '') LIKE :keyword_account_label
                    OR COALESCE(v.summary_line_summary, '') LIKE :keyword_line_summary
                )";
                $keyword = '%' . $filters['keyword'] . '%';
                $params[':keyword_voucher_no'] = $keyword;
                $params[':keyword_summary'] = $keyword;
                $params[':keyword_client_name'] = $keyword;
                $params[':keyword_project_name'] = $keyword;
                $params[':keyword_account_label'] = $keyword;
                $params[':keyword_line_summary'] = $keyword;
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

                case 'journal_status':
                    $sql .= " AND (CASE
                        WHEN v.status IN ('POSTED', 'CLOSED') THEN 'POSTED'
                        WHEN COALESCE(v.line_count, 0) = 0 THEN 'EMPTY'
                        WHEN COALESCE(v.debit_total, 0) <= 0
                          OR COALESCE(v.debit_total, 0) <> COALESCE(v.credit_total, 0) THEN 'UNBALANCED'
                        ELSE 'READY'
                    END) = {$key}";
                    $params[$key] = $this->normalizeJournalStatusFilter($rawValue);
                    break;

                case 'type':
                    $sql .= " AND 'VOUCHER' = {$key}";
                    $params[$key] = strtoupper($rawValue);
                    break;

                case 'summary':
                case 'summary':
                case 'summary_text':
                    $sql .= " AND v.summary LIKE {$likeKey}";
                    $params[$likeKey] = "%{$rawValue}%";
                    break;

                case 'account_label':
                    $sql .= " AND voucher_line_accounts.account_label LIKE {$likeKey}";
                    $params[$likeKey] = "%{$rawValue}%";
                    break;

                case 'debit_total':
                case 'credit_total':
                    $sql .= " AND CAST(COALESCE(v.{$field}, 0) AS CHAR) LIKE {$likeKey}";
                    $params[$likeKey] = "%{$rawValue}%";
                    break;

                case 'line_count':
                    $sql .= " AND CAST(COALESCE(v.line_count, 0) AS CHAR) LIKE {$likeKey}";
                    $params[$likeKey] = "%{$rawValue}%";
                    break;

                case 'summary_line_summary':
                    $sql .= " AND COALESCE(v.summary_line_summary, '') LIKE {$likeKey}";
                    $params[$likeKey] = "%{$rawValue}%";
                    break;

                case 'summary_account_id':
                    $sql .= " AND (
                        COALESCE(v.summary_account_id, '') LIKE {$likeKey}
                        OR COALESCE(voucher_line_accounts.account_label, '') LIKE {$likeKey}
                        OR COALESCE(voucher_line_accounts.account_name, '') LIKE {$likeKey}
                    )";
                    $params[$likeKey] = "%{$rawValue}%";
                    break;

                case 'summary_client_id':
                    $sql .= " AND (
                        COALESCE(v.summary_client_id, '') LIKE {$likeKey}
                        OR COALESCE(summary_client.client_name, '') LIKE {$likeKey}
                    )";
                    $params[$likeKey] = "%{$rawValue}%";
                    break;

                case 'summary_project_id':
                    $sql .= " AND (
                        COALESCE(v.summary_project_id, '') LIKE {$likeKey}
                        OR COALESCE(summary_project.project_name, '') LIKE {$likeKey}
                    )";
                    $params[$likeKey] = "%{$rawValue}%";
                    break;

                case 'summary_bank_account_id':
                    $sql .= " AND (
                        COALESCE(v.summary_bank_account_id, '') LIKE {$likeKey}
                        OR COALESCE(summary_bank_account.account_name, '') LIKE {$likeKey}
                    )";
                    $params[$likeKey] = "%{$rawValue}%";
                    break;

                case 'summary_card_id':
                    $sql .= " AND (
                        COALESCE(v.summary_card_id, '') LIKE {$likeKey}
                        OR COALESCE(summary_card.card_name, '') LIKE {$likeKey}
                    )";
                    $params[$likeKey] = "%{$rawValue}%";
                    break;

                case 'summary_employee_id':
                    $sql .= " AND (
                        COALESCE(v.summary_employee_id, '') LIKE {$likeKey}
                        OR COALESCE(summary_employee.employee_name, '') LIKE {$likeKey}
                    )";
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

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return array_map([$this, 'normalizeVoucherRow'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        } catch (\Throwable $e) {
            error_log('[VoucherModel] getList failed: ' . $e->getMessage());
            return $this->getListFallback();
        }
    }

    private function getListFallback(): array
    {
        $stmt = $this->db->query("
            SELECT
                v.*,
                v.voucher_no AS voucher_no,
                'VOUCHER' AS type,
                CASE
                    WHEN v.status IN ('POSTED', 'CLOSED') THEN 'POSTED'
                    WHEN COALESCE(v.line_count, 0) = 0 THEN 'EMPTY'
                    WHEN COALESCE(v.debit_total, 0) <= 0
                      OR COALESCE(v.debit_total, 0) <> COALESCE(v.credit_total, 0) THEN 'UNBALANCED'
                    ELSE 'READY'
                END AS journal_status,
                COALESCE(voucher_line_accounts.account_label, '') AS account_label,
                COALESCE(v.debit_total, 0) AS debit_total,
                COALESCE(v.credit_total, 0) AS credit_total,
                COALESCE(v.line_count, 0) AS line_count,
                transaction_links.transaction_id AS transaction_id,
                transaction_links.match_status AS match_status,
                NULL AS import_type,
                COALESCE(summary_client.client_name, '') AS client_name,
                COALESCE(voucher_line_accounts.account_name, '') AS summary_account_name,
                COALESCE(summary_client.client_name, '') AS summary_client_name,
                COALESCE(summary_project.project_name, '') AS summary_project_name,
                COALESCE(summary_bank_account.account_name, '') AS summary_bank_account_name,
                COALESCE(summary_card.card_name, '') AS summary_card_name,
                COALESCE(summary_employee.employee_name, '') AS summary_employee_name,
                NULL AS reversal_voucher_id,
                NULL AS reversal_voucher_no,
                NULL AS original_voucher_no,
                CASE
                    WHEN transaction_links.transaction_id IS NULL THEN 'unlinked'
                    ELSE 'linked'
                END AS linked_status
            FROM {$this->table} v
            LEFT JOIN (
                SELECT
                    hv.id AS voucher_id,
                    COALESCE(NULLIF(CONCAT(a.account_code, ' ', a.account_name), ' '), '') AS account_label,
                    COALESCE(a.account_name, '') AS account_name
                FROM {$this->table} hv
                LEFT JOIN ledger_accounts a
                    ON a.id = hv.summary_account_id
                   AND a.deleted_at IS NULL
                WHERE hv.deleted_at IS NULL
            ) voucher_line_accounts
                ON voucher_line_accounts.voucher_id = v.id
            LEFT JOIN system_clients summary_client
                ON summary_client.id = v.summary_client_id
               AND summary_client.deleted_at IS NULL
            LEFT JOIN system_projects summary_project
                ON summary_project.id = v.summary_project_id
               AND summary_project.deleted_at IS NULL
            LEFT JOIN system_bank_accounts summary_bank_account
                ON summary_bank_account.id = v.summary_bank_account_id
               AND summary_bank_account.deleted_at IS NULL
            LEFT JOIN system_cards summary_card
                ON summary_card.id = v.summary_card_id
               AND summary_card.deleted_at IS NULL
            LEFT JOIN user_employees summary_employee
                ON summary_employee.id = v.summary_employee_id
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
            WHERE v.deleted_at IS NULL
            ORDER BY v.sort_no ASC, v.voucher_date ASC, v.created_at ASC
        ");

        return array_map([$this, 'normalizeVoucherRow'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function normalizeStatusFilter(string $value): string
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');

        return match ($normalized) {
            '임시', '임시저장', 'draft' => VoucherStatus::DRAFT,
            '검토요청', 'review_requested', 'review-requested' => VoucherStatus::REVIEW_REQUESTED,
            '검토완료', 'reviewed' => VoucherStatus::REVIEWED,
            '승인', 'posted' => VoucherStatus::POSTED,
            '마감', 'closed' => VoucherStatus::CLOSED,
            '삭제', 'deleted' => VoucherStatus::DELETED,
            default => VoucherStatus::normalize($value, (string) $value),
        };
    }

    private function normalizeJournalStatusFilter(string $value): string
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');

        return match ($normalized) {
            '분개없음', '미분개', 'empty' => 'EMPTY',
            '차대불일치', '불일치', 'unbalanced' => 'UNBALANCED',
            '분개완료', 'ready' => 'READY',
            '승인완료', 'posted' => 'POSTED',
            default => strtoupper($value),
        };
    }

    private function normalizeSourceTypeFilter(string $value): string
    {
        $normalized = mb_strtoupper(trim($value), 'UTF-8');

        return match ($normalized) {
            '홈택스', 'HOMETAX', 'TAX' => 'TAX',
            '카드사', 'CARD_COMPANY', '카드', 'CARD' => 'CARD',
            '은행', 'BANK' => 'BANK',
            '쇼핑몰', 'SHOPPING' => 'SHOPPING',
            '수입', '무역', '수입/무역', 'TRADE', 'IMPORT' => 'TRADE',
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

    public function findActiveEvidenceIds(array $evidenceIds): array
    {
        $evidenceIds = array_values(array_filter(array_unique(array_map(
            static fn(mixed $id): string => trim((string) $id),
            $evidenceIds
        ))));
        if ($evidenceIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($evidenceIds as $index => $evidenceId) {
            $key = ':evidence_id_' . $index;
            $placeholders[] = $key;
            $params[$key] = $evidenceId;
        }

        $stmt = $this->db->prepare("
            SELECT DISTINCT l.evidence_id
            FROM ledger_evidence_links l
            INNER JOIN ledger_vouchers v
                ON v.id = l.target_id
               AND v.deleted_at IS NULL
            WHERE l.deleted_at IS NULL
              AND l.target_type = 'VOUCHER'
              AND l.evidence_id IN (" . implode(', ', $placeholders) . ")
        ");
        $stmt->execute($params);

        return array_values(array_filter(array_map(
            static fn(array $row): string => trim((string) ($row['evidence_id'] ?? '')),
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        )));
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                v.*
            FROM {$this->table} v
            WHERE v.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row = ActorHelper::enrichActorNamesRow($row, [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
            'deleted_by_name' => 'deleted_by',
        ]);

        return $this->normalizeVoucherRow($row);
    }

    public function getByIdForUpdate(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT v.*
            FROM {$this->table} v
            WHERE v.id = :id
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->normalizeVoucherRow($row) : null;
    }

    public function getByVoucherNo(string $voucherNo): ?array
    {
        $stmt = $this->db->prepare("
            SELECT v.*
            FROM {$this->table} v
            WHERE v.voucher_no = :voucher_no
            LIMIT 1
        ");
        $stmt->execute([':voucher_no' => $voucherNo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return $this->normalizeVoucherRow(ActorHelper::enrichActorNamesRow($row, [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
            'deleted_by_name' => 'deleted_by',
        ]));
    }

    public function searchSummaryTexts(string $keyword, int $limit = 10): array
    {
        $limit = max(1, min($limit, 20));
        $stmt = $this->db->prepare("
            SELECT
                TRIM(summary) AS summary,
                COUNT(*) AS used_count,
                MAX(created_at) AS last_used_at
            FROM {$this->table}
            WHERE deleted_at IS NULL
              AND summary IS NOT NULL
              AND TRIM(summary) <> ''
              AND summary LIKE :keyword
            GROUP BY TRIM(summary)
            ORDER BY used_count DESC, last_used_at DESC
            LIMIT {$limit}
        ");
        $stmt->execute([
            ':keyword' => '%' . $keyword . '%',
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function nextVoucherNo(string $voucherDate): string
    {
        $dateKey = preg_replace('/[^0-9]/', '', $voucherDate) ?: date('Ymd');
        $prefix = substr($dateKey, 0, 8);

        $stmt = $this->db->prepare("
            SELECT voucher_no
            FROM {$this->table}
            WHERE voucher_no LIKE :prefix
            ORDER BY voucher_no DESC
            LIMIT 1
        ");
        $stmt->execute([':prefix' => $prefix . '-%']);

        $latest = (string) ($stmt->fetchColumn() ?: '');
        $next = 1;
        if (preg_match('/-(\d+)$/', $latest, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%04d', $prefix, $next);
    }

    public function getTrashList(): array
    {
        $stmt = $this->db->query("
            SELECT v.*, v.deleted_by AS deleted_by_name
            FROM {$this->table} v
            WHERE v.deleted_at IS NOT NULL
            ORDER BY v.deleted_at DESC, v.sort_no DESC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(
            fn(array $row): array => ActorHelper::enrichActorNamesRow($row, [
                'deleted_by_name' => 'deleted_by',
            ]),
            $rows
        );
    }

    public function getDeletedIds(): array
    {
        $stmt = $this->db->query("
            SELECT id
            FROM {$this->table}
            WHERE deleted_at IS NOT NULL
        ");

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'id');
    }

    public function searchForPicker(array $filters): array
    {
        $statuses = array_values(array_filter(array_map('strval', (array) ($filters['statuses'] ?? []))));
        if ($statuses === []) {
            return [];
        }

        $params = [];
        $statusPlaceholders = [];
        foreach ($statuses as $index => $status) {
            $key = ':status' . $index;
            $statusPlaceholders[] = $key;
            $params[$key] = $status;
        }

        $sql = "
            SELECT
                v.id,
                v.voucher_no,
                v.voucher_date,
                COALESCE(summary_client.client_name, '') AS client_name,
                COALESCE(v.summary, '') AS summary,
                COALESCE(v.debit_total, 0) AS debit_total,
                COALESCE(v.credit_total, 0) AS credit_total,
                v.status
            FROM {$this->table} v
            LEFT JOIN system_clients summary_client
                ON summary_client.id = v.summary_client_id
               AND summary_client.deleted_at IS NULL
            WHERE v.deleted_at IS NULL
              AND v.status IN (" . implode(', ', $statusPlaceholders) . ")
        ";

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $sql .= "
              AND (
                  v.voucher_no LIKE :keyword
                  OR COALESCE(summary_client.client_name, '') LIKE :keyword
                  OR v.summary LIKE :keyword
              )
            ";
            $params[':keyword'] = '%' . $keyword . '%';
        }

        foreach ([
            'date_from' => 'v.voucher_date >= :date_from',
            'date_to' => 'v.voucher_date <= :date_to',
            'client_id' => "COALESCE(v.summary_client_id, '') = :client_id",
        ] as $key => $clause) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $sql .= " AND {$clause}";
                $params[':' . $key] = $value;
            }
        }

        if (($filters['min_amount'] ?? '') !== '') {
            $sql .= " AND COALESCE(v.debit_total, 0) >= :min_amount";
            $params[':min_amount'] = (float) $filters['min_amount'];
        }
        if (($filters['max_amount'] ?? '') !== '') {
            $sql .= " AND COALESCE(v.debit_total, 0) <= :max_amount";
            $params[':max_amount'] = (float) $filters['max_amount'];
        }

        $sql .= " ORDER BY v.voucher_date DESC, v.voucher_no DESC LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map(static function (array $row): array {
            $row['debit_total'] = (float) ($row['debit_total'] ?? 0);
            $row['credit_total'] = (float) ($row['credit_total'] ?? 0);
            $row['summary_text'] = $row['summary'] ?? ($row['summary_text'] ?? '');
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function normalizeVoucherRow(array $row): array
    {
        if (!array_key_exists('summary_text', $row)) {
            $row['summary_text'] = $row['summary'] ?? null;
        }
        if (array_key_exists('status', $row)) {
            $row['status'] = VoucherStatus::normalize($row['status'], (string) $row['status']);
        }

        return $row;
    }

    public function insert(array $data): bool
    {
        $allowed = [
            'id',
            'sort_no',
            'voucher_no',
            'voucher_date',
            'status',
            'summary',
            'debit_total',
            'credit_total',
            'line_count',
            'summary_account_id',
            'summary_client_id',
            'summary_project_id',
            'summary_bank_account_id',
            'summary_card_id',
            'summary_employee_id',
            'summary_line_summary',
            'reject_reason',
            'is_reversal',
            'reversal_of',
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
            'status',
            'summary',
            'debit_total',
            'credit_total',
            'line_count',
            'summary_account_id',
            'summary_client_id',
            'summary_project_id',
            'summary_bank_account_id',
            'summary_card_id',
            'summary_employee_id',
            'summary_line_summary',
            'reject_reason',
            'is_reversal',
            'reversal_of',
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
        $status = VoucherStatus::DELETED;
        $sql = "
            UPDATE {$this->table}
            SET status = '{$status}',
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
        $status = VoucherStatus::DRAFT;
        $sql = "
            UPDATE {$this->table}
            SET status = '{$status}',
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

    private function hasColumn(string $column): bool
    {
        static $columns = null;

        if ($columns === null) {
            try {
                $stmt = $this->db->query("SHOW COLUMNS FROM {$this->table}");
                $columns = array_flip(array_map(
                    static fn(array $row): string => (string) ($row['Field'] ?? ''),
                    $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
                ));
            } catch (\Throwable) {
                $columns = [];
            }
        }

        return isset($columns[$column]);
    }

    private function hasTable(string $table): bool
    {
        static $tables = [];

        if (!array_key_exists($table, $tables)) {
            try {
                $stmt = $this->db->prepare("
                    SELECT 1
                    FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = :table_name
                    LIMIT 1
                ");
                $stmt->execute([':table_name' => $table]);
                $tables[$table] = (bool) $stmt->fetchColumn();
            } catch (\Throwable) {
                $tables[$table] = false;
            }
        }

        return $tables[$table];
    }

    private function tableColumnExists(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;

        if (!array_key_exists($key, $cache)) {
            try {
                $stmt = $this->db->prepare("
                    SELECT 1
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = :table_name
                      AND COLUMN_NAME = :column_name
                    LIMIT 1
                ");
                $stmt->execute([
                    ':table_name' => $table,
                    ':column_name' => $column,
                ]);
                $cache[$key] = (bool) $stmt->fetchColumn();
            } catch (\Throwable) {
                $cache[$key] = false;
            }
        }

        return $cache[$key];
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
