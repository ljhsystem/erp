<?php

declare(strict_types=1);

namespace App\Services\System;

final class StatutoryStandardValueSummaryService
{
    public const FORMAT_AMOUNT = 'AMOUNT';
    public const FORMAT_RATE = 'RATE';
    public const FORMAT_MATRIX = 'MATRIX';
    public const FORMAT_ELIGIBILITY = 'ELIGIBILITY_SUMMARY';
    public const FORMAT_TEXT = 'TEXT';

    /** @return array{value_summary:string,value_summary_formatter_code:string} */
    public function project(array $standard, array $values, ?array $firstField): array
    {
        if (($standard['policy_component_code'] ?? null) === 'ELIGIBILITY') {
            return [
                'value_summary' => $this->eligibilitySummary($standard, $values),
                'value_summary_formatter_code' => self::FORMAT_ELIGIBILITY,
            ];
        }

        return $this->firstFieldSummary($values, $firstField);
    }

    private function eligibilitySummary(array $standard, array $values): string
    {
        if (($values['decision_code'] ?? null) === 'DEPENDENT_RESULT'
            && ($values['dependent_insurance_type_code'] ?? null) === 'HEALTH_INSURANCE') {
            return '건강보험 가입결과 종속';
        }

        $employmentType = (string) ($standard['employment_type_code'] ?? $values['employment_type_code'] ?? '');
        $workScope = (string) ($standard['work_scope_code'] ?? $values['work_scope_code'] ?? '');

        return match ([$employmentType, $workScope]) {
            ['REGULAR', 'HEAD_OFFICE'] => '상용 · 본사 가입자격',
            ['DAILY', 'HEAD_OFFICE'] => '일반 일용 · 본사 가입자격',
            ['DAILY', 'CONSTRUCTION_SITE'] => '건설 일용 · 현장 가입자격',
            default => '가입자격 기준',
        };
    }

    /** @return array{value_summary:string,value_summary_formatter_code:string} */
    private function firstFieldSummary(array $values, ?array $field): array
    {
        $fieldCode = (string) ($field['code'] ?? '');
        $value = $fieldCode !== '' ? ($values[$fieldCode] ?? null) : null;
        if ($value === null || $value === '') {
            return ['value_summary' => '-', 'value_summary_formatter_code' => self::FORMAT_TEXT];
        }

        $type = strtolower((string) ($field['type'] ?? ''));
        if (in_array($type, ['matrix', 'bracket'], true)) {
            $rowsKey = (string) ($field['object_storage']['rows_key'] ?? 'rows');
            $rows = is_array($value) && array_is_list($value)
                ? $value
                : (is_array($value) && is_array($value[$rowsKey] ?? null) ? $value[$rowsKey] : []);
            $name = trim((string) ($field['name'] ?? '')) ?: ($type === 'bracket' ? '구간' : '기준표');
            return ['value_summary' => $name . ' ' . count($rows) . '건', 'value_summary_formatter_code' => self::FORMAT_MATRIX];
        }
        if ($type === 'rate' && is_numeric($value)) {
            return [
                'value_summary' => rtrim(rtrim(number_format((float) $value * 100, 6, '.', ','), '0'), '.') . '%',
                'value_summary_formatter_code' => self::FORMAT_RATE,
            ];
        }
        if ($type === 'amount' && is_numeric($value)) {
            return ['value_summary' => number_format((float) $value, 0) . '원', 'value_summary_formatter_code' => self::FORMAT_AMOUNT];
        }
        if ($type === 'number' && is_numeric($value)) {
            return [
                'value_summary' => rtrim(rtrim(number_format((float) $value, 6, '.', ','), '0'), '.'),
                'value_summary_formatter_code' => self::FORMAT_TEXT,
            ];
        }
        if (is_bool($value)) {
            return ['value_summary' => $value ? '예' : '아니오', 'value_summary_formatter_code' => self::FORMAT_TEXT];
        }
        return [
            'value_summary' => is_scalar($value) ? (string) $value : '-',
            'value_summary_formatter_code' => self::FORMAT_TEXT,
        ];
    }
}
