<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\Institution\AttendanceCalculationPolicy;
use App\Services\Institution\AttendanceWeeklyRecalculationService;

function attendancePolicyAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$policy = new AttendanceCalculationPolicy();
$normalSchedule = [
    'work_schedule_type' => 'NORMAL',
    'work_date' => '2026-08-17',
    'start' => '2026-08-17 09:00:00',
    'end' => '2026-08-17 18:00:00',
    'scheduled_seconds' => 28800,
    'break_seconds' => 3600,
    'exception' => null,
];
$normalSegments = [
    ['segment_type_code' => 'WORK', 'started_at' => '2026-08-17 09:00:00', 'ended_at' => '2026-08-17 18:00:00'],
    ['segment_type_code' => 'BREAK', 'started_at' => '2026-08-17 12:00:00', 'ended_at' => '2026-08-17 13:00:00'],
];
$normal = $policy->calculate($normalSchedule, $normalSegments, []);
attendancePolicyAssert($normal['actual_work_seconds'] === 28800, '정상근무 실제근로가 8시간이어야 합니다.');
attendancePolicyAssert($normal['actual_break_seconds'] === 3600, '실제 휴게가 1시간이어야 합니다.');
attendancePolicyAssert($normal['normal_work_seconds'] === 28800, '정상근로가 8시간이어야 합니다.');
attendancePolicyAssert($normal['calculated_overtime_seconds'] === 0, '법정기준 없이 연장근로를 확정하면 안 됩니다.');

$workingStandard=['id'=>'fixture-working-a','value_data'=>['daily_legal_work_seconds'=>28800,'weekly_legal_work_seconds'=>144000,'week_start_day'=>1,'night_start_time'=>'22:00','night_end_time'=>'06:00']];
$holidayStandard=['id'=>'fixture-holiday-a','value_data'=>['holidays'=>[['date'=>'2026-08-17','holiday_type'=>'PUBLIC_HOLIDAY','holiday_name'=>'Fixture 휴일']]]];
$statutoryNormal=$policy->calculate($normalSchedule,$normalSegments,[],$workingStandard,$holidayStandard);
attendancePolicyAssert($statutoryNormal['holiday_work_seconds']===28800,'법정공휴일 실제근로가 휴일근로로 분류되어야 합니다.');
attendancePolicyAssert($statutoryNormal['calculation_status_code']==='CALCULATED','법정기준과 공휴일 기준이 있으면 확정 가능해야 합니다.');

$overlappingBreaks = $normalSegments + [];
$overlappingBreaks[] = ['segment_type_code' => 'OUTSIDE', 'started_at' => '2026-08-17 12:30:00', 'ended_at' => '2026-08-17 13:30:00'];
$overlap = $policy->calculate($normalSchedule, $overlappingBreaks, []);
attendancePolicyAssert($overlap['actual_break_seconds'] === 5400, '겹치는 제외구간을 이중 차감하면 안 됩니다.');
attendancePolicyAssert($overlap['actual_work_seconds'] === 27000, 'WORK와 제외구간의 교집합만 차감해야 합니다.');

$excessSegments = [[
    'segment_type_code' => 'WORK',
    'started_at' => '2026-08-17 09:00:00',
    'ended_at' => '2026-08-17 19:00:00',
]];
$excess = $policy->calculate($normalSchedule, $excessSegments, []);
attendancePolicyAssert($excess['contract_excess_seconds'] === 7200, '계약 예정초과를 별도로 분류해야 합니다.');
attendancePolicyAssert($excess['calculated_overtime_seconds'] === 0, '계약 예정초과를 법정 연장으로 바꾸면 안 됩니다.');
attendancePolicyAssert($excess['calculation_status_code'] === 'NEEDS_CONFIRMATION', '미분류 예정초과는 확인 필요여야 합니다.');
$legalExcess=$policy->calculate($normalSchedule,$excessSegments,[],$workingStandard,$holidayStandard);
attendancePolicyAssert($legalExcess['contract_excess_seconds']===7200,'계약 예정초과 Projection이 유지되어야 합니다.');
attendancePolicyAssert($legalExcess['calculated_overtime_seconds']===7200,'법정 일 기준 초과가 별도로 계산되어야 합니다.');

$weekly=$policy->calculate($normalSchedule,$normalSegments,[],$workingStandard,$holidayStandard,['prior_actual_seconds'=>144000,'prior_overtime_seconds'=>0]);
attendancePolicyAssert($weekly['calculated_overtime_seconds']===28800,'주 기준 추가 연장근로가 계산되어야 합니다.');
$weeklyNoDouble=$policy->calculate($normalSchedule,$excessSegments,[],$workingStandard,$holidayStandard,['prior_actual_seconds'=>144000,'prior_overtime_seconds'=>0]);
attendancePolicyAssert($weeklyNoDouble['calculated_overtime_seconds']===36000,'일·주 연장 중복 없이 현재일 실제근로만 분류해야 합니다.');

$nightSchedule=array_replace($normalSchedule,['work_schedule_type'=>'NIGHT','work_date'=>'2026-08-18','start'=>'2026-08-18 22:00:00','end'=>'2026-08-19 06:00:00','scheduled_seconds'=>28800,'break_seconds'=>0]);
$nightSegments=[['segment_type_code'=>'WORK','started_at'=>'2026-08-18 22:00:00','ended_at'=>'2026-08-19 06:00:00']];
$night=$policy->calculate($nightSchedule,$nightSegments,[],$workingStandard,['id'=>'fixture-holiday-b','value_data'=>['holidays'=>[]]]);
attendancePolicyAssert($night['night_work_seconds']===28800,'자정 경계 야간근로가 계산되어야 합니다.');

