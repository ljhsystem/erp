<?php

declare(strict_types=1);

namespace App\Services\Institution;

final class RegularEmploymentIncomeInsuranceProjectionService
{
    private const INSURANCE_CODES = [
        'NATIONAL_PENSION' => '국민연금',
        'HEALTH_INSURANCE' => '건강보험',
        'LONG_TERM_CARE' => '장기요양보험',
        'LONG_TERM_CARE_INSURANCE' => '장기요양보험',
        'EMPLOYMENT_INSURANCE' => '고용보험',
        'EMPLOYMENT_INSURANCE_VOCATIONAL' => '고용안정·직업능력개발',
        'INDUSTRIAL_ACCIDENT_INSURANCE' => '산재보험',
    ];

    public function project(array $lines, ?array $contract, array $coverageRows, ?string $calculatedAt = null): array
    {
        $coverages = [];
        foreach ($coverageRows as $coverage) {
            $type = trim((string) ($coverage['insurance_type_code'] ?? ''));
            if ($type !== '') $coverages[$type][] = $coverage;
        }

        foreach ($lines as &$line) {
            $lineCode = (string) ($line['item_code'] ?? '');
            if (!isset(self::INSURANCE_CODES[$lineCode])) continue;
            $insuranceCode = $this->canonicalInsuranceCode($lineCode);
            $coverage = count($coverages[$insuranceCode] ?? []) === 1 ? $coverages[$insuranceCode][0] : null;
            $line['eligibility_projection'] = $this->projection(
                $line,
                $lineCode,
                $insuranceCode,
                self::INSURANCE_CODES[$lineCode],
                $contract,
                $coverage,
                $calculatedAt
            );
        }
        unset($line);
        return $lines;
    }

