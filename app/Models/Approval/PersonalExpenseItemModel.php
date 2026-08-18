<?php

namespace App\Models\Approval;

use PDO;

class PersonalExpenseItemModel
{
    private const COLUMNS = [
        'expense_date', 'expense_category', 'payment_method', 'receipt_type',
        'merchant_name', 'merchant_business_no', 'merchant_representative',
        'merchant_address', 'merchant_address_detail', 'merchant_phone', 'project_id', 'client_id',
        'item_name', 'item_specification', 'item_unit_name', 'item_quantity',
        'item_unit_price', 'item_supply_amount', 'item_vat_amount', 'item_total_amount',
        'item_description', 'item_memo',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listForHeader(string $headerId, bool $forUpdate = false): array
    {
        $sql = 'SELECT item.*, project.project_name,
                       COALESCE(client.client_name, client.company_name) AS client_name
                FROM approval_personal_expense_items item
                LEFT JOIN system_projects project ON project.id = item.project_id AND project.deleted_at IS NULL
                LEFT JOIN system_clients client ON client.id = item.client_id AND client.deleted_at IS NULL
                WHERE item.personal_expense_id = :header_id AND item.deleted_at IS NULL
                ORDER BY item.sort_no, item.created_at' . ($forUpdate ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':header_id' => $headerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listForHeaders(array $headerIds): array
    {
        $headerIds = array_values(array_unique(array_filter(array_map('strval', $headerIds))));
        if ($headerIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($headerIds as $index => $headerId) {
            $key = ':header_' . $index;
            $placeholders[] = $key;
            $params[$key] = $headerId;
        }

        $sql = 'SELECT item.*, project.project_name,
                       COALESCE(client.client_name, client.company_name) AS client_name
                FROM approval_personal_expense_items item
                LEFT JOIN system_projects project ON project.id = item.project_id AND project.deleted_at IS NULL
                LEFT JOIN system_clients client ON client.id = item.client_id AND client.deleted_at IS NULL
                WHERE item.personal_expense_id IN (' . implode(',', $placeholders) . ')
                  AND item.deleted_at IS NULL
                ORDER BY item.personal_expense_id, item.sort_no, item.created_at';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function activeAggregate(string $headerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS item_count,
                    COALESCE(SUM(item_supply_amount), 0) AS supply_amount,
                    COALESCE(SUM(item_vat_amount), 0) AS vat_amount,
                    COALESCE(SUM(item_total_amount), 0) AS total_amount
               FROM approval_personal_expense_items
              WHERE personal_expense_id = :header_id
                AND deleted_at IS NULL'
        );
        $stmt->execute([':header_id' => $headerId]);
        $aggregate = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'item_count' => (int) ($aggregate['item_count'] ?? 0),
            'supply_amount' => (string) ($aggregate['supply_amount'] ?? '0.00'),
            'vat_amount' => (string) ($aggregate['vat_amount'] ?? '0.00'),
            'total_amount' => (string) ($aggregate['total_amount'] ?? '0.00'),
        ];
    }

    public function insert(string $id, string $headerId, int $sortNo, array $data, string $actor): void
    {
        $columns = array_merge(['id', 'sort_no', 'personal_expense_id'], self::COLUMNS, ['created_by', 'updated_by']);
        $values = array_merge(['id' => $id, 'sort_no' => $sortNo, 'personal_expense_id' => $headerId], $data, ['created_by' => $actor, 'updated_by' => $actor]);
        $stmt = $this->pdo->prepare('INSERT INTO approval_personal_expense_items (' . implode(',', $columns) . ') VALUES (' . implode(',', array_map(static fn ($column) => ':' . $column, $columns)) . ')');
        $params = [];
        foreach ($columns as $column) {
            $params[':' . $column] = $values[$column] ?? null;
        }
        $stmt->execute($params);
    }

    public function reserveSortNumbers(string $headerId): void
    {
        $stmt = $this->pdo->prepare('UPDATE approval_personal_expense_items SET sort_no = sort_no + 1000000 WHERE personal_expense_id = :header_id AND deleted_at IS NULL');
        $stmt->execute([':header_id' => $headerId]);
    }

    public function updateOwned(string $id, string $headerId, int $sortNo, array $data, string $actor): int
    {
        $columns = array_merge(['sort_no'], self::COLUMNS, ['updated_by']);
        $values = array_merge(['sort_no' => $sortNo], $data, ['updated_by' => $actor]);
        $sets = array_map(static fn ($column) => "{$column} = :{$column}", $columns);
        $stmt = $this->pdo->prepare('UPDATE approval_personal_expense_items SET ' . implode(',', $sets) . ', updated_at = NOW() WHERE id = :id AND personal_expense_id = :header_id AND deleted_at IS NULL');
        $params = [':id' => $id, ':header_id' => $headerId];
        foreach ($columns as $column) {
            $params[':' . $column] = $values[$column] ?? null;
        }
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function softDeleteMissing(string $headerId, array $keepIds, string $actor): void
    {
        $sql = 'UPDATE approval_personal_expense_items SET deleted_at = NOW(), deleted_by = :deleted_by, updated_at = NOW(), updated_by = :updated_by WHERE personal_expense_id = :header_id AND deleted_at IS NULL';
        $params = [':deleted_by' => $actor, ':updated_by' => $actor, ':header_id' => $headerId];
        if ($keepIds !== []) {
            $placeholders = [];
            foreach (array_values($keepIds) as $index => $id) {
                $key = ':keep_' . $index;
                $placeholders[] = $key;
                $params[$key] = $id;
            }
            $sql .= ' AND id NOT IN (' . implode(',', $placeholders) . ')';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function evidenceReferenceCountForHeader(string $headerId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*)
            FROM ledger_evidence_employee_personal_expense evidence
            INNER JOIN approval_personal_expense_items item
              ON item.id = evidence.source_personal_expense_item_id
            WHERE item.personal_expense_id = :header_id");
        $stmt->execute([':header_id' => $headerId]);
        return (int) $stmt->fetchColumn();
    }

    public function purgeForHeader(string $headerId): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM approval_personal_expense_items WHERE personal_expense_id = :header_id');
        $stmt->execute([':header_id' => $headerId]);
        return $stmt->rowCount();
    }
}
