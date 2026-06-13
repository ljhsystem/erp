<?php

namespace App\Services\Ledger;

use PDO;

class EvidenceDeleteRestoreService
{
    public function __construct(private PDO $pdo, private array $callbacks = [])
    {
    }

    public function softDeleteEvidenceProcessingByEvidenceIds(array $evidenceIds, string $actor): void
    {
        $evidenceIds = array_values(array_unique(array_filter(array_map('strval', $evidenceIds))));
        if ($evidenceIds === [] || !$this->tableExists('ledger_evidence_processing')) {
            return;
        }

        [$inSql, $params] = $this->placeholdersForIds($evidenceIds, 'soft_delete_processing_id');
        $this->pdo->prepare("
            UPDATE ledger_evidence_processing
            SET deleted_at = NOW(),
                updated_at = NOW()
            WHERE evidence_id IN ({$inSql})
              AND deleted_at IS NULL
        ")->execute($params);
    }

    public function restoreEvidenceProcessingByEvidenceIds(array $evidenceIds, string $actor): void
    {
        $evidenceIds = array_values(array_unique(array_filter(array_map('strval', $evidenceIds))));
        if ($evidenceIds === [] || !$this->tableExists('ledger_evidence_processing')) {
            return;
        }

        [$inSql, $params] = $this->placeholdersForIds($evidenceIds, 'restore_processing_id');
        $this->pdo->prepare("
            UPDATE ledger_evidence_processing
            SET deleted_at = NULL,
                updated_at = NOW()
            WHERE evidence_id IN ({$inSql})
        ")->execute($params);
    }

    public function softDeleteEvidenceBodyByEvidenceIds(array $evidenceIds, string $actor): void
    {
        $this->updateEvidenceBodyDeletedAtByEvidenceIds($evidenceIds, 'NOW()');
    }

    public function restoreEvidenceBodyByEvidenceIds(array $evidenceIds, string $actor): void
    {
        $this->updateEvidenceBodyDeletedAtByEvidenceIds($evidenceIds, 'NULL');
    }

    public function updateEvidenceBodyDeletedAtByEvidenceIds(array $evidenceIds, string $deletedAtSql): void
    {
        $evidenceIds = array_values(array_unique(array_filter(array_map('strval', $evidenceIds))));
        if ($evidenceIds === []) {
            return;
        }

        foreach ($this->evidenceBodyTables() as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            [$inSql, $params] = $this->placeholdersForIds($evidenceIds, 'body_' . $table . '_id');
            $this->pdo->prepare("
                UPDATE {$table}
                SET deleted_at = {$deletedAtSql},
                    updated_at = NOW()
                WHERE id IN ({$inSql})
            ")->execute($params);
        }
    }

    public function evidenceBodyTables(): array
    {
        return [
            'ledger_evidence_bank',
            'ledger_evidence_tax_invoice',
            'ledger_evidence_cash_receipt',
            'ledger_evidence_card_purchase',
        ];
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
