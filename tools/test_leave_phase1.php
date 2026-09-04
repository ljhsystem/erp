<?php

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';
require PROJECT_ROOT . '/core/Storage.php';

use App\Services\Institution\LeaveService;
use Core\DbPdo;
use Core\Session;

$pdo = DbPdo::conn();
$user = $pdo->query("SELECT u.id,u.username FROM auth_users u JOIN user_employees e ON e.user_id=u.id WHERE u.username='ljhsystem' AND u.is_active=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    throw new RuntimeException('검증할 직원 사용자가 없습니다.');
}
Session::start(30);
$_SESSION['user'] = ['id' => $user['id'], 'username' => $user['username']];
$service = new LeaveService($pdo);
$employeeId = $service->employeeIdForUser((string) $user['id']);
$typeId = (string) $pdo->query("SELECT id FROM institution_leave_types WHERE type_code='ANNUAL_PAID'")->fetchColumn();
$uuid = static function (): string {
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3) . '-8' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
};

$pdo->beginTransaction();
try {
    $scheduleRows = $pdo->query("SELECT id FROM institution_employment_contracts_weekly_schedules WHERE contract_id=(SELECT id FROM institution_employment_contracts WHERE employee_id=" . $pdo->quote($employeeId) . " AND contract_status='APPROVED' AND deleted_at IS NULL ORDER BY contract_start_date DESC LIMIT 1) AND day_type='WORKDAY'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($scheduleRows as $index => $scheduleId) {
        $stmt = $pdo->prepare('INSERT INTO institution_employment_contracts_break_schedules (id,weekly_schedule_id,sort_no,start_time,end_time,end_day_offset,created_at,created_by,updated_at,updated_by) VALUES (:id,:schedule,1,\'12:00:00\',\'13:00:00\',0,NOW(),:actor,NOW(),:actor_update)');
        $stmt->execute([':id'=>$uuid(),':schedule'=>$scheduleId,':actor'=>'SYSTEM:ROLLBACK_TEST',':actor_update'=>'SYSTEM:ROLLBACK_TEST']);
    }
    $grantKey = $uuid();
    $grant = $service->grant([
        'employee_id' => $employeeId, 'leave_type_id' => $typeId, 'base_year' => 2098,
        'granted_minutes' => 480, 'usable_from' => '2098-01-01', 'usable_to' => '2098-12-31',
        'expires_on' => '2098-12-31', 'reason' => 'rollback 검증 부여', 'request_key' => $grantKey,
    ]);
    $duplicate = $service->grant([
        'employee_id' => $employeeId, 'leave_type_id' => $typeId, 'base_year' => 2098,
        'granted_minutes' => 999, 'usable_from' => '2098-01-01', 'usable_to' => '2098-12-31',
        'reason' => 'rollback 중복', 'request_key' => $grantKey,
    ]);
    if ($grant['data']['id'] !== $duplicate['data']['id']) {
        throw new RuntimeException('부여 request_key 멱등성 검증 실패');
    }
    $service->adjust(['employee_id'=>$employeeId,'leave_type_id'=>$typeId,'base_year'=>2098,'amount_minutes'=>60,'reason'=>'rollback 양수 조정','request_key'=>$uuid()]);
    $service->adjust(['employee_id'=>$employeeId,'leave_type_id'=>$typeId,'base_year'=>2098,'amount_minutes'=>-30,'reason'=>'rollback 음수 조정','request_key'=>$uuid()]);
    try {
        $service->adjust(['employee_id'=>$employeeId,'leave_type_id'=>$typeId,'base_year'=>2098,'amount_minutes'=>-9999,'reason'=>'rollback 음수 초과','request_key'=>$uuid()]);
        throw new RuntimeException('마이너스 잔액이 차단되지 않았습니다.');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), '현재 잔액')) {
            throw $exception;
        }
    }
    $balances = $service->balances(['base_year'=>2098], $employeeId)['data'];
    $annual = array_values(array_filter($balances, static fn(array $row): bool => $row['leave_type_id'] === $typeId))[0] ?? null;
    if (!$annual || (int) $annual['balance_minutes'] !== 510) {
        throw new RuntimeException('불변 원장 잔액 합계 검증 실패');
    }
    $monday = new DateTimeImmutable('2098-01-01');
    while ((int) $monday->format('N') !== 1) $monday = $monday->modify('+1 day');
    $dates = [$monday->format('Y-m-d'),$monday->modify('+1 day')->format('Y-m-d')];
    foreach ([['FULL_DAY',480],['AM_HALF',240],['PM_HALF',240],['HOURLY',60]] as [$unit,$expectedMinutes]) {
        $item = ['leave_type_id'=>$typeId,'leave_date'=>$dates[0],'request_unit_code'=>$unit];
        if ($unit === 'HOURLY') {$item['requested_start_at']=$dates[0].' 10:00:00';$item['requested_end_at']=$dates[0].' 11:00:00';}
        $saved=$service->save(['reason'=>'rollback '.$unit,'request_key'=>$uuid(),'items'=>[$item]],$employeeId);
        $detail=$service->detail($saved['data']['id'])['data'];
        if((int)$detail['items'][0]['requested_minutes']!==$expectedMinutes)throw new RuntimeException($unit.' 분 계산 검증 실패');
    }
    $multi=$service->save(['reason'=>'rollback 연속 다일','request_key'=>$uuid(),'items'=>array_map(fn($date)=>['leave_type_id'=>$typeId,'leave_date'=>$date,'request_unit_code'=>'FULL_DAY'],$dates)],$employeeId);
    if((int)$service->detail($multi['data']['id'])['data']['requested_total_minutes']!==960)throw new RuntimeException('연속 다일 계산 검증 실패');
    try{$service->save(['reason'=>'rollback 비근무일','request_key'=>$uuid(),'items'=>[['leave_type_id'=>$typeId,'leave_date'=>$monday->modify('+6 day')->format('Y-m-d'),'request_unit_code'=>'FULL_DAY']]],$employeeId);throw new RuntimeException('비근무일 신청이 차단되지 않았습니다.');}catch(RuntimeException $exception){if(!str_contains($exception->getMessage(),'근무일'))throw $exception;}
    $approvalDate=$monday->modify('+7 day')->format('Y-m-d');
    $approvedDraft=$service->save(['reason'=>'rollback 승인 흐름','request_key'=>$uuid(),'items'=>[['leave_type_id'=>$typeId,'leave_date'=>$approvalDate,'request_unit_code'=>'FULL_DAY']]],$employeeId);
    $otherEmployeeId=(string)$pdo->query("SELECT id FROM user_employees WHERE id<>".$pdo->quote($employeeId)." ORDER BY sort_no LIMIT 1")->fetchColumn();
    if($otherEmployeeId==='')throw new RuntimeException('소유권 검증용 다른 직원이 없습니다.');
    $insertForeignRequest=$pdo->prepare("INSERT INTO institution_leave_requests (id,request_no,employee_id,request_kind_code,original_request_id,business_status_code,current_approval_request_id,reason,requested_total_minutes,request_key,created_at,created_by,updated_at,updated_by) VALUES (:id,:no,:employee,'LEAVE',NULL,:status,NULL,'rollback 타인 소유권 검증',0,:request_key,NOW(),'SYSTEM:ROLLBACK_TEST',NOW(),'SYSTEM:ROLLBACK_TEST')");
    $foreignDraftId=$uuid();$insertForeignRequest->execute([':id'=>$foreignDraftId,':no'=>'LV-OWN-DRAFT-'.substr(str_replace('-','',$foreignDraftId),0,8),':employee'=>$otherEmployeeId,':status'=>'DRAFT',':request_key'=>$uuid()]);
    try{$service->submit($foreignDraftId);throw new RuntimeException('다른 직원의 신청 상신이 차단되지 않았습니다.');}catch(RuntimeException $exception){if(!str_contains($exception->getMessage(),'다른 직원'))throw $exception;}
    try{$service->detailOwned($foreignDraftId);throw new RuntimeException('다른 직원의 신청 상세조회가 차단되지 않았습니다.');}catch(RuntimeException $exception){if(!str_contains($exception->getMessage(),'다른 직원'))throw $exception;}
    try{$service->save(['id'=>$foreignDraftId,'reason'=>'rollback 타인 수정','request_key'=>$uuid(),'items'=>[]],$employeeId);throw new RuntimeException('다른 직원의 신청 수정이 차단되지 않았습니다.');}catch(RuntimeException $exception){if(!str_contains($exception->getMessage(),'다른 직원'))throw $exception;}
    $foreignApprovedId=$uuid();$insertForeignRequest->execute([':id'=>$foreignApprovedId,':no'=>'LV-OWN-APPROVED-'.substr(str_replace('-','',$foreignApprovedId),0,8),':employee'=>$otherEmployeeId,':status'=>'APPROVED',':request_key'=>$uuid()]);
    try{$service->cancel($foreignApprovedId,$uuid(),'rollback 타인 취소');throw new RuntimeException('다른 직원의 승인 휴가 취소가 차단되지 않았습니다.');}catch(RuntimeException $exception){if(!str_contains($exception->getMessage(),'다른 직원'))throw $exception;}
    $submitted=$service->submit($approvedDraft['data']['id']);
    $stepStmt=$pdo->prepare("SELECT id FROM user_approval_request_steps WHERE request_id=:request AND status='pending' LIMIT 1");$stepStmt->execute([':request'=>$submitted['data']['request_id']]);$stepId=(string)$stepStmt->fetchColumn();
    $service->act($stepId,'approved','rollback 최종 승인');
    $approvedDetail=$service->detail($approvedDraft['data']['id'])['data'];
    if($approvedDetail['business_status_code']!=='APPROVED'||count($approvedDetail['usages'])!==1)throw new RuntimeException('최종 승인 usage 확정 검증 실패');
    $cancelled=$service->cancel($approvedDraft['data']['id'],$uuid(),'rollback 전체 취소');
    $cancelStepStmt=$pdo->prepare("SELECT id FROM user_approval_request_steps WHERE request_id=:request AND status='pending' LIMIT 1");$cancelStepStmt->execute([':request'=>$cancelled['data']['approval_request_id']]);$cancelStepId=(string)$cancelStepStmt->fetchColumn();
    $service->act($cancelStepId,'approved','rollback 취소 승인');
    $cancelledDetail=$service->detail($approvedDraft['data']['id'])['data'];
    if($cancelledDetail['business_status_code']!=='CANCELLED'||$cancelledDetail['usages'][0]['usage_status_code']!=='CANCELLED')throw new RuntimeException('승인 후 전체 취소 검증 실패');
    echo "leave phase1 rollback test passed\n";
} finally {
    $pdo->rollBack();
}
