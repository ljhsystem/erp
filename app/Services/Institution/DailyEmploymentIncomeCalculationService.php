<?php

namespace App\Services\Institution;

use App\Services\System\StatutoryStandardResolver;
use PDO;

final class DailyEmploymentIncomeCalculationService
{
    public const STANDARD_TYPE = 'DAILY_WORKER_INCOME_TAX';
    public const LOCAL_TAX_STANDARD_TYPE = 'LOCAL_INCOME_TAX_WITHHOLDING';

    private StatutoryStandardResolver $standards;

    public function __construct(PDO $db)
    {
        $this->standards = new StatutoryStandardResolver($db);
    }

    /**
     * 일용근로소득세는 근무일별 지급액 단위로 계산한다.
     * 지방소득세율은 호출자가 별도 법정기준으로 확정한 값만 전달한다.
     */
    public function calculateWorkday(string $workDate, float $grossAmount, float $nonTaxableAmount, ?string $withholdingDate = null): array
    {
        if (!$this->isDate($workDate) || $grossAmount < 0 || $nonTaxableAmount < 0 || $nonTaxableAmount > $grossAmount) {
            throw new \InvalidArgumentException('근무일과 지급금액을 확인해 주세요.');
        }

        $taxReferenceDate = $withholdingDate ?? $workDate;
        if (!$this->isDate($taxReferenceDate)) throw new \InvalidArgumentException('원천징수일을 확인해 주세요.');
        $standard = $this->standards->resolve(self::STANDARD_TYPE, $taxReferenceDate);
        $value = (array) ($standard['value_data'] ?? []);
        $policy = (array) ($value['calculation_policy'] ?? []);
        $this->assertContract($value, $policy);

        $taxable = max(0.0, $grossAmount - $nonTaxableAmount);
        $deductionAmount = min($taxable, max(0.0, (float) $value['daily_income_deduction']));
        $calculationBasis = max(0.0, $taxable - $deductionAmount);
        $beforeCredit = max(0.0, $calculationBasis * (float) $value['daily_income_tax_rate']);
        $taxCreditAmount = max(0.0, $beforeCredit * (float) $value['daily_income_tax_credit_rate']);
        $afterCredit = max(0.0, $beforeCredit - $taxCreditAmount);
        $incomeTax = max(0.0, $this->roundByPolicy($afterCredit, $policy));

        $threshold = (float) $policy['threshold'];
        if ($policy['threshold_comparison'] === 'LESS_THAN' && $incomeTax < $threshold) {
            $incomeTax = 0.0;
        }
        $localStandard = $this->standards->resolve(self::LOCAL_TAX_STANDARD_TYPE, $taxReferenceDate);
        $localValue = (array) ($localStandard['value_data'] ?? []);
        $localPolicy = (array) ($localValue['calculation_policy'] ?? []);
        $localIncomeTaxRate = $localValue['rate_value'] ?? null;
        if (!is_numeric($localIncomeTaxRate) || !isset($localPolicy['method'], $localPolicy['discard_below_unit'])) {
            throw new \RuntimeException('지방소득세 공식 계산정책이 완전하지 않습니다.');
        }
        if ((float) $localIncomeTaxRate < 0) {
            throw new \RuntimeException('지방소득세 공식 세율이 올바르지 않습니다.');
        }
        $localBeforeRounding = max(0.0, $incomeTax * (float) $localIncomeTaxRate);
        $localIncomeTax = max(0.0, $this->roundByPolicy($localBeforeRounding, $localPolicy));

        $lines = [
            [
                'line_type_code' => 'DEDUCTION',
                'line_code' => 'DAILY_WORKER_INCOME_TAX',
                'line_name_snapshot' => '일용근로소득세',
                'application_status_code' => 'APPLICABLE',
                'calculation_basis_amount' => $calculationBasis,
                'calculation_rate' => (float) $value['daily_income_tax_rate'],
                'calculation_before_rounding' => $afterCredit,
                'rounding_method_code' => (string) $policy['method'],
                'rounding_unit' => (float) $policy['discard_below_unit'],
                'statutory_standard_id' => (string) $standard['id'],
                'standard_effective_from' => $standard['effective_from'] ?? null,
                'standard_effective_to' => $standard['effective_to'] ?? null,
                'final_amount' => $incomeTax,
            ],
            [
                'line_type_code' => 'DEDUCTION',
                'line_code' => 'LOCAL_INCOME_TAX',
                'line_name_snapshot' => '지방소득세',
                'application_status_code' => 'APPLICABLE',
                'calculation_basis_amount' => $incomeTax,
                'calculation_rate' => (float) $localIncomeTaxRate,
                'calculation_before_rounding' => $localBeforeRounding,
                'rounding_method_code' => (string) $localPolicy['method'],
                'rounding_unit' => (float) $localPolicy['discard_below_unit'],
                'statutory_standard_id' => (string) $localStandard['id'],
                'standard_effective_from' => $localStandard['effective_from'] ?? null,
                'standard_effective_to' => $localStandard['effective_to'] ?? null,
                'final_amount' => $localIncomeTax,
            ],
        ];
        $lines[0]['calculation_mode_projection'] = IncomeCalculationModeProjectionService::automatic($lines[0], [
            'calculation_basis_name' => '일용근로소득 공제·세율·근로소득세액공제를 순서대로 적용',
            'detail' => '근무일별 과세 지급액을 기준으로 법정 공제와 세액공제 후 끝수처리합니다.',
        ]);
        $lines[1]['calculation_mode_projection'] = IncomeCalculationModeProjectionService::automatic($lines[1], [
            'calculation_basis_name' => '확정된 일용근로소득세를 기준으로 지방소득세율 적용',
            'detail' => '근로소득세에 법정 지방소득세율을 적용한 뒤 끝수처리합니다.',
        ]);

        return [
            'taxable_amount' => $taxable,
            'non_taxable_amount' => $nonTaxableAmount,
            'daily_income_deduction_amount' => $deductionAmount,
            'income_tax_base_amount' => $calculationBasis,
            'calculated_income_tax_amount' => $beforeCredit,
            'earned_income_tax_credit_amount' => $taxCreditAmount,
            'income_tax_after_credit_amount' => $afterCredit,
            'income_tax_amount' => $incomeTax,
            'local_income_tax_amount' => $localIncomeTax,
            'statutory_standard_id' => (string) $standard['id'],
            'lines' => $lines,
        ];
    }

