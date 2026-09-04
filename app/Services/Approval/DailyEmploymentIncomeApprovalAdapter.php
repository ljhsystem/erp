<?php

declare(strict_types=1);

namespace App\Services\Approval;

use App\Services\Institution\DailyEmploymentIncomeService;
use PDO;

final class DailyEmploymentIncomeApprovalAdapter implements ApprovalDocumentAdapterInterface
{
    private DailyEmploymentIncomeService $service;

    public function __construct(PDO $pdo)
    {
        $this->service = new DailyEmploymentIncomeService($pdo);
    }

    public function documentType(): string
    {
        return DailyEmploymentIncomeService::DOCUMENT_TYPE;
    }

    public function uiMetadata(): array
    {
        return [
            'document_type' => $this->documentType(),
            'display_name' => '일용근로소득',
            'detail_section_title' => '그룹·근로자별 지급내역',
            'item_columns' => [
                ['sort_no', '순번'],
                ['business_division_name', '사업구분'],
                ['worker_name_snapshot', '근로자'],
                ['total_work_days', '근무일수'],
                ['total_gross_amount', '지급액(세전)'],
                ['total_deduction_amount', '총 원천징수'],
                ['total_net_payment_amount', '실지급액'],
                ['total_employer_burden_amount', '회사부담'],
            ],
            'total_fields' => [
                ['total_gross_amount', '지급액(세전)', 'amount'],
                ['total_deduction_amount', '총 원천징수', 'amount'],
                ['total_net_payment_amount', '실지급액', 'amount'],
                ['total_employer_burden_amount', '회사부담 합계', 'amount'],
            ],
            'final_approval_message' => '최종 승인하면 그룹·근로자별 증빙과 지급 거래가 생성됩니다. 승인하시겠습니까?',
            'attachment_supported' => false,
            'supported' => true,
        ];
    }

    public function detail(array $request): array
    {
        return $this->service->approvalDetail($request);
    }

    public function act(string $stepId, string $decision, ?string $comment): array
    {
        return $this->service->act($stepId, $decision, $comment);
    }
}
