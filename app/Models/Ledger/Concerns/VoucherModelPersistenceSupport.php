<?php

namespace App\Models\Ledger\Concerns;

use PDO;

trait VoucherModelPersistenceSupport
{
    public function purge(string $id): bool
    {
        return $this->hardDelete($id);
    }

    private function filterData(array $data, array $allowed): array
    {
        $payload = [];
        foreach ($allowed as $column) {
            if (array_key_exists($column, $data)) $payload[$column] = $data[$column];
        }
        return $payload;
    }

    private function hasColumn(string $column): bool
    {
        static $columns = null;
        if ($columns === null) {
            try {
                $stmt = $this->db->query("SHOW COLUMNS FROM {$this->table}");
                $columns = array_flip(array_map(
                    static fn(array $row): string => (string) ($row['Field'] ?? ''),
                    $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
                ));
            } catch (\Throwable) {
                $columns = [];
            }
        }
        return isset($columns[$column]);
    }

    private function hasTable(string $table): bool
    {
        static $tables = [];
        if (!array_key_exists($table, $tables)) {
            try {
                $stmt = $this->db->prepare('SELECT 1 FROM information_schema.TABLES '
                    . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name LIMIT 1');
                $stmt->execute([':table_name' => $table]);
                $tables[$table] = (bool) $stmt->fetchColumn();
            } catch (\Throwable) {
                $tables[$table] = false;
            }
        }
        return $tables[$table];
    }

    private function tableColumnExists(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (!array_key_exists($key, $cache)) {
            try {
                $stmt = $this->db->prepare('SELECT 1 FROM information_schema.COLUMNS '
                    . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name '
                    . 'AND COLUMN_NAME = :column_name LIMIT 1');
                $stmt->execute([':table_name' => $table, ':column_name' => $column]);
                $cache[$key] = (bool) $stmt->fetchColumn();
            } catch (\Throwable) {
                $cache[$key] = false;
            }
        }
        return $cache[$key];
    }

    private function bindParams(array $data): array
    {
        $params = [];
        foreach ($data as $column => $value) $params[':' . $column] = $value;
        return $params;
    }
}
