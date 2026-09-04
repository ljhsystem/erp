<?php

namespace App\Services\Institution;

final class EmploymentIncomeTaxTableService
{
    public function calculate(float $taxableSalary, int|string $dependentCount, array $valueData): array
    {
        $dependents = filter_var($dependentCount, FILTER_VALIDATE_INT);
        if ($dependents === false || $dependents < 1) {
            throw new \InvalidArgumentException('공제대상 가족수는 1명 이상이어야 합니다.');
        }
        $table = $valueData['table'] ?? null;
        if (!is_array($table)) {
            throw new \RuntimeException('간이세액표 데이터가 없습니다.');
        }
        $supported = [];
        foreach ((array) ($table['dependent_counts'] ?? []) as $value) {
            if (filter_var($value, FILTER_VALIDATE_INT) !== false) {
                $supported[(int) $value] = (string) (int) $value;
            }
        }
        if (!isset($supported[$dependents])) {
            throw new \InvalidArgumentException('해당 간이세액표는 공제대상 가족수 ' . $dependents . '명을 지원하지 않습니다.');
        }

        foreach ((array) ($table['rows'] ?? []) as $row) {
            $from = (float) ($row['salary_from'] ?? 0);
            $toValue = $row['salary_to'] ?? null;
            $to = $toValue === null || $toValue === '' ? null : (float) $toValue;
            if ($taxableSalary < $from || ($to !== null && $taxableSalary >= $to)) {
                continue;
            }
            $columnKey = $supported[$dependents];
            $taxColumns = is_array($row['tax_by_dependents'] ?? null) ? $row['tax_by_dependents'] : [];
            if (!array_key_exists($columnKey, $taxColumns)) {
                throw new \RuntimeException('선택된 급여구간에 가족수 ' . $dependents . '명 세액이 없습니다.');
            }
            $tableTax = round((float) $taxColumns[$columnKey], 2);
            $tax = $tableTax;
            $policy = $valueData['calculation_policy'] ?? [];
            if (($policy['threshold_comparison'] ?? '') === 'LESS_THAN' && $tax < (float) ($policy['threshold'] ?? 0)) {
                $tax = 0.0;
            }
            return [
                'tax_amount' => $tax,
                'table_tax_amount' => $tableTax,
                'taxable_salary_amount' => round($taxableSalary, 2),
                'salary_from' => $from,
                'salary_to' => $to,
                'dependent_count' => $dependents,
                'dependent_column_key' => $columnKey,
                'supported_dependent_counts' => array_keys($supported),
                'threshold' => isset($policy['threshold']) ? (float) $policy['threshold'] : null,
                'threshold_applied' => $tax !== $tableTax,
            ];
        }
        throw new \RuntimeException('과세대상 월급여액에 적용할 간이세액표 급여구간이 없습니다.');
    }
}
