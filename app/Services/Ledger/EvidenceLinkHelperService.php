<?php

namespace App\Services\Ledger;

use PDO;

class EvidenceLinkHelperService
{
    public function __construct(private PDO $pdo, private array $callbacks = [])
    {
    }

    public function deleteEvidencePurgeDependencies(array $evidenceIds): void
    {
        $evidenceIds = $this->normalizeIds($evidenceIds);
        if ($evidenceIds === []) {
            return;
        }

        $this->call('deleteBankTransactionsByEvidenceIds', $evidenceIds);
        $this->deleteEvidenceLinksByEvidenceIds($evidenceIds);
        $this->detachEvidenceSourceRefs($evidenceIds);
        $this->deleteProcessingItemsByEvidenceIds($evidenceIds);
    }

    public function softDeleteEvidenceLinksByEvidenceIds(array $evidenceIds): void
    {
        $evidenceIds = $this->normalizeIds($evidenceIds);
        if ($evidenceIds === [] || !$this->call('tableExists', 'ledger_evidence_links')) {
            return;
        }

        [$inSql, $params] = $this->call('placeholdersForIds', $evidenceIds, 'soft_delete_link_id');
        $this->pdo->prepare("
            UPDATE ledger_evidence_links
            SET deleted_at = NOW(),
                updated_at = NOW()
            WHERE evidence_id IN ({$inSql})
              AND deleted_at IS NULL
        ")->execute($params);
    }

    public function deleteEvidenceLinksByEvidenceIds(array $evidenceIds): void
    {
        $evidenceIds = $this->normalizeIds($evidenceIds);
        if ($evidenceIds === [] || !$this->call('tableExists', 'ledger_evidence_links')) {
            return;
        }

        [$inSql, $params] = $this->call('placeholdersForIds', $evidenceIds, 'purge_link_evidence_id');
        $this->pdo->prepare("
            DELETE FROM ledger_evidence_links
            WHERE evidence_id IN ({$inSql})
        ")->execute($params);
    }

    public function detachEvidenceSourceRefs(array $evidenceIds): void
    {
        $evidenceIds = $this->normalizeIds($evidenceIds);
        if ($evidenceIds === []) {
            return;
        }

        if ($this->call('tableExists', 'ledger_vouchers') && $this->call('tableColumnExists', 'ledger_vouchers', 'source_id')) {
            [$inSql, $params] = $this->call('placeholdersForIds', $evidenceIds, 'purge_voucher_source_id');
            $this->pdo->prepare("
                UPDATE ledger_vouchers
                SET source_id = NULL
                WHERE source_id IN ({$inSql})
            ")->execute($params);
        }

        if ($this->call('tableExists', 'ledger_transactions') && $this->call('tableColumnExists', 'ledger_transactions', 'evidence_id')) {
            [$inSql, $params] = $this->call('placeholdersForIds', $evidenceIds, 'purge_transaction_evidence_id');
            $this->pdo->prepare("
                UPDATE ledger_transactions
                SET evidence_id = NULL
                WHERE evidence_id IN ({$inSql})
            ")->execute($params);
        }
    }

    public function deleteProcessingItemsByEvidenceIds(array $evidenceIds): void
    {
        $evidenceIds = $this->normalizeIds($evidenceIds);
        if ($evidenceIds === [] || !$this->call('tableExists', 'ledger_processing_items')) {
            return;
        }

        [$inSql, $params] = $this->call('placeholdersForIds', $evidenceIds, 'purge_processing_source_id');
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM ledger_processing_items
            WHERE source_table = 'ledger_data_evidences'
              AND source_id IN ({$inSql})
        ");
        $stmt->execute($params);
        $itemIds = $this->normalizeIds($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        if ($itemIds === []) {
            return;
        }

        if ($this->call('tableExists', 'ledger_processing_item_actions')) {
            [$actionInSql, $actionParams] = $this->call('placeholdersForIds', $itemIds, 'purge_processing_action_id');
            [$relatedInSql, $relatedParams] = $this->call('placeholdersForIds', $itemIds, 'purge_processing_related_id');
            $this->pdo->prepare("
                DELETE FROM ledger_processing_item_actions
                WHERE processing_item_id IN ({$actionInSql})
                   OR related_processing_item_id IN ({$relatedInSql})
            ")->execute($actionParams + $relatedParams);
        }

        foreach (['ledger_transaction_lines', 'ledger_voucher_lines'] as $table) {
            if (!$this->call('tableExists', $table) || !$this->call('tableColumnExists', $table, 'processing_item_id')) {
                continue;
            }
            [$lineInSql, $lineParams] = $this->call('placeholdersForIds', $itemIds, 'purge_' . $table . '_item_id');
            $this->pdo->prepare("
                UPDATE {$table}
                SET processing_item_id = NULL
                WHERE processing_item_id IN ({$lineInSql})
            ")->execute($lineParams);
        }

        [$itemInSql, $itemParams] = $this->call('placeholdersForIds', $itemIds, 'purge_processing_item_id');
        $this->pdo->prepare("
            DELETE FROM ledger_processing_items
            WHERE id IN ({$itemInSql})
        ")->execute($itemParams);
    }

    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('strval', $ids))));
    }

    private function call(string $name, mixed ...$args): mixed
    {
        if (!isset($this->callbacks[$name])) {
            throw new \RuntimeException('Missing callback: ' . $name);
        }

        return ($this->callbacks[$name])(...$args);
    }
}
