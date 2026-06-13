<?php

namespace App\Services\Ledger;

use PDO;

class EvidenceLifecycleService
{
    public function __construct(private PDO $pdo, private array $callbacks = [])
    {
    }

    public function purgeSeedRowsByIds(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
        if ($ids === []) {
            return 0;
        }

        $this->pdo->beginTransaction();
        try {
            $this->deleteEvidencePurgeDependencies($ids);

            [$inSql, $params] = $this->placeholdersForIds($ids, 'purge_delete_seed_id');
            $this->deleteEvidenceBodyByEvidenceIds($ids);
            $this->deleteEvidenceProcessingByEvidenceIds($ids);

            $stmt = $this->pdo->prepare("
                DELETE FROM ledger_evidence_payloads
                WHERE evidence_id IN ({$inSql})
                  AND deleted_at IS NOT NULL
            ");
            $stmt->execute($params);
            $deletedCount = $stmt->rowCount();

            $this->pdo->commit();
            return $deletedCount;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function deleteEvidenceProcessingByEvidenceIds(array $evidenceIds): void
    {
        $evidenceIds = array_values(array_unique(array_filter(array_map('strval', $evidenceIds))));
        if ($evidenceIds === [] || !$this->tableExists('ledger_evidence_processing')) {
            return;
        }

        [$inSql, $params] = $this->placeholdersForIds($evidenceIds, 'delete_processing_id');
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM ledger_evidence_processing
            WHERE evidence_id IN ({$inSql})
        ");
        $stmt->execute($params);
        $processingIds = array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));
        if ($processingIds !== [] && $this->tableExists('ledger_evidence_processing_logs')) {
            [$logInSql, $logParams] = $this->placeholdersForIds($processingIds, 'delete_processing_log_id');
            $this->pdo->prepare("
                DELETE FROM ledger_evidence_processing_logs
                WHERE processing_id IN ({$logInSql})
            ")->execute($logParams);
        }

        $this->pdo->prepare("
            DELETE FROM ledger_evidence_processing
            WHERE evidence_id IN ({$inSql})
        ")->execute($params);
    }

    public function deleteEvidenceBodyByEvidenceIds(array $evidenceIds): void
    {
        $evidenceIds = array_values(array_unique(array_filter(array_map('strval', $evidenceIds))));
        if ($evidenceIds === []) {
            return;
        }

        foreach ($this->evidenceBodyTables() as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            [$inSql, $params] = $this->placeholdersForIds($evidenceIds, 'delete_' . $table . '_id');
            $this->pdo->prepare("
                DELETE FROM {$table}
                WHERE id IN ({$inSql})
            ")->execute($params);
        }
    }

    public function syncBankTransactionsSoftDelete(array $evidenceIds, string $actor): void
    {
        $evidenceIds = array_values(array_unique(array_filter(array_map('strval', $evidenceIds))));
        if ($evidenceIds === [] || !$this->tableExists('ledger_bank_transactions')) {
            return;
        }

        [$inSql, $params] = $this->placeholdersForIds($evidenceIds, 'bank_soft_delete_id');
        $params[':deleted_by'] = $actor;
        $params[':updated_by'] = $actor;
        $stmt = $this->pdo->prepare("
            UPDATE ledger_bank_transactions
            SET deleted_at = NOW(),
                deleted_by = :deleted_by,
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE evidence_id IN ({$inSql})
              AND deleted_at IS NULL
        ");
        $stmt->execute($params);
    }

    public function syncBankTransactionsRestore(array $evidenceIds, string $actor): void
    {
        $evidenceIds = array_values(array_unique(array_filter(array_map('strval', $evidenceIds))));
        if ($evidenceIds === [] || !$this->tableExists('ledger_bank_transactions')) {
            return;
        }

        [$inSql, $params] = $this->placeholdersForIds($evidenceIds, 'bank_restore_id');
        $params[':updated_by'] = $actor;
        $stmt = $this->pdo->prepare("
            UPDATE ledger_bank_transactions
            SET deleted_at = NULL,
                deleted_by = NULL,
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE evidence_id IN ({$inSql})
        ");
        $stmt->execute($params);
    }

    public function deleteBankTransactionsByEvidenceIds(array $evidenceIds): void
    {
        $evidenceIds = array_values(array_unique(array_filter(array_map('strval', $evidenceIds))));
        if ($evidenceIds === [] || !$this->tableExists('ledger_bank_transactions')) {
            return;
        }

        [$inSql, $params] = $this->placeholdersForIds($evidenceIds, 'bank_purge_id');
        $stmt = $this->pdo->prepare("
            DELETE FROM ledger_bank_transactions
            WHERE evidence_id IN ({$inSql})
        ");
        $stmt->execute($params);
    }

    private function deleteEvidencePurgeDependencies(array $ids): void
    {
        $this->call('deleteEvidencePurgeDependencies', $ids);
    }

    private function evidenceBodyTables(): array
    {
        return $this->call('evidenceBodyTables');
    }

    private function placeholdersForIds(array $ids, string $prefix): array
    {
        return $this->call('placeholdersForIds', $ids, $prefix);
    }

    private function tableExists(string $tableName): bool
    {
        return $this->call('tableExists', $tableName);
    }

    private function call(string $name, mixed ...$args): mixed
    {
        if (!isset($this->callbacks[$name])) {
            throw new \RuntimeException('Missing callback: ' . $name);
        }

        return ($this->callbacks[$name])(...$args);
    }
}
