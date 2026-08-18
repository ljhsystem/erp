<?php

namespace App\Models\Ledger;

use PDO;

final class VoucherPostingValidationModel
{
    public function __construct(private PDO $db)
    {
    }

    public function activePostingAccountIds(array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        $stmt = $this->db->prepare('SELECT id FROM ledger_accounts WHERE id IN ('
            . implode(',', array_fill(0, count($accountIds), '?'))
            . ') AND deleted_at IS NULL AND is_active = 1 AND is_posting = 1');
        $stmt->execute(array_values($accountIds));

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
}