    private function projection(
        array $line,
        string $lineCode,
        string $insuranceCode,
        string $insuranceName,
        ?array $contract,
        ?array $coverage,
        ?string $calculatedAt
    ): array {
        $status = strtoupper(trim((string) ($line['application_status_code'] ?? '')));
        $status = $status !== '' ? $status : 'CONFIRMATION_REQUIRED';
        $reason = trim((string) ($line['calculation_message'] ?? $line['business_reason'] ?? ''));
        $projection = [
            'insurance_type_code' => $insuranceCode,
            'insurance_type_name' => $insuranceName,
            'application_status_code' => $status,
            'eligibility_status_code' => null,
            'eligibility_revision_id' => null,
            'premium_revision_id' => $line['statutory_standard_id'] ?? null,
            'calculated_at' => $calculatedAt,
        ];

        $businessDivisionBurden = $contract !== null
            && trim((string) ($contract['project_id'] ?? '')) === ''
            && $status === 'APPLICABLE'
            && in_array($insuranceCode, ['EMPLOYMENT_INSURANCE', 'INDUSTRIAL_ACCIDENT_INSURANCE'], true);
        if ($businessDivisionBurden) {
            return $projection + [
                'decision_source_code' => 'BUSINESS_DIVISION_POLICY',
                'decision_source_name' => '사업구분 기본정책',
                'decision_basis_name' => '본사·통신판매 사업구분은 우리 회사가 부담합니다.',
                'company_burden_status_code' => 'APPLICABLE',
                'company_burden_name' => '우리 회사 부담',
                'burden_source_code' => 'BUSINESS_DIVISION_POLICY',
                'burden_source_name' => '사업구분 기본정책',
            ];
        }

        $contractSetting = $this->contractSetting($insuranceCode, $contract);
        if ($contractSetting !== null) {
            $settingReason = trim((string) ($contractSetting['reason'] ?? ''));
            $basis = $settingReason !== '' ? $settingReason : ($status === 'EXCLUDED'
                ? '근로계약에서 적용 제외로 설정되었습니다.'
                : '근로계약에서 적용으로 설정되었습니다.');
            return $projection + [
                'decision_source_code' => 'EMPLOYMENT_CONTRACT_SETTING',
                'decision_source_name' => '근로계약 설정',
                'decision_basis_name' => $status === 'APPLICABLE' ? $basis : null,
                'reason_name' => $status === 'EXCLUDED' ? $basis : null,
                'manual_setting_reason' => $basis,
                'effective_start_date' => $contract['contract_start_date'] ?? null,
                'effective_end_date' => $contract['contract_end_date'] ?? null,
            ];
        }

        if ($coverage !== null && !empty($coverage['confirmed_at'])) {
            $coverageStatus = strtoupper(trim((string) ($coverage['coverage_status_code'] ?? '')));
            $coverageReason = trim((string) ($coverage['exclusion_reason'] ?? $coverage['note'] ?? ''));
            $basis = $coverageReason !== '' ? $coverageReason : ($coverageStatus === 'ACQUIRED'
                ? '확정 가입정보에서 가입 상태가 확인되었습니다.'
                : '확정 가입정보에서 적용 제외 상태가 확인되었습니다.');
            return $projection + [
                'decision_source_code' => 'COVERAGE_RECORD',
                'decision_source_name' => '가입정보',
                'decision_basis_name' => $status === 'APPLICABLE' ? $basis : null,
                'reason_name' => $status === 'EXCLUDED' ? $basis : null,
                'reason_detail' => trim((string) ($coverage['note'] ?? '')) ?: null,
                'coverage_status_code' => $coverageStatus,
                'coverage_status_name' => $coverageStatus === 'ACQUIRED' ? '가입' : ($coverageStatus === 'EXCLUDED' ? '제외' : '확인 필요'),
                'effective_start_date' => $coverage['effective_from'] ?? null,
                'effective_end_date' => $coverage['effective_to'] ?? null,
            ];
        }

        if ($lineCode === 'LONG_TERM_CARE' || $lineCode === 'LONG_TERM_CARE_INSURANCE') {
            return $projection + [
                'decision_source_code' => 'DEPENDENT_INSURANCE_RESULT',
                'decision_source_name' => '건강보험 계산결과 종속',
                'decision_basis_name' => $status === 'APPLICABLE' ? '건강보험 적용 결과에 따라 장기요양보험료를 계산했습니다.' : null,
                'reason_name' => $status === 'EXCLUDED' ? ($reason ?: '건강보험 적용 제외 결과에 따라 제외되었습니다.') : null,
            ];
        }

        if ($status === 'APPLICABLE' || $status === 'EXCLUDED') {
            $basis = $reason !== '' ? $reason : ($status === 'APPLICABLE'
                ? '저장된 상용근로소득 계산결과에서 보험료 적용이 확정되었습니다.'
                : '저장된 상용근로소득 계산결과에서 적용 제외가 확정되었습니다.');
            return $projection + [
                'decision_source_code' => 'CALCULATION_SNAPSHOT',
                'decision_source_name' => '상용근로소득 계산 Snapshot',
                'decision_basis_name' => $status === 'APPLICABLE' ? $basis : null,
                'reason_name' => $status === 'EXCLUDED' ? $basis : null,
            ];
        }

        return $projection + [
            'decision_source_code' => null,
            'decision_source_name' => null,
            'reason_name' => $reason !== '' ? $reason : '보험 적용상태를 확인할 자료가 없습니다.',
        ];
    }

    private function contractSetting(string $insuranceCode, ?array $contract): ?array
    {
        if ($contract === null) return null;
        $fields = match ($insuranceCode) {
            'EMPLOYMENT_INSURANCE' => ['employment_insurance_application_status_code', 'employment_insurance_exclusion_reason'],
            'INDUSTRIAL_ACCIDENT_INSURANCE' => ['industrial_accident_application_status_code', 'industrial_accident_exclusion_reason'],
            default => null,
        };
        if ($fields === null || trim((string) ($contract[$fields[0]] ?? '')) === '') return null;
        return ['status' => $contract[$fields[0]], 'reason' => $contract[$fields[1]] ?? null];
    }

    private function canonicalInsuranceCode(string $lineCode): string
    {
        return match ($lineCode) {
            'LONG_TERM_CARE_INSURANCE' => 'LONG_TERM_CARE',
            'EMPLOYMENT_INSURANCE_VOCATIONAL' => 'EMPLOYMENT_INSURANCE',
            default => $lineCode,
        };
    }
}
