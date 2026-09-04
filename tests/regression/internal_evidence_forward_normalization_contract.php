<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));

$read = static fn(string $path): string => file_get_contents(PROJECT_ROOT . '/' . $path) ?: '';
$dailyAdd = $read('app/migrations/20260901_01_normalize_daily_employment_income_evidence.up.sql');
$dailyBackfill = $read('app/migrations/20260901_02_backfill_daily_employment_income_evidence_raw.up.sql');
$salaryAdd = $read('app/migrations/20260902_41_add_salary_evidence_ssot_columns.up.sql');
$salaryBackfill = $read('app/migrations/20260902_42_backfill_salary_evidence_ssot_columns.up.sql');
$personalAdd = $read('app/migrations/20260902_43_add_personal_expense_evidence_ssot_columns.up.sql');
$personalBackfill = $read('app/migrations/20260902_44_backfill_personal_expense_evidence_ssot_columns.up.sql');
$dailyCommonAdd = $read('app/migrations/20260902_45_add_daily_evidence_common_columns.up.sql');
$dailyCommonBackfill = $read('app/migrations/20260902_46_backfill_daily_evidence_common_columns.up.sql');
$salaryService = $read('app/Services/Institution/RegularEmploymentIncomeAccountingGenerationService.php');
$personalService = $read('app/Services/Approval/PersonalExpenseApprovalService.php');
$transactionService = $read('app/Services/Ledger/TransactionCrudService.php');

$checks = [
    'daily_raw_physical_columns' => str_contains($dailyAdd, 'raw_work_day_count')
        && str_contains($dailyAdd, 'raw_gross_payment_amount')
        && !str_contains($dailyAdd, 'ADD COLUMN gross_payment_amount'),
    'daily_deterministic_backfill' => str_contains($dailyBackfill, 'e.raw_gross_payment_amount=e.total_gross_amount')
        && str_contains($dailyBackfill, "e.operation_type='DAILY_WORKER'")
        && str_contains($dailyBackfill, 'e.raw_income_year_month=e.income_year_month'),
    'salary_split_migration' => str_contains($salaryAdd, 'ledger_evidence_salary_report')
        && !str_contains($salaryAdd, 'ledger_evidence_daily_employment_income')
        && !str_contains($salaryAdd, 'ledger_evidence_employee_personal_expense'),
    'salary_hash_semantics' => str_contains($salaryAdd, 'source_hash')
        && str_contains($salaryAdd, 'reconstruction_hash')
        && !str_contains($salaryBackfill, "SET snapshot_origin_code='LEGACY_RECONSTRUCTED'")
        && !str_contains($salaryBackfill, 'SET source_hash='),
    'salary_new_approval_snapshot' => str_contains($salaryService, "'snapshot_origin_code' => 'APPROVAL_CAPTURED'")
        && str_contains($salaryService, "'source_hash' => hash('sha256', \$snapshotJson)")
        && !str_contains($salaryService, "'raw_employee_count' => 1"),
    'personal_split_migration' => str_contains($personalAdd, 'ledger_evidence_employee_personal_expense')
        && !str_contains($personalAdd, 'ledger_evidence_salary_report'),
    'personal_manual_review_guard' => str_contains($personalBackfill, 'MANUAL_REVIEW_REQUIRED')
        && str_contains($personalBackfill, ')<>1'),
    'personal_raw_source_mapping' => str_contains($personalAdd, 'raw_application_date')
        && str_contains($personalAdd, 'raw_project_id')
        && str_contains($personalAdd, 'raw_client_id')
        && str_contains($personalBackfill, 'evidence.raw_project_id=item.project_id'),
    'personal_new_approval_trace' => str_contains($personalService, "'approval_request_id'=>\$request['id']")
        && str_contains($personalService, "'approved_by'=>\$actor"),
    'personal_vat_signed_contract' => str_contains($personalService, "'settlement_type' => 'VAT', 'amount_sign' => 'PLUS'")
        && str_contains($personalService, "'transaction_supply_amount'=>\$item['item_supply_amount']")
        && str_contains($personalService, "'transaction_final_amount'=>\$item['item_total_amount']"),
    'signed_settlement_formula' => str_contains($transactionService, '$finalAmount = $supplyAmount + $settlementAmount;')
        && str_contains($transactionService, "\$amountSign === 'MINUS' ? (-1 * \$baseAmount) : \$baseAmount"),
    'daily_common_envelope' => str_contains($dailyCommonAdd, 'ADD COLUMN external_key')
        && str_contains($dailyCommonAdd, 'ADD COLUMN client_id')
        && str_contains($dailyCommonAdd, 'ADD COLUMN deleted_at')
        && str_contains($dailyCommonBackfill, "evidence.import_type='DAILY_EMPLOYMENT_INCOME'"),
    'work_team_forward_compatibility' => str_contains($salaryAdd, 'work_team_id')
        && str_contains($personalAdd, 'work_team_id')
        && str_contains($dailyCommonAdd, 'raw_work_team_id'),
    'external_p1_scope_removed' => !is_file(PROJECT_ROOT . '/app/migrations/20260902_11_add_external_evidence_source_trace.up.sql')
        && !is_file(PROJECT_ROOT . '/app/migrations/20260902_21_create_tax_invoice_evidence_raw_lines.up.sql')
        && !is_file(PROJECT_ROOT . '/app/migrations/20260902_33_add_evidence_metadata_source_contract.up.sql'),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
