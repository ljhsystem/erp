<?php

namespace App\Models\Institution;

use App\Services\Institution\EmployeeAssignmentResolver;
use PDO;

class AttendanceModel
{
    public function __construct(private readonly PDO $db) {}

    public function employee(string $id): ?array { return $this->one('SELECT * FROM user_employees WHERE id=:id', [':id'=>$id]); }
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
    public function exceptions(string $dailyId): array { return $this->all('SELECT * FROM institution_attendance_daily_exceptions WHERE daily_record_id=:id ORDER BY exception_type_code', [':id'=>$dailyId]); }
    public function weeklySchedule(string $contractId, int $day): ?array { return $this->one('SELECT * FROM institution_employment_contracts_weekly_schedules WHERE contract_id=:contract AND day_of_week=:day', [':contract'=>$contractId,':day'=>$day]); }
    public function scheduleBreaks(string $weeklyScheduleId): array { return $this->all('SELECT * FROM institution_employment_contracts_break_schedules WHERE weekly_schedule_id=:id ORDER BY sort_no,id',[':id'=>$weeklyScheduleId]); }
    public function leaveAt(string $employeeId,string $date): bool { return (bool)$this->scalar("SELECT 1 FROM institution_job_assignments_leave_periods WHERE employee_id=:employee AND status_code<>'CANCELLED' AND start_date<=:date_from AND COALESCE(actual_end_date,planned_end_date,'9999-12-31')>=:date_to LIMIT 1", [':employee'=>$employeeId,':date_from'=>$date,':date_to'=>$date]); }
    public function approvedLeaveUsages(string $employeeId,string $date): array{return $this->all("SELECT * FROM institution_leave_usages WHERE employee_id=:employee AND leave_date=:work_date AND usage_status_code='ACTIVE' ORDER BY leave_start_at,id",[':employee'=>$employeeId,':work_date'=>$date]);}
    public function employmentStatusAt(string $employeeId,string $date): ?string{$quoted=$this->db->quote($date);$range=EmployeeAssignmentResolver::containsSql('effective_date','ended_date',$quoted);$row=$this->one("SELECT status_code FROM institution_job_assignments_employment_status_histories WHERE employee_id=:employee AND {$range} ORDER BY effective_date DESC,id DESC LIMIT 1",[':employee'=>$employeeId]);return $row['status_code']??null;}
    public function snapshot(string $employeeId,string $date): array { $quoted=$this->db->quote($date);$department=EmployeeAssignmentResolver::containsSql('da.effective_from','da.effective_to',$quoted);$job=EmployeeAssignmentResolver::containsSql('ja.start_date','ja.end_date',$quoted);$project=EmployeeAssignmentResolver::effectiveStatusSql('pa.start_date','pa.end_date','pa.status_code',$quoted);$workplace=EmployeeAssignmentResolver::effectiveStatusSql('wa.start_date','wa.end_date','wa.status_code',$quoted);return $this->one("SELECT d.id department_id,d.dept_name department_name,j.id job_id,j.job_name,p.id project_id,p.project_name,wa.id workplace_assignment_id,COALESCE(wa.workplace_name_snapshot,wp.project_name) workplace_name FROM user_employees e LEFT JOIN institution_job_assignments_department_histories da ON da.employee_id=e.id AND {$department} LEFT JOIN user_departments d ON d.id=da.department_id LEFT JOIN institution_job_assignments_job_histories ja ON ja.employee_id=e.id AND {$job} AND ja.status_code<>'CANCELLED' LEFT JOIN institution_job_assignments_jobs j ON j.id=ja.job_id LEFT JOIN institution_job_assignments_project_histories pa ON pa.employee_id=e.id AND pa.is_primary=1 AND {$project}='ACTIVE' LEFT JOIN system_projects p ON p.id=pa.project_id LEFT JOIN institution_job_assignments_workplace_histories wa ON wa.employee_id=e.id AND {$workplace}='ACTIVE' LEFT JOIN system_projects wp ON wp.id=wa.project_id WHERE e.id=:employee LIMIT 1",[':employee'=>$employeeId])??[]; }

