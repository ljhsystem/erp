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
    public function uiMetadata(): array{return['document_type'=>$this->documentType(),'display_name'=>'취업규칙·인사규정 개정','detail_section_title'=>'규정 개정정보','item_columns'=>[],'total_fields'=>[],'final_approval_message'=>'이 규정 개정본을 최종 승인하시겠습니까?','attachment_supported'=>false,'supported'=>true];}
    public function detail(array $request): array
    {
        $detail = $this->service->detail((string) $request['document_id'])['data'];
        return ['type'=>$this->documentType(),'type_name'=>'취업규칙·인사규정 개정','header'=>array_merge($request,$detail,['request_id'=>$request['id']]),'items'=>[],'attachments'=>[],'detail_supported'=>true];
    }
    public function act(string $stepId, string $decision, ?string $comment): array
    {
        $userId = (string) ((new AuthSessionService())->getCurrentUser()['id'] ?? '');
        return $this->service->act($stepId, $decision, $comment, $userId);
    }
}
