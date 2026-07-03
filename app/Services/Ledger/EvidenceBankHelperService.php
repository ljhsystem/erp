<?php

namespace App\Services\Ledger;

use PDO;

class EvidenceBankHelperService
{
    private array $bankAccountIdCache = [];

    public function __construct(private PDO $pdo, private array $callbacks = [])
    {
    }

    public function bankTransactionPayloadFromRow(array $row): array
    {
        $transactionDateTime = $this->rawTransactionDateTime($row);
        $deposit = $this->call('amountOrNull', $row['raw_deposit_amount'] ?? $row['deposit_amount'] ?? null);
        $withdraw = $this->call('amountOrNull', $row['raw_withdraw_amount'] ?? $row['withdraw_amount'] ?? null);
        $balance = $this->call('amountOrNull', $row['raw_balance_amount'] ?? $row['balance_amount'] ?? null);
        $checkBill = $this->call('amountOrNull', $row['raw_check_bill_amount'] ?? $row['check_bill_amount'] ?? null);
        $transactionDirection = $this->normalizeDirection($row);

        return [
            'data_type' => 'BANK_TRANSACTION',
            'source_key' => trim((string) ($row['bank_reference_no'] ?? '')) !== '' ? (string) $row['bank_reference_no'] : 'BANK:' . (string) ($row['id'] ?? ''),
            'import_type' => (string) ($row['import_type'] ?? 'BANK_TRANSACTION'),
            'business_unit' => (string) ($row['business_unit'] ?? ''),
            'transaction_direction' => $transactionDirection,
            'operation_type' => (string) ($row['operation_type'] ?? ''),
            'bank_account_id' => (string) ($row['bank_account_id'] ?? ''),
            'bank_account_name' => $this->bankAccountNameById((string) ($row['bank_account_id'] ?? '')),
            'card_id' => (string) ($row['card_id'] ?? ''),
            'team_id' => (string) ($row['team_id'] ?? ''),
            'employee_id' => (string) ($row['employee_id'] ?? ''),
            'raw_transaction_datetime' => $transactionDateTime,
            'raw_deposit_amount' => $deposit,
            'raw_withdraw_amount' => $withdraw,
            'raw_balance_amount' => $balance,
            'balance_status' => (string) ($row['balance_status'] ?? $this->call('bankBalanceStatus', $balance)),
            'raw_check_bill_amount' => $checkBill,
            'raw_description' => (string) ($row['raw_description'] ?? $row['description'] ?? ''),
            'raw_counterparty_name' => (string) ($row['raw_counterparty_name'] ?? $row['counterparty_name'] ?? ''),
            'raw_counterparty_account_number' => (string) ($row['raw_counterparty_account_number'] ?? $row['counterparty_account_number'] ?? ''),
            'raw_counterparty_bank_name' => (string) ($row['raw_counterparty_bank_name'] ?? $row['counterparty_bank_name'] ?? ''),
            'raw_cms_code' => (string) ($row['raw_cms_code'] ?? $row['bank_reference_no'] ?? ''),
            'raw_memo' => (string) ($row['raw_memo'] ?? $row['memo'] ?? ''),
            'raw_transaction_type' => (string) ($row['raw_transaction_type'] ?? ''),
        ];
    }

    public function bankDirectionLabel(string $directionCode): string
    {
        return match (strtoupper(trim($directionCode))) {
            'DEPOSIT', 'IN' => '입금',
            'WITHDRAW', 'OUT' => '출금',
            'TRANSFER' => '이체',
            'FEE' => '수수료',
            'INTEREST' => '이자',
            default => $directionCode,
        };
    }

    public function bankAccountNameById(string $bankAccountId): string
    {
        if ($bankAccountId === '') {
            return '';
        }

        if (!array_key_exists($bankAccountId, $this->bankAccountIdCache)) {
            $this->bankAccountIdCache[$bankAccountId] = (string) ($this->call('businessRefNameById', 'ACCOUNT', $bankAccountId) ?? '');
        }

        return (string) $this->bankAccountIdCache[$bankAccountId];
    }

