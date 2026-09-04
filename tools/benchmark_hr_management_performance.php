<?php
declare(strict_types=1);

use App\Models\Institution\AttendanceModel;
use App\Models\Institution\EducationModel;
use App\Models\Institution\LeaveModel;
use App\Models\Institution\QualificationEducationPolicyModel;
use App\Models\Institution\QualificationModel;
use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$db = DbPdo::conn();
$attendance = new AttendanceModel($db);
$leave = new LeaveModel($db);
$qualification = new QualificationModel($db);
$education = new EducationModel($db);
$policy = new QualificationEducationPolicyModel($db);
$request = ['draw'=>1,'start'=>0,'length'=>50,'order'=>[],'columns'=>[],'search'=>['value'=>''],'filters'=>'[]'];

$measure = static function (callable $callback, int $queryCount) use ($db): array {
    $samples = []; $value = null;
    for ($i = 0; $i < 6; $i++) {
        $start = hrtime(true); $value = $callback(); $elapsed = (hrtime(true) - $start) / 1_000_000;
        if ($i > 0) $samples[] = round($elapsed, 3);
    }
    sort($samples); $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $rows = is_array($value['rows'] ?? null) ? count($value['rows']) : (is_array($value) ? count($value) : 0);
    return ['median_ms'=>$samples[intdiv(count($samples),2)],'samples_ms'=>$samples,'query_count'=>$queryCount,'payload_bytes'=>strlen((string)$json),'rows'=>$rows];
};

$month = date('Y-m');
$results = [
    'attendance.daily' => $measure(fn()=>$attendance->listDaily($request), 3),
    'attendance.monthly' => $measure(fn()=>$attendance->monthlyList($request + ['closing_month'=>$month]), 2),
    'attendance.exceptions' => $measure(fn()=>$attendance->listDaily($request, null, true), 3),
    'attendance.closures' => $measure(fn()=>$attendance->closureList($request + ['closing_month'=>$month]), 2),
    'leave.status' => $measure(fn()=>$leave->page($request), 2),
    'leave.balances' => $measure(fn()=>$leave->balances($request + ['base_year'=>(int)date('Y')]), 2),
    'leave.types' => $measure(fn()=>$leave->options()['types'], 1),
    'leave.options' => $measure(fn()=>$leave->options(), 1),
    'qualification.status' => $measure(fn()=>$qualification->page($request), 2),
    'qualification.education' => $measure(fn()=>$education->page($request), 2),
    'qualification.types' => $measure(fn()=>$policy->qualificationTypes(false), 1),
    'qualification.courses' => $measure(fn()=>$policy->courses(false), 1),
    'qualification.job_requirements' => $measure(fn()=>$policy->requirements('qualification'), 1),
    'qualification.education_requirements' => $measure(fn()=>$policy->requirements('education'), 1),
];

$tables = ['user_employees','institution_attendance_daily_records','institution_attendance_clock_events','institution_attendance_work_segments','institution_attendance_daily_exceptions','institution_attendance_monthly_closures','institution_leave_requests','institution_leave_request_items','institution_leave_types','institution_leave_ledger_entries','institution_qualifications_employee_records','institution_educations_employee_records','institution_educations_courses'];
$counts=[];foreach($tables as $table)$counts[$table]=(int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
$monthFrom=date('Y-m-01');$monthTo=date('Y-m-t');
$explainSql=[
    'attendance_daily'=>"SELECT r.id FROM institution_attendance_daily_records r JOIN user_employees e ON e.id=r.employee_id JOIN auth_users u ON u.id=e.user_id WHERE r.work_date BETWEEN '{$monthFrom}' AND '{$monthTo}' ORDER BY r.work_date DESC LIMIT 50",
    'attendance_monthly_aggregate'=>"SELECT employee_id,SUM(actual_work_seconds) FROM institution_attendance_daily_records WHERE work_date BETWEEN '{$monthFrom}' AND '{$monthTo}' GROUP BY employee_id",
    'leave_balance_aggregate'=>"SELECT employee_id,leave_type_id,SUM(amount_minutes) FROM institution_leave_ledger_entries WHERE base_year=".(int)date('Y')." GROUP BY employee_id,leave_type_id",
    'qualification_status'=>"SELECT q.id FROM institution_qualifications_employee_records q JOIN institution_qualifications_types t ON t.id=q.qualification_type_id WHERE q.deleted_at IS NULL ORDER BY q.created_at DESC LIMIT 50",
    'education_status'=>"SELECT r.id FROM institution_educations_employee_records r JOIN institution_educations_courses c ON c.id=r.course_id WHERE r.deleted_at IS NULL ORDER BY r.education_start_at DESC LIMIT 50",
];
$explains=[];foreach($explainSql as $key=>$sql)$explains[$key]=$db->query('EXPLAIN '.$sql)->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['measured_at'=>date(DATE_ATOM),'database_counts'=>$counts,'results'=>$results,'explains'=>$explains], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
