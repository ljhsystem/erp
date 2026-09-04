<?php

namespace App\Services\Institution;

final class RegularEmploymentIncomePayLineService
{
    private const OTHER_COMPONENT_CODE = 'OTHER_PAY';

    public function __construct(private readonly ?PayComponentService $payComponents = null)
    {
    }

    public function contractLine(array $line, string $contractId): array
    {
        return $line + [
            'pay_effect_code' => 'CONTRACT_BASE',
            'business_source_code' => 'EMPLOYMENT_CONTRACT',
            'source_reference_id' => $contractId,
            'source_key' => null,
            'business_reason' => null,
            'processed_at' => null,
            'processed_by' => null,
        ];
    }

    public function normalizeManualLine(array $line, string $actor, string $effectiveDate, ?string $processedAt = null): array
    {
        $effect = strtoupper(trim((string) ($line['pay_effect_code'] ?? '')));
        if (!in_array($effect, ['INCREASE', 'DECREASE'], true)) {
            throw new \InvalidArgumentException('수동 지급항목은 증액 또는 감액으로 구분해 주세요.');
        }
        $amount = round((float) str_replace(',', '', (string) ($line['final_amount'] ?? '')), 2);
        if ($amount <= 0) throw new \InvalidArgumentException('증액·감액 금액은 0원보다 커야 합니다.');
        $reason = trim((string) ($line['business_reason'] ?? ''));
        if (!$this->payComponents) throw new \RuntimeException('급여항목 SSOT 조회 책임이 연결되지 않았습니다.');
        $componentId = trim((string) ($line['source_reference_id'] ?? ''));
        if ($componentId === '') throw new \InvalidArgumentException('증액·감액 지급항목을 선택해 주세요.');
        $component = $this->payComponents->requireActiveForDate($componentId, $effectiveDate);
        $token = $this->sourceToken((string) ($line['source_key'] ?? ''));
        $componentCode = strtoupper((string) $component['component_code']);
        if ($componentCode === self::OTHER_COMPONENT_CODE && $reason === '') {
            throw new \InvalidArgumentException('기타 급여항목은 사유를 입력해 주세요.');
        }
        $sourceKey = implode('|', ['PAY_COMPONENT', $componentCode, $effect, $token]);
        return [
            'item_type_code' => 'PAY',
            'pay_effect_code' => $effect,
            'item_code' => 'ADJ_' . str_replace('-', '', $token),
            'item_name_snapshot' => (string) $component['component_name'],
            'taxable_flag' => $this->payComponents->taxableFlag($component),
            'calculated_amount' => $amount,
            'adjustment_amount' => 0.0,
            'final_amount' => $amount,
            'adjustment_reason' => null,
            'calculation_source_code' => strtoupper((string) ($line['calculation_source_code'] ?? 'MANUAL')) === 'HISTORICAL_IMPORT' ? 'HISTORICAL_IMPORT' : 'MANUAL',
            'business_source_code' => strtoupper((string) ($line['business_source_code'] ?? 'MANUAL')),
            'source_reference_id' => (string) $component['id'],
            'source_key' => $sourceKey,
            'business_reason' => $reason !== '' ? $reason : null,
            'processed_at' => $processedAt ?? date('Y-m-d H:i:s'),
            'processed_by' => $actor,
        ];
    }

