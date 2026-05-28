<?php

namespace App\Services\Ledger;

use Core\Database;
use PDO;

class ProcessingItemAggregateService
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function syncEvidenceHeaderStatus(string $evidenceId, string $actor): void
    {
        if ($evidenceId === '' || !$this->tableExists('ledger_processing_items')) {
            return;
        }

        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total_count,
                SUM(CASE WHEN transaction_status = 'CREATED' THEN 1 ELSE 0 END) AS created_count,
                SUM(CASE WHEN transaction_status = 'PROCESSING' THEN 1 ELSE 0 END) AS processing_count,
                SUM(CASE WHEN transaction_status = 'ERROR' THEN 1 ELSE 0 END) AS error_count,
                SUM(CASE WHEN transaction_status = 'NONE' THEN 1 ELSE 0 END) AS none_count
            FROM ledger_processing_items
            WHERE source_table = 'ledger_data_evidences'
              AND source_id = :evidence_id
              AND deleted_at IS NULL
              AND is_current = 1
        ");
        $stmt->execute([':evidence_id' => $evidenceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $total = (int) ($row['total_count'] ?? 0);
        if ($total <= 0) {
            return;
        }

        $status = 'NONE';
        if ((int) ($row['error_count'] ?? 0) > 0) {
            $status = 'ERROR';
        } elseif ((int) ($row['processing_count'] ?? 0) > 0) {
            $status = 'PROCESSING';
        } elseif ((int) ($row['created_count'] ?? 0) === $total) {
            $status = 'CREATED';
        } elseif ((int) ($row['created_count'] ?? 0) > 0 || (int) ($row['none_count'] ?? 0) > 0) {
            $status = 'PARTIAL';
        }

        $this->db->prepare("
            UPDATE ledger_data_evidences
            SET transaction_status = :transaction_status,
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE id = :id
        ")->execute([
            ':id' => $evidenceId,
            ':transaction_status' => $status,
            ':updated_by' => $actor,
        ]);
    }

    private function tableExists(string $tableName): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
            LIMIT 1
        ");
        $stmt->execute([':table_name' => $tableName]);

        return (bool) $stmt->fetchColumn();
    }
}
