<?php

namespace App\Services\Institution;

final class RegularEmploymentIncomeHistoricalService
{
    private const SNAPSHOT_FIELDS = [
        'national_pension_basis_snapshot',
        'health_insurance_basis_snapshot',
        'employment_insurance_basis_snapshot',
    ];

    public function normalizeSnapshots(array $item): array
    {
        $snapshots = [];
        foreach (self::SNAPSHOT_FIELDS as $field) {
            $value = $item[$field] ?? null;
            if ($value === null || $value === '') {
                $snapshots[$field] = null;
                continue;
            }
            $amount = $this->amount($value, $field);
            $snapshots[$field] = $amount;
        }
        return $snapshots;
    }

    public function normalizeLines(array $lines): array
    {
        if ($lines === []) {
            throw new \InvalidArgumentException('과거 실제 지급·공제 항목을 입력해 주세요.');
        }
        $normalized = [];
        foreach (array_values($lines) as $index => $line) {
            $calculated = $this->nullableAmount($line['calculated_amount'] ?? null, '자동계산값');
            $adjustment = $this->nullableSignedAmount($line['adjustment_amount'] ?? null);
            $final = $this->nullableAmount($line['final_amount'] ?? null, '최종금액');
            $reason = trim((string) ($line['adjustment_reason'] ?? ''));
            if ($final === null) {
                throw new \InvalidArgumentException('과거 급여의 모든 지급·공제 최종금액을 확정해 주세요.');
            }
            if ($calculated === null && $adjustment !== null) {
                throw new \InvalidArgumentException('법정계산이 불가능한 항목에는 조정금액을 입력할 수 없습니다.');
            }
            if ($calculated !== null) {
                $expectedAdjustment = round($final - $calculated, 2);
                if ($adjustment === null) $adjustment = $expectedAdjustment;
                if (abs(($calculated + $adjustment) - $final) >= 0.01) {
                    throw new \InvalidArgumentException('자동계산값, 조정금액, 최종금액의 합계를 확인해 주세요.');
                }
                if (abs($adjustment) >= 0.01 && $reason === '') {
                    throw new \InvalidArgumentException('0원이 아닌 조정금액에는 조정사유를 입력해 주세요.');
                }
            }
            $status = $calculated === null ? 'NOT_VERIFIABLE' : (abs((float) $adjustment) < 0.01 ? 'CALCULATED' : 'WARNING');
            $type = strtoupper(trim((string) ($line['item_type_code'] ?? '')));
            $code = strtoupper(trim((string) ($line['item_code'] ?? '')));
            $name = trim((string) ($line['item_name_snapshot'] ?? ''));
            if (!in_array($type, ['PAY', 'DEDUCTION', 'EMPLOYER_BURDEN'], true) || $code === '' || $name === '') {
                throw new \InvalidArgumentException('급여 항목 구분·코드·항목명을 확인해 주세요.');
            }
            $payEffect = $type === 'PAY' ? strtoupper(trim((string) ($line['pay_effect_code'] ?? ''))) : null;
            if ($type === 'PAY' && !in_array($payEffect, ['CONTRACT_BASE', 'INCREASE', 'DECREASE'], true)) {
                throw new \InvalidArgumentException('과거 지급항목의 계약기준·증액·감액 구분을 확인해 주세요.');
            }
            $businessSource = strtoupper(trim((string) ($line['business_source_code'] ?? '')));
            $businessReason = trim((string) ($line['business_reason'] ?? '')) ?: null;
            if ($type === 'PAY' && in_array($payEffect, ['INCREASE', 'DECREASE'], true) && ($businessSource === '' || $businessReason === null)) {
                throw new \InvalidArgumentException('과거 증액·감액의 업무원천과 사유를 입력해 주세요.');
            }
            $normalized[] = [
                'id' => trim((string) ($line['id'] ?? '')),
                'sort_no' => $index + 1,
                'item_type_code' => $type,
                'pay_effect_code' => $payEffect,
                'item_code' => $code,
                'item_name_snapshot' => $name,
                'taxable_flag' => array_key_exists('taxable_flag', $line) ? $line['taxable_flag'] : null,
                'calculated_amount' => $calculated,
                'adjustment_amount' => $adjustment,
                'final_amount' => $final,
                'adjustment_reason' => $reason !== '' ? $reason : null,
                'calculation_source_code' => 'HISTORICAL_IMPORT',
                'business_source_code' => $businessSource ?: null,
                'source_reference_id' => trim((string) ($line['source_reference_id'] ?? '')) ?: null,
                'source_key' => trim((string) ($line['source_key'] ?? '')) ?: null,
                'business_reason' => $businessReason,
                'processed_at' => $line['processed_at'] ?? null,
                'processed_by' => $line['processed_by'] ?? null,
                'verification_status_code' => $status,
            ];
        }
        return $normalized;
    }

    public function totals(array $lines): array
    {
        $gross = 0.0;
        $deduction = 0.0;
        $burden = 0.0;
        $payCount = 0;
        $deductionCount = 0;
        foreach ($lines as $line) {
            $amount = (float) $line['final_amount'];
            if ($line['item_type_code'] === 'PAY') { $gross += ($line['pay_effect_code'] === 'DECREASE' ? -$amount : $amount); $payCount++; }
            elseif ($line['item_type_code'] === 'DEDUCTION') { $deduction += (new RegularEmploymentIncomeDeductionLineService())->signedAmount($line); $deductionCount++; }
            elseif ($line['item_type_code'] === 'EMPLOYER_BURDEN') $burden += $amount;
            else throw new \InvalidArgumentException('급여 항목 구분을 확인해 주세요.');
        }
        if ($payCount === 0 || $deductionCount === 0) throw new \InvalidArgumentException('과거 급여의 지급항목과 공제항목을 모두 입력해 주세요.');
        $net = round($gross - $deduction, 2);
        if ($net < 0) throw new \InvalidArgumentException('실지급액은 0원보다 작을 수 없습니다.');
        return [
            'gross_amount' => round($gross, 2),
            'deduction_amount' => round($deduction, 2),
            'net_payment_amount' => $net,
            'employer_burden_amount' => round($burden, 2),
        ];
    }

    public function verificationStatus(array $lines): string
    {
        $statuses = array_column($lines, 'verification_status_code');
        if (in_array('NOT_VERIFIABLE', $statuses, true)) return 'NOT_VERIFIABLE';
        if (in_array('WARNING', $statuses, true)) return 'WARNING';
        return 'CALCULATED';
    }

    private function amount(mixed $value, string $label): float
    {
        $amount = round((float) str_replace(',', '', (string) $value), 2);
        if ($amount < 0) throw new \InvalidArgumentException($label . '은 0원 이상이어야 합니다.');
        return $amount;
    }

    private function nullableAmount(mixed $value, string $label): ?float
    {
        return $value === null || $value === '' ? null : $this->amount($value, $label);
    }

    private function nullableSignedAmount(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : round((float) str_replace(',', '', (string) $value), 2);
    }
}
