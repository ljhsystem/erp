<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Institution\RegularEmploymentIncomeCalculationService;
use App\Services\Institution\RegularEmploymentIncomeService;
use Core\DbPdo;

$db = DbPdo::conn();
$user = $db->query("SELECT id,username FROM auth_users WHERE is_active=1 ORDER BY created_at,id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$user) throw new RuntimeException('회귀검사 Actor를 찾을 수 없습니다.');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$_SESSION['user'] = ['id'=>$user['id'],'username'=>$user['username']];
$_SESSION['auth_state'] = ['user_id'=>$user['id'],'status'=>'NORMAL'];

$employeeId = '6e8fb7ef-ea70-4d37-9aed-74f33b355127';
$settlements = [
    ['item_type_code'=>'DEDUCTION','settlement_parent_code'=>'HEALTH_INSURANCE','settlement_type_code'=>'ADDITIONAL_COLLECTION','settlement_period'=>'2012','final_amount'=>15000,'business_reason'=>'건강보험 정산 추징'],
    ['item_type_code'=>'DEDUCTION','settlement_parent_code'=>'HEALTH_INSURANCE','settlement_type_code'=>'REFUND','settlement_period'=>'2011','final_amount'=>2000,'business_reason'=>'건강보험 정산 환급'],
];
$calculation = new RegularEmploymentIncomeCalculationService($db);
$service = new RegularEmploymentIncomeService($db);
$db->beginTransaction();
try {
    $baseline = $calculation->preview('2013-09','2013-10-11',[['employee_id'=>$employeeId,'dependent_count_snapshot'=>1,'national_pension_basis_snapshot'=>988000,'health_insurance_basis_snapshot'=>988890,'employment_insurance_basis_snapshot'=>988890]],'SYSTEM:REGRESSION')['results'][0];
    $preview = $calculation->preview('2013-09','2013-10-11',[['employee_id'=>$employeeId,'dependent_count_snapshot'=>1,'national_pension_basis_snapshot'=>988000,'health_insurance_basis_snapshot'=>988890,'employment_insurance_basis_snapshot'=>988890,'deduction_line_items'=>$settlements]],'SYSTEM:REGRESSION');
    $save = $service->savePayEffect(['income_year_month'=>'2013-09','payment_date'=>'2013-10-11','title'=>'공제 정산 저장 Fixture','items'=>$preview['results']]);
    $documentId = (string) $save['data']['id'];
    $detail = $service->detail($documentId)['data'];
    $storedSettlements = array_values(array_filter($detail['items'][0]['line_items'], static fn(array $line): bool => str_starts_with((string)($line['source_key']??''),'SETTLEMENT|')));
    $resave = $service->savePayEffect(['id'=>$documentId,'income_year_month'=>'2013-09','payment_date'=>'2013-10-11','title'=>'공제 정산 저장 Fixture','items'=>$detail['items']]);
    $reopened = $service->detail((string)$resave['data']['id'])['data'];
    $reopenedSettlements = array_values(array_filter($reopened['items'][0]['line_items'], static fn(array $line): bool => str_starts_with((string)($line['source_key']??''),'SETTLEMENT|')));
    $checks = [
        'saved_two_settlement_lines' => count($storedSettlements)===2,
        'saved_totals' => (float)$detail['items'][0]['deduction_amount']===(float)$baseline['deduction_amount']+13000.0
            && (float)$detail['items'][0]['net_payment_amount']===(float)$baseline['net_payment_amount']-13000.0,
        'reopen_metadata' => count(array_filter($storedSettlements, static fn(array $line): bool => str_contains((string)$line['source_key'],'|ADDITIONAL_COLLECTION|2012|')))===1
            && count(array_filter($storedSettlements, static fn(array $line): bool => str_contains((string)$line['source_key'],'|REFUND|2011|')))===1,
        'resave_idempotent' => count($reopenedSettlements)===2&&(float)$reopened['items'][0]['deduction_amount']===(float)$baseline['deduction_amount']+13000.0,
    ];
    $failed = array_keys(array_filter($checks, static fn(bool $value): bool => !$value));
    echo json_encode(['success'=>$failed===[],'checks'=>$checks,'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),PHP_EOL;
    if ($failed!==[]) exit(1);
} finally {
    if ($db->inTransaction()) $db->rollBack();
}
