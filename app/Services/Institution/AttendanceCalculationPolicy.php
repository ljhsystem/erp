<?php

namespace App\Services\Institution;

final class AttendanceCalculationPolicy
{
    public const VERSION = 1;
    private const SUPPORTED_SCHEDULE_TYPES = ['NORMAL', 'NIGHT'];

    public function calculate(array $schedule, array $segments, array $leaveUsages, ?array $workingStandard = null, ?array $holidayStandard = null, array $weekContext = []): array
    {
        $workIntervals = $this->intervals($segments, ['WORK']);
        $breakIntervals = $this->intervals($segments, ['BREAK', 'OUTSIDE']);
        $workSeconds = $this->unionSeconds($workIntervals);
        $excludedWorkSeconds = $this->intersectionSeconds($workIntervals, $breakIntervals);
        $workEnvelope = $workIntervals === [] ? [] : [[min(array_column($workIntervals, 0)), max(array_column($workIntervals, 1))]];
        $actualBreakSeconds = $this->intersectionSeconds($workEnvelope, $breakIntervals);
        $actualWorkSeconds = max(0, $workSeconds - $excludedWorkSeconds);

        $leaveSeconds = array_sum(array_map(
            static fn(array $usage): int => max(0, (int) ($usage['used_minutes'] ?? 0) * 60),
            $leaveUsages
        ));
        $units = array_column($leaveUsages, 'request_unit_code');
        $fullLeave = in_array('FULL_DAY', $units, true);
        $amLeave = in_array('AM_HALF', $units, true);
        $pmLeave = in_array('PM_HALF', $units, true);
        $startCoveredByLeave = $this->coveredByLeave((string) ($schedule['start'] ?? ''), $leaveUsages);
        $endCoveredByLeave = $this->coveredByLeave((string) ($schedule['end'] ?? ''), $leaveUsages);
        $scheduledSeconds = max(0, (int) ($schedule['scheduled_seconds'] ?? 0) - $leaveSeconds);
        $first = $workIntervals === [] ? null : date('Y-m-d H:i:s', min(array_column($workIntervals, 0)));
        $last = $workIntervals === [] ? null : date('Y-m-d H:i:s', max(array_column($workIntervals, 1)));
        $late = $first && !empty($schedule['start']) && !$amLeave && !$fullLeave && !$startCoveredByLeave
            ? max(0, strtotime($first) - strtotime((string) $schedule['start']))
            : 0;
        $early = $last && !empty($schedule['end']) && !$pmLeave && !$fullLeave && !$endCoveredByLeave
            ? max(0, strtotime((string) $schedule['end']) - strtotime($last))
            : 0;

        $scheduleType = strtoupper((string) ($schedule['work_schedule_type'] ?? ''));
        $supported = in_array($scheduleType, self::SUPPORTED_SCHEDULE_TYPES, true);
        $contractExcessSeconds = max(0, $actualWorkSeconds - $scheduledSeconds);
        $statutoryReady = $workingStandard !== null && $holidayStandard !== null;
        $dailyLegal = max(0, (int) ($workingStandard['value_data']['daily_legal_work_seconds'] ?? 0));
        $weeklyLegal = max(0, (int) ($workingStandard['value_data']['weekly_legal_work_seconds'] ?? 0));
        $dailyOvertime = $dailyLegal > 0 ? max(0, $actualWorkSeconds - $dailyLegal) : 0;
        $priorActual = max(0, (int) ($weekContext['prior_actual_seconds'] ?? 0));
        $priorOvertime = max(0, (int) ($weekContext['prior_overtime_seconds'] ?? 0));
        $weeklyAdditional = $weeklyLegal > 0 ? max(0, $priorActual + $actualWorkSeconds - $weeklyLegal - $priorOvertime - $dailyOvertime) : 0;
        $legalOvertime = $dailyOvertime + $weeklyAdditional;
        $holiday = $this->isPublicHoliday((string) ($schedule['work_date'] ?? ''), $holidayStandard);
        $nightSeconds = $workingStandard ? $this->nightSeconds($workIntervals, $breakIntervals, (string) ($workingStandard['value_data']['night_start_time'] ?? ''), (string) ($workingStandard['value_data']['night_end_time'] ?? '')) : 0;
        $needsConfirmation = !$supported
            || !empty($schedule['exception'])
            || !empty($schedule['calculation_issue_code'])
            || !$statutoryReady || $dailyLegal===0 || $weeklyLegal===0
            || ((int) ($schedule['break_seconds'] ?? 0) > 0 && $actualBreakSeconds === 0);

        return [
            'actual_work_seconds' => $actualWorkSeconds,
            'actual_break_seconds' => $actualBreakSeconds,
            'scheduled_work_seconds' => $scheduledSeconds,
            'normal_work_seconds' => min($actualWorkSeconds, $scheduledSeconds),
            'contract_excess_seconds' => $contractExcessSeconds,
            'calculated_overtime_seconds' => $legalOvertime,
            'night_work_seconds' => $nightSeconds,
            'holiday_work_seconds' => $holiday ? $actualWorkSeconds : 0,
            'late_candidate_seconds' => $late,
            'early_leave_candidate_seconds' => $early,
            'first_clock_in_at' => $first,
            'last_clock_out_at' => $last,
            'calculation_status_code' => $needsConfirmation ? 'NEEDS_CONFIRMATION' : 'CALCULATED',
            'calculation_version' => self::VERSION,
            'unsupported_schedule_type' => !$supported,
            'statutory_classification_pending' => !$statutoryReady,
        ];
    }

