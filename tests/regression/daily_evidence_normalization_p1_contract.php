<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

use App\Services\Institution\DailyEmploymentIncomeAccountingGenerationService;
use App\Services\Ledger\EvidenceTypePolicyService;

$reflection = new ReflectionClass(DailyEmploymentIncomeAccountingGenerationService::class);
$service = $reflection->newInstanceWithoutConstructor();
$amountMethod = $reflection->getMethod('assertEvidenceAmounts');
$expectedMethod = $reflection->getMethod('expectedSettlementTrace');
$traceMethod = $reflection->getMethod('assertSettlementTrace');

$header = ['id' => 'DOC', 'income_year_month' => '2026-08'];
$group = ['id' => 'GROUP', 'business_unit' => 'CONSTRUCTION'];
$revision = ['id' => 'REVISION', 'source_hash' => str_repeat('a', 64)];
$plan = ['header' => $header, 'revision' => $revision];
$baseItem = [
    'id' => 'ITEM',
    'total_work_days' => 1,
    'total_gross_amount' => 100000,
    'total_deduction_amount' => 0,
    'total_net_payment_amount' => 100000,
    'total_employer_burden_amount' => 0,
    'lines' => [],
];

$checks = [];
$amountMethod->invoke($service, $header, $group, $baseItem);
$checks['zero_deduction'] = $expectedMethod->invoke($service, $plan, $group, $baseItem) === [];

$employmentItem = $baseItem;
$employmentItem['total_deduction_amount'] = 900;
$employmentItem['total_net_payment_amount'] = 99100;
$employmentItem['lines'] = [[
    'id' => 'LINE-EMPLOYMENT', 'line_type_code' => 'DEDUCTION',
    'application_status_code' => 'APPLICABLE', 'final_amount' => 900,
    'line_code' => 'EMPLOYMENT_INSURANCE',
]];
$expectedEmployment = $expectedMethod->invoke($service, $plan, $group, $employmentItem);
$checks['employment_only'] = count($expectedEmployment) === 1;

$multipleItem = $employmentItem;
$multipleItem['total_deduction_amount'] = 1900;
$multipleItem['total_net_payment_amount'] = 98100;
$multipleItem['lines'][] = [
    'id' => 'LINE-TAX', 'line_type_code' => 'DEDUCTION',
    'application_status_code' => 'APPLICABLE', 'final_amount' => 1000,
    'line_code' => 'INCOME_TAX',
];
$checks['multiple_deductions'] = count($expectedMethod->invoke($service, $plan, $group, $multipleItem)) === 2;

$employerOnly = $baseItem;
$employerOnly['total_employer_burden_amount'] = 5000;
$employerOnly['lines'] = [[
    'id' => 'LINE-EMPLOYER', 'line_type_code' => 'EMPLOYER_BURDEN',
    'application_status_code' => 'APPLICABLE', 'final_amount' => 5000,
    'line_code' => 'INDUSTRIAL_ACCIDENT_INSURANCE',
]];
$checks['employer_burden_excluded'] = $expectedMethod->invoke($service, $plan, $group, $employerOnly) === [];

$mismatchRejected = false;
try {
    $invalid = $baseItem;
    $invalid['total_net_payment_amount'] = 99999;
    $amountMethod->invoke($service, $header, $group, $invalid);
} catch (Throwable) {
    $mismatchRejected = true;
}
$checks['amount_mismatch_rejected'] = $mismatchRejected;

$settlement = [[
    'amount_sign' => 'MINUS',
    'amount' => 900,
    'meta_json' => json_encode([
        'burden_subject' => 'EMPLOYEE',
        'source_document_id' => 'DOC', 'source_group_id' => 'GROUP', 'source_item_id' => 'ITEM',
        'source_line_id' => 'LINE-EMPLOYMENT', 'line_code' => 'EMPLOYMENT_INSURANCE',
        'calculation_revision_id' => 'REVISION', 'source_hash' => str_repeat('a', 64),
        'attribution_month' => '2026-08',
    ], JSON_THROW_ON_ERROR),
]];
$traceMethod->invoke($service, $settlement, $expectedEmployment);
$checks['settlement_trace'] = true;

