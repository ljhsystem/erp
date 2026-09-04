<?php

namespace App\Services\Institution;

use App\Models\Institution\AttendanceModel;

class AttendanceScheduleService
{
    private AttendanceCalculationPolicy $calculationPolicy;

    public function __construct(
        private readonly AttendanceModel $model,
        private readonly EmploymentContractValidityService $contracts,
        ?AttendanceCalculationPolicy $calculationPolicy = null
    ) {$this->calculationPolicy=$calculationPolicy??new AttendanceCalculationPolicy();}

    public function resolve(string $employeeId,string $workDate): array
    {
        $contracts=$this->contracts->effectiveContracts($employeeId,$workDate);
        if(count($contracts)>1)return ['exception'=>'CONTRACT_CONFLICT','contract_id'=>null,'start'=>null,'end'=>null,'break_seconds'=>0,'scheduled_seconds'=>0,'workday'=>false];
        if(!$contracts)return ['exception'=>'NO_SCHEDULE','contract_id'=>null,'start'=>null,'end'=>null,'break_seconds'=>0,'scheduled_seconds'=>0,'workday'=>false];
        $contract=$contracts[0];$day=(int)date('N',strtotime($workDate));$schedule=$this->model->weeklySchedule((string)$contract['id'],$day);
        if(!$schedule)return ['exception'=>'NO_SCHEDULE','contract_id'=>$contract['id'],'start'=>null,'end'=>null,'break_seconds'=>0,'scheduled_seconds'=>0,'workday'=>false];
        if((string)$schedule['day_type']!=='WORKDAY')return ['exception'=>null,'contract_id'=>$contract['id'],'start'=>null,'end'=>null,'break_seconds'=>0,'scheduled_seconds'=>0,'workday'=>false];
        $start=$workDate.' '.$schedule['start_time'];$end=date('Y-m-d H:i:s',strtotime($workDate.' '.$schedule['end_time'].' +'.(int)$schedule['end_day_offset'].' day'));
        $breaks=$this->model->scheduleBreaks((string)$schedule['id']);
        $breakSeconds=0;$resolvedBreaks=[];$calculationIssue=null;
        if($breaks!==[]){
            foreach($breaks as $break){
                $breakStart=$workDate.' '.$break['start_time'];
                $breakEnd=date('Y-m-d H:i:s',strtotime($workDate.' '.$break['end_time'].' +'.(int)$break['end_day_offset'].' day'));
                if(strtotime($breakStart)<strtotime($start)||strtotime($breakEnd)>strtotime($end)||strtotime($breakEnd)<=strtotime($breakStart)){$calculationIssue='BREAK_SCHEDULE_INVALID';$resolvedBreaks=[];$breakSeconds=0;break;}
                $seconds=strtotime($breakEnd)-strtotime($breakStart);$breakSeconds+=$seconds;$resolvedBreaks[]=['start'=>$breakStart,'end'=>$breakEnd,'seconds'=>$seconds];
            }
            if($calculationIssue===null&&$breakSeconds!==(int)$schedule['break_minutes']*60){$calculationIssue='BREAK_SCHEDULE_MISMATCH';$resolvedBreaks=[];$breakSeconds=0;}
        }
        $requiredBreakSeconds=(int)$schedule['break_minutes']*60;
        $scheduled=max(0,strtotime($end)-strtotime($start)-$requiredBreakSeconds);
        return ['exception'=>null,'calculation_issue_code'=>$calculationIssue,'calculation_issue_message'=>$this->calculationIssueMessage($calculationIssue),'contract_id'=>$contract['id'],'work_schedule_type'=>$contract['work_schedule_type']??null,'work_date'=>$workDate,'start'=>$start,'end'=>$end,'break_seconds'=>$requiredBreakSeconds,'breaks'=>$resolvedBreaks,'scheduled_seconds'=>$scheduled,'workday'=>true];
    }

    public function resolveWorkDate(string $employeeId,string $occurredAt): string
    {
        $eventTime=strtotime($occurredAt);if($eventTime===false)throw new \InvalidArgumentException('출퇴근 일시 형식이 올바르지 않습니다.');
        $eventDate=date('Y-m-d',$eventTime);$candidates=[];
        foreach([date('Y-m-d',strtotime($eventDate.' -1 day')),$eventDate] as $candidate)$candidates[$candidate]=$this->resolve($employeeId,$candidate);
        return $this->calculationPolicy->workDateForEvent($occurredAt,$candidates);
    }

    private function calculationIssueMessage(?string $code): ?string
    {
        return match($code){
            'BREAK_SCHEDULE_INVALID'=>'근로계약의 상세 휴게시간이 예정 근무구간을 벗어나 근무시간 확인이 필요합니다.',
            'BREAK_SCHEDULE_MISMATCH'=>'근로계약의 휴게시간 총량과 상세 휴게시간이 일치하지 않아 근무시간 확인이 필요합니다.',
            default=>null,
        };
    }
}
