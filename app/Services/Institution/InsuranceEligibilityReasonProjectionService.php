<?php

declare(strict_types=1);

namespace App\Services\Institution;

use App\Models\Institution\InsuranceEligibilityReasonCodeModel;
use PDO;

final class InsuranceEligibilityReasonProjectionService
{
    private ?InsuranceEligibilityReasonCodeModel $codes;
    private array $cache = [];

    public function __construct(?PDO $db = null)
    {
        $this->codes = $db === null ? null : new InsuranceEligibilityReasonCodeModel($db);
    }

    public function enrich(array $result, array $policy): array
    {
        $reasonCode = trim((string) ($result['reason_code'] ?? $result['eligibility_reason_code'] ?? ''));
        $reason = $this->reasonByCode($policy, $reasonCode);

        if (trim((string) ($result['reason_name'] ?? '')) === '' && $reason !== null) {
            $result['reason_name'] = trim((string) ($reason['name'] ?? ''));
        }
        if (trim((string) ($result['reason_detail'] ?? '')) === '' && $reason !== null) {
            $result['reason_detail'] = trim((string) ($reason['detail'] ?? $reason['reason_detail'] ?? ''));
        }

        $evaluated = (array) ($result['evaluated_conditions'] ?? []);
        $result['failed_conditions'] = array_values(array_filter(
            $evaluated,
            static fn(array $condition): bool => ($condition['state'] ?? null) === InsuranceEligibilityConditionEvaluator::FALSE
                || in_array((string) ($condition['status_code'] ?? ''), ['EXCLUDED', 'NOT_ELIGIBLE'], true)
        ));
        $result['missing_facts'] = array_values((array) ($result['missing_inputs'] ?? []));
        $result['component_results'] = array_values((array) ($result['component_results'] ?? []));
        $result['passed_conditions'] = array_values(array_filter(
            $evaluated,
            static fn(array $condition): bool => ($condition['state'] ?? null) === InsuranceEligibilityConditionEvaluator::TRUE
                || in_array((string) ($condition['status_code'] ?? ''), ['APPLICABLE', 'ELIGIBLE'], true)
        ));

        if (trim((string) ($result['reason_name'] ?? '')) === '') {
            $decisive = $this->decisiveComponent($result['component_results']);
            if ($decisive !== null) {
                $result['reason_code'] = $reasonCode !== '' ? $reasonCode : ($decisive['reason_code'] ?? null);
                $result['reason_name'] = trim((string) ($decisive['reason_name'] ?? ''));
                $result['reason_detail'] = trim((string) ($decisive['reason_detail'] ?? ''));
            }
        }
        $status = strtoupper(trim((string) ($result['status'] ?? $result['result_code'] ?? $result['eligibility_status_code'] ?? '')));
        if (in_array($status, ['ELIGIBLE', 'APPLICABLE', 'PARTIALLY_ELIGIBLE', 'PARTIALLY_APPLICABLE'], true)) {
            $basisComponent = $this->applicableComponent($result['component_results']);
            $result['decision_basis_code'] = trim((string) ($result['decision_basis_code'] ?? $reasonCode
                ?: ($basisComponent['reason_code'] ?? '')));
            $result['decision_basis_name'] = trim((string) ($result['decision_basis_name'] ?? $result['reason_name']
                ?? ($basisComponent['reason_name'] ?? '')));
            $result['decision_basis_detail'] = trim((string) ($result['decision_basis_detail'] ?? $result['reason_detail']
                ?? ($basisComponent['reason_detail'] ?? '')));
        }

        return $result;
    }

    private function reasonByCode(array $policy, string $reasonCode): ?array
    {
        if ($reasonCode === '') {
            return null;
        }
        foreach ((array) ($policy['reason_codes'] ?? []) as $reason) {
            if (is_array($reason) && (string) ($reason['code'] ?? '') === $reasonCode) {
                return $reason;
            }
        }
        if ($this->codes === null) {
            return null;
        }
        if (!array_key_exists($reasonCode, $this->cache)) {
            $row = $this->codes->find($reasonCode);
            $extra = is_array($row) ? json_decode((string) ($row['extra_data'] ?? ''), true) : null;
            $this->cache[$reasonCode] = is_array($row) ? [
                'code' => $reasonCode,
                'name' => trim((string) ($row['code_name'] ?? '')),
                'detail' => trim((string) (($extra['reason_detail'] ?? null) ?: ($row['note'] ?? ''))),
            ] : null;
        }
        return $this->cache[$reasonCode];
    }

    private function decisiveComponent(array $components): ?array
    {
        foreach (['EXCLUDED', 'CONFIRMATION_REQUIRED', 'APPLICABLE'] as $status) {
            foreach ($components as $component) {
                if (is_array($component)
                    && (string) ($component['status_code'] ?? '') === $status
                    && trim((string) ($component['reason_name'] ?? '')) !== '') {
                    return $component;
                }
            }
        }
        return null;
    }

    private function applicableComponent(array $components): ?array
    {
        foreach ($components as $component) {
            if (is_array($component) && (string) ($component['status_code'] ?? '') === 'APPLICABLE') {
                return $component;
            }
        }
        return null;
    }
}
