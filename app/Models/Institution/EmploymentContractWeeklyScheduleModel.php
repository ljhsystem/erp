<?php

namespace App\Models\Institution;

use Core\Helpers\UuidHelper;
use PDO;

class EmploymentContractWeeklyScheduleModel
{
    public function __construct(private readonly PDO $db) {}

    public function forContract(string $contractId, bool $forUpdate = false): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM institution_employment_contracts_weekly_schedules
             WHERE contract_id = :contract_id ORDER BY day_of_week' . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $stmt->execute([':contract_id' => $contractId]);
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
        if($rows===[])return[];
        $ids=array_column($rows,'id');$marks=implode(',',array_fill(0,count($ids),'?'));
        $breakStmt=$this->db->prepare("SELECT * FROM institution_employment_contracts_break_schedules WHERE weekly_schedule_id IN ($marks) ORDER BY weekly_schedule_id,sort_no");$breakStmt->execute($ids);$by=[];foreach($breakStmt->fetchAll(PDO::FETCH_ASSOC)?:[] as$break)$by[$break['weekly_schedule_id']][]=$break;
        foreach($rows as&$row)$row['break_schedules']=$by[$row['id']]??[];unset($row);return$rows;
    }

    public function replace(string $contractId, array $rows, string $actor): void
    {
        $this->db->prepare(
            'DELETE FROM institution_employment_contracts_weekly_schedules WHERE contract_id = :contract_id'
        )->execute([':contract_id' => $contractId]);
        $stmt = $this->db->prepare(
            'INSERT INTO institution_employment_contracts_weekly_schedules
             (id, contract_id, day_of_week, day_type, start_time, end_time, end_day_offset,
              break_minutes, created_at, created_by, updated_at, updated_by)
             VALUES (:id, :contract_id, :day_of_week, :day_type, :start_time, :end_time,
                     :end_day_offset, :break_minutes, NOW(), :created_by, NOW(), :updated_by)'
        );
        foreach ($rows as $row) {
            $weeklyId=UuidHelper::generate();$stmt->execute([
                ':id' => $weeklyId, ':contract_id' => $contractId,
                ':day_of_week' => $row['day_of_week'], ':day_type' => $row['day_type'],
                ':start_time' => $row['start_time'], ':end_time' => $row['end_time'],
                ':end_day_offset' => $row['end_day_offset'], ':break_minutes' => $row['break_minutes'],
                ':created_by' => $actor, ':updated_by' => $actor,
            ]);
            $breakStmt=$this->db->prepare('INSERT INTO institution_employment_contracts_break_schedules (id,weekly_schedule_id,sort_no,start_time,end_time,end_day_offset,created_at,created_by,updated_at,updated_by) VALUES (:id,:weekly,:sort_no,:start_time,:end_time,:offset,NOW(),:created_by,NOW(),:updated_by)');
            foreach($row['break_schedules']??[] as$index=>$break)$breakStmt->execute([':id'=>UuidHelper::generate(),':weekly'=>$weeklyId,':sort_no'=>$index+1,':start_time'=>$break['start_time'],':end_time'=>$break['end_time'],':offset'=>$break['end_day_offset'],':created_by'=>$actor,':updated_by'=>$actor]);
        }
    }
}
