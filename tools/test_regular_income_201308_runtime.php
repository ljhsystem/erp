<?php
declare(strict_types=1);
define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';
use App\Services\Institution\RegularEmploymentIncomeCalculationService;
use App\Services\Institution\RegularEmploymentIncomeService;
use Core\DbPdo;
function runtimeAssert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$db = DbPdo::conn();
$income = new RegularEmploymentIncomeService($db);
$calculation = new RegularEmploymentIncomeCalculationService($db);
$targets = ['ce50c61c-8b08-4f58-b8bc-e11f1dbafb84', '6e8fb7ef-ea70-4d37-9aed-74f33b355127'];
$candidates = array_column($income->eligibleEmployees('2013-08')['data']['candidates'], null, 'employee_id');
$results = [];
foreach ($targets as $employeeId) {
    runtimeAssert(isset($candidates[$employeeId]), '2013-08 계산 대상 직원을 찾을 수 없습니다.');
    $preview = $calculation->preview('2013-08', '2013-09-11', [['employee_id'=>$employeeId,'dependent_count_snapshot'=>null]], 'SYSTEM:FIXTURE')['results'][0];
    runtimeAssert((float)$preview['gross_amount'] === 1088890.0, '계약 지급총액이 일치하지 않습니다.');
    runtimeAssert((float)$preview['taxable_amount'] === 988890.0, '과세 지급총액이 일치하지 않습니다.');
    runtimeAssert((float)$preview['non_taxable_amount'] === 100000.0, '비과세 지급총액이 일치하지 않습니다.');
    $results[$employeeId] = $preview;
}
echo json_encode(['success'=>true,'employees'=>$results], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
