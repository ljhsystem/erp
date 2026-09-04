<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

use Core\DbPdo;

$pdo = DbPdo::conn();
$schema = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$expectedSchema = '';
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--schema=')) $expectedSchema = trim(substr($argument, 9));
}
if ($expectedSchema !== '' && $schema !== $expectedSchema) throw new RuntimeException('SCHEMA_MISMATCH');

$scalar = static fn(string $sql): int => (int) $pdo->query($sql)->fetchColumn();
$columns = static function (string $table) use ($pdo): array {
    $statement = $pdo->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table ORDER BY ORDINAL_POSITION');
    $statement->execute([':table' => $table]);
    return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
};
$columnReport = static function (string $table, array $required) use ($columns): array {
    $actual = [];
    foreach ($columns($table) as $column) {
        $actual[(string) $column['COLUMN_NAME']] = ['type' => (string) $column['COLUMN_TYPE'], 'nullable' => $column['IS_NULLABLE'] === 'YES', 'default' => $column['COLUMN_DEFAULT']];
    }
    $result = [];
    foreach ($required as $column) $result[$column] = $actual[$column] ?? null;
    return $result;
};
$has = static function (string $table, string $column) use ($columns): bool {
    return in_array($column, array_column($columns($table), 'COLUMN_NAME'), true);
};

$common = ['id','sort_no','external_key','source_type','import_type','business_unit','transaction_direction','operation_type','client_id','employee_id','project_id','bank_account_id','card_id','work_team_id','evidence_status','created_at','created_by','updated_at','updated_by','deleted_at','deleted_by'];
$tables = ['PAYROLL_REPORT'=>'ledger_evidence_salary_report','EMPLOYEE_EXPENSE_PERSONAL'=>'ledger_evidence_employee_personal_expense','DAILY_EMPLOYMENT_INCOME'=>'ledger_evidence_daily_employment_income'];
$report = ['mode'=>'READ_ONLY_SELECT_DRY_RUN','schema'=>$schema,'ssot'=>'docs/architecture/EvidenceOriginalContract.md','tables'=>[],'manual_review_required'=>[]];
foreach ($tables as $type => $table) {
    $contract = $columnReport($table, $common);
    $report['tables'][$type] = ['table'=>$table,'rows'=>$scalar("SELECT COUNT(*) FROM `{$table}`"),'common_columns'=>$contract,'missing_common_columns'=>array_keys(array_filter($contract, static fn(mixed $value): bool => $value === null))];
}

$salaryMismatch = $scalar('SELECT COUNT(*) FROM ledger_evidence_salary_report evidence LEFT JOIN institution_regular_employment_incomes header ON header.id=evidence.source_regular_employment_income_id LEFT JOIN institution_regular_employment_income_items item ON item.id=evidence.regular_employment_income_item_id AND item.regular_employment_income_id=evidence.source_regular_employment_income_id WHERE header.id IS NULL OR item.id IS NULL OR ROUND(evidence.raw_gross_amount,2)<>ROUND(item.gross_amount,2) OR ROUND(evidence.raw_deduction_amount,2)<>ROUND(item.deduction_amount,2)');
$report['tables']['PAYROLL_REPORT']['dry_run'] = [
    'common_backfill_rows'=>$has($tables['PAYROLL_REPORT'],'source_document_id')?$scalar('SELECT COUNT(*) FROM ledger_evidence_salary_report WHERE source_document_id IS NULL'):$report['tables']['PAYROLL_REPORT']['rows'],
    'raw_backfill_rows'=>$has($tables['PAYROLL_REPORT'],'raw_gross_payment_amount')?$scalar('SELECT COUNT(*) FROM ledger_evidence_salary_report WHERE raw_gross_payment_amount IS NULL OR raw_worker_deduction_amount IS NULL'):$report['tables']['PAYROLL_REPORT']['rows'],
    'source_or_amount_mismatch_rows'=>$salaryMismatch,'legacy_snapshot_reconstruction_rows'=>0,
    'not_applicable_nulls'=>['client_id','project_id','bank_account_id','card_id','work_team_id'],
];

