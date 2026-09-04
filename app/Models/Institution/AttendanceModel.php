<?php

namespace App\Models\Institution;

use App\Services\Institution\EmployeeAssignmentResolver;
use PDO;

class AttendanceModel
{
    public function __construct(private readonly PDO $db) {}

    public function employee(string $id,bool $lock=false): ?array { return $this->one('SELECT * FROM user_employees WHERE id=:id'.($lock?' FOR UPDATE':''), [':id'=>$id]); }
    public function employeeByUser(string $userId): ?array { return $this->one('SELECT * FROM user_employees WHERE user_id=:id', [':id'=>$userId]); }
    public function eventByRequest(string $key): ?array { return $this->one('SELECT * FROM institution_attendance_clock_events WHERE request_key=:key', [':key'=>$key]); }
    public function duplicateEvent(string $employeeId,string $type,string $occurredAt): ?array { return $this->one('SELECT * FROM institution_attendance_clock_events WHERE employee_id=:employee AND event_type_code=:type AND occurred_at=:occurred AND is_valid=1 LIMIT 1',[':employee'=>$employeeId,':type'=>$type,':occurred'=>$occurredAt]); }
    public function clockEvent(string $id,bool $lock=false): ?array { return $this->one('SELECT * FROM institution_attendance_clock_events WHERE id=:id'.($lock?' FOR UPDATE':''),[':id'=>$id]); }
    public function auditByRequest(string $key): ?array { return $this->one('SELECT * FROM institution_attendance_audits WHERE request_key=:key', [':key'=>$key]); }
    public function historyByRequest(string $key): ?array { return $this->one('SELECT * FROM institution_attendance_monthly_closure_histories WHERE source_request_key=:key', [':key'=>$key]); }
    public function daily(string $employeeId, string $date, bool $lock=false): ?array { return $this->one('SELECT * FROM institution_attendance_daily_records WHERE employee_id=:employee AND work_date=:date'.($lock?' FOR UPDATE':''), [':employee'=>$employeeId,':date'=>$date]); }
    public function dailyById(string $id, bool $lock=false): ?array { return $this->one('SELECT * FROM institution_attendance_daily_records WHERE id=:id'.($lock?' FOR UPDATE':''), [':id'=>$id]); }
    public function closure(string $employeeId, string $month, bool $lock=false): ?array { return $this->one('SELECT * FROM institution_attendance_monthly_closures WHERE employee_id=:employee AND closing_month=:month'.($lock?' FOR UPDATE':''), [':employee'=>$employeeId,':month'=>$month]); }
    public function closureById(string $id): ?array { return $this->one('SELECT * FROM institution_attendance_monthly_closures WHERE id=:id', [':id'=>$id]); }
    public function validEvents(string $employeeId, string $from, string $to): array { return $this->all('SELECT * FROM institution_attendance_clock_events WHERE employee_id=:employee AND is_valid=1 AND occurred_at>=:from_at AND occurred_at<:to_at ORDER BY occurred_at,id', [':employee'=>$employeeId,':from_at'=>$from,':to_at'=>$to]); }
    public function clockEventsForDate(string $employeeId, string $date): array
    {
        return $this->all(
            'SELECT * FROM institution_attendance_clock_events
             WHERE employee_id=:employee AND occurred_at>=:from_at AND occurred_at<:to_at
             ORDER BY occurred_at,id',
            [
                ':employee' => $employeeId,
                ':from_at' => $date . ' 00:00:00',
                ':to_at' => date('Y-m-d H:i:s', strtotime($date . ' +2 day')),
            ]
        );
    }
    public function segments(string $dailyId): array { return $this->all('SELECT * FROM institution_attendance_work_segments WHERE daily_record_id=:id ORDER BY started_at,id', [':id'=>$dailyId]); }
    public function exceptions(string $dailyId): array { return $this->all("SELECT x.*,COALESCE(c.code_name,x.exception_type_code) exception_type_name FROM institution_attendance_daily_exceptions x LEFT JOIN system_codes c ON c.code_group='ATTENDANCE_EXCEPTION_TYPE' AND c.code=x.exception_type_code WHERE x.daily_record_id=:id ORDER BY x.exception_type_code", [':id'=>$dailyId]); }
    public function weeklySchedule(string $contractId, int $day): ?array { return $this->one('SELECT * FROM institution_employment_contracts_weekly_schedules WHERE contract_id=:contract AND day_of_week=:day', [':contract'=>$contractId,':day'=>$day]); }
    public function scheduleBreaks(string $weeklyScheduleId): array { return $this->all('SELECT * FROM institution_employment_contracts_break_schedules WHERE weekly_schedule_id=:id ORDER BY sort_no,id',[':id'=>$weeklyScheduleId]); }
    public function leaveAt(string $employeeId,string $date): bool { return (bool)$this->scalar("SELECT 1 FROM institution_job_assignments_leave_periods WHERE employee_id=:employee AND status_code<>'CANCELLED' AND start_date<=:date_from AND COALESCE(actual_end_date,planned_end_date,'9999-12-31')>=:date_to LIMIT 1", [':employee'=>$employeeId,':date_from'=>$date,':date_to'=>$date]); }
    public function approvedLeaveUsages(string $employeeId,string $date): array{return $this->all("SELECT * FROM institution_leave_usages WHERE employee_id=:employee AND leave_date=:work_date AND usage_status_code='ACTIVE' ORDER BY leave_start_at,id",[':employee'=>$employeeId,':work_date'=>$date]);}
    public function employmentStatusAt(string $employeeId,string $date): ?string{$quoted=$this->db->quote($date);$range=EmployeeAssignmentResolver::containsSql('effective_date','ended_date',$quoted);$row=$this->one("SELECT status_code FROM institution_job_assignments_employment_status_histories WHERE employee_id=:employee AND {$range} ORDER BY effective_date DESC,id DESC LIMIT 1",[':employee'=>$employeeId]);return $row['status_code']??null;}
    public function snapshot(string $employeeId,string $date): array { $quoted=$this->db->quote($date);$department=EmployeeAssignmentResolver::containsSql('da.effective_from','da.effective_to',$quoted);$job=EmployeeAssignmentResolver::containsSql('ja.start_date','ja.end_date',$quoted);$project=EmployeeAssignmentResolver::effectiveStatusSql('pa.start_date','pa.end_date','pa.status_code',$quoted);$workplace=EmployeeAssignmentResolver::effectiveStatusSql('wa.start_date','wa.end_date','wa.status_code',$quoted);return $this->one("SELECT d.id department_id,d.dept_name department_name,j.id job_id,j.job_name,p.id project_id,p.project_name,wa.id workplace_assignment_id,COALESCE(wa.workplace_name_snapshot,wp.project_name) workplace_name FROM user_employees e LEFT JOIN institution_job_assignments_department_histories da ON da.employee_id=e.id AND {$department} LEFT JOIN user_departments d ON d.id=da.department_id LEFT JOIN institution_job_assignments_job_histories ja ON ja.employee_id=e.id AND {$job} AND ja.status_code<>'CANCELLED' LEFT JOIN institution_job_assignments_jobs j ON j.id=ja.job_id LEFT JOIN institution_job_assignments_project_histories pa ON pa.employee_id=e.id AND pa.is_primary=1 AND {$project}='ACTIVE' LEFT JOIN system_projects p ON p.id=pa.project_id LEFT JOIN institution_job_assignments_workplace_histories wa ON wa.employee_id=e.id AND {$workplace}='ACTIVE' LEFT JOIN system_projects wp ON wp.id=wa.project_id WHERE e.id=:employee LIMIT 1",[':employee'=>$employeeId])??[]; }

