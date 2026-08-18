<?php

namespace App\Services\Institution;

use App\Models\Institution\AttendanceModel;
use App\Services\System\StatutoryStandardResolver;

final class AttendanceWeeklyRecalculationService
{
    public function __construct(
        private readonly AttendanceModel $model,
        private readonly StatutoryStandardResolver $statutory,
        private readonly AttendanceCalculationPolicy $policy,
        private readonly AttendanceScheduleService $schedule
    ) {
    }

    public function recalculate(string $employeeId, string $workDate, string $actor): array
    {
        $anchor = $this->statutory->resolveOptional('WORKING_TIME_STANDARD', $workDate);
        $weekStartDay = (int) ($anchor['value_data']['week_start_day'] ?? 0);
        if ($weekStartDay < 1 || $weekStartDay > 7) {
            return [];
        }

        [$weekStart, $weekEnd] = $this->policy->weekRange($workDate, $weekStartDay);
        $rows = $this->model->weekRowsForUpdate($employeeId, $weekStart, $weekEnd);
        $this->assertNoClosedMonth($employeeId, $rows);

        $inputs = [];
        foreach ($rows as $row) {
            $standard = $this->statutory->resolveOptional('WORKING_TIME_STANDARD', (string) $row['work_date']);
            $inputs[] = $row + ['resolved_standard' => $standard];
        }
        $allocations = self::allocateOvertime($inputs);
        $updated = [];
        foreach ($inputs as $index => $row) {
            $standard = $row['resolved_standard'];
            $allocation = $allocations[$index];
            $this->model->update('institution_attendance_daily_records', (string) $row['id'], [
                'working_time_standard_id' => $standard['id'] ?? null,
                'calculated_overtime_seconds' => $allocation['overtime_seconds'],
                'calculation_status_code' => $allocation['calculation_status_code'],
                'calculation_version' => AttendanceCalculationPolicy::VERSION,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $actor,
            ]);
            $updated[] = (string) $row['id'];
        }

        return ['week_start' => $weekStart, 'week_end' => $weekEnd, 'daily_record_ids' => $updated];
    }

    public static function allocateOvertime(array $rows): array
    {
        $weeklyActual = 0;
        $weeklyDailyExcess = 0;
        $result = [];
        foreach ($rows as $row) {
            $standard = $row['resolved_standard'] ?? null;
            $dailyLegal = max(0, (int) ($standard['value_data']['daily_legal_work_seconds'] ?? 0));
            $weeklyLegal = max(0, (int) ($standard['value_data']['weekly_legal_work_seconds'] ?? 0));
            $actual = max(0, (int) ($row['actual_work_seconds'] ?? 0));
            $dailyExcess = $dailyLegal > 0 ? max(0, $actual - $dailyLegal) : 0;
            $weeklyAdditional = $weeklyLegal > 0
                ? max(0, $weeklyActual + $actual - $weeklyLegal - $weeklyDailyExcess - $dailyExcess)
                : 0;
            $result[] = [
                'daily_excess_seconds' => $dailyExcess,
                'weekly_additional_seconds' => $weeklyAdditional,
                'overtime_seconds' => $dailyExcess + $weeklyAdditional,
                'calculation_status_code' => $standard === null || $dailyLegal === 0 || $weeklyLegal === 0
                    ? 'NEEDS_CONFIRMATION'
                    : (string) ($row['calculation_status_code'] ?? 'CALCULATED'),
            ];
            $weeklyActual += $actual;
            $weeklyDailyExcess += $dailyExcess;
        }
        return $result;
    }

    public function assertMonthBoundaryReady(string $employeeId, string $month, string $asOfDate, bool $lock = false): void
    {
        $monthEnd = date('Y-m-t', strtotime($month . '-01'));
        $standard = $this->statutory->resolveOptional('WORKING_TIME_STANDARD', $monthEnd);
        $weekStartDay = (int) ($standard['value_data']['week_start_day'] ?? 0);
        if ($weekStartDay < 1 || $weekStartDay > 7) {
            throw new \RuntimeException('마지막 법정 주간의 기준을 확인할 수 없어 마감할 수 없습니다.');
        }
        [, $weekEnd] = $this->policy->weekRange($monthEnd, $weekStartDay);
        if ($weekEnd > $monthEnd && $asOfDate < $weekEnd) {
            throw new \RuntimeException('귀속월 마지막 날이 포함된 법정 주간이 아직 종료되지 않아 근태를 마감할 수 없습니다.');
        }
        if ($weekEnd <= $monthEnd) {
            return;
        }

        $nextMonth = substr(date('Y-m-d', strtotime($monthEnd . ' +1 day')), 0, 7);
        $nextClosure = $this->model->closure($employeeId, $nextMonth, $lock);
        if ($nextClosure && (string) $nextClosure['close_status_code'] === 'CLOSED') {
            throw new \RuntimeException('다음 달이 이미 마감된 법정 주간은 자동으로 변경할 수 없습니다. 재오픈 절차가 필요합니다.');
        }

        $from = date('Y-m-d', strtotime($monthEnd . ' +1 day'));
        $dailyByDate = [];
        foreach ($this->model->dailyRowsBetween($employeeId, $from, $weekEnd, $lock) as $row) {
            $dailyByDate[(string) $row['work_date']] = $row;
        }
        for ($date = $from; $date <= $weekEnd; $date = date('Y-m-d', strtotime($date . ' +1 day'))) {
            $employmentStatus = $this->model->employmentStatusAt($employeeId, $date);
            if (!in_array($employmentStatus, ['ACTIVE', 'ON_LEAVE'], true)) {
                continue;
            }
            $schedule = $this->schedule->resolve($employeeId, $date);
            $dailyRequired = !empty($schedule['workday']) || !empty($schedule['exception']);
            if (!$dailyRequired) {
                continue;
            }
            $daily = $dailyByDate[$date] ?? null;
            if (!$daily || !in_array((string) ($daily['calculation_status_code'] ?? ''), ['CALCULATED', 'NEEDS_CONFIRMATION'], true)) {
                throw new \RuntimeException('법정 주간의 다음 달 근태가 모두 준비·재계산되지 않아 마감할 수 없습니다.');
            }
        }

        $unconfirmed = array_filter($dailyByDate, static fn(array $row): bool => (string) $row['calculation_status_code'] === 'NEEDS_CONFIRMATION');
        $blocking = $this->model->blockingExceptionsBetween($employeeId, $from, $weekEnd);
        if ($unconfirmed !== [] || $blocking !== []) {
            throw new \RuntimeException('법정 주간의 다음 달 근태에 확인 필요 또는 미해결 차단 사유가 있어 마감할 수 없습니다.');
        }
    }

    private function assertNoClosedMonth(string $employeeId, array $rows): void
    {
        foreach (array_unique(array_map(static fn(array $row): string => substr((string) $row['work_date'], 0, 7), $rows)) as $month) {
            $closure = $this->model->closure($employeeId, $month);
            if ($closure && (string) $closure['close_status_code'] === 'CLOSED') {
                throw new \RuntimeException('마감된 월이 포함된 법정 주간은 자동으로 변경할 수 없습니다. 먼저 해당 월을 재오픈해 주세요.');
            }
        }
    }
}
