<?php

declare(strict_types=1);

namespace App\Services\Institution;

final class DailyEmploymentIncomeBusinessUnitPolicyService
{
    private const POLICY_KEY = 'daily_employment_income';

    public function fromCodeRow(array $row): array
    {
        $extraData = json_decode((string) ($row['extra_data'] ?? ''), true);
        $policy = is_array($extraData) && is_array($extraData[self::POLICY_KEY] ?? null)
            ? $extraData[self::POLICY_KEY]
            : [];
        foreach (['uses_project', 'requires_project', 'uses_work_team', 'requires_work_team'] as $key) {
            if (!array_key_exists($key, $policy)) {
                throw new \RuntimeException('사업구분의 일용근로소득 적용정책을 확인할 수 없습니다.');
            }
            $policy[$key] = filter_var($policy[$key], FILTER_VALIDATE_BOOL);
        }
        if ($policy['requires_project'] && !$policy['uses_project']) {
            throw new \RuntimeException('사업구분의 프로젝트 적용정책이 올바르지 않습니다.');
        }
        if ($policy['requires_work_team'] && !$policy['uses_work_team']) {
            throw new \RuntimeException('사업구분의 작업팀 적용정책이 올바르지 않습니다.');
        }
        return ['id'=>(string)$row['code'],'name'=>(string)$row['code_name'],'sort_no'=>(int)$row['sort_no']] + $policy;
    }

    public function technicalWorkScope(array $policy, ?string $projectId): string
    {
        return !empty($policy['uses_project']) && $projectId !== null ? 'PROJECT' : 'HEAD_OFFICE';
    }

    /**
     * 근로자 Master 유형이 아니라 계산 당시 사업구분 정책으로 법정 가입자격 Scope를 파생한다.
     */
    public function eligibilityScope(array $policy, ?string $projectId): array
    {
        $businessUnit = strtoupper(trim((string) ($policy['id'] ?? '')));
        if ($businessUnit === '') {
            throw new \RuntimeException('가입자격 판정에 필요한 사업구분을 확인할 수 없습니다.');
        }

        $construction = !empty($policy['requires_project']);
        if ($construction && $projectId === null) {
            return [
                'employment_type_code' => 'DAILY',
                'business_unit_code' => $businessUnit,
                'eligibility_work_scope_code' => null,
                'project_required' => true,
                'insurance_workplace_required' => false,
                'derivation_reason_code' => 'BUSINESS_UNIT_PROJECT_REQUIRED',
                'missing_inputs' => [[
                    'field' => 'project_id',
                    'code' => 'PROJECT_REQUIRED_FOR_BUSINESS_UNIT',
                ]],
            ];
        }

        $reasonCode = match ($businessUnit) {
            'HQ' => 'HQ_NON_CONSTRUCTION_POLICY',
            'ECOMMERCE' => 'ECOMMERCE_NON_CONSTRUCTION_POLICY',
            'CONSTRUCTION' => 'CONSTRUCTION_SITE_POLICY',
            default => $construction
                ? 'PROJECT_REQUIRED_BUSINESS_UNIT_POLICY'
                : 'PROJECT_NOT_USED_BUSINESS_UNIT_POLICY',
        };

        return [
            'employment_type_code' => 'DAILY',
            'business_unit_code' => $businessUnit,
            'eligibility_work_scope_code' => $construction ? 'CONSTRUCTION_SITE' : 'HEAD_OFFICE',
            'project_required' => $construction,
            'insurance_workplace_required' => false,
            'derivation_reason_code' => $reasonCode,
            'missing_inputs' => [],
        ];
    }
}