    public function totals(array $lines): array
    {
        $totals = ['contract_amount'=>0.0,'increase_amount'=>0.0,'decrease_amount'=>0.0,'gross_amount'=>0.0,'taxable_amount'=>0.0,'non_taxable_amount'=>0.0,'deduction_amount'=>0.0,'employer_burden_amount'=>0.0,'net_payment_amount'=>0.0];
        foreach ($lines as $line) {
            $amount = round((float) ($line['final_amount'] ?? 0), 2);
            if ($amount < 0) throw new \InvalidArgumentException('급여 Line 금액은 음수로 저장할 수 없습니다.');
            $type = (string) ($line['item_type_code'] ?? '');
            if ($type === 'DEDUCTION') { $totals['deduction_amount'] += (new RegularEmploymentIncomeDeductionLineService())->signedAmount($line); continue; }
            if ($type === 'EMPLOYER_BURDEN') { $totals['employer_burden_amount'] += $amount; continue; }
            if ($type !== 'PAY') throw new \InvalidArgumentException('급여 항목 구분을 확인해 주세요.');
            $effect = (string) ($line['pay_effect_code'] ?? '');
            $sign = $effect === 'DECREASE' ? -1 : 1;
            if ($effect === 'CONTRACT_BASE') $totals['contract_amount'] += $amount;
            elseif ($effect === 'INCREASE') $totals['increase_amount'] += $amount;
            elseif ($effect === 'DECREASE') $totals['decrease_amount'] += $amount;
            else throw new \InvalidArgumentException('PAY 효과 구분을 확인해 주세요.');
            $totals['gross_amount'] += $sign * $amount;
            $taxKey = !empty($line['taxable_flag']) ? 'taxable_amount' : 'non_taxable_amount';
            $totals[$taxKey] += $sign * $amount;
        }
        foreach (['gross_amount','taxable_amount','non_taxable_amount'] as $key) {
            if ($totals[$key] < 0) throw new \InvalidArgumentException('증액·감액 후 지급금액은 0원보다 작을 수 없습니다.');
        }
        $totals['net_payment_amount'] = $totals['gross_amount'] - $totals['deduction_amount'];
        if ($totals['net_payment_amount'] < 0) throw new \InvalidArgumentException('실지급액은 0원보다 작을 수 없습니다.');
        return array_map(static fn(float $amount): float => round($amount, 2), $totals);
    }

    public function finalPayComposition(array $lines): array
    {
        $composition = [];
        $decreases = [];
        $positiveCodes = [];
        foreach ($lines as $line) {
            if (($line['item_type_code'] ?? '') !== 'PAY') continue;
            if (($line['pay_effect_code'] ?? '') === 'DECREASE') { $decreases[] = $line; continue; }
            $amount = round((float) ($line['final_amount'] ?? 0), 2);
            if ($amount <= 0) continue;
            $componentCode = $this->componentCode($line);
            if (($line['pay_effect_code'] ?? '') === 'INCREASE'
                && $componentCode !== self::OTHER_COMPONENT_CODE
                && isset($positiveCodes[$componentCode])) {
                throw new \InvalidArgumentException('이미 존재하는 지급항목은 증액으로 중복 추가할 수 없습니다.');
            }
            $positiveCodes[$componentCode] = true;
            $composition[] = ['line'=>$line,'amount'=>$amount,'component_code'=>$componentCode];
        }
        foreach ($decreases as $decrease) {
            $remaining = round((float) $decrease['final_amount'], 2);
            $componentCode = $this->componentCode($decrease);
            for ($index = count($composition) - 1; $index >= 0 && $remaining > 0; $index--) {
                $matches = $componentCode === self::OTHER_COMPONENT_CODE
                    ? !empty($composition[$index]['line']['taxable_flag'])
                    : $composition[$index]['component_code'] === $componentCode;
                if (!$matches) continue;
                $applied = min($remaining, $composition[$index]['amount']);
                $composition[$index]['amount'] = round($composition[$index]['amount'] - $applied, 2);
                $remaining = round($remaining - $applied, 2);
            }
            if ($remaining > 0) throw new \RuntimeException('선택한 지급항목의 감액 가능 금액이 부족합니다.');
        }
        return array_values(array_filter($composition, static fn(array $row): bool => $row['amount'] > 0));
    }

    private function sourceToken(string $sourceKey): string
    {
        $parts = explode('|', trim($sourceKey));
        $token = (string) end($parts);
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $token)) {
            throw new \InvalidArgumentException('증액·감액 요청 식별값을 확인해 주세요.');
        }
        return strtolower($token);
    }

    private function componentCode(array $line): string
    {
        if (in_array((string) ($line['pay_effect_code'] ?? ''), ['', 'CONTRACT_BASE'], true)) return strtoupper((string) ($line['item_code'] ?? ''));
        $parts = explode('|', (string) ($line['source_key'] ?? ''));
        if (($parts[0] ?? '') !== 'PAY_COMPONENT' || trim((string) ($parts[1] ?? '')) === '') {
            throw new \RuntimeException('증액·감액 지급항목의 SSOT 참조를 확인할 수 없습니다.');
        }
        return strtoupper(trim((string) $parts[1]));
    }
}
