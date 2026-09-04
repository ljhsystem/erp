<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

use Core\DbPdo;

$db = DbPdo::conn();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$scalar = static function (string $sql) use ($db): int {
    return (int) $db->query($sql)->fetchColumn();
};
$rows = static function (string $sql) use ($db): array {
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
};

$personalUnresolved = $rows("SELECT evidence.id
    FROM ledger_evidence_employee_personal_expense evidence
    LEFT JOIN approval_personal_expense_items item ON item.id=evidence.source_personal_expense_item_id
    LEFT JOIN approval_personal_expenses header ON header.id=item.personal_expense_id
    LEFT JOIN user_approval_requests request
      ON request.id=header.current_approval_request_id
     AND request.document_type='PERSONAL_EXPENSE' AND request.document_id=header.id
    WHERE item.id IS NULL OR header.id IS NULL OR request.id IS NULL
       OR request.status<>'approved'
       OR (SELECT COUNT(*) FROM user_approval_request_steps final_step
            WHERE final_step.request_id=request.id AND final_step.step_type='FINAL_APPROVAL'
              AND final_step.status='approved' AND final_step.is_active=1)<>1
    ORDER BY evidence.id");

$result = [
    'mode' => 'READ_ONLY_SELECT_DRY_RUN',
    'database' => (string) $db->query('SELECT DATABASE()')->fetchColumn(),
    'daily' => [
        'target_rows' => $scalar('SELECT COUNT(*) FROM ledger_evidence_daily_employment_income'),
        'amount_formula_mismatch_rows' => $scalar('SELECT COUNT(*) FROM ledger_evidence_daily_employment_income WHERE ROUND(total_gross_amount-total_deduction_amount,2)<>ROUND(total_net_payment_amount,2)'),
        'business_unit_unresolved_rows' => $scalar("SELECT COUNT(*) FROM ledger_evidence_daily_employment_income evidence LEFT JOIN institution_daily_employment_income_groups income_group ON income_group.id=evidence.daily_employment_income_group_id LEFT JOIN system_codes code ON code.code_group='BUSINESS_UNIT' AND code.code=income_group.business_unit AND code.is_active=1 WHERE income_group.id IS NULL OR code.id IS NULL"),
    ],
    'salary' => [
        'target_rows' => $scalar('SELECT COUNT(*) FROM ledger_evidence_salary_report'),
        'source_or_amount_mismatch_rows' => $scalar('SELECT COUNT(*) FROM ledger_evidence_salary_report evidence LEFT JOIN institution_regular_employment_incomes header ON header.id=evidence.source_regular_employment_income_id LEFT JOIN institution_regular_employment_income_items item ON item.id=evidence.regular_employment_income_item_id WHERE header.id IS NULL OR item.id IS NULL OR ROUND(evidence.raw_gross_amount,2)<>ROUND(item.gross_amount,2) OR ROUND(evidence.raw_deduction_amount,2)<>ROUND(item.deduction_amount,2)'),
    ],
    'personal_expense' => [
        'target_rows' => $scalar('SELECT COUNT(*) FROM ledger_evidence_employee_personal_expense'),
        'manual_review_required_rows' => count($personalUnresolved),
        'manual_review_evidence_ids' => array_column($personalUnresolved, 'id'),
    ],
    'table_settings' => [
        'daily_rows' => $scalar("SELECT COUNT(*) FROM system_user_settings WHERE setting_type='TABLE' AND page_key='evidence-daily-employment-income' AND settings_json REGEXP 'total_work_days|total_gross_amount|total_deduction_amount|total_net_payment_amount|total_employer_burden_amount|evidence_status_code'"),
        'salary_rows' => $scalar("SELECT COUNT(*) FROM system_user_settings WHERE setting_type='TABLE' AND page_key IN ('evidence-payroll','evidence-payroll-report') AND settings_json REGEXP 'raw_gross_amount|raw_deduction_amount'"),
        'salary_employee_count_rows' => $scalar("SELECT COUNT(*) FROM system_user_settings WHERE setting_type='TABLE' AND page_key IN ('evidence-payroll','evidence-payroll-report') AND settings_json LIKE '%raw_employee_count%'"),
    ],
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
