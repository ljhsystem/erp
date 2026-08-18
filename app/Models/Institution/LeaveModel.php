<?php

namespace App\Models\Institution;

use PDO;

class LeaveModel
{
    public function __construct(private readonly PDO $db) {}
    public function one(string $sql,array $params=[]): ?array{$s=$this->db->prepare($sql);$s->execute($params);return$s->fetch(PDO::FETCH_ASSOC)?:null;}
    public function all(string $sql,array $params=[]): array{$s=$this->db->prepare($sql);$s->execute($params);return$s->fetchAll(PDO::FETCH_ASSOC)?:[];}
    public function scalar(string $sql,array $params=[]): mixed{$s=$this->db->prepare($sql);$s->execute($params);return$s->fetchColumn();}
    public function insert(string $table,array $data): void{$cols=array_keys($data);$s=$this->db->prepare("INSERT INTO `$table` (`".implode('`,`',$cols)."`) VALUES (:".implode(',:',$cols).')');$s->execute($data);}
    public function update(string $table,string $id,array $data): void{$set=[];foreach(array_keys($data)as$c)$set[]="`$c`=:$c";$data['id']=$id;$s=$this->db->prepare("UPDATE `$table` SET ".implode(',',$set).' WHERE id=:id');$s->execute($data);}
    public function employeeByUser(string $userId): ?array{return$this->one('SELECT * FROM user_employees WHERE user_id=:id',[':id'=>$userId]);}
    public function employee(string $id): ?array{return$this->one('SELECT * FROM user_employees WHERE id=:id',[':id'=>$id]);}
    public function type(string $id,bool $lock=false): ?array{return$this->one('SELECT * FROM institution_leave_types WHERE id=:id'.($lock?' FOR UPDATE':''),[':id'=>$id]);}
    public function request(string $id,bool $lock=false): ?array{return$this->one('SELECT * FROM institution_leave_requests WHERE id=:id'.($lock?' FOR UPDATE':''),[':id'=>$id]);}
    public function requestByKey(string $key): ?array{return$this->one('SELECT * FROM institution_leave_requests WHERE request_key=:key',[':key'=>$key]);}
    public function items(string $id): array{return$this->all('SELECT i.*,t.type_code,t.type_name,t.deducts_balance FROM institution_leave_request_items i JOIN institution_leave_types t ON t.id=i.leave_type_id WHERE i.leave_request_id=:id ORDER BY i.sort_no',[':id'=>$id]);}
    public function usageByItem(string $id): ?array{return$this->one('SELECT * FROM institution_leave_usages WHERE request_item_id=:id',[':id'=>$id]);}
    public function usagesForRequest(string $id,bool $lock=false): array{return$this->all('SELECT u.* FROM institution_leave_usages u JOIN institution_leave_request_items i ON i.id=u.request_item_id WHERE i.leave_request_id=:id ORDER BY i.sort_no'.($lock?' FOR UPDATE':''),[':id'=>$id]);}
    public function balance(string $employee,string $type,int $year,bool $lock=false): int{return(int)$this->scalar('SELECT COALESCE(SUM(amount_minutes),0) FROM institution_leave_ledger_entries WHERE employee_id=:employee AND leave_type_id=:type AND base_year=:year'.($lock?' FOR UPDATE':''),[':employee'=>$employee,':type'=>$type,':year'=>$year]);}
    public function ledgerByRequest(string $key): ?array{return$this->one('SELECT * FROM institution_leave_ledger_entries WHERE request_key=:key',[':key'=>$key]);}
    public function auditByRequest(string $key): ?array{return$this->one('SELECT * FROM institution_leave_audits WHERE request_key=:key',[':key'=>$key]);}
    public function weeklySchedule(string $contract,int $day): ?array{return$this->one('SELECT * FROM institution_employment_contracts_weekly_schedules WHERE contract_id=:contract AND day_of_week=:day',[':contract'=>$contract,':day'=>$day]);}
    public function breaks(string $schedule): array{return$this->all('SELECT * FROM institution_employment_contracts_break_schedules WHERE weekly_schedule_id=:id ORDER BY sort_no',[':id'=>$schedule]);}
    public function longLeave(string $employee,string $date): bool{return(bool)$this->scalar("SELECT 1 FROM institution_job_assignments_leave_periods WHERE employee_id=:employee AND status_code<>'CANCELLED' AND start_date<=:d1 AND COALESCE(actual_end_date,planned_end_date,'9999-12-31')>=:d2 LIMIT 1",[':employee'=>$employee,':d1'=>$date,':d2'=>$date]);}
    public function monthClosed(string $employee,string $date): bool{return(bool)$this->scalar("SELECT 1 FROM institution_attendance_monthly_closures WHERE employee_id=:employee AND closing_month=DATE_FORMAT(:date_value,'%Y-%m') AND close_status_code='CLOSED' LIMIT 1",[':employee'=>$employee,':date_value'=>$date]);}
    public function overlappingUsage(string $employee,string $start,string $end,?string $requestId=null): bool{$sql="SELECT 1 FROM institution_leave_usages u JOIN institution_leave_request_items i ON i.id=u.request_item_id WHERE u.employee_id=:employee AND u.usage_status_code='ACTIVE' AND u.leave_start_at<:end_at AND u.leave_end_at>:start_at";$p=[':employee'=>$employee,':start_at'=>$start,':end_at'=>$end];if($requestId){$sql.=' AND i.leave_request_id<>:request';$p[':request']=$requestId;}return(bool)$this->scalar($sql.' LIMIT 1',$p);}
    public function page(array $q,?string $scope=null,string $mode='requests'): array
    {
        $start=max(0,(int)($q['start']??0));$length=max(1,min(200,(int)($q['length']??50)));$where=['1=1'];$p=[];
        if($scope!==null){$where[]='r.employee_id=:scope';$p[':scope']=$scope;}elseif(!empty($q['employee_id'])){$where[]='r.employee_id=:employee';$p[':employee']=$q['employee_id'];}
        if(!empty($q['status_code'])){$where[]='r.business_status_code=:status';$p[':status']=$q['status_code'];}
        if(!empty($q['date_from'])){$where[]='EXISTS(SELECT 1 FROM institution_leave_request_items fi WHERE fi.leave_request_id=r.id AND fi.leave_date>=:from_date)';$p[':from_date']=$q['date_from'];}
        if(!empty($q['date_to'])){$where[]='EXISTS(SELECT 1 FROM institution_leave_request_items ti WHERE ti.leave_request_id=r.id AND ti.leave_date<=:to_date)';$p[':to_date']=$q['date_to'];}
        $w=implode(' AND ',$where);$total=(int)$this->scalar("SELECT COUNT(*) FROM institution_leave_requests r WHERE $w",$p);
        $rows=$this->all("SELECT r.*,e.employee_name,u.username,MIN(i.leave_date) leave_from,MAX(i.leave_date) leave_to,GROUP_CONCAT(DISTINCT t.type_name ORDER BY t.sort_no) leave_type_names FROM institution_leave_requests r JOIN user_employees e ON e.id=r.employee_id LEFT JOIN auth_users u ON u.id=e.user_id LEFT JOIN institution_leave_request_items i ON i.leave_request_id=r.id LEFT JOIN institution_leave_types t ON t.id=i.leave_type_id WHERE $w GROUP BY r.id ORDER BY r.created_at DESC LIMIT $start,$length",$p);
        return['rows'=>$rows,'total'=>$total];
    }
    public function balances(array $q,?string $scope=null): array{$where=['1=1'];$p=[];if($scope){$where[]='e.id=:scope';$p[':scope']=$scope;}elseif(!empty($q['employee_id'])){$where[]='e.id=:employee';$p[':employee']=$q['employee_id'];}$year=(int)($q['base_year']??date('Y'));$p[':year_select']=$year;$p[':year_join']=$year;$rows=$this->all('SELECT e.id employee_id,e.employee_name,t.id leave_type_id,t.type_code,t.type_name,:year_select base_year,COALESCE(SUM(l.amount_minutes),0) balance_minutes FROM user_employees e CROSS JOIN institution_leave_types t LEFT JOIN institution_leave_ledger_entries l ON l.employee_id=e.id AND l.leave_type_id=t.id AND l.base_year=:year_join WHERE '.implode(' AND ',$where).' AND t.is_active=1 GROUP BY e.id,t.id ORDER BY e.sort_no,t.sort_no',$p);return$rows;}
    public function options(): array{return['employees'=>$this->all("SELECT id value,CONCAT(employee_name,' (',id,')') label FROM user_employees ORDER BY sort_no"),'types'=>$this->all('SELECT id value,type_code,type_name label,is_paid,deducts_balance,requires_approval,evidence_policy_code,allowed_units_json,is_active FROM institution_leave_types ORDER BY sort_no')];}
}
