<?php

declare(strict_types=1);

namespace App\Services\Institution;

final class DailyEmploymentIncomeAccountingLinkPolicyService
{
    public function validate(array $row): array
    {
        $role = strtoupper(trim((string) ($row['artifact_role'] ?? '')));
        $required = [
            'EVIDENCE' => [],
            'WORKER_PAYMENT' => ['transaction_id'],
        ];
        if (!isset($required[$role])) {
            throw new \InvalidArgumentException('지원하지 않는 일용근로소득 생성 역할입니다.');
        }
        foreach (['closure_id', 'daily_employment_income_id', 'daily_employment_income_group_id',
            'daily_employment_income_item_id', 'worker_client_id', 'business_key_hash', 'payload_hash', 'evidence_id'] as $key) {
            $this->requireValue($row, $key);
        }
        if (preg_match('/^[0-9a-f]{64}$/', (string) $row['business_key_hash']) !== 1) {
            throw new \InvalidArgumentException('생성 업무키 Hash 형식이 올바르지 않습니다.');
        }
        foreach ($required[$role] as $key) {
            $this->requireValue($row, $key);
        }

        $forbidden = $role === 'EVIDENCE' ? ['transaction_id'] : [];
        foreach ($forbidden as $key) {
            if (trim((string) ($row[$key] ?? '')) !== '') {
                throw new \InvalidArgumentException('생성 역할과 대상 연결 조합이 올바르지 않습니다.');
            }
        }

        return $row + ['artifact_role' => $role];
    }

    private function requireValue(array $row, string $key): void
    {
        if (trim((string) ($row[$key] ?? '')) === '') {
            throw new \InvalidArgumentException('일용근로소득 생성 연결 필수값이 누락되었습니다.');
        }
    }
}
