<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

$db = DbPdo::conn();
$queries = [
    'regular' => "SELECT transaction_row.id,transaction_row.transaction_date current_transaction_date,LAST_DAY(CONCAT(evidence.raw_income_year_month,'-01')) expected_date,evidence.id evidence_id FROM ledger_evidence_links evidence_link JOIN ledger_evidence_salary_report evidence ON evidence.id=evidence_link.evidence_id JOIN ledger_transactions transaction_row ON transaction_row.id=evidence_link.target_id WHERE evidence_link.evidence_type='PAYROLL_REPORT' AND evidence_link.target_type='TRANSACTION' AND evidence_link.deleted_at IS NULL",
    'daily' => "SELECT transaction_row.id,transaction_row.transaction_date current_transaction_date,(SELECT MAX(workday.work_date) FROM institution_daily_employment_income_workdays workday WHERE workday.daily_employment_income_item_id=evidence.daily_employment_income_item_id) expected_date,evidence.id evidence_id FROM ledger_evidence_links evidence_link JOIN ledger_evidence_daily_employment_income evidence ON evidence.id=evidence_link.evidence_id JOIN ledger_transactions transaction_row ON transaction_row.id=evidence_link.target_id WHERE evidence_link.evidence_type='DAILY_EMPLOYMENT_INCOME' AND evidence_link.target_type='TRANSACTION' AND evidence_link.deleted_at IS NULL",
    'business' => "SELECT transaction_row.id,transaction_row.transaction_date current_transaction_date,item.transaction_date expected_date,evidence.id evidence_id FROM ledger_evidence_links evidence_link JOIN ledger_evidence_business_income evidence ON evidence.id=evidence_link.evidence_id JOIN institution_business_income_items item ON item.id=evidence.business_income_item_id JOIN ledger_transactions transaction_row ON transaction_row.id=evidence_link.target_id WHERE evidence_link.evidence_type='BUSINESS_INCOME_REPORT' AND evidence_link.target_type='TRANSACTION' AND evidence_link.deleted_at IS NULL",
];
$result = [];
foreach ($queries as $type => $sql) {
    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $result[$type] = [
        'count' => count($rows),
        'mismatches' => array_values(array_filter($rows, static fn(array $row): bool => (string) $row['current_transaction_date'] !== (string) $row['expected_date'])),
    ];
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
