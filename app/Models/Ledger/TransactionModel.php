<?php

namespace App\Models\Ledger;

use Core\Database;
use PDO;

class TransactionModel
{
    protected string $table = 'ledger_transactions';

    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getList(array $filters = []): array
    {
        $sql = "
            SELECT
                t.*,
                COALESCE(sc.client_name, '') AS client_name,
                COALESCE(sp.project_name, '') AS project_name
            FROM {$this->table} t
            LEFT JOIN system_clients sc
                ON t.client_id = sc.id
            LEFT JOIN system_projects sp
                ON t.project_id = sp.id
            WHERE t.deleted_at IS NULL
        ";

        $params = [];

        if (!empty($filters['source_type'])) {
            $sql .= " AND t.source_type = :source_type";
            $params[':source_type'] = $filters['source_type'];
        }

        if (!empty($filters['work_unit'])) {
            $sql .= " AND t.work_unit = :work_unit";
            $params[':work_unit'] = $filters['work_unit'];
        }

        if (!empty($filters['transaction_type'])) {
            $sql .= " AND t.transaction_type = :transaction_type";
            $params[':transaction_type'] = $filters['transaction_type'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND t.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['doc_status'])) {
            $sql .= " AND t.doc_status = :doc_status";
            $params[':doc_status'] = $filters['doc_status'];
        }

        if (!empty($filters['match_status'])) {
            $sql .= " AND t.match_status = :match_status";
            $params[':match_status'] = $filters['match_status'];
        }

        if (!empty($filters['acct_status'])) {
            $sql .= " AND t.acct_status = :acct_status";
            $params[':acct_status'] = $filters['acct_status'];
        }

        if (!empty($filters['project_id'])) {
            $sql .= " AND t.project_id = :project_id";
            $params[':project_id'] = $filters['project_id'];
        }

        if (!empty($filters['client_id'])) {
            $sql .= " AND t.client_id = :client_id";
            $params[':client_id'] = $filters['client_id'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND t.transaction_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND t.transaction_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $sql .= " ORDER BY t.sort_no DESC, t.transaction_date DESC, t.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getUnpostedList(): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM {$this->table}
            WHERE deleted_at IS NULL
              AND acct_status = 'unposted'
            ORDER BY sort_no DESC, transaction_date DESC, created_at DESC
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                t.*,
                COALESCE(sc.client_name, '') AS client_name,
                COALESCE(sp.project_name, '') AS project_name
            FROM {$this->table} t
            LEFT JOIN system_clients sc
                ON t.client_id = sc.id
            LEFT JOIN system_projects sp
                ON t.project_id = sp.id
            WHERE t.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insert(array $data): bool
    {
        $allowed = [
            'id',
            'sort_no',
            'work_unit',
            'source_type',
            'transaction_type',
            'transaction_date',
            'project_id',
            'client_id',
            'bank_account_id',
            'card_id',
            'employee_id',
            'order_ref',
            'document_type',
            'document_no',
            'ref_type',
            'tax_type',
            'item_summary',
            'description',
            'specification',
            'unit_name',
            'quantity',
            'unit_price',
            'supply_amount',
            'vat_amount',
            'total_amount',
            'currency',
            'exchange_rate',
            'status',
            'doc_status',
            'match_status',
            'acct_status',
            'is_active',
            'evidence_file_path',
            'note',
            'memo',
            'created_at',
            'created_by',
            'updated_at',
            'updated_by',
            'deleted_at',
            'deleted_by',
        ];

        $payload = $this->filterData($data, $allowed);

        if (!isset($payload['id'], $payload['source_type'], $payload['transaction_type'], $payload['transaction_date'])) {
            return false;
        }

        $columns = array_keys($payload);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->db->prepare($sql);

        return $stmt->execute($this->bindParams($payload));
    }

    public function update(string $id, array $data): bool
    {
        $allowed = [
            'source_type',
            'work_unit',
            'transaction_type',
            'transaction_date',
            'project_id',
            'client_id',
            'bank_account_id',
            'card_id',
            'employee_id',
            'order_ref',
            'document_type',
            'document_no',
            'ref_type',
            'tax_type',
            'item_summary',
            'description',
            'specification',
            'unit_name',
            'quantity',
            'unit_price',
            'supply_amount',
            'vat_amount',
            'total_amount',
            'currency',
            'exchange_rate',
            'status',
            'doc_status',
            'match_status',
            'acct_status',
            'is_active',
            'evidence_file_path',
            'note',
            'memo',
            'updated_at',
            'updated_by',
            'deleted_at',
            'deleted_by',
        ];

        $payload = $this->filterData($data, $allowed);

        if ($payload === []) {
            return false;
        }

        $set = [];
        foreach (array_keys($payload) as $column) {
            $set[] = "{$column} = :{$column}";
        }

        $sql = "
            UPDATE {$this->table}
            SET " . implode(', ', $set) . "
            WHERE id = :id
        ";

        $params = $this->bindParams($payload);
        $params[':id'] = $id;

        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    public function softDelete(string $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET is_active = 0,
                deleted_at = NOW()
            WHERE id = :id
              AND deleted_at IS NULL
        ");

        return $stmt->execute([':id' => $id]);
    }

    public function restore(string $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET is_active = 1,
                deleted_at = NULL,
                deleted_by = NULL
            WHERE id = :id
        ");

        return $stmt->execute([':id' => $id]);
    }

    public function hardDelete(string $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM {$this->table}
            WHERE id = :id
        ");

        return $stmt->execute([':id' => $id]);
    }

    private function filterData(array $data, array $allowed): array
    {
        $payload = [];

        foreach ($allowed as $column) {
            if (array_key_exists($column, $data)) {
                $payload[$column] = $data[$column];
            }
        }

        return $payload;
    }

    private function bindParams(array $data): array
    {
        $params = [];

        foreach ($data as $column => $value) {
            $params[':' . $column] = $value;
        }

        return $params;
    }
}
