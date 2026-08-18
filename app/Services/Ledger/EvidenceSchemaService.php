<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceSchemaModel;

class EvidenceSchemaService
{
    private static array $cache = [];

    public function __construct(private readonly EvidenceSchemaModel $model = new EvidenceSchemaModel()) {}

    public function columnExists(string $tableName, string $columnName): bool
    {
        $key = $tableName . '.' . $columnName;
        return self::$cache[$key] ??= $this->safe(fn(): bool => $this->model->columnExists($tableName, $columnName));
    }

    public function tableExists(string $tableName): bool
    {
        $key = 'table:' . $tableName;
        return self::$cache[$key] ??= $this->safe(fn(): bool => $this->model->tableExists($tableName));
    }

    private function safe(callable $query): bool
    {
        try { return (bool) $query(); } catch (\Throwable) { return false; }
    }
}
