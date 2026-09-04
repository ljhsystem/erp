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
    public function requestByApproval(string $approvalRequestId,bool $lock=false): ?array{return$this->one('SELECT * FROM institution_leave_requests WHERE current_approval_request_id=:approval'.($lock?' FOR UPDATE':''),[':approval'=>$approvalRequestId]);}
    public function requestByKey(string $key): ?array{return$this->one('SELECT * FROM institution_leave_requests WHERE request_key=:key',[':key'=>$key]);}
    public function items(string $id): array{return$this->all('SELECT i.*,t.type_code,t.type_name,t.deducts_balance,t.evidence_policy_code FROM institution_leave_request_items i JOIN institution_leave_types t ON t.id=i.leave_type_id WHERE i.leave_request_id=:id ORDER BY i.sort_no',[':id'=>$id]);}
    public function usageByItem(string $id): ?array{return$this->one('SELECT * FROM institution_leave_usages WHERE request_item_id=:id',[':id'=>$id]);}
    public function usagesForRequest(string $id,bool $lock=false): array{return$this->all('SELECT u.* FROM institution_leave_usages u JOIN institution_leave_request_items i ON i.id=u.request_item_id WHERE i.leave_request_id=:id ORDER BY i.sort_no'.($lock?' FOR UPDATE':''),[':id'=>$id]);}
    public function balance(string $employee,string $type,int $year,bool $lock=false): int{return(int)$this->scalar('SELECT COALESCE(SUM(amount_minutes),0) FROM institution_leave_ledger_entries WHERE employee_id=:employee AND leave_type_id=:type AND base_year=:year'.($lock?' FOR UPDATE':''),[':employee'=>$employee,':type'=>$type,':year'=>$year]);}
    public function grantsForConsumption(string $employee,string $type,int $year,string $occurredOn): array{return$this->all("SELECT * FROM institution_leave_grants WHERE employee_id=:employee AND leave_type_id=:type AND base_year=:year AND usable_from<=:occurred_from AND usable_to>=:occurred_to AND (expires_on IS NULL OR expires_on>=:expires_on) ORDER BY (expires_on IS NULL),expires_on,usable_to,usable_from,created_at,id FOR UPDATE",[':employee'=>$employee,':type'=>$type,':year'=>$year,':occurred_from'=>$occurredOn,':occurred_to'=>$occurredOn,':expires_on'=>$occurredOn]);}
    public function grantBalances(array $grantIds): array
    {
        if($grantIds===[])return[];
        $placeholders=[];$params=[];
        foreach(array_values($grantIds)as$index=>$grantId){$key=':grant_'.$index;$placeholders[]=$key;$params[$key]=$grantId;}
        $rows=$this->all('SELECT grant_id,COALESCE(SUM(amount_minutes),0) balance_minutes FROM institution_leave_ledger_entries WHERE grant_id IN ('.implode(',',$placeholders).') GROUP BY grant_id FOR UPDATE',$params);
        $balances=[];foreach($rows as$row)$balances[(string)$row['grant_id']]=(int)$row['balance_minutes'];
        return$balances;
    }
    public function usageLedgerAllocations(string $usageId,bool $lock=false): array{return$this->all("SELECT * FROM institution_leave_ledger_entries WHERE entry_type_code='USAGE' AND source_domain_code='USAGE' AND source_id=:usage AND grant_id IS NOT NULL ORDER BY source_sequence,id".($lock?' FOR UPDATE':''),[':usage'=>$usageId]);}
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
        if(!empty($q['leave_type_id'])){$where[]='EXISTS(SELECT 1 FROM institution_leave_request_items li WHERE li.leave_request_id=r.id AND li.leave_type_id=:leave_type)';$p[':leave_type']=$q['leave_type_id'];}
        if(!empty($q['approval_status'])){$where[]='ar.status=:approval_status';$p[':approval_status']=$q['approval_status'];}
        $keyword=trim((string)($q['keyword']??$q['search']['value']??''));if($keyword!==''){$where[]='(r.request_no LIKE :keyword OR r.reason LIKE :keyword OR EXISTS(SELECT 1 FROM institution_leave_request_items ki JOIN institution_leave_types kt ON kt.id=ki.leave_type_id WHERE ki.leave_request_id=r.id AND kt.type_name LIKE :keyword))';$p[':keyword']='%'.$keyword.'%';}
        if(!empty($q['date_from'])){$where[]='EXISTS(SELECT 1 FROM institution_leave_request_items fi WHERE fi.leave_request_id=r.id AND fi.leave_date>=:from_date)';$p[':from_date']=$q['date_from'];}
        if(!empty($q['date_to'])){$where[]='EXISTS(SELECT 1 FROM institution_leave_request_items ti WHERE ti.leave_request_id=r.id AND ti.leave_date<=:to_date)';$p[':to_date']=$q['date_to'];}
        $w=implode(' AND ',$where);$total=(int)$this->scalar("SELECT COUNT(*) FROM institution_leave_requests r WHERE $w",$p);
        $rows=$this->all("SELECT r.*,e.employee_name,u.username,MIN(i.leave_date) leave_from,MAX(i.leave_date) leave_to,GROUP_CONCAT(DISTINCT t.type_name ORDER BY t.sort_no) leave_type_names,GROUP_CONCAT(DISTINCT i.request_unit_code ORDER BY i.sort_no) request_unit_names,COALESCE(SUM(i.deductible_minutes),0) deductible_total_minutes,ar.status approval_status,ars.step_name current_step_name,ar.completed_at FROM institution_leave_requests r JOIN user_employees e ON e.id=r.employee_id LEFT JOIN auth_users u ON u.id=e.user_id LEFT JOIN institution_leave_request_items i ON i.leave_request_id=r.id LEFT JOIN institution_leave_types t ON t.id=i.leave_type_id LEFT JOIN user_approval_requests ar ON ar.id=r.current_approval_request_id LEFT JOIN user_approval_request_steps ars ON ars.request_id=ar.id AND ars.sort_no=ar.current_step AND ars.is_active=1 WHERE $w GROUP BY r.id ORDER BY r.created_at DESC LIMIT $start,$length",$p);
        return['rows'=>$rows,'total'=>$total];
    }
    public function balances(array $q,?string $scope=null): array
    {
        $where=['t.is_active=1'];$p=[];$year=(int)($q['base_year']??date('Y'));
        $filters=json_decode((string)($q['filters']??'[]'),true);
        foreach(is_array($filters)?$filters:[] as $index=>$filter){$field=(string)($filter['field']??'');$value=is_array($filter['value']??null)?'':trim((string)($filter['value']??''));if($value==='')continue;$key=':filter_'.$index;if($field==='employee_id'){$where[]='e.id='.$key;$p[$key]=$value;}elseif($field==='leave_type_id'){$where[]='t.id='.$key;$p[$key]=$value;}elseif($field==='base_year'){$year=(int)$value;}}
        if($scope){$where[]='e.id=:scope';$p[':scope']=$scope;}elseif(!empty($q['employee_id'])){$where[]='e.id=:employee';$p[':employee']=$q['employee_id'];}
        $search=trim((string)($q['search']['value']??''));if($search!==''){$where[]='(e.employee_name LIKE :search OR t.type_name LIKE :search)';$p[':search']='%'.$search.'%';}
        $whereSql=implode(' AND ',$where);$start=max(0,(int)($q['start']??0));$length=max(1,min(200,(int)($q['length']??50)));
        $total=(int)$this->scalar('SELECT COUNT(*) FROM user_employees e CROSS JOIN institution_leave_types t WHERE '.$whereSql,$p);
        $p[':year_select']=$year;$p[':year_join']=$year;
        $rows=$this->all('SELECT e.id employee_id,e.employee_name,t.id leave_type_id,t.type_code,t.type_name,:year_select base_year,COALESCE(l.balance_minutes,0) balance_minutes FROM user_employees e CROSS JOIN institution_leave_types t LEFT JOIN (SELECT employee_id,leave_type_id,SUM(amount_minutes) balance_minutes FROM institution_leave_ledger_entries WHERE base_year=:year_join GROUP BY employee_id,leave_type_id) l ON l.employee_id=e.id AND l.leave_type_id=t.id WHERE '.$whereSql.' ORDER BY e.sort_no,t.sort_no LIMIT '.$start.','.$length,$p);
        return['rows'=>$rows,'total'=>$total];
    }
    public function options(): array{return['types'=>$this->all('SELECT id value,type_code,type_name label,is_paid,deducts_balance,requires_approval,evidence_policy_code,allowed_units_json,is_active FROM institution_leave_types ORDER BY sort_no')];}
}
