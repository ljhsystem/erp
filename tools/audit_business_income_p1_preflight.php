<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;
use App\Services\System\StatutoryStandardResolver;

$db = DbPdo::conn();
$rows = static fn (string $sql): array => $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
$scalar = static fn (string $sql): int|string => $db->query($sql)->fetchColumn();

$targetTables = [
    'system_statutory_standards',
    'system_statutory_standard_revisions',
    'system_statutory_standard_items',
    'system_client_tax_profiles',
    'institution_business_incomes',
    'institution_business_income_revisions',
    'institution_business_income_calculation_results',
    'institution_business_income_approval_links',
    'institution_business_income_artifact_links',
    'ledger_evidence_business_income_raw_lines',
    'ledger_account_rules',
    'ledger_account_roles',
];
$quoted = implode(',', array_map(static fn (string $table): string => $db->quote($table), $targetTables));
$existing = $rows("SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ({$quoted}) ORDER BY TABLE_NAME");

$standards = [];
$standardTables = array_column($existing, 'TABLE_NAME');
if (in_array('system_statutory_standards', $standardTables, true)) {
    $columns = array_column($rows("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='system_statutory_standards' ORDER BY ORDINAL_POSITION"), 'COLUMN_NAME');
    $codeColumn = in_array('standard_type_code', $columns, true)
        ? 'standard_type_code'
        : (in_array('standard_code', $columns, true) ? 'standard_code' : (in_array('code', $columns, true) ? 'code' : null));
    if ($codeColumn !== null) {
        $standards = $rows("SELECT * FROM system_statutory_standards WHERE `{$codeColumn}` IN ('BUSINESS_INCOME_WITHHOLDING','LOCAL_INCOME_TAX_WITHHOLDING')");
    }
}

$businessTables = $rows("SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE '%business_income%' ORDER BY TABLE_NAME");
$migrationFiles = glob(PROJECT_ROOT . '/app/migrations/20260903_0[1-9]_*.up.sql') ?: [];
$statutoryColumns = $rows("SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='system_statutory_standards' ORDER BY ORDINAL_POSITION");
$statutoryExamples = $rows("SELECT standard_type_code,effective_from,effective_to,value_data FROM system_statutory_standards ORDER BY effective_from DESC,id LIMIT 5");
$metadataColumns = $rows("SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_metadata' ORDER BY ORDINAL_POSITION");
$metadata = $rows("SELECT * FROM ledger_evidence_metadata WHERE import_type IN ('BUSINESS_INCOME','BUSINESS_INCOME_REPORT')");
$metadataExamples = $rows("SELECT * FROM ledger_evidence_metadata WHERE import_type IN ('PAYROLL_REPORT','DAILY_WORK_REPORT','DAILY_EMPLOYMENT_INCOME') ORDER BY import_type");
$codes = $rows("SELECT code_group,code,code_name,is_active FROM system_codes WHERE code_group IN ('IMPORT_TYPE','SOURCE_TYPE','TRANSACTION_DIRECTION','OPERATION_TYPE') AND code IN ('BUSINESS_INCOME','BUSINESS_INCOME_REPORT','INTERNAL_APPROVAL','OUT') ORDER BY code_group,code");
$codeGroups = $rows("SELECT code_group,code,code_name,is_active FROM system_codes WHERE code_group IN ('IMPORT_TYPE','SOURCE_TYPE','TRANSACTION_DIRECTION','OPERATION_TYPE') ORDER BY code_group,sort_no,code");
$dependencies = ['system_clients','user_approval_requests','user_approval_templates','user_approval_template_steps','system_page_registry','auth_permissions','auth_roles','auth_role_permissions','ledger_transactions','ledger_transaction_items','ledger_transaction_settlements','ledger_evidence_links','ledger_evidence_metadata'];
$dependencyQuoted = implode(',', array_map(static fn (string $table): string => $db->quote($table), $dependencies));
$requiredPolicyFields = ['method','discard_below_unit','stage','base_value_code','aggregation_unit','application_order','threshold','threshold_comparison'];
$resolver = new StatutoryStandardResolver($db);
$policyChecks = [];
foreach (['2013-06-30','2024-06-30','2024-07-01',date('Y-m-d')] as $paymentDate) {
    foreach (['BUSINESS_INCOME_WITHHOLDING','LOCAL_INCOME_TAX_WITHHOLDING'] as $type) {
        try {
            $revision = $resolver->resolve($type, $paymentDate);
            $policy = (array)($revision['value_data']['calculation_policy'] ?? []);
            $missing = array_values(array_diff($requiredPolicyFields, array_keys($policy)));
            $policyChecks[] = ['date'=>$paymentDate,'type'=>$type,'revision_id'=>$revision['id'],'missing_fields'=>$missing,'ready'=>$missing===[]];
        } catch (Throwable $exception) {
            $policyChecks[] = ['date'=>$paymentDate,'type'=>$type,'revision_id'=>null,'missing_fields'=>$requiredPolicyFields,'ready'=>false,'error'=>$exception->getMessage()];
        }
    }
}
$statutoryPolicyReady = !in_array(false, array_column($policyChecks, 'ready'), true);

echo json_encode([
    'success' => true,
    'database' => (string) $scalar('SELECT DATABASE()'),
    'mariadb_version' => (string) $scalar('SELECT VERSION()'),
    'migration_history_tables' => array_column($rows("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE '%migration%' ORDER BY TABLE_NAME"), 'TABLE_NAME'),
    'migration_files' => array_map('basename', $migrationFiles),
    'statutory_policy_ready' => $statutoryPolicyReady,
    'statutory_policy_checks' => $policyChecks,
    'target_tables' => $existing,
    'business_income_tables' => $businessTables,
    'statutory_standards' => $standards,
    'statutory_columns' => $statutoryColumns,
    'statutory_examples' => $statutoryExamples,
    'evidence_metadata_columns' => $metadataColumns,
    'evidence_metadata' => $metadata,
    'evidence_metadata_examples' => $metadataExamples,
    'canonical_codes' => $codes,
    'canonical_code_groups' => $codeGroups,
    'dependencies' => $rows("SELECT TABLE_NAME,TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ({$dependencyQuoted}) ORDER BY TABLE_NAME"),
    'approval_source_templates' => $rows("SELECT template_row.id,template_row.template_key,template_row.document_type,COUNT(step_row.id) active_steps FROM user_approval_templates template_row LEFT JOIN user_approval_template_steps step_row ON step_row.template_id=template_row.id AND step_row.is_active=1 WHERE template_row.document_type IN ('DAILY_EMPLOYMENT_INCOME','BUSINESS_INCOME') AND template_row.is_active=1 GROUP BY template_row.id,template_row.template_key,template_row.document_type ORDER BY template_row.document_type,template_row.sort_no"),
    'account_candidates' => $rows("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND (COLUMN_NAME LIKE '%account%role%' OR COLUMN_NAME IN ('account_code','account_id','role_code')) ORDER BY TABLE_NAME, ORDINAL_POSITION"),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
