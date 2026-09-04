<?php

namespace App\Services\Institution;

final class RegularEmploymentIncomeDeductionLineService
{
    public const ADDITIONAL_COLLECTION = 'ADDITIONAL_COLLECTION';
    public const REFUND = 'REFUND';

    public function normalizeSettlement(array $line, string $actor): array
    {
        $parent = strtoupper(trim((string) ($line['settlement_parent_code'] ?? $this->parentCode($line) ?? '')));
        $type = strtoupper(trim((string) ($line['settlement_type_code'] ?? $this->settlementType($line) ?? '')));
        $period = trim((string) ($line['settlement_period'] ?? $this->period($line) ?? ''));
        $amount = round((float) str_replace(',', '', (string) ($line['final_amount'] ?? '')), 2);
        $reason = trim((string) ($line['business_reason'] ?? ''));
        if (!in_array($parent, $this->supportedParents(), true)) {
            throw new \InvalidArgumentException('정산할 공제종류를 확인해 주세요.');
        }
        if (!in_array($type, [self::ADDITIONAL_COLLECTION, self::REFUND], true)) {
            throw new \InvalidArgumentException('정산유형을 확인해 주세요.');
        }
        if (!preg_match('/^\d{4}(?:-\d{2})?(?:~\d{4}(?:-\d{2})?)?$/', $period)) {
            throw new \InvalidArgumentException('정산 대상기간 또는 귀속연도를 확인해 주세요.');
        }
        if ($amount <= 0) throw new \InvalidArgumentException('정산 금액은 0원보다 커야 합니다.');
        if ($reason === '') throw new \InvalidArgumentException('정산 사유를 입력해 주세요.');
        $token = substr(hash('sha256', implode('|', [$parent, $type, $period, $reason, (string) ($line['source_reference_id'] ?? '')])), 0, 12);
        $sourceKey = implode('|', ['SETTLEMENT', $parent, $type, $period, $token]);
        return [
            'item_type_code' => 'DEDUCTION',
            'pay_effect_code' => null,
            'item_code' => 'SET_' . $parent . '_' . ($type === self::REFUND ? 'R' : 'A') . '_' . $token,
            'item_name_snapshot' => $this->parentName($parent) . ($type === self::REFUND ? ' 정산 환급' : ' 정산 추가징수'),
            'taxable_flag' => null,
            'calculated_amount' => $amount,
            'adjustment_amount' => 0.0,
            'final_amount' => $amount,
            'adjustment_reason' => null,
            'calculation_source_code' => 'MANUAL',
            'calculation_status_code' => 'CALCULATED',
            'calculation_message' => null,
            'application_status_code' => 'APPLICABLE',
            'business_source_code' => strtoupper(trim((string) ($line['business_source_code'] ?? 'MANUAL'))) ?: 'MANUAL',
            'source_reference_id' => trim((string) ($line['source_reference_id'] ?? '')) ?: null,
            'source_key' => $sourceKey,
            'business_reason' => $reason,
            'processed_at' => trim((string) ($line['processed_at'] ?? '')) ?: date('Y-m-d H:i:s'),
            'processed_by' => trim((string) ($line['processed_by'] ?? '')) ?: $actor,
            'settlement_parent_code' => $parent,
            'settlement_type_code' => $type,
            'settlement_period' => $period,
        ];
    }

    public function isSettlement(array $line): bool
    {
        return str_starts_with((string) ($line['source_key'] ?? ''), 'SETTLEMENT|');
    }

    public function parentCode(array $line): ?string
    {
        return $this->meta($line)[1] ?? null;
    }

    public function settlementType(array $line): ?string
    {
        return $this->meta($line)[2] ?? null;
    }

    public function period(array $line): ?string
    {
        return $this->meta($line)[3] ?? null;
    }

    public function signedAmount(array $line): float
    {
        $amount = round((float) ($line['final_amount'] ?? 0), 2);
        return $this->isSettlement($line) && $this->settlementType($line) === self::REFUND ? -$amount : $amount;
    }

    public function projectionType(array $line): string
    {
        if (!$this->isSettlement($line)) return (string) $line['item_code'] . '_CURRENT';
        return (string) $this->parentCode($line) . ($this->settlementType($line) === self::REFUND ? '_REFUND' : '_SETTLEMENT');
    }

    public function supportedParents(): array
    {
        return ['EMPLOYMENT_INCOME_TAX','LOCAL_INCOME_TAX','EMPLOYMENT_INSURANCE','HEALTH_INSURANCE','LONG_TERM_CARE','NATIONAL_PENSION'];
    }

    private function meta(array $line): array
    {
        return explode('|', (string) ($line['source_key'] ?? ''));
    }

    private function parentName(string $code): string
    {
        return ['EMPLOYMENT_INCOME_TAX'=>'근로소득세','LOCAL_INCOME_TAX'=>'지방소득세','EMPLOYMENT_INSURANCE'=>'고용보험','HEALTH_INSURANCE'=>'건강보험','LONG_TERM_CARE'=>'장기요양보험','NATIONAL_PENSION'=>'국민연금'][$code] ?? $code;
    }
}