    public function listDaily(array $q, ?string $employeeScope=null, bool $exceptionsOnly=false): array
    {
        $start=max(0,(int)($q['start']??0)); $length=max(1,min(200,(int)($q['length']??50)));
        $from=(string)($q['date_from']??date('Y-m-01')); $to=(string)($q['date_to']??date('Y-m-t'));
        $where=['r.work_date BETWEEN :from_date AND :to_date'];$p=[':from_date'=>$from,':to_date'=>$to];
        if($employeeScope){$where[]='r.employee_id=:scope';$p[':scope']=$employeeScope;}
        elseif(!empty($q['employee_id'])){$where[]='r.employee_id=:employee';$p[':employee']=$q['employee_id'];}
        if($exceptionsOnly)$where[]="EXISTS(SELECT 1 FROM institution_attendance_daily_exceptions x WHERE x.daily_record_id=r.id AND x.resolution_status_code='OPEN')";
        if(!empty($q['department_id'])){$where[]='r.department_id_snapshot=:department';$p[':department']=$q['department_id'];}
        $sqlWhere=implode(' AND ',$where);
        $total=(int)$this->scalar("SELECT COUNT(*) FROM institution_attendance_daily_records r WHERE {$sqlWhere}",$p);
        $rows=$this->all("SELECT r.*,e.employee_name,u.username,(SELECT GROUP_CONCAT(x.exception_type_code ORDER BY x.exception_type_code) FROM institution_attendance_daily_exceptions x WHERE x.daily_record_id=r.id AND x.resolution_status_code='OPEN') exception_codes FROM institution_attendance_daily_records r JOIN user_employees e ON e.id=r.employee_id JOIN auth_users u ON u.id=e.user_id WHERE {$sqlWhere} ORDER BY r.work_date DESC,e.sort_no LIMIT {$start},{$length}",$p);
        return ['rows'=>$rows,'total'=>$total,'filtered'=>$total];
    }
    public function monthlyList(array $q, ?string $employeeScope=null): array
    {
        $month = (string) ($q['closing_month'] ?? date('Y-m'));
        $from = $month . '-01';
        $to = date('Y-m-t', strtotime($from));
        $start = max(0, (int) ($q['start'] ?? 0));
        $length = max(1, min(200, (int) ($q['length'] ?? 50)));
        $where = '1=1';
        $filters = [];
        if ($employeeScope) {
            $where .= ' AND e.id=:scope';
            $filters[':scope'] = $employeeScope;
        } elseif (!empty($q['employee_id'])) {
            $where .= ' AND e.id=:employee';
            $filters[':employee'] = $q['employee_id'];
        }
        if (!empty($q['department_id'])) {
            $where .= ' AND e.department_id=:department';
            $filters[':department'] = $q['department_id'];
        }

        $total = (int) $this->scalar("SELECT COUNT(*) FROM user_employees e WHERE {$where}", $filters);
        $params = $filters + [
            ':month' => $month,
            ':daily_from' => $from,
            ':daily_to' => $to,
            ':exception_from' => $from,
            ':exception_to' => $to,
        ];
        $rows = $this->all(
            "SELECT c.id,e.id employee_id,e.employee_name,m.closing_month,
                    COALESCE(c.close_status_code,'OPEN') close_status_code,
                    COALESCE(c.current_revision,0) current_revision,c.current_history_id,
                    h.workday_count,h.scheduled_work_seconds,h.actual_work_seconds,
                    h.calculated_overtime_seconds,h.night_work_seconds,h.holiday_work_seconds,
                    h.late_candidate_count,h.early_leave_candidate_count,h.absence_candidate_days,
                    h.missing_clock_count,h.ledger_hash,COALESCE(d.daily_count,0) daily_count,
                    COALESCE(x.open_missing_count,0) open_missing_count,
                    COALESCE(x.contract_conflict_count,0) contract_conflict_count,
                    COALESCE(x.leave_conflict_count,0) leave_conflict_count
             FROM user_employees e
             CROSS JOIN (SELECT :month closing_month) m
             LEFT JOIN institution_attendance_monthly_closures c
               ON c.employee_id=e.id AND c.closing_month=m.closing_month
             LEFT JOIN institution_attendance_monthly_closure_histories h ON h.id=c.current_history_id
             LEFT JOIN (
               SELECT employee_id,COUNT(*) daily_count
               FROM institution_attendance_daily_records
               WHERE work_date BETWEEN :daily_from AND :daily_to GROUP BY employee_id
             ) d ON d.employee_id=e.id
             LEFT JOIN (
               SELECT r.employee_id,
                      SUM(x.resolution_status_code='OPEN' AND x.exception_type_code IN ('MISSING_CLOCK_IN','MISSING_CLOCK_OUT')) open_missing_count,
                      SUM(x.resolution_status_code='OPEN' AND x.exception_type_code='CONTRACT_CONFLICT') contract_conflict_count,
                      SUM(x.resolution_status_code='OPEN' AND x.exception_type_code='LEAVE_PERIOD_CONFLICT') leave_conflict_count
               FROM institution_attendance_daily_exceptions x
               JOIN institution_attendance_daily_records r ON r.id=x.daily_record_id
               WHERE r.work_date BETWEEN :exception_from AND :exception_to GROUP BY r.employee_id
             ) x ON x.employee_id=e.id
             WHERE {$where} ORDER BY e.sort_no LIMIT {$start},{$length}",
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'filtered' => $total];
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
    public function options(): array { return ['employees'=>$this->all("SELECT id value,CONCAT(employee_name,' (',id,')') label FROM user_employees ORDER BY sort_no"),'departments'=>$this->all('SELECT id value,dept_name label FROM user_departments WHERE is_active=1 ORDER BY sort_no'),'projects'=>$this->all('SELECT id value,project_name label FROM system_projects WHERE deleted_at IS NULL AND is_active=1 ORDER BY sort_no,project_name')]; }
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
        $daily=$this->dailyById($dailyId);if(!$daily)return;
        if($this->scalar("SELECT 1 FROM institution_attendance_work_segments WHERE daily_record_id=:id AND segment_type_code='WORK' AND is_manual=1 LIMIT 1",[':id'=>$dailyId]))return;
        $events=$this->validEvents($daily['employee_id'],$daily['work_date'].' 00:00:00',date('Y-m-d H:i:s',strtotime($daily['work_date'].' +2 day')));$open=null;$now=date('Y-m-d H:i:s');
        foreach($events as $event){if($event['event_type_code']==='CLOCK_IN'){if($open===null)$open=$event;continue;}if($open===null||strtotime($event['occurred_at'])<=strtotime($open['occurred_at']))continue;$this->insert('institution_attendance_work_segments',['id'=>$this->uuid(),'daily_record_id'=>$dailyId,'segment_type_code'=>'WORK','started_at'=>$open['occurred_at'],'ended_at'=>$event['occurred_at'],'duration_seconds'=>strtotime($event['occurred_at'])-strtotime($open['occurred_at']),'project_id'=>null,'project_name_snapshot'=>null,'workplace_assignment_id'=>null,'workplace_name_snapshot'=>null,'source_type_code'=>'SYSTEM','is_manual'=>0,'created_at'=>$now,'created_by'=>$daily['updated_by'],'updated_at'=>$now,'updated_by'=>$daily['updated_by']]);$open=null;}
    }
    public function setMonthClosed(string $employeeId,string $month,bool $closed,string $actor): void { $from=$month.'-01';$to=date('Y-m-t',strtotime($from));$s=$this->db->prepare('UPDATE institution_attendance_daily_records SET process_status_code=:status,updated_at=NOW(),updated_by=:actor WHERE employee_id=:employee AND work_date BETWEEN :from_date AND :to_date');$s->execute([':status'=>$closed?'CLOSED':'CALCULATED',':actor'=>$actor,':employee'=>$employeeId,':from_date'=>$from,':to_date'=>$to]); }
    private function one(string $sql,array $p=[]): ?array {$s=$this->db->prepare($sql);$s->execute($p);return $s->fetch(PDO::FETCH_ASSOC)?:null;}
    private function all(string $sql,array $p=[]): array {$s=$this->db->prepare($sql);$s->execute($p);return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}
    private function scalar(string $sql,array $p=[]): mixed {$s=$this->db->prepare($sql);$s->execute($p);return $s->fetchColumn();}
    private function uuid(): string{$h=bin2hex(random_bytes(16));return substr($h,0,8).'-'.substr($h,8,4).'-4'.substr($h,13,3).'-'.dechex((hexdec($h[16])&3)|8).substr($h,17,3).'-'.substr($h,20,12);}
}
