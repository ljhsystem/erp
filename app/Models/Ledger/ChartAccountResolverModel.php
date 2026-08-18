<?php

namespace App\Models\Ledger;

use PDO;

class ChartAccountResolverModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function resolveActiveByIdCodeOrName(string $value, string $accountCode): ?array
    {
        foreach ([['id', $value], ['account_code', $accountCode !== '' ? $accountCode : $value]] as [$column, $needle]) {
            if ($needle === '') {
                continue;
            }
            $stmt = $this->db->prepare("SELECT id FROM ledger_accounts WHERE deleted_at IS NULL AND {$column} = :value LIMIT 1");
            $stmt->execute([':value' => $needle]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($row) {
                return $row;
            }
        }

        if ($value === '') {
            return null;
        }
        $stmt = $this->db->prepare('SELECT id FROM ledger_accounts WHERE deleted_at IS NULL AND account_name = :value LIMIT 2');
        $stmt->execute([':value' => $value]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (count($rows) > 1) {
            throw new \InvalidArgumentException('동일한 계정과목명이 여러 건입니다. 계정코드를 입력하세요.');
        }
        return $rows[0] ?? null;
    }
}
