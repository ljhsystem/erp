<?php

namespace App\Models\Funds;

use PDO;

class BankPaymentEvidenceModel
{
    public function __construct(private PDO $pdo)
    {
    }

    public function lockWithdrawal(string $evidenceId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT evidence.id,
                   evidence.transaction_direction,
                   evidence.raw_deposit_amount AS deposit_amount,
                   evidence.raw_withdraw_amount AS withdraw_amount,
                   evidence.bank_account_id,
                   account.currency
            FROM ledger_evidence_bank_transaction evidence
            LEFT JOIN system_bank_accounts account
              ON account.id = evidence.bank_account_id
             AND account.deleted_at IS NULL
            WHERE evidence.id = :id
              AND evidence.deleted_at IS NULL
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([':id' => $evidenceId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function searchWithdrawals(string $query = '', int $limit = 30): array
    {
        $conditions = [
            'evidence.deleted_at IS NULL',
            'COALESCE(evidence.raw_withdraw_amount, 0) > 0',
            'COALESCE(evidence.raw_deposit_amount, 0) = 0',
        ];
        $params = [];
        if ($query !== '') {
            $conditions[] = "(evidence.raw_description LIKE :query
                OR evidence.raw_counterparty_name LIKE :query
                OR account.account_name LIKE :query
                OR account.account_number LIKE :query)";
            $params[':query'] = '%' . $query . '%';
        }
        $sql = "
            SELECT evidence.id, evidence.raw_transaction_datetime AS transaction_datetime,
                   evidence.raw_description AS description,
                   evidence.raw_counterparty_name AS counterparty_name,
                   evidence.raw_withdraw_amount AS withdraw_amount,
                   account.account_name, account.bank_name, account.account_number,
                   GREATEST(
                       evidence.raw_withdraw_amount - COALESCE(allocation.allocated_amount, 0),
                       0
                   ) AS available_amount
            FROM ledger_evidence_bank_transaction evidence
            LEFT JOIN system_bank_accounts account
              ON account.id = evidence.bank_account_id AND account.deleted_at IS NULL
            LEFT JOIN (
                SELECT evidence_id, SUM(amount) AS allocated_amount
                FROM ledger_evidence_links
                WHERE evidence_type = 'BANK_TRANSACTION'
                  AND target_type = 'PAYMENT_SCHEDULE'
                  AND link_type = 'PAYMENT'
                  AND deleted_at IS NULL
                GROUP BY evidence_id
            ) allocation ON allocation.evidence_id = evidence.id
            WHERE " . implode(' AND ', $conditions) . "
            HAVING available_amount > 0
            ORDER BY evidence.raw_transaction_datetime DESC, evidence.sort_no DESC
            LIMIT :limit
        ";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
