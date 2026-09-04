<?php

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';
require PROJECT_ROOT . '/core/Storage.php';

use App\Models\Institution\AttendanceModel;
use App\Services\Institution\AttendanceService;
use App\Services\System\StatutoryStandardService;
use Core\DbPdo;
use Core\Helpers\ActorHelper;

function attendanceUuid(): string
{
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3) . '-8' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
}

$pdo = DbPdo::conn();
$model = new AttendanceModel($pdo);
$employeeId = (string) $pdo->query('SELECT id FROM user_employees ORDER BY sort_no LIMIT 1')->fetchColumn();
if ($employeeId === '') {
    throw new RuntimeException('검증할 직원이 없습니다.');
}
$actor = ActorHelper::system('근태 rollback 테스트');
$workDate = '1990-12-30';
$now = date('Y-m-d H:i:s');
$pdo->beginTransaction();
try {
    $workingStandardId=attendanceUuid();$holidayStandardId=attendanceUuid();
    $model->insert('system_statutory_standards',['id'=>$workingStandardId,'sort_no'=>1,'standard_type_code'=>'WORKING_TIME_STANDARD','effective_from'=>'1990-01-01','effective_to'=>'1990-12-31','value_data'=>json_encode(['daily_legal_work_seconds'=>28800,'weekly_legal_work_seconds'=>144000,'week_start_day'=>1,'night_start_time'=>'22:00','night_end_time'=>'06:00'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'note'=>'rollback fixture','created_at'=>$now,'created_by'=>$actor,'updated_at'=>$now,'updated_by'=>$actor]);
    $model->insert('system_statutory_standards',['id'=>$holidayStandardId,'sort_no'=>1,'standard_type_code'=>'PUBLIC_HOLIDAY_CALENDAR','effective_from'=>'1990-01-01','effective_to'=>'1990-12-31','value_data'=>json_encode(['holidays'=>[]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'note'=>'rollback fixture','created_at'=>$now,'created_by'=>$actor,'updated_at'=>$now,'updated_by'=>$actor]);
    $clockIds = [];
    foreach ([['CLOCK_IN','09:00:00'],['CLOCK_OUT','12:00:00'],['CLOCK_IN','13:00:00'],['CLOCK_OUT','18:00:00']] as [$type,$time]) {
        $clockId = attendanceUuid();
        $clockIds[] = $clockId;
        $model->insert('institution_attendance_clock_events', [
            'id'=>$clockId,'employee_id'=>$employeeId,'event_type_code'=>$type,
            'occurred_at'=>$workDate.' '.$time,'collected_at'=>$now,'source_type_code'=>'SYSTEM',
            'external_key'=>null,'request_key'=>attendanceUuid(),'device_identifier'=>null,'source_payload'=>null,
            'is_valid'=>1,'invalid_reason'=>null,'created_at'=>$now,'created_by'=>$actor,
        ]);
    }
    $service = new AttendanceService($pdo);
    $method = new ReflectionMethod($service, 'recalculateInternal');
    $daily = $method->invoke($service, $employeeId, $workDate, $actor, false);
    $segments = $model->segments((string) $daily['id']);
    if (count(array_filter($segments, fn(array $row): bool => $row['segment_type_code'] === 'WORK')) !== 2) {
        throw new RuntimeException('분할 WORK 구간 pairing 검증 실패');
    }
    if ((int) $daily['actual_work_seconds'] !== 28800) {
        throw new RuntimeException('실제 근무초 검증 실패');
    }
    if ((string) $daily['working_time_standard_id'] !== $workingStandardId
        || (string) $daily['public_holiday_standard_id'] !== $holidayStandardId) {
        throw new RuntimeException('법정기준 Revision FK 스냅샷 검증 실패');
    }
    $statutoryService = new StatutoryStandardService($pdo, $actor);
    $immutability = new ReflectionMethod($statutoryService, 'assertAttendanceRevisionImmutable');
    $workingDetail = $pdo->query("SELECT * FROM system_statutory_standards WHERE id=" . $pdo->quote($workingStandardId))->fetch(PDO::FETCH_ASSOC);
    $workingDetail['sources'] = [];
    $changedData = $workingDetail;
    $changedData['value_data'] = json_encode(['daily_legal_work_seconds'=>1], JSON_UNESCAPED_UNICODE);
    try {
        $immutability->invoke($statutoryService, $workingDetail, $changedData, []);
        throw new RuntimeException('근태 참조 법정기준의 직접 수정이 차단되지 않았습니다.');
    } catch (ReflectionException $exception) {
        throw $exception;
    } catch (InvalidArgumentException $exception) {
        if (!str_contains($exception->getMessage(), 'Revision/Correction')) throw $exception;
    }
    $exceptionCodes = array_column($model->exceptions((string) $daily['id']), 'exception_type_code');
    if (!in_array('NO_SCHEDULE', $exceptionCodes, true)) {
        fwrite(STDERR, json_encode($exceptionCodes, JSON_UNESCAPED_UNICODE) . "\n");
        throw new RuntimeException('계약 없음 예외 검증 실패');
    }
    $noSchedule = array_values(array_filter(
        $model->exceptions((string) $daily['id']),
        fn(array $row): bool => $row['exception_type_code'] === 'NO_SCHEDULE'
            && $row['source_type_code'] === 'CALCULATION'
    ))[0];
    $method->invoke($service, $employeeId, $workDate, $actor, false);
    $sameExceptions = array_values(array_filter(
        $model->exceptions((string) $daily['id']),
        fn(array $row): bool => $row['exception_type_code'] === 'NO_SCHEDULE'
            && $row['source_type_code'] === 'CALCULATION'
    ));
    if (count($sameExceptions) !== 1 || $sameExceptions[0]['id'] !== $noSchedule['id']) {
        throw new RuntimeException('동일 자동 예외 재계산 멱등성 검증 실패');
    }

    $model->insert('institution_attendance_daily_exceptions', [
        'id'=>attendanceUuid(),'daily_record_id'=>$daily['id'],'exception_type_code'=>'LATE',
        'candidate_seconds'=>60,'source_type_code'=>'CALCULATION','resolution_status_code'=>'OPEN',
        'resolution_reason'=>null,'resolved_at'=>null,'resolved_by'=>null,
        'created_at'=>$now,'created_by'=>$actor,'updated_at'=>$now,'updated_by'=>$actor,
    ]);
    $manualExceptionId = attendanceUuid();
    $model->insert('institution_attendance_daily_exceptions', [
        'id'=>$manualExceptionId,'daily_record_id'=>$daily['id'],'exception_type_code'=>'LATE',
        'candidate_seconds'=>120,'source_type_code'=>'ADMIN','resolution_status_code'=>'OPEN',
        'resolution_reason'=>'수동 확인','resolved_at'=>null,'resolved_by'=>null,
        'created_at'=>$now,'created_by'=>$actor,'updated_at'=>$now,'updated_by'=>$actor,
    ]);
    $method->invoke($service, $employeeId, $workDate, $actor, false);
    $exceptionsAfterSync = $model->exceptions((string) $daily['id']);
    $calculatedLate = array_values(array_filter($exceptionsAfterSync, fn(array $row): bool => $row['exception_type_code'] === 'LATE' && $row['source_type_code'] === 'CALCULATION'))[0];
    $manualLate = array_values(array_filter($exceptionsAfterSync, fn(array $row): bool => $row['id'] === $manualExceptionId))[0];
    if ($calculatedLate['resolution_status_code'] !== 'RESOLVED' || $manualLate['resolution_status_code'] !== 'OPEN') {
        throw new RuntimeException('자동 예외 해소 또는 수동 예외 보존 검증 실패');
    }
    foreach ($model->exceptions((string) $daily['id']) as $exception) {
        $model->update('institution_attendance_daily_exceptions', $exception['id'], [
            'resolution_status_code'=>'RESOLVED','resolution_reason'=>'rollback 테스트 확인',
            'resolved_at'=>$now,'resolved_by'=>$actor,'updated_at'=>$now,'updated_by'=>$actor,
        ]);
    }
    $model->update('institution_attendance_daily_records',(string)$daily['id'],['calculation_status_code'=>'CALCULATED','updated_at'=>$now,'updated_by'=>$actor]);
    $testedService = new AttendanceService($pdo, static fn(): string => $actor);
    $detail = $testedService->detail($employeeId, $workDate)['data'];
    if (count($detail['clock_events'] ?? []) !== 4) throw new RuntimeException('출퇴근 원본 상세 검증 실패');
    $monthly = $testedService->monthlyList(['closing_month'=>'1990-12','start'=>0,'length'=>1], $employeeId);
    if (count($monthly['data']) !== 1 || (int) $monthly['recordsFiltered'] !== 1) {
        throw new RuntimeException('월별 서버 페이징 또는 본인 조회범위 검증 실패');
    }
    $closeKey1 = attendanceUuid();
    $firstClose = $testedService->close(['employee_id'=>$employeeId,'closing_month'=>'1990-12','reason'=>'rollback 최초 마감','request_key'=>$closeKey1]);
    if ((int) $firstClose['data']['revision'] !== 1) throw new RuntimeException('최초 revision 검증 실패');
    $duplicateClose = $testedService->close(['employee_id'=>$employeeId,'closing_month'=>'1990-12','reason'=>'rollback 최초 마감','request_key'=>$closeKey1]);
    if ((string) $duplicateClose['data']['id'] !== (string) $firstClose['data']['id']) throw new RuntimeException('마감 멱등성 검증 실패');
    try {$testedService->invalidateClock(['clock_event_id'=>$clockIds[0],'reason'=>'마감 차단 검증','request_key'=>attendanceUuid()]);throw new RuntimeException('마감월 원본 무효화가 차단되지 않았습니다.');}catch(RuntimeException $exception){if(!str_contains($exception->getMessage(),'마감된 월'))throw $exception;}
    try {$testedService->registerAdminClock(['employee_id'=>$employeeId,'event_type_code'=>'CLOCK_OUT','occurred_at'=>$workDate.' 19:00:00','reason'=>'마감 차단 검증','request_key'=>attendanceUuid()]);throw new RuntimeException('마감월 출퇴근 등록이 차단되지 않았습니다.');}catch(RuntimeException $exception){if(!str_contains($exception->getMessage(),'마감된 월'))throw $exception;}
    try {$testedService->recalculate(['employee_id'=>$employeeId,'work_date'=>$workDate,'request_key'=>attendanceUuid()]);throw new RuntimeException('마감월 재계산이 차단되지 않았습니다.');}catch(RuntimeException $exception){if(!str_contains($exception->getMessage(),'마감된 월'))throw $exception;}
    try {$testedService->correct(['daily_record_id'=>$daily['id'],'work_date'=>$workDate,'reason'=>'마감 차단 검증','request_key'=>attendanceUuid(),'segments'=>[['segment_type_code'=>'WORK','started_at'=>$workDate.' 09:00:00','ended_at'=>$workDate.' 17:00:00','project_id'=>null,'workplace_assignment_id'=>null]]]);throw new RuntimeException('마감월 관리자 정정이 차단되지 않았습니다.');}catch(RuntimeException $exception){if(!str_contains($exception->getMessage(),'마감된 월'))throw $exception;}
    try {$testedService->reopen(['employee_id'=>$employeeId,'closing_month'=>'1990-12','reason'=>'요청키 충돌 검증','request_key'=>$closeKey1]);throw new RuntimeException('다른 작업의 요청키 재사용이 차단되지 않았습니다.');}catch(RuntimeException $exception){if(!str_contains($exception->getMessage(),'다른 작업'))throw $exception;}
    $testedService->reopen(['employee_id'=>$employeeId,'closing_month'=>'1990-12','reason'=>'rollback 재오픈','request_key'=>attendanceUuid()]);
    $testedService->invalidateClock(['clock_event_id'=>$clockIds[0],'reason'=>'재오픈 후 무효화','request_key'=>attendanceUuid()]);
    $reopenedNoSchedule = array_values(array_filter(
        $model->exceptions((string) $daily['id']),
        fn(array $row): bool => $row['exception_type_code'] === 'NO_SCHEDULE'
            && $row['source_type_code'] === 'CALCULATION'
    ))[0];
    if ($reopenedNoSchedule['id'] !== $noSchedule['id'] || $reopenedNoSchedule['resolution_status_code'] !== 'OPEN') {
        throw new RuntimeException('해결된 자동 예외 재활성화 검증 실패');
    }
    if ($reopenedNoSchedule['resolution_reason'] !== 'rollback 테스트 확인' || $reopenedNoSchedule['resolved_by'] !== $actor) {
        throw new RuntimeException('관리자 예외 처리 이력 보존 검증 실패');
    }
    $testedService->registerAdminClock([
        'employee_id'=>$employeeId,'event_type_code'=>'CLOCK_IN','occurred_at'=>$workDate.' 09:00:00',
        'reason'=>'무효 원본 대체 등록','request_key'=>attendanceUuid(),
    ]);
    $testedService->correct([
        'daily_record_id'=>$daily['id'],'work_date'=>$workDate,'reason'=>'rollback 관리자 정정','request_key'=>attendanceUuid(),
        'segments'=>[[
            'segment_type_code'=>'WORK','started_at'=>$workDate.' 09:00:00','ended_at'=>$workDate.' 17:30:00',
            'project_id'=>null,'workplace_assignment_id'=>null,
        ]],
    ]);
    $correctionAudit = $pdo->query("SELECT COUNT(*) FROM institution_attendance_audits WHERE action_type_code='ADMIN_CORRECT'")->fetchColumn();
    if ((int) $correctionAudit !== 1) throw new RuntimeException('관리자 정정 감사 검증 실패');
    foreach ($model->exceptions((string) $daily['id']) as $exception) {
        if ($exception['resolution_status_code'] !== 'OPEN') continue;
        $model->update('institution_attendance_daily_exceptions', $exception['id'], [
            'resolution_status_code'=>'RESOLVED','resolution_reason'=>'rollback 재마감 확인',
            'resolved_at'=>$now,'resolved_by'=>$actor,'updated_at'=>$now,'updated_by'=>$actor,
        ]);
    }
    $model->update('institution_attendance_daily_records',(string)$daily['id'],['calculation_status_code'=>'CALCULATED','updated_at'=>$now,'updated_by'=>$actor]);
    $secondClose = $testedService->close(['employee_id'=>$employeeId,'closing_month'=>'1990-12','reason'=>'rollback 재마감','request_key'=>attendanceUuid()]);
    $closure = $model->closure($employeeId, '1990-12');
    $histories = $model->histories((string) $closure['id']);
    if ((int) $secondClose['data']['revision'] !== 2 || count($histories) !== 2) throw new RuntimeException('재마감 revision 검증 실패');
    if (count($model->histories((string) $closure['id'], attendanceUuid())) !== 0) throw new RuntimeException('마감 revision 본인 조회범위 검증 실패');
    if ((string) $closure['current_history_id'] !== (string) $secondClose['data']['id']) throw new RuntimeException('현재 history 포인터 검증 실패');
    if ((string) $firstClose['data']['ledger_hash'] === (string) $secondClose['data']['ledger_hash']) throw new RuntimeException('재마감 원장 해시 변경 검증 실패');
    echo "attendance rollback test passed\n";
} finally {
    $pdo->rollBack();
}
