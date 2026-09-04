<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$policy = (string) file_get_contents($root . '/app/Services/Ledger/EvidenceTypePolicyService.php');
$salary = (string) file_get_contents($root . '/app/Services/Institution/RegularEmploymentIncomeAccountingGenerationService.php');
$daily = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeAccountingGenerationService.php');
$business = (string) file_get_contents($root . '/app/Services/Institution/BusinessIncomeTransactionGenerationService.php');
$migration = (string) file_get_contents($root . '/app/migrations/20260903_24_align_income_evidence_originals.up.sql');

$checks = [
    'three_read_only' => substr_count($policy, "'read_only' => true") >= 3,
    'income_date_ssot_projection' => str_contains($policy, "['evidence_date', 'transaction_date', 'raw_withholding_date'")
        && str_contains($policy, "['transaction_date', 'evidence_date', 'raw_withholding_date'")
        && str_contains($salary, "'raw_withholding_date' => \$header['withholding_date']"),
    'salary_immutable_lines' => str_contains($salary, 'insertLine') && str_contains($salary, "'line_items' => array_map"),
    'salary_completed_original' => str_contains($salary, 'EvidenceWorkflowPolicyService::COMPLETED') && str_contains($salary, "'source_type' => 'INTERNAL_APPROVAL'"),
    'daily_completed_original' => str_contains($daily, "'evidence_status_code' => 'COMPLETED'") && str_contains($daily, "'source_type' => 'INTERNAL_APPROVAL'"),
    'business_raw_amount_only' => !str_contains($business, "'supply_amount' =>") && !str_contains($business, "'vat_amount' =>") && !str_contains($business, "'total_amount' =>"),
    'business_legacy_columns_removed' => str_contains($migration, 'DROP COLUMN supply_amount') && str_contains($migration, 'DROP COLUMN total_amount'),
    'no_trigger' => stripos($migration, 'CREATE TRIGGER') === false,
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
