<?php

namespace App\Models\Ledger;

use Core\Database;
use Core\Helpers\UuidHelper;
use PDO;

class EvidenceLinkModel
{
    private PDO $db;
    private string $table = 'ledger_evidence_links';

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

    public function findLinkedVoucherInfoByEvidenceId(string $evidenceId): ?array
    {
        if ($evidenceId === '' || !$this->tableExists() || !$this->existsTable('ledger_vouchers')) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT v.id, v.voucher_no, v.voucher_date, v.status
            FROM {$this->table} l
            INNER JOIN ledger_vouchers v
                ON v.id = l.target_id
               AND v.deleted_at IS NULL
            WHERE l.evidence_id = :evidence_id
              AND l.target_type = 'VOUCHER'
              AND l.deleted_at IS NULL
            ORDER BY v.voucher_date DESC, v.voucher_no DESC
            LIMIT 1
        ");
        $stmt->execute([':evidence_id' => $evidenceId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getEvidenceIdsByVoucherId(string $voucherId, string $exceptEvidenceId = ''): array
    {
        if ($voucherId === '' || !$this->tableExists()) {
            return [];
        }

        $params = [':voucher_id' => $voucherId];
        $exceptSql = '';
        if ($exceptEvidenceId !== '') {
            $exceptSql = 'AND evidence_id <> :except_evidence_id';
            $params[':except_evidence_id'] = $exceptEvidenceId;
        }

        $stmt = $this->db->prepare("
            SELECT evidence_id
            FROM {$this->table}
            WHERE target_type = 'VOUCHER'
              AND target_id = :voucher_id
              AND deleted_at IS NULL
              {$exceptSql}
        ");
        $stmt->execute($params);

        return array_values(array_filter(array_map(
            static fn(array $row): string => trim((string) ($row['evidence_id'] ?? '')),
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        )));
    }

    public function softDeleteVoucherLinks(string $voucherId, array $evidenceIds): void
    {
        $evidenceIds = array_values(array_filter(array_unique(array_map('strval', $evidenceIds))));
        if ($voucherId === '' || $evidenceIds === [] || !$this->tableExists()) {
            return;
        }

        $placeholders = [];
        $params = [':voucher_id' => $voucherId];
        foreach ($evidenceIds as $index => $evidenceId) {
            $key = ':evidence_id_' . $index;
            $placeholders[] = $key;
            $params[$key] = $evidenceId;
        }

        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET deleted_at = NOW(),
                updated_at = NOW()
            WHERE target_type = 'VOUCHER'
              AND target_id = :voucher_id
              AND evidence_id IN (" . implode(', ', $placeholders) . ")
              AND deleted_at IS NULL
        ");
        $stmt->execute($params);
    }

    public function linkVoucher(string $evidenceId, string $voucherId): void
    {
        if ($evidenceId === '' || $voucherId === '' || !$this->tableExists() || !$this->existsTable('ledger_data_evidences')) {
            return;
        }

        $existing = $this->db->prepare("
            SELECT id
            FROM {$this->table}
            WHERE evidence_id = :evidence_id
              AND target_type = 'VOUCHER'
              AND target_id = :voucher_id
            LIMIT 1
        ");
        $existing->execute([
            ':evidence_id' => $evidenceId,
            ':voucher_id' => $voucherId,
        ]);
        $row = $existing->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($row) {
            $stmt = $this->db->prepare("
                UPDATE {$this->table}
                SET deleted_at = NULL,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':id' => $row['id']]);
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO {$this->table}
                (id, evidence_type, evidence_id, target_type, target_id, link_type, amount, created_at, updated_at)
            SELECT
                :id, e.source_type, e.id, 'VOUCHER', :voucher_id, 'MANUAL', 0, NOW(), NOW()
            FROM ledger_data_evidences e
            WHERE e.id = :evidence_id
              AND e.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([
            ':id' => UuidHelper::generate(),
            ':voucher_id' => $voucherId,
            ':evidence_id' => $evidenceId,
        ]);
    }

    public function syncTransactionLink(string $transactionId, string $evidenceId): void
    {
        if ($transactionId === '' || !$this->tableExists()) {
            return;
        }

        $params = [':transaction_id' => $transactionId];
        $exceptSql = '';
        if ($evidenceId !== '') {
            $exceptSql = 'AND evidence_id <> :evidence_id';
            $params[':evidence_id'] = $evidenceId;
        }
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET deleted_at = NOW(), updated_at = NOW()
            WHERE target_type = 'TRANSACTION'
              AND target_id = :transaction_id
              AND deleted_at IS NULL
              {$exceptSql}
        ");
        $stmt->execute($params);

        if ($evidenceId === '' || !$this->existsTable('ledger_data_evidences')) {
            return;
        }

        $existing = $this->db->prepare("
            SELECT id
            FROM {$this->table}
            WHERE evidence_id = :evidence_id
              AND target_type = 'TRANSACTION'
              AND target_id = :transaction_id
            LIMIT 1
        ");
        $existing->execute([':evidence_id' => $evidenceId, ':transaction_id' => $transactionId]);
        $linkId = trim((string) ($existing->fetchColumn() ?: ''));
        if ($linkId !== '') {
            $restore = $this->db->prepare("
                UPDATE {$this->table}
                SET deleted_at = NULL, updated_at = NOW()
                WHERE id = :id
            ");
            $restore->execute([':id' => $linkId]);
            return;
        }

        $insert = $this->db->prepare("
            INSERT INTO {$this->table}
                (id, evidence_type, evidence_id, target_type, target_id, link_type, amount, created_at, updated_at)
            SELECT
                :id, e.source_type, e.id, 'TRANSACTION', :transaction_id, 'MANUAL', 0, NOW(), NOW()
            FROM ledger_data_evidences e
            WHERE e.id = :evidence_id
              AND e.deleted_at IS NULL
            LIMIT 1
        ");
        $insert->execute([
            ':id' => UuidHelper::generate(),
            ':transaction_id' => $transactionId,
            ':evidence_id' => $evidenceId,
        ]);
    }

    public function purgeByVoucherId(string $voucherId): void
    {
        if ($voucherId === '' || !$this->tableExists()) {
            return;
        }

        $stmt = $this->db->prepare("
            DELETE FROM {$this->table}
            WHERE target_type = 'VOUCHER'
              AND target_id = :voucher_id
        ");
        $stmt->execute([':voucher_id' => $voucherId]);
    }

    public function activeVoucherExistsForEvidence(string $evidenceId, string $excludeVoucherId = ''): bool
    {
        if ($evidenceId === '' || !$this->tableExists() || !$this->existsTable('ledger_vouchers')) {
            return false;
        }

        $params = [':evidence_id' => $evidenceId];
        $excludeSql = '';
        if ($excludeVoucherId !== '') {
            $excludeSql = 'AND v.id <> :exclude_voucher_id';
            $params[':exclude_voucher_id'] = $excludeVoucherId;
        }

        $stmt = $this->db->prepare("
            SELECT 1
            FROM {$this->table} l
            INNER JOIN ledger_vouchers v
                ON v.id = l.target_id
               AND v.deleted_at IS NULL
            WHERE l.evidence_id = :evidence_id
              AND l.target_type = 'VOUCHER'
              AND l.deleted_at IS NULL
              {$excludeSql}
            LIMIT 1
        ");
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function getProcessingItemIdsByVoucherId(string $voucherId): array
    {
        if ($voucherId === '' || !$this->tableExists() || !$this->columnExists('processing_item_id')) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT processing_item_id
            FROM {$this->table}
            WHERE target_type = 'VOUCHER'
              AND target_id = :voucher_id
              AND processing_item_id IS NOT NULL
        ");
        $stmt->execute([':voucher_id' => $voucherId]);

        return array_values(array_filter(array_map(
            static fn(array $row): string => trim((string) ($row['processing_item_id'] ?? '')),
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        )));
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
