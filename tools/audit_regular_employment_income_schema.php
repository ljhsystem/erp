<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$db = Core\DbPdo::conn();
$tables = [
    'institution_regular_employment_incomes',
    'institution_regular_employment_income_items',
    'ledger_evidence_salary_report',
];

foreach ($tables as $table) {
    echo '=== ' . $table . ' ===' . PHP_EOL;
    $statement = $db->query('SHOW CREATE TABLE `' . $table . '`');
    $row = $statement->fetch(PDO::FETCH_NUM);
    echo (string) ($row[1] ?? '') . PHP_EOL;
}

foreach (['ledger_evidence_metadata', 'ledger_evidence_metadata_columns', 'user_approval_templates', 'user_approval_template_steps'] as $table) {
    echo '=== CONTRACT ' . $table . ' ===' . PHP_EOL;
    $statement = $db->query('SHOW CREATE TABLE `' . $table . '`');
    $row = $statement->fetch(PDO::FETCH_NUM);
    echo (string) ($row[1] ?? '') . PHP_EOL;
}

foreach ([
    'IMPORT_TYPE', 'SOURCE_TYPE', 'OPERATION_TYPE', 'TRANSACTION_DIRECTION',
    'BUSINESS_UNIT', 'APPROVAL_DOCUMENT_TYPE',
] as $group) {
    echo '=== CODE ' . $group . ' ===' . PHP_EOL;
    $statement = $db->prepare(
        'SELECT code,code_name,is_active FROM system_codes WHERE code_group=:code_group ORDER BY sort_no'
    );
    $statement->execute([':code_group' => $group]);
    echo json_encode($statement->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

echo '=== SALARY METADATA ===' . PHP_EOL;
$statement = $db->query(
    "SELECT * FROM ledger_evidence_metadata WHERE import_type IN ('PAYROLL','SALARY_REPORT')"
);
echo json_encode(
    $statement->fetchAll(PDO::FETCH_ASSOC),
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
) . PHP_EOL;

echo '=== PERSONAL EXPENSE METADATA ===' . PHP_EOL;
$statement = $db->prepare(
    'SELECT * FROM ledger_evidence_metadata WHERE import_type=:import_type AND deleted_at IS NULL'
);
$statement->execute([':import_type' => 'EMPLOYEE_EXPENSE_PERSONAL']);
$metadata = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
echo json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
if ($metadata !== []) {
    $statement = $db->prepare(
        'SELECT * FROM ledger_evidence_metadata_columns WHERE metadata_id=:metadata_id ORDER BY sort_no'
    );
    $statement->execute([':metadata_id' => $metadata['id']]);
    echo json_encode($statement->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
}

echo '=== PERSONAL EXPENSE APPROVAL STEPS ===' . PHP_EOL;
$statement = $db->query(
    "SELECT s.* FROM user_approval_template_steps s JOIN user_approval_templates t ON t.id=s.template_id WHERE t.document_type='PERSONAL_EXPENSE' ORDER BY s.sort_no"
);
echo json_encode($statement->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

echo '=== APPROVAL TEMPLATES ===' . PHP_EOL;
$statement = $db->query(
    'SELECT id,template_name,document_type,is_active FROM user_approval_templates ORDER BY sort_no'
);
echo json_encode(
    $statement->fetchAll(PDO::FETCH_ASSOC),
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
) . PHP_EOL;
