<?php

declare(strict_types=1);

namespace App\Services\Institution;

final class InsuranceEligibilityPolicyValidator
{
    private const RESULTS = ['ELIGIBLE', 'PARTIALLY_ELIGIBLE', 'NOT_ELIGIBLE', 'CONFIRMATION_REQUIRED'];
    private const COMBINATIONS = ['ALL', 'ANY', 'NONE'];
    private const OPERATORS = ['EQ', 'NE', 'IN', 'NOT_IN', 'LT', 'LTE', 'GT', 'GTE', 'TRUE', 'FALSE'];

    public function validate(array $policy): void
    {
        $decisionModel = (string) ($policy['decision_model_code'] ?? 'SIMPLE_PERSON_ELIGIBILITY');
        if (!in_array($decisionModel, ['SIMPLE_PERSON_ELIGIBILITY', 'COMPONENT_ELIGIBILITY', 'BUSINESS_AND_WORKER_ELIGIBILITY'], true)) {
            throw new \InvalidArgumentException('가입자격 판정모델이 올바르지 않습니다.');
        }
        if ($decisionModel !== 'SIMPLE_PERSON_ELIGIBILITY') {
            $this->validateExtended($policy, $decisionModel);
            return;
        }
        $requiredFields = [
            'policy_version', 'insurance_type_code', 'employment_type_code', 'work_scope_code',
            'decision_code', 'age', 'employment_period', 'monthly_conditions',
            'overall_combination_code', 'aggregation', 'transition', 'requirements', 'exclusions',
            'dependent_insurance_type_code', 'premium_revision_type_code',
            'eligible_result_code', 'not_eligible_result_code', 'missing_input_result_code',
            'official_evidence_status', '_schema',
        ];
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $policy)) {
                throw new \InvalidArgumentException('가입자격 정책 필드가 누락되었습니다: ' . $field);
            }
        }
        if (!in_array($policy['overall_combination_code'], ['ALL', 'ANY'], true)) {
            throw new \InvalidArgumentException('가입자격 전체 조건 결합방식이 올바르지 않습니다.');
        }
        if (!in_array($policy['monthly_conditions']['combination_code'] ?? null, ['ALL', 'ANY', 'NONE'], true)) {
            throw new \InvalidArgumentException('가입자격 월 조건 결합방식이 올바르지 않습니다.');
        }
        foreach (['eligible_result_code', 'not_eligible_result_code', 'missing_input_result_code'] as $field) {
            if (!in_array($policy[$field], self::RESULTS, true)) {
                throw new \InvalidArgumentException('가입자격 결과코드가 올바르지 않습니다.');
            }
        }
        if (($policy['_schema']['condition_language'] ?? null) !== 'STRUCTURED_NO_EXPRESSION') {
            throw new \InvalidArgumentException('가입자격 정책은 구조화된 조건만 사용할 수 있습니다.');
        }
    }

    private function validateExtended(array $policy, string $decisionModel): void
    {
        foreach (['policy_version', 'insurance_type_code', 'employment_type_code', 'work_scope_code', 'required_facts', 'overall_aggregation_code', 'reason_codes', '_schema'] as $field) {
            if (!array_key_exists($field, $policy)) throw new \InvalidArgumentException('가입자격 정책 필드가 누락되었습니다: ' . $field);
        }
        $collectionKey = $decisionModel === 'COMPONENT_ELIGIBILITY' ? 'components' : 'stages';
        $codeKey = $decisionModel === 'COMPONENT_ELIGIBILITY' ? 'component_code' : 'stage_code';
        $nameKey = $decisionModel === 'COMPONENT_ELIGIBILITY' ? 'component_name' : 'stage_name';
        $expectedAggregation = $decisionModel === 'COMPONENT_ELIGIBILITY'
            ? 'COMPONENT_STATUS_AGGREGATION'
            : 'ALL_STAGES_REQUIRED';
        if ($policy['overall_aggregation_code'] !== $expectedAggregation) {
            throw new \InvalidArgumentException('가입자격 전체 집계방식이 판정모델과 일치하지 않습니다.');
        }
        if (empty($policy[$collectionKey]) || !is_array($policy[$collectionKey])) {
            throw new \InvalidArgumentException('가입자격 판정 구성요소가 비어 있습니다.');
        }
        if (!is_array($policy['required_facts']) || !is_array($policy['reason_codes'])) {
            throw new \InvalidArgumentException('가입자격 필수사실 또는 판정사유 계약이 올바르지 않습니다.');
        }
        $requiredFacts = [];
        foreach ($policy['required_facts'] as $fact) {
            if (!is_array($fact) || empty($fact['fact_code']) || empty($fact['fact_name'])) {
                throw new \InvalidArgumentException('가입자격 필수사실 정의가 올바르지 않습니다.');
            }
            $factCode = (string) $fact['fact_code'];
            if (isset($requiredFacts[$factCode])) {
                throw new \InvalidArgumentException('가입자격 필수사실 코드가 중복되었습니다: ' . $factCode);
            }
            $requiredFacts[$factCode] = true;
        }
        $reasonCodes = [];
        foreach ($policy['reason_codes'] as $reason) {
            if (!is_array($reason) || empty($reason['code']) || empty($reason['name'])) {
                throw new \InvalidArgumentException('가입자격 판정사유 정의가 올바르지 않습니다.');
            }
            $reasonCodes[(string) $reason['code']] = true;
        }
        $seenCodes = [];
        foreach ($policy[$collectionKey] as $row) {
            $hasRules = isset($row['rules']) && is_array($row['rules']);
            $hasCondition = isset($row['condition']) && is_array($row['condition']);
            if (empty($row[$codeKey]) || empty($row[$nameKey]) || (!$hasRules && !$hasCondition)) {
                throw new \InvalidArgumentException('가입자격 판정 구성요소 계약이 올바르지 않습니다.');
            }
            $rowCode = (string) $row[$codeKey];
            if (isset($seenCodes[$rowCode])) {
                throw new \InvalidArgumentException('가입자격 판정 구성요소 코드가 중복되었습니다: ' . $rowCode);
            }
            $seenCodes[$rowCode] = true;
            if (!in_array($row['combination_code'] ?? null, self::COMBINATIONS, true)) throw new \InvalidArgumentException('가입자격 조건 결합방식이 올바르지 않습니다.');
            if ($decisionModel === 'COMPONENT_ELIGIBILITY' && !in_array($row['required_application_code'] ?? null, ['NOT_REQUIRED', 'OPTIONAL'], true)) {
                throw new \InvalidArgumentException('가입신청 필요구분이 올바르지 않습니다.');
            }
            foreach ((array) ($row['rules'] ?? []) as $rule) {
                if (empty($rule['fact_code']) || !in_array($rule['operator'] ?? null, self::OPERATORS, true) || !array_key_exists('expected_value', $rule)) {
                    throw new \InvalidArgumentException('가입자격 구조화 조건이 올바르지 않습니다.');
                }
                if (!isset($requiredFacts[(string) $rule['fact_code']])) {
                    throw new \InvalidArgumentException('가입자격 조건에서 선언되지 않은 필수사실을 사용했습니다: ' . $rule['fact_code']);
                }
            }
            if ($hasCondition) $this->validateExpression($row['condition'], $requiredFacts);
            if (isset($row['optional_application_condition'])) {
                if ($decisionModel !== 'COMPONENT_ELIGIBILITY' || !is_array($row['optional_application_condition'])) {
                    throw new \InvalidArgumentException('임의가입 조건 계약이 올바르지 않습니다.');
                }
                $this->validateExpression($row['optional_application_condition'], $requiredFacts);
            }
            foreach (['applicable_reason', 'excluded_reason', 'confirmation_reason'] as $reasonKey) {
                if (empty($row[$reasonKey]['code']) || empty($row[$reasonKey]['name'])) throw new \InvalidArgumentException('가입자격 판정사유 표시명이 누락되었습니다.');
                if (!isset($reasonCodes[(string) $row[$reasonKey]['code']])) {
                    throw new \InvalidArgumentException('가입자격 판정사유가 reason_codes에 등록되지 않았습니다: ' . $row[$reasonKey]['code']);
                }
            }
        }
        if ($decisionModel === 'BUSINESS_AND_WORKER_ELIGIBILITY'
            && array_keys($seenCodes) !== ['BUSINESS_APPLICABILITY', 'WORKER_STATUS', 'ACTUAL_WORK_ENGAGEMENT']) {
            throw new \InvalidArgumentException('산재보험 가입자격 단계의 순서와 구성이 올바르지 않습니다.');
        }
        if (($policy['_schema']['condition_language'] ?? null) !== 'STRUCTURED_NO_EXPRESSION') throw new \InvalidArgumentException('가입자격 정책은 구조화된 조건만 사용할 수 있습니다.');
    }

    private function validateExpression(array $expression, array $requiredFacts): void
    {
        if (array_key_exists('conditions', $expression)) {
            if (!in_array($expression['combination_code'] ?? null, self::COMBINATIONS, true)
                || !is_array($expression['conditions']) || $expression['conditions'] === []) {
                throw new \InvalidArgumentException('가입자격 중첩 조건그룹이 올바르지 않습니다.');
            }
            foreach ($expression['conditions'] as $condition) {
                if (!is_array($condition)) throw new \InvalidArgumentException('가입자격 중첩 조건이 올바르지 않습니다.');
                $this->validateExpression($condition, $requiredFacts);
            }
            return;
        }
        $factCode = (string) ($expression['fact_code'] ?? '');
        if ($factCode === '' || !isset($requiredFacts[$factCode])
            || !in_array($expression['operator'] ?? null, self::OPERATORS, true)
            || !array_key_exists('expected_value', $expression)) {
            throw new \InvalidArgumentException('가입자격 중첩 조건식이 올바르지 않습니다.');
        }
    }
}
