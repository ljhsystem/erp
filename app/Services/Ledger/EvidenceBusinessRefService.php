<?php

namespace App\Services\Ledger;

class EvidenceBusinessRefService
{
    public function __construct(private array $callbacks = [])
    {
    }

    public function businessRefIdForStorage(string $refType, array $payload): ?string
    {
        $refType = $this->call('normalizeVoucherRefType', $refType);
        foreach ($this->businessRefCandidateValues($refType, $payload, true) as $value) {
            $resolved = $refType === 'ACCOUNT'
                ? $this->call('resolveBankAccountId', $value)
                : $this->call('resolveVoucherRefId', $refType, $value);
            if ($resolved !== null && $resolved !== '') {
                return $resolved;
            }
        }

        return null;
    }

    public function businessRefNameForStorage(string $refType, array $payload): ?string
    {
        $refType = $this->call('normalizeVoucherRefType', $refType);
        $uuidCandidate = null;
        if ($refType === 'ACCOUNT') {
            $accountId = (string) ($this->businessRefIdForStorage('ACCOUNT', $payload) ?? '');
            if ($accountId !== '' && $this->call('isUuid', $accountId)) {
                $resolvedName = $this->call('businessRefNameById', 'ACCOUNT', $accountId);
                if ($resolvedName !== null && $resolvedName !== '') {
                    return $resolvedName;
                }
            }
        }
        if ($refType === 'CLIENT') {
            $clientId = (string) ($this->businessRefIdForStorage('CLIENT', $payload) ?? '');
            if ($clientId !== '' && $this->call('isUuid', $clientId)) {
                $resolvedName = $this->call('businessRefNameById', 'CLIENT', $clientId);
                if ($resolvedName !== null && $resolvedName !== '') {
                    return $resolvedName;
                }
            }
            $clientName = (string) $this->call('clientNameFromImportParty', $payload);
            if ($this->isEmptySelectionLabel($clientName)) {
                $clientName = '';
            }
            if ($clientName !== '' && !$this->call('isUuid', $clientName)) {
                return $clientName;
            }
            if ($this->call('isUuid', $clientName)) {
                $uuidCandidate = $clientName;
            }
        }

        foreach ($this->businessRefCandidateValues($refType, $payload, false) as $value) {
            $value = trim((string) $value);
            if ($this->isEmptySelectionLabel($value)) {
                continue;
            }
            if ($value !== '' && !$this->call('isUuid', $value)) {
                return $value;
            }
            if ($value !== '' && $this->call('isUuid', $value) && $uuidCandidate === null) {
                $uuidCandidate = $value;
            }
        }

        if ($uuidCandidate !== null) {
            $resolvedName = $this->call('businessRefNameById', $refType, $uuidCandidate);
            if ($resolvedName !== null && $resolvedName !== '') {
                return $resolvedName;
            }
        }

        return null;
    }

