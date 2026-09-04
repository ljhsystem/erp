<?php

declare(strict_types=1);

namespace App\Services\Institution;

use Core\Helpers\ActorHelper;
use PDO;

final class DailyEmploymentIncomeGroupInsurancePolicyService
{
    public const MANUAL_SOURCE_CODE = 'DAILY_GROUP_MANUAL_SETTING';
    public const BUSINESS_POLICY_SOURCE_CODE = 'BUSINESS_DIVISION_POLICY';
    private const STORED_SOURCE_CODE = 'MANUAL_INTERIM_GROUP';
    private const STORED_BUSINESS_POLICY_SOURCE_CODE = 'AUTO_APPLIED';

    private IncomeCalculationCodeService $incomeCodes;

    public function __construct(PDO $db)
    {
        $this->incomeCodes = new IncomeCalculationCodeService($db);
    }

    public function normalize(array $group, bool $requireComplete): array
    {
        $businessUnit = strtoupper(trim((string) ($group['business_unit'] ?? '')));
        $automatic = in_array($businessUnit, ['HQ', 'ECOMMERCE'], true);
        $result = [];
        foreach ($this->definitions() as $prefix => $definition) {
            $status = $automatic ? 'APPLICABLE' : strtoupper(trim((string) ($group[$prefix . '_application_status_code'] ?? '')));
            $reason = $status === 'EXCLUDED' ? $definition['name'] . ' 우리 회사 미부담' : null;
            if ($status === '') {
                if ($requireComplete) {
                    throw new \InvalidArgumentException($definition['name'] . ' 회사부담 여부를 선택해 주세요.');
                }
                $result[$prefix . '_application_status_code'] = null;
                $result[$prefix . '_decision_reason'] = null;
                $result[$prefix . '_decision_source_code_id'] = null;
                $result[$prefix . '_decision_source_code'] = null;
                $result[$prefix . '_set_by'] = null;
                $result[$prefix . '_set_at'] = null;
                continue;
            }
            if (!in_array($status, ['APPLICABLE', 'EXCLUDED'], true)) {
                throw new \InvalidArgumentException($definition['name'] . ' 적용여부를 확인해 주세요.');
            }
            $sourceCode = strtoupper(trim((string) ($group[$prefix . '_decision_source_code'] ?? '')));
            $allowedSources = [self::MANUAL_SOURCE_CODE, 'GROUP_MANUAL_SETTING', self::STORED_SOURCE_CODE, self::BUSINESS_POLICY_SOURCE_CODE, self::STORED_BUSINESS_POLICY_SOURCE_CODE];
            if ($sourceCode !== '' && !in_array($sourceCode, $allowedSources, true)) {
                throw new \InvalidArgumentException($definition['name'] . ' 설정원천을 확인해 주세요.');
            }
            $sourceId = trim((string) ($group[$prefix . '_decision_source_code_id'] ?? '')) ?: null;
            $storedSourceCode = $automatic ? self::STORED_BUSINESS_POLICY_SOURCE_CODE : self::STORED_SOURCE_CODE;
            if ($automatic || $sourceId === null || $sourceCode !== '') $sourceId = $this->incomeCodes->id(IncomeCalculationCodeService::ACTUAL_SOURCE_GROUP, $storedSourceCode);
            $this->incomeCodes->assertIdInGroup($sourceId, IncomeCalculationCodeService::ACTUAL_SOURCE_GROUP);
            $result[$prefix . '_application_status_code'] = $status;
            $result[$prefix . '_decision_reason'] = $reason;
            $result[$prefix . '_decision_source_code_id'] = $sourceId;
            $result[$prefix . '_decision_source_code'] = $automatic ? self::BUSINESS_POLICY_SOURCE_CODE : self::MANUAL_SOURCE_CODE;
            $result[$prefix . '_set_by'] = trim((string) ($group[$prefix . '_set_by'] ?? $group['updated_by'] ?? '')) ?: null;
            $result[$prefix . '_set_at'] = trim((string) ($group[$prefix . '_set_at'] ?? $group['updated_at'] ?? '')) ?: null;
        }
        return $result;
    }

