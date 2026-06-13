<?php

namespace App\Models\Funds;

use PDO;

class BankTransactionReportModel
{
    private array $columnCache = [];

    public function __construct(private PDO $pdo)
    {
    }

    public function rows(array $filters = []): array
    {
        if (!$this->tableExists('ledger_bank_transactions')) {
            return [];
        }

        [$where, $params] = $this->whereSql($filters, false);
        $payloadTableExists = $this->tableExists('ledger_evidence_payloads');
        $evidenceMappedPayloadSelect = $payloadTableExists && $this->columnExists('ledger_evidence_payloads', 'mapped_payload_json')
            ? 'p.mapped_payload_json'
            : 'NULL';
        $evidencePayloadJoin = $payloadTableExists ? "
            LEFT JOIN ledger_evidence_payloads p
                ON p.evidence_type = e.source_type COLLATE utf8mb4_unicode_ci
               AND p.evidence_id = e.id COLLATE utf8mb4_unicode_ci" : '';
        $clientNameSelect = $this->clientNameSql();
        $sql = "
            SELECT
                b.id,
                b.evidence_id,
                " . $this->selectColumn('b', 'transaction_datetime', "TIMESTAMP(b.transaction_date, COALESCE(b.transaction_time, '00:00:00'))") . " AS transaction_datetime,
                b.transaction_date,
                b.transaction_time,
                b.bank_account_id,
                COALESCE(NULLIF(ba.account_name, ''), b.bank_account_id, '-') AS account_name,
                COALESCE(NULLIF(ba.bank_name, ''), '') AS bank_name,
                COALESCE(NULLIF(ba.account_number, ''), '') AS account_number,
                UPPER(COALESCE(b.transaction_type, '')) AS transaction_type,
                COALESCE(b.deposit_amount, 0) AS deposit_amount,
                COALESCE(b.withdraw_amount, 0) AS withdraw_amount,
                b.balance_amount,
                COALESCE(b.description, '') AS description,
                COALESCE(b.memo, '') AS memo,
                {$clientNameSelect} AS client_name,
                COALESCE(b.counterparty_name, '') AS counterparty_name,
                " . $this->selectColumn('b', 'counterparty_account_number', "''") . " AS counterparty_account_number,
                " . $this->selectColumn('b', 'counterparty_bank_name', "''") . " AS counterparty_bank_name,
                b.bank_reference_no,
                b.currency_code,
                b.created_at AS uploaded_at,
                b.updated_at,
                b.deleted_at AS bank_deleted_at,
                COALESCE(b.deleted_at, e.deleted_at) AS deleted_at,
                e.source_type AS evidence_source_type,
                {$evidenceMappedPayloadSelect} AS evidence_mapped_payload_json,
                e.evidence_status,
                e.voucher_status,
                e.transaction_status,
                e.deleted_at AS evidence_deleted_at,
                COALESCE(vlink.voucher_count, 0) AS voucher_count,
                vlink.voucher_id,
                vlink.voucher_no,
                vlink.voucher_date
            FROM ledger_bank_transactions b
            LEFT JOIN system_bank_accounts ba
                ON ba.id = b.bank_account_id
            LEFT JOIN ledger_data_evidences e
                ON e.id = b.evidence_id
            {$evidencePayloadJoin}
            LEFT JOIN " . $this->voucherLinkSubquery() . " vlink
                ON vlink.evidence_id = b.evidence_id
            WHERE {$where}
            ORDER BY COALESCE(" . $this->selectColumn('b', 'transaction_datetime', 'NULL') . ", TIMESTAMP(b.transaction_date, COALESCE(b.transaction_time, '00:00:00'))) DESC,
                     b.created_at DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $rows = array_map(fn(array $row): array => $this->normalizeRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        usort($rows, static function (array $left, array $right): int {
            $leftSortNo = (float) ($left['_evidence_sort_no'] ?? 0);
            $rightSortNo = (float) ($right['_evidence_sort_no'] ?? 0);
            if ($leftSortNo > 0 || $rightSortNo > 0) {
                if ($leftSortNo <= 0) return 1;
                if ($rightSortNo <= 0) return -1;
                return $leftSortNo <=> $rightSortNo;
            }

            return strcmp((string) ($right['transaction_datetime'] ?? ''), (string) ($left['transaction_datetime'] ?? ''));
        });
        $this->applyCalculatedBalances($rows);
        $this->applyTransactionDateOrderFlags($rows);
        foreach ($rows as $index => &$row) {
            $row['sort_no'] = $index + 1;
            unset($row['_evidence_sort_no']);
        }
        unset($row);

        return $rows;
    }

    public function summary(array $filters = []): array
    {
        if (!$this->tableExists('ledger_bank_transactions')) {
            return $this->emptySummary();
        }

        [$where, $params] = $this->whereSql($filters, false);
        $sql = "
            SELECT
                COALESCE(SUM(COALESCE(b.deposit_amount, 0)), 0) AS deposit_total,
                COALESCE(SUM(COALESCE(b.withdraw_amount, 0)), 0) AS withdraw_total,
                COALESCE(SUM(CASE WHEN COALESCE(vlink.voucher_count, 0) = 0 THEN 1 ELSE 0 END), 0) AS unlinked_count,
                COALESCE(SUM(CASE WHEN COALESCE(vlink.voucher_count, 0) > 0 THEN 1 ELSE 0 END), 0) AS voucher_linked_count
            FROM ledger_bank_transactions b
            LEFT JOIN system_bank_accounts ba
                ON ba.id = b.bank_account_id
            LEFT JOIN ledger_data_evidences e
                ON e.id = b.evidence_id
            LEFT JOIN " . $this->voucherLinkSubquery() . " vlink
                ON vlink.evidence_id = b.evidence_id
            WHERE {$where}
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $summary = array_merge($this->emptySummary(), $summary);
        $summary['ending_balance'] = $this->calculatedEndingBalance($filters);

        return $summary;
    }

    public function find(string $id, bool $includeDeleted = false): ?array
    {
        $filters = [];
        if ($includeDeleted) {
            $filters[] = ['field' => 'deleted_scope', 'value' => 'ALL'];
        }
        $rows = $this->rows($filters);
        foreach ($rows as $row) {
            if ((string) ($row['id'] ?? '') === $id) {
                return $row;
            }
        }

        return null;
    }

    public function hasVoucherLink(string $id): bool
    {
        $row = $this->find($id, true);
        return (int) ($row['voucher_count'] ?? 0) > 0;
    }

    public function softDelete(string $id, string $actor): bool
    {
        $row = $this->find($id, true);
        if (!$row) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                UPDATE ledger_bank_transactions
                SET deleted_at = NOW(),
                    deleted_by = :actor,
                    updated_at = NOW(),
                    updated_by = :actor
                WHERE id = :id
                  AND deleted_at IS NULL
            ");
            $stmt->execute([':id' => $id, ':actor' => $actor]);

            $evidenceId = (string) ($row['evidence_id'] ?? '');
            if ($evidenceId !== '' && $this->tableExists('ledger_data_evidences')) {
                $this->pdo->prepare("
                    UPDATE ledger_data_evidences
                    SET deleted_at = NOW(),
                        deleted_by = :actor,
                        updated_at = NOW(),
                        updated_by = :actor
                    WHERE id = :id
                      AND deleted_at IS NULL
                ")->execute([':id' => $evidenceId, ':actor' => $actor]);
            }

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function restore(string $id, string $actor): bool
    {
        $row = $this->find($id, true);
        if (!$row) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("
                UPDATE ledger_bank_transactions
                SET deleted_at = NULL,
                    deleted_by = NULL,
                    updated_at = NOW(),
                    updated_by = :actor
                WHERE id = :id
            ")->execute([':id' => $id, ':actor' => $actor]);

            $evidenceId = (string) ($row['evidence_id'] ?? '');
            if ($evidenceId !== '' && $this->tableExists('ledger_data_evidences')) {
                $this->pdo->prepare("
                    UPDATE ledger_data_evidences
                    SET deleted_at = NULL,
                        deleted_by = NULL,
                        updated_at = NOW(),
                        updated_by = :actor
                    WHERE id = :id
                ")->execute([':id' => $evidenceId, ':actor' => $actor]);
            }

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function whereSql(array $filters, bool $includeDeleted): array
    {
        $where = [];
        $params = [];
        $deletedScope = $includeDeleted ? 'ALL' : 'ACTIVE';

        foreach ($filters as $index => $filter) {
            $field = (string) ($filter['field'] ?? '');
            $value = $filter['value'] ?? null;
            if ($field === '' || $value === '' || $value === null) {
                continue;
            }

            if ($field === 'deleted_scope') {
                $deletedScope = strtoupper((string) $value);
                continue;
            }

            if (is_array($value) && array_key_exists('start', $value) && array_key_exists('end', $value)) {
                $start = (string) ($value['start'] ?? '');
                $end = (string) ($value['end'] ?? '');
                if ($start === '' || $end === '') {
                    continue;
                }
                $column = match ($field) {
                    'uploaded_at', 'created_at' => 'b.created_at',
                    default => "COALESCE(" . $this->selectColumn('b', 'transaction_datetime', 'NULL') . ", TIMESTAMP(b.transaction_date, COALESCE(b.transaction_time, '00:00:00')))",
                };
                $where[] = "{$column} BETWEEN :start_{$index} AND :end_{$index}";
                $params[":start_{$index}"] = strlen($start) === 10 ? $start . ' 00:00:00' : $start;
                $params[":end_{$index}"] = strlen($end) === 10 ? $end . ' 23:59:59' : $end;
                continue;
            }

            $param = ":filter_{$index}";
            switch ($field) {
                case 'id':
                    $where[] = 'b.id = ' . $param;
                    $params[$param] = (string) $value;
                    break;
                case 'transaction_datetime':
                    $where[] = "COALESCE(" . $this->selectColumn('b', 'transaction_datetime', 'NULL') . ", TIMESTAMP(b.transaction_date, COALESCE(b.transaction_time, '00:00:00'))) LIKE " . $param;
                    $params[$param] = '%' . (string) $value . '%';
                    break;
                case 'bank_account_id':
                case 'account':
                    $where[] = 'b.bank_account_id = ' . $param;
                    $params[$param] = (string) $value;
                    break;
                case 'account_name':
                    $where[] = 'COALESCE(ba.account_name, "") LIKE ' . $param;
                    $params[$param] = '%' . (string) $value . '%';
                    break;
                case 'account_number':
                    $where[] = 'COALESCE(ba.account_number, "") LIKE ' . $param;
                    $params[$param] = $this->filterLikePattern($value);
                    break;
                case 'bank_name':
                    $where[] = 'ba.bank_name LIKE ' . $param;
                    $params[$param] = '%' . (string) $value . '%';
                    break;
                case 'direction':
                case 'transaction_type':
                    $direction = strtoupper((string) $value);
                    if ($direction === 'IN' || $direction === '입금') {
                        $where[] = 'COALESCE(b.deposit_amount, 0) > 0';
                    } elseif ($direction === 'OUT' || $direction === '출금') {
                        $where[] = 'COALESCE(b.withdraw_amount, 0) > 0';
                    } else {
                        $where[] = 'UPPER(COALESCE(b.transaction_type, "")) = ' . $param;
                        $params[$param] = $direction;
                    }
                    break;
                case 'deposit_amount':
                    $amount = $this->filterAmount($value);
                    if ($amount === null) {
                        break;
                    }
                    $where[] = 'COALESCE(b.deposit_amount, 0) = ' . $param;
                    $params[$param] = $amount;
                    break;
                case 'withdraw_amount':
                    $amount = $this->filterAmount($value);
                    if ($amount === null) {
                        break;
                    }
                    $where[] = 'COALESCE(b.withdraw_amount, 0) = ' . $param;
                    $params[$param] = $amount;
                    break;
                case 'client_name':
                    $where[] = $this->clientNameSql() . ' LIKE ' . $param;
                    $params[$param] = '%' . (string) $value . '%';
                    break;
                case 'counterparty_name':
                    $where[] = 'COALESCE(b.counterparty_name, "") LIKE ' . $param;
                    $params[$param] = '%' . (string) $value . '%';
                    break;
                case 'counterparty_account_number':
                    $where[] = 'COALESCE(' . $this->selectColumn('b', 'counterparty_account_number', 'NULL') . ', "") LIKE ' . $param;
                    $params[$param] = $this->filterLikePattern($value);
                    break;
                case 'counterparty_bank_name':
                    $where[] = 'COALESCE(' . $this->selectColumn('b', 'counterparty_bank_name', 'NULL') . ', "") LIKE ' . $param;
                    $params[$param] = '%' . (string) $value . '%';
                    break;
                case 'description':
                case 'memo':
                    $where[] = '(COALESCE(b.description, "") LIKE ' . $param . ' OR COALESCE(b.memo, "") LIKE ' . $param . ')';
                    $params[$param] = '%' . (string) $value . '%';
                    break;
                case 'voucher_link_status':
                    $status = strtoupper((string) $value);
                    if ($status === 'LINKED') {
                        $where[] = 'COALESCE(vlink.voucher_count, 0) > 0';
                    } elseif ($status === 'UNLINKED') {
                        $where[] = 'COALESCE(vlink.voucher_count, 0) = 0';
                    }
                    break;
                case 'evidence_status':
                    $status = strtoupper((string) $value);
                    if ($status === 'DELETED' || $status === '삭제') {
                        $where[] = '(b.deleted_at IS NOT NULL OR e.deleted_at IS NOT NULL)';
                        $deletedScope = 'ALL';
                        break;
                    }
                    $where[] = 'UPPER(COALESCE(e.evidence_status, "")) = ' . $param;
                    $params[$param] = $status;
                    break;
                case 'amount_min':
                    $amount = $this->filterAmount($value);
                    if ($amount === null) {
                        break;
                    }
                    $where[] = 'GREATEST(COALESCE(b.deposit_amount, 0), COALESCE(b.withdraw_amount, 0)) >= ' . $param;
                    $params[$param] = $amount;
                    break;
                case 'amount_max':
                    $amount = $this->filterAmount($value);
                    if ($amount === null) {
                        break;
                    }
                    $where[] = 'GREATEST(COALESCE(b.deposit_amount, 0), COALESCE(b.withdraw_amount, 0)) <= ' . $param;
                    $params[$param] = $amount;
                    break;
                default:
                    $where[] = '(COALESCE(ba.account_name, "") LIKE ' . $param . '
                        OR COALESCE(ba.bank_name, "") LIKE ' . $param . '
                        OR COALESCE(b.description, "") LIKE ' . $param . '
                        OR COALESCE(b.memo, "") LIKE ' . $param . '
                        OR ' . $this->clientNameSql() . ' LIKE ' . $param . '
                        OR COALESCE(b.counterparty_name, "") LIKE ' . $param . ')';
                    $params[$param] = '%' . (string) $value . '%';
                    break;
            }
        }

        if ($deletedScope === 'DELETED') {
            $where[] = '(b.deleted_at IS NOT NULL OR e.deleted_at IS NOT NULL)';
        } elseif ($deletedScope !== 'ALL') {
            $where[] = '(b.deleted_at IS NULL AND e.deleted_at IS NULL)';
        }

        return [$where !== [] ? implode(' AND ', $where) : '1 = 1', $params];
    }

    private function filterAmount(mixed $value): ?float
    {
        $normalized = preg_replace('/[^0-9.\-]/', '', (string) $value);
        if ($normalized === null || $normalized === '' || $normalized === '-' || $normalized === '.') {
            return null;
        }

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function filterLikePattern(mixed $value): string
    {
        $pattern = str_replace('*', '%', trim((string) $value));
        return '%' . $pattern . '%';
    }

    private function normalizeRow(array $row): array
    {
        $deposit = (float) ($row['deposit_amount'] ?? 0);
        $withdraw = (float) ($row['withdraw_amount'] ?? 0);
        $voucherCount = (int) ($row['voucher_count'] ?? 0);
        $payload = $this->decodeJsonObject($row['evidence_mapped_payload_json'] ?? null);
        $payloadValue = static function (array $keys, mixed $fallback = null) use ($payload): mixed {
            foreach ($keys as $key) {
                if (!array_key_exists($key, $payload)) {
                    continue;
                }
                $value = $payload[$key];
                if ($value !== null && $value !== '') {
                    return $value;
                }
            }

            return $fallback;
        };
        $sortNo = (float) ($payload['_status_sort_no'] ?? 0);

        $row['sort_no'] = $this->formatSortNo($sortNo);
        $row['_evidence_sort_no'] = $sortNo;
        $row['deposit_amount'] = $deposit;
        $row['withdraw_amount'] = $withdraw;
        $row['original_balance_amount'] = $row['balance_amount'] === null ? null : (float) $row['balance_amount'];
        $row['calculated_balance_amount'] = null;
        $row['balance_difference'] = null;
        $row['source_payload'] = $payload;
        $row['source_transaction_datetime'] = $payloadValue(['transaction_datetime', 'transaction_date', '거래일시'], $row['transaction_datetime'] ?? $row['transaction_date'] ?? null);
        $row['source_withdraw_amount'] = $payloadValue(['withdraw_amount', 'withdrawal_amount', 'withdraw', '출금'], $withdraw);
        $row['source_deposit_amount'] = $payloadValue(['deposit_amount', 'deposit', '입금'], $deposit);
        $row['source_balance_amount'] = $payloadValue(['balance_amount', 'balance_after', '잔액', '거래후잔액'], $row['original_balance_amount']);
        $row['source_description'] = $payloadValue(['description', '거래내용'], $row['description'] ?? '');
        $row['source_counterparty_account_number'] = $payloadValue(['counterparty_account_number', '상대계좌번호'], $row['counterparty_account_number'] ?? '');
        $row['source_counterparty_bank_name'] = $payloadValue(['counterparty_bank_name', '상대은행'], $row['counterparty_bank_name'] ?? '');
        $row['source_memo'] = $payloadValue(['memo', '메모'], $row['memo'] ?? '');
        $row['source_bank_direction'] = $payloadValue(['bank_direction', '거래구분'], $row['transaction_type'] ?? '');
        $row['source_check_bill_amount'] = $payloadValue(['check_bill_amount', 'check_amount', '수표어음금액'], 0);
        $row['source_bank_reference_no'] = $payloadValue(['bank_reference_no', 'cms_code', 'CMS코드'], $row['bank_reference_no'] ?? '');
        $row['source_counterparty_name'] = $payloadValue(['counterparty_name', 'counterparty_account_holder_name', 'counterparty_account_name', '상대계좌예금주명'], $row['counterparty_name'] ?? '');
        $row['counterparty_name'] = (string) ($row['source_counterparty_name'] ?? '');
        $row['client_name'] = $payloadValue(['client_name', 'client_company_name', '거래처명', '거래처'], $row['client_name'] ?? '');
        $row['direction'] = $deposit > 0 ? 'IN' : ($withdraw > 0 ? 'OUT' : strtoupper((string) ($row['transaction_type'] ?? '')));
        $row['account_number'] = (string) ($row['account_number'] ?? '');
        $row['counterparty_account_number'] = (string) ($row['counterparty_account_number'] ?? '');
        unset($row['evidence_mapped_payload_json']);
        $row['voucher_link_status'] = $voucherCount > 0 ? 'LINKED' : 'UNLINKED';
        $row['voucher_link_label'] = $voucherCount > 0 ? '연결완료' : '미연결';
        $row['evidence_label'] = $row['deleted_at'] ? '삭제됨' : ((string) ($row['evidence_status'] ?? '') ?: '원본');

        return $row;
    }

    private function applyCalculatedBalances(array &$rows): void
    {
        $groups = [];
        foreach ($rows as $index => $row) {
            $accountKey = trim((string) ($row['bank_account_id'] ?? ''));
            if ($accountKey === '') {
                $accountKey = 'account:' . trim((string) ($row['account_name'] ?? '')) . ':' . trim((string) ($row['account_number'] ?? ''));
            }
            $groups[$accountKey][] = $index;
        }

        foreach ($groups as $indexes) {
            $openingBalance = 0.0;
            $hasAnchor = false;
            foreach ($indexes as $index) {
                $originalBalance = $rows[$index]['original_balance_amount'] ?? null;
                if ($originalBalance === null) {
                    continue;
                }
                $openingBalance = (float) $originalBalance
                    - (float) ($rows[$index]['deposit_amount'] ?? 0)
                    + (float) ($rows[$index]['withdraw_amount'] ?? 0);
                $hasAnchor = true;
                break;
            }

            $runningBalance = $hasAnchor ? $openingBalance : 0.0;
            foreach ($indexes as $index) {
                $runningBalance += (float) ($rows[$index]['deposit_amount'] ?? 0);
                $runningBalance -= (float) ($rows[$index]['withdraw_amount'] ?? 0);

                $calculated = round($runningBalance, 2);
                $original = $rows[$index]['original_balance_amount'] ?? null;
                $rows[$index]['calculated_balance_amount'] = $calculated;
                $rows[$index]['balance_amount'] = $calculated;
                $rows[$index]['balance_difference'] = $original === null ? null : round((float) $original - $calculated, 2);
                $rows[$index]['balance_status'] = $original === null
                    ? 'CALCULATED'
                    : (abs((float) $rows[$index]['balance_difference']) < 0.01 ? 'MATCHED' : 'DIFFERENT');
            }
        }
    }

    private function applyTransactionDateOrderFlags(array &$rows): void
    {
        $groups = [];
        foreach ($rows as $index => $row) {
            $accountKey = trim((string) ($row['bank_account_id'] ?? ''));
            if ($accountKey === '') {
                $accountKey = 'account:' . trim((string) ($row['account_name'] ?? '')) . ':' . trim((string) ($row['account_number'] ?? ''));
            }
            $groups[$accountKey][] = $index;
            $rows[$index]['transaction_datetime_order_status'] = 'OK';
            $rows[$index]['transaction_datetime_order_message'] = '';
        }

        foreach ($groups as $indexes) {
            $previousTime = null;
            foreach ($indexes as $index) {
                $currentText = trim((string) ($rows[$index]['transaction_datetime'] ?? $rows[$index]['transaction_date'] ?? ''));
                $currentTime = $currentText !== '' ? strtotime($currentText) : false;
                if ($currentTime === false) {
                    $rows[$index]['transaction_datetime_order_status'] = 'MISSING';
                    $rows[$index]['transaction_datetime_order_message'] = '거래일시가 없어 순번 흐름을 확인할 수 없습니다.';
                    continue;
                }

                if ($previousTime !== null && $currentTime < $previousTime) {
                    $rows[$index]['transaction_datetime_order_status'] = 'OUT_OF_ORDER';
                    $rows[$index]['transaction_datetime_order_message'] = '같은 계좌의 이전 순번보다 거래일시가 빠릅니다. 순번을 확인해 주세요.';
                }
                $previousTime = max($previousTime ?? $currentTime, $currentTime);
            }
        }
    }

    private function calculatedEndingBalance(array $filters): ?float
    {
        $rows = $this->rows($filters);
        if ($rows === []) {
            return null;
        }

        $endingByAccount = [];
        foreach ($rows as $row) {
            $accountKey = trim((string) ($row['bank_account_id'] ?? ''));
            if ($accountKey === '') {
                $accountKey = 'account:' . trim((string) ($row['account_name'] ?? '')) . ':' . trim((string) ($row['account_number'] ?? ''));
            }
            $endingByAccount[$accountKey] = $row['calculated_balance_amount'] ?? $row['balance_amount'] ?? null;
        }

        $balances = array_filter($endingByAccount, static fn($value): bool => $value !== null && $value !== '');
        if ($balances === []) {
            return null;
        }

        return array_sum(array_map('floatval', $balances));
    }

    private function endingBalance(array $filters): ?float
    {
        [$where, $params] = $this->whereSql($filters, false);
        $stmt = $this->pdo->prepare("
            SELECT b.balance_amount
            FROM ledger_bank_transactions b
            LEFT JOIN system_bank_accounts ba ON ba.id = b.bank_account_id
            LEFT JOIN ledger_data_evidences e ON e.id = b.evidence_id
            LEFT JOIN " . $this->voucherLinkSubquery() . " vlink ON vlink.evidence_id = b.evidence_id
            WHERE {$where}
              AND b.balance_amount IS NOT NULL
            ORDER BY COALESCE(" . $this->selectColumn('b', 'transaction_datetime', 'NULL') . ", TIMESTAMP(b.transaction_date, COALESCE(b.transaction_time, '00:00:00'))) DESC,
                     b.created_at DESC
            LIMIT 1
        ");
        $stmt->execute($params);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (float) $value;
    }

    private function emptySummary(): array
    {
        return [
            'deposit_total' => 0,
            'withdraw_total' => 0,
            'ending_balance' => null,
            'unlinked_count' => 0,
            'voucher_linked_count' => 0,
        ];
    }

    private function voucherLinkSubquery(): string
    {
        if (!$this->tableExists('ledger_evidence_links') || !$this->tableExists('ledger_vouchers')) {
            return "(SELECT NULL AS evidence_id, 0 AS voucher_count, NULL AS voucher_id, NULL AS voucher_no, NULL AS voucher_date WHERE 1 = 0)";
        }

        $deletedFilter = $this->columnExists('ledger_evidence_links', 'deleted_at') ? 'AND l.deleted_at IS NULL' : '';
        $voucherNoSelect = $this->columnExists('ledger_vouchers', 'voucher_no') ? 'MAX(v.voucher_no)' : ($this->columnExists('ledger_vouchers', 'code') ? 'MAX(v.code)' : 'MAX(v.id)');

        return "(
            SELECT
                l.evidence_id,
                COUNT(DISTINCT v.id) AS voucher_count,
                MAX(v.id) AS voucher_id,
                {$voucherNoSelect} AS voucher_no,
                MAX(v.voucher_date) AS voucher_date
            FROM ledger_evidence_links l
            INNER JOIN ledger_vouchers v
                ON v.id = l.target_id
               AND v.deleted_at IS NULL
            WHERE l.evidence_id IS NOT NULL
              AND l.target_type = 'VOUCHER'
              {$deletedFilter}
            GROUP BY l.evidence_id
        )";
    }

    private function selectColumn(string $alias, string $column, string $fallback): string
    {
        return $this->columnExists($alias === 'b' ? 'ledger_bank_transactions' : $alias, $column)
            ? "{$alias}.{$column}"
            : $fallback;
    }

    private function clientNameSql(): string
    {
        $bankClientName = $this->columnExists('ledger_bank_transactions', 'client_name')
            ? "NULLIF(b.client_name, '')"
            : 'NULL';
        $evidenceClientName = $this->columnExists('ledger_data_evidences', 'client_name')
            ? "NULLIF(e.client_name, '')"
            : 'NULL';

        return "COALESCE({$bankClientName}, {$evidenceClientName}, '')";
    }

    private function decodeJsonObject(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function formatSortNo(float $value): string
    {
        if ($value <= 0) {
            return '';
        }

        if (abs($value - round($value)) < 0.000001) {
            return (string) (int) round($value);
        }

        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
        ");
        $stmt->execute([':table' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, $this->columnCache)) {
            return $this->columnCache[$key];
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND COLUMN_NAME = :column
        ");
        $stmt->execute([':table' => $table, ':column' => $column]);
        $this->columnCache[$key] = (int) $stmt->fetchColumn() > 0;

        return $this->columnCache[$key];
    }
}
