<?php

require_once __DIR__ . '/../../app/Services/Institution/AttendanceCalculationPolicy.php';
require_once __DIR__ . '/../../app/Services/Concerns/LogsServiceOperations.php';
require_once __DIR__ . '/../../app/Services/Institution/AttendanceService.php';

use App\Services\Institution\AttendanceCalculationPolicy;
use App\Services\Institution\AttendanceService;

function expectSame(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($label . ': expected=' . var_export($expected, true) . ', actual=' . var_export($actual, true));
    }
}

$policy = new AttendanceCalculationPolicy();
$schedule = [
    'work_date' => '2026-08-17', 'workday' => true, 'work_schedule_type' => 'NORMAL',
    'start' => '2026-08-17 09:00:00', 'end' => '2026-08-17 18:00:00',
    'scheduled_seconds' => 28800, 'break_seconds' => 3600, 'exception' => null,
];
$working = ['value_data' => [
    'daily_legal_work_seconds' => 28800, 'weekly_legal_work_seconds' => 144000,
    'night_start_time' => '22:00:00', 'night_end_time' => '06:00:00', 'week_start_day' => 1,
]];
$holiday = ['value_data' => ['holidays' => []]];
$segments = [
    ['segment_type_code' => 'WORK', 'started_at' => '2026-08-17 09:00:00', 'ended_at' => '2026-08-17 18:00:00'],
    ['segment_type_code' => 'BREAK', 'started_at' => '2026-08-17 12:00:00', 'ended_at' => '2026-08-17 13:00:00'],
    ['segment_type_code' => 'OUTSIDE', 'started_at' => '2026-08-17 12:30:00', 'ended_at' => '2026-08-17 13:30:00'],
];
$result = $policy->calculate($schedule, $segments, [], $working, $holiday);
expectSame(5400, $result['actual_break_seconds'], 'BREAK/OUTSIDE union');
expectSame(27000, $result['actual_work_seconds'], 'actual work after exclusion');
expectSame(0, $result['contract_excess_seconds'], 'contract excess');
expectSame(0, $result['calculated_overtime_seconds'], 'legal overtime');

$caseA = $policy->calculate($schedule, [
    ['segment_type_code' => 'WORK', 'started_at' => '2026-08-17 09:00:00', 'ended_at' => '2026-08-17 18:00:00'],
    ['segment_type_code' => 'BREAK', 'started_at' => '2026-08-17 12:00:00', 'ended_at' => '2026-08-17 13:00:00'],
], [], $working, $holiday);
expectSame(28800, $caseA['actual_work_seconds'], 'A continuous clock with scheduled break');
expectSame(3600, $caseA['actual_break_seconds'], 'A scheduled break duration');
expectSame('CALCULATED', $caseA['calculation_status_code'], 'A scheduled break is final');

$caseB = $policy->calculate($schedule, [
    ['segment_type_code' => 'WORK', 'started_at' => '2026-08-17 09:00:00', 'ended_at' => '2026-08-17 12:00:00'],
    ['segment_type_code' => 'BREAK', 'started_at' => '2026-08-17 12:00:00', 'ended_at' => '2026-08-17 13:00:00'],
    ['segment_type_code' => 'WORK', 'started_at' => '2026-08-17 13:00:00', 'ended_at' => '2026-08-17 18:00:00'],
], [], $working, $holiday);
expectSame(28800, $caseB['actual_work_seconds'], 'B split clocks with scheduled break');
expectSame(3600, $caseB['actual_break_seconds'], 'B scheduled break duration');
expectSame('CALCULATED', $caseB['calculation_status_code'], 'B scheduled break is final');

$caseC = $policy->calculate($schedule, [
    ['segment_type_code' => 'WORK', 'started_at' => '2026-08-17 09:00:00', 'ended_at' => '2026-08-17 18:00:00'],
    ['segment_type_code' => 'BREAK', 'started_at' => '2026-08-17 12:00:00', 'ended_at' => '2026-08-17 13:00:00'],
], [], $working, $holiday);
expectSame(28800, $caseC['actual_work_seconds'], 'C administrator break correction');
expectSame('CALCULATED', $caseC['calculation_status_code'], 'C administrator break is final');

$missingBreak = $policy->calculate($schedule, [[
    'segment_type_code' => 'WORK', 'started_at' => '2026-08-17 09:00:00', 'ended_at' => '2026-08-17 18:00:00',
]], [], $working, $holiday);
expectSame('NEEDS_CONFIRMATION', $missingBreak['calculation_status_code'], 'missing required break needs confirmation');