    public function listDaily(array $q, ?string $employeeScope=null, bool $exceptionsOnly=false): array
    {
        $start=max(0,(int)($q['start']??0));$length=max(1,min(200,(int)($q['length']??50)));
        $base=[];$baseParams=[];
        if($employeeScope){$base[]='r.employee_id=:scope';$baseParams[':scope']=$employeeScope;}
        if($exceptionsOnly)$base[]="(r.calculation_status_code='NEEDS_CONFIRMATION' OR EXISTS(SELECT 1 FROM institution_attendance_daily_exceptions bx WHERE bx.daily_record_id=r.id AND bx.resolution_status_code='OPEN'))";
        $where=$base;$params=$baseParams;
        $filters=$this->dataTableFilters($q);
        $from=(string)($q['date_from']??date('Y-m-01'));$to=(string)($q['date_to']??date('Y-m-t'));
        foreach($filters as $filter){if(($filter['field']??'')==='work_date'&&is_array($filter['value']??null)){$from=(string)($filter['value']['start']??$from);$to=(string)($filter['value']['end']??$to);}}
        $where[]='r.work_date BETWEEN :from_date AND :to_date';$params[':from_date']=$from;$params[':to_date']=$to;
        foreach($filters as $index=>$filter){$field=(string)($filter['field']??'');$value=is_array($filter['value']??null)?'':trim((string)($filter['value']??''));if($value==='')continue;$key=':filter_'.$index;if($field==='employee_id'){$where[]='r.employee_id='.$key;$params[$key]=$value;}elseif($field==='department_id'){$where[]='r.department_id_snapshot='.$key;$params[$key]=$value;}elseif($field==='process_status_code'){$where[]='r.process_status_code='.$key;$params[$key]=$value;}elseif($field==='calculation_status_code'){$where[]='r.calculation_status_code='.$key;$params[$key]=$value;}elseif($field==='resolution_status_code'&&$value==='OPEN'){$where[]="(r.calculation_status_code='NEEDS_CONFIRMATION' OR EXISTS(SELECT 1 FROM institution_attendance_daily_exceptions rx WHERE rx.daily_record_id=r.id AND rx.resolution_status_code='OPEN'))";}elseif($field==='exception_type_code'){$where[]="EXISTS(SELECT 1 FROM institution_attendance_daily_exceptions fx WHERE fx.daily_record_id=r.id AND fx.resolution_status_code='OPEN' AND fx.exception_type_code={$key})";$params[$key]=$value;}elseif($field==='keyword'){$where[]="(e.employee_name LIKE {$key} OR u.username LIKE {$key})";$params[$key]='%'.$value.'%';}}
        $global=trim((string)($q['search']['value']??''));if($global!==''){$where[]='(e.employee_name LIKE :global OR u.username LIKE :global OR r.department_name_snapshot LIKE :global)';$params[':global']='%'.$global.'%';}
        $fromSql=' FROM institution_attendance_daily_records r JOIN user_employees e ON e.id=r.employee_id JOIN auth_users u ON u.id=e.user_id';
        $baseSql=$base?' WHERE '.implode(' AND ',$base):'';$filteredSql=$where?' WHERE '.implode(' AND ',$where):'';
        $total=(int)$this->scalar('SELECT COUNT(*)'.$fromSql.$baseSql,$baseParams);$filtered=(int)$this->scalar('SELECT COUNT(*)'.$fromSql.$filteredSql,$params);
        $order=$this->dataTableOrder($q,['work_date'=>'r.work_date','username'=>'u.username','employee_id'=>'e.employee_name','daily_record_id'=>'e.employee_name','department_name_snapshot'=>'r.department_name_snapshot','scheduled_start_at'=>'r.scheduled_start_at','actual_work_seconds'=>'r.actual_work_seconds','calculated_overtime_seconds'=>'r.calculated_overtime_seconds'],'r.work_date DESC,e.sort_no ASC');
        $rows=$this->all("SELECT r.*,e.employee_name,u.username,
            (SELECT GROUP_CONCAT(x.exception_type_code ORDER BY x.exception_type_code) FROM institution_attendance_daily_exceptions x WHERE x.daily_record_id=r.id AND x.resolution_status_code='OPEN') exception_codes,
            (SELECT GROUP_CONCAT(COALESCE(c.code_name,x.exception_type_code) ORDER BY x.exception_type_code SEPARATOR ', ') FROM institution_attendance_daily_exceptions x LEFT JOIN system_codes c ON c.code_group='ATTENDANCE_EXCEPTION_TYPE' AND c.code=x.exception_type_code WHERE x.daily_record_id=r.id AND x.resolution_status_code='OPEN') exception_labels,
            CASE WHEN EXISTS(SELECT 1 FROM institution_attendance_daily_exceptions x WHERE x.daily_record_id=r.id AND x.resolution_status_code='OPEN') THEN 'OPEN' WHEN r.calculation_status_code='NEEDS_CONFIRMATION' THEN 'NEEDS_CONFIRMATION' ELSE 'RESOLVED' END exception_resolution_status_code,
            CASE WHEN EXISTS(SELECT 1 FROM institution_attendance_daily_exceptions x WHERE x.daily_record_id=r.id AND x.resolution_status_code='OPEN') THEN '미해결 근태 예외를 확인하고 필요한 보정 후 재계산해 주세요.' WHEN r.calculation_status_code='NEEDS_CONFIRMATION' THEN '근무시간 계산 조건을 확인하고 필요한 보정 후 재계산해 주세요.' ELSE '재계산으로 예외가 해소되었습니다.' END exception_reason
            {$fromSql}{$filteredSql} ORDER BY {$order} LIMIT {$start},{$length}",$params);
        return ['rows'=>$rows,'total'=>$total,'filtered'=>$filtered];
    }
    public function monthlyList(array $q, ?string $employeeScope=null): array
    {
        return $this->monthlyProjectionList($q,$employeeScope,false);
    }
    public function closureList(array $q, ?string $employeeScope=null): array{return $this->monthlyProjectionList($q,$employeeScope,true);}

    private function monthlyProjectionList(array $q,?string $employeeScope,bool $closureMode): array
    {
        $month=(string)($q['closing_month']??date('Y-m'));$outer=[];$params=[];$baseParams=[];$hasResultFilter=false;
        foreach($this->dataTableFilters($q) as $index=>$filter){$field=(string)($filter['field']??'');$value=is_array($filter['value']??null)?'':trim((string)($filter['value']??''));if($value==='')continue;if($field==='closing_month'){$month=$value;continue;}$key=':filter_'.$index;if(in_array($field,['employee_id','department_id','close_status_code','calculation_status_code','readiness_status_code'],true)){$outer[]='p.'.$field.'='.$key;$params[$key]=$value;$hasResultFilter=true;}}
        if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month))$month=date('Y-m');
        if($employeeScope!==null){$outer[]='p.employee_id=:scope';$params[':scope']=$employeeScope;$baseParams[':scope']=$employeeScope;}
        $global=trim((string)($q['search']['value']??''));if($global!==''){$outer[]='p.employee_name LIKE :global';$params[':global']='%'.$global.'%';$hasResultFilter=true;}
        $from=$month.'-01';$to=date('Y-m-t',strtotime($from));$start=max(0,(int)($q['start']??0));$length=max(1,min(200,(int)($q['length']??50)));
        $projection="SELECT c.id,e.id employee_id,e.employee_name,e.department_id,m.closing_month,
            COALESCE(c.close_status_code,'OPEN') close_status_code,COALESCE(c.current_revision,0) current_revision,c.current_history_id,c.closed_at,
            CASE WHEN c.close_status_code='CLOSED' AND h.id IS NOT NULL THEN h.workday_count ELSE COALESCE(d.workday_count,0) END workday_count,
            CASE WHEN c.close_status_code='CLOSED' AND h.id IS NOT NULL THEN h.scheduled_work_seconds ELSE COALESCE(d.scheduled_work_seconds,0) END scheduled_work_seconds,
            CASE WHEN c.close_status_code='CLOSED' AND h.id IS NOT NULL THEN h.actual_work_seconds ELSE COALESCE(d.actual_work_seconds,0) END actual_work_seconds,
            CASE WHEN c.close_status_code='CLOSED' AND h.id IS NOT NULL THEN h.calculated_overtime_seconds ELSE COALESCE(d.calculated_overtime_seconds,0) END calculated_overtime_seconds,
            CASE WHEN c.close_status_code='CLOSED' AND h.id IS NOT NULL THEN h.night_work_seconds ELSE COALESCE(d.night_work_seconds,0) END night_work_seconds,
            CASE WHEN c.close_status_code='CLOSED' AND h.id IS NOT NULL THEN h.holiday_work_seconds ELSE COALESCE(d.holiday_work_seconds,0) END holiday_work_seconds,
            CASE WHEN c.close_status_code='CLOSED' AND h.id IS NOT NULL THEN h.late_candidate_count ELSE COALESCE(x.late_count,0) END late_candidate_count,
            CASE WHEN c.close_status_code='CLOSED' AND h.id IS NOT NULL THEN h.early_leave_candidate_count ELSE COALESCE(x.early_count,0) END early_leave_candidate_count,
            CASE WHEN c.close_status_code='CLOSED' AND h.id IS NOT NULL THEN h.absence_candidate_days ELSE COALESCE(x.absence_count,0) END absence_candidate_days,
            CASE WHEN c.close_status_code='CLOSED' AND h.id IS NOT NULL THEN h.missing_clock_count ELSE COALESCE(x.missing_count,0) END missing_clock_count,
            COALESCE(d.daily_count,0) daily_count,COALESCE(d.confirmation_count,0) confirmation_count,COALESCE(d.incomplete_count,0) incomplete_count,
            COALESCE(x.open_exception_count,0) open_exception_count,COALESCE(x.blocking_exception_count,0) blocking_exception_count,
            CASE WHEN c.close_status_code='CLOSED' THEN 'CLOSED' WHEN COALESCE(d.confirmation_count,0)>0 THEN 'NEEDS_CONFIRMATION' WHEN COALESCE(d.incomplete_count,0)>0 THEN 'INCOMPLETE' ELSE 'CALCULATED' END calculation_status_code,
            CASE WHEN c.close_status_code='CLOSED' THEN 'CLOSED' WHEN COALESCE(d.daily_count,0)=0 THEN 'NO_DATA' WHEN COALESCE(d.confirmation_count,0)>0 OR COALESCE(d.incomplete_count,0)>0 OR COALESCE(x.blocking_exception_count,0)>0 THEN 'BLOCKED' ELSE 'READY' END readiness_status_code,
            CONCAT_WS(', ',IF(COALESCE(d.daily_count,0)=0,'계산된 일별 근태 없음',NULL),IF(COALESCE(d.confirmation_count,0)>0,CONCAT('확인 필요 ',d.confirmation_count,'건'),NULL),IF(COALESCE(d.incomplete_count,0)>0,CONCAT('계산 미완료 ',d.incomplete_count,'건'),NULL),IF(COALESCE(x.blocking_exception_count,0)>0,CONCAT('차단 예외 ',x.blocking_exception_count,'건'),NULL)) readiness_reason,
            CASE WHEN c.close_status_code='CLOSED' AND h.id IS NOT NULL THEN h.late_candidate_count+h.early_leave_candidate_count+h.absence_candidate_days+h.missing_clock_count ELSE COALESCE(x.open_exception_count,0)+COALESCE(d.confirmation_count,0) END abnormal_count,
            CASE WHEN c.close_status_code='CLOSED' THEN 'SNAPSHOT' ELSE 'LIVE' END aggregate_source_code,h.ledger_hash
          FROM user_employees e CROSS JOIN (SELECT :month closing_month) m
          LEFT JOIN institution_attendance_monthly_closures c ON c.employee_id=e.id AND c.closing_month=m.closing_month
          LEFT JOIN institution_attendance_monthly_closure_histories h ON h.id=c.current_history_id
          LEFT JOIN (SELECT employee_id,COUNT(*) daily_count,SUM(scheduled_work_seconds>0) workday_count,SUM(scheduled_work_seconds) scheduled_work_seconds,SUM(actual_work_seconds) actual_work_seconds,SUM(calculated_overtime_seconds) calculated_overtime_seconds,SUM(night_work_seconds) night_work_seconds,SUM(holiday_work_seconds) holiday_work_seconds,SUM(calculation_status_code='NEEDS_CONFIRMATION') confirmation_count,SUM(calculation_status_code NOT IN ('CALCULATED','NEEDS_CONFIRMATION')) incomplete_count FROM institution_attendance_daily_records WHERE work_date BETWEEN :daily_from AND :daily_to GROUP BY employee_id) d ON d.employee_id=e.id
          LEFT JOIN (SELECT r.employee_id,SUM(x.resolution_status_code='OPEN') open_exception_count,SUM(x.resolution_status_code='OPEN' AND x.exception_type_code='LATE') late_count,SUM(x.resolution_status_code='OPEN' AND x.exception_type_code='EARLY_LEAVE') early_count,SUM(x.resolution_status_code='OPEN' AND x.exception_type_code='ABSENT') absence_count,SUM(x.resolution_status_code='OPEN' AND x.exception_type_code IN ('MISSING_CLOCK_IN','MISSING_CLOCK_OUT')) missing_count,SUM(x.resolution_status_code='OPEN' AND x.exception_type_code IN ('MISSING_CLOCK_IN','MISSING_CLOCK_OUT','CONTRACT_CONFLICT','NO_SCHEDULE','LEAVE_PERIOD_CONFLICT','DUPLICATE_CLOCK_IN','DUPLICATE_CLOCK_OUT')) blocking_exception_count FROM institution_attendance_daily_exceptions x JOIN institution_attendance_daily_records r ON r.id=x.daily_record_id WHERE r.work_date BETWEEN :exception_from AND :exception_to GROUP BY r.employee_id) x ON x.employee_id=e.id";
        $projectionParams=[':month'=>$month,':daily_from'=>$from,':daily_to'=>$to,':exception_from'=>$from,':exception_to'=>$to];$where=$outer?' WHERE '.implode(' AND ',$outer):'';
        $totalSql='SELECT COUNT(*) FROM user_employees e'.($employeeScope!==null?' WHERE e.id=:scope':'');$total=(int)$this->scalar($totalSql,$baseParams);
        $filtered=$hasResultFilter?(int)$this->scalar("SELECT COUNT(*) FROM ({$projection}) p{$where}",$projectionParams+$params):$total;
        $fallback=$closureMode?'p.employee_name ASC':'p.employee_name ASC';$order=$this->dataTableOrder($q,['closing_month'=>'p.closing_month','employee_id'=>'p.employee_name','workday_count'=>'p.workday_count','actual_work_seconds'=>'p.actual_work_seconds','close_status_code'=>'p.close_status_code','readiness_status_code'=>'p.readiness_status_code','current_revision'=>'p.current_revision'],$fallback);
        $rows=$this->all("SELECT * FROM ({$projection}) p{$where} ORDER BY {$order} LIMIT {$start},{$length}",$projectionParams+$params);
        return ['rows'=>$rows,'total'=>$total,'filtered'=>$filtered];
    }
    public function monthRows(string $employeeId,string $month,bool $lock=false): array { $from=$month.'-01';$to=date('Y-m-t',strtotime($from));return $this->all('SELECT * FROM institution_attendance_daily_records WHERE employee_id=:employee AND work_date BETWEEN :from_date AND :to_date ORDER BY work_date'.($lock?' FOR UPDATE':''),[':employee'=>$employeeId,':from_date'=>$from,':to_date'=>$to]); }
    public function weekRowsBefore(string $employeeId,string $from,string $before): array{return $this->all('SELECT actual_work_seconds,calculated_overtime_seconds FROM institution_attendance_daily_records WHERE employee_id=:employee AND work_date>=:from_date AND work_date<:before_date ORDER BY work_date',[':employee'=>$employeeId,':from_date'=>$from,':before_date'=>$before]);}
    public function weekRowsForUpdate(string $employeeId,string $from,string $to): array{return $this->all('SELECT * FROM institution_attendance_daily_records WHERE employee_id=:employee AND work_date BETWEEN :from_date AND :to_date ORDER BY work_date,id FOR UPDATE',[':employee'=>$employeeId,':from_date'=>$from,':to_date'=>$to]);}
    public function dailyRowsBetween(string $employeeId,string $from,string $to,bool $lock=false): array{return $this->all('SELECT * FROM institution_attendance_daily_records WHERE employee_id=:employee AND work_date BETWEEN :from_date AND :to_date ORDER BY work_date,id'.($lock?' FOR UPDATE':''),[':employee'=>$employeeId,':from_date'=>$from,':to_date'=>$to]);}
    public function blockingExceptionsBetween(string $employeeId,string $from,string $to): array{return $this->all("SELECT x.exception_type_code,r.work_date FROM institution_attendance_daily_exceptions x JOIN institution_attendance_daily_records r ON r.id=x.daily_record_id WHERE r.employee_id=:employee AND r.work_date BETWEEN :from_date AND :to_date AND x.resolution_status_code='OPEN' AND x.exception_type_code IN ('MISSING_CLOCK_IN','MISSING_CLOCK_OUT','CONTRACT_CONFLICT','NO_SCHEDULE','LEAVE_PERIOD_CONFLICT','DUPLICATE_CLOCK_IN','DUPLICATE_CLOCK_OUT') ORDER BY r.work_date,x.exception_type_code",[':employee'=>$employeeId,':from_date'=>$from,':to_date'=>$to]);}
    public function monthExceptions(string $employeeId,string $month): array { return $this->all("SELECT x.*,r.work_date FROM institution_attendance_daily_exceptions x JOIN institution_attendance_daily_records r ON r.id=x.daily_record_id WHERE r.employee_id=:employee AND DATE_FORMAT(r.work_date,'%Y-%m')=:month AND x.resolution_status_code='OPEN' ORDER BY r.work_date",[':employee'=>$employeeId,':month'=>$month]); }
    public function monthConfirmationCount(string $employeeId,string $month): int{return (int)$this->scalar("SELECT COUNT(*) FROM institution_attendance_daily_records WHERE employee_id=:employee AND DATE_FORMAT(work_date,'%Y-%m')=:month AND calculation_status_code='NEEDS_CONFIRMATION'",[':employee'=>$employeeId,':month'=>$month]);}
    public function histories(string $closureId, ?string $employeeScope = null): array
    {
        $where = 'h.monthly_closure_id=:id';
        $params = [':id' => $closureId];
        if ($employeeScope !== null) {
            $where .= ' AND h.employee_id=:employee';
            $params[':employee'] = $employeeScope;
        }
        return $this->all(
            "SELECT h.* FROM institution_attendance_monthly_closure_histories h
             WHERE {$where} ORDER BY h.revision DESC",
            $params
        );
    }
    public function project(string $id): ?array{return $this->one('SELECT * FROM system_projects WHERE id=:id AND deleted_at IS NULL',[':id'=>$id]);}
    public function workplace(string $id): ?array{return $this->one("SELECT * FROM institution_job_assignments_workplace_histories WHERE id=:id AND status_code<>'CANCELLED'",[':id'=>$id]);}
    public function activeProjectAssignment(string $employeeId,string $projectId,string $date): ?array{$quoted=$this->db->quote($date);$status=EmployeeAssignmentResolver::effectiveStatusSql('start_date','end_date','status_code',$quoted);return $this->one("SELECT * FROM institution_job_assignments_project_histories WHERE employee_id=:employee AND project_id=:project AND {$status}='ACTIVE' LIMIT 1",[':employee'=>$employeeId,':project'=>$projectId]);}
    public function activeWorkplaceAssignment(string $employeeId,string $assignmentId,string $date): ?array{$quoted=$this->db->quote($date);$status=EmployeeAssignmentResolver::effectiveStatusSql('start_date','end_date','status_code',$quoted);return $this->one("SELECT * FROM institution_job_assignments_workplace_histories WHERE id=:id AND employee_id=:employee AND {$status}='ACTIVE' LIMIT 1",[':id'=>$assignmentId,':employee'=>$employeeId]);}
    public function activeWorkplaceOptions(string $employeeId,string $date): array{$quoted=$this->db->quote($date);$status=EmployeeAssignmentResolver::effectiveStatusSql('start_date','end_date','status_code',$quoted);return $this->all("SELECT id value,COALESCE(NULLIF(workplace_name_snapshot,''),CONCAT('근무지 ',start_date)) label FROM institution_job_assignments_workplace_histories WHERE employee_id=:employee AND {$status}='ACTIVE' ORDER BY start_date DESC,id",[':employee'=>$employeeId]);}
    public function options(): array { return ['departments'=>$this->all('SELECT id value,dept_name label FROM user_departments WHERE is_active=1 ORDER BY sort_no'),'process_statuses'=>$this->codeOptions('ATTENDANCE_PROCESS_STATUS'),'exception_types'=>$this->codeOptions('ATTENDANCE_EXCEPTION_TYPE')]; }
    public function insert(string $table,array $data): void { $c=array_keys($data);$s=$this->db->prepare("INSERT INTO `$table` (`".implode('`,`',$c)."`) VALUES (:".implode(',:',$c).')');$s->execute($data); }
    public function update(string $table,string $id,array $data): void { $set=[];foreach(array_keys($data) as $c)$set[]="`$c`=:$c";$data['id']=$id;$s=$this->db->prepare("UPDATE `$table` SET ".implode(',',$set).' WHERE id=:id');$s->execute($data); }
    public function calculatedExceptionsForUpdate(string $dailyId): array
    {
        return $this->all(
            "SELECT * FROM institution_attendance_daily_exceptions
             WHERE daily_record_id=:id AND source_type_code='CALCULATION'
             ORDER BY exception_type_code FOR UPDATE",
            [':id' => $dailyId]
        );
    }

    public function syncCalculatedExceptions(string $dailyId, array $types, string $actor, string $now): void
    {
        $existingByType = [];
        foreach ($this->calculatedExceptionsForUpdate($dailyId) as $row) {
            $existingByType[(string) $row['exception_type_code']] = $row;
        }

        foreach ($types as $type => $seconds) {
            $existing = $existingByType[$type] ?? null;
            if ($existing) {
                $this->update('institution_attendance_daily_exceptions', (string) $existing['id'], [
                    'candidate_seconds' => (int) $seconds,
                    'resolution_status_code' => 'OPEN',
                    'updated_at' => $now,
                    'updated_by' => $actor,
                ]);
                unset($existingByType[$type]);
                continue;
            }

            $this->insert('institution_attendance_daily_exceptions', [
                'id' => $this->uuid(),
                'daily_record_id' => $dailyId,
                'exception_type_code' => $type,
                'candidate_seconds' => (int) $seconds,
                'source_type_code' => 'CALCULATION',
                'resolution_status_code' => 'OPEN',
                'resolution_reason' => null,
                'resolved_at' => null,
                'resolved_by' => null,
                'created_at' => $now,
                'created_by' => $actor,
                'updated_at' => $now,
                'updated_by' => $actor,
            ]);
        }

        foreach ($existingByType as $existing) {
            if ((string) $existing['resolution_status_code'] !== 'OPEN') {
                continue;
            }
            $this->update('institution_attendance_daily_exceptions', (string) $existing['id'], [
                'resolution_status_code' => 'RESOLVED',
                'resolution_reason' => '자동 재계산 결과 예외 해소',
                'resolved_at' => $now,
                'resolved_by' => $actor,
                'updated_at' => $now,
                'updated_by' => $actor,
            ]);
        }
    }
    public function deleteAutomaticSegments(string $dailyId): void
    {
        $s=$this->db->prepare('DELETE FROM institution_attendance_work_segments WHERE daily_record_id=:id AND is_manual=0');$s->execute([':id'=>$dailyId]);
    }
    public function setMonthClosed(string $employeeId,string $month,bool $closed,string $actor): void { $from=$month.'-01';$to=date('Y-m-t',strtotime($from));$s=$this->db->prepare('UPDATE institution_attendance_daily_records SET process_status_code=:status,updated_at=NOW(),updated_by=:actor WHERE employee_id=:employee AND work_date BETWEEN :from_date AND :to_date');$s->execute([':status'=>$closed?'CLOSED':'CALCULATED',':actor'=>$actor,':employee'=>$employeeId,':from_date'=>$from,':to_date'=>$to]); }
    private function dataTableFilters(array $query): array{$filters=json_decode((string)($query['filters']??'[]'),true);return is_array($filters)?array_values(array_filter($filters,'is_array')):[];}
    private function dataTableOrder(array $query,array $allowed,string $fallback): string{$order=is_array($query['order'][0]??null)?$query['order'][0]:[];$columns=is_array($query['columns']??null)?$query['columns']:[];$index=filter_var($order['column']??null,FILTER_VALIDATE_INT);$column=$index!==false&&isset($columns[$index])?(string)($columns[$index]['data']??''):'';$field=$allowed[$column]??null;if($field===null)return $fallback;$direction=strtolower((string)($order['dir']??'desc'))==='asc'?'ASC':'DESC';return $field.' '.$direction;}
    private function codeOptions(string $group): array{return $this->all('SELECT code value,code_name label FROM system_codes WHERE code_group=:group_name AND is_active=1 ORDER BY sort_no,code',[':group_name'=>$group]);}
    private function one(string $sql,array $p=[]): ?array {$s=$this->db->prepare($sql);$s->execute($p);return $s->fetch(PDO::FETCH_ASSOC)?:null;}
    private function all(string $sql,array $p=[]): array {$s=$this->db->prepare($sql);$s->execute($p);return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}
    private function scalar(string $sql,array $p=[]): mixed {$s=$this->db->prepare($sql);$s->execute($p);return $s->fetchColumn();}
    private function uuid(): string{$h=bin2hex(random_bytes(16));return substr($h,0,8).'-'.substr($h,8,4).'-4'.substr($h,13,3).'-'.dechex((hexdec($h[16])&3)|8).substr($h,17,3).'-'.substr($h,20,12);}
}
