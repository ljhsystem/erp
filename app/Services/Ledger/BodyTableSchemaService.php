<?php

namespace App\Services\Ledger;

use PDO;

class BodyTableSchemaService
{
    private array $columnExistsCache = [];

    public function __construct(private PDO $pdo)
    {
    }

    public function columnExists(string $tableName, string $columnName): bool
    {
        $cacheKey = $tableName . '.' . $columnName;
        if (array_key_exists($cacheKey, $this->columnExistsCache)) {
            return $this->columnExistsCache[$cacheKey];
        }

        try {
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
            $exists = (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            $exists = false;
        }

        $this->columnExistsCache[$cacheKey] = $exists;

        return $exists;
    }

    public function firstExistingColumnExpr(string $tableName, string $alias, array $candidates, string $fallback = 'NULL'): string
    {
        $normalizedAlias = trim($alias);
        foreach ($candidates as $columnName) {
            $normalizedColumn = trim((string) $columnName);
            if ($normalizedColumn !== '' && $this->columnExists($tableName, $normalizedColumn)) {
                return "{$normalizedAlias}.{$normalizedColumn}";
            }
        }

        return $fallback;
    }

    public function coalesceExistingColumnExpr(string $tableName, string $alias, array $candidates, string $fallback = 'NULL'): string
    {
        $normalizedAlias = trim($alias);
        $expressions = [];

        foreach ($candidates as $columnName) {
            $normalizedColumn = trim((string) $columnName);
            if ($normalizedColumn !== '' && $this->columnExists($tableName, $normalizedColumn)) {
                $expressions[] = "NULLIF(TRIM({$normalizedAlias}.{$normalizedColumn}), '')";
            }
        }

        return $expressions === []
            ? $fallback
            : 'COALESCE(' . implode(', ', $expressions) . ', ' . $fallback . ')';
    }

    public function sourceFormatIdSelect(string $tableName, string $alias = 'body'): string
    {
        return $this->firstExistingColumnExpr($tableName, $alias, ['format_id'], "''");
    }

    public function sourceRawJsonSelect(string $tableName, string $alias = 'body'): string
    {
        return $this->firstExistingColumnExpr($tableName, $alias, ['raw_json'], "''");
    }

    public function sourceParsedJsonSelect(string $tableName, string $alias = 'body'): string
    {
        return $this->firstExistingColumnExpr($tableName, $alias, ['parsed_json'], "''");
    }
}