    public function companyBurdenResult(array $item, string $prefix): array
    {
        $definition = $this->definitions()[$prefix] ?? null;
        if ($definition === null) {
            throw new \InvalidArgumentException('지원하지 않는 Group 보험 수동설정입니다.');
        }
        $status = strtoupper(trim((string) ($item[$prefix . '_application_status_code'] ?? '')));
        $reason = trim((string) ($item[$prefix . '_decision_reason'] ?? ''));
        $setBy = trim((string) ($item[$prefix . '_set_by'] ?? $item['updated_by'] ?? '')) ?: ActorHelper::user();
        $setAt = trim((string) ($item[$prefix . '_set_at'] ?? $item['updated_at'] ?? '')) ?: date('Y-m-d H:i:s');
        if ($status === '') {
            return [
                'status' => 'CONFIRMATION_REQUIRED',
                'result_code' => 'CONFIRMATION_REQUIRED',
                'reason_code' => 'COMPANY_BURDEN_SETTING_REQUIRED',
                'reason_name' => $definition['name'] . ' 회사부담 여부 미선택',
                'reason_detail' => $definition['name'] . ' 회사부담 여부를 선택해 주세요.',
                'missing_inputs' => [[
                    'field' => $prefix . '_application_status_code',
                    'code' => strtoupper($prefix) . '_APPLICATION_STATUS_REQUIRED',
                ]],
                'eligibility_revision_id' => null,
                'premium_revision_id' => null,
                'decision_source_code' => null,
                'manual_setting_reason' => null,
                'set_by' => null,
                'set_at' => null,
                'component_results' => [],
            ];
        }
        $eligible = $status === 'APPLICABLE';
        $resultStatus = $eligible ? 'APPLICABLE' : 'EXCLUDED';
        $sourceCode = strtoupper(trim((string) ($item[$prefix . '_decision_source_code'] ?? '')));
        $businessPolicy = $sourceCode === self::BUSINESS_POLICY_SOURCE_CODE;
        $decisionSource = $businessPolicy ? self::BUSINESS_POLICY_SOURCE_CODE : self::MANUAL_SOURCE_CODE;
        $basisName = $businessPolicy ? '사업구분 기본정책' : '일용근로 Group 수동설정';
        $detail = $eligible ? '우리 회사 부담으로 설정되었습니다.' : '우리 회사가 부담하지 않도록 설정되었습니다.';
        $components = array_map(static fn(array $component): array => [
            'component_code' => $component['code'],
            'component_name' => $component['name'],
            'status_code' => $eligible ? 'APPLICABLE' : 'EXCLUDED',
            'reason_code' => null,
            'reason_name' => $basisName,
            'reason_detail' => $detail,
            'decision_source_code' => $decisionSource,
        ], $definition['components']);
        return [
            'status' => $resultStatus,
            'result_code' => $resultStatus,
            'reason_code' => null,
            'reason_name' => $eligible ? $basisName : $reason,
            'reason_detail' => $detail,
            'decision_basis_code' => $eligible ? $decisionSource : null,
            'decision_basis_name' => $eligible ? $basisName : null,
            'decision_basis_detail' => $eligible ? $detail : null,
            'missing_inputs' => [],
            'eligibility_revision_id' => null,
            'premium_revision_id' => null,
            'decision_source_code' => $decisionSource,
            'decision_source_name' => $basisName,
            'manual_setting_reason' => null,
            'company_burden_status_code' => $status,
            'company_burden_name' => $eligible ? '우리 회사 부담' : '우리 회사 미부담',
            'burden_source_code' => $decisionSource,
            'burden_source_name' => $basisName,
            'set_by' => $setBy,
            'set_by_name' => ActorHelper::displayName($setBy),
            'set_at' => $setAt,
            'component_results' => $components,
            'passed_conditions' => $eligible ? $components : [],
            'manual_application_status_code' => $status,
        ];
    }

    private function definitions(): array
    {
        return [
            'employment_insurance' => [
                'name' => '고용보험',
                'components' => [
                    ['code' => 'UNEMPLOYMENT_BENEFIT', 'name' => '고용보험 실업급여'],
                    ['code' => 'EMPLOYMENT_STABILITY_VOCATIONAL', 'name' => '고용안정·직업능력개발'],
                ],
            ],
            'industrial_accident' => [
                'name' => '산재보험',
                'components' => [
                    ['code' => 'INDUSTRIAL_ACCIDENT', 'name' => '산재보험'],
                ],
            ],
        ];
    }
}