$unsupported = $policy->calculate(array_replace($normalSchedule, ['work_schedule_type' => 'FLEXIBLE']), $normalSegments, []);
attendancePolicyAssert($unsupported['unsupported_schedule_type'] === true, '미지원 근무형태를 NORMAL로 계산하면 안 됩니다.');
attendancePolicyAssert($unsupported['calculation_status_code'] === 'NEEDS_CONFIRMATION', '미지원 근무형태는 확인 필요여야 합니다.');
foreach (['FLEXIBLE', 'SELECTIVE', 'SHIFT', 'OTHER'] as $unsupportedType) {
    $unsupportedResult = $policy->calculate(
        array_replace($normalSchedule, ['work_schedule_type' => $unsupportedType]),
        $normalSegments,
        [],
        $workingStandard,
        $holidayStandard
    );
    attendancePolicyAssert($unsupportedResult['unsupported_schedule_type'] === true, $unsupportedType . '을 NORMAL로 fallback하면 안 됩니다.');
    attendancePolicyAssert($unsupportedResult['calculation_status_code'] === 'NEEDS_CONFIRMATION', $unsupportedType . '은 확인 필요여야 합니다.');
}
$missingWorking = $policy->calculate($normalSchedule, $normalSegments, [], null, $holidayStandard);
attendancePolicyAssert($missingWorking['calculated_overtime_seconds'] === 0 && $missingWorking['calculation_status_code'] === 'NEEDS_CONFIRMATION', '근로시간 법정기준 미등록은 fallback 없이 확인 필요여야 합니다.');
$missingHoliday = $policy->calculate($normalSchedule, $normalSegments, [], $workingStandard, null);
attendancePolicyAssert($missingHoliday['holiday_work_seconds'] === 0 && $missingHoliday['calculation_status_code'] === 'NEEDS_CONFIRMATION', '공휴일 Calendar 미등록은 fallback 없이 확인 필요여야 합니다.');

$hourlyLeave = [[
    'request_unit_code' => 'HOURLY',
    'used_minutes' => 60,
    'leave_start_at' => '2026-08-17 09:00:00',
    'leave_end_at' => '2026-08-17 10:00:00',
]];
$lateStart = [[
    'segment_type_code' => 'WORK',
    'started_at' => '2026-08-17 10:00:00',
    'ended_at' => '2026-08-17 18:00:00',
]];
$leave = $policy->calculate($normalSchedule, $lateStart, $hourlyLeave);
attendancePolicyAssert($leave['late_candidate_seconds'] === 0, '예정 시작을 덮는 시간휴가는 지각에서 제외해야 합니다.');

$nightCandidates = [
    '2026-08-18' => ['workday' => true, 'start' => '2026-08-18 22:00:00', 'end' => '2026-08-19 06:00:00'],
    '2026-08-19' => ['workday' => false, 'start' => null, 'end' => null],
];
attendancePolicyAssert($policy->workDateForEvent('2026-08-19 06:00:00', $nightCandidates) === '2026-08-18', '익일 퇴근은 야간 일정 시작일에 귀속되어야 합니다.');
$normalCandidates = [
    '2026-08-18' => ['workday' => true, 'start' => '2026-08-18 09:00:00', 'end' => '2026-08-18 18:00:00'],
    '2026-08-19' => ['workday' => true, 'start' => '2026-08-19 09:00:00', 'end' => '2026-08-19 18:00:00'],
];
attendancePolicyAssert($policy->workDateForEvent('2026-08-19 09:00:00', $normalCandidates) === '2026-08-19', '일반근무의 다음날 출근을 전날과 합치면 안 됩니다.');

attendancePolicyAssert($policy->weekRange('2026-08-31', 1) === ['2026-08-31', '2026-09-06'], '월 경계 법정 주간 범위가 정확해야 합니다.');
$weeklyRows = [];
foreach ([28800, 28800, 28800, 28800, 36000] as $seconds) {
    $weeklyRows[] = ['actual_work_seconds' => $seconds, 'calculation_status_code' => 'CALCULATED', 'resolved_standard' => $workingStandard];
}
$weeklyAllocation = AttendanceWeeklyRecalculationService::allocateOvertime($weeklyRows);
attendancePolicyAssert(array_sum(array_column($weeklyAllocation, 'overtime_seconds')) === 7200, '일·주 법정 연장근로가 중복 산입되면 안 됩니다.');
attendancePolicyAssert($weeklyAllocation[4]['daily_excess_seconds'] === 7200, '일 기준 초과는 발생 날짜에 귀속되어야 합니다.');
$weeklyOnlyRows = [];
foreach ([28800, 28800, 28800, 28800, 28800, 14400] as $seconds) {
    $weeklyOnlyRows[] = ['actual_work_seconds' => $seconds, 'calculation_status_code' => 'CALCULATED', 'resolved_standard' => $workingStandard];
}
$weeklyOnlyAllocation = AttendanceWeeklyRecalculationService::allocateOvertime($weeklyOnlyRows);
attendancePolicyAssert($weeklyOnlyAllocation[5]['weekly_additional_seconds'] === 14400, '주 기준 추가 연장근로는 초과가 발생한 날짜에 귀속되어야 합니다.');

echo "AttendanceCalculationPolicy fixture PASS\n";