$personalUnresolved = $scalar("SELECT COUNT(*) FROM ledger_evidence_employee_personal_expense evidence LEFT JOIN approval_personal_expense_items item ON item.id=evidence.source_personal_expense_item_id LEFT JOIN approval_personal_expenses header ON header.id=item.personal_expense_id LEFT JOIN user_approval_requests request ON request.id=header.current_approval_request_id AND request.document_type='PERSONAL_EXPENSE' AND request.document_id=header.id WHERE item.id IS NULL OR header.id IS NULL OR request.id IS NULL OR request.status<>'approved' OR (SELECT COUNT(*) FROM user_approval_request_steps final_step WHERE final_step.request_id=request.id AND final_step.step_type='FINAL_APPROVAL' AND final_step.status='approved' AND final_step.is_active=1 AND final_step.action_at IS NOT NULL AND final_step.acted_by IS NOT NULL)<>1");
$report['tables']['EMPLOYEE_EXPENSE_PERSONAL']['dry_run'] = [
    'common_backfill_rows'=>$has($tables['EMPLOYEE_EXPENSE_PERSONAL'],'source_document_id')?$scalar('SELECT COUNT(*) FROM ledger_evidence_employee_personal_expense WHERE source_document_id IS NULL'):$report['tables']['EMPLOYEE_EXPENSE_PERSONAL']['rows'],
    'raw_backfill_rows'=>$has($tables['EMPLOYEE_EXPENSE_PERSONAL'],'raw_application_date')?$scalar('SELECT COUNT(*) FROM ledger_evidence_employee_personal_expense WHERE raw_application_date IS NULL'):$report['tables']['EMPLOYEE_EXPENSE_PERSONAL']['rows'],
    'approval_or_source_unresolved_rows'=>$personalUnresolved,'legacy_snapshot_reconstruction_rows'=>0,
    'not_applicable_nulls'=>['bank_account_id','card_id','work_team_id'],
];

$dailyMismatch = $scalar('SELECT COUNT(*) FROM ledger_evidence_daily_employment_income evidence LEFT JOIN institution_daily_employment_income_groups income_group ON income_group.id=evidence.daily_employment_income_group_id AND income_group.daily_employment_income_id=evidence.source_daily_employment_income_id LEFT JOIN institution_daily_employment_income_items item ON item.id=evidence.daily_employment_income_item_id AND item.daily_employment_income_group_id=evidence.daily_employment_income_group_id WHERE income_group.id IS NULL OR item.id IS NULL OR item.worker_client_id<>evidence.worker_client_id');
$report['tables']['DAILY_EMPLOYMENT_INCOME']['dry_run'] = [
    'common_backfill_rows'=>$has($tables['DAILY_EMPLOYMENT_INCOME'],'external_key')?$scalar('SELECT COUNT(*) FROM ledger_evidence_daily_employment_income WHERE external_key IS NULL'):$report['tables']['DAILY_EMPLOYMENT_INCOME']['rows'],
    'raw_backfill_rows'=>$has($tables['DAILY_EMPLOYMENT_INCOME'],'raw_business_unit')?$scalar('SELECT COUNT(*) FROM ledger_evidence_daily_employment_income WHERE raw_business_unit IS NULL'):$report['tables']['DAILY_EMPLOYMENT_INCOME']['rows'],
    'source_mismatch_rows'=>$dailyMismatch,
    'amount_formula_mismatch_rows'=>$scalar('SELECT COUNT(*) FROM ledger_evidence_daily_employment_income WHERE ROUND(raw_gross_payment_amount-raw_worker_deduction_amount,2)<>ROUND(raw_net_payment_amount,2)'),
    'raw_line_rows'=>$scalar('SELECT COUNT(*) FROM ledger_evidence_daily_employment_income_lines'),
    'not_applicable_nulls'=>['employee_id','bank_account_id','card_id'],
];
foreach (['PAYROLL_REPORT'=>$salaryMismatch,'EMPLOYEE_EXPENSE_PERSONAL'=>$personalUnresolved,'DAILY_EMPLOYMENT_INCOME'=>$dailyMismatch] as $type=>$count) if ($count>0) $report['manual_review_required'][$type]=$count;

