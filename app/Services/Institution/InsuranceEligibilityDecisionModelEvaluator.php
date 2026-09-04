<?php

declare(strict_types=1);

namespace App\Services\Institution;

final class InsuranceEligibilityDecisionModelEvaluator
{
    public function __construct(private readonly InsuranceEligibilityConditionEvaluator $conditions)
    {
    }

    public function evaluate(array $policy, array $context): array
    {
        return match ((string) $policy['decision_model_code']) {
            'COMPONENT_ELIGIBILITY' => $this->evaluateComponents($policy, $context),
            'BUSINESS_AND_WORKER_ELIGIBILITY' => $this->evaluateStages($policy, $context),
            default => throw new \InvalidArgumentException('지원하지 않는 가입자격 판정모델입니다.'),
        };
    }

    private function evaluateComponents(array $policy, array $context): array
    {
        $results = [];
        foreach ($policy['components'] as $component) {
            $evaluated = $this->evaluateRuleGroup($component, $context);
            $status = $this->stateToStatus($evaluated['state']);
            $applicationFact = 'NOT_REQUIRED';
            $requiredApplication = (string) ($component['required_application_code'] ?? 'NOT_REQUIRED');
            if ($status === 'APPLICABLE' && is_array($component['optional_application_condition'] ?? null)) {
                $optional = $this->evaluateExpression($component['optional_application_condition'], $context);
                if ($optional['state'] === InsuranceEligibilityConditionEvaluator::UNKNOWN) {
                    $status = 'CONFIRMATION_REQUIRED';
                    $evaluated['missing_inputs'] = array_merge($evaluated['missing_inputs'], $optional['missing_inputs']);
                } elseif ($optional['state'] === InsuranceEligibilityConditionEvaluator::TRUE) {
                    $requiredApplication = 'OPTIONAL';
                }
            }
            if ($requiredApplication === 'OPTIONAL' && $status === 'APPLICABLE') {
                $applicationFact = strtoupper(trim((string) (($context['application_facts'] ?? [])[$component['component_code']] ?? 'UNKNOWN')));
                $status = match ($applicationFact) {
                    'APPLIED' => 'APPLICABLE',
                    'NOT_APPLIED' => 'EXCLUDED',
                    default => 'CONFIRMATION_REQUIRED',
                };
            }
            $results[] = [
                'component_code' => (string) $component['component_code'],
                'component_name' => (string) $component['component_name'],
                'status_code' => $status,
                'status_name' => $this->statusName($status),
                'reason_code' => $this->reasonCode($evaluated, $status, $component),
                'reason_name' => $this->reasonName($evaluated, $status, $component),
                'reason_detail' => $this->reasonDetail($evaluated, $status, $component),
                'required_application_code' => $requiredApplication,
                'application_fact_status_code' => $applicationFact,
                'employee_contribution_applicable' => $status === 'APPLICABLE' && !empty($component['employee_contribution_applicable']),
                'employer_contribution_applicable' => $status === 'APPLICABLE' && !empty($component['employer_contribution_applicable']),
                'calculation_basis' => null,
                'employee_amount' => null,
                'employer_amount' => null,
                'eligibility_revision_id' => null,
                'premium_revision_id' => null,
                'evaluated_rules' => $evaluated['rules'],
                'missing_inputs' => $evaluated['missing_inputs'],
            ];
        }

        $statuses = array_column($results, 'status_code');
        $status = in_array('CONFIRMATION_REQUIRED', $statuses, true)
            ? 'CONFIRMATION_REQUIRED'
            : (count(array_unique($statuses)) > 1
                ? 'PARTIALLY_ELIGIBLE'
                : (($statuses[0] ?? 'EXCLUDED') === 'APPLICABLE' ? 'ELIGIBLE' : 'NOT_ELIGIBLE'));

        return $this->decision($status, $results, 'component_results');
    }

