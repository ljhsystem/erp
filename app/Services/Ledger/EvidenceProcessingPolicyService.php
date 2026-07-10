<?php

namespace App\Services\Ledger;

use PDO;

class EvidenceProcessingPolicyService
{
    public function __construct(
        private PDO $pdo,
        private $tableExists
    ) {
    }

    private function tableExists(string $table): bool
    {
        return ($this->tableExists)($table);
    }

    public function hasProcessingTable(): bool
    {
        return $this->tableExists('ledger_evidence_processing');
    }

    public function joinForBody(string $bodyAlias, string $sourceTypeSql): string
    {
        if (!$this->hasProcessingTable()) {
            return '';
        }

        $operator = str_starts_with(trim($sourceTypeSql), '(') ? 'IN' : '=';

        return "
            LEFT JOIN ledger_evidence_processing pr
                ON pr.evidence_type COLLATE utf8mb4_general_ci {$operator} {$sourceTypeSql} COLLATE utf8mb4_general_ci
               AND pr.evidence_id COLLATE utf8mb4_general_ci = {$bodyAlias}.id COLLATE utf8mb4_general_ci
               AND pr.deleted_at IS NULL
        ";
    }

    public function filteredStatusHasRows(string $status): bool
    {
        if ($this->hasProcessingTable()) {
            return true;
        }

        return !in_array($status, ['PROCESSED', 'ERROR', 'DUPLICATED'], true);
    }

    public function statusSelect(string $default = 'READY'): string
    {
        if (!$this->hasProcessingTable()) {
            return "'" . addslashes($default) . "'";
        }

        return "COALESCE(pr.processing_status, '" . addslashes($default) . "')";
    }

    public function reviewStatusSelect(string $default = 'NORMAL'): string
    {
        if (!$this->hasProcessingTable()) {
            return "'" . addslashes($default) . "'";
        }

        return "COALESCE(pr.review_status, '" . addslashes($default) . "')";
    }

    public function errorMessageSelect(): string
    {
        return $this->hasProcessingTable() ? 'pr.last_error_message' : 'NULL';
    }
}
