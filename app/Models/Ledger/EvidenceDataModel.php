<?php

namespace App\Models\Ledger;

use Core\Database;
use PDO;

class EvidenceDataModel
{
    private PDO $db;
    private string $table = 'ledger_data_evidences';

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function tableExists(): bool
    {
        return $this->existsTable($this->table);
    }

    public function columnExists(string $column): bool
    {
        return $this->existsColumn($this->table, $column);
    }

    public function getById(string $id): ?array
    {
        if ($id === '' || !$this->tableExists()) {
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

    public function getTransactionImportType(string $transactionId): ?string
    {
        if ($transactionId === '' || !$this->tableExists() || !$this->columnExists('transaction_id')) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT source_type
            FROM {$this->table}
            WHERE transaction_id = :transaction_id
              AND deleted_at IS NULL
            ORDER BY latest_imported_at DESC, updated_at DESC, created_at DESC
            LIMIT 1
        ");
        $stmt->execute([':transaction_id' => $transactionId]);
        $value = trim((string) ($stmt->fetchColumn() ?: ''));

        return $value !== '' ? $value : null;
    }

    public function getTransactionSeedSource(string $transactionId): ?array
    {
        if ($transactionId === '' || !$this->tableExists() || !$this->columnExists('transaction_id')) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT id, 0 AS row_no, source_type, source_key, latest_imported_at AS processed_at, created_at
            FROM {$this->table}
            WHERE transaction_id = :transaction_id
              AND deleted_at IS NULL
            ORDER BY latest_imported_at DESC, updated_at DESC, created_at DESC
            LIMIT 1
        ");
        $stmt->execute([':transaction_id' => $transactionId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findIdsByTransactionIds(array $transactionIds): array
    {
        if (!$this->tableExists() || !$this->columnExists('transaction_id')) {
            return [];
        }

        $transactionIds = array_values(array_filter(array_unique(array_map(
            static fn(mixed $id): string => trim((string) $id),
            $transactionIds
        ))));
        if ($transactionIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($transactionIds as $index => $transactionId) {
            $key = ':transaction_id_' . $index;
            $placeholders[] = $key;
            $params[$key] = $transactionId;
        }

        $stmt = $this->db->prepare("
            SELECT id
            FROM {$this->table}
            WHERE transaction_id IN (" . implode(', ', $placeholders) . ")
              AND deleted_at IS NULL
        ");
        $stmt->execute($params);

        return array_values(array_filter(array_map(
            static fn(array $row): string => trim((string) ($row['id'] ?? '')),
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        )));
    }

    public function getPayloadContext(string $evidenceId): ?array
    {
        if ($evidenceId === '' || !$this->tableExists() || !$this->columnExists('mapped_payload_json')) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT source_type, mapped_payload_json
            FROM {$this->table}
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $evidenceId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getSeedSourceById(string $evidenceId): ?array
    {
        if ($evidenceId === '' || !$this->tableExists()) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT id, 0 AS row_no, source_type, source_key, latest_imported_at AS processed_at, created_at
            FROM {$this->table}
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $evidenceId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getVoucherSeedSourcesByVoucherId(string $voucherId): array
    {
        if (
            $voucherId === ''
            || !$this->tableExists()
            || !$this->existsTable('ledger_evidence_links')
        ) {
            return [];
        }

        $transactionIdSelect = $this->columnExists('transaction_id')
            ? 'e.transaction_id'
            : 'NULL AS transaction_id';
        $payloadSelect = $this->columnExists('mapped_payload_json')
            ? 'e.mapped_payload_json'
            : 'NULL AS mapped_payload_json';
        $amountSelect = $this->columnExists('total_amount')
            ? 'e.total_amount'
            : 'NULL AS total_amount';

        $stmt = $this->db->prepare("
            SELECT
                e.id,
                0 AS row_no,
                e.source_type,
                e.source_key,
                NULL AS format_id,
                NULL AS format_name,
                e.evidence_date,
                e.client_name,
                {$amountSelect},
                {$payloadSelect},
                {$transactionIdSelect},
                e.latest_imported_at AS processed_at,
                e.created_at
            FROM ledger_evidence_links l
            INNER JOIN {$this->table} e
                ON e.id = l.evidence_id
               AND e.deleted_at IS NULL
            WHERE l.target_type = 'VOUCHER'
              AND l.target_id = :voucher_id
              AND l.deleted_at IS NULL
            ORDER BY l.updated_at DESC, l.created_at DESC
        ");
        $stmt->execute([':voucher_id' => $voucherId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function searchForPicker(string $query = '', array $allowedEvidenceTypes = []): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $hasClients = $this->existsTable('system_clients') && $this->columnExists('client_id');
        $clientSelect = $hasClients ? "COALESCE(sc.client_name, '')" : "''";
        $clientJoin = $hasClients ? "
            LEFT JOIN system_clients sc
                ON sc.id = e.client_id
        " : '';
        $hasPayload = $this->columnExists('mapped_payload_json');
        $payloadSelect = $hasPayload ? 'e.mapped_payload_json' : 'NULL AS mapped_payload_json';
        $amountSelect = $this->columnExists('total_amount') ? 'e.total_amount' : 'NULL AS total_amount';

        $allowedEvidenceTypes = array_values(array_intersect(
            ['DATA', 'FUND', 'BOTH'],
            array_unique(array_map(static fn(mixed $type): string => strtoupper(trim((string) $type)), $allowedEvidenceTypes))
        ));
        if ($allowedEvidenceTypes === []) {
            return [];
        }

        $policyPlaceholders = [];
        $params = [];
        foreach ($allowedEvidenceTypes as $index => $evidenceType) {
            $key = ':policy_evidence_type_' . $index;
            $policyPlaceholders[] = $key;
            $params[$key] = $evidenceType;
        }
        $where = "e.deleted_at IS NULL
            AND m.deleted_at IS NULL
            AND m.evidence_type IN (" . implode(', ', $policyPlaceholders) . ")";
        if ($query !== '') {
            $numericQuery = preg_replace('/[^0-9.\-]/', '', $query) ?? '';
            $where .= "
                AND (
                    e.source_type LIKE :keyword
                    OR e.source_key LIKE :keyword
                    OR e.evidence_date LIKE :keyword
                    OR e.client_name LIKE :keyword
                    " . ($this->columnExists('total_amount') ? "OR e.total_amount LIKE :keyword" : '') . "
                    " . ($this->columnExists('total_amount') && $numericQuery !== '' ? "OR e.total_amount LIKE :numeric_keyword" : '') . "
                    " . ($hasPayload ? "OR e.mapped_payload_json LIKE :keyword" : '') . "
                    " . ($hasPayload && $numericQuery !== '' ? "OR e.mapped_payload_json LIKE :numeric_keyword" : '') . "
                    " . ($hasClients ? "OR sc.client_name LIKE :keyword" : '') . "
                )
            ";
            $params[':keyword'] = '%' . $query . '%';
            if ($numericQuery !== '') {
                $params[':numeric_keyword'] = '%' . $numericQuery . '%';
            }
        }

        $baseSelectSql = "
            SELECT
                e.id,
                e.source_type,
                e.source_key,
                e.evidence_date,
                '' AS format_name,
                {$amountSelect},
                {$payloadSelect},
                e.latest_imported_at AS processed_at,
                e.created_at,
                e.voucher_status,
                COALESCE(NULLIF(e.client_name, ''), {$clientSelect}) AS client_name
            FROM {$this->table} e
            INNER JOIN ledger_evidence_metadata m
                ON m.import_type = e.source_type
            {$clientJoin}
            WHERE {$where}
        ";
        $orderSql = " ORDER BY e.evidence_date DESC, e.latest_imported_at DESC, e.created_at DESC";

        if ($query !== '') {
            $stmt = $this->db->prepare($baseSelectSql . $orderSql . " LIMIT 200");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $rows = [];
        $groupsStmt = $this->db->prepare("
            SELECT e.source_type
            FROM {$this->table} e
            INNER JOIN ledger_evidence_metadata m
                ON m.import_type = e.source_type
               AND m.deleted_at IS NULL
               AND m.evidence_type IN (" . implode(', ', $policyPlaceholders) . ")
            WHERE e.deleted_at IS NULL
            GROUP BY e.source_type
            ORDER BY e.source_type ASC
        ");
        $groupsStmt->execute($params);
        $groups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($groups as $group) {
            $groupWhere = $where . " AND e.source_type <=> :group_source_type";
            $groupParams = $params;
            $groupParams[':group_source_type'] = $group['source_type'] ?? null;

            $stmt = $this->db->prepare(str_replace("WHERE {$where}", "WHERE {$groupWhere}", $baseSelectSql) . $orderSql . " LIMIT 30");
            $stmt->execute($groupParams);
            $rows = array_merge($rows, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        }

        return $rows;
    }

    public function isLinkableByPolicy(string $evidenceId, array $allowedEvidenceTypes): bool
    {
        $allowedEvidenceTypes = array_values(array_intersect(
            ['DATA', 'FUND', 'BOTH'],
            array_unique(array_map(static fn(mixed $type): string => strtoupper(trim((string) $type)), $allowedEvidenceTypes))
        ));
        if ($evidenceId === '' || $allowedEvidenceTypes === [] || !$this->tableExists()) {
            return false;
        }

        $placeholders = [];
        $params = [':evidence_id' => $evidenceId];
        foreach ($allowedEvidenceTypes as $index => $evidenceType) {
            $key = ':evidence_type_' . $index;
            $placeholders[] = $key;
            $params[$key] = $evidenceType;
        }

        $stmt = $this->db->prepare("
            SELECT 1
            FROM {$this->table} e
            INNER JOIN ledger_evidence_metadata m
                ON m.import_type = e.source_type
               AND m.deleted_at IS NULL
            WHERE e.id = :evidence_id
              AND e.deleted_at IS NULL
              AND m.evidence_type IN (" . implode(', ', $placeholders) . ")
            LIMIT 1
        ");
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function updateMappedPayload(string $evidenceId, string $sourceType, string $mappedPayloadJson, string $actor): bool
    {
        if (
            $evidenceId === ''
            || $sourceType === ''
            || !$this->tableExists()
            || !$this->columnExists('mapped_payload_json')
        ) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET mapped_payload_json = :mapped_payload_json,
                payload_hash = SHA2(COALESCE(:mapped_payload_json_hash, ''), 256),
                updated_at = NOW(),
                updated_by = :actor
            WHERE source_type = :source_type
              AND id = :id
              AND deleted_at IS NULL
        ");

        return $stmt->execute([
            ':id' => $evidenceId,
            ':source_type' => $sourceType,
            ':mapped_payload_json' => $mappedPayloadJson,
            ':mapped_payload_json_hash' => $mappedPayloadJson,
            ':actor' => $actor,
        ]);
    }

    public function updateVoucherStatus(string $evidenceId, string $voucherStatus, string $actor, ?string $errorMessage = null): bool
    {
        if ($evidenceId === '' || !$this->tableExists()) {
            return false;
        }

        $sets = [];
        $params = [
            ':id' => $evidenceId,
            ':actor' => $actor,
        ];

        if ($this->columnExists('voucher_status')) {
            $sets[] = 'voucher_status = :voucher_status';
            $params[':voucher_status'] = $voucherStatus;
        }
        if ($this->columnExists('error_message')) {
            $sets[] = 'error_message = :error_message';
            $params[':error_message'] = $errorMessage;
        }
        if ($this->columnExists('updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }
        if ($this->columnExists('updated_by')) {
            $sets[] = 'updated_by = :actor';
        }
        if ($sets === []) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET " . implode(', ', $sets) . "
            WHERE id = :id
              AND deleted_at IS NULL
        ");

        return $stmt->execute($params);
    }

    private function existsTable(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        $stmt = $this->db->prepare("
            SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
            LIMIT 1
        ");
        $stmt->execute([':table_name' => $table]);
        $cache[$table] = (bool) $stmt->fetchColumn();

        return $cache[$table];
    }

    private function existsColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
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
            ':table_name' => $table,
            ':column_name' => $column,
        ]);
        $cache[$key] = (bool) $stmt->fetchColumn();

        return $cache[$key];
    }
}
