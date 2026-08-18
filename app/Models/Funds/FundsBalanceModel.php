<?php

namespace App\Models\Funds;

use PDO;

class FundsBalanceModel
{
    public function __construct(private PDO $pdo)
    {
    }

    public function accountingBalancesByBankAccount(?string $throughDate = null): array
    {
        $dateCondition = $throughDate !== null ? 'AND voucher.voucher_date <= :through_date' : '';
        $stmt = $this->pdo->prepare("
            SELECT ref.ref_id AS bank_account_id,
                   SUM(line.debit - line.credit) AS accounting_balance,
                   MAX(voucher.voucher_date) AS last_voucher_date
            FROM ledger_voucher_lines line
            INNER JOIN ledger_vouchers voucher
              ON voucher.id = line.voucher_id
             AND voucher.deleted_at IS NULL
             AND UPPER(voucher.status) IN ('POSTED', 'CLOSED')
            INNER JOIN ledger_voucher_line_refs ref
              ON ref.voucher_line_id = line.id
             AND ref.ref_target IN ('ACCOUNT', 'BANK', 'BANK_ACCOUNT')
            INNER JOIN system_bank_accounts bank
              ON bank.id = ref.ref_id
             AND bank.deleted_at IS NULL
            WHERE 1 = 1
              {$dateCondition}
            GROUP BY ref.ref_id
        ");
        $stmt->execute($throughDate !== null ? [':through_date' => $throughDate] : []);

        $balances = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $balances[(string) $row['bank_account_id']] = [
                'balance' => (float) $row['accounting_balance'],
                'last_voucher_date' => $row['last_voucher_date'],
            ];
        }
        return $balances;
    }
}
