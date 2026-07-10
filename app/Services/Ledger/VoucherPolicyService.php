<?php

namespace App\Services\Ledger;

use PDO;

class VoucherPolicyService
{
    public function __construct(private PDO $pdo, private array $callbacks = []) {}

    public function applyEvidenceRefsToVoucherLines(array $lines, array $evidence): array
    {
        foreach ($lines as &$line) {
            $accountId = $this->resolveLedgerAccountId((string) ($line['account_id'] ?? ''));
            if ($accountId === null) {
                continue;
            }

            $policies = $this->voucherRefPoliciesForAccount($accountId);
            if ($policies === []) {
                continue;
            }

            $refs = is_array($line['refs'] ?? null) ? $line['refs'] : [];
            $existingTypes = [];
            foreach ($refs as $ref) {
                $type = $this->normalizeVoucherRefType((string) ($ref['ref_target'] ?? ''));
                if ($type !== '') {
                    $existingTypes[$type] = true;
                }
            }

            foreach (array_keys($policies) as $refType) {
                $refType = $this->normalizeVoucherRefType($refType);
                if ($refType === '' || isset($existingTypes[$refType])) {
                    continue;
                }

                $refId = $this->evidenceRefIdForType($refType, $evidence);
                if ($refId === null || $refId === '') {
                    continue;
                }

                $refs[] = [
                    'ref_target' => $refType,
                    'ref_id' => $refId,
                    'is_primary' => 0,
                ];
                $existingTypes[$refType] = true;
            }

            $line['refs'] = $refs;
        }
        unset($line);

        return $lines;
    }

    public function missingRequiredEvidenceRefsMessage(array $lines, array $evidence): ?string
    {
        $missing = [];
        foreach ($lines as $index => $line) {
            $accountId = $this->resolveLedgerAccountId((string) ($line['account_id'] ?? ''));
            if ($accountId === null) {
                continue;
            }

            $policies = $this->voucherRefPoliciesForAccount($accountId);
            foreach ($policies as $refType => $isRequired) {
                if (!$isRequired) {
                    continue;
                }
                if ($this->lineHasRefType($line, $refType)) {
                    continue;
                }
                if ($this->evidenceRefIdForType($refType, $evidence) !== null) {
                    continue;
                }
                $missing[] = ($index + 1) . '번째 라인: ' . $this->voucherRefTypeLabel($refType);
            }
        }

        return $missing === [] ? null : '증빙에서 필수 참조값을 찾을 수 없습니다. ' . implode(', ', array_values(array_unique($missing)));
    }

    public function lineHasRefType(array $line, string $refType): bool
    {
        $target = $this->normalizeVoucherRefType($refType);
        foreach ((array) ($line['refs'] ?? []) as $ref) {
            if (
                $this->normalizeVoucherRefType((string) ($ref['ref_target'] ?? '')) === $target
                && trim((string) ($ref['ref_id'] ?? '')) !== ''
            ) {
                return true;
            }
        }

        return false;
    }

    public function evidenceRefIdForType(string $refType, array $evidence): ?string
    {
        return $this->call('businessRefIdForStorage', $this->normalizeVoucherRefType($refType), $evidence);
    }

