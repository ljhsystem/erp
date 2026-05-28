<?php

namespace App\Models\Funds;

use PDO;

class PaymentInfoReportModel
{
    private array $columnCache = [];
    private array $tableCache = [];

    public function __construct(private PDO $pdo)
    {
    }

    public function rows(array $filters = []): array
    {
        if (!$this->tableExists('ledger_voucher_payments')) {
            return [];
        }

        [$where, $params] = $this->whereSql($filters);
        $directionExpr = $this->paymentDirectionExpr();
        $voucherNoExpr = $this->selectColumn('v', 'voucher_no', 'v.code');
        $voucherStatusExpr = $this->selectColumn('v', 'journal_status', 'v.status');
        $accountNumberExpr = $this->tableExists('system_bank_accounts')
            ? $this->selectColumn('ba', 'account_number', "''")
            : "''";
        $cardNumberExpr = $this->tableExists('system_cards')
            ? $this->selectColumn('c', 'card_number', "''")
            : "''";

        $sql = "
            SELECT
                p.id,
                p.voucher_id,
                {$directionExpr} AS payment_direction,
                UPPER(COALESCE(p.payment_type, '')) AS payment_type,
                p.payment_id,
                COALESCE(p.amount, 0) AS amount,
                p.created_at,
                {$voucherNoExpr} AS voucher_no,
                v.voucher_date,
                COALESCE(v.summary_text, '') AS summary_text,
                COALESCE(v.status, '') AS voucher_status,
                {$voucherStatusExpr} AS journal_status,
                COALESCE(ba.account_name, c.card_name, p.payment_id, '-') AS payment_name,
                COALESCE(ba.bank_name, '') AS bank_name,
                {$accountNumberExpr} AS account_number,
                {$cardNumberExpr} AS card_number
            FROM ledger_voucher_payments p
            LEFT JOIN ledger_vouchers v
                ON v.id = p.voucher_id
            " . ($this->tableExists('system_bank_accounts') ? "
            LEFT JOIN system_bank_accounts ba
                ON UPPER(COALESCE(p.payment_type, '')) = 'ACCOUNT'
               AND ba.id = p.payment_id
            " : "") . "
            " . ($this->tableExists('system_cards') ? "
            LEFT JOIN system_cards c
                ON UPPER(COALESCE(p.payment_type, '')) = 'CARD'
               AND c.id = p.payment_id
            " : "") . "
            WHERE {$where}
            ORDER BY COALESCE(v.voucher_date, DATE(p.created_at)) DESC,
                     p.created_at DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(fn(array $row): array => $this->normalizeRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function summary(array $filters = []): array
    {
        if (!$this->tableExists('ledger_voucher_payments')) {
            return $this->emptySummary();
        }

        [$where, $params] = $this->whereSql($filters);
        $directionExpr = $this->paymentDirectionExpr();
        $sql = "
            SELECT
                COUNT(*) AS payment_count,
                COALESCE(SUM(CASE WHEN {$directionExpr} = 'IN' THEN COALESCE(p.amount, 0) ELSE 0 END), 0) AS in_total,
                COALESCE(SUM(CASE WHEN {$directionExpr} = 'OUT' THEN COALESCE(p.amount, 0) ELSE 0 END), 0) AS out_total,
                COALESCE(SUM(COALESCE(p.amount, 0)), 0) AS total_amount
            FROM ledger_voucher_payments p
            LEFT JOIN ledger_vouchers v
                ON v.id = p.voucher_id
            " . ($this->tableExists('system_bank_accounts') ? "
            LEFT JOIN system_bank_accounts ba
                ON UPPER(COALESCE(p.payment_type, '')) = 'ACCOUNT'
               AND ba.id = p.payment_id
            " : "") . "
            " . ($this->tableExists('system_cards') ? "
            LEFT JOIN system_cards c
                ON UPPER(COALESCE(p.payment_type, '')) = 'CARD'
               AND c.id = p.payment_id
            " : "") . "
            WHERE {$where}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_merge($this->emptySummary(), $stmt->fetch(PDO::FETCH_ASSOC) ?: []);
    }

    private function whereSql(array $filters): array
    {
        $where = [];
        $params = [];

        if ($this->columnExists('ledger_vouchers', 'deleted_at')) {
            $where[] = '(v.deleted_at IS NULL OR v.id IS NULL)';
        }

        foreach ($filters as $index => $filter) {
            $field = (string) ($filter['field'] ?? '');
            $value = $filter['value'] ?? null;
            if ($field === '' || $value === '' || $value === null) {
                continue;
            }

            if (is_array($value) && array_key_exists('start', $value) && array_key_exists('end', $value)) {
                $start = (string) ($value['start'] ?? '');
                $end = (string) ($value['end'] ?? '');
                if ($start === '' || $end === '') {
                    continue;
                }
                $column = $field === 'created_at' ? 'p.created_at' : 'v.voucher_date';
                $where[] = "{$column} BETWEEN :start_{$index} AND :end_{$index}";
                $params[":start_{$index}"] = strlen($start) === 10 && $column === 'p.created_at' ? $start . ' 00:00:00' : $start;
                $params[":end_{$index}"] = strlen($end) === 10 && $column === 'p.created_at' ? $end . ' 23:59:59' : $end;
                continue;
            }

            $param = ":filter_{$index}";
            switch ($field) {
                case 'payment_direction':
                case 'direction':
                    $where[] = $this->paymentDirectionExpr() . ' = ' . $param;
                    $params[$param] = strtoupper((string) $value);
                    break;
                case 'payment_type':
                    $where[] = 'UPPER(COALESCE(p.payment_type, "")) = ' . $param;
                    $params[$param] = strtoupper((string) $value);
                    break;
                case 'voucher_no':
                    $where[] = $this->selectColumn('v', 'voucher_no', 'v.code') . ' LIKE ' . $param;
                    $params[$param] = '%' . (string) $value . '%';
                    break;
                case 'summary_text':
                    $where[] = 'COALESCE(v.summary_text, "") LIKE ' . $param;
                    $params[$param] = '%' . (string) $value . '%';
                    break;
                case 'payment_name':
                default:
                    $where[] = '(COALESCE(ba.account_name, "") LIKE ' . $param . '
                        OR COALESCE(ba.bank_name, "") LIKE ' . $param . '
                        OR COALESCE(c.card_name, "") LIKE ' . $param . '
                        OR COALESCE(v.summary_text, "") LIKE ' . $param . '
                        OR COALESCE(' . $this->selectColumn('v', 'voucher_no', 'v.code') . ', "") LIKE ' . $param . ')';
                    $params[$param] = '%' . (string) $value . '%';
                    break;
            }
        }

        return [$where !== [] ? implode(' AND ', $where) : '1 = 1', $params];
    }

    private function normalizeRow(array $row): array
    {
        $direction = strtoupper((string) ($row['payment_direction'] ?? 'OUT')) === 'IN' ? 'IN' : 'OUT';
        $type = strtoupper((string) ($row['payment_type'] ?? ''));

        $row['payment_direction'] = $direction;
        $row['payment_direction_label'] = $direction === 'IN' ? '입금' : '출금';
        $row['payment_type'] = $type;
        $row['payment_type_label'] = $type === 'CARD' ? '카드' : ($type === 'ACCOUNT' ? '계좌' : $type);
        $row['amount'] = (float) ($row['amount'] ?? 0);
        $row['payment_number_masked'] = $type === 'CARD'
            ? $this->maskNumber((string) ($row['card_number'] ?? ''))
            : $this->maskNumber((string) ($row['account_number'] ?? ''));
        unset($row['card_number'], $row['account_number']);
        $row['match_status'] = 'UNMATCHED';
        $row['match_status_label'] = '은행입출금 미연결';

        return $row;
    }

    private function paymentDirectionExpr(): string
    {
        if ($this->columnExists('ledger_voucher_payments', 'payment_direction')) {
            return "UPPER(COALESCE(p.payment_direction, 'OUT'))";
        }
        if ($this->columnExists('ledger_voucher_payments', 'direction')) {
            return "UPPER(COALESCE(p.direction, 'OUT'))";
        }
        return "'OUT'";
    }

    private function emptySummary(): array
    {
        return [
            'payment_count' => 0,
            'in_total' => 0,
            'out_total' => 0,
            'total_amount' => 0,
        ];
    }

    private function selectColumn(string $alias, string $column, string $fallback): string
    {
        return $this->columnExists($this->tableNameForAlias($alias), $column) ? "{$alias}.{$column}" : $fallback;
    }

    private function tableNameForAlias(string $alias): string
    {
        return match ($alias) {
            'v' => 'ledger_vouchers',
            'ba' => 'system_bank_accounts',
            'c' => 'system_cards',
            default => $alias,
        };
    }

    private function tableExists(string $table): bool
    {
        if (!array_key_exists($table, $this->tableCache)) {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table
            ");
            $stmt->execute([':table' => $table]);
            $this->tableCache[$table] = (int) $stmt->fetchColumn() > 0;
        }

        return $this->tableCache[$table];
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }
        if (!array_key_exists($table, $this->columnCache)) {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM {$table}");
            $this->columnCache[$table] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'Field');
        }

        return in_array($column, $this->columnCache[$table], true);
    }

    private function maskNumber(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) <= 4) {
            return str_repeat('*', strlen($digits));
        }

        return str_repeat('*', max(0, strlen($digits) - 4)) . substr($digits, -4);
    }
}
