<?php

namespace App\Models\System;

use PDO;

class SystemSchemaModel
{
    private array $tableExistsCache = [];
    private array $columnExistsCache = [];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getTableColumns(string $tableName): array
    {
        $stmt = $this->pdo->prepare("
            SELECT COLUMN_NAME, COLUMN_COMMENT, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, ORDINAL_POSITION
              FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
             ORDER BY ORDINAL_POSITION ASC
        ");
        $stmt->execute([':table_name' => $tableName]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getTableComment(string $tableName): string
    {
        $stmt = $this->pdo->prepare("
            SELECT TABLE_COMMENT
              FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
             LIMIT 1
        ");
        $stmt->execute([':table_name' => $tableName]);

        return (string) ($stmt->fetchColumn() ?: '');
    }

    public function tableExists(string $tableName): bool
    {
        if (!array_key_exists($tableName, $this->tableExistsCache)) {
            $stmt = $this->pdo->prepare("
                SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name LIMIT 1
            ");
            $stmt->execute([':table_name' => $tableName]);
            $this->tableExistsCache[$tableName] = (bool) $stmt->fetchColumn();
        }

        return $this->tableExistsCache[$tableName];
    }

    public function columnExists(string $tableName, string $columnName): bool
    {
        $cacheKey = $tableName . '.' . $columnName;
        if (!array_key_exists($cacheKey, $this->columnExistsCache)) {
            $stmt = $this->pdo->prepare("
                SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table_name
                   AND COLUMN_NAME = :column_name
                 LIMIT 1
            ");
            $stmt->execute([':table_name' => $tableName, ':column_name' => $columnName]);
            $this->columnExistsCache[$cacheKey] = (bool) $stmt->fetchColumn();
        }

        return $this->columnExistsCache[$cacheKey];
    }
}