    public function hasVoucherLinesPayload(array $payload): bool
    {
        $lines = $payload['_voucher_lines'] ?? null;
        if (!is_array($lines) || $lines === []) {
            return false;
        }

        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            if ($this->call('normalizeBankVoucherLineRowType', $line['line_row_type'] ?? null) === 'AUX') {
                continue;
            }
            $account = trim((string) ($line['account_id'] ?? ''));
            $debit = $this->call('amountOrNull', $line['debit'] ?? null);
            $credit = $this->call('amountOrNull', $line['credit'] ?? null);
            if ($account !== '' && (($debit !== null && $debit != 0.0) || ($credit !== null && $credit != 0.0))) {
                return true;
            }
        }

        return false;
    }

    public function bankVoucherValidationMessage(array $payload): ?string
    {
        if (!$this->hasVoucherLinesPayload($payload)) {
            return null;
        }

        try {
            $lines = $this->call('bankVoucherLinesForSave', $payload['_voucher_lines'] ?? []);
            $missingRefMessage = $this->call('missingRequiredEvidenceRefsMessage', $lines, $payload);
            if ($missingRefMessage !== null) {
                return $missingRefMessage;
            }
            $this->call('bankVoucherPaymentsForSave', $payload);
            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    public function resetBankEvidenceTransactionClaim(string $evidenceId, string $actor): void
    {
        if ($evidenceId === '') {
            return;
        }

        $stmt = $this->pdo->prepare("
            UPDATE ledger_data_evidences
            SET transaction_status = 'NONE',
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE id = :id
              AND source_type = 'BANK_TRANSACTION'
              AND transaction_status = 'PROCESSING'
              AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':id' => $evidenceId,
            ':updated_by' => $actor,
        ]);
    }


    public function nullableString(mixed $value): ?string
    {
        $value = trim((string) $this->call('payloadScalarForStorage', $value));
        return $value === '' ? null : $value;
    }

    public function looksLikeBankAccountNumber(string $value): bool
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        return strlen($digits) >= 8 && strlen($digits) >= (int) floor(strlen(trim($value)) * 0.65);
    }

    public function normalizeBankTransactionPayload(array $row): array
    {
        foreach ($row as $key => $value) {
            if (!str_starts_with((string) $key, '_') && !is_scalar($value) && $value !== null) {
                $row[$key] = $this->call('payloadScalarForStorage', $value, str_ends_with((string) $key, '_id'));
            }
        }
        $row['raw_deposit_amount'] = $this->call('amountOrNull', $row['raw_deposit_amount'] ?? $row['deposit_amount'] ?? null);
        $row['raw_withdraw_amount'] = $this->call('amountOrNull', $row['raw_withdraw_amount'] ?? $row['withdraw_amount'] ?? $row['withdrawal_amount'] ?? null);
        $row['raw_balance_amount'] = $this->call('amountOrNull', $row['raw_balance_amount'] ?? $row['balance_amount'] ?? null);
        $row['raw_check_bill_amount'] = $this->call('amountOrNull', $row['raw_check_bill_amount'] ?? $row['check_bill_amount'] ?? null);

        $amount = $this->call('amountOrNull', $row['amount'] ?? null);
        if (($row['raw_deposit_amount'] === null || $row['raw_deposit_amount'] == 0.0)
            && ($row['raw_withdraw_amount'] === null || $row['raw_withdraw_amount'] == 0.0)
            && $amount !== null) {
            $direction = $this->normalizeDirection($row);
            if ($direction === 'OUT') {
                $row['raw_withdraw_amount'] = abs((float) $amount);
            } else {
                $row['raw_deposit_amount'] = abs((float) $amount);
            }
        }

        $row['transaction_direction'] = $this->normalizeDirection($row);
        $row['raw_transaction_datetime'] = $this->rawTransactionDateTime($row);
        $row['raw_description'] = trim((string) ($row['raw_description'] ?? $row['description'] ?? ''));
        $row['raw_counterparty_account_number'] = trim((string) ($row['raw_counterparty_account_number'] ?? $row['counterparty_account_number'] ?? $row['counterparty_account_no'] ?? ''));
        $row['raw_counterparty_bank_name'] = trim((string) ($row['raw_counterparty_bank_name'] ?? $row['counterparty_bank_name'] ?? $row['counterparty_bank'] ?? $row['bank_name'] ?? ''));
        $row['raw_memo'] = trim((string) ($row['raw_memo'] ?? $row['memo'] ?? ''));
        $row['raw_cms_code'] = trim((string) ($row['raw_cms_code'] ?? $row['bank_reference_no'] ?? ''));
        $row['raw_transaction_type'] = trim((string) ($row['raw_transaction_type'] ?? ''));

        $hasExplicitRawCounterpartyName = array_key_exists('raw_counterparty_name', $row);
        $counterpartyName = $this->bankCounterpartyName($row);
        if (!$hasExplicitRawCounterpartyName && $counterpartyName !== '') {
            $row['raw_counterparty_name'] = $counterpartyName;
            $row['client_company_name'] = $counterpartyName;
        } elseif (!$hasExplicitRawCounterpartyName && !empty($row['client_company_name']) && $this->looksLikeBankAccountNumber((string) $row['client_company_name'])) {
            $row['raw_counterparty_account_number'] = $row['raw_counterparty_account_number'] ?? $row['client_company_name'];
            unset($row['client_company_name']);
        }

        foreach (['counterparty_account_number', 'counterparty_account_no', 'account_number', 'client_business_number'] as $key) {
            if (!empty($row[$key]) && $this->looksLikeBankAccountNumber((string) $row[$key])) {
                $row['raw_counterparty_account_number'] = $row['raw_counterparty_account_number'] ?? $row[$key];
                if ($key === 'client_business_number') {
                    unset($row['client_business_number']);
                }
                break;
            }
        }
        foreach (['counterparty_bank_name', 'counterparty_bank', 'bank_name'] as $key) {
            if (!empty($row[$key])) {
                $row['raw_counterparty_bank_name'] = $row['raw_counterparty_bank_name'] ?? $row[$key];
                break;
            }
        }

        return $row;
    }

    public function bankCounterpartyName(array $row): string
    {
        foreach (['raw_counterparty_name', 'counterparty_name', 'counterparty_account_holder_name', 'counterparty_account_holder', 'account_holder', 'client_company_name'] as $key) {
            $value = $this->call('cleanCompanyName', (string) ($row[$key] ?? ''));
            if ($value !== '' && !$this->looksLikeBankAccountNumber($value)) {
                return $value;
            }
        }

        return '';
    }

    private function normalizeDirection(array $row): string
    {
        $direction = $this->call('normalizeTransactionDirection', (string) ($row['transaction_direction'] ?? $row['bank_direction'] ?? ''));
        if ($direction === 'FUND') {
            return 'FUND';
        }
        if ($direction === '') {
            $withdraw = $this->call('amountOrNull', $row['raw_withdraw_amount'] ?? $row['withdraw_amount'] ?? $row['withdrawal_amount'] ?? null);
            $deposit = $this->call('amountOrNull', $row['raw_deposit_amount'] ?? $row['deposit_amount'] ?? null);
            if ($withdraw !== null && $withdraw > 0) {
                return 'FUND';
            }
            if ($deposit !== null && $deposit > 0) {
                return 'FUND';
            }
        }

        return $direction;
    }

    private function rawTransactionDateTime(array $row): ?string
    {
        $dateTime = trim((string) ($row['raw_transaction_datetime'] ?? $row['transaction_datetime'] ?? $row['transaction_at'] ?? ''));
        if ($dateTime !== '') {
            return $this->call('dateTimeValue', $dateTime);
        }

        $legacyDate = trim((string) ($row['transaction_date'] ?? ''));
        $legacyTime = trim((string) ($row['transaction_time'] ?? ''));
        if ($legacyDate !== '') {
            $combined = trim($legacyDate . ' ' . $legacyTime);
            return $this->call('dateTimeValue', $combined);
        }

        return null;
    }

    private function call(string $name, mixed ...$args): mixed
    {
        $callback = $this->callbacks[$name] ?? null;
        if (!is_callable($callback)) {
            throw new \RuntimeException('Missing EvidenceBankHelperService callback: ' . $name);
        }

        return $callback(...$args);
    }
}