$outsideEnvelope = $policy->calculate($schedule, [
    ['segment_type_code' => 'WORK', 'started_at' => '2026-08-17 09:00:00', 'ended_at' => '2026-08-17 18:00:00'],
    ['segment_type_code' => 'BREAK', 'started_at' => '2026-08-17 07:00:00', 'ended_at' => '2026-08-17 08:00:00'],
], [], $working, $holiday);
expectSame(0, $outsideEnvelope['actual_break_seconds'], 'break outside work envelope is ignored');
expectSame('NEEDS_CONFIRMATION', $outsideEnvelope['calculation_status_code'], 'outside break does not satisfy required break');

$service = (new ReflectionClass(AttendanceService::class))->newInstanceWithoutConstructor();
$automaticSegments = new ReflectionMethod(AttendanceService::class, 'automaticSegments');
$scheduleWithBreak = $schedule + ['breaks' => [[
    'start' => '2026-08-17 12:00:00', 'end' => '2026-08-17 13:00:00', 'seconds' => 3600,
]]];
$autoA = $automaticSegments->invoke($service, [
    ['event_type_code' => 'CLOCK_IN', 'occurred_at' => '2026-08-17 09:00:00'],
    ['event_type_code' => 'CLOCK_OUT', 'occurred_at' => '2026-08-17 18:00:00'],
], $scheduleWithBreak, 'daily-a', 'SYSTEM:TEST', '2026-08-17 18:00:00');
expectSame(1, count(array_filter($autoA, static fn(array $row): bool => $row['segment_type_code'] === 'BREAK')), 'A automatic scheduled break');

$autoB = $automaticSegments->invoke($service, [
    ['event_type_code' => 'CLOCK_IN', 'occurred_at' => '2026-08-17 09:00:00'],
    ['event_type_code' => 'CLOCK_OUT', 'occurred_at' => '2026-08-17 12:00:00'],
    ['event_type_code' => 'CLOCK_IN', 'occurred_at' => '2026-08-17 13:00:00'],
    ['event_type_code' => 'CLOCK_OUT', 'occurred_at' => '2026-08-17 18:00:00'],
], $scheduleWithBreak, 'daily-b', 'SYSTEM:TEST', '2026-08-17 18:00:00');
expectSame(2, count(array_filter($autoB, static fn(array $row): bool => $row['segment_type_code'] === 'WORK')), 'B automatic split work');
expectSame(1, count(array_filter($autoB, static fn(array $row): bool => $row['segment_type_code'] === 'BREAK')), 'B automatic scheduled break');

$long = $policy->calculate($schedule, [[
    'segment_type_code' => 'WORK', 'started_at' => '2026-08-17 09:00:00', 'ended_at' => '2026-08-17 19:00:00',
]], [], $working, $holiday);
expectSame(7200, $long['contract_excess_seconds'], 'contract excess is schedule based');
expectSame(7200, $long['calculated_overtime_seconds'], 'daily overtime is statutory based');

$weekly = $policy->calculate($schedule, [[
    'segment_type_code' => 'WORK', 'started_at' => '2026-08-17 09:00:00', 'ended_at' => '2026-08-17 19:00:00',
]], [], $working, $holiday, ['prior_actual_seconds' => 136800, 'prior_overtime_seconds' => 0]);
expectSame(28800, $weekly['calculated_overtime_seconds'], 'daily/weekly overtime is not duplicated');

$nightSchedule = $schedule + [];
$nightSchedule['work_date'] = '2026-08-17';
$nightSchedule['work_schedule_type'] = 'NIGHT';
$nightSchedule['start'] = '2026-08-17 21:00:00';
$nightSchedule['end'] = '2026-08-18 07:00:00';
$night = $policy->calculate($nightSchedule, [[
    'segment_type_code' => 'WORK', 'started_at' => '2026-08-17 21:00:00', 'ended_at' => '2026-08-18 07:00:00',
]], [], $working, $holiday);
expectSame(28800, $night['night_work_seconds'], 'night interval across midnight');
expectSame('2026-08-17', $policy->workDateForEvent('2026-08-18 06:30:00', ['2026-08-17' => $nightSchedule]), 'overnight work date');
expectSame(['2026-08-17', '2026-08-23'], $policy->weekRange('2026-08-19', 1), 'statutory week range');

echo "attendance policy regression: OK\n";
