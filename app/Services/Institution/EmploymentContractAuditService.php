<?php

namespace App\Services\Institution;

use App\Models\Institution\EmploymentContractAuditModel;
use App\Models\Institution\EmploymentContractComponentModel;
use App\Models\Institution\EmploymentContractModel;
use App\Models\Institution\EmploymentContractWeeklyScheduleModel;
use App\Models\Institution\EmploymentContractWorkSchedulePolicyModel;
use PDO;

final class EmploymentContractAuditService
{
    private EmploymentContractAuditModel $audits;
    private EmploymentContractModel $contracts;
    private EmploymentContractComponentModel $components;
    private EmploymentContractWeeklyScheduleModel $schedules;
    private EmploymentContractWorkSchedulePolicyModel $policies;

    public function __construct(PDO $db)
    {
        $this->audits=new EmploymentContractAuditModel($db);$this->contracts=new EmploymentContractModel($db);
        $this->components=new EmploymentContractComponentModel($db);$this->schedules=new EmploymentContractWeeklyScheduleModel($db);
        $this->policies=new EmploymentContractWorkSchedulePolicyModel($db);
    }

    public function snapshot(string $contractId, bool $includeDeleted=false): ?array
    {
        $header=$this->contracts->find($contractId,$includeDeleted);
        if(!$header)return null;
        return ['header'=>$this->clean($header,['employee_identifier_snapshot','current_approval_request_id']),
            'weekly_schedules'=>array_map(fn(array $row):array=>$this->cleanSchedule($row),$this->schedules->forContract($contractId)),
            'work_schedule_policy'=>$this->cleanNullable($this->policies->forContract($contractId)),
            'components'=>array_map(fn(array $row):array=>$this->clean($row,['master_component_name']),$this->components->activeForContract($contractId))];
    }

    public function record(string $contractId,string $action,?array $before,?array $after,string $reason,string $actor,string $requestKey,?string $approvalRequestId=null): array
    {
        $reason=trim($reason);$requestKey=trim($requestKey);
        if($reason===''||$requestKey==='')throw new \InvalidArgumentException('감사 사유와 요청 키가 필요합니다.');
        return $this->audits->record(['contract_id'=>$contractId,'action_type'=>$action,
            'before_data'=>$this->encode($before),'after_data'=>$this->encode($after),'reason'=>$reason,
            'processed_by'=>$actor,'processed_at'=>date('Y-m-d H:i:s'),'approval_request_id'=>$approvalRequestId?:null,'request_key'=>$requestKey]);
    }

    public function histories(string $contractId): array{return array_map(function(array$row):array{foreach(['before_data','after_data']as$key)$row[$key]=isset($row[$key])&&$row[$key]!==null?json_decode((string)$row[$key],true,512,JSON_THROW_ON_ERROR):null;return$row;},$this->audits->histories($contractId));}

    private function cleanSchedule(array $row): array{$breaks=$row['break_schedules']??[];unset($row['break_schedules']);$row=$this->clean($row);$row['break_schedules']=array_map(fn(array $break):array=>$this->clean($break),$breaks);return$row;}
    private function cleanNullable(?array $row): ?array{return$row===null?null:$this->clean($row);}
    private function clean(array $row,array $extra=[]): array{foreach(array_merge(['id','contract_id','weekly_schedule_id','created_at','created_by','created_by_name','updated_at','updated_by','updated_by_name','deleted_at','deleted_by','deleted_by_name','employee_name','project_name','fixed_term_reason_name','processed_by_name'],$extra)as$key)unset($row[$key]);return$row;}
    private function encode(?array $value): ?string{return$value===null?null:json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
}
