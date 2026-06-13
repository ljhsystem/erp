<?php

namespace App\Services\Ledger;

use Core\Helpers\UuidHelper;
use PDO;

class EvidenceBankHelperService
{
    private array $bankAccountIdCache = [];

    public function __construct(private PDO $pdo, private array $callbacks = [])
    {
    }

    public function ensureBankTransactionEvidenceRows(): void
    {
        if (
            !$this->call('tableExists', 'ledger_bank_transactions')
            || !$this->call('tableExists', 'ledger_evidence_bank')
            || !$this->call('tableExists', 'ledger_evidence_payloads')
            || !$this->call('tableExists', 'ledger_evidence_processing')
        ) {
            return;
        }

        $stmt = $this->pdo->query("
            SELECT b.*
            FROM ledger_bank_transactions b
            LEFT JOIN ledger_evidence_bank eb ON eb.id = b.evidence_id
            WHERE eb.id IS NULL
            ORDER BY b.created_at ASC, b.id ASC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            return;
        }

        $startedTransaction = !$this->pdo->inTransaction();
        if ($startedTransaction) {
            $this->pdo->beginTransaction();
        }

        $payloadUpsert = $this->pdo->prepare("
            INSERT INTO ledger_evidence_payloads
                (id, evidence_type, evidence_id, mapped_payload_json, raw_json, payload_hash,
                 format_id, source_key, latest_imported_at, created_at, created_by,
                 updated_at, updated_by, deleted_at, deleted_by)
            VALUES
                (:payload_id, 'BANK_TRANSACTION', :evidence_id, :mapped_payload_json, :raw_json,
                 SHA2(COALESCE(:mapped_payload_json_hash, ''), 256), NULL, :source_key,
                 :latest_imported_at, :created_at, :created_by, :updated_at, :updated_by,
                 :deleted_at, :deleted_by)
            ON DUPLICATE KEY UPDATE
                mapped_payload_json = VALUES(mapped_payload_json),
                raw_json = VALUES(raw_json),
                payload_hash = VALUES(payload_hash),
                source_key = VALUES(source_key),
                latest_imported_at = VALUES(latest_imported_at),
                updated_at = VALUES(updated_at),
                updated_by = VALUES(updated_by),
                deleted_at = VALUES(deleted_at),
                deleted_by = VALUES(deleted_by)
        ");
        $processingUpsert = $this->pdo->prepare("
            INSERT INTO ledger_evidence_processing
                (id, evidence_type, evidence_id, processing_status, review_status, last_error_message, created_at, updated_at, deleted_at)
            VALUES
                (:processing_id, 'BANK_TRANSACTION', :evidence_id, :processing_status, 'NORMAL', :error_message, :created_at, :updated_at, :deleted_at)
            ON DUPLICATE KEY UPDATE
                processing_status = VALUES(processing_status),
                review_status = VALUES(review_status),
                last_error_message = VALUES(last_error_message),
                updated_at = VALUES(updated_at),
                deleted_at = VALUES(deleted_at)
        ");
        $dualWrite = new EvidenceDualWriteService($this->pdo);

        try {
            foreach ($rows as $row) {
                $evidenceId = trim((string) ($row['evidence_id'] ?? ''));
                if ($evidenceId === '') {
                    $evidenceId = UuidHelper::generate();
                    $this->pdo->prepare('UPDATE ledger_bank_transactions SET evidence_id = :evidence_id WHERE id = :id')
                        ->execute([':evidence_id' => $evidenceId, ':id' => (string) $row['id']]);
                }

                $payload = $this->bankTransactionPayloadFromRow($row);
                $payload['transaction_type'] = (string) ($row['transaction_type'] ?? '');
                $payload = $this->normalizeBankTransactionPayload($payload);
                $sourceKey = trim((string) ($row['bank_reference_no'] ?? ''));
                if ($sourceKey === '') {
                    $sourceKey = 'BANK:' . (string) $row['id'];
                }
                $totalAmount = (float) ($this->call('amountOrNull', $row['deposit_amount'] ?? null) ?? 0)
                    + (float) ($this->call('amountOrNull', $row['withdraw_amount'] ?? null) ?? 0);
                $deletedAt = $row['deleted_at'] ?? null;
                $createdAt = $row['created_at'] ?? date('Y-m-d H:i:s');
                $updatedAt = $row['updated_at'] ?? $createdAt;
                $createdBy = (string) ($row['created_by'] ?? 'SYSTEM:BANK_SYNC');
                $updatedBy = (string) ($row['updated_by'] ?? 'SYSTEM:BANK_SYNC');
                $rawJson = $this->call('jsonEncodeForStorage', $payload);
                $mappedPayloadJson = $this->call('jsonEncodeForStorage', $payload);
                $voucherErrorMessage = $this->bankVoucherValidationMessage($payload);
                $processingStatus = $voucherErrorMessage === null || trim((string) $voucherErrorMessage) === ''
                    ? 'READY'
                    : 'REVIEW_REQUIRED';

                $payloadUpsert->execute([
                    ':payload_id' => UuidHelper::generate(),
                    ':evidence_id' => $evidenceId,
                    ':mapped_payload_json' => $mappedPayloadJson,
                    ':mapped_payload_json_hash' => $mappedPayloadJson,
                    ':raw_json' => $rawJson,
                    ':source_key' => $sourceKey,
                    ':latest_imported_at' => $createdAt,
                    ':created_at' => $createdAt,
                    ':created_by' => $createdBy,
                    ':updated_at' => $updatedAt,
                    ':updated_by' => $updatedBy,
                    ':deleted_at' => $deletedAt ?: null,
                    ':deleted_by' => $row['deleted_by'] ?? null,
                ]);

                $processingUpsert->execute([
                    ':processing_id' => UuidHelper::generate(),
                    ':evidence_id' => $evidenceId,
                    ':processing_status' => $processingStatus,
                    ':error_message' => $voucherErrorMessage,
                    ':created_at' => $createdAt,
                    ':updated_at' => $updatedAt,
                    ':deleted_at' => $deletedAt ?: null,
                ]);

                $legacyForEvidence = [
                    'id' => $evidenceId,
                    'source_type' => 'BANK_TRANSACTION',
                    'source_key' => $sourceKey,
                    'evidence_date' => $this->call('dateValueOrNull', $row['transaction_date'] ?? null),
                    'client_id' => null,
                    'project_id' => null,
                    'client_name' => null,
                    'bank_account_id' => (string) ($row['bank_account_id'] ?? ''),
                    'currency' => (string) ($row['currency_code'] ?? 'KRW'),
                    'total_amount' => $totalAmount,
                    'evidence_status' => 'ACTIVE',
                    'transaction_status' => 'NONE',
                    'voucher_status' => $this->call('uploadVoucherStatus', 'BANK_TRANSACTION', $payload, 'READY'),
                    'error_message' => $voucherErrorMessage,
                    'raw_json' => $rawJson,
                    'mapped_payload_json' => $mappedPayloadJson,
                    'created_at' => $createdAt,
                    'created_by' => $createdBy,
                    'updated_at' => $updatedAt,
                    'updated_by' => $updatedBy,
                    'deleted_at' => $deletedAt ?: null,
                    'deleted_by' => $row['deleted_by'] ?? null,
                ];
                $dualWriteResult = $dualWrite->syncFromLegacyRow($legacyForEvidence);
                if (($dualWriteResult['dual_write_status'] ?? '') !== 'success') {
                    throw new \RuntimeException('bank evidence body update failed: ' . (string) ($dualWriteResult['message'] ?? 'unknown'));
                }
            }

            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function bankTransactionPayloadFromRow(array $row): array
    {
        return [
            'import_type' => 'BANK_TRANSACTION',
            'data_type' => 'BANK_TRANSACTION',
            'source_key' => trim((string) ($row['bank_reference_no'] ?? '')) !== '' ? (string) $row['bank_reference_no'] : 'BANK:' . (string) ($row['id'] ?? ''),
            'transaction_date' => $this->call('dateValueOrNull', $row['transaction_date'] ?? null),
            'transaction_datetime' => $this->call('dateTimeValue', $row['transaction_datetime'] ?? null),
            'transaction_time' => (string) ($row['transaction_time'] ?? ''),
            'bank_direction' => $this->bankDirectionLabel((string) ($row['transaction_type'] ?? '')),
            'bank_account_id' => (string) ($row['bank_account_id'] ?? ''),
            'bank_account_name' => $this->bankAccountNameById((string) ($row['bank_account_id'] ?? '')),
            'deposit_amount' => $this->call('amountOrNull', $row['deposit_amount'] ?? null),
            'withdraw_amount' => $this->call('amountOrNull', $row['withdraw_amount'] ?? null),
            'balance_amount' => $this->call('amountOrNull', $row['balance_amount'] ?? null),
            'balance_status' => (string) ($row['balance_status'] ?? $this->call('bankBalanceStatus', $row['balance_amount'] ?? null)),
            'check_bill_amount' => $this->call('amountOrNull', $row['check_bill_amount'] ?? null),
            'currency_code' => (string) ($row['currency_code'] ?? 'KRW'),
            'exchange_rate' => $this->call('amountOrNull', $row['exchange_rate'] ?? null),
            'description' => (string) ($row['description'] ?? ''),
            'counterparty_name' => (string) ($row['counterparty_name'] ?? ''),
            'counterparty_account_number' => (string) ($row['counterparty_account_number'] ?? ''),
            'counterparty_bank_name' => (string) ($row['counterparty_bank_name'] ?? ''),
            'bank_reference_no' => (string) ($row['bank_reference_no'] ?? ''),
            'memo' => (string) ($row['memo'] ?? ''),
        ];
    }

    public function bankDirectionLabel(string $transactionType): string
    {
        return match (strtoupper(trim($transactionType))) {
            'DEPOSIT', 'IN' => '??????????????????????關?쒎첎?嫄??怨몃룯?????',
            'WITHDRAW', 'OUT' => '??????????',
            'TRANSFER' => '??????????????????????',
            'FEE' => '???????????????????????????????????????',
            'INTEREST' => '??????????????',
            default => $transactionType,
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

    public function upsertBankTransactionFromPayload(string $evidenceId, array $payload, string $actor): void
    {
        if ($evidenceId === '') {
            return;
        }
        $this->ensureBankTransactionBalanceColumns();
        $payload = $this->normalizeBankTransactionPayload($payload);

        $stmt = $this->pdo->prepare('
            SELECT id
            FROM ledger_bank_transactions
            WHERE evidence_id = :evidence_id
              AND deleted_at IS NULL
            ORDER BY created_at DESC
            LIMIT 1
        ');
        $stmt->execute([':evidence_id' => $evidenceId]);
        $existingId = $stmt->fetchColumn();
        $existingId = $existingId !== false ? (string) $existingId : null;

        $values = [
            ':id' => $existingId ?? UuidHelper::generate(),
            ':transaction_date' => $this->call('dateValue', $payload['transaction_date'] ?? $payload['evidence_date'] ?? date('Y-m-d')),
            ':transaction_time' => $this->nullableString($payload['transaction_time'] ?? null),
            ':bank_account_id' => $this->call('businessRefIdForStorage', 'ACCOUNT', $payload) ?? '',
            ':transaction_type' => $this->bankTransactionType($payload['bank_direction'] ?? $payload['transaction_direction'] ?? null, $payload),
            ':deposit_amount' => $this->call('number', $payload['deposit_amount'] ?? null),
            ':withdraw_amount' => $this->call('number', $payload['withdraw_amount'] ?? null),
            ':balance_amount' => $this->call('amountOrNull', $payload['balance_amount'] ?? null),
            ':balance_status' => $this->call('bankBalanceStatus', $payload['balance_amount'] ?? null),
            ':currency_code' => (string) ($payload['currency_code'] ?? $payload['currency'] ?? 'KRW'),
            ':exchange_rate' => (float) ($this->call('amountOrNull', $payload['exchange_rate'] ?? null) ?? 1),
            ':description' => $this->nullableString($payload['description'] ?? null),
            ':counterparty_name' => $this->nullableString($payload['counterparty_name'] ?? null),
            ':bank_reference_no' => $this->nullableString($payload['bank_reference_no'] ?? $payload['source_key'] ?? null),
            ':evidence_id' => $evidenceId,
            ':memo' => $this->nullableString($payload['memo'] ?? null),
            ':actor' => $actor,
        ];

        if ($existingId !== null) {
            $update = $this->pdo->prepare('
                UPDATE ledger_bank_transactions
                SET transaction_date = :transaction_date,
                    transaction_time = :transaction_time,
                    bank_account_id = :bank_account_id,
                    transaction_type = :transaction_type,
                    deposit_amount = :deposit_amount,
                    withdraw_amount = :withdraw_amount,
                    balance_amount = :balance_amount,
                    balance_status = :balance_status,
                    currency_code = :currency_code,
                    exchange_rate = :exchange_rate,
                    description = :description,
                    counterparty_name = :counterparty_name,
                    bank_reference_no = :bank_reference_no,
                    evidence_id = :evidence_id,
                    memo = :memo,
                    updated_at = NOW(),
                    updated_by = :actor
                WHERE id = :id
            ');
            $update->execute($values);
            $this->updateBankCounterpartyExtraFields($evidenceId, $payload, $actor);
            return;
        }

        $insert = $this->pdo->prepare('
            INSERT INTO ledger_bank_transactions
                (id, transaction_date, transaction_time, bank_account_id, transaction_type,
                 deposit_amount, withdraw_amount, balance_amount, balance_status, currency_code, exchange_rate,
                 description, counterparty_name, bank_reference_no, evidence_id, memo, created_by, updated_by)
            VALUES
                (:id, :transaction_date, :transaction_time, :bank_account_id, :transaction_type,
                 :deposit_amount, :withdraw_amount, :balance_amount, :balance_status, :currency_code, :exchange_rate,
                 :description, :counterparty_name, :bank_reference_no, :evidence_id, :memo, :created_by, :updated_by)
            ON DUPLICATE KEY UPDATE
                transaction_date = VALUES(transaction_date),
                transaction_time = VALUES(transaction_time),
                transaction_type = VALUES(transaction_type),
                deposit_amount = VALUES(deposit_amount),
                withdraw_amount = VALUES(withdraw_amount),
                balance_amount = VALUES(balance_amount),
                balance_status = VALUES(balance_status),
                currency_code = VALUES(currency_code),
                exchange_rate = VALUES(exchange_rate),
                description = VALUES(description),
                counterparty_name = VALUES(counterparty_name),
                bank_reference_no = VALUES(bank_reference_no),
                evidence_id = VALUES(evidence_id),
                memo = VALUES(memo),
                deleted_at = NULL,
                deleted_by = NULL,
                updated_at = NOW(),
                updated_by = VALUES(updated_by)
        ');
        $insertValues = $values;
        unset($insertValues[':actor']);
        $insert->execute($insertValues + [
            ':created_by' => $actor,
            ':updated_by' => $actor,
        ]);
        $this->updateBankCounterpartyExtraFields($evidenceId, $payload, $actor);
    }

    public function updateBankCounterpartyExtraFields(string $evidenceId, array $payload, string $actor): void
    {
        $sets = [];
        $params = [
            ':evidence_id' => $evidenceId,
            ':actor' => $actor,
        ];

        if ($this->call('tableColumnExists', 'ledger_bank_transactions', 'counterparty_account_number')) {
            $sets[] = 'counterparty_account_number = :counterparty_account_number';
            $params[':counterparty_account_number'] = $this->nullableString($payload['counterparty_account_number'] ?? $payload['counterparty_account_no'] ?? $payload['account_number'] ?? null);
        }
        if ($this->call('tableColumnExists', 'ledger_bank_transactions', 'counterparty_bank_name')) {
            $sets[] = 'counterparty_bank_name = :counterparty_bank_name';
            $params[':counterparty_bank_name'] = $this->nullableString($payload['counterparty_bank_name'] ?? $payload['counterparty_bank'] ?? $payload['counterparty_bank_name'] ?? $payload['bank_name'] ?? null);
        }
        if ($this->call('tableColumnExists', 'ledger_bank_transactions', 'transaction_datetime')) {
            $sets[] = 'transaction_datetime = :transaction_datetime';
            $params[':transaction_datetime'] = $this->call('dateTimeValue', $payload['transaction_datetime'] ?? $payload['transaction_at'] ?? null);
        }
        if ($this->call('tableColumnExists', 'ledger_bank_transactions', 'check_bill_amount')) {
            $sets[] = 'check_bill_amount = :check_bill_amount';
            $params[':check_bill_amount'] = $this->call('amountOrNull', $payload['check_bill_amount'] ?? $payload['check_amount'] ?? $payload['bill_amount'] ?? null);
        }

        if ($sets === []) {
            return;
        }

        $sets[] = 'updated_at = NOW()';
        $sets[] = 'updated_by = :actor';
        $stmt = $this->pdo->prepare('
            UPDATE ledger_bank_transactions
            SET ' . implode(', ', $sets) . '
            WHERE evidence_id = :evidence_id
              AND deleted_at IS NULL
        ');
        $stmt->execute($params);
    }

    public function ensureBankTransactionBalanceColumns(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        if (!$this->call('tableExists', 'ledger_bank_transactions')) {
            $checked = true;
            return;
        }

        if ($this->call('tableColumnExists', 'ledger_bank_transactions', 'balance_amount')) {
            try {
                $this->pdo->exec("
                    ALTER TABLE `ledger_bank_transactions`
                        MODIFY COLUMN `balance_amount` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'Actual bank balance after transaction'
                ");
            } catch (\Throwable) {
            }
        }

        if (!$this->call('tableColumnExists', 'ledger_bank_transactions', 'balance_status')) {
            try {
                $after = $this->call('tableColumnExists', 'ledger_bank_transactions', 'balance_amount') ? 'balance_amount' : 'withdraw_amount';
                $this->pdo->exec("
                    ALTER TABLE `ledger_bank_transactions`
                        ADD COLUMN `balance_status` VARCHAR(20) NULL DEFAULT 'EMPTY' COMMENT 'ACTUAL, EMPTY, ESTIMATED, INVALID' AFTER `{$after}`
                ");
            } catch (\Throwable) {
            }
        }

        if ($this->call('tableColumnExists', 'ledger_bank_transactions', 'balance_status')) {
            try {
                $this->pdo->exec("
                    UPDATE `ledger_bank_transactions`
                    SET `balance_status` = CASE
                        WHEN `balance_amount` IS NULL THEN 'EMPTY'
                        ELSE 'ACTUAL'
                    END
                    WHERE `balance_status` IS NULL OR `balance_status` = ''
                ");
            } catch (\Throwable) {
            }
        }

        $checked = true;
    }

    public function bankTransactionType(mixed $value, array $payload = []): string
    {
        $type = strtoupper(trim((string) $value));
        $aliases = [
            'IN' => 'DEPOSIT',
            'OUT' => 'WITHDRAW',
            '??????????????????????關?쒎첎?嫄??怨몃룯?????' => 'DEPOSIT',
            '??????????' => 'WITHDRAW',
            '??????????????????????關?쒎첎?嫄??怨몃룯???????????????????????산뭐???????' => 'DEPOSIT',
            '??????????????????濾????????????????곗뿨???????????????????????怨뺤떪?????' => 'WITHDRAW',
            '??????????????????????' => 'TRANSFER',
            '???????????????????????????????????????' => 'FEE',
            '??????????????' => 'INTEREST',
        ];
        $type = $aliases[$type] ?? $type;
        if ($type === 'ETC' || $type === '') {
            $deposit = $this->call('amountOrNull', $payload['deposit_amount'] ?? null);
            $withdraw = $this->call('amountOrNull', $payload['withdraw_amount'] ?? $payload['withdrawal_amount'] ?? null);
            if ($withdraw !== null && $withdraw > 0) {
                $type = 'WITHDRAW';
            } elseif ($deposit !== null && $deposit > 0) {
                $type = 'DEPOSIT';
            }
        }

        return in_array($type, ['DEPOSIT', 'WITHDRAW', 'TRANSFER', 'CARD_PAYMENT', 'FEE', 'INTEREST', 'ETC'], true) ? $type : 'ETC';
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
        $row['withdraw_amount'] = $row['withdraw_amount'] ?? $row['withdrawal_amount'] ?? null;
        $deposit = $this->call('amountOrNull', $row['deposit_amount'] ?? null);
        $withdraw = $this->call('amountOrNull', $row['withdraw_amount'] ?? null);
        $amount = $this->call('amountOrNull', $row['total_amount'] ?? $row['amount'] ?? null);

        if (($deposit === null || $deposit == 0.0) && ($withdraw === null || $withdraw == 0.0) && $amount !== null) {
            $direction = $this->call('normalizeTransactionDirection', (string) ($row['transaction_direction'] ?? $row['bank_direction'] ?? ''));
            if ($direction === '') {
                $legacyTypeDirection = $this->call('normalizeTransactionDirection', (string) ($row['transaction_type'] ?? ''));
                if (in_array($legacyTypeDirection, ['IN', 'OUT'], true)) {
                    $direction = $legacyTypeDirection;
                }
            }
            if ($direction === 'OUT') {
                $withdraw = abs($amount);
            } else {
                $deposit = abs($amount);
            }
        }

        if ($deposit !== null) {
            $row['deposit_amount'] = $deposit;
        }
        if ($withdraw !== null) {
            $row['withdraw_amount'] = $withdraw;
            $row['withdrawal_amount'] = $withdraw;
        }
        if (!isset($row['total_amount']) || $this->call('amountOrNull', $row['total_amount']) === null) {
            $row['total_amount'] = (float) ($deposit && $deposit != 0.0 ? $deposit : ($withdraw ?? 0));
        }

        if (empty($row['transaction_direction'])) {
            if ($withdraw !== null && $withdraw > 0) {
                $row['transaction_direction'] = 'OUT';
            } elseif ($deposit !== null && $deposit > 0) {
                $row['transaction_direction'] = 'IN';
            }
        }

        $timeValue = trim((string) ($row['transaction_time'] ?? ''));
        if ($timeValue !== '' && preg_match('/\d{4}[-\/.]\d{1,2}[-\/.]\d{1,2}|\d{1,2}[-\/.]\d{1,2}[-\/.]\d{2,4}/', $timeValue)) {
            $row['transaction_datetime'] = $row['transaction_datetime'] ?? $timeValue;
            if (empty($row['transaction_date'])) {
                $row['transaction_date'] = $this->call('dateValue', $timeValue);
            }
            if (preg_match('/(\d{1,2}:\d{2}(?::\d{2})?)/', $timeValue, $match)) {
                $row['transaction_time'] = $match[1];
            } else {
                unset($row['transaction_time']);
            }
        }

        $dateTime = trim((string) ($row['transaction_datetime'] ?? $row['transaction_at'] ?? ''));
        if ($dateTime === '' && !empty($row['transaction_date']) && preg_match('/\d{1,2}:\d{2}/', (string) $row['transaction_date'])) {
            $dateTime = trim((string) $row['transaction_date']);
            $row['transaction_datetime'] = $row['transaction_datetime'] ?? $dateTime;
        }
        if ($dateTime !== '') {
            $row['transaction_date'] = $this->call('dateValue', $dateTime) ?: ($row['transaction_date'] ?? null);
            if (empty($row['transaction_time']) && preg_match('/(\d{1,2}:\d{2}(?::\d{2})?)/', $dateTime, $match)) {
                $row['transaction_time'] = $match[1];
            }
        }

        $counterpartyName = $this->bankCounterpartyName($row);
        if ($counterpartyName !== '') {
            $row['counterparty_name'] = $counterpartyName;
            $row['client_company_name'] = $counterpartyName;
        } elseif (!empty($row['client_company_name']) && $this->looksLikeBankAccountNumber((string) $row['client_company_name'])) {
            $row['counterparty_account_number'] = $row['counterparty_account_number'] ?? $row['client_company_name'];
            unset($row['client_company_name']);
        }

        foreach (['counterparty_account_number', 'counterparty_account_no', 'account_number', 'client_business_number'] as $key) {
            if (!empty($row[$key]) && $this->looksLikeBankAccountNumber((string) $row[$key])) {
                $row['counterparty_account_number'] = $row['counterparty_account_number'] ?? $row[$key];
                if ($key === 'client_business_number') {
                    unset($row['client_business_number']);
                }
                break;
            }
        }
        foreach (['counterparty_bank_name', 'counterparty_bank', 'bank_name'] as $key) {
            if (!empty($row[$key])) {
                $row['counterparty_bank_name'] = $row['counterparty_bank_name'] ?? $row[$key];
                break;
            }
        }

        return $row;
    }

    public function bankCounterpartyName(array $row): string
    {
        foreach (['counterparty_name', 'counterparty_account_holder_name', 'counterparty_account_holder', 'account_holder', 'client_company_name', 'client_name'] as $key) {
            $value = $this->call('cleanCompanyName', (string) ($row[$key] ?? ''));
            if ($value !== '' && !$this->looksLikeBankAccountNumber($value)) {
                return $value;
            }
        }

        return '';
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