    private function evaluateStages(array $policy, array $context): array
    {
        $results = [];
        foreach ($policy['stages'] as $stage) {
            $evaluated = $this->evaluateRuleGroup($stage, $context);
            $status = $this->stateToStatus($evaluated['state']);
            $results[] = [
                'component_code' => (string) $stage['stage_code'],
                'component_name' => (string) $stage['stage_name'],
                'status_code' => $status,
                'status_name' => $this->statusName($status),
                'reason_code' => $this->reasonCode($evaluated, $status, $stage),
                'reason_name' => $this->reasonName($evaluated, $status, $stage),
                'reason_detail' => $this->reasonDetail($evaluated, $status, $stage),
                'required_application_code' => 'NOT_REQUIRED',
                'application_fact_status_code' => 'NOT_REQUIRED',
                'employee_contribution_applicable' => false,
                'employer_contribution_applicable' => $status === 'APPLICABLE',
                'calculation_basis' => null,
                'employee_amount' => null,
                'employer_amount' => null,
                'eligibility_revision_id' => null,
                'premium_revision_id' => null,
                'evaluated_rules' => $evaluated['rules'],
                'missing_inputs' => $evaluated['missing_inputs'],
            ];
        }
        $state = $this->conditions->combine(array_map(
            static fn(array $row): string => match ($row['status_code']) {
                'APPLICABLE' => InsuranceEligibilityConditionEvaluator::TRUE,
                'EXCLUDED' => InsuranceEligibilityConditionEvaluator::FALSE,
                default => InsuranceEligibilityConditionEvaluator::UNKNOWN,
            },
            $results
        ), 'ALL');
        $status = match ($state) {
            InsuranceEligibilityConditionEvaluator::TRUE => 'ELIGIBLE',
            InsuranceEligibilityConditionEvaluator::FALSE => 'NOT_ELIGIBLE',
            default => 'CONFIRMATION_REQUIRED',
        };
        return $this->decision($status, $results, 'stage_results');
    }

    private function evaluateRuleGroup(array $group, array $context): array
    {
        if (is_array($group['condition'] ?? null)) {
            return $this->evaluateExpression($group['condition'], $context);
        }
        $rules = [];
        $missing = [];
        foreach ((array) ($group['rules'] ?? []) as $rule) {
            $factCode = (string) $rule['fact_code'];
            $exists = array_key_exists($factCode, $context) && $context[$factCode] !== null && $context[$factCode] !== '';
            $state = $exists ? $this->compare($context[$factCode], (string) $rule['operator'], $rule['expected_value'] ?? null)
                : InsuranceEligibilityConditionEvaluator::UNKNOWN;
            if (!$exists) {
                $missing[$factCode] = ['field' => $factCode, 'code' => strtoupper($factCode) . '_REQUIRED'];
            }
            $rules[] = $rule + ['state' => $state, 'actual_value' => $exists ? $context[$factCode] : null];
        }
        return [
            'state' => $this->conditions->combine(array_column($rules, 'state'), (string) ($group['combination_code'] ?? 'ALL')),
            'rules' => $rules,
            'missing_inputs' => array_values($missing),
        ];
    }

    private function evaluateExpression(array $expression, array $context): array
    {
        if (isset($expression['conditions'])) {
            $evaluated = array_map(
                fn(array $condition): array => $this->evaluateExpression($condition, $context),
                array_values(array_filter((array) $expression['conditions'], 'is_array'))
            );
            $missing = [];
            foreach ($evaluated as $result) {
                foreach ($result['missing_inputs'] as $row) $missing[$row['field'] . '|' . $row['code']] = $row;
            }
            return [
                'state' => $this->conditions->combine(
                    array_column($evaluated, 'state'),
                    (string) ($expression['combination_code'] ?? 'ALL')
                ),
                'rules' => $evaluated,
                'missing_inputs' => array_values($missing),
            ];
        }

        $factCode = (string) ($expression['fact_code'] ?? '');
        $exists = $factCode !== '' && array_key_exists($factCode, $context)
            && $context[$factCode] !== null && $context[$factCode] !== '';
        $state = $exists
            ? $this->compare($context[$factCode], (string) ($expression['operator'] ?? ''), $expression['expected_value'] ?? null)
            : InsuranceEligibilityConditionEvaluator::UNKNOWN;
        return [
            'state' => $state,
            'rules' => [$expression + ['state' => $state, 'actual_value' => $exists ? $context[$factCode] : null]],
            'missing_inputs' => $exists ? [] : [[
                'field' => $factCode,
                'code' => strtoupper($factCode) . '_REQUIRED',
            ]],
        ];
    }

