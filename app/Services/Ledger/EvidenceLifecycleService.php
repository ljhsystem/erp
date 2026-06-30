<?php

namespace App\Services\Ledger;

use PDO;

class EvidenceLifecycleService
{
    public function __construct(private PDO $pdo, private array $callbacks = [])
    {
    }

    public function purgeSeedRowsByIds(array $ids, ?string $evidenceType = null): int
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
        if ($ids === []) {
            return 0;
        }

        $this->pdo->beginTransaction();
        try {
            $this->deleteEvidencePurgeDependencies($ids);
            $this->deleteLegacyEvidenceRowsByIds($ids);

            [$inSql, $params] = $this->placeholdersForIds($ids, 'purge_delete_seed_id');
            $this->deleteEvidenceBodyByEvidenceIds($ids, $evidenceType);
            $this->deleteEvidenceProcessingByEvidenceIds($ids);

            $stmt = $this->pdo->prepare("
                DELETE FROM ledger_evidence_payloads
                WHERE evidence_id IN ({$inSql})
                  AND deleted_at IS NOT NULL
            ");
            $stmt->execute($params);
            $deletedCount = max($stmt->rowCount(), count($ids));

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

    public function deleteEvidenceBodyByEvidenceIds(array $evidenceIds, ?string $evidenceType = null): void
    {
        if ($evidenceType !== null) {
            $evidenceType = strtoupper(trim($evidenceType));
        }
        $evidenceIds = array_values(array_unique(array_filter(array_map('strval', $evidenceIds))));
        if ($evidenceIds === []) {
            return;
        }

        $tables = $evidenceType === ''
            ? $this->evidenceBodyTables()
            : $this->evidenceBodyTablesForType($evidenceType);
        if ($tables === []) {
            $tables = $this->evidenceBodyTables();
        }
        foreach ($tables as $table) {
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

    public function deleteLegacyEvidenceRowsByIds(array $evidenceIds): void
    {
        $evidenceIds = array_values(array_unique(array_filter(array_map('strval', $evidenceIds))));
        if ($evidenceIds === [] || !$this->tableExists('ledger_data_evidences')) {
            return;
        }

        [$inSql, $params] = $this->placeholdersForIds($evidenceIds, 'legacy_evidence_purge_id');
        $stmt = $this->pdo->prepare("
            DELETE FROM ledger_data_evidences
            WHERE id IN ({$inSql})
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

    private function evidenceBodyTablesForType(?string $evidenceType): array
    {
        return match ($evidenceType) {
            'BANK_TRANSACTION' => ['ledger_evidence_bank_transaction'],
            'TAX_INVOICE' => ['ledger_evidence_tax_invoice'],
            'TAX_INVOICE_MANUAL' => ['ledger_evidence_tax_invoice_manual'],
            'CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES' => ['ledger_evidence_cash_receipt'],
            'CARD_HOMETAX' => ['ledger_evidence_card_hometax'],
            'CARD', 'CARD_STATEMENT', 'CARD_APPROVAL' => ['ledger_evidence_card_statement'],
            'EMPLOYEE_EXPENSE' => ['ledger_evidence_employee_expense'],
            'PAYROLL', 'PAYROLL_WITHHOLDING' => ['ledger_evidence_payroll'],
            'BUSINESS_INCOME' => ['ledger_evidence_business_income'],
            'BUSINESS_DATA' => ['ledger_evidence_cash_sales'],
            'CONSTRUCTION' => ['ledger_evidence_daily_worker'],
            'SHOPPING_ORDER', 'IMPORT_INVOICE' => ['ledger_evidence_business_data'],
            default => $this->evidenceBodyTables(),
        };
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
