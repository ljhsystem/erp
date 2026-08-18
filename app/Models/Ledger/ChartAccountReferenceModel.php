<?php

namespace App\Models\Ledger;

use PDO;

class ChartAccountReferenceModel
{
    private const REFERENCES = [
        ['table' => 'ledger_voucher_lines', 'column' => 'account_id', 'label' => '전표라인'],
        ['table' => 'ledger_journal_rules', 'column' => 'debit_account_id', 'label' => '분개규칙 차변계정'],
        ['table' => 'ledger_journal_rules', 'column' => 'credit_account_id', 'label' => '분개규칙 대변계정'],
        ['table' => 'ledger_journal_rules', 'column' => 'vat_account_id', 'label' => '분개규칙 부가세계정'],
        ['table' => 'ledger_journal_client_account_patterns', 'column' => 'account_id', 'label' => '거래처 분개학습'],
        ['table' => 'ledger_journal_recent_patterns', 'column' => 'debit_account_id', 'label' => '최근 분개패턴 차변계정'],
        ['table' => 'ledger_journal_recent_patterns', 'column' => 'credit_account_id', 'label' => '최근 분개패턴 대변계정'],
        ['table' => 'ledger_journal_recent_patterns', 'column' => 'vat_account_id', 'label' => '최근 분개패턴 부가세계정'],
        ['table' => 'ledger_journal_learning_events', 'column' => 'final_account_id', 'label' => '분개 학습 최종계정'],
        ['table' => 'system_clients', 'column' => 'default_account_id', 'label' => '거래처 기본계정'],
    ];

    public function __construct(private readonly PDO $db)
    {
    }

    public function deletedIds(array $ids = []): array
    {
        $sql = 'SELECT id FROM ledger_accounts WHERE deleted_at IS NOT NULL';
        $params = [];
        if ($ids !== []) {
            $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
            $sql .= ' AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params = $ids;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function blockingChildren(array $ids): int
    {
        [$placeholders, $params] = $this->placeholders($ids);
        $sql = "SELECT COUNT(*) FROM ledger_accounts WHERE parent_id IN ({$placeholders}) AND id NOT IN ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([...$params, ...$params]);
        return (int) $stmt->fetchColumn();
    }

    public function referenceCounts(array $ids): array
    {
        [$placeholders, $params] = $this->placeholders($ids);
        $counts = [];
        foreach (self::REFERENCES as $reference) {
            if (!$this->columnExists($reference['table'], $reference['column'])) {
                continue;
            }

            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM `{$reference['table']}` WHERE `{$reference['column']}` IN ({$placeholders})"
            );
            $stmt->execute($params);
            $count = (int) $stmt->fetchColumn();
            if ($count > 0) {
                $counts[] = ['label' => $reference['label'], 'count' => $count];
            }
        }

        return $counts;
    }

    private function placeholders(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
        if ($ids === []) {
            throw new \InvalidArgumentException('계정 ID가 없습니다.');
        }
        return [implode(',', array_fill(0, count($ids), '?')), $ids];
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column LIMIT 1');
        $stmt->execute([':table' => $table, ':column' => $column]);
        return (bool) $stmt->fetchColumn();
    }
}
