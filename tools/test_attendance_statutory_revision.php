<?php

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';
require PROJECT_ROOT . '/core/Storage.php';

use App\Models\Institution\AttendanceModel;
use App\Services\System\StatutoryStandardResolver;
use App\Services\System\StatutoryStandardService;
use Core\DbPdo;
use Core\Helpers\ActorHelper;

function revisionUuid(): string{$hex=bin2hex(random_bytes(16));return substr($hex,0,8).'-'.substr($hex,8,4).'-4'.substr($hex,13,3).'-8'.substr($hex,17,3).'-'.substr($hex,20,12);}
function revisionBlocked(callable $callback,string $message): void{try{$callback();}catch(InvalidArgumentException $exception){if(str_contains($exception->getMessage(),$message))return;throw $exception;}throw new RuntimeException('예상한 Revision 불변 Guard가 동작하지 않았습니다.');}

$pdo=DbPdo::conn();$pdo->beginTransaction();
try{
    $employee=(string)$pdo->query('SELECT id FROM user_employees ORDER BY sort_no LIMIT 1')->fetchColumn();
    if($employee==='')throw new RuntimeException('검증할 직원이 없습니다.');
    $actor=ActorHelper::system('근태 법정기준 rollback 테스트');$now=date('Y-m-d H:i:s');
    $service=new StatutoryStandardService($pdo,$actor);$resolver=new StatutoryStandardResolver($pdo);$model=new AttendanceModel($pdo);
    $calendarA=['standard_type_code'=>'PUBLIC_HOLIDAY_CALENDAR','effective_from'=>'1980-01-01','effective_to'=>'1980-12-31','value_data'=>['holidays'=>[['date'=>'1980-06-01','holiday_type'=>'PUBLIC_HOLIDAY','holiday_name'=>'Fixture A']]],'note'=>'Fixture Calendar A','sources'=>[]];
    $calendarAId=(string)$service->save($calendarA)['data']['id'];
    $dailyId=revisionUuid();
    $model->insert('institution_attendance_daily_records',['id'=>$dailyId,'employee_id'=>$employee,'work_date'=>'1980-06-01','employment_contract_id'=>null,'working_time_standard_id'=>null,'public_holiday_standard_id'=>$calendarAId,'scheduled_start_at'=>null,'scheduled_end_at'=>null,'scheduled_break_seconds'=>0,'first_clock_in_at'=>null,'last_clock_out_at'=>null,'actual_work_seconds'=>0,'actual_break_seconds'=>0,'scheduled_work_seconds'=>0,'contract_excess_seconds'=>0,'calculated_overtime_seconds'=>0,'night_work_seconds'=>0,'holiday_work_seconds'=>0,'late_candidate_seconds'=>0,'early_leave_candidate_seconds'=>0,'process_status_code'=>'CALCULATED','calculation_status_code'=>'CALCULATED','calculation_version'=>1,'is_corrected'=>0,'department_id_snapshot'=>null,'department_name_snapshot'=>null,'job_id_snapshot'=>null,'job_name_snapshot'=>null,'primary_project_id_snapshot'=>null,'primary_project_name_snapshot'=>null,'workplace_assignment_id_snapshot'=>null,'workplace_name_snapshot'=>null,'created_at'=>$now,'created_by'=>$actor,'updated_at'=>$now,'updated_by'=>$actor]);

    revisionBlocked(fn()=>$service->save(array_replace($calendarA,['id'=>$calendarAId,'note'=>'직접 변경'])), 'Revision/Correction');
    $changed=$calendarA;$changed['id']=$calendarAId;$changed['value_data']['holidays'][0]['holiday_name']='직접 변경';
    revisionBlocked(fn()=>$service->save($changed), 'Revision/Correction');
    revisionBlocked(fn()=>$service->delete($calendarAId), '삭제할 수 없습니다');

    $service->save(array_replace($calendarA,['id'=>$calendarAId,'effective_to'=>'1980-06-30']));
    $calendarB=$calendarA;$calendarB['effective_from']='1980-07-01';$calendarB['effective_to']='1980-12-31';$calendarB['note']='Fixture Calendar B';$calendarB['value_data']['holidays']=[['date'=>'1980-07-01','holiday_type'=>'PUBLIC_HOLIDAY','holiday_name'=>'Fixture B']];
    $calendarBId=(string)$service->save($calendarB)['data']['id'];
    if((string)$resolver->resolve('PUBLIC_HOLIDAY_CALENDAR','1980-06-01')['id']!==$calendarAId)throw new RuntimeException('과거 Calendar A 선택 검증 실패');
    if((string)$resolver->resolve('PUBLIC_HOLIDAY_CALENDAR','1980-07-01')['id']!==$calendarBId)throw new RuntimeException('Correction Calendar B 선택 검증 실패');
    if((string)$model->dailyById($dailyId)['public_holiday_standard_id']!==$calendarAId)throw new RuntimeException('기존 Daily Calendar A 보존 검증 실패');

    $workingValues=['daily_legal_work_seconds'=>28800,'weekly_legal_work_seconds'=>144000,'week_start_day'=>1,'night_start_time'=>'22:00','night_end_time'=>'06:00'];
    $workingA=['standard_type_code'=>'WORKING_TIME_STANDARD','effective_from'=>'1979-01-01','effective_to'=>'1979-12-31','value_data'=>$workingValues,'note'=>'Fixture Working A','sources'=>[]];
    $workingAId=(string)$service->save($workingA)['data']['id'];$workingDailyId=revisionUuid();
    $model->insert('institution_attendance_daily_records',['id'=>$workingDailyId,'employee_id'=>$employee,'work_date'=>'1979-06-01','employment_contract_id'=>null,'working_time_standard_id'=>$workingAId,'public_holiday_standard_id'=>null,'scheduled_start_at'=>null,'scheduled_end_at'=>null,'scheduled_break_seconds'=>0,'first_clock_in_at'=>null,'last_clock_out_at'=>null,'actual_work_seconds'=>0,'actual_break_seconds'=>0,'scheduled_work_seconds'=>0,'contract_excess_seconds'=>0,'calculated_overtime_seconds'=>0,'night_work_seconds'=>0,'holiday_work_seconds'=>0,'late_candidate_seconds'=>0,'early_leave_candidate_seconds'=>0,'process_status_code'=>'CALCULATED','calculation_status_code'=>'CALCULATED','calculation_version'=>1,'is_corrected'=>0,'department_id_snapshot'=>null,'department_name_snapshot'=>null,'job_id_snapshot'=>null,'job_name_snapshot'=>null,'primary_project_id_snapshot'=>null,'primary_project_name_snapshot'=>null,'workplace_assignment_id_snapshot'=>null,'workplace_name_snapshot'=>null,'created_at'=>$now,'created_by'=>$actor,'updated_at'=>$now,'updated_by'=>$actor]);
    $workingChanged=$workingA;$workingChanged['id']=$workingAId;$workingChanged['value_data']['daily_legal_work_seconds']=1;
    revisionBlocked(fn()=>$service->save($workingChanged),'Revision/Correction');
    $service->save(array_replace($workingA,['id'=>$workingAId,'effective_to'=>'1979-06-30']));
    $workingB=$workingA;$workingB['effective_from']='1979-07-01';$workingB['effective_to']='1979-12-31';$workingB['note']='Fixture Working B';$workingBId=(string)$service->save($workingB)['data']['id'];
    if((string)$resolver->resolve('WORKING_TIME_STANDARD','1979-06-01')['id']!==$workingAId||(string)$resolver->resolve('WORKING_TIME_STANDARD','1979-07-01')['id']!==$workingBId)throw new RuntimeException('근로시간 Revision A/B 선택 검증 실패');
    if((string)$model->dailyById($workingDailyId)['working_time_standard_id']!==$workingAId)throw new RuntimeException('기존 Daily Working A 보존 검증 실패');
    echo "Attendance statutory revision fixture PASS\n";
}finally{$pdo->rollBack();}
