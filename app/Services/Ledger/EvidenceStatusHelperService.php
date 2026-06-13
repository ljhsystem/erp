<?php

namespace App\Services\Ledger;

use PDO;

class EvidenceStatusHelperService
{
    public function __construct(private PDO $pdo, private array $callbacks = [])
    {
    }

    public function applyReadinessToEvidenceRow(array &$row): void
    {
        $payload = is_array($row['mapped_payload'] ?? null) ? $row['mapped_payload'] : [];
        $baseStatus = strtoupper(trim((string) ($row['process_status'] ?? $row['status'] ?? '')));
        $readiness = $this->call('readinessForEvidenceRow', $row, $payload);

        $row['readiness_status'] = $readiness['status'];
        $row['readiness_errors'] = $readiness['errors'];
        $row['missing_fields'] = $readiness['missing_fields'];
        $row['processing_type'] = $readiness['processing_type'];
        $row['processing_objects'] = $readiness['processing_objects'];
        $row['processing_label'] = $readiness['processing_label'];
        $row['generation_target'] = $readiness['generation_target'];
        $row['generation_objects'] = $readiness['generation_objects'];
        $row['generation_label'] = $readiness['generation_label'];

        if (in_array($baseStatus, ['', 'READY'], true)) {
            $row['process_status'] = $readiness['status'];
            $row['status'] = $readiness['status'];
        }
    }

    public function hasActiveOutputForEvidenceRow(array $row): bool
    {
        $rowId = (string) ($row['id'] ?? '');
        $transactionId = trim((string) ($row['transaction_id'] ?? ''));
        if ($transactionId !== '' && $this->activeTransactionExists($transactionId)) {
            return true;
        }

        if ($this->activeVoucherExistsForEvidence($rowId, $transactionId)) {
            return true;
        }

        return $this->activeTransactionExistsForEvidenceFingerprint($row);
    }

    public function activeTransactionExists(string $transactionId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM ledger_transactions
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $transactionId]);

        return (bool) $stmt->fetchColumn();
    }

    public function activeVoucherExistsForEvidence(string $evidenceId, string $transactionId = ''): bool
    {
        if ($evidenceId === '') {
            return false;
        }

        if ($this->call('tableExists', 'ledger_evidence_links')) {
            $stmt = $this->pdo->prepare("
                SELECT 1
                FROM ledger_evidence_links l
                INNER JOIN ledger_vouchers v
                    ON v.id = l.target_id
                   AND v.deleted_at IS NULL
                WHERE l.evidence_id = :evidence_id
                  AND l.target_type = 'VOUCHER'
                  AND l.deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([':evidence_id' => $evidenceId]);
            if ($stmt->fetchColumn()) {
                return true;
            }
        }

        $conditions = [];
        $params = [];
        if ($this->call('tableColumnExists', 'ledger_vouchers', 'source_id')) {
            $conditions[] = 'source_id = :source_id';
            $params[':source_id'] = $evidenceId;
        }
        if ($transactionId !== '' && $this->call('tableColumnExists', 'ledger_vouchers', 'transaction_id')) {
            $conditions[] = 'transaction_id = :transaction_id';
            $params[':transaction_id'] = $transactionId;
        }
        if ($conditions === []) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM ledger_vouchers
            WHERE (" . implode(' OR ', $conditions) . ")
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function activeTransactionExistsForEvidenceFingerprint(array $row): bool
    {
        $mapped = json_decode((string) ($row['mapped_payload_json'] ?? ''), true);
        if (!is_array($mapped)) {
            return false;
        }

        $sourceType = $this->call('normalizeDataType', (string) ($row['source_type'] ?? ''));
        $transactionDate = $this->call('dateValue', $mapped['transaction_date'] ?? $mapped['evidence_date'] ?? $row['evidence_date'] ?? '');
        $amount = $this->call('amountOrNull', $mapped['total_amount'] ?? $mapped['amount'] ?? null);
        if ($amount === null) {
            $supply = (float) ($this->call('amountOrNull', $mapped['supply_amount'] ?? null) ?? 0);
            $vat = (float) ($this->call('amountOrNull', $mapped['vat_amount'] ?? null) ?? 0);
            $amount = $supply + $vat;
        }
        if ($sourceType === '' || $transactionDate === '' || $amount === null) {
            return false;
        }

        $description = trim((string) ($mapped['description'] ?? ''));
        $params = [
            ':import_type' => $sourceType,
            ':transaction_date' => $transactionDate,
            ':total_amount' => (float) $amount,
        ];
        $descriptionSql = '';
        if ($description !== '') {
            $descriptionSql = 'AND (description = :description OR description IS NULL OR description = \'\')';
            $params[':description'] = $description;
        }

        $stmt = $this->pdo->prepare("
            SELECT id
            FROM ledger_transactions
            WHERE import_type = :import_type
              AND transaction_date = :transaction_date
              AND ABS(COALESCE(total_amount, 0) - :total_amount) < 0.01
              AND deleted_at IS NULL
              {$descriptionSql}
            LIMIT 1
        ");
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function uploadVoucherStatus(string $dataType, array $payload, string $processStatus): string
    {
        if ($processStatus === 'ERROR') {
            return 'ERROR';
        }

        if ($this->call('normalizeDataType', $dataType) !== 'BANK_TRANSACTION') {
            return 'NONE';
        }

        if (!$this->call('hasVoucherLinesPayload', $payload)) {
            return 'WAITING';
        }

        return $this->call('bankVoucherValidationMessage', $payload) === null ? 'READY' : 'ERROR';
    }

    public function evidenceHasTransactionIdColumn(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'ledger_data_evidences'
              AND COLUMN_NAME = 'transaction_id'
            LIMIT 1
        ");
        $stmt->execute();
        $exists = (bool) $stmt->fetchColumn();

        return $exists;
    }

    public function evidenceTransactionIdSelect(string $alias = ''): string
    {
        if (!$this->evidenceHasTransactionIdColumn()) {
            return 'NULL AS transaction_id';
        }

        return ($alias !== '' ? $alias . '.' : '') . 'transaction_id';
    }

    public function evidenceCreatedTransactionSql(string $alias = ''): string
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        $conditions = [
            "{$prefix}transaction_status IN ('CREATED', 'PROCESSED', 'DONE', 'COMPLETED', 'POSTED')",
        ];
        if ($this->evidenceHasTransactionIdColumn()) {
            $conditions[] = "({$prefix}transaction_id IS NOT NULL AND {$prefix}transaction_id <> '')";
        }

        return '(' . implode(' OR ', $conditions) . ')';
    }

    public function bankBalanceStatus(mixed $value): string
    {
        if ($this->call('amountOrNull', $value) !== null) {
            return 'ACTUAL';
        }

        $text = trim((string) ($value ?? ''));
        if ($text === '' || $text === '-' || strcasecmp($text, 'NaN') === 0) {
            return 'EMPTY';
        }

        return 'INVALID';
    }

    private function call(string $name, mixed ...$args): mixed
    {
        if (!isset($this->callbacks[$name])) {
            throw new \RuntimeException('Missing callback: ' . $name);
        }

        return ($this->callbacks[$name])(...$args);
    }
}
