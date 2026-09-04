<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$db = DbPdo::conn();
$queries = [
    'account_columns' => "SELECT column_name, data_type FROM information_schema.columns WHERE table_schema=database() AND table_name='ledger_accounts' ORDER BY ordinal_position",
    'line_columns' => "SELECT column_name, data_type FROM information_schema.columns WHERE table_schema=database() AND table_name='ledger_voucher_lines' ORDER BY ordinal_position",
    'expense_accounts' => "SELECT a.id,a.account_code,a.account_name,a.account_group,a.normal_balance,a.level,a.parent_id,a.is_active,COALESCE(a.is_posting,1) is_posting,a.allow_sub_account,a.note,a.memo,a.deleted_at,p.account_code parent_code,p.account_name parent_name FROM ledger_accounts a LEFT JOIN ledger_accounts p ON p.id=a.parent_id WHERE a.deleted_at IS NULL AND a.account_code LIKE '551%' ORDER BY a.account_code",
    'rule_counts' => "SELECT r.rule_code,r.rule_name,r.item_code,r.accounting_role_code,r.debit_credit,a.account_code,a.account_name,r.priority_no,r.origin_code,r.rule_status,r.condition_hash,r.revision_no FROM ledger_journal_rules r JOIN ledger_accounts a ON a.id=r.account_id WHERE r.deleted_at IS NULL AND r.operation_type='PERSONAL_EXPENSE' ORDER BY r.sort_no,r.rule_code",
    'revision_count' => "SELECT action_code,COUNT(*) count FROM ledger_journal_rule_revisions GROUP BY action_code ORDER BY action_code",
    'migration_tables' => "SELECT table_name FROM information_schema.tables WHERE table_schema=database() AND table_name LIKE '%migration%' ORDER BY table_name",
    'source_ref_columns' => "SELECT table_name,column_name,data_type FROM information_schema.columns WHERE table_schema=database() AND table_name IN ('ledger_voucher_line_source_refs','ledger_evidence_links','ledger_evidence_employee_personal_expense','approval_personal_expense_items') ORDER BY table_name,ordinal_position",
    'voucher_line_usage' => "SELECT a.account_code,a.account_name,COUNT(*) line_count,SUM(vl.debit) debit_total,MAX(v.voucher_date) latest_voucher_date,MAX(v.voucher_no) latest_voucher_no FROM ledger_voucher_lines vl JOIN ledger_accounts a ON a.id=vl.account_id JOIN ledger_vouchers v ON v.id=vl.voucher_id WHERE vl.debit>0 GROUP BY a.id,a.account_code,a.account_name ORDER BY line_count DESC,a.account_code",
    'personal_expense_ref_policies' => "SELECT a.account_code,a.account_name,s.id account_sub_policy_id,s.ref_target,s.is_required,c.operation_type,c.accounting_role_code,c.effective_from,c.effective_to FROM ledger_accounts a LEFT JOIN ledger_accounts_sub s ON s.account_id=a.id LEFT JOIN ledger_account_context_ref_policies c ON c.account_sub_policy_id=s.id AND c.deleted_at IS NULL AND c.is_active=1 WHERE a.account_code IN ('551091','551380','551040','551220','551030','216100') ORDER BY a.account_code,s.sort_no",
];

$result = [];
foreach ($queries as $name => $sql) {
    try {
        $result[$name] = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $result[$name] = ['error' => $e->getMessage()];
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
