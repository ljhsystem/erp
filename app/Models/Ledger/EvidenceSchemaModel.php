<?php

namespace App\Models\Ledger;

use Core\Database;
use PDO;

class EvidenceSchemaModel
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function columnExists(string $tableName, string $columnName): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
            LIMIT 1
        ");
        $stmt->execute([':table_name' => $tableName, ':column_name' => $columnName]);
        return (bool) $stmt->fetchColumn();
    }

    public function tableExists(string $tableName): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1 FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
            LIMIT 1
        ");
        $stmt->execute([':table_name' => $tableName]);
        return (bool) $stmt->fetchColumn();
    }

    public function columns(string $tableName): array
    {
        $stmt = $this->db->prepare("SELECT COLUMN_NAME, COLUMN_COMMENT, DATA_TYPE, IS_NULLABLE, ORDINAL_POSITION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name ORDER BY ORDINAL_POSITION ASC");
        $stmt->execute([':table_name' => $tableName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function columnInfo(string $tableName, string $columnName): array
    {
        $stmt = $this->db->prepare("SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, ORDINAL_POSITION, COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name LIMIT 1");
        $stmt->execute([':table_name' => $tableName, ':column_name' => $columnName]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function firstExistingColumnExpression(string $tableName, string $alias, array $candidates, string $fallback = 'NULL'): string
    {
        $this->assertIdentifier($alias);
        foreach ($candidates as $column) {
            $column = trim((string) $column);
            if ($column !== '') { $this->assertIdentifier($column); }
            if ($column !== '' && $this->columnExists($tableName, $column)) return $alias . '.' . $column;
        }
        return $fallback;
    }

    public function coalesceExistingColumnExpression(string $tableName, string $alias, array $candidates, string $fallback = 'NULL'): string
    {
        $this->assertIdentifier($alias); $expressions = [];
        foreach ($candidates as $column) {
            $column = trim((string) $column);
            if ($column !== '') { $this->assertIdentifier($column); }
            if ($column !== '' && $this->columnExists($tableName, $column)) $expressions[] = "NULLIF(TRIM({$alias}.{$column}), '')";
        }
        return $expressions === [] ? $fallback : 'COALESCE(' . implode(', ', $expressions) . ', ' . $fallback . ')';
    }

    public function firstExistingColumnExpr(string $tableName, string $alias, array $candidates, string $fallback = 'NULL'): string
    {
        return $this->firstExistingColumnExpression($tableName, $alias, $candidates, $fallback);
    }

    public function coalesceExistingColumnExpr(string $tableName, string $alias, array $candidates, string $fallback = 'NULL'): string
    {
        return $this->coalesceExistingColumnExpression($tableName, $alias, $candidates, $fallback);
    }

    public function sourceFormatIdSelect(string $tableName, string $alias = 'body'): string
    {
        return $this->firstExistingColumnExpression($tableName, $alias, ['format_id'], "''");
    }

    public function sourceRawJsonSelect(string $tableName, string $alias = 'body'): string
    {
        return $this->firstExistingColumnExpression($tableName, $alias, ['raw_json'], "''");
    }

    public function sourceParsedJsonSelect(string $tableName, string $alias = 'body'): string
    {
        return $this->firstExistingColumnExpression($tableName, $alias, ['parsed_json'], "''");
    }

    private function assertIdentifier(string $value): void
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $value) !== 1) throw new \InvalidArgumentException('Invalid schema identifier');
    }
}