    private function compare(mixed $actual, string $operator, mixed $expected): string
    {
        $matched = match (strtoupper($operator)) {
            'EQ' => $actual === $expected || (string) $actual === (string) $expected,
            'NE' => !($actual === $expected || (string) $actual === (string) $expected),
            'IN' => in_array($actual, (array) $expected, true) || in_array((string) $actual, array_map('strval', (array) $expected), true),
            'NOT_IN' => !in_array($actual, (array) $expected, true) && !in_array((string) $actual, array_map('strval', (array) $expected), true),
            'LT' => (float) $actual < (float) $expected,
            'LTE' => (float) $actual <= (float) $expected,
            'GT' => (float) $actual > (float) $expected,
            'GTE' => (float) $actual >= (float) $expected,
            'TRUE' => filter_var($actual, FILTER_VALIDATE_BOOL),
            'FALSE' => !filter_var($actual, FILTER_VALIDATE_BOOL),
            default => throw new \InvalidArgumentException('지원하지 않는 가입자격 조건 연산자입니다.'),
        };
        return $matched ? InsuranceEligibilityConditionEvaluator::TRUE : InsuranceEligibilityConditionEvaluator::FALSE;
    }

    private function decision(string $status, array $results, string $resultKey): array
    {
        $missing = [];
        foreach ($results as $result) {
            if ($result['status_code'] !== 'CONFIRMATION_REQUIRED') continue;
            foreach ($result['missing_inputs'] as $row) $missing[$row['field'] . '|' . $row['code']] = $row;
        }
        return [
            'status' => $status,
            'result_code' => $status,
            'reason_code' => match ($status) {
                'ELIGIBLE' => 'POLICY_CONDITIONS_MET',
                'PARTIALLY_ELIGIBLE' => 'PARTIAL_COMPONENT_APPLICABILITY',
                'NOT_ELIGIBLE' => 'POLICY_CONDITIONS_NOT_MET',
                default => 'MISSING_ELIGIBILITY_INPUT',
            },
            'missing_inputs' => array_values($missing),
            'evaluated_conditions' => $results,
            $resultKey => $results,
            'component_results' => $results,
            'premium_revision_id' => null,
        ];
    }

    private function stateToStatus(string $state): string
    {
        return match ($state) {
            InsuranceEligibilityConditionEvaluator::TRUE => 'APPLICABLE',
            InsuranceEligibilityConditionEvaluator::FALSE => 'EXCLUDED',
            default => 'CONFIRMATION_REQUIRED',
        };
    }

    private function reasonCode(array $evaluated, string $status, array $definition): string
    {
        $key = match ($status) {'APPLICABLE' => 'applicable_reason', 'EXCLUDED' => 'excluded_reason', default => 'confirmation_reason'};
        return (string) (($definition[$key]['code'] ?? null) ?: match ($status) {
            'APPLICABLE' => 'POLICY_CONDITIONS_MET', 'EXCLUDED' => 'POLICY_CONDITIONS_NOT_MET', default => 'REQUIRED_FACT_MISSING',
        });
    }

    private function reasonName(array $evaluated, string $status, array $definition): string
    {
        $key = match ($status) {'APPLICABLE' => 'applicable_reason', 'EXCLUDED' => 'excluded_reason', default => 'confirmation_reason'};
        return (string) (($definition[$key]['name'] ?? null) ?: match ($status) {
            'APPLICABLE' => '법정 가입요건 충족', 'EXCLUDED' => '법정 가입요건 미충족', default => '가입자격 판정 사실 확인 필요',
        });
    }

    private function reasonDetail(array $evaluated, string $status, array $definition): string
    {
        $key = match ($status) {'APPLICABLE' => 'applicable_reason', 'EXCLUDED' => 'excluded_reason', default => 'confirmation_reason'};
        return (string) (($definition[$key]['detail'] ?? null) ?: $this->reasonName($evaluated, $status, $definition));
    }

    private function statusName(string $status): string
    {
        return ['APPLICABLE' => '적용', 'EXCLUDED' => '적용 제외', 'CONFIRMATION_REQUIRED' => '확인 필요'][$status];
    }
}
