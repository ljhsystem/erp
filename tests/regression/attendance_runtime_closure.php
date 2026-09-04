<?php

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';
require PROJECT_ROOT . '/core/Storage.php';

use App\Models\Institution\AttendanceModel;
use App\Services\Institution\AttendanceService;
use Core\DbPdo;
use Core\Helpers\ActorHelper;
function runtimeUuid(): string
{
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3)
        . '-8' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
}

function runtimeAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function runtimeCounts(PDO $pdo, array $tables): array
{
    $counts = [];
    foreach ($tables as $table) $counts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    return $counts;
}

$pdo = DbPdo::conn();
$model = new AttendanceModel($pdo);
$tables = [
    'institution_employment_contracts',
    'institution_employment_contracts_weekly_schedules',
    'institution_employment_contracts_break_schedules',
    'institution_attendance_clock_events',
    'institution_attendance_daily_records',
    'institution_attendance_work_segments',
    'institution_attendance_daily_exceptions',
    'institution_attendance_audits',
    'institution_attendance_monthly_closures',
    'institution_attendance_monthly_closure_histories',
    'system_statutory_standards',
];
$beforeCounts = runtimeCounts($pdo, $tables);
$sourceContract = $pdo->query("SELECT * FROM institution_employment_contracts WHERE contract_status='APPROVED' AND deleted_at IS NULL ORDER BY approved_at DESC,id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
runtimeAssert(is_array($sourceContract), '복제 기준으로 사용할 승인 근로계약이 없습니다.');
$sourceSchedules = $pdo->query('SELECT * FROM institution_employment_contracts_weekly_schedules WHERE contract_id=' . $pdo->quote((string) $sourceContract['id']) . ' ORDER BY day_of_week')->fetchAll(PDO::FETCH_ASSOC);
runtimeAssert(count($sourceSchedules) === 7, '복제 기준 근로계약의 주간일정 7건이 필요합니다.');

$fixture = [
    'contract_id' => runtimeUuid(),
    'working_standard_id' => runtimeUuid(),
    'holiday_standard_id' => runtimeUuid(),
    'work_date' => '1990-12-31',
    'clock_in_key' => runtimeUuid(),
    'clock_out_key' => runtimeUuid(),
    'recalculate_key' => runtimeUuid(),
    'close_key' => runtimeUuid(),
];
$employeeId = (string) $sourceContract['employee_id'];
$actor = ActorHelper::system('근태 Runtime Closure rollback Fixture');
$now = date('Y-m-d H:i:s');
$results = [];

$pdo->beginTransaction();
try {
    $contract = $sourceContract;
    $contract['id'] = $fixture['contract_id'];
    $contract['sort_no'] = (int) $sourceContract['sort_no'] + 100000;
    $contract['contract_no'] = 'ROLLBACK-' . str_replace('-', '', $fixture['contract_id']);
    $contract['previous_contract_id'] = null;
    $contract['revision_no'] = 1;
    $contract['revision_reason'] = 'rollback Fixture';
    $contract['contract_start_date'] = '1990-01-01';
    if ($contract['contract_period_type'] === 'FIXED_TERM') $contract['contract_end_date'] = '1990-12-31';
    else {$contract['contract_end_date'] = null;$contract['fixed_term_reason_code'] = null;$contract['fixed_term_reason_detail'] = null;}
    $contract['work_schedule_type'] = 'NORMAL';
    $contract['probation_start_date'] = null;
    $contract['probation_end_date'] = null;
    $contract['probation_rate'] = null;
    $contract['contract_status'] = 'APPROVED';
    $contract['current_approval_request_id'] = null;
    $contract['approved_at'] = $now;
    $contract['terminated_at'] = null;
    $contract['termination_reason'] = null;
    $contract['note'] = 'attendance runtime rollback fixture';
    $contract['created_at'] = $now;
    $contract['created_by'] = $actor;
    $contract['updated_at'] = $now;
    $contract['updated_by'] = $actor;
    $contract['deleted_at'] = null;
    $contract['deleted_by'] = null;
    $model->insert('institution_employment_contracts', $contract);

    $mondayScheduleId = '';
    foreach ($sourceSchedules as $sourceSchedule) {
        $schedule = $sourceSchedule;
        $schedule['id'] = runtimeUuid();
        $schedule['contract_id'] = $fixture['contract_id'];
        if ((int) $schedule['day_of_week'] === 1) {
            $mondayScheduleId = $schedule['id'];
            $schedule['day_type'] = 'WORKDAY';
            $schedule['start_time'] = '09:00:00';
            $schedule['end_time'] = '18:00:00';
            $schedule['end_day_offset'] = 0;
            $schedule['break_minutes'] = 60;
        }
        $schedule['created_at'] = $now;
        $schedule['created_by'] = $actor;
        $schedule['updated_at'] = $now;
        $schedule['updated_by'] = $actor;
        $model->insert('institution_employment_contracts_weekly_schedules', $schedule);
    }
    runtimeAssert($mondayScheduleId !== '', '월요일 Fixture 일정을 생성하지 못했습니다.');

    $model->insert('system_statutory_standards', [
        'id'=>$fixture['working_standard_id'],'sort_no'=>100000,'standard_type_code'=>'WORKING_TIME_STANDARD',
        'effective_from'=>'1990-01-01','effective_to'=>'1990-12-31',
        'value_data'=>json_encode(['daily_legal_work_seconds'=>28800,'weekly_legal_work_seconds'=>144000,'week_start_day'=>1,'night_start_time'=>'22:00','night_end_time'=>'06:00'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        'note'=>'attendance runtime rollback fixture','created_at'=>$now,'created_by'=>$actor,'updated_at'=>$now,'updated_by'=>$actor,
    ]);
    $model->insert('system_statutory_standards', [
        'id'=>$fixture['holiday_standard_id'],'sort_no'=>100000,'standard_type_code'=>'PUBLIC_HOLIDAY_CALENDAR',
        'effective_from'=>'1990-01-01','effective_to'=>'1990-12-31','value_data'=>json_encode(['holidays'=>[]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        'note'=>'attendance runtime rollback fixture','created_at'=>$now,'created_by'=>$actor,'updated_at'=>$now,'updated_by'=>$actor,
    ]);

    $service = new AttendanceService($pdo, static fn(): string => $actor);
    $scenarioA = $service->registerAdminClock([
        'employee_id'=>$employeeId,'event_type_code'=>'CLOCK_IN','occurred_at'=>$fixture['work_date'].' 09:00:00',
        'reason'=>'Scenario A rollback Fixture','request_key'=>$fixture['clock_in_key'],
    ]);
    $dailyA = $scenarioA['data']['daily_record'] ?? null;
    runtimeAssert(is_array($dailyA), 'Scenario A daily_records 생성 실패');
    runtimeAssert((string) $dailyA['work_date'] === $fixture['work_date'], 'Scenario A work_date 결정 실패');
    runtimeAssert((string) $dailyA['calculation_status_code'] === 'NEEDS_CONFIRMATION', 'Scenario A NEEDS_CONFIRMATION 미발생');
    $exceptionsA = $service->exceptionList(['draw'=>1,'start'=>0,'length'=>50,'date_from'=>$fixture['work_date'],'date_to'=>$fixture['work_date']], $employeeId);
    runtimeAssert((int) $exceptionsA['recordsFiltered'] === 1, 'Scenario A 누락·이상 근태 조회 대상 누락');
    $detailA = $service->detail($employeeId, $fixture['work_date'])['data'];
    runtimeAssert(($detailA['calculation_issue_code'] ?? null) === null, '상세 휴게구간 미지정이 근태 계산 오류로 처리되었습니다.');
    $results['A'] = ['clock_events'=>1,'work_date'=>$dailyA['work_date'],'calculation_status'=>$dailyA['calculation_status_code'],'exception_rows'=>$exceptionsA['recordsFiltered'],'message'=>$detailA['calculation_issue_message']];

    $scenarioB = $service->registerAdminClock([
        'employee_id'=>$employeeId,'event_type_code'=>'CLOCK_OUT','occurred_at'=>$fixture['work_date'].' 18:00:00',
        'reason'=>'Scenario B rollback Fixture','request_key'=>$fixture['clock_out_key'],
    ]);
    $dailyB = $scenarioB['data']['daily_record'] ?? null;
    runtimeAssert(is_array($dailyB), 'Scenario B daily_records 재계산 실패');
    $eventsB = $model->validEvents($employeeId, $fixture['work_date'].' 00:00:00', '1991-01-02 00:00:00');
    $segmentsB = $model->segments((string) $dailyB['id']);
    runtimeAssert(count($eventsB) === 2, 'Scenario B CLOCK_IN/CLOCK_OUT 2건 보존 실패');
    runtimeAssert(count(array_filter($eventsB, static fn(array $row): bool => (int) $row['is_valid'] === 1)) === 2, 'Scenario B 원천 유효성 보존 실패');
    runtimeAssert(count(array_filter($segmentsB, static fn(array $row): bool => $row['segment_type_code'] === 'WORK')) === 1, 'Scenario B WORK 구간 생성 실패');
    runtimeAssert((int) $dailyB['actual_work_seconds'] === 32400, 'Scenario B 잠정 실제시간 9시간 계산 실패');
    runtimeAssert((string) $dailyB['calculation_status_code'] === 'NEEDS_CONFIRMATION', 'Scenario B NEEDS_CONFIRMATION 유지 실패');
    $closeBlocked = false;
    try {$service->close(['employee_id'=>$employeeId,'closing_month'=>'1990-12','reason'=>'Scenario B 마감 Guard','request_key'=>$fixture['close_key']]);}
    catch (RuntimeException $exception) {$closeBlocked = str_contains($exception->getMessage(), '확인 필요');}
    runtimeAssert($closeBlocked, 'Scenario B 월 마감 Guard 차단 실패');
    $eventSnapshot = array_map(static fn(array $row): array => ['id'=>$row['id'],'event_type_code'=>$row['event_type_code'],'occurred_at'=>$row['occurred_at'],'is_valid'=>(int)$row['is_valid']], $eventsB);
    $results['B'] = ['clock_events'=>count($eventsB),'work_segments'=>count($segmentsB),'actual_work_seconds'=>(int)$dailyB['actual_work_seconds'],'calculation_status'=>$dailyB['calculation_status_code'],'close_blocked'=>$closeBlocked];

    $model->insert('institution_employment_contracts_break_schedules', [
        'id'=>runtimeUuid(),'weekly_schedule_id'=>$mondayScheduleId,'sort_no'=>1,'start_time'=>'12:00:00','end_time'=>'13:00:00','end_day_offset'=>0,
        'created_at'=>$now,'created_by'=>$actor,'updated_at'=>$now,'updated_by'=>$actor,
    ]);
    $scenarioC = $service->recalculate(['employee_id'=>$employeeId,'work_date'=>$fixture['work_date'],'request_key'=>$fixture['recalculate_key']]);
    $dailyC = $scenarioC['data'];
    $segmentsC = $model->segments((string) $dailyC['id']);
    $workC = array_values(array_filter($segmentsC, static fn(array $row): bool => $row['segment_type_code'] === 'WORK'));
    $breakC = array_values(array_filter($segmentsC, static fn(array $row): bool => $row['segment_type_code'] === 'BREAK'));
    runtimeAssert(count($workC) === 1 && count($breakC) === 1, 'Scenario C WORK/BREAK 중복 또는 누락');
    runtimeAssert($workC[0]['started_at'] === $fixture['work_date'].' 09:00:00' && $workC[0]['ended_at'] === $fixture['work_date'].' 18:00:00', 'Scenario C WORK 구간 불일치');
    runtimeAssert($breakC[0]['started_at'] === $fixture['work_date'].' 12:00:00' && $breakC[0]['ended_at'] === $fixture['work_date'].' 13:00:00', 'Scenario C BREAK 구간 불일치');
    runtimeAssert((int) $dailyC['actual_work_seconds'] === 28800 && (int) $dailyC['actual_break_seconds'] === 3600, 'Scenario C 8시간 근로·1시간 휴게 계산 실패');
    runtimeAssert((string) $dailyC['calculation_status_code'] === 'CALCULATED', 'Scenario C NEEDS_CONFIRMATION 해소 실패');
    $eventsC = $model->validEvents($employeeId, $fixture['work_date'].' 00:00:00', '1991-01-02 00:00:00');
    $eventSnapshotAfter = array_map(static fn(array $row): array => ['id'=>$row['id'],'event_type_code'=>$row['event_type_code'],'occurred_at'=>$row['occurred_at'],'is_valid'=>(int)$row['is_valid']], $eventsC);
    runtimeAssert($eventSnapshotAfter === $eventSnapshot, 'Scenario C 재계산이 clock_events 원본을 변경함');
    $registerAudits = (int) $pdo->query("SELECT COUNT(*) FROM institution_attendance_audits WHERE employee_id=".$pdo->quote($employeeId)." AND target_id IN (".$pdo->quote($eventSnapshot[0]['id']).','.$pdo->quote($eventSnapshot[1]['id']).") AND action_type_code='REGISTER'")->fetchColumn();
    $recalculateAudits = (int) $pdo->query("SELECT COUNT(*) FROM institution_attendance_audits WHERE employee_id=".$pdo->quote($employeeId)." AND action_type_code='RECALCULATE' AND request_key=".$pdo->quote($fixture['recalculate_key']))->fetchColumn();
    runtimeAssert($registerAudits === 2 && $recalculateAudits === 1, 'Scenario C 감사로그 정책 검증 실패');
    $results['C'] = ['work_segments'=>count($workC),'break_segments'=>count($breakC),'actual_work_seconds'=>(int)$dailyC['actual_work_seconds'],'actual_break_seconds'=>(int)$dailyC['actual_break_seconds'],'calculation_status'=>$dailyC['calculation_status_code'],'register_audits'=>$registerAudits,'recalculate_audits'=>$recalculateAudits];
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
}

$afterCounts = runtimeCounts($pdo, $tables);
runtimeAssert($afterCounts === $beforeCounts, 'ROLLBACK 후 관련 테이블 전체 건수가 일치하지 않습니다.');
$targetChecks = [
    'contract'=>(int)$pdo->query('SELECT COUNT(*) FROM institution_employment_contracts WHERE id='.$pdo->quote($fixture['contract_id']))->fetchColumn(),
    'clock_events'=>(int)$pdo->query('SELECT COUNT(*) FROM institution_attendance_clock_events WHERE request_key IN ('.$pdo->quote($fixture['clock_in_key']).','.$pdo->quote($fixture['clock_out_key']).')')->fetchColumn(),
    'daily_records'=>(int)$pdo->query('SELECT COUNT(*) FROM institution_attendance_daily_records WHERE employee_id='.$pdo->quote($employeeId).' AND work_date='.$pdo->quote($fixture['work_date']))->fetchColumn(),
    'audits'=>(int)$pdo->query('SELECT COUNT(*) FROM institution_attendance_audits WHERE request_key IN ('.$pdo->quote($fixture['clock_in_key']).','.$pdo->quote($fixture['clock_out_key']).','.$pdo->quote($fixture['recalculate_key']).','.$pdo->quote($fixture['close_key']).')')->fetchColumn(),
    'standards'=>(int)$pdo->query('SELECT COUNT(*) FROM system_statutory_standards WHERE id IN ('.$pdo->quote($fixture['working_standard_id']).','.$pdo->quote($fixture['holiday_standard_id']).')')->fetchColumn(),
];
runtimeAssert(array_sum($targetChecks) === 0, 'ROLLBACK 후 Fixture key 잔존 데이터가 있습니다.');

echo json_encode(['success'=>true,'fixture'=>['employee_id'=>$employeeId,'work_date'=>$fixture['work_date']],'scenarios'=>$results,'before_counts'=>$beforeCounts,'after_counts'=>$afterCounts,'target_checks'=>$targetChecks], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . PHP_EOL;
