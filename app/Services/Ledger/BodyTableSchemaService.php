<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceSchemaModel;
use PDO;

class BodyTableSchemaService
{
    private array $columnExistsCache = [];

    private EvidenceSchemaModel $schemaModel;

    public function __construct(PDO $pdo)
    {
        $this->schemaModel = new EvidenceSchemaModel($pdo);
    }

    public function columnExists(string $tableName, string $columnName): bool
    {
        $cacheKey = $tableName . '.' . $columnName;
        if (array_key_exists($cacheKey, $this->columnExistsCache)) {
            return $this->columnExistsCache[$cacheKey];
        }

        try {
            $exists = $this->schemaModel->columnExists($tableName, $columnName);
        } catch (\Throwable) {
            $exists = false;
        }

        $this->columnExistsCache[$cacheKey] = $exists;

        return $exists;
    }

    public function firstExistingColumnExpr(string $tableName, string $alias, array $candidates, string $fallback = 'NULL'): string
    {
        return $this->schemaModel->firstExistingColumnExpression($tableName, trim($alias), $candidates, $fallback);
    }

    public function coalesceExistingColumnExpr(string $tableName, string $alias, array $candidates, string $fallback = 'NULL'): string
    {
        return $this->schemaModel->coalesceExistingColumnExpression($tableName, trim($alias), $candidates, $fallback);
    }

    public function sourceFormatIdSelect(string $tableName, string $alias = 'body'): string
    {
        return $this->schemaModel->firstExistingColumnExpression($tableName, $alias, ['format_id'], "''");
    }

    public function sourceRawJsonSelect(string $tableName, string $alias = 'body'): string
    {
        return $this->schemaModel->firstExistingColumnExpression($tableName, $alias, ['raw_json'], "''");
    }

    public function sourceParsedJsonSelect(string $tableName, string $alias = 'body'): string
    {
        return $this->schemaModel->firstExistingColumnExpression($tableName, $alias, ['parsed_json'], "''");
    }
}