    public function weekRange(string $workDate, int $weekStartDay): array
    {
        if ($weekStartDay < 1 || $weekStartDay > 7) throw new \InvalidArgumentException('주 시작요일이 올바르지 않습니다.');
        $day=(int)date('N',strtotime($workDate));$offset=($day-$weekStartDay+7)%7;$from=date('Y-m-d',strtotime($workDate.' -'.$offset.' day'));
        return [$from,date('Y-m-d',strtotime($from.' +6 day'))];
    }

    public function workDateForEvent(string $occurredAt, array $candidateSchedules): string
    {
        $eventTime = strtotime($occurredAt);
        if ($eventTime === false) throw new \InvalidArgumentException('출퇴근 일시 형식이 올바르지 않습니다.');
        foreach ($candidateSchedules as $workDate => $schedule) {
            if (empty($schedule['workday']) || empty($schedule['start']) || empty($schedule['end'])) continue;
            if ($eventTime >= strtotime((string) $schedule['start']) && $eventTime <= strtotime((string) $schedule['end'])) {
                return (string) $workDate;
            }
        }
        return date('Y-m-d', $eventTime);
    }

    private function intervals(array $segments, array $types): array
    {
        $intervals = [];
        foreach ($segments as $segment) {
            if (!in_array((string) ($segment['segment_type_code'] ?? ''), $types, true)) continue;
            $start = strtotime((string) ($segment['started_at'] ?? ''));
            $end = strtotime((string) ($segment['ended_at'] ?? ''));
            if ($start === false || $end === false || $end <= $start) continue;
            $intervals[] = [$start, $end];
        }
        return $this->merge($intervals);
    }

    private function merge(array $intervals): array
    {
        usort($intervals, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($intervals as $interval) {
            $last = count($merged) - 1;
            if ($last < 0 || $interval[0] > $merged[$last][1]) {
                $merged[] = $interval;
                continue;
            }
            $merged[$last][1] = max($merged[$last][1], $interval[1]);
        }
        return $merged;
    }

    private function unionSeconds(array $intervals): int
    {
        return array_sum(array_map(static fn(array $interval): int => $interval[1] - $interval[0], $intervals));
    }

    private function intersectionSeconds(array $work, array $breaks): int
    {
        $seconds = 0;
        foreach ($work as $workInterval) {
            foreach ($breaks as $breakInterval) {
                $seconds += max(0, min($workInterval[1], $breakInterval[1]) - max($workInterval[0], $breakInterval[0]));
            }
        }
        return $seconds;
    }

    private function coveredByLeave(string $scheduledAt, array $leaveUsages): bool
    {
        $point = strtotime($scheduledAt);
        if ($point === false) return false;
        foreach ($leaveUsages as $usage) {
            $start = strtotime((string) ($usage['leave_start_at'] ?? ''));
            $end = strtotime((string) ($usage['leave_end_at'] ?? ''));
            if ($start !== false && $end !== false && $start <= $point && $end >= $point) return true;
        }
        return false;
    }

    private function isPublicHoliday(string $workDate, ?array $standard): bool
    {
        foreach((array)($standard['value_data']['holidays']??[]) as $holiday)if(($holiday['date']??'')===$workDate&&in_array($holiday['holiday_type']??'',['PUBLIC_HOLIDAY','SUBSTITUTE_PUBLIC_HOLIDAY'],true))return true;
        return false;
    }

    private function nightSeconds(array $work, array $breaks, string $startTime, string $endTime): int
    {
        if(!preg_match('/^\d{2}:\d{2}(:\d{2})?$/',$startTime)||!preg_match('/^\d{2}:\d{2}(:\d{2})?$/',$endTime))return 0;
        $total=0;foreach($work as $interval){$from=date('Y-m-d',$interval[0]-86400);$to=date('Y-m-d',$interval[1]);for($date=$from;$date<=$to;$date=date('Y-m-d',strtotime($date.' +1 day'))){$a=strtotime($date.' '.$startTime);$b=strtotime($date.' '.$endTime);if($b<=$a)$b+=86400;$seconds=max(0,min($interval[1],$b)-max($interval[0],$a));foreach($breaks as $break)$seconds-=max(0,min($interval[1],$b,$break[1])-max($interval[0],$a,$break[0]));$total+=max(0,$seconds);}}
        return $total;
    }
}
