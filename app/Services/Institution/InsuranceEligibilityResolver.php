<?php

declare(strict_types=1);

namespace App\Services\Institution;

use App\Services\System\StatutoryStandardResolver;
use PDO;

final class InsuranceEligibilityResolver
{
    private StatutoryStandardResolver $standards;
    private InsuranceEligibilityPolicyValidator $validator;
    private InsuranceEligibilityConditionEvaluator $conditionEvaluator;
    private InsuranceEligibilityDecisionModelEvaluator $decisionModelEvaluator;
    private InsuranceEligibilityReasonProjectionService $reasonProjection;

    public function __construct(private readonly PDO $db)
    {
        $this->standards = new StatutoryStandardResolver($db);
        $this->validator = new InsuranceEligibilityPolicyValidator();
        $this->conditionEvaluator = new InsuranceEligibilityConditionEvaluator();
        $this->decisionModelEvaluator = new InsuranceEligibilityDecisionModelEvaluator($this->conditionEvaluator);
        $this->reasonProjection = new InsuranceEligibilityReasonProjectionService($db);
    }

    public function resolve(array $context): array
    {
        $missing = [];
        foreach (['company_id', 'worker_client_id', 'attribution_date', 'insurance_type_code', 'employment_type_code', 'work_scope_code'] as $field) {
            if (empty($context[$field])) {
                $missing[] = $this->missingInput($field);
            }
        }
        if ($missing !== []) {
            return $this->confirmation($missing, null, null, [], $context);
        }

        $additionalDimensions = [];
        if (array_key_exists('transition_status_code', $context) && $context['transition_status_code'] !== null) {
            $additionalDimensions['transition_status_code'] = $context['transition_status_code'];
        }
        $standard = $this->standards->resolveOptionalComponent(
            (string)$context['insurance_type_code'],
            'ELIGIBILITY',
            (string)$context['employment_type_code'],
            (string)$context['work_scope_code'],
            (string)$context['attribution_date'],
            $additionalDimensions
        );
        if ($standard === null) {
            return $this->confirmation([['field'=>'eligibility_revision', 'code'=>'ELIGIBILITY_REVISION_NOT_REGISTERED']], null, null, [], $context);
        }
        $policy = (array)($standard['value_data'] ?? []);
        $this->validator->validate($policy);

        $decisionModel = (string) ($policy['decision_model_code'] ?? 'SIMPLE_PERSON_ELIGIBILITY');
        if ($decisionModel !== 'SIMPLE_PERSON_ELIGIBILITY') {
            $result = $this->decisionModelEvaluator->evaluate($policy, $context);
            foreach ($result['component_results'] as &$componentResult) {
                $componentResult['eligibility_revision_id'] = $standard['id'];
            }
            unset($componentResult);
            if (isset($result['stage_results'])) $result['stage_results'] = $result['component_results'];
            return $this->decorate($result, $standard, $policy, $context) + ['decision_model_code' => $decisionModel];
        }

        if (!empty($policy['dependent_insurance_type_code'])) {
            if (!isset($context['dependent_result'])) {
                return $this->confirmation([['field'=>'dependent_result', 'code'=>'DEPENDENT_INSURANCE_RESULT_REQUIRED']], $standard, $policy, [], $context);
            }
            $dependent = $context['dependent_result'];
            return $this->decorate([
                'status'=>$dependent['status'],
                'reason_code'=>'DEPENDENT_INSURANCE_RESULT',
                'missing_inputs'=>$dependent['missing_inputs'] ?? [],
                'premium_revision_id'=>null,
                'evaluated_conditions'=>[[
                    'condition_code'=>'DEPENDENT_RESULT',
                    'state'=>$dependent['status'] === 'CONFIRMATION_REQUIRED'
                        ? InsuranceEligibilityConditionEvaluator::UNKNOWN
                        : InsuranceEligibilityConditionEvaluator::TRUE,
                ]],
            ], $standard, $policy, $context);
        }

        $conditions = [
            $this->evaluateAge((array)($policy['age'] ?? []), $context),
            $this->evaluateEmploymentPeriod((array)($policy['employment_period'] ?? []), $context),
            $this->evaluateMonthlyConditions((array)($policy['monthly_conditions'] ?? []), $context),
        ];
        $state = $this->conditionEvaluator->combine(array_column($conditions, 'state'), (string)($policy['overall_combination_code'] ?? 'ALL'));
        if ($state === InsuranceEligibilityConditionEvaluator::FALSE) {
            $failed = current(array_filter($conditions, static fn(array $condition): bool => $condition['state'] === InsuranceEligibilityConditionEvaluator::FALSE));
            return $this->result('NOT_ELIGIBLE', (string)($failed['reason_code'] ?? 'POLICY_CONDITIONS_NOT_MET'), $standard, $policy, $context, $conditions);
        }
        if ($state === InsuranceEligibilityConditionEvaluator::UNKNOWN) {
            foreach ($conditions as $condition) {
                if ($condition['state'] !== InsuranceEligibilityConditionEvaluator::UNKNOWN) {
                    continue;
                }
                foreach ((array)($condition['missing_inputs'] ?? []) as $row) {
                    $missing[$row['field'] . '|' . $row['code']] = $row;
                }
            }
            return $this->confirmation(array_values($missing), $standard, $policy, $conditions, $context);
        }
        return $this->result('ELIGIBLE', 'POLICY_CONDITIONS_MET', $standard, $policy, $context, $conditions);
    }

