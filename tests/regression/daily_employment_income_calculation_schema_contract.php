<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$m15 = file_get_contents($root . '/app/migrations/20260827_15_create_daily_income_calculation_results_allocations.up.sql');
$m16 = file_get_contents($root . '/app/migrations/20260827_16_create_daily_income_reconciliation_closure_baseline.up.sql');
$checks = [
    'Calculation Revision' => str_contains($m15, 'institution_daily_employment_income_calculation_revisions'),
    '기관 Result 7종' => array_reduce([
        'INCOME_TAX','LOCAL_INCOME_TAX','NATIONAL_PENSION','HEALTH_INSURANCE',
        'LONG_TERM_CARE_INSURANCE','EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT_INSURANCE',
    ], static fn(bool $passed, string $type): bool => $passed && str_contains($m15, "'{$type}'"), true),
    'NULL-safe 물리 Scope Key' => str_contains($m15, "workplace_scope_key VARCHAR(36) NOT NULL")
        && str_contains($m15, "workday_scope_key VARCHAR(36) NOT NULL"),
    'Allocation 결정순위' => str_contains($m15, 'decision_rank') && str_contains($m15, 'residual_applied'),
    'Reconciliation 상태' => str_contains($m16, "status_code IN ('PASSED','FAILED','STALE')"),
    '1원 차단' => str_contains($m16, 'ABS(difference_amount)>=1'),
    'Closure 계산 Revision 연결' => str_contains($m16, 'calculation_revision_id') && str_contains($m16, 'reconciliation_id'),
    'Closure business key' => str_contains($m16, 'business_key_hash'),
];
foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}
echo "OK: 기관계산 물리 Baseline 계약\n";
