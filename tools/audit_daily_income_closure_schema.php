<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$db = DbPdo::conn();
$tables = [
    'institution_daily_employment_incomes',
    'institution_daily_employment_income_groups',
    'institution_daily_employment_income_items',
    'institution_daily_employment_income_calculation_revisions',
    'institution_daily_employment_income_calculation_results',
    'ledger_evidence_daily_employment_income',
    'ledger_transactions',
    'ledger_evidence_links',
    'user_approval_templates',
    'user_approval_template_steps',
    'user_approval_requests',
    'user_approval_request_steps',
];

$result = [];
foreach ($tables as $table) {
    $exists = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name');
    $exists->execute([':table_name' => $table]);
    if ((int) $exists->fetchColumn() !== 1) {
        $result[$table] = ['exists' => false];
        continue;
    }
    $statement = $db->query('SHOW CREATE TABLE `' . $table . '`');
    $row = $statement->fetch(PDO::FETCH_NUM);
    $result[$table] = ['exists' => true, 'create_sql' => (string) ($row[1] ?? '')];
}

$templates = $db->query(
    "SELECT t.* FROM user_approval_templates t "
    . "WHERE t.document_type IN ('REGULAR_EMPLOYMENT_INCOME','PERSONAL_EXPENSE','DAILY_EMPLOYMENT_INCOME') "
    . 'ORDER BY t.document_type,t.created_at,t.id'
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
foreach ($templates as &$template) {
    $steps = $db->prepare('SELECT * FROM user_approval_template_steps WHERE template_id=:template_id ORDER BY sort_no,id');
    $steps->execute([':template_id' => $template['id']]);
    $template['steps'] = $steps->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
unset($template);

echo json_encode(['database' => $db->query('SELECT DATABASE()')->fetchColumn(), 'tables' => $result, 'templates' => $templates], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