    private function evaluateAge(array $policy, array $context): array
    {
        if (($policy['minimum_age'] ?? null) === null && ($policy['maximum_age_exclusive'] ?? null) === null) {
            return $this->condition('AGE', InsuranceEligibilityConditionEvaluator::TRUE);
        }
        if ($this->missing($context, 'birth_date')) {
            return $this->condition('AGE', InsuranceEligibilityConditionEvaluator::UNKNOWN, null, [$this->missingInput('birth_date')]);
        }
        $age = (int)(new \DateTimeImmutable((string)$context['birth_date']))->diff(new \DateTimeImmutable((string)$context['attribution_date']))->y;
        if (($policy['minimum_age'] ?? null) !== null && $age < (int)$policy['minimum_age']) {
            return $this->condition('AGE', InsuranceEligibilityConditionEvaluator::FALSE, 'MINIMUM_AGE_NOT_MET', [], ['evaluated_age'=>$age]);
        }
        if (($policy['maximum_age_exclusive'] ?? null) !== null && $age >= (int)$policy['maximum_age_exclusive']) {
            return $this->condition('AGE', InsuranceEligibilityConditionEvaluator::FALSE, 'MAXIMUM_AGE_EXCLUDED', [], ['evaluated_age'=>$age]);
        }
        return $this->condition('AGE', InsuranceEligibilityConditionEvaluator::TRUE, null, [], ['evaluated_age'=>$age]);
    }

    private function evaluateEmploymentPeriod(array $policy, array $context): array
    {
        $months = $policy['minimum_continuous_months'] ?? null;
        if ($months === null) {
            return $this->condition('EMPLOYMENT_PERIOD', InsuranceEligibilityConditionEvaluator::TRUE);
        }
        $missing = [];
        foreach (['employment_start_date', 'employment_end_date_or_open_status', 'continuous_employment_confirmed'] as $field) {
            if ($this->missing($context, $field)) {
                $missing[] = $this->missingInput($field);
            }
        }
        if ($missing !== []) {
            return $this->condition('EMPLOYMENT_PERIOD', InsuranceEligibilityConditionEvaluator::UNKNOWN, null, $missing);
        }
        $start = new \DateTimeImmutable((string)$context['employment_start_date']);
        $end = !empty($context['employment_end_date'])
            ? new \DateTimeImmutable((string)$context['employment_end_date'])
            : new \DateTimeImmutable((string)$context['attribution_date']);
        $satisfied = $start->modify('+' . (int)$months . ' month') <= $end;
        return $this->condition('EMPLOYMENT_PERIOD', $satisfied ? InsuranceEligibilityConditionEvaluator::TRUE : InsuranceEligibilityConditionEvaluator::FALSE, $satisfied ? null : 'CONTINUOUS_EMPLOYMENT_PERIOD_NOT_MET', [], ['minimum_continuous_months'=>(int)$months]);
    }

