<?php

namespace App\Models\Ledger;

use PDO;

class EvidenceWriteModel
{
    protected PDO $pdo;
    protected string $table = '';
    /** @var array<string,array<string,array{data_type:string,max_length:int|null}>> */
    private static array $tableColumnsCache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function upsertById(array $payload): bool
    {
        if ($this->table === '') {
            return false;
        }

        $payload = $this->filterPayloadForExistingColumns($payload);
        $id = trim((string) ($payload['id'] ?? ''));
        if ($id === '') {
            return false;
        }

        $columns = array_keys($payload);
        $insertColumns = implode(', ', array_map(static fn (string $c): string => "`{$c}`", $columns));
        $insertValues = implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns));
        $updateColumns = array_values(array_filter($columns, static fn (string $c): bool => $c !== 'id'));
        $updateSql = implode(",\n                ", array_map(static fn (string $c): string => "`{$c}` = VALUES(`{$c}`)", $updateColumns));

        $sql = "
            INSERT INTO `{$this->table}` ({$insertColumns})
            VALUES ({$insertValues})
            ON DUPLICATE KEY UPDATE
                {$updateSql}
        ";

        $stmt = $this->pdo->prepare($sql);
        $params = [];
        foreach ($payload as $column => $value) {
            $params[':' . $column] = $value;
        }

        return $stmt->execute($params);
    }

    private function filterPayloadForExistingColumns(array $payload): array
    {
        $columns = $this->tableColumns();
        if ($columns === []) {
            return [];
        }

        $filtered = array_filter(
            $payload,
            static fn(string $column): bool => isset($columns[$column]),
            ARRAY_FILTER_USE_KEY
        );

        foreach ($filtered as $column => $value) {
            $filtered[$column] = $this->normalizeValueForColumn($value, $columns[$column] ?? []);
        }

        return $filtered;
    }

    /**
     * @return array<string,array{data_type:string,max_length:int|null}>
     */
    private function tableColumns(): array
    {
        if (isset(self::$tableColumnsCache[$this->table])) {
            return self::$tableColumnsCache[$this->table];
        }

        $stmt = $this->pdo->prepare("
            SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
        ");
        $stmt->execute([':table_name' => $this->table]);

        $columns = [];
        foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
            $columnName = (string) ($row['COLUMN_NAME'] ?? '');
            if ($columnName === '') {
                continue;
            }

            $columns[$columnName] = [
                'data_type' => strtolower(trim((string) ($row['DATA_TYPE'] ?? ''))),
                'max_length' => isset($row['CHARACTER_MAXIMUM_LENGTH']) ? (int) $row['CHARACTER_MAXIMUM_LENGTH'] : null,
            ];
        }

        self::$tableColumnsCache[$this->table] = $columns;

        return $columns;
    }

    private function normalizeValueForColumn(mixed $value, array $columnMeta): mixed
    {
        $dataType = strtolower(trim((string) ($columnMeta['data_type'] ?? '')));
        if ($value === '') {
            return in_array($dataType, ['char', 'varchar', 'text', 'mediumtext', 'longtext', 'tinytext'], true) ? '' : null;
        }

        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $maxLength = isset($columnMeta['max_length']) ? (int) $columnMeta['max_length'] : 0;
            if ($maxLength > 0 && strlen($value) > $maxLength) {
                $value = substr($value, 0, $maxLength);
            }
        }

        return match ($dataType) {
            'date' => $this->normalizeDateValue($value),
            'datetime', 'timestamp' => $this->normalizeDateTimeValue($value),
            'time' => $this->normalizeTimeValue($value),
            'tinyint', 'smallint', 'mediumint', 'int', 'bigint' => $this->normalizeIntegerValue($value),
            'decimal', 'numeric', 'float', 'double', 'real' => $this->normalizeDecimalValue($value),
            default => $value,
        };
    }

    private function normalizeDateValue(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $ts = strtotime($raw);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function normalizeDateTimeValue(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $ts = strtotime($raw);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    private function normalizeTimeValue(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $raw) === 1) {
            return strlen($raw) === 5 ? ($raw . ':00') : $raw;
        }

        $ts = strtotime($raw);
        return $ts ? date('H:i:s', $ts) : null;
    }

    private function normalizeIntegerValue(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(',', '', trim((string) $value));
        return is_numeric($normalized) ? (int) round((float) $normalized) : null;
    }

    private function normalizeDecimalValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(',', '', trim((string) $value));
        if (!is_numeric($normalized)) {
            return null;
        }

        return (string) $normalized;
    }
}
