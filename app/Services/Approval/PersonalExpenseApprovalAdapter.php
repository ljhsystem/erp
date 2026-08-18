<?php

namespace App\Services\Approval;

use App\Models\Approval\ApprovalInboxModel;
use PDO;

class PersonalExpenseApprovalAdapter implements ApprovalDocumentAdapterInterface
{
    private ApprovalInboxModel $inbox;
    private PersonalExpenseApprovalService $approval;

    public function __construct(PDO $pdo)
    {
        $this->inbox = new ApprovalInboxModel($pdo);
        $this->approval = new PersonalExpenseApprovalService($pdo);
    }

    public function documentType(): string
    {
        return 'PERSONAL_EXPENSE';
    }

    public function uiMetadata(): array
    {
        return [
            'document_type' => $this->documentType(), 'display_name' => '개인경비',
            'detail_section_title' => '개인경비 아이템',
            'item_columns' => [
                ['sort_no','순번'], ['expense_date','지출일자'], ['expense_category_name','경비구분'],
                ['payment_method_name','지출수단'], ['receipt_type_name','증빙종류'], ['project_name','프로젝트'],
                ['client_name','거래처'], ['merchant_name','가맹점'], ['item_name','품명'],
                ['item_quantity','수량'], ['item_unit_price','단가'], ['item_supply_amount','공급가액'],
                ['item_vat_amount','부가세'], ['item_total_amount','합계금액'], ['item_description','적요'], ['item_memo','메모'],
            ],
            'total_fields' => [['supply_amount_total','공급가액 합계','amount'],['vat_amount_total','부가세 합계','amount'],['total_amount','총금액','amount']],
            'final_approval_message' => '최종 승인하면 개인경비 신청내용을 기준으로 증빙원본과 거래가 생성됩니다. 승인하시겠습니까?',
            'attachment_supported' => false, 'supported' => true,
        ];
    }

    public function detail(array $request): array
    {
        return [
            'type' => $this->documentType(),
            'type_name' => '개인경비 신청서',
            'header' => $request + ['request_id' => (string) $request['id']],
            'items' => $this->inbox->items((string) $request['document_id']),
            'totals' => $this->inbox->totals((string) $request['document_id']),
            'attachments' => [],
            'attachment_supported' => false,
            'detail_supported' => true,
        ];
    }

    public function act(string $stepId, string $decision, ?string $comment): array
    {
        return $this->approval->act($stepId, $decision, $comment);
    }
}