    private function evaluateMonthlyConditions(array $policy, array $context): array
    {
        $checks = [];
        foreach (['minimum_work_days'=>'monthly_work_days', 'minimum_work_minutes'=>'monthly_work_minutes', 'minimum_income_amount'=>'monthly_income_amount'] as $rule => $input) {
            if (($policy[$rule] ?? null) === null) {
                continue;
            }
            if ($this->missing($context, $input)) {
                $checks[] = $this->condition(strtoupper($rule), InsuranceEligibilityConditionEvaluator::UNKNOWN, null, [$this->missingInput($input)]);
                continue;
            }
            $checks[] = $this->condition(strtoupper($rule), (float)$context[$input] >= (float)$policy[$rule] ? InsuranceEligibilityConditionEvaluator::TRUE : InsuranceEligibilityConditionEvaluator::FALSE, null, [], ['required_value'=>$policy[$rule], 'actual_value'=>$context[$input]]);
        }
        $combination = (string)($policy['combination_code'] ?? 'ANY');
        $state = $this->conditionEvaluator->combine(array_column($checks, 'state'), $combination);
        $missing = [];
        foreach ($checks as $check) {
            foreach ((array)($check['missing_inputs'] ?? []) as $row) {
                $missing[] = $row;
            }
        }
        return $this->condition('MONTHLY_CONDITIONS', $state, $state === InsuranceEligibilityConditionEvaluator::FALSE ? 'MONTHLY_CONDITIONS_NOT_MET' : null, $missing, ['combination_code'=>$combination, 'conditions'=>$checks]);
    }

    private function condition(string $code, string $state, ?string $reasonCode = null, array $missing = [], array $details = []): array
    {
        return ['condition_code'=>$code, 'state'=>$state, 'reason_code'=>$reasonCode, 'missing_inputs'=>$missing] + $details;
    }

    private function missing(array $context, string $field): bool
    {
        if ($field === 'employment_end_date_or_open_status') {
            return empty($context['employment_end_date']) && !array_key_exists('employment_end_open', $context);
        }
        return !array_key_exists($field, $context) || $context[$field] === null || $context[$field] === '';
    }

    private function missingInput(string $field): array
    {
        return ['field'=>$field, 'code'=>strtoupper($field) . '_REQUIRED'];
    }

    private function confirmation(array $missing, ?array $standard, ?array $policy, array $conditions, array $context): array
    {
        return $this->decorate(['status'=>'CONFIRMATION_REQUIRED', 'reason_code'=>'MISSING_ELIGIBILITY_INPUT', 'missing_inputs'=>$missing, 'premium_revision_id'=>null, 'evaluated_conditions'=>$conditions], $standard, $policy, $context);
    }

    private function result(string $status, string $reason, array $standard, array $policy, array $context, array $conditions): array
    {
        return $this->decorate(['status'=>$status, 'reason_code'=>$reason, 'missing_inputs'=>[], 'premium_revision_id'=>null, 'evaluated_conditions'=>$conditions, 'used_work_days'=>$context['monthly_work_days'] ?? null, 'used_work_minutes'=>$context['monthly_work_minutes'] ?? null, 'used_income_amount'=>$context['monthly_income_amount'] ?? null], $standard, $policy, $context);
    }

    private function decorate(array $result, ?array $standard, ?array $policy, array $context): array
    {
        $decorated = $result + [
            'eligibility_revision_id'=>$standard['id'] ?? null,
            'selected_revision_id'=>$standard['id'] ?? null,
            'revision_effective_from'=>$standard['effective_from'] ?? null,
            'revision_effective_to'=>$standard['effective_to'] ?? null,
            'policy_scope'=>[
                'insurance_type_code'=>$policy['insurance_type_code'] ?? ($context['insurance_type_code'] ?? null),
                'employment_type_code'=>$policy['employment_type_code'] ?? ($context['employment_type_code'] ?? null),
                'work_scope_code'=>$policy['work_scope_code'] ?? ($context['work_scope_code'] ?? null),
            ],
            'result_code'=>$result['status'],
            'condition_combination_code'=>$policy['overall_combination_code'] ?? null,
            'worker_birth_date_exists'=>!$this->missing($context, 'birth_date'),
        ];
        return $this->reasonProjection->enrich($decorated, $policy ?? []);
    }
}
