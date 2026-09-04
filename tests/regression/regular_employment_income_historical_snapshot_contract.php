<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

use App\Services\Institution\RegularEmploymentIncomeHistoricalService;

$service = new RegularEmploymentIncomeHistoricalService();
$checks = [];
$scenario = static fn(?float $calculated, ?float $adjustment, float $final, ?string $reason = null): array => [[
    'item_type_code' => 'DEDUCTION',
    'item_code' => 'HEALTH_INSURANCE',
    'item_name_snapshot' => '건강보험',
    'calculated_amount' => $calculated,
    'adjustment_amount' => $adjustment,
    'final_amount' => $final,
    'adjustment_reason' => $reason,
]];

$a = $service->normalizeLines($scenario(100, 10, 110, '실제 급여대장 차이'));
$checks['scenario_a'] = $a[0]['verification_status_code'] === 'WARNING';
$b = $service->normalizeLines($scenario(100, null, 100));
$checks['scenario_b'] = $b[0]['adjustment_amount'] === 0.0 && $b[0]['verification_status_code'] === 'CALCULATED';
$c = $service->normalizeLines($scenario(null, null, 75480));
$checks['scenario_c'] = $c[0]['verification_status_code'] === 'NOT_VERIFIABLE' && $c[0]['final_amount'] === 75480.0;

try { $service->normalizeLines($scenario(null, 10, 110, '잘못된 조정')); $checks['scenario_d'] = false; }
catch (InvalidArgumentException) { $checks['scenario_d'] = true; }
try { $service->normalizeLines($scenario(100, 10, 110)); $checks['scenario_e'] = false; }
catch (InvalidArgumentException) { $checks['scenario_e'] = true; }

$snapshots = $service->normalizeSnapshots([
    'national_pension_basis_snapshot' => 1000000,
    'health_insurance_basis_snapshot' => 1088890,
    'employment_insurance_basis_snapshot' => 1088890,
]);
$checks['direct_snapshots'] = $snapshots['national_pension_basis_snapshot'] === 1000000.0
    && $snapshots['health_insurance_basis_snapshot'] === 1088890.0
    && $snapshots['employment_insurance_basis_snapshot'] === 1088890.0;

$lines = [
    ...$service->normalizeLines([['item_type_code' => 'PAY', 'pay_effect_code' => 'CONTRACT_BASE', 'business_source_code' => 'EMPLOYMENT_CONTRACT', 'item_code' => 'PAY', 'item_name_snapshot' => '지급총액', 'calculated_amount' => null, 'adjustment_amount' => null, 'final_amount' => 1088890]]),
    ...$service->normalizeLines([['item_type_code' => 'DEDUCTION', 'item_code' => 'DEDUCTION', 'item_name_snapshot' => '공제총액', 'calculated_amount' => null, 'adjustment_amount' => null, 'final_amount' => 75480]]),
];
$totals = $service->totals($lines);
$checks['historical_totals'] = $totals === ['gross_amount' => 1088890.0, 'deduction_amount' => 75480.0, 'net_payment_amount' => 1013410.0, 'employer_burden_amount' => 0.0];

$migration = file_get_contents(PROJECT_ROOT . '/app/migrations/20260822_12_add_regular_income_insurance_basis_snapshots.up.sql');
$checks['migration_contract'] = is_string($migration)
    && substr_count($migration, '_basis_snapshot DECIMAL(18,2) NULL') === 3
    && str_contains($migration, 'calculated_amount DECIMAL(18,2) NULL DEFAULT NULL')
    && str_contains($migration, 'adjustment_amount DECIMAL(18,2) NULL DEFAULT NULL')
    && str_contains($migration, 'calculated_amount IS NULL AND adjustment_amount IS NULL')
    && str_contains($migration, 'COALESCE(adjustment_amount, 0)');
$calculationSource = file_get_contents(PROJECT_ROOT . '/app/Services/Institution/RegularEmploymentIncomeCalculationService.php');
$incomeSource = file_get_contents(PROJECT_ROOT . '/app/Services/Institution/RegularEmploymentIncomeService.php');
$checks['scenario_f_direct_snapshot_without_coverage'] = is_string($calculationSource)
    && str_contains($calculationSource, '$sourceCode = \'PAYROLL_SNAPSHOT\'')
    && str_contains($calculationSource, '계산기초는 사용자가 확인한 급여 Snapshot을 사용했습니다.');
$checks['scenario_g_coverage_basis_proposal'] = is_string($calculationSource)
    && str_contains($calculationSource, '$sourceCode = \'INSURANCE_ASSESSMENT_BASE\'');
$checks['scenario_h_approved_snapshot_guard'] = is_string($incomeSource)
    && str_contains($incomeSource, '승인·결재 중인 급여 Snapshot은 변경할 수 없습니다.');
$uiSource = file_get_contents(PROJECT_ROOT . '/public/assets/js/pages/institution/regular-employment-income/index.js');
$checks['scenario_a_no_social_insurance_navigation'] = is_string($uiSource)
    && !str_contains($uiSource, '/institution/social-insurance')
    && !str_contains($uiSource, 'reference_month')
    && !str_contains($uiSource, '사회보험 이력 등록')
    && !str_contains($uiSource, "textContent='이력 등록'");
$checks['scenario_e_modal_state_preserved'] = is_string($uiSource)
    && !str_contains($uiSource, 'location.href')
    && !str_contains($uiSource, 'window.location');
$checks['historical_coverage_message_weakened'] = is_string($calculationSource)
    && str_contains($calculationSource, 'Coverage/Basis가 없어 지급항목 최종금액으로 계산기초를 자동 산출했습니다.')
    && !str_contains($calculationSource, '적용이력이 등록되어 있지 않습니다.');

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($failed === [] ? 0 : 1);
