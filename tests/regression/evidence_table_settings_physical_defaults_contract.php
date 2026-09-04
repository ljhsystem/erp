<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$meta = (string) file_get_contents($root . '/app/Services/System/DataTableColumnMetaService.php');
$schemaModel = (string) file_get_contents($root . '/app/Models/System/SystemSchemaModel.php');
$settings = (string) file_get_contents($root . '/public/assets/js/common/datatable/dataTableSettings.js');

$evidenceDomains = [
    'evidence-bank-transaction' => 'ledger_evidence_bank_transaction',
    'evidence-tax-invoice' => 'ledger_evidence_tax_invoice',
    'evidence-tax-invoice-manual' => 'ledger_evidence_tax_invoice_manual',
    'evidence-cash-receipt' => 'ledger_evidence_cash_receipt',
    'evidence-card-hometax' => 'ledger_evidence_card_hometax',
    'evidence-card-statement' => 'ledger_evidence_card_statement',
    'evidence-employee-expense-personal' => 'ledger_evidence_employee_personal_expense',
    'evidence-payroll-report' => 'ledger_evidence_salary_report',
    'evidence-daily-employment-income' => 'ledger_evidence_daily_employment_income',
];

$checks = [
    'physical_query_uses_current_schema' => str_contains($schemaModel, 'TABLE_SCHEMA = DATABASE()')
        && str_contains($schemaModel, 'ORDER BY ORDINAL_POSITION ASC'),
    'evidence_uses_exact_db_comment' => str_contains($meta, "str_starts_with(\$domain, 'evidence-')")
        && str_contains($meta, 'physicalColumnLabel('),
    'physical_nullability_is_default_requirement' => str_contains(
        $settings,
        "column.__dtSettingsRequired\n                        ? COLUMN_REQUIREMENT_POLICY.REQUIRED\n                        : COLUMN_REQUIREMENT_POLICY.NONE"
    ),
    'restore_refetches_current_metadata' => substr_count(
        $settings,
        'forceRefresh: true'
    ) >= 2,
    'physical_order_uses_db_ordinal' => str_contains(
        $settings,
        'physicalMeta?.ordinal_position'
    ),
];

foreach ($evidenceDomains as $domain => $table) {
    $checks['domain_' . $domain] = str_contains(
        $meta,
        "'{$domain}' => ['table' => '{$table}']"
    );
}

$failed = array_keys(array_filter($checks, static fn (bool $passed): bool => !$passed));
echo json_encode([
    'success' => $failed === [],
    'checks' => $checks,
    'failed' => $failed,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($failed === [] ? 0 : 1);