    public function businessRefCandidateValues(string $refType, array $payload, bool $includeIds): array
    {
        $keys = match ($this->call('normalizeVoucherRefType', $refType)) {
            'CLIENT' => $includeIds
                ? ['client_id', 'client_name', 'client_name_ko', 'client_name_en', 'company_name_ko', 'company_name_en', 'client_company_name', 'client_company_name_ko', 'client_company_name_en', 'counterparty_name', 'merchant_company_name', 'supplier_company_name', 'customer_company_name', 'client_business_number', 'supplier_business_number', 'customer_business_number']
                : ['client_name', 'client_name_ko', 'client_name_en', 'client_id'],
            'PROJECT' => $includeIds
                ? ['project_id', 'project_name', 'project_code']
                : ['project_name', 'project_code', 'project_id'],
            'EMPLOYEE' => $includeIds
                ? ['employee_id', 'employee_name', 'user_name', 'user_id']
                : ['employee_name', 'user_name', 'employee_id'],
            'ACCOUNT' => $includeIds
                ? ['bank_account_id', 'account_number', 'payment_account_number', 'bank_account_name', 'bank_account', 'account_name', 'payment_account_name', 'bank_name', 'payment_bank_name']
                : ['bank_account_name', 'bank_account', 'account_name', 'payment_account_name', 'bank_account_id', 'account_number', 'payment_account_number'],
            'CARD' => $includeIds
                ? ['card_id', 'card_name', 'card_number', 'card_company_name']
                : ['card_name', 'card_number', 'card_company_name', 'card_id'],
            'TEAM' => $includeIds
                ? ['team_id', 'team_name']
                : ['team_name', 'team_id'],
            default => [],
        };

        $values = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }
            $value = trim((string) $payload[$key]);
            if ($value !== '' && !$this->isEmptySelectionLabel($value)) {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    public function isEmptySelectionLabel(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return true;
        }

        $labels = [
            '__none__',
            '__CODE_NONE__',
            '_none_',
            '__none',
            'none__',
            '--none--',
            '선택',
            '선택하세요',
            '선택해 주세요',
            '선택해주세요',
            '미선택',
            '없음',
            '거래처 선택',
            '프로젝트 선택',
            '직원 선택',
            '계좌 선택',
            '카드 선택',
            '팀 선택',
            '거래처를 선택하세요',
            '프로젝트를 선택하세요',
            '직원을 선택하세요',
            '계좌를 선택하세요',
            '카드를 선택하세요',
            '팀을 선택하세요',
        ];
        if (in_array($value, $labels, true)) {
            return true;
        }

        return str_ends_with($value, ' 선택') || str_ends_with($value, ' 선택하세요');
    }

    public function normalizeBusinessRefPayload(array $payload): array
    {
        foreach ($this->businessRefPayloadKeyMap() as $refType => $keys) {
            $idKey = $keys['id'];
            $nameKey = $keys['name'];
            $id = trim((string) ($payload[$idKey] ?? ''));
            $name = trim((string) $this->call('payloadScalarForStorage', $payload[$nameKey] ?? null));
            if ($this->isEmptySelectionLabel($id)) {
                $id = '';
            }
            if ($this->isEmptySelectionLabel($name)) {
                $name = '';
            }

            if ($id === '' && $this->call('isUuid', $name)) {
                $id = $name;
            }
            if ($id === '') {
                $id = (string) ($this->businessRefIdForStorage($refType, $payload) ?? '');
            }

            $displayName = '';
            if ($name !== '' && !$this->call('isUuid', $name)) {
                $displayName = $name;
            } elseif ($id !== '') {
                $displayName = (string) ($this->call('businessRefNameById', $refType, $id) ?? '');
            }
            if ($refType === 'ACCOUNT' && $id !== '') {
                $resolvedName = (string) ($this->call('businessRefNameById', $refType, $id) ?? '');
                if ($resolvedName !== '') {
                    $displayName = $resolvedName;
                }
            }
            if ($displayName === '') {
                $displayName = (string) ($this->businessRefNameForStorage($refType, $payload) ?? '');
            }

            if ($id !== '' && $this->call('isUuid', $id)) {
                $payload[$idKey] = $id;
            }
            if ($displayName !== '' && !$this->call('isUuid', $displayName)) {
                $payload[$nameKey] = $displayName;
            }
        }

        return $payload;
    }

    public function businessRefPayloadKeyMap(): array
    {
        return [
            'CLIENT' => ['id' => 'client_id', 'name' => 'client_name'],
            'PROJECT' => ['id' => 'project_id', 'name' => 'project_name'],
            'EMPLOYEE' => ['id' => 'employee_id', 'name' => 'employee_name'],
            'ACCOUNT' => ['id' => 'bank_account_id', 'name' => 'bank_account_name'],
            'CARD' => ['id' => 'card_id', 'name' => 'card_name'],
            'TEAM' => ['id' => 'team_id', 'name' => 'team_name'],
        ];
    }

    private function call(string $name, mixed ...$args): mixed
    {
        if (!isset($this->callbacks[$name])) {
            throw new \RuntimeException('Missing callback: ' . $name);
        }

        return ($this->callbacks[$name])(...$args);
    }
}
