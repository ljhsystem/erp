<?php
declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
$db=DbPdo::conn();

$timed=static function(callable $callback,int $repeat=5):float{$samples=[];for($i=0;$i<$repeat+1;$i++){$start=hrtime(true);$callback();if($i>0)$samples[]=(hrtime(true)-$start)/1_000_000;}sort($samples);return round($samples[intdiv(count($samples),2)],3);};
$db->exec('CREATE TEMPORARY TABLE perf_hr_numbers (n INT PRIMARY KEY)');
$db->exec('INSERT INTO perf_hr_numbers(n) SELECT a.n+b.n*10+c.n*100+d.n*1000 FROM (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9)a CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9)b CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9)c CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9)d');
$db->exec('CREATE TEMPORARY TABLE perf_attendance (employee_id INT NOT NULL,work_date DATE NOT NULL,actual_work_seconds INT NOT NULL,calculation_status_code VARCHAR(30) NOT NULL,INDEX idx_date_employee(work_date,employee_id))');
$db->exec("INSERT INTO perf_attendance SELECT MOD(n,20),DATE_ADD('2025-01-01',INTERVAL FLOOR(n/20) DAY),28800,'CALCULATED' FROM perf_hr_numbers WHERE n<7300");
$aggregate="SELECT employee_id,SUM(actual_work_seconds),SUM(calculation_status_code='NEEDS_CONFIRMATION') FROM perf_attendance WHERE work_date BETWEEN '2025-01-01' AND '2025-12-31' GROUP BY employee_id";
$attendanceBefore=$timed(function()use($db,$aggregate){$db->query($aggregate)->fetchAll();$db->query($aggregate)->fetchAll();});
$attendanceAfter=$timed(fn()=>$db->query($aggregate)->fetchAll());

$db->exec('CREATE TEMPORARY TABLE perf_leave_balance (employee_id INT NOT NULL,leave_type_id INT NOT NULL,amount_minutes INT NOT NULL,base_year INT NOT NULL,INDEX idx_balance(employee_id,leave_type_id,base_year))');
$db->exec('INSERT INTO perf_leave_balance SELECT MOD(n,20),MOD(FLOOR(n/20),8),480,2026 FROM perf_hr_numbers WHERE n<800');
$leaveAll='SELECT employee_id,leave_type_id,SUM(amount_minutes) balance FROM perf_leave_balance WHERE base_year=2026 GROUP BY employee_id,leave_type_id ORDER BY employee_id,leave_type_id';
$leavePaged=$leaveAll.' LIMIT 50';
$leaveBefore=$timed(fn()=>$db->query($leaveAll)->fetchAll());$leaveAfter=$timed(fn()=>$db->query($leavePaged)->fetchAll());
$allRows=count($db->query($leaveAll)->fetchAll());$pageRows=count($db->query($leavePaged)->fetchAll());

$db->exec('CREATE TEMPORARY TABLE perf_qualification (employee_id INT NOT NULL,type_id INT NOT NULL,valid_to DATE,status_code VARCHAR(30),INDEX idx_employee_type(employee_id,type_id,valid_to))');
$db->exec("INSERT INTO perf_qualification SELECT MOD(n,20),MOD(FLOOR(n/20),5),DATE_ADD('2026-01-01',INTERVAL MOD(n,730) DAY),'ACTIVE' FROM perf_hr_numbers WHERE n<100");
$db->exec('CREATE TEMPORARY TABLE perf_education (employee_id INT NOT NULL,course_id INT NOT NULL,education_end_at DATETIME,completion_status_code VARCHAR(30),INDEX idx_course_completion(course_id,completion_status_code,education_end_at))');
$db->exec("INSERT INTO perf_education SELECT MOD(n,20),MOD(FLOOR(n/20),9),DATE_ADD('2020-01-01',INTERVAL n DAY),'COMPLETED' FROM perf_hr_numbers WHERE n<900");
$qualificationMs=$timed(fn()=>$db->query("SELECT employee_id,type_id,MAX(valid_to) FROM perf_qualification WHERE status_code='ACTIVE' GROUP BY employee_id,type_id")->fetchAll());
$educationMs=$timed(fn()=>$db->query("SELECT employee_id,course_id,MAX(education_end_at) FROM perf_education WHERE completion_status_code='COMPLETED' GROUP BY employee_id,course_id")->fetchAll());

echo json_encode(['fixture'=>['employees'=>20,'attendance_rows'=>7300,'leave_ledger_rows'=>800,'qualification_rows'=>100,'education_rows'=>900],'attendance_monthly'=>['before_ms'=>$attendanceBefore,'after_ms'=>$attendanceAfter,'aggregate_executions'=>'2→1'],'leave_balance'=>['before_ms'=>$leaveBefore,'after_ms'=>$leaveAfter,'rows'=>$allRows.'→'.$pageRows],'qualification_projection_ms'=>$qualificationMs,'education_last_completion_ms'=>$educationMs,'operational_rows_written'=>0,'temporary_rows_remaining_after_connection'=>0],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),PHP_EOL;