foreach (['missing_settlement' => [], 'wrong_sign' => [array_replace($settlement[0], ['amount_sign' => 'PLUS'])]] as $key => $fixture) {
    try {
        $traceMethod->invoke($service, $fixture, $expectedEmployment);
        $checks[$key] = false;
    } catch (Throwable) {
        $checks[$key] = true;
    }
}

$serviceSource = file_get_contents(PROJECT_ROOT . '/app/Services/Institution/DailyEmploymentIncomeAccountingGenerationService.php') ?: '';
$modelSource = file_get_contents(PROJECT_ROOT . '/app/Models/Institution/DailyEmploymentIncomeAccountingGenerationModel.php') ?: '';
$readerSource = file_get_contents(PROJECT_ROOT . '/app/Models/Ledger/DailyEmploymentIncomeEvidenceReadModel.php') ?: '';
$boundarySource = file_get_contents(PROJECT_ROOT . '/app/Services/Ledger/EvidenceBodyReadService.php') ?: '';
$migration = file_get_contents(PROJECT_ROOT . '/app/migrations/20260901_01_normalize_daily_employment_income_evidence.up.sql') ?: '';

$checks['canonical_evidence_type'] = str_contains($serviceSource, "EVIDENCE_TYPE = 'DAILY_EMPLOYMENT_INCOME'");
$checks['operation_type_guard'] = str_contains($serviceSource, "operation_type'] !== 'DAILY_WORKER'");
$checks['one_item_guard'] = str_contains($serviceSource, "item_count'] !== 1");
$checks['gross_item_guard'] = str_contains($serviceSource, "item_total'], 2) !== \$amounts['gross']");
$checks['signed_settlement_guard'] = str_contains($serviceSource, "settlement_total'], 2) !== -\$amounts['deduction']");
$checks['one_link_guard'] = str_contains($modelSource, ') evidence_link_count');
$checks['legacy_alias_boundary'] = str_contains($boundarySource, "['DAILY_WORK_REPORT', 'PAYROLL_WITHHOLDING']")
    && str_contains($readerSource, "'DAILY_EMPLOYMENT_INCOME' import_type")
    && EvidenceTypePolicyService::normalizeLegacyDataType('DAILY_WORK_REPORT') === 'DAILY_EMPLOYMENT_INCOME'
    && EvidenceTypePolicyService::normalizeLegacyDataType('PAYROLL_WITHHOLDING') === 'DAILY_EMPLOYMENT_INCOME';
$backfillMigration = file_get_contents(PROJECT_ROOT . '/app/migrations/20260901_02_backfill_daily_employment_income_evidence_raw.up.sql') ?: '';
$checks['migration_nullable_backfill'] = str_contains($migration, 'raw_gross_payment_amount DECIMAL(18,2) NULL')
    && str_contains($migration, 'raw_income_year_month CHAR(7) NULL')
    && str_contains($migration, 'transaction_direction VARCHAR(30) NULL')
    && str_contains($backfillMigration, "e.operation_type='DAILY_WORKER'")
    && str_contains($backfillMigration, 'e.raw_gross_payment_amount=e.total_gross_amount');
$checks['dual_write'] = str_contains($serviceSource, "'raw_gross_payment_amount' => \$item['total_gross_amount']")
    && str_contains($serviceSource, "'raw_income_year_month' => \$header['income_year_month']")
    && str_contains($serviceSource, "'total_gross_amount' => \$item['total_gross_amount']");
$rawLineMigration = file_get_contents(PROJECT_ROOT . '/app/migrations/20260901_05_create_daily_employment_income_evidence_raw_lines.up.sql') ?: '';
$checks['raw_line_contract'] = str_contains($rawLineMigration, 'ledger_evidence_daily_employment_income_lines')
    && str_contains($rawLineMigration, 'UNIQUE KEY uq_daily_evidence_raw_line')
    && str_contains($serviceSource, 'insertEvidenceRawLine')
    && str_contains($serviceSource, 'assertRawLines');
$checks['failure_checkpoints'] = str_contains($serviceSource, "after_registry_")
    && str_contains($serviceSource, "before_closure_complete");
$checks['callback_reuse_guard'] = str_contains($serviceSource, 'assertCompletedArtifacts');

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