    private function assertContract(array $value, array $policy): void
    {
        foreach (['daily_income_deduction', 'daily_income_tax_rate', 'daily_income_tax_credit_rate'] as $key) {
            if (!array_key_exists($key, $value) || !is_numeric($value[$key])) {
                throw new \RuntimeException('일용근로소득 법정기준 값이 완전하지 않습니다.');
            }
        }
        if ((float) $value['daily_income_deduction'] < 0
            || (float) $value['daily_income_tax_rate'] < 0
            || (float) $value['daily_income_tax_credit_rate'] < 0
            || (float) $value['daily_income_tax_credit_rate'] > 1) {
            throw new \RuntimeException('일용근로소득 법정기준 값의 허용범위를 확인해 주세요.');
        }
        foreach (['method', 'discard_below_unit', 'stage', 'base_value_code', 'aggregation_unit', 'threshold', 'threshold_comparison', 'workplace_scope'] as $key) {
            if (!array_key_exists($key, $policy)) {
                throw new \RuntimeException('일용근로소득 계산정책이 완전하지 않습니다.');
            }
        }
        if ($policy['stage'] !== 'AFTER_TAX_CREDIT'
            || $policy['base_value_code'] !== 'DAILY_TAX_AFTER_CREDIT'
            || $policy['aggregation_unit'] !== 'WITHHOLDING_AGENT_RECIPIENT_WORKDAY_PAYMENT'
            || $policy['workplace_scope'] !== 'EACH_WORKPLACE') {
            throw new \RuntimeException('일용근로소득 법정기준의 계산단위가 지원 계약과 다릅니다.');
        }
    }

    private function roundByPolicy(float $amount, array $policy): float
    {
        $amount = max(0.0, $amount);
        $unit = max(1.0, (float) $policy['discard_below_unit']);
        return match ((string) $policy['method']) {
            'TRUNCATE' => floor($amount / $unit) * $unit,
            'ROUND' => round($amount / $unit) * $unit,
            'CEIL' => ceil($amount / $unit) * $unit,
            default => throw new \RuntimeException('지원하지 않는 끝수처리 정책입니다.'),
        };
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
