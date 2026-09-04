<?php

namespace App\Services\Institution;

final class RegularEmploymentIncomeLineSnapshotValidationService
{
    private const COVERAGE_REQUIRED_CODES = [
        'NATIONAL_PENSION',
        'HEALTH_INSURANCE',
        'LONG_TERM_CARE',
        'LONG_TERM_CARE_INSURANCE',
        'EMPLOYMENT_INSURANCE',
        'EMPLOYMENT_INSURANCE_VOCATIONAL',
    ];

    public function validateEmployerBurden(array $line): void
    {
        $code = strtoupper(trim((string) ($line['item_code'] ?? '')));
        $status = strtoupper(trim((string) ($line['application_status_code'] ?? '')));

        if ($status === 'EXCLUDED') {
            if (!$this->hasNumericValue($line, 'final_amount') || round((float) $line['final_amount'], 2) !== 0.0) {
                $this->fail('적용 제외 사용자부담 실제 적용액은 0원이어야 합니다.');
            }
            if ($this->reason($line) === '') {
                $this->fail('적용 제외 사용자부담의 미적용 사유가 없습니다.');
            }
            return;
        }

        if ($status !== 'APPLICABLE') {
            $this->fail('사용자부담 적용상태가 미확정입니다.');
        }

        if (!$this->hasNumericValue($line, 'calculation_basis_amount')) {
            $this->fail('적용 사용자부담 계산기초가 누락되었습니다.');
        }
        if (trim((string) ($line['statutory_standard_id'] ?? '')) === '') {
            $this->fail('사용자부담 법정기준이 누락되었습니다.');
        }
        foreach ([
            'calculation_rate' => '사용자부담 계산요율이 누락되었습니다.',
            'calculation_before_rounding' => '사용자부담 계산 전 금액이 누락되었습니다.',
            'rounding_unit' => '사용자부담 끝수처리 단위가 누락되었습니다.',
            'calculated_amount' => '사용자부담 자동계산액이 누락되었습니다.',
            'final_amount' => '사용자부담 실제 적용액이 누락되었습니다.',
        ] as $field => $message) {
            if (!$this->hasNumericValue($line, $field)) $this->fail($message);
        }
        if (trim((string) ($line['rounding_method_code'] ?? '')) === '') {
            $this->fail('사용자부담 끝수처리 방법이 누락되었습니다.');
        }
        if (trim((string) ($line['calculation_source_code'] ?? '')) === '') {
            $this->fail('사용자부담 계산원천이 누락되었습니다.');
        }
        if (trim((string) ($line['business_source_code'] ?? '')) === '') {
            $this->fail('사용자부담 실제 적용원천이 누락되었습니다.');
        }
        if (trim((string) ($line['processed_at'] ?? '')) === '' || trim((string) ($line['processed_by'] ?? '')) === '') {
            $this->fail('사용자부담 처리자 또는 처리시각이 누락되었습니다.');
        }

        if (in_array($code, self::COVERAGE_REQUIRED_CODES, true)
            && trim((string) ($line['social_insurance_coverage_id'] ?? '')) === '') {
            $this->fail('적용 사용자부담 Coverage가 누락되었습니다.');
        }
        if ($code === 'EMPLOYMENT_INSURANCE_VOCATIONAL'
            && trim((string) ($line['workplace_size_period_id'] ?? '')) === '') {
            $this->fail('직업능력개발 회사규모가 누락되었습니다.');
        }
        if ($code === 'INDUSTRIAL_ACCIDENT_INSURANCE') {
            $this->fail('산재보험 공식 Scope가 미확정입니다.');
        }
    }

    private function hasNumericValue(array $line, string $field): bool
    {
        return array_key_exists($field, $line) && $line[$field] !== null && $line[$field] !== '' && is_numeric($line[$field]);
    }

    private function reason(array $line): string
    {
        foreach (['adjustment_reason', 'business_reason', 'calculation_message'] as $field) {
            $reason = trim((string) ($line[$field] ?? ''));
            if ($reason !== '') return $reason;
        }
        return '';
    }

    private function fail(string $message): never
    {
        throw new RegularEmploymentIncomeAccountingException('PAYROLL_SOURCE_INCOMPLETE', $message);
    }
}
