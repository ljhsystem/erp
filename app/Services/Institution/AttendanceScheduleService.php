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
        if((int)$schedule['break_minutes']>0&&$breaks===[])throw new \RuntimeException('상세 휴게구간이 입력되지 않은 근로계약입니다.');
        $breakSeconds=0;$resolvedBreaks=[];
        foreach($breaks as $break){$breakStart=$workDate.' '.$break['start_time'];$breakEnd=date('Y-m-d H:i:s',strtotime($workDate.' '.$break['end_time'].' +'.(int)$break['end_day_offset'].' day'));if(strtotime($breakStart)<strtotime($start)||strtotime($breakEnd)>strtotime($end)||strtotime($breakEnd)<=strtotime($breakStart))throw new \RuntimeException('상세 휴게구간이 예정 근무구간을 벗어났습니다.');$seconds=strtotime($breakEnd)-strtotime($breakStart);$breakSeconds+=$seconds;$resolvedBreaks[]=['start'=>$breakStart,'end'=>$breakEnd,'seconds'=>$seconds];}
        if($breakSeconds!==(int)$schedule['break_minutes']*60)throw new \RuntimeException('상세 휴게구간 합계와 계약 휴게시간이 일치하지 않습니다.');
        $scheduled=max(0,strtotime($end)-strtotime($start)-$breakSeconds);
        return ['exception'=>null,'contract_id'=>$contract['id'],'work_schedule_type'=>$contract['work_schedule_type']??null,'work_date'=>$workDate,'start'=>$start,'end'=>$end,'break_seconds'=>$breakSeconds,'breaks'=>$resolvedBreaks,'scheduled_seconds'=>$scheduled,'workday'=>true];
    }

    public function resolveWorkDate(string $employeeId,string $occurredAt): string
    {
        $eventTime=strtotime($occurredAt);if($eventTime===false)throw new \InvalidArgumentException('출퇴근 일시 형식이 올바르지 않습니다.');
        $eventDate=date('Y-m-d',$eventTime);$candidates=[];
        foreach([date('Y-m-d',strtotime($eventDate.' -1 day')),$eventDate] as $candidate)$candidates[$candidate]=$this->resolve($employeeId,$candidate);
        return $this->calculationPolicy->workDateForEvent($occurredAt,$candidates);
    }
}