$report['link_integrity'] = [
    'orphan_transaction_links'=>$scalar("SELECT COUNT(*) FROM ledger_evidence_links link_row LEFT JOIN ledger_transactions transaction_row ON transaction_row.id=link_row.target_id WHERE link_row.evidence_type IN ('PAYROLL_REPORT','EMPLOYEE_EXPENSE_PERSONAL','DAILY_EMPLOYMENT_INCOME') AND link_row.target_type='TRANSACTION' AND link_row.deleted_at IS NULL AND transaction_row.id IS NULL"),
    'orphan_voucher_links'=>$scalar("SELECT COUNT(*) FROM ledger_evidence_links link_row LEFT JOIN ledger_vouchers voucher ON voucher.id=link_row.target_id WHERE link_row.evidence_type IN ('PAYROLL_REPORT','EMPLOYEE_EXPENSE_PERSONAL','DAILY_EMPLOYMENT_INCOME') AND link_row.target_type='VOUCHER' AND link_row.deleted_at IS NULL AND voucher.id IS NULL"),
];
$report['table_settings'] = [
    'daily_legacy_key_rows'=>$scalar("SELECT COUNT(*) FROM system_user_settings WHERE setting_type='TABLE' AND page_key='evidence-daily-employment-income' AND JSON_VALID(settings_json)=1 AND JSON_SEARCH(settings_json,'one','team_id') IS NOT NULL"),
    'daily_standard_key_rows'=>$scalar("SELECT COUNT(*) FROM system_user_settings WHERE setting_type='TABLE' AND page_key='evidence-daily-employment-income' AND JSON_VALID(settings_json)=1 AND JSON_SEARCH(settings_json,'one','work_team_id') IS NOT NULL"),
    'salary_legacy_key_rows'=>$scalar("SELECT COUNT(*) FROM system_user_settings WHERE setting_type='TABLE' AND page_key IN ('evidence-payroll','evidence-payroll-report') AND JSON_VALID(settings_json)=1 AND (JSON_SEARCH(settings_json,'one','raw_gross_amount') IS NOT NULL OR JSON_SEARCH(settings_json,'one','raw_deduction_amount') IS NOT NULL OR JSON_SEARCH(settings_json,'one','team_id') IS NOT NULL)"),
    'salary_legacy_team_key_rows'=>$scalar("SELECT COUNT(*) FROM system_user_settings WHERE setting_type='TABLE' AND page_key IN ('evidence-payroll','evidence-payroll-report') AND JSON_VALID(settings_json)=1 AND JSON_SEARCH(settings_json,'one','team_id') IS NOT NULL"),
    'personal_expense_legacy_key_rows'=>$scalar("SELECT COUNT(*) FROM system_user_settings WHERE setting_type='TABLE' AND page_key='evidence-employee-expense-personal' AND JSON_VALID(settings_json)=1 AND JSON_SEARCH(settings_json,'one','team_id') IS NOT NULL"),
];
$report['post_migration'] = [
    'updated_at_auto_columns'=>$scalar("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('ledger_evidence_salary_report','ledger_evidence_employee_personal_expense','ledger_evidence_daily_employment_income') AND COLUMN_NAME='updated_at' AND EXTRA LIKE '%on update%'") ,
    'salary_backfill_mismatch_rows'=>$scalar('SELECT COUNT(*) FROM ledger_evidence_salary_report WHERE source_document_id IS NULL OR source_item_id IS NULL OR business_key_hash IS NULL OR source_document_id<>source_regular_employment_income_id OR source_item_id<>regular_employment_income_item_id OR NOT(work_team_id<=>team_id) OR ROUND(raw_gross_payment_amount,2)<>ROUND(raw_gross_amount,2) OR ROUND(raw_worker_deduction_amount,2)<>ROUND(raw_deduction_amount,2)'),
    'personal_backfill_mismatch_rows'=>$scalar('SELECT COUNT(*) FROM ledger_evidence_employee_personal_expense evidence JOIN approval_personal_expense_items item ON item.id=evidence.source_personal_expense_item_id JOIN approval_personal_expenses header ON header.id=item.personal_expense_id WHERE evidence.source_document_id<>header.id OR evidence.source_item_id<>item.id OR evidence.approval_request_id<>header.current_approval_request_id OR evidence.raw_application_date<>header.application_date OR NOT(evidence.raw_project_id<=>item.project_id) OR NOT(evidence.raw_client_id<=>item.client_id)'),
    'daily_backfill_mismatch_rows'=>$scalar("SELECT COUNT(*) FROM ledger_evidence_daily_employment_income evidence JOIN institution_daily_employment_income_groups income_group ON income_group.id=evidence.daily_employment_income_group_id JOIN institution_daily_employment_income_items item ON item.id=evidence.daily_employment_income_item_id WHERE evidence.external_key<>CONCAT('DEI:',evidence.business_key_hash) OR evidence.source_type<>'APPROVAL' OR evidence.import_type<>'DAILY_EMPLOYMENT_INCOME' OR evidence.client_id<>evidence.worker_client_id OR evidence.employee_id IS NOT NULL OR evidence.bank_account_id IS NOT NULL OR evidence.card_id IS NOT NULL OR evidence.raw_business_unit<>income_group.business_unit OR NOT(evidence.raw_project_id<=>income_group.project_id) OR NOT(evidence.raw_work_team_id<=>income_group.work_team_id)"),
    'personal_total_amount'=>$scalar('SELECT ROUND(SUM(raw_total_amount),0) FROM ledger_evidence_employee_personal_expense'),
    'daily_raw_pay_amount'=>$scalar("SELECT ROUND(SUM(raw_final_amount),0) FROM ledger_evidence_daily_employment_income_lines WHERE line_type_code='PAY'"),
    'daily_raw_deduction_amount'=>$scalar("SELECT ROUND(SUM(raw_final_amount),0) FROM ledger_evidence_daily_employment_income_lines WHERE line_type_code='DEDUCTION' AND application_status_code='APPLICABLE'"),
    'daily_raw_employer_burden_amount'=>$scalar("SELECT ROUND(SUM(raw_final_amount),0) FROM ledger_evidence_daily_employment_income_lines WHERE line_type_code='EMPLOYER_BURDEN' AND application_status_code='APPLICABLE'"),
    'invalid_table_settings_json_rows'=>$scalar("SELECT COUNT(*) FROM system_user_settings WHERE setting_type='TABLE' AND page_key IN ('evidence-payroll','evidence-payroll-report','evidence-employee-expense-personal','evidence-daily-employment-income') AND JSON_VALID(settings_json)=0"),
    'table_settings_team_key_conflict_rows'=>$scalar("SELECT COUNT(*) FROM system_user_settings WHERE setting_type='TABLE' AND page_key IN ('evidence-payroll','evidence-payroll-report','evidence-employee-expense-personal','evidence-daily-employment-income') AND JSON_SEARCH(settings_json,'one','team_id') IS NOT NULL AND JSON_SEARCH(settings_json,'one','work_team_id') IS NOT NULL"),
    'salary_standard_team_rows'=>$scalar("SELECT COUNT(*) FROM system_user_settings WHERE setting_type='TABLE' AND page_key IN ('evidence-payroll','evidence-payroll-report') AND JSON_SEARCH(settings_json,'one','work_team_id') IS NOT NULL"),
    'personal_standard_team_rows'=>$scalar("SELECT COUNT(*) FROM system_user_settings WHERE setting_type='TABLE' AND page_key='evidence-employee-expense-personal' AND JSON_SEARCH(settings_json,'one','work_team_id') IS NOT NULL"),
    'daily_standard_team_rows'=>$scalar("SELECT COUNT(*) FROM system_user_settings WHERE setting_type='TABLE' AND page_key='evidence-daily-employment-income' AND JSON_SEARCH(settings_json,'one','work_team_id') IS NOT NULL"),
    'related_transaction_changed_during_migration_rows'=>$scalar("SELECT COUNT(*) FROM ledger_transactions transaction_row JOIN ledger_evidence_links link_row ON link_row.target_type='TRANSACTION' AND link_row.target_id=transaction_row.id AND link_row.deleted_at IS NULL WHERE link_row.evidence_type IN ('PAYROLL_REPORT','EMPLOYEE_EXPENSE_PERSONAL','DAILY_EMPLOYMENT_INCOME') AND transaction_row.updated_at>='2026-09-02 15:19:00'"),
    'related_voucher_changed_during_migration_rows'=>$scalar("SELECT COUNT(*) FROM ledger_vouchers voucher JOIN ledger_evidence_links link_row ON link_row.target_type='VOUCHER' AND link_row.target_id=voucher.id AND link_row.deleted_at IS NULL WHERE link_row.evidence_type IN ('PAYROLL_REPORT','EMPLOYEE_EXPENSE_PERSONAL','DAILY_EMPLOYMENT_INCOME') AND voucher.updated_at>='2026-09-02 15:19:00'"),
    'migration_table_trigger_count'=>$scalar("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE IN ('ledger_evidence_salary_report','ledger_evidence_employee_personal_expense','ledger_evidence_daily_employment_income','system_user_settings')"),
    'salary_ssot_index_count'=>$scalar("SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_salary_report' AND INDEX_NAME IN ('uk_salary_report_business_key_hash','idx_salary_report_work_team')"),
    'personal_ssot_index_count'=>$scalar("SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_employee_personal_expense' AND INDEX_NAME IN ('idx_personal_expense_evidence_approval_request','idx_personal_expense_evidence_work_team','uk_personal_expense_evidence_business_key_hash')"),
    'personal_approval_fk_count'=>$scalar("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_employee_personal_expense' AND CONSTRAINT_NAME='fk_personal_expense_evidence_approval_request'"),
    'daily_common_index_count'=>$scalar("SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_daily_employment_income' AND INDEX_NAME IN ('idx_daily_evidence_sort_no','idx_daily_evidence_external_key','idx_daily_evidence_client')"),
];
$report['internal_metadata'] = $pdo->query("SELECT import_type,source_table,evidence_type FROM ledger_evidence_metadata WHERE source_table IN ('ledger_evidence_salary_report','ledger_evidence_employee_personal_expense','ledger_evidence_daily_employment_income') AND deleted_at IS NULL ORDER BY source_table,import_type")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$report['personal_import_types'] = $pdo->query('SELECT import_type,COUNT(*) row_count FROM ledger_evidence_employee_personal_expense GROUP BY import_type ORDER BY import_type')->fetchAll(PDO::FETCH_ASSOC) ?: [];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
