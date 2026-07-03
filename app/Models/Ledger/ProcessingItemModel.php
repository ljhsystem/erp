<?php

namespace App\Models\Ledger;

use Core\Database;
use Core\Helpers\UuidHelper;
use PDO;

class ProcessingItemModel
{
    protected string $table = 'ledger_processing_items';

    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function existsTable(): bool
    {
        return $this->tableExists($this->table);
    }

    public function getById(string $id): ?array
    {
        if (!$this->existsTable()) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM {$this->table}
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getBySource(string $sourceTable, string $sourceId): array
    {
        if (!$this->existsTable()) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM {$this->table}
            WHERE source_table = :source_table
              AND source_id = :source_id
              AND deleted_at IS NULL
            ORDER BY sort_no ASC, created_at ASC
        ");
        $stmt->execute([
            ':source_table' => $sourceTable,
            ':source_id' => $sourceId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getByIds(array $ids): array
    {
        if (!$this->existsTable()) {
            return [];
        }

        $ids = array_values(array_filter(array_unique(array_map('strval', $ids))));
        if ($ids === []) {
            return [];
        }

        [$inSql, $params] = $this->placeholders($ids, 'processing_item');
        $stmt = $this->db->prepare("
            SELECT *
            FROM {$this->table}
            WHERE id IN ({$inSql})
              AND deleted_at IS NULL
            ORDER BY source_table ASC, source_id ASC, sort_no ASC, created_at ASC
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function ensureDefaultItemForEvidence(array $evidence): ?array
    {
        return $this->ensureDefaultItem('ledger_data_evidences', $evidence);
    }

    public function updateStatus(
        string $id,
        string $transactionStatus,
        string $itemStatus,
        ?string $message,
        string $actor
    ): bool {
        if (!$this->existsTable()) {
            return false;
        }

        return $this->update($id, [
            'transaction_status' => $transactionStatus,
            'item_status' => $itemStatus,
            'is_current' => in_array($itemStatus, ['SPLIT', 'MERGED', 'INACTIVE', 'DELETED', 'IGNORED'], true) ? 0 : 1,
            'correction_status' => $message === null || trim($message) === '' ? 'NONE' : 'NEEDS_CORRECTION',
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $actor,
        ]);
    }

    public function insert(array $data): bool
    {
        $payload = $this->filterData($data);
        if (!isset($payload['id'], $payload['source_table'], $payload['source_id'], $payload['source_type'])) {
            return false;
        }

        $columns = array_keys($payload);
        $placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);
        $stmt = $this->db->prepare(sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        ));

        return $stmt->execute($this->bindParams($payload));
    }

    public function update(string $id, array $data): bool
    {
        $payload = $this->filterData($data);
        if ($payload === []) {
            return false;
        }

        $set = [];
        foreach (array_keys($payload) as $column) {
            $set[] = "{$column} = :{$column}";
        }

        $params = $this->bindParams($payload);
        $params[':id'] = $id;
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET " . implode(', ', $set) . "
            WHERE id = :id
        ");

        return $stmt->execute($params);
    }

    private function ensureDefaultItem(string $sourceTable, array $source): ?array
    {
        if (!$this->existsTable()) {
            return null;
        }

        $sourceId = trim((string) ($source['id'] ?? ''));
        if ($sourceId === '') {
            return null;
        }

        $items = $this->getBySource($sourceTable, $sourceId);
        if ($items !== []) {
            return $items[0];
        }

        $timestamp = date('Y-m-d H:i:s');
        $id = UuidHelper::generate();
        $sourceType = (string) ($source['source_type'] ?? '');
        $totalAmount = $source['total_amount']
            ?? max((float) ($source['deposit_amount'] ?? 0), (float) ($source['withdraw_amount'] ?? 0));
        $displayPath = $this->rootDisplayPath($sourceTable, $source);
        $lineType = $sourceType;
        foreach ([
            $source['operation_type'] ?? null,
            $source['transaction_direction'] ?? null,
            $source['import_type'] ?? null,
            $source['source_type'] ?? null,
        ] as $candidateLineType) {
            $candidateLineType = trim((string) ($candidateLineType ?? ''));
            if ($candidateLineType !== '') {
                $lineType = $candidateLineType;
                break;
            }
        }

        $ok = $this->insert([
            'id' => $id,
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'source_type' => $sourceType,
            'source_domain' => $this->sourceDomainForTable($sourceTable),
            'lineage_root_id' => $id,
            'display_path' => $displayPath,
            'is_current' => 1,
            'sort_no' => ctype_digit($displayPath) ? (int) $displayPath : 1,
            'item_type' => 'DEFAULT',
            'line_type' => $lineType,
            'item_status' => $source['evidence_status'] ?? $source['status'] ?? 'ACTIVE',
            'transaction_status' => $source['transaction_status'] ?? 'NONE',
            'voucher_status' => $source['voucher_status'] ?? 'NONE',
            'readiness_status' => $source['readiness_status'] ?? 'UNKNOWN',
            'correction_status' => trim((string) ($source['error_message'] ?? '')) === '' ? 'NONE' : 'NEEDS_CORRECTION',
            'item_date' => $source['evidence_date'] ?? $source['transaction_date'] ?? null,
            'client_id' => $source['client_id'] ?? null,
            'project_id' => $source['project_id'] ?? null,
            'employee_id' => $source['employee_id'] ?? null,
            'bank_account_id' => $source['bank_account_id'] ?? null,
            'card_id' => $source['card_id'] ?? null,
            'supply_amount' => $source['supply_amount'] ?? null,
            'vat_amount' => $source['vat_amount'] ?? null,
            'total_amount' => $totalAmount,
            'currency' => $source['currency'] ?? $source['currency_code'] ?? 'KRW',
            'description' => $source['description'] ?? null,
            'memo' => $source['memo'] ?? null,
            'raw_json' => $source['raw_json'] ?? null,
            'mapped_payload_json' => $source['mapped_payload_json'] ?? null,
            'created_at' => $source['created_at'] ?? $timestamp,
            'created_by' => $source['created_by'] ?? null,
            'updated_at' => $source['updated_at'] ?? $timestamp,
            'updated_by' => $source['updated_by'] ?? null,
        ]);

        return $ok ? $this->getById($id) : null;
    }

    private function filterData(array $data): array
    {
        $allowed = [
            'id',
            'source_table',
            'source_id',
            'source_type',
            'source_domain',
            'parent_item_id',
            'source_item_id',
            'lineage_root_id',
            'display_path',
            'is_current',
            'split_group_id',
            'merge_group_id',
            'sort_no',
            'item_type',
            'line_type',
            'item_status',
            'transaction_status',
            'voucher_status',
            'readiness_status',
            'correction_status',
            'item_date',
            'client_id',
            'project_id',
            'employee_id',
            'bank_account_id',
            'card_id',
            'account_id',
            'quantity',
            'unit_price',
            'supply_amount',
            'vat_amount',
            'total_amount',
            'currency',
            'description',
            'memo',
            'raw_json',
            'mapped_payload_json',
            'created_at',
            'created_by',
            'updated_at',
            'updated_by',
            'deleted_at',
            'deleted_by',
        ];

        $payload = [];
        foreach ($allowed as $column) {
            if (array_key_exists($column, $data) && $this->tableColumnExists($this->table, $column)) {
                $payload[$column] = $data[$column];
            }
        }

        return $payload;
    }

    private function placeholders(array $ids, string $prefix): array
    {
        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $key = ':' . $prefix . '_' . $index;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        return [implode(', ', $placeholders), $params];
    }

    private function tableExists(string $tableName): bool
    {
        static $cache = [];
        if (array_key_exists($tableName, $cache)) {
            return $cache[$tableName];
        }

        $stmt = $this->db->prepare("
            SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
            LIMIT 1
        ");
        $stmt->execute([':table_name' => $tableName]);
        $cache[$tableName] = (bool) $stmt->fetchColumn();

        return $cache[$tableName];
    }

    private function tableColumnExists(string $tableName, string $columnName): bool
    {
        static $cache = [];
        $key = $tableName . '.' . $columnName;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $stmt = $this->db->prepare("
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
        $cache[$key] = (bool) $stmt->fetchColumn();

        return $cache[$key];
    }

    private function bindParams(array $data): array
    {
        $params = [];
        foreach ($data as $column => $value) {
            $params[':' . $column] = $value;
        }

        return $params;
    }

    private function rootDisplayPath(string $sourceTable, array $source): string
    {
        foreach (['root_display_path', 'workflow_no', 'display_path', 'row_no', 'sort_no'] as $key) {
            $value = trim((string) ($source[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $sourceId = trim((string) ($source['id'] ?? ''));
        if ($sourceId !== '' && ctype_digit($sourceId)) {
            return $sourceId;
        }

        $stmt = $this->db->prepare("
            SELECT MAX(CAST(display_path AS UNSIGNED))
            FROM {$this->table}
            WHERE source_table = :source_table
              AND parent_item_id IS NULL
              AND display_path REGEXP '^[0-9]+$'
              AND deleted_at IS NULL
        ");
        $stmt->execute([':source_table' => $sourceTable]);
        $max = (int) ($stmt->fetchColumn() ?: 0);

        return (string) ($max + 1);
    }

    private function sourceDomainForTable(string $sourceTable): ?string
    {
        return match ($sourceTable) {
            'ledger_data_evidences' => 'EVIDENCE',
            default => null,
        };
    }
}
