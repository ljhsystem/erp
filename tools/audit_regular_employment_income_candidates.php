<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Models\Institution\EmploymentContractModel;
use App\Services\Institution\EmploymentContractValidityService;
use App\Services\Institution\RegularEmploymentIncomeService;
use App\Services\Institution\RegularEmploymentIncomeCalculationService;
use Core\Helpers\ActorHelper;
use Core\DbPdo;

$month = $argv[1] ?? '2013-07';
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    throw new InvalidArgumentException('귀속연월은 YYYY-MM 형식이어야 합니다.');
}

$from = $month . '-01';
$to = date('Y-m-t', strtotime($from));
$db = DbPdo::conn();
$employees = $db->prepare(
    "SELECT e.id, e.employee_name, e.employment_status,
            COALESCE(e.real_hire_date, e.doc_hire_date) hire_date,
            COALESCE(e.real_retire_date, e.doc_retire_date) retire_date,
            EXISTS(
                SELECT 1 FROM institution_job_assignments_employment_status_histories h
                 WHERE h.employee_id = e.id
                   AND h.effective_date <= :period_to
                   AND COALESCE(h.ended_date, '9999-12-31') >= :period_from
                   AND h.status_code IN ('ACTIVE', 'ON_LEAVE')
            ) history_overlap
       FROM user_employees e
      WHERE COALESCE(e.real_hire_date, e.doc_hire_date, '0001-01-01') <= :period_to_employee
        AND COALESCE(e.real_retire_date, e.doc_retire_date, '9999-12-31') >= :period_from_employee
      ORDER BY e.employee_name, e.id"
);
$employees->execute([
    ':period_from' => $from,
    ':period_to' => $to,
    ':period_from_employee' => $from,
    ':period_to_employee' => $to,
]);
$contracts = (new EmploymentContractValidityService(new EmploymentContractModel($db)))
    ->effectiveContractsForPeriod($from, $to);
$questions = static function () use ($db): int {
    $row = $db->query("SHOW SESSION STATUS LIKE 'Questions'")->fetch(PDO::FETCH_ASSOC);
    return (int) ($row['Value'] ?? 0);
};
$questionsBefore = $questions();
$apiResult = (new RegularEmploymentIncomeService($db))->eligibleEmployees($month);
$queryCount = $questions() - $questionsBefore - 1;
$candidateIds = array_column($apiResult['data']['candidates'] ?? [], 'employee_id');
$paymentDate = $argv[2] ?? '';
$calculation = $candidateIds !== [] && preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)
    ? (new RegularEmploymentIncomeCalculationService($db))->preview(
        $month,
        $paymentDate,
        $candidateIds,
        ActorHelper::system('상용근로소득 계산 읽기전용 감사')
    )
    : null;

echo json_encode([
    'month' => $month,
    'period' => [$from, $to],
    'employees' => $employees->fetchAll(PDO::FETCH_ASSOC) ?: [],
    'contracts' => array_map(static fn(array $row): array => [
        'id' => $row['id'],
        'employee_id' => $row['employee_id'],
        'contract_start_date' => $row['contract_start_date'],
        'contract_end_date' => $row['contract_end_date'],
        'contract_status' => $row['contract_status'],
        'revision_no' => $row['revision_no'],
        'deleted_at' => $row['deleted_at'],
    ], $contracts),
    'api_result' => $apiResult,
    'candidate_api_query_count' => $queryCount,
    'n_plus_one' => $queryCount > 4,
    'calculation' => $calculation,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
