<?php

declare(strict_types=1);

namespace App\Services\Institution;

final class IncomeCalculationModeProjectionService
{
    public static function automatic(array $line, array $context = []): array
    {
        return [
            'display_type_code' => 'CALCULATION_MODE',
            'item_name' => (string) ($context['item_name'] ?? $line['line_name_snapshot'] ?? ''),
            'calculation_method_name' => '법정기준 자동계산',
            'calculation_basis_name' => (string) ($context['calculation_basis_name'] ?? '적용 법정기준에 따른 자동 산정'),
            'standard_name' => (string) ($context['standard_name'] ?? ''),
            'effective_from' => $line['standard_effective_from'] ?? $line['tax_table_effective_from'] ?? null,
            'effective_to' => $line['standard_effective_to'] ?? $line['tax_table_effective_to'] ?? null,
            'basis_amount' => $line['calculation_basis_amount'] ?? null,
            'rate' => $line['calculation_rate'] ?? null,
            'rounding_method_code' => $line['rounding_method_code'] ?? null,
            'rounding_unit' => $line['rounding_unit'] ?? null,
            'calculated_amount' => $line['calculated_amount'] ?? $line['final_amount'] ?? null,
            'detail' => (string) ($context['detail'] ?? ''),
        ];
    }
}
