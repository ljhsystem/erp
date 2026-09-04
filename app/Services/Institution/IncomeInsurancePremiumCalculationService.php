<?php

namespace App\Services\Institution;

final class IncomeInsurancePremiumCalculationService
{
    public function calculate(float $base, float $rate, array $standard): array
    {
        $values = (array) ($standard['value_data'] ?? []);
        $policy = (array) ($values['calculation_policy'] ?? []);
        if ($base < 0 || $rate < 0 || empty($policy['method'])) {
            throw new \RuntimeException('공식 보험료 계산정책이 없습니다.');
        }
        $before = round($base * $rate, 4);
        $amount = $this->finalize($before, $policy, $values);
        return [
            'calculation_basis_amount' => $base,
            'calculation_rate' => $rate,
            'calculation_before_rounding' => $before,
            'rounding_method_code' => (string) $policy['method'],
            'rounding_unit' => max(1, (int) ($policy['discard_below_unit'] ?? 1)),
            'calculated_amount' => round($amount, 2),
        ];
    }

    public function finalize(float $value, array $policy, array $values): float
    {
        if (($policy['stage'] ?? '') === 'ASSESSMENT_BASE') return $value;
        $stage = (string) ($values['result_limit_application_stage'] ?? '');
        if ($stage === 'AFTER_PREMIUM_CALCULATION') $value = $this->clamp($value, $values);
        $value = $this->round($value, $policy);
        if ($stage === 'AFTER_ROUNDING' || ($stage === '' && $this->limitsReady($values, $policy))) {
            $value = $this->clamp($value, $values);
        }
        return $value;
    }

    private function round(float $value, array $policy): float
    {
        $unit = max(1, (int) ($policy['discard_below_unit'] ?? 1));
        return match ($policy['method'] ?? '') {
            'TRUNCATE' => floor($value / $unit) * $unit,
            'ROUND' => round($value / $unit) * $unit,
            'ROUND_UP', 'CEIL' => ceil($value / $unit) * $unit,
            default => throw new \RuntimeException('공식 끝수처리 정책이 없습니다.'),
        };
    }

    private function clamp(float $value, array $values): float
    {
        if (isset($values['minimum_result_amount']) && $values['minimum_result_amount'] !== '') $value = max($value, (float) $values['minimum_result_amount']);
        if (isset($values['maximum_result_amount']) && $values['maximum_result_amount'] !== '') $value = min($value, (float) $values['maximum_result_amount']);
        return $value;
    }

    private function limitsReady(array $values, array $policy): bool
    {
        $hasMin = isset($values['minimum_result_amount']) && $values['minimum_result_amount'] !== '';
        $hasMax = isset($values['maximum_result_amount']) && $values['maximum_result_amount'] !== '';
        if (!$hasMin && !$hasMax) return true;
        $stage = (string) ($values['result_limit_application_stage'] ?? '');
        if (in_array($stage, ['AFTER_PREMIUM_CALCULATION', 'AFTER_ROUNDING'], true)) return true;
        return $stage === '' && ($policy['method'] ?? '') === 'TRUNCATE';
    }
}
