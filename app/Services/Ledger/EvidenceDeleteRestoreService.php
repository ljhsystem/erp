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

    public function softDeleteEvidenceBodyByEvidenceIds(array $evidenceIds, string $actor, ?string $evidenceType = null): void
    {
        $this->updateEvidenceBodyDeletedAtByEvidenceIds($evidenceIds, $actor, true, $evidenceType);
    }

    public function restoreEvidenceBodyByEvidenceIds(array $evidenceIds, string $actor, ?string $evidenceType = null): void
    {
        $this->updateEvidenceBodyDeletedAtByEvidenceIds($evidenceIds, $actor, false, $evidenceType);
    }

    public function updateEvidenceBodyDeletedAtByEvidenceIds(
        array $evidenceIds,
        string $actor,
        bool $isDelete,
        ?string $evidenceType = null
    ): void {
        if ($evidenceType !== null) {
            $evidenceType = strtoupper(trim($evidenceType));
        }

        $tables = $evidenceType === '' || $evidenceType === null
            ? $this->evidenceBodyTables()
            : $this->bodyTablesForEvidenceType($evidenceType);

        $tables = array_values(array_filter(
            array_unique(array_map('trim', $tables)),
            static fn(string $table) => $table !== ''
        ));
        $existingTables = [];
        foreach ($tables as $table) {
            if ($this->tableExists($table)) {
                $existingTables[] = $table;
            }
        }
        if ($existingTables === []) {
            $existingTables = array_values(array_filter($this->evidenceBodyTables(), fn(string $table) => $this->tableExists($table)));
        }
        $tables = $existingTables;

        $evidenceIds = array_values(array_unique(array_filter(array_map('strval', $evidenceIds))));
        if ($evidenceIds === []) {
            return;
        }

        foreach ($tables as $table) {
            [$inSql, $params] = $this->placeholdersForIds($evidenceIds, 'body_' . $table . '_id');
            $sets = [];
            $queryParams = $params;
            if ($this->tableColumnExists($table, 'deleted_at')) {
                $sets[] = 'deleted_at = ' . ($isDelete ? 'NOW()' : 'NULL');
            }
            if ($this->tableColumnExists($table, 'deleted_by')) {
                if ($isDelete) {
                    $sets[] = 'deleted_by = :deleted_by';
                    $queryParams[':deleted_by'] = $actor;
                } else {
                    $sets[] = 'deleted_by = NULL';
                }
            }
            if ($this->tableColumnExists($table, 'updated_at')) {
                $sets[] = 'updated_at = NOW()';
            }
            if ($this->tableColumnExists($table, 'updated_by')) {
                $sets[] = 'updated_by = :updated_by';
                $queryParams[':updated_by'] = $actor;
            }
            if ($sets === []) {
                continue;
            }

            $this->pdo->prepare("
                UPDATE {$table}
                SET " . implode(",\n                    ", $sets) . "
                WHERE id IN ({$inSql})
            ")->execute($queryParams);
        }
    }

    public function bodyTablesForEvidenceType(?string $evidenceType): array
    {
        return match ($evidenceType) {
            'BANK_TRANSACTION' => ['ledger_evidence_bank_transaction'],
            'TAX_INVOICE' => ['ledger_evidence_tax_invoice'],
            'TAX_INVOICE_MANUAL' => ['ledger_evidence_tax_invoice_manual'],
            'CASH_RECEIPT' => ['ledger_evidence_cash_receipt'],
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

    public function evidenceBodyTables(): array
    {
        return [
            'ledger_evidence_bank_transaction',
            'ledger_evidence_tax_invoice',
            'ledger_evidence_tax_invoice_manual',
            'ledger_evidence_cash_receipt',
            'ledger_evidence_card_hometax',
            'ledger_evidence_card_statement',
            'ledger_evidence_card_sales',
            'ledger_evidence_employee_expense',
            'ledger_evidence_payroll',
            'ledger_evidence_daily_worker',
            'ledger_evidence_business_income',
            'ledger_evidence_cash_sales',
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

    private function tableColumnExists(string $tableName, string $columnName): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
            LIMIT 1
        ");
        $stmt->execute([
            ':table_name' => $tableName,
            ':column_name' => $columnName,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function call(string $name, mixed ...$args): mixed
    {
        if (!isset($this->callbacks[$name])) {
            throw new \RuntimeException('Missing callback: ' . $name);
        }

        return ($this->callbacks[$name])(...$args);
    }
}
