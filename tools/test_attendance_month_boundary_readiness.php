<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\Institution\AttendanceModel;
use App\Services\Institution\AttendanceCalculationPolicy;
use App\Services\Institution\AttendanceScheduleService;
use App\Services\Institution\AttendanceWeeklyRecalculationService;
use App\Services\System\StatutoryStandardResolver;

final class BoundaryFixtureModel extends AttendanceModel
{
    public array $dailyRows = [];
    public array $blockingRows = [];
    public ?array $nextClosure = null;

    public function __construct() {}
    public function closure(string $employeeId,string $month,bool $lock=false): ?array{return $month === '2026-09' ? $this->nextClosure : null;}
    public function dailyRowsBetween(string $employeeId,string $from,string $to,bool $lock=false): array{return $this->dailyRows;}
    public function employmentStatusAt(string $employeeId,string $date): ?string{return 'ACTIVE';}
    public function blockingExceptionsBetween(string $employeeId,string $from,string $to): array{return $this->blockingRows;}
}

final class BoundaryFixtureResolver extends StatutoryStandardResolver
{
    public function __construct() {}
    public function resolveOptional(string $type,string $date): ?array{return ['id'=>'fixture-working','value_data'=>['week_start_day'=>1,'daily_legal_work_seconds'=>28800,'weekly_legal_work_seconds'=>144000]];}
}

final class BoundaryFixtureSchedule extends AttendanceScheduleService
{
    public function __construct() {}
    public function resolve(string $employeeId,string $date): array{return ['workday'=>true,'exception'=>null];}
}

function boundaryAssertThrows(callable $callback,string $expected): void
{
    try {$callback();} catch (RuntimeException $exception) {if (str_contains($exception->getMessage(),$expected)) return;throw $exception;}
    throw new RuntimeException('예상한 월 경계 차단이 발생하지 않았습니다: '.$expected);
}

$model = new BoundaryFixtureModel();
$service = new AttendanceWeeklyRecalculationService($model,new BoundaryFixtureResolver(),new AttendanceCalculationPolicy(),new BoundaryFixtureSchedule());
$employee = 'fixture-employee';

boundaryAssertThrows(fn()=>$service->assertMonthBoundaryReady($employee,'2026-08','2026-09-05'),'아직 종료되지 않아');
boundaryAssertThrows(fn()=>$service->assertMonthBoundaryReady($employee,'2026-08','2026-09-06'),'준비·재계산되지 않아');

foreach (range(1,6) as $day) $model->dailyRows[]=['id'=>'daily-'.$day,'work_date'=>sprintf('2026-09-%02d',$day),'calculation_status_code'=>'CALCULATED'];
$service->assertMonthBoundaryReady($employee,'2026-08','2026-09-06');

$model->blockingRows=[['work_date'=>'2026-09-03','exception_type_code'=>'DUPLICATE_CLOCK_IN']];
boundaryAssertThrows(fn()=>$service->assertMonthBoundaryReady($employee,'2026-08','2026-09-06'),'미해결 차단 사유');
$model->blockingRows=[];
$model->dailyRows[2]['calculation_status_code']='NEEDS_CONFIRMATION';
boundaryAssertThrows(fn()=>$service->assertMonthBoundaryReady($employee,'2026-08','2026-09-06'),'확인 필요');
$model->dailyRows[2]['calculation_status_code']='CALCULATED';
$model->nextClosure=['close_status_code'=>'CLOSED'];
boundaryAssertThrows(fn()=>$service->assertMonthBoundaryReady($employee,'2026-08','2026-09-06'),'재오픈');

echo "Attendance month-boundary readiness fixture PASS\n";
