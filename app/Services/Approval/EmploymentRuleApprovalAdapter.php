<?php
namespace App\Services\Approval;

use App\Services\Auth\AuthSessionService;
use App\Services\Institution\EmploymentRuleService;
use PDO;

class EmploymentRuleApprovalAdapter implements ApprovalDocumentAdapterInterface
{
    private EmploymentRuleService $service;
    public function __construct(PDO $pdo) { $this->service = new EmploymentRuleService($pdo); }
    public function documentType(): string { return EmploymentRuleService::DOCUMENT_TYPE; }
    public function uiMetadata(): array{return['document_type'=>$this->documentType(),'display_name'=>'취업규칙 개정','detail_section_title'=>'개정 정책 항목','item_columns'=>[['sort_no','순번'],['policy_code','정책'],['value_type_code','값 유형'],['value_text','문자 값'],['value_number','숫자 값'],['unit_code','단위'],['operator_code','연산자'],['note','비고']],'total_fields'=>[],'final_approval_message'=>'이 취업규칙 개정을 최종 승인하시겠습니까?','attachment_supported'=>false,'supported'=>true];}
    public function detail(array $request): array
    {
        $detail = $this->service->detail((string) $request['document_id'])['data'];
        return ['type'=>$this->documentType(),'type_name'=>'취업규칙 개정','header'=>array_merge($request,$detail,['request_id'=>$request['id']]),'items'=>$detail['items'],'attachments'=>[],'detail_supported'=>true];
    }
    public function act(string $stepId, string $decision, ?string $comment): array
    {
        $userId = (string) ((new AuthSessionService())->getCurrentUser()['id'] ?? '');
        return $this->service->act($stepId, $decision, $comment, $userId);
    }
}
