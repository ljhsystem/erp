<?php

namespace App\Services\Approval;

use App\Services\Institution\EmploymentContractService;
use PDO;

class EmploymentContractApprovalAdapter implements ApprovalDocumentAdapterInterface
{
    private EmploymentContractService $contracts;

    public function __construct(PDO $pdo)
    {
        $this->contracts = new EmploymentContractService($pdo);
    }

    public function documentType(): string
    {
        return EmploymentContractService::DOCUMENT_TYPE;
    }

    public function uiMetadata(): array
    {
        return [
            'document_type'=>$this->documentType(), 'display_name'=>'근로계약', 'detail_section_title'=>'지급조건',
            'item_columns'=>[['component_name','급여항목'],['amount','계약금액'],['allowance_summary','근로수당 세부']],
            'total_fields'=>[['monthly_total_amount','월 지급합계','amount'],['annualized_amount','연 환산액','amount']],
            'final_approval_message'=>'이 근로계약을 최종 승인하시겠습니까?',
            'attachment_supported'=>false, 'supported'=>true,
        ];
    }

    public function detail(array $request): array
    {
        $detail = $this->contracts->detail((string) $request['document_id'], true)['data'];
        $monthlyTotalAmount = round(array_sum(array_map(
            static fn(array $row): float => (float) ($row['amount'] ?? 0),
            $detail['components']
        )), 2);
        $annualizedAmount = round($monthlyTotalAmount * 12, 2);
        $totals = $detail['compensation_summary'] + [
            'monthly_total_amount' => $monthlyTotalAmount,
            'annualized_amount' => $annualizedAmount,
        ];
        $totals['total_amount'] = $monthlyTotalAmount;
        return [
            'type' => $this->documentType(),
            'type_name' => '근로계약서',
            'header' => array_merge($request, $detail['contract'], [
                'request_id' => (string) $request['id'],
                'total_amount' => $monthlyTotalAmount,
                'monthly_total_amount' => $monthlyTotalAmount,
                'annualized_amount' => $annualizedAmount,
            ]),
            'items' => array_map(static function (array $row): array {
                $workTypeNames = ['OVERTIME' => '연장근로', 'NIGHT' => '야간근로', 'HOLIDAY' => '휴일근로'];
                $workType = trim((string) ($row['work_type'] ?? ''));
                $row['allowance_summary'] = $workType === '' ? '-': implode(' · ', array_filter([
                    $workTypeNames[$workType] ?? $workType,
                    !empty($row['quantity']) ? $row['quantity'] . '시간' : null,
                    !empty($row['premium_rate']) ? $row['premium_rate'] . '배' : null,
                    ($row['excess_payment_policy'] ?? '') === 'SEPARATE_PAYMENT' ? '초과분 별도 지급' : null,
                    $row['agreement_basis'] ?? null,
                ]));
                return $row;
            }, $detail['components']),
            'weekly_schedules' => $detail['weekly_schedules'],
            'work_schedule_policy' => $detail['work_schedule_policy'],
            'schedule_summary' => $detail['schedule_summary'],
            'totals' => $totals,
            'detail_supported' => true,
        ];
    }

    public function act(string $stepId, string $decision, ?string $comment): array
    {
        return $this->contracts->act($stepId, $decision, $comment);
    }
}
