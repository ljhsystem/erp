<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$calculation = (string) file_get_contents($root . '/app/Services/Institution/RegularEmploymentIncomeCalculationService.php');
$service = (string) file_get_contents($root . '/app/Services/Institution/RegularEmploymentIncomeService.php');
$javascript = (string) file_get_contents($root . '/public/assets/js/pages/institution/regular-employment-income/index.js');
$commonCards = (string) file_get_contents($root . '/public/assets/js/common/income-calculation-cards.js');

$checks = [
    'calculation_version_fits_schema' => str_contains($calculation, "public const VERSION = 'REGULAR_INCOME_V4_CARDS';")
        && strlen('REGULAR_INCOME_V4_CARDS') <= 30,
    'item_level_blocked_lines' => str_contains($calculation, 'blockDeduction')
        && str_contains($calculation, "'calculation_status_code' => 'NEEDS_CONFIRMATION'"),
    'independent_insurance_items' => str_contains($calculation, "foreach (['NATIONAL_PENSION', 'HEALTH_INSURANCE', 'EMPLOYMENT_INSURANCE']"),
    'long_term_care_dependency_visible' => str_contains($calculation, '기초가 되는 건강보험료가 미확정입니다.'),
    'employment_policy_driven_calculation' => str_contains($calculation, "\$policyBaseCode === 'INSURABLE_REMUNERATION'")
        && str_contains($calculation, "\$base = \$taxable")
        && str_contains($calculation, "\$this->finalizePremium(\$beforeRounding, \$policy, \$standard['value_data'])")
        && str_contains($commonCards, "TRUNCATE: '절사'"),
    'income_tax_not_faked_as_zero' => str_contains($calculation, '공제대상 가족수 Snapshot을 입력해 주세요.'),
    'historical_reference_dates_split' => str_contains($calculation, 'in_array($type, $insuranceTypes, true) ? $insuranceDate : $paymentDate'),
    'historical_coverage_message' => !str_contains($calculation, '적용이력이 등록되어 있지 않습니다.')
        && str_contains($calculation, 'Coverage/Basis가 없어 지급항목 최종금액으로 계산기초를 자동 산출했습니다.'),
    'local_tax_dependency_visible' => str_contains($calculation, '근로소득세가 미확정되어 지방소득세를 계산할 수 없습니다.'),
    'unknown_total_is_null' => str_contains($calculation, "'deduction_amount' => \$unresolved ? null")
        && str_contains($calculation, "'net_payment_amount' => \$unresolved ? null"),
    'confirmed_partial_total_exposed' => str_contains($calculation, "'confirmed_deduction_amount'")
        && str_contains($calculation, "'unresolved_deduction_count'"),
    'empty_other_deduction_omitted' => !str_contains($calculation, "'OTHER_DEDUCTION', '기타공제', 0"),
    'manual_deduction_recalculated' => str_contains($calculation, 'normalizeSettlement($manualDeduction, $actor)')
        && str_contains($calculation, "'deduction_line_items'"),
    'persistence_projection_whitelisted' => str_contains($service, 'array_intersect_key($line,$lineColumns)')
        || str_contains($service, 'array_intersect_key($line, $lineColumns)'),
    'settlement_source_survives_submit' => count(array_filter(
        ['business_source_code','source_reference_id','source_key','business_reason','processed_at','processed_by'],
        static fn(string $column): bool => str_contains($service, "'{$column}'")
    )) === 6,
    'grid_unknown_display' => str_contains($javascript, "deduction_amount_display:unresolved?'미확정'")
        && str_contains($javascript, "net_payment_amount_display:unresolved?'미확정'"),
    'contract_pay_uses_all_pay_lines' => str_contains($javascript, "line.item_type_code==='PAY'")
        && str_contains($javascript, 'contractPay=payLines.length'),
    'detail_reason_visible' => str_contains($javascript, "warning.textContent=calculated===null?(line.calculation_message")
        && str_contains($javascript, "'자동계산과 다른 금액을 적용하는 이유'"),
    'submit_guard_preserved' => str_contains($service, 'assertUnifiedSubmittable')
        && str_contains($service, '계산기초 또는 법정기준을 확인해 주세요.'),
];

$failed = array_keys(array_filter($checks, static fn(bool $value): bool => !$value));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
