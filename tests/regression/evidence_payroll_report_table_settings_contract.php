<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$meta = (string) file_get_contents($root . '/app/Services/System/DataTableColumnMetaService.php');
$policy = (string) file_get_contents($root . '/app/Services/Ledger/EvidenceTypePolicyService.php');
$page = (string) file_get_contents($root . '/public/assets/js/pages/ledger/evidence-page-app.js');
$modal = (string) file_get_contents($root . '/public/assets/js/pages/ledger/evidence-list/modal.js');
$readModel = (string) file_get_contents($root . '/app/Models/Ledger/PayrollEvidenceReadModel.php');
$table = (string) file_get_contents($root . '/public/assets/js/pages/ledger/evidence-list/table.js');
$pageApp = (string) file_get_contents($root . '/public/assets/js/pages/ledger/evidence-page-app.js');
$sourceRepository = (string) file_get_contents($root . '/app/Repositories/Ledger/EvidenceSourceRepository.php');
$commonTableCss = (string) file_get_contents($root . '/public/assets/css/components/data-table.css');
$evidenceCss = (string) file_get_contents($root . '/public/assets/css/pages/ledger/data-status.css');

$checks = [
    'policy_domain' => str_contains($policy, "'meta_domain' => 'evidence-payroll-report'"),
    'physical_table_mapping' => str_contains($meta, "'evidence-payroll-report' => ['table' => 'ledger_evidence_salary_report']"),
    'shared_meta_api' => str_contains($page, "dataTableColumns: '/api/settings/system/data-table-columns'"),
    'modal_uses_same_domain' => str_contains($page, 'loadEvidenceModalFieldOptions')
        && str_contains($page, 'evidenceMetaDomain(normalizedType)'),
    'modal_uses_table_policy' => str_contains($modal, 'currentPolicyState')
        && str_contains($modal, 'columnRequirementPolicy'),
    'salary_report_reference_names' => str_contains($readModel, 'source_regular_employment_income_name')
        && str_contains($readModel, 'approval_request_name')
        && str_contains($readModel, 'regular_employment_income_item_name')
        && str_contains($readModel, "'{\$linkEvidenceType}' AS import_type")
        && str_contains($readModel, "'approved_by_name' => 'approved_by'"),
    'table_uses_generic_reference_names' => str_contains($table, "key.endsWith('_id') && key !== 'id'")
        && str_contains($table, '`${key.slice(0, -3)}_name`'),
    'detail_uses_generic_reference_names' => str_contains($pageApp, "key.endsWith('_id') && key !== 'id'")
        && str_contains($pageApp, "key.endsWith('_by')"),
    'personal_expense_source_reference_name' => str_contains($sourceRepository, "'name' => 'source_personal_expense_item_name'")
        && str_contains($sourceRepository, "'table' => 'approval_personal_expense_items'"),
    'paged_salary_reference_names' => str_contains($sourceRepository, "'name' => 'source_regular_employment_income_name'")
        && str_contains($sourceRepository, "'columns' => ['title']")
        && str_contains($sourceRepository, "'name' => 'approval_request_name'")
        && str_contains($sourceRepository, "'columns' => ['sort_no']")
        && str_contains($sourceRepository, "'name' => 'regular_employment_income_item_name'")
        && str_contains($sourceRepository, "'columns' => ['employee_name_snapshot']"),
    'headers_are_not_truncated' => str_contains($commonTableCss, '.dataTables_wrapper table.dataTable thead th .dt-column-title')
        && str_contains($commonTableCss, 'white-space: normal')
        && !str_contains($evidenceCss, '#evidenceStatusTable_wrapper table.dataTable th.evidence-data-column'),
    'two_status_ui_contract' => !str_contains($table, 'CLASSIFICATION_PENDING')
        && !str_contains($pageApp, 'CLASSIFICATION_PENDING'),
];

$failed = array_keys(array_filter($checks, static fn (bool $value): bool => !$value));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