    public function voucherRefPoliciesForAccount(string $accountId): array
    {
        if ($accountId === '') {
            return [];
        }

        $policies = [];

        if ($this->call('tableExists', 'ledger_accounts_sub')) {
            $hasRefType = $this->call('tableColumnExists', 'ledger_accounts_sub', 'ref_target');
            $hasSubCode = $this->call('tableColumnExists', 'ledger_accounts_sub', 'sub_code');
            if ($hasRefType || $hasSubCode) {
                $select = [
                    $hasRefType ? 'ref_target' : "'' AS ref_target",
                    $hasSubCode ? 'sub_code' : "'' AS sub_code",
                    $this->call('tableColumnExists', 'ledger_accounts_sub', 'is_required') ? 'is_required' : '0 AS is_required',
                ];
                $deleted = $this->call('tableColumnExists', 'ledger_accounts_sub', 'deleted_at') ? ' AND deleted_at IS NULL' : '';
                $stmt = $this->pdo->prepare("
                    SELECT " . implode(', ', $select) . "
                    FROM ledger_accounts_sub
                    WHERE account_id = :account_id
                      {$deleted}
                ");
                $stmt->execute([':account_id' => $accountId]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $type = $this->policyRefTypeFromRow($row);
                    if ($type === '') {
                        continue;
                    }
                    $policies[$type] = !empty($policies[$type]) || (int) ($row['is_required'] ?? 0) === 1;
                }
            }
        }

        if ($this->call('tableExists', 'ledger_account_sub_policies')) {
            $deleted = $this->call('tableColumnExists', 'ledger_account_sub_policies', 'deleted_at') ? ' AND deleted_at IS NULL' : '';
            $stmt = $this->pdo->prepare("
                SELECT sub_account_type, custom_group_code, is_required
                FROM ledger_account_sub_policies
                WHERE account_id = :account_id
                  {$deleted}
            ");
            $stmt->execute([':account_id' => $accountId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $type = $this->policyRefTypeFromSubPolicy($row);
                if ($type === '') {
                    continue;
                }
                $policies[$type] = !empty($policies[$type]) || (int) ($row['is_required'] ?? 0) === 1;
            }
        }

        return $policies;
    }

    public function policyRefTypeFromRow(array $row): string
    {
        $refType = $this->normalizeVoucherRefType((string) ($row['ref_target'] ?? ''));
        $subCode = $this->normalizeVoucherRefType((string) ($row['sub_code'] ?? ''));
        if ($refType === 'REF_TARGET') {
            return $subCode;
        }

        return $refType !== '' ? $refType : $subCode;
    }

    public function policyRefTypeFromSubPolicy(array $row): string
    {
        $type = strtolower(trim((string) ($row['sub_account_type'] ?? '')));
        return match ($type) {
            'partner', 'client', 'customer', 'vendor', 'counterparty' => 'CLIENT',
            'project' => 'PROJECT',
            'employee', 'staff', 'user' => 'EMPLOYEE',
            'account', 'bank', 'bank_account' => 'ACCOUNT',
            'card' => 'CARD',
            'custom' => $this->normalizeVoucherRefType((string) ($row['custom_group_code'] ?? '')),
            default => $this->normalizeVoucherRefType($type),
        };
    }

    public function resolveLedgerAccountId(string $accountValue): ?string
    {
        $accountValue = $this->normalizeAccountInput($accountValue);
        if ($accountValue === '' || !$this->call('tableExists', 'ledger_accounts')) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT id
            FROM ledger_accounts
            WHERE deleted_at IS NULL
              AND (id = :account_id_value OR account_code = :account_code_value)
            LIMIT 1
        ");
        $stmt->execute([
            ':account_id_value' => $accountValue,
            ':account_code_value' => $accountValue,
        ]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (string) $id : null;
    }

    public function voucherRefTypeLabel(string $refType): string
    {
        return match ($this->normalizeVoucherRefType($refType)) {
            'CLIENT' => '거래처',
            'PROJECT' => '프로젝트',
            'EMPLOYEE' => '직원',
            'ACCOUNT' => '계좌',
            'CARD' => '카드',
            default => $refType,
        };
    }

    public function normalizeVoucherRefType(string $value): string
    {
        $rawValue = trim($value);
        $knownKorean = [
            '거래처' => 'CLIENT',
            '프로젝트' => 'PROJECT',
            '계좌' => 'ACCOUNT',
            '은행계좌' => 'ACCOUNT',
            '카드' => 'CARD',
            '직원' => 'EMPLOYEE',
            '사원' => 'EMPLOYEE',
        ];
        if (isset($knownKorean[$rawValue])) {
            return $knownKorean[$rawValue];
        }

        $value = strtoupper($rawValue);
        return match ($value) {
            '거래처', 'CLIENT', 'CUSTOMER', 'VENDOR', 'COUNTERPARTY' => 'CLIENT',
            '프로젝트', 'PROJECT' => 'PROJECT',
            '계좌', '은행계좌', 'ACCOUNT', 'BANK', 'BANK_ACCOUNT' => 'ACCOUNT',
            '카드', 'CARD' => 'CARD',
            '직원', '사원', 'EMPLOYEE', 'USER' => 'EMPLOYEE',
            default => $value,
        };
    }

    public function normalizeAccountInput(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^([0-9A-Za-z._-]+)\s+.+$/u', $value, $matches) === 1) {
            return $matches[1];
        }

        return $value;
    }

    private function call(string $name, mixed ...$args): mixed
    {
        $callback = $this->callbacks[$name] ?? null;
        if (!is_callable($callback)) {
            throw new \RuntimeException('Missing VoucherPolicyService callback: ' . $name);
        }

        return $callback(...$args);
    }
}
