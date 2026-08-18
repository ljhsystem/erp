<?php
declare(strict_types=1);
use App\Services\Institution\EmploymentContractService;
define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
$service = (new ReflectionClass(EmploymentContractService::class))->newInstanceWithoutConstructor();
$validate = new ReflectionMethod(EmploymentContractService::class, 'validateWeeklySchedules');
$project = new ReflectionMethod(EmploymentContractService::class, 'weeklyScheduleProjection');
$policy = new ReflectionMethod(EmploymentContractService::class, 'validateSchedulePolicy');
$schedule = new ReflectionMethod(EmploymentContractService::class, 'validateSchedule');
$week = static fn(string $type, int $offset = 0): array => array_map(static fn(int $day): array => [
    'day_of_week' => $day, 'day_type' => $day <= 5 ? 'WORKDAY' : ($day === 7 ? 'WEEKLY_HOLIDAY' : 'UNPAID_DAY_OFF'),
    'start_time' => $day <= 5 ? ($type === 'NIGHT' ? '22:00' : '09:00') : null,
    'end_time' => $day <= 5 ? ($type === 'NIGHT' ? '06:00' : '18:00') : null,
    'end_day_offset' => $day <= 5 ? $offset : null, 'break_minutes' => $day <= 5 ? 60 : null,
    'break_schedules' => $day <= 5 ? [[
        'start_time' => $type === 'NIGHT' ? '23:00' : '12:00',
        'end_time' => $type === 'NIGHT' ? '00:00' : '13:00',
        'end_day_offset' => $type === 'NIGHT' ? 1 : 0,
    ]] : [],
], range(1, 7));
$normal = $validate->invoke($service, $week('NORMAL'), 'NORMAL');
if ((float) $project->invoke($service, $normal)['weekly_hours'] !== 40.0) throw new RuntimeException('일반근무 projection 실패');
$databaseTimeWeek = $week('NORMAL');
$databaseTimeWeek[0]['start_time'] = '09:00:00';
$databaseTimeWeek[0]['end_time'] = '18:00:00';
$databaseTimeNormal = $validate->invoke($service, $databaseTimeWeek, 'NORMAL');
if ($databaseTimeNormal[0]['start_time'] !== '09:00' || $databaseTimeNormal[0]['end_time'] !== '18:00') {
    throw new RuntimeException('DB 시간 형식 정규화 실패');
}
$night = $validate->invoke($service, $week('NIGHT', 1), 'NIGHT');
if ((float) $project->invoke($service, $night)['weekly_hours'] !== 35.0) throw new RuntimeException('야간근무 익일 projection 실패');
try { $validate->invoke($service, $week('NIGHT', 0), 'NIGHT'); throw new RuntimeException('익일 누락 허용'); } catch (ReflectionException|InvalidArgumentException $e) {}
$selective = $policy->invoke($service, 'SELECTIVE', [
    'settlement_period_days' => 28, 'reference_weekly_hours' => 40,
    'selectable_start_time' => '07:00', 'selectable_end_time' => '22:00',
    'core_start_time' => '10:00', 'core_end_time' => '15:00', 'policy_detail' => '4주 정산',
]);
if ($selective[':reference_weekly_hours'] !== 40.0) throw new RuntimeException('선택근무 정책 실패');
foreach (['SHIFT', 'FLEXIBLE', 'OTHER'] as $type) {
    $input = ['policy_detail' => $type . ' 상세'];
    if ($type === 'FLEXIBLE') $input['settlement_period_days'] = 28;
    if (!array_key_exists(':policy_detail', $policy->invoke($service, $type, $input))) {
        throw new RuntimeException($type . ' 상세 정책 실패');
    }
}
$flexible = $schedule->invoke($service, 'FLEXIBLE', $week('FLEXIBLE'), [
    'settlement_period_days' => 28,
    'reference_weekly_hours' => 40, 'policy_detail' => '4주 정산 기준 반복일정',
]);
if (count($flexible[0]) !== 7 || $flexible[1] === null) {
    throw new RuntimeException('탄력근무 일정·정책 병행 실패');
}
$other = $schedule->invoke($service, 'OTHER', $week('OTHER'), [
    'policy_detail' => '기타 기준 반복일정',
]);
if (count($other[0]) !== 7 || $other[1] === null) {
    throw new RuntimeException('기타근무 일정·정책 병행 실패');
}
foreach (['SELECTIVE', 'SHIFT'] as $type) {
    try {
        $schedule->invoke($service, $type, $week($type), [
            'reference_weekly_hours' => 40,
            'selectable_start_time' => '07:00', 'selectable_end_time' => '22:00',
            'policy_detail' => $type . ' 정책',
        ]);
        throw new RuntimeException($type . ' 주간 반복일정이 허용되었습니다.');
    } catch (ReflectionException|InvalidArgumentException $exception) {
    }
}
echo "employment-contract schedule policy: OK\n";
