<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceLinkModel;
use App\Models\Ledger\EvidenceImportModel;
use PDO;

class EvidenceStatusHelperService
{
    private EvidenceLinkModel $linkModel;
    private EvidenceImportModel $evidenceModel;

    public function __construct(PDO $pdo, private array $callbacks = [])
    {
        $this->linkModel = new EvidenceLinkModel($pdo);
        $this->evidenceModel = new EvidenceImportModel($pdo);
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
        $importType = strtoupper(trim((string) ($row['import_type'] ?? '')));
        $evidenceId = trim((string) ($row['id'] ?? ''));

        return $importType !== ''
            && $evidenceId !== ''
            && $this->linkModel->hasActiveLink($importType, $evidenceId);
    }

    public function activeVoucherExistsForEvidence(string $importType, string $evidenceId): bool
    {
        return $this->linkModel->findLinkedVoucherInfo($importType, $evidenceId) !== null;
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

        $exists = false;

        return $exists;
    }

    public function evidenceTransactionIdSelect(string $alias = ''): string
    {
        return 'NULL AS transaction_id';
    }

    public function evidenceCreatedTransactionSql(string $alias = ''): string
    {
        return $this->evidenceModel->createdTransactionCondition($alias);
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
