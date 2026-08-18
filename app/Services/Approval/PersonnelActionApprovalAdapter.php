<?php

namespace App\Services\Approval;

use App\Services\Institution\PersonnelActionService;
use App\Services\Institution\PersonnelActionChangePolicy;
use PDO;

class PersonnelActionApprovalAdapter implements ApprovalDocumentAdapterInterface
{
    private PersonnelActionService $service;
    public function __construct(PDO $pdo){$this->service=new PersonnelActionService($pdo);}
    public function documentType(): string{return PersonnelActionService::DOCUMENT_TYPE;}
    public function uiMetadata(): array{return['document_type'=>$this->documentType(),'display_name'=>'인사발령','detail_section_title'=>'발령 대상 및 변경내용','item_columns'=>[['employee_name','대상 직원'],['change_type_name','변경 항목'],['before_display_snapshot','변경 전'],['after_display_snapshot','변경 후'],['individual_reason','개별 사유']],'total_fields'=>[['target_count','대상자','count'],['change_count','변경항목','count']],'final_approval_message'=>'이 인사발령을 최종 승인하시겠습니까?','attachment_supported'=>false,'supported'=>true];}
    public function detail(array $request): array { $detail=$this->service->detail((string)$request['document_id'])['data'];$items=[];foreach($detail['targets'] as $target){foreach($target['changes'] as $change)$items[]=$change+['change_type_name'=>PersonnelActionChangePolicy::label((string)$change['change_type_code']),'employee_name'=>$target['employee_name'],'individual_reason'=>$target['individual_reason']];}return['type'=>$this->documentType(),'type_name'=>'인사발령','header'=>array_merge($request,$detail['action'],['request_id'=>$request['id']]),'items'=>$items,'targets'=>$detail['targets'],'totals'=>['target_count'=>count($detail['targets']),'change_count'=>count($items)],'attachments'=>[],'attachment_supported'=>false,'detail_supported'=>true]; }
    public function act(string $stepId,string $decision,?string $comment): array{return $this->service->act($stepId,$decision,$comment);}
}
