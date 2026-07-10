<?php

namespace App\Repositories\Ledger;

use PDO;

class EvidenceMetadataRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function sourceTables(): array
    {
        $stmt = $this->pdo->query("
            SELECT TABLE_NAME AS name, COALESCE(NULLIF(TABLE_COMMENT, ''), TABLE_NAME) AS label
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_TYPE = 'BASE TABLE'
              AND (TABLE_NAME LIKE 'ledger_evidence_%' OR TABLE_NAME = 'ledger_bank_transactions')
              AND TABLE_NAME NOT IN ('ledger_evidence_metadata', 'ledger_evidence_metadata_columns')
            ORDER BY TABLE_NAME ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function sourceColumns(string $tableName): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COLUMN_NAME AS name,
                COALESCE(NULLIF(COLUMN_COMMENT, ''), COLUMN_NAME) AS label,
                DATA_TYPE AS data_type,
                IS_NULLABLE AS is_nullable,
                ORDINAL_POSITION AS ordinal_position
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
            ORDER BY ORDINAL_POSITION ASC
        ");
        $stmt->execute([':table_name' => $tableName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function recommendSourceTable(string $importType): ?string
    {
        $tables = array_column($this->sourceTables(), 'name');
        $expected = 'ledger_evidence_' . strtolower($importType);
        if (in_array($expected, $tables, true)) {
            return $expected;
        }

        return null;
    }

    public function activeImportTypes(): array
    {
        $stmt = $this->pdo->query("
            SELECT code, code_name
            FROM system_codes
            WHERE code_group = 'IMPORT_TYPE'
              AND is_active = 1
              AND deleted_at IS NULL
            ORDER BY sort_no ASC, code ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function tableExists(string $tableName): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_TYPE = 'BASE TABLE'
              AND TABLE_NAME = :table_name
              AND (TABLE_NAME LIKE 'ledger_evidence_%' OR TABLE_NAME = 'ledger_bank_transactions')
              AND TABLE_NAME NOT IN ('ledger_evidence_metadata', 'ledger_evidence_metadata_columns')
        ");
        $stmt->execute([':table_name' => $tableName]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function activeImportTypeExists(string $importType): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM system_codes
            WHERE code_group = 'IMPORT_TYPE'
              AND code = :code
              AND is_active = 1
              AND deleted_at IS NULL
        ");
        $stmt->execute([':code' => $importType]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
