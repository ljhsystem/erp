<?php

namespace App\Services\Approval;

use App\Services\Institution\LeaveService;
use PDO;

class LeaveApprovalAdapter implements ApprovalDocumentAdapterInterface
{
    private LeaveService $service;
    public function __construct(PDO $pdo){$this->service=new LeaveService($pdo);}
    public function documentType(): string{return LeaveService::DOCUMENT_TYPE;}
    public function uiMetadata(): array{return['document_type'=>$this->documentType(),'display_name'=>'휴가신청','detail_section_title'=>'휴가 사용일','item_columns'=>[['leave_date','휴가일'],['type_name','휴가종류'],['request_unit_code','신청단위'],['requested_start_at','시작시각'],['requested_end_at','종료시각'],['requested_minutes','신청시간(분)']],'total_fields'=>[['requested_total_minutes','총 신청시간','minutes']],'final_approval_message'=>'이 휴가신청을 최종 승인하시겠습니까?','attachment_supported'=>false,'supported'=>true];}
    public function detail(array $request): array{$detail=$this->service->detail((string)$request['document_id'])['data'];return['type'=>$this->documentType(),'type_name'=>'휴가신청','header'=>array_merge($request,$detail,['request_id'=>$request['id']]),'items'=>$detail['items'],'totals'=>['requested_total_minutes'=>$detail['requested_total_minutes']],'attachments'=>[],'attachment_supported'=>false,'detail_supported'=>true];}
    public function act(string $stepId,string $decision,?string $comment): array{return$this->service->act($stepId,$decision,$comment);}
}
