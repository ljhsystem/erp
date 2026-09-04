<?php

declare(strict_types=1);

namespace App\Services\Institution;

final class DailyEmploymentIncomeCalculationSourceService
{
    public function hash(array $source): string
    {
        return hash('sha256', json_encode(
            $this->canonicalize($source),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
    }

    public function canonicalize(array $source): array
    {
        $groups = array_map(fn(array $group): array => $this->group($group), $source['groups'] ?? []);
        return [
            'daily_employment_income_id' => $this->text($source['daily_employment_income_id'] ?? null),
            'income_year_month' => $this->text($source['income_year_month'] ?? null),
            'payment_sequence' => max(1, (int) ($source['payment_sequence'] ?? 1)),
            'calculation_policy_version' => $this->text($source['calculation_policy_version'] ?? null),
            'groups' => $groups,
        ];
    }

    private function group(array $group): array
    {
        $items = array_map(fn(array $item): array => $this->item($item), $group['items'] ?? []);
        return [
            'business_unit' => $this->text($group['business_unit'] ?? null),
            'project_id' => $this->text($group['project_id'] ?? null),
            'work_team_id' => $this->text($group['work_team_id'] ?? null),
            'employment_insurance_application_status_code' => $this->text($group['employment_insurance_application_status_code'] ?? null),
            'employment_insurance_decision_reason' => $this->text($group['employment_insurance_decision_reason'] ?? null),
            'employment_insurance_decision_source_code_id' => $this->text($group['employment_insurance_decision_source_code_id'] ?? null),
            'industrial_accident_application_status_code' => $this->text($group['industrial_accident_application_status_code'] ?? null),
            'industrial_accident_decision_reason' => $this->text($group['industrial_accident_decision_reason'] ?? null),
            'industrial_accident_decision_source_code_id' => $this->text($group['industrial_accident_decision_source_code_id'] ?? null),
            'items' => $items,
        ];
    }

    private function item(array $item): array
    {
        $workdays = array_map(fn(array $workday): array => $this->workday($workday), $item['workdays'] ?? []);
        usort($workdays, static fn(array $left, array $right): int => $left['work_date'] <=> $right['work_date']);

        $insuranceResults = [];
        foreach ((array)($item['lines'] ?? []) as $line) {
            if (!in_array(($line['line_type_code'] ?? ''), ['DEDUCTION', 'EMPLOYER_BURDEN'], true)) continue;
            if (!in_array(($line['line_code'] ?? ''), [
                'NATIONAL_PENSION', 'HEALTH_INSURANCE', 'LONG_TERM_CARE',
                'EMPLOYMENT_INSURANCE', 'EMPLOYMENT_INSURANCE_VOCATIONAL', 'INDUSTRIAL_ACCIDENT_INSURANCE',
            ], true)) continue;
            $eligibility = (array)($line['eligibility_result'] ?? []);
            $insuranceResults[] = [
                'line_type_code' => $this->text($line['line_type_code'] ?? null),
                'insurance_type_code' => $this->text($line['line_code'] ?? null),
                'eligibility_status_code' => $this->text($eligibility['status'] ?? null),
                'eligibility_reason_code' => $this->text($eligibility['reason_code'] ?? null),
                'decision_source_code' => $this->text($eligibility['decision_source_code'] ?? null),
                'manual_setting_reason' => $this->text($eligibility['manual_setting_reason'] ?? null),
                'company_burden_status_code' => $this->text($eligibility['company_burden_status_code'] ?? null),
                'burden_source_code' => $this->text($eligibility['burden_source_code'] ?? null),
                'eligibility_revision_id' => $this->text($eligibility['eligibility_revision_id'] ?? null),
                'premium_revision_id' => $this->text($line['statutory_standard_id'] ?? null),
                'automatic_amount' => ($line['calculated_amount'] ?? null) === null ? null : $this->amount($line['calculated_amount']),
                'final_amount' => ($line['final_amount'] ?? null) === null ? null : $this->amount($line['final_amount']),
                'adjustment_reason' => $this->text($line['adjustment_reason'] ?? null),
            ];
        }
        usort($insuranceResults, static fn(array $left, array $right): int => [
            $left['insurance_type_code'], $left['line_type_code'],
        ] <=> [
            $right['insurance_type_code'], $right['line_type_code'],
        ]);

        return [
            'worker_client_id' => $this->text($item['worker_client_id'] ?? null),
            'work_type_code' => $this->text($item['work_type_code'] ?? null),
            'work_description' => $this->text($item['work_description'] ?? null),
            'workdays' => $workdays,
            'insurance_results' => $insuranceResults,
        ];
    }

    private function workday(array $workday): array
    {
        $lines = array_values(array_filter(
            $workday['lines'] ?? [],
            static fn(array $line): bool => ($line['taxability_code'] ?? null) !== 'NON_TAXABLE'
                || ($line['revision_status_code'] ?? null) === 'CONFIRMED'
        ));
        $lines = array_map(fn(array $line): array => [
            'line_type_code' => $this->text($line['line_type_code'] ?? null),
            'line_code' => $this->text($line['line_code'] ?? null),
            'taxability_code' => $this->text($line['taxability_code'] ?? null),
            'final_amount' => $this->amount($line['final_amount'] ?? 0),
            'statutory_standard_id' => $this->text($line['statutory_standard_id'] ?? null),
            'non_taxable_revision_id' => $this->text($line['non_taxable_revision_id'] ?? null),
        ], $lines);
        usort($lines, static fn(array $left, array $right): int => [
            $left['line_type_code'], $left['line_code'], $left['non_taxable_revision_id'],
        ] <=> [
            $right['line_type_code'], $right['line_code'], $right['non_taxable_revision_id'],
        ]);

        return [
            'work_date' => $this->text($workday['work_date'] ?? null),
            'actual_work_minutes' => !array_key_exists('actual_work_minutes', $workday)
                || $workday['actual_work_minutes'] === null ? null : (int) $workday['actual_work_minutes'],
            'daily_rate_amount' => $this->amount($workday['daily_rate_amount'] ?? 0),
            'taxable_additional_amount' => $this->amount($workday['taxable_additional_amount'] ?? $workday['allowance_amount'] ?? 0),
            'non_taxable_additional_amount' => $this->amount($workday['non_taxable_additional_amount'] ?? $workday['non_taxable_amount'] ?? 0),
            'non_taxable_reason' => $this->text($workday['non_taxable_reason'] ?? null),
            'social_insurance_workplace_id' => $this->text($workday['social_insurance_workplace_id'] ?? null),
            'insurance_resolver_revision' => $this->text($workday['insurance_resolver_revision'] ?? null),
            'lines' => $lines,
        ];
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function amount(mixed $value): string
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('기관계산 원천금액을 확인해 주세요.');
        }
        return number_format((float) $value, 2, '.', '');
    }
}
