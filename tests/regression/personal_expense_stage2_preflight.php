<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;
$pdo = DbPdo::conn();
$result = [];
$result['company'] = $pdo->query("SELECT id,found_date FROM system_company WHERE id='e2509853-5961-4db6-a2ee-1080da4ca98f'")->fetch(\PDO::FETCH_ASSOC) ?: null;
$result['account'] = $pdo->query("SELECT id,account_code,account_name,is_active,is_posting,deleted_at FROM ledger_accounts WHERE id='0e6378f9-cf0d-43b9-aec3-49d07f527d3d'")->fetch(\PDO::FETCH_ASSOC) ?: null;
$result['employee_policy'] = $pdo->query("SELECT id,account_id,ref_target,is_required FROM ledger_accounts_sub WHERE id='1df4c54c-f9a7-44e8-b99a-55984ff78192'")->fetch(\PDO::FETCH_ASSOC) ?: null;
$result['context_table_exists'] = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=database() AND table_name='ledger_account_context_ref_policies'")->fetchColumn();
$result['roles'] = $pdo->query("SELECT code,is_active FROM system_codes WHERE code_group='JOURNAL_ACCOUNTING_ROLE' ORDER BY sort_no,id")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
$result['expense_category_codes'] = $pdo->query("SELECT code,code_name,is_active FROM system_codes WHERE code_group='PERSONAL_EXPENSE_CATEGORY' ORDER BY sort_no,id")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
$result['fee_semantic_codes'] = $pdo->query("SELECT code_group,code,code_name,is_active FROM system_codes
    WHERE UPPER(code) REGEXP 'FEE|COMMISSION' OR code_name REGEXP '수수료|법무|등기'
    ORDER BY code_group,sort_no,id")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
$result['baseline'] = $pdo->query("SELECT config_value FROM system_settings_config WHERE config_key='journal_learning_policy.default'")->fetchColumn();
$result['journal_rule_columns'] = $pdo->query("SELECT column_name,is_nullable,column_default,data_type
    FROM information_schema.columns WHERE table_schema=database() AND table_name='ledger_journal_rules'
    ORDER BY ordinal_position")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
$result['settings_audit_tables'] = $pdo->query("SELECT table_name FROM information_schema.tables
    WHERE table_schema=database() AND table_name LIKE '%setting%audit%' ORDER BY table_name")->fetchAll(\PDO::FETCH_COLUMN) ?: [];
$result['personal_expense_rule_count'] = (int) $pdo->query("SELECT COUNT(*) FROM ledger_journal_rules
    WHERE operation_type='PERSONAL_EXPENSE' OR import_type='EMPLOYEE_EXPENSE_PERSONAL'")->fetchColumn();
$result['fixture'] = $pdo->query("SELECT evidence.id,evidence.source_personal_expense_item_id,
           item.personal_expense_id,evidence.raw_expense_category,item.expense_category AS source_expense_category,
           evidence.raw_item_name,evidence.raw_total_amount,evidence.employee_id,evidence.raw_expense_date,
           evidence.external_key,evidence.created_by,evidence.created_at,evidence.updated_by,evidence.updated_at,
           request.id AS approval_request_id,request.status AS approval_status,request.completed_at,
           link.target_id AS transaction_id
    FROM ledger_evidence_employee_personal_expense evidence
    INNER JOIN approval_personal_expense_items item ON item.id=evidence.source_personal_expense_item_id
    INNER JOIN approval_personal_expenses header ON header.id=item.personal_expense_id
    LEFT JOIN user_approval_requests request ON request.id=header.current_approval_request_id
    LEFT JOIN ledger_evidence_links link ON link.evidence_type=evidence.import_type AND link.evidence_id=evidence.id
       AND link.target_type='TRANSACTION' AND link.deleted_at IS NULL
    WHERE evidence.import_type='EMPLOYEE_EXPENSE_PERSONAL' AND evidence.operation_type='PERSONAL_EXPENSE'
      AND evidence.raw_expense_date BETWEEN '2013-07-01' AND '2013-07-31'
    ORDER BY raw_expense_date,evidence.sort_no")->fetchAll(\PDO::FETCH_ASSOC) ?: [];

$categoryTotals = [];
foreach ($result['fixture'] as $row) {
    $category = (string) $row['raw_expense_category'];
    $categoryTotals[$category] = ($categoryTotals[$category] ?? 0.0) + (float) $row['raw_total_amount'];
}
$result['fixture_category_totals'] = $categoryTotals;
$result['fixture_total'] = array_sum($categoryTotals);
$result['operating_change_count'] = 0;

echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
