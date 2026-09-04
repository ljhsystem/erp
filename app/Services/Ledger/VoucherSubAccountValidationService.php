<?php

namespace App\Services\Ledger;

use App\Models\Ledger\SubAccountPolicyModel;
use PDO;

class VoucherSubAccountValidationService
{
    private AccountContextRefPolicyService $contextPolicies;
    private SubAccountPolicyModel $accountPolicies;

    public function __construct(PDO $pdo)
    {
        $this->contextPolicies = new AccountContextRefPolicyService($pdo);
        $this->accountPolicies = new SubAccountPolicyModel($pdo);
    }

    public function validate(array $lines, array $voucherContext): void
    {
        $grouped = $this->accountPolicies->getGroupedByAccountIds(array_column($lines, 'account_id'));
        foreach ($lines as $line) {
            $accountId = trim((string) ($line['account_id'] ?? ''));
            $role = strtoupper(trim((string) ($line['accounting_role_code'] ?? '')));
            $required = [];
            foreach ($grouped[$accountId] ?? [] as $policy) {
                if ((int) ($policy['is_required'] ?? 0) === 1) $required[] = strtoupper((string) $policy['ref_target']);
            }
            if ($role !== '') {
                $required = array_merge($required, $this->contextPolicies->requiredRefTargets([
                    'company_id' => $voucherContext['company_id'] ?? '', 'account_id' => $accountId,
                    'operation_type' => $voucherContext['operation_type'] ?? '', 'accounting_role_code' => $role,
                    'base_date' => $voucherContext['base_date'] ?? '',
                ]));
            }
            $selected = [];
            foreach ((array) ($line['refs'] ?? []) as $ref) if (trim((string) ($ref['ref_id'] ?? '')) !== '') $selected[strtoupper((string) ($ref['ref_target'] ?? ''))] = true;
            $missing = array_values(array_filter(array_unique($required), static fn(string $target): bool => empty($selected[$target])));
            if ($missing !== []) throw new \InvalidArgumentException('필수 보조계정이 선택되지 않았습니다. (대상: ' . implode(', ', $missing) . ')');
        }
    }
}
