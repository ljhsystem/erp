<?php

namespace App\Models\Ledger;

use Core\Database;
use Core\Helpers\UuidHelper;
use PDO;

class ProcessingItemActionModel
{
    protected string $table = 'ledger_processing_item_actions';

    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function createAction(array $data): ?array
    {
        if (!$this->existsTable()) {
            return null;
        }

        $payload = $this->filterData($data);
        $payload['id'] = $payload['id'] ?? UuidHelper::generate();
        $payload['actor_type'] = $payload['actor_type'] ?? 'USER';

        if (empty($payload['processing_item_id']) || empty($payload['action_type'])) {
            return null;
        }

        foreach (['before_status_json', 'after_status_json', 'before_payload_json', 'after_payload_json', 'metadata_json'] as $column) {
            if (isset($payload[$column]) && is_array($payload[$column])) {
                $payload[$column] = $this->encodeJson($payload[$column]);
            }
        }

        $columns = array_keys($payload);
        $placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);
        $stmt = $this->db->prepare(sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        ));

        if (!$stmt->execute($this->bindParams($payload))) {
            return null;
        }

        return $this->findById((string) $payload['id']);
    }

    public function findById(string $id): ?array
    {
        if (!$this->existsTable() || trim($id) === '') {
            return null;
        }

        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByProcessingItemId(string $processingItemId, int $limit = 100): array
    {
        return $this->findByColumn('processing_item_id', $processingItemId, $limit);
    }

    public function findByActionGroupId(string $actionGroupId, int $limit = 200): array
    {
        return $this->findByColumn('action_group_id', $actionGroupId, $limit);
    }

    public function findRecentBySource(string $sourceDomain, string $sourceId, ?string $sourceType = null, int $limit = 100): array
    {
        if (!$this->existsTable() || trim($sourceDomain) === '' || trim($sourceId) === '') {
            return [];
        }

        $where = ['source_domain = :source_domain', 'source_id = :source_id'];
        $params = [
            ':source_domain' => $sourceDomain,
            ':source_id' => $sourceId,
        ];
        if ($sourceType !== null && trim($sourceType) !== '') {
            $where[] = 'source_type = :source_type';
            $params[':source_type'] = $sourceType;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM {$this->table}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY created_at DESC
            LIMIT " . max(1, min(500, $limit))
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByRelatedTransactionId(string $transactionId, int $limit = 100): array
    {
        return $this->findByColumn('related_transaction_id', $transactionId, $limit);
    }

    public function findByRelatedVoucherId(string $voucherId, int $limit = 100): array
    {
        return $this->findByColumn('related_voucher_id', $voucherId, $limit);
    }

    private function findByColumn(string $column, string $value, int $limit): array
    {
        if (!$this->existsTable() || trim($value) === '' || !$this->tableColumnExists($this->table, $column)) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM {$this->table}
            WHERE {$column} = :value
            ORDER BY created_at DESC
            LIMIT " . max(1, min(500, $limit))
        );
        $stmt->execute([':value' => $value]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function filterData(array $data): array
    {
        $allowed = [
            'id',
            'processing_item_id',
            'action_type',
            'action_group_id',
            'related_processing_item_id',
            'related_transaction_id',
            'related_voucher_id',
            'source_domain',
            'source_table',
            'source_type',
            'source_id',
            'before_status_json',
            'after_status_json',
            'before_payload_json',
            'after_payload_json',
            'action_reason',
            'action_source',
            'actor_type',
            'actor_user_id',
            'error_message',
            'metadata_json',
            'created_at',
        ];

        $payload = [];
        foreach ($allowed as $column) {
            if (array_key_exists($column, $data) && $this->tableColumnExists($this->table, $column)) {
                $payload[$column] = $data[$column];
            }
        }

        return $payload;
    }

    private function existsTable(): bool
    {
        return $this->tableExists($this->table);
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

    private function encodeJson(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
