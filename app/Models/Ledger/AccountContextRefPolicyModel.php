<?php

namespace App\Models\Ledger;

use PDO;

class AccountContextRefPolicyModel
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function matching(array $context): array
    {
        $stmt = $this->pdo->prepare("SELECT p.*,s.account_id,s.ref_target,s.is_required AS account_required
            FROM ledger_account_context_ref_policies p
            INNER JOIN ledger_accounts_sub s ON s.id=p.account_sub_policy_id
            INNER JOIN ledger_accounts a ON a.id=s.account_id
            WHERE p.company_id=:company_id AND s.account_id=:account_id
              AND p.operation_type=:operation_type AND p.accounting_role_code=:accounting_role_code
              AND p.is_active=1 AND p.deleted_at IS NULL
              AND p.effective_from<=:base_date AND (p.effective_to IS NULL OR p.effective_to>=:base_date)
              AND a.is_active=1 AND a.is_posting=1 AND a.deleted_at IS NULL
            ORDER BY p.effective_from DESC,p.sort_no,p.id");
        $stmt->execute([
            ':company_id' => $context['company_id'], ':account_id' => $context['account_id'],
            ':operation_type' => $context['operation_type'], ':accounting_role_code' => $context['accounting_role_code'],
            ':base_date' => $context['base_date'],
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function overlapping(array $data, ?string $excludeId = null, bool $lock = false): array
    {
        $sql = "SELECT * FROM ledger_account_context_ref_policies
            WHERE company_id=:company_id AND account_sub_policy_id=:account_sub_policy_id
              AND operation_type=:operation_type AND accounting_role_code=:accounting_role_code
              AND deleted_at IS NULL AND id<>:exclude_id
              AND effective_from<=COALESCE(:effective_to,'9999-12-31')
              AND COALESCE(effective_to,'9999-12-31')>=:effective_from" . ($lock ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':company_id' => $data['company_id'], ':account_sub_policy_id' => $data['account_sub_policy_id'],
            ':operation_type' => $data['operation_type'], ':accounting_role_code' => $data['accounting_role_code'],
            ':effective_from' => $data['effective_from'], ':effective_to' => $data['effective_to'], ':exclude_id' => $excludeId ?? '',
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function allowedPolicy(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT s.*,a.is_active AS account_active,a.is_posting,a.deleted_at AS account_deleted_at FROM ledger_accounts_sub s INNER JOIN ledger_accounts a ON a.id=s.account_id WHERE s.id=:id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): bool
    {
        $stmt = $this->pdo->prepare("INSERT INTO ledger_account_context_ref_policies
            (id,company_id,account_sub_policy_id,operation_type,accounting_role_code,effective_from,effective_to,is_active,sort_no,created_by,updated_by)
            VALUES (:id,:company_id,:account_sub_policy_id,:operation_type,:accounting_role_code,:effective_from,:effective_to,:is_active,:sort_no,:created_by,:updated_by)");
        return $stmt->execute($data);
    }

    public function referencedAllowedPolicyIds(array $ids, bool $activeOnly = false): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
        if ($ids === []) return [];
        $stmt = $this->pdo->prepare('SELECT DISTINCT account_sub_policy_id FROM ledger_account_context_ref_policies WHERE account_sub_policy_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')' . ($activeOnly ? ' AND deleted_at IS NULL AND is_active=1' : ''));
        $stmt->execute($ids);
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function softDelete(string $id, string $actor): bool
    {
        $stmt = $this->pdo->prepare('UPDATE ledger_account_context_ref_policies SET is_active=0,deleted_at=NOW(),deleted_by=:actor,updated_by=:actor WHERE id=:id AND deleted_at IS NULL');
        $stmt->execute([':id' => $id, ':actor' => $actor]);
        return $stmt->rowCount() === 1;
    }

    public function restore(string $id, string $actor): bool
    {
        $stmt = $this->pdo->prepare('UPDATE ledger_account_context_ref_policies SET deleted_at=NULL,deleted_by=NULL,updated_by=:actor WHERE id=:id AND deleted_at IS NOT NULL');
        $stmt->execute([':id' => $id, ':actor' => $actor]);
        return $stmt->rowCount() === 1;
    }
}
