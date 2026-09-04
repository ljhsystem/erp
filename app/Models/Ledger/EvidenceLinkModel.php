<?php

namespace App\Models\Ledger;

use Core\Database;
use Core\Helpers\UuidHelper;
use PDO;
use PDOException;

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

    public function getVoucherEvidences(string $voucherId): array
    {
        if ($voucherId === '' || !$this->tableExists()) {
            return [];
        }
        $stmt = $this->db->prepare("
            SELECT evidence_type AS import_type, evidence_id, link_type, created_at, updated_at
            FROM {$this->table}
            WHERE target_type = 'VOUCHER'
              AND target_id = :voucher_id
              AND deleted_at IS NULL
            ORDER BY created_at, id
        ");
        $stmt->execute([':voucher_id' => $voucherId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function replaceVoucherEvidences(string $voucherId, array $evidences): void
    {
        if ($voucherId === '' || !$this->tableExists()) {
            return;
        }

        $identities = [];
        foreach ($evidences as $evidence) {
            $importType = strtoupper(trim((string) ($evidence['import_type'] ?? '')));
            $evidenceId = trim((string) ($evidence['evidence_id'] ?? ''));
            if ($importType !== '' && $evidenceId !== '') {
                $identities[$importType . "\0" . $evidenceId] = [$importType, $evidenceId];
            }
        }

        $existing = $this->getVoucherEvidences($voucherId);
        foreach ($existing as $link) {
            $key = (string) $link['import_type'] . "\0" . (string) $link['evidence_id'];
            if (isset($identities[$key])) {
                unset($identities[$key]);
                continue;
            }
            $delete = $this->db->prepare("
                UPDATE {$this->table}
                SET deleted_at = NOW(), updated_at = NOW()
                WHERE evidence_type = :import_type
                  AND evidence_id = :evidence_id
                  AND target_type = 'VOUCHER'
                  AND target_id = :voucher_id
                  AND deleted_at IS NULL
            ");
            $delete->execute([
                ':import_type' => $link['import_type'],
                ':evidence_id' => $link['evidence_id'],
                ':voucher_id' => $voucherId,
            ]);
        }

        foreach ($identities as [$importType, $evidenceId]) {
            $conflict = $this->db->prepare("
                SELECT l.target_id
                FROM {$this->table} l
                INNER JOIN ledger_vouchers v ON v.id = l.target_id AND v.deleted_at IS NULL
                WHERE l.evidence_type = :import_type
                  AND l.evidence_id = :evidence_id
                  AND l.target_type = 'VOUCHER'
                  AND l.target_id <> :voucher_id
                  AND l.deleted_at IS NULL
                LIMIT 1
                FOR UPDATE
            ");
            $conflict->execute([
                ':import_type' => $importType,
                ':evidence_id' => $evidenceId,
                ':voucher_id' => $voucherId,
            ]);
            if ($conflict->fetchColumn()) {
                throw new \RuntimeException('이미 다른 전표에 연결된 증빙입니다.');
            }
            $restore = $this->db->prepare("
                SELECT id FROM {$this->table}
                WHERE evidence_type = :import_type
                  AND evidence_id = :evidence_id
                  AND target_type = 'VOUCHER'
                  AND target_id = :voucher_id
                ORDER BY deleted_at IS NULL DESC, updated_at DESC
                LIMIT 1
            ");
            $restore->execute([
                ':import_type' => $importType,
                ':evidence_id' => $evidenceId,
                ':voucher_id' => $voucherId,
            ]);
            $linkId = trim((string) ($restore->fetchColumn() ?: ''));
            if ($linkId !== '') {
                $this->db->prepare("
                    UPDATE {$this->table}
                    SET deleted_at = NULL, updated_at = NOW()
                    WHERE id = :id
                ")->execute([':id' => $linkId]);
                continue;
            }
            $this->db->prepare("
                INSERT INTO {$this->table}
                    (id, evidence_type, evidence_id, target_type, target_id, link_type, amount, created_at, updated_at)
                VALUES
                    (:id, :import_type, :evidence_id, 'VOUCHER', :voucher_id, 'MANUAL', 0, NOW(), NOW())
            ")->execute([
                ':id' => UuidHelper::generate(),
                ':import_type' => $importType,
                ':evidence_id' => $evidenceId,
                ':voucher_id' => $voucherId,
            ]);
        }
    }

    public function columnExists(string $column): bool
    {
        return $this->existsColumn($this->table, $column);
    }

    public function findLinkedVoucherInfo(string $importType, string $evidenceId): ?array
    {
        if ($importType === '' || $evidenceId === '' || !$this->tableExists() || !$this->existsTable('ledger_vouchers')) {
            return null;
        }
        $stmt = $this->db->prepare("
            SELECT v.id, v.voucher_no, v.voucher_date, v.status
            FROM {$this->table} l
            INNER JOIN ledger_vouchers v ON v.id = l.target_id AND v.deleted_at IS NULL
            WHERE l.evidence_type = :import_type
              AND l.evidence_id = :evidence_id
              AND l.target_type = 'VOUCHER'
              AND l.deleted_at IS NULL
            ORDER BY v.voucher_date DESC, v.voucher_no DESC
            LIMIT 1
        ");
        $stmt->execute([':import_type' => $importType, ':evidence_id' => $evidenceId]);
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

    public function upsertAutoVoucherEvidence(string $importType, string $evidenceId, string $voucherId): void
    {
        if ($importType === '' || $evidenceId === '' || $voucherId === '' || !$this->tableExists()) return;
        $stmt = $this->db->prepare("SELECT id FROM ledger_evidence_links WHERE evidence_type = :import_type AND evidence_id = :evidence_id AND target_type = 'VOUCHER' AND target_id = :voucher_id AND link_type = 'AUTO' ORDER BY deleted_at IS NULL DESC, updated_at DESC, created_at DESC LIMIT 1");
        $stmt->execute([':import_type' => $importType, ':evidence_id' => $evidenceId, ':voucher_id' => $voucherId]);
        $id = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($id !== '') {
            $restore = $this->db->prepare("UPDATE ledger_evidence_links SET amount = 0, deleted_at = NULL, updated_at = NOW() WHERE id = :id");
            $restore->execute([':id' => $id]);
            return;
        }
        $insert = $this->db->prepare("INSERT INTO ledger_evidence_links (id, evidence_type, evidence_id, target_type, target_id, link_type, amount, created_at, updated_at) VALUES (:id, :import_type, :evidence_id, 'VOUCHER', :voucher_id, 'AUTO', 0, NOW(), NOW())");
        $insert->execute([':id' => \Core\Helpers\UuidHelper::generate(), ':import_type' => $importType, ':evidence_id' => $evidenceId, ':voucher_id' => $voucherId]);
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

    public function getTransactionEvidences(string $transactionId): array
    {
        if ($transactionId === '' || !$this->tableExists()) {
            return [];
        }
        $stmt = $this->db->prepare("
            SELECT evidence_type AS import_type, evidence_id, link_type, created_at, updated_at
            FROM {$this->table}
            WHERE target_type = 'TRANSACTION'
              AND target_id = :transaction_id
              AND deleted_at IS NULL
            ORDER BY created_at, id
        ");
        $stmt->execute([':transaction_id' => $transactionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function activeTransactionsForEvidence(string $importType, string $evidenceId, bool $forUpdate = false): array
    {
        if ($evidenceId === '' || !$this->tableExists()) {
            return [];
        }

        $sql = "
            SELECT l.id AS link_id, l.target_id AS transaction_id
            FROM {$this->table} l
            INNER JOIN ledger_transactions transaction_row
                ON transaction_row.id = l.target_id
               AND transaction_row.deleted_at IS NULL
            WHERE l.evidence_type = :import_type
              AND l.evidence_id = :evidence_id
              AND l.target_type = 'TRANSACTION'
              AND l.deleted_at IS NULL
            ORDER BY l.created_at, l.id
        " . ($forUpdate ? ' FOR UPDATE' : '');
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':import_type' => strtoupper(trim($importType)),
            ':evidence_id' => $evidenceId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function replaceTransactionEvidences(string $transactionId, array $evidences): void
    {
        if ($transactionId === '' || !$this->tableExists()) {
            return;
        }
        $this->db->prepare("
            UPDATE {$this->table}
            SET deleted_at = NOW(), updated_at = NOW()
            WHERE target_type = 'TRANSACTION'
              AND target_id = :transaction_id
              AND deleted_at IS NULL
        ")->execute([':transaction_id' => $transactionId]);

        $identities = [];
        foreach ($evidences as $evidence) {
            $importType = strtoupper(trim((string) ($evidence['import_type'] ?? '')));
            $evidenceId = trim((string) ($evidence['evidence_id'] ?? ''));
            if ($importType !== '' && $evidenceId !== '') {
                $identities[$importType . "\0" . $evidenceId] = [$importType, $evidenceId];
            }
        }
        foreach ($identities as [$importType, $evidenceId]) {
            if (!$this->allowsMultipleTransactions($importType)) {
                $conflict = $this->db->prepare("
                SELECT l.target_id
                FROM {$this->table} l
                INNER JOIN ledger_transactions t ON t.id = l.target_id AND t.deleted_at IS NULL
                WHERE l.evidence_type = :import_type
                  AND l.evidence_id = :evidence_id
                  AND l.target_type = 'TRANSACTION'
                  AND l.target_id <> :transaction_id
                  AND l.deleted_at IS NULL
                LIMIT 1
                FOR UPDATE
            ");
                $conflict->execute([
                    ':import_type' => $importType,
                    ':evidence_id' => $evidenceId,
                    ':transaction_id' => $transactionId,
                ]);
                if ($conflict->fetchColumn()) {
                    throw new \RuntimeException('이미 다른 거래에 연결된 증빙입니다.');
                }
            }
            $existing = $this->db->prepare("
                SELECT id FROM {$this->table}
                WHERE evidence_type = :import_type
                  AND evidence_id = :evidence_id
                  AND target_type = 'TRANSACTION'
                  AND target_id = :transaction_id
                LIMIT 1
            ");
            $existing->execute([
                ':import_type' => $importType,
                ':evidence_id' => $evidenceId,
                ':transaction_id' => $transactionId,
            ]);
            $linkId = trim((string) ($existing->fetchColumn() ?: ''));
            if ($linkId !== '') {
                try {
                    $this->db->prepare("
                        UPDATE {$this->table}
                        SET deleted_at = NULL, updated_at = NOW()
                        WHERE id = :id
                    ")->execute([':id' => $linkId]);
                } catch (PDOException $e) {
                    $this->throwTransactionEvidenceConflict($e);
                }
                continue;
            }
            try {
                $this->db->prepare("
                    INSERT INTO {$this->table}
                        (id, evidence_type, evidence_id, target_type, target_id, link_type, amount, created_at, updated_at)
                    VALUES
                        (:id, :import_type, :evidence_id, 'TRANSACTION', :transaction_id, 'MANUAL', 0, NOW(), NOW())
                ")->execute([
                    ':id' => UuidHelper::generate(),
                    ':import_type' => $importType,
                    ':evidence_id' => $evidenceId,
                    ':transaction_id' => $transactionId,
                ]);
            } catch (PDOException $e) {
                $this->throwTransactionEvidenceConflict($e);
            }
        }
    }

    public function upsertAutoTransactionEvidence(string $importType, string $evidenceId, string $transactionId): void
    {
        $importType = strtoupper(trim($importType));
        if ($importType === '' || $evidenceId === '' || $transactionId === '' || !$this->tableExists()) return;
        $statement = $this->db->prepare(
            "SELECT id FROM {$this->table} WHERE evidence_type=:import_type AND evidence_id=:evidence_id "
            . "AND target_type='TRANSACTION' AND target_id=:transaction_id ORDER BY deleted_at IS NULL DESC,updated_at DESC,id LIMIT 1 FOR UPDATE"
        );
        $statement->execute([':import_type' => $importType, ':evidence_id' => $evidenceId, ':transaction_id' => $transactionId]);
        $id = trim((string) ($statement->fetchColumn() ?: ''));
        if ($id !== '') {
            $this->db->prepare("UPDATE {$this->table} SET link_type='AUTO',amount=0,deleted_at=NULL,updated_at=NOW() WHERE id=:id")
                ->execute([':id' => $id]);
            return;
        }
        $this->db->prepare(
            "INSERT INTO {$this->table} (id,evidence_type,evidence_id,target_type,target_id,link_type,amount,created_at,updated_at) "
            . "VALUES (:id,:import_type,:evidence_id,'TRANSACTION',:transaction_id,'AUTO',0,NOW(),NOW())"
        )->execute([
            ':id' => UuidHelper::generate(), ':import_type' => $importType,
            ':evidence_id' => $evidenceId, ':transaction_id' => $transactionId,
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

    public function purgeByTransactionId(string $transactionId): void
    {
        if ($transactionId === '' || !$this->tableExists()) {
            return;
        }
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE target_type = 'TRANSACTION' AND target_id = :transaction_id");
        $stmt->execute([':transaction_id' => $transactionId]);
    }

    public function softDeleteByTarget(string $targetType, string $targetId): void
    {
        if ($targetId === '' || !$this->tableExists()) {
            return;
        }
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET deleted_at = NOW(), updated_at = NOW()
            WHERE target_type = :target_type
              AND target_id = :target_id
              AND deleted_at IS NULL
        ");
        try {
            $stmt->execute([':target_type' => strtoupper($targetType), ':target_id' => $targetId]);
        } catch (PDOException $e) {
            if (strtoupper($targetType) === 'TRANSACTION') {
                $this->throwTransactionEvidenceConflict($e);
            }
            throw $e;
        }
    }

    public function restoreByTarget(string $targetType, string $targetId): void
    {
        if ($targetId === '' || !$this->tableExists()) {
            return;
        }
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET deleted_at = NULL, updated_at = NOW()
            WHERE target_type = :target_type
              AND target_id = :target_id
              AND deleted_at IS NOT NULL
        ");
        $stmt->execute([':target_type' => strtoupper($targetType), ':target_id' => $targetId]);
    }

    public function transactionRestoreHasConflict(string $transactionId): bool
    {
        if ($transactionId === '' || !$this->tableExists()) {
            return false;
        }
        $stmt = $this->db->prepare("
            SELECT 1
            FROM {$this->table} original_link
            INNER JOIN {$this->table} active_link
                ON active_link.evidence_type = original_link.evidence_type
               AND active_link.evidence_id = original_link.evidence_id
               AND active_link.target_type = 'TRANSACTION'
               AND active_link.target_id <> original_link.target_id
               AND active_link.deleted_at IS NULL
            INNER JOIN ledger_transactions active_transaction
                ON active_transaction.id = active_link.target_id
               AND active_transaction.deleted_at IS NULL
            LEFT JOIN ledger_evidence_metadata metadata
                ON metadata.import_type = original_link.evidence_type
               AND metadata.deleted_at IS NULL
            WHERE original_link.target_type = 'TRANSACTION'
              AND original_link.target_id = :transaction_id
              AND COALESCE(metadata.transaction_cardinality, 'SINGLE_TRANSACTION') = 'SINGLE_TRANSACTION'
            LIMIT 1
        ");
        $stmt->execute([':transaction_id' => $transactionId]);
        return (bool) $stmt->fetchColumn();
    }

    private function throwTransactionEvidenceConflict(PDOException $e): never
    {
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        if ($e->getCode() === '23000' && $driverCode === 1062
            && str_contains((string) ($e->errorInfo[2] ?? $e->getMessage()), 'uk_evl_active_evidence_target_pair')) {
            throw new \RuntimeException('이미 연결된 증빙과 거래입니다.', 0, $e);
        }
        throw $e;
    }

    public function countActiveTransactionLinks(array $transactionIds): int
    {
        $transactionIds = array_values(array_filter(array_map('strval', $transactionIds)));
        if ($transactionIds === [] || !$this->tableExists()) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM {$this->table}
             WHERE target_type = 'TRANSACTION'
               AND target_id IN ({$placeholders})
               AND deleted_at IS NULL"
        );
        $stmt->execute($transactionIds);
        return (int) $stmt->fetchColumn();
    }

    public function hasActiveLink(string $importType, string $evidenceId): bool
    {
        if ($importType === '' || $evidenceId === '' || !$this->tableExists()) return false;
        $stmt = $this->db->prepare("SELECT 1 FROM {$this->table} WHERE evidence_type = :import_type
            AND evidence_id = :evidence_id AND target_type IN ('TRANSACTION', 'VOUCHER')
            AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([':import_type' => strtoupper($importType), ':evidence_id' => $evidenceId]);
        return (bool) $stmt->fetchColumn();
    }

    public function deleteByEvidenceIdentity(string $importType, string $evidenceId): int
    {
        if ($importType === '' || $evidenceId === '' || !$this->tableExists()) return 0;
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE evidence_type = :import_type AND evidence_id = :evidence_id");
        $stmt->execute([':import_type' => strtoupper($importType), ':evidence_id' => $evidenceId]);
        return $stmt->rowCount();
    }

    public function lockPaymentAllocationsByEvidence(string $evidenceId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, evidence_type, evidence_id, target_type, target_id,
                   link_type, amount, memo, created_at, updated_at, deleted_at
            FROM {$this->table}
            WHERE evidence_type = 'BANK_TRANSACTION'
              AND evidence_id = :evidence_id
              AND target_type = 'PAYMENT_SCHEDULE'
              AND deleted_at IS NULL
            FOR UPDATE
        ");
        $stmt->execute([':evidence_id' => $evidenceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function lockPaymentAllocationsBySchedule(string $scheduleId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, evidence_type, evidence_id, target_type, target_id,
                   link_type, amount, memo, created_at, updated_at, deleted_at
            FROM {$this->table}
            WHERE target_type = 'PAYMENT_SCHEDULE'
              AND target_id = :target_id
              AND deleted_at IS NULL
            FOR UPDATE
        ");
        $stmt->execute([':target_id' => $scheduleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function upsertPaymentAllocation(
        string $evidenceId,
        string $scheduleId,
        string $amount,
        ?string $memo = null
    ): string {
        $stmt = $this->db->prepare("
            SELECT id
            FROM {$this->table}
            WHERE evidence_type = 'BANK_TRANSACTION'
              AND evidence_id = :evidence_id
              AND target_type = 'PAYMENT_SCHEDULE'
              AND target_id = :target_id
              AND link_type = 'PAYMENT'
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([':evidence_id' => $evidenceId, ':target_id' => $scheduleId]);
        $id = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($id === '') {
            $id = UuidHelper::generate();
            $insert = $this->db->prepare("
                INSERT INTO {$this->table} (
                    id, evidence_type, evidence_id, target_type, target_id,
                    link_type, amount, memo, created_at, updated_at
                )
                VALUES (
                    :id, 'BANK_TRANSACTION', :evidence_id, 'PAYMENT_SCHEDULE',
                    :target_id, 'PAYMENT', :amount, :memo, NOW(), NOW()
                )
            ");
            $insert->execute([
                ':id' => $id,
                ':evidence_id' => $evidenceId,
                ':target_id' => $scheduleId,
                ':amount' => $amount,
                ':memo' => $memo,
            ]);
            return $id;
        }

        $update = $this->db->prepare("
            UPDATE {$this->table}
            SET amount = :amount, memo = :memo, deleted_at = NULL, updated_at = NOW()
            WHERE id = :id
        ");
        $update->execute([':id' => $id, ':amount' => $amount, ':memo' => $memo]);
        return $id;
    }

    public function softDeletePaymentAllocation(string $linkId): int
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET deleted_at = NOW(), updated_at = NOW()
            WHERE id = :id
              AND target_type = 'PAYMENT_SCHEDULE'
              AND deleted_at IS NULL
        ");
        $stmt->execute([':id' => $linkId]);
        return $stmt->rowCount();
    }

    public function getPaymentAllocations(string $scheduleId): array
    {
        $stmt = $this->db->prepare("
            SELECT link.id, link.evidence_type AS import_type, link.evidence_id,
                   link.amount, link.memo, link.created_at, link.updated_at,
                   bank.raw_transaction_datetime AS transaction_datetime,
                   bank.raw_description AS description,
                   bank.raw_withdraw_amount AS withdraw_amount,
                   account.account_name, account.bank_name, account.account_number
            FROM {$this->table} link
            INNER JOIN ledger_evidence_bank_transaction bank
              ON bank.id = link.evidence_id AND bank.deleted_at IS NULL
            LEFT JOIN system_bank_accounts account ON account.id = bank.bank_account_id
            WHERE link.target_type = 'PAYMENT_SCHEDULE'
              AND link.target_id = :target_id
              AND link.deleted_at IS NULL
            ORDER BY bank.raw_transaction_datetime DESC, link.created_at DESC
        ");
        $stmt->execute([':target_id' => $scheduleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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

    private function allowsMultipleTransactions(string $importType): bool
    {
        $stmt=$this->db->prepare("SELECT transaction_cardinality FROM ledger_evidence_metadata WHERE import_type=:type AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([':type'=>strtoupper(trim($importType))]);
        return $stmt->fetchColumn()==='MULTI_TRANSACTION';
    }
}
