<?php

namespace App\Models\Ledger;

use PDO;

class VoucherLineSourceRefModel
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function insert(array $row): void
    {
        $columns = array_keys($row);
        $stmt = $this->pdo->prepare(sprintf(
            'INSERT INTO ledger_voucher_line_source_refs (%s) VALUES (%s)',
            implode(',', $columns),
            implode(',', array_map(static fn(string $column): string => ':' . $column, $columns))
        ));
        $stmt->execute(array_combine(array_map(static fn(string $column): string => ':' . $column, $columns), array_values($row)));
    }

    public function byVoucher(string $companyId, string $voucherId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ledger_voucher_line_source_refs WHERE company_id=:company_id AND voucher_id=:voucher_id ORDER BY voucher_line_id,allocation_sequence,id');
        $stmt->execute([':company_id' => $companyId, ':voucher_id' => $voucherId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function groupedByVoucherLine(string $companyId, string $voucherId): array
    {
        $grouped = [];
        foreach ($this->byVoucher($companyId, $voucherId) as $row) {
            $grouped[(string) $row['voucher_line_id']][] = $row;
        }
        return $grouped;
    }

    public function deleteByVoucher(string $companyId, string $voucherId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM ledger_voucher_line_source_refs WHERE company_id=:company_id AND voucher_id=:voucher_id');
        $stmt->execute([':company_id' => $companyId, ':voucher_id' => $voucherId]);
    }

    public function sourceRef(string $companyId, string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ledger_voucher_line_source_refs WHERE company_id=:company_id AND id=:id LIMIT 1');
        $stmt->execute([':company_id' => $companyId, ':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function ruleRevisionExists(string $ruleId, int $revisionNo, string $roleCode, string $side): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM ledger_journal_rules r
              JOIN ledger_journal_rule_revisions rv
                ON rv.rule_id=r.id AND rv.revision_no=:revision_no
             WHERE r.id=:rule_id AND r.accounting_role_code=:role_code AND r.debit_credit=:side
             LIMIT 1'
        );
        $stmt->execute([
            ':revision_no' => $revisionNo,
            ':rule_id' => $ruleId,
            ':role_code' => $roleCode,
            ':side' => $side,
        ]);
        return (bool) $stmt->fetchColumn();
    }
}
