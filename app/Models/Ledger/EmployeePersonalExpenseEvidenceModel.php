<?php

namespace App\Models\Ledger;

use PDO;

class EmployeePersonalExpenseEvidenceModel
{
    private const TABLE = 'ledger_evidence_employee_personal_expense';
    private ?array $writableColumns = null;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findBySourceItemId(string $sourceItemId, bool $forUpdate = false): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . self::TABLE . ' WHERE source_personal_expense_item_id = :source_item_id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
        $stmt->execute([':source_item_id' => $sourceItemId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function nextSortNo(): int
    {
        return max(1, (int) $this->pdo->query('SELECT COALESCE(MAX(sort_no), 0) + 1 FROM ' . self::TABLE)->fetchColumn());
    }

    public function insert(array $data): void
    {
        $columns = array_values(array_intersect(array_keys($data), $this->writableColumns()));
        if ($columns === []) {
            throw new \RuntimeException('개인경비 증빙에 저장할 수 있는 컬럼이 없습니다.');
        }
        $stmt = $this->pdo->prepare('INSERT INTO ' . self::TABLE . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', array_map(static fn($c) => ':' . $c, $columns)) . ')');
        $params=[]; foreach($columns as $column) $params[':' . $column] = $data[$column] ?? null;
        $stmt->execute($params);
    }

    private function writableColumns(): array
    {
        if ($this->writableColumns !== null) {
            return $this->writableColumns;
        }
        $stmt = $this->pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name'
        );
        $stmt->execute([':table_name' => self::TABLE]);
        return $this->writableColumns = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
}
