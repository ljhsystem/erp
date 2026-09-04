<?php

declare(strict_types=1);

use App\Services\Ledger\TransactionReferenceValidatorService;
use Core\Database;

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/core/Storage.php';
require PROJECT_ROOT . '/core/Bootstrap.php';

$pdo = Database::getInstance()->getConnection();
$documentId = '4d9f970e-c6c5-4a7c-88ff-8a5cfdb47fb6';
$statement = $pdo->prepare(
    "SELECT income.id document_id,
            income.income_year_month,
            COALESCE(income.payroll_period_start_date, CONCAT(income.income_year_month, '-01')) period_from,
            COALESCE(income.payroll_period_end_date, LAST_DAY(CONCAT(income.income_year_month, '-01'))) period_to,
            item.id item_id,
            item.employee_id,
            item.employment_contract_id,
            employee.employment_status
       FROM institution_regular_employment_incomes income
       JOIN institution_regular_employment_income_items item
         ON item.regular_employment_income_id = income.id
        AND item.deleted_at IS NULL
       JOIN user_employees employee ON employee.id = item.employee_id
      WHERE income.id = :id
        AND income.deleted_at IS NULL
      ORDER BY item.sort_no"
);
$statement->execute([':id' => $documentId]);
$rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
if ($rows === []) {
    throw new RuntimeException('검증 대상 상용근로소득 원천행을 찾을 수 없습니다.');
}

$retired = null;
foreach ($rows as $row) {
    if ((string) $row['employment_status'] !== 'ACTIVE') {
        $retired = $row;
        break;
    }
}
if ($retired === null) {
    throw new RuntimeException('현재 비재직 상태인 과거 급여 검증 직원을 찾을 수 없습니다.');
}

$validator = new TransactionReferenceValidatorService($pdo);
$payload = [
    'employee_id' => $retired['employee_id'],
    'business_unit' => 'HQ',
    'transaction_direction' => 'EXPENSE',
    'operation_type' => 'PAYROLL',
    'currency' => 'KRW',
];
$context = [
    'employee_policy' => 'REGULAR_EMPLOYMENT_INCOME_EFFECTIVE_SNAPSHOT',
    'source_document_id' => $retired['document_id'],
    'source_item_id' => $retired['item_id'],
    'employment_contract_id' => $retired['employment_contract_id'],
    'period_from' => $retired['period_from'],
    'period_to' => $retired['period_to'],
];

$validator->validate($payload, null, $context);

$defaultBlocked = false;
try {
    $validator->validate($payload);
} catch (InvalidArgumentException) {
    $defaultBlocked = true;
}

$wrongPeriodBlocked = false;
try {
    $invalid = $context;
    $invalid['period_from'] = '2013-07-01';
    $validator->validate($payload, null, $invalid);
} catch (InvalidArgumentException) {
    $wrongPeriodBlocked = true;
}

$result = [
    'success' => $defaultBlocked && $wrongPeriodBlocked,
    'employee_id' => $retired['employee_id'],
    'current_employment_status' => $retired['employment_status'],
    'effective_snapshot_allowed' => true,
    'default_current_active_policy_blocked' => $defaultBlocked,
    'wrong_period_blocked' => $wrongPeriodBlocked,
];
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($result['success'] ? 0 : 1);
