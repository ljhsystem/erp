<?php

namespace App\Services\Approval;

use PDO;

class ApprovalDocumentAdapterRegistry
{
    /** @var array<string, ApprovalDocumentAdapterInterface> */
    private array $adapters = [];

    public function __construct(PDO $pdo)
    {
        foreach ([
            new PersonalExpenseApprovalAdapter($pdo),
            new EmploymentContractApprovalAdapter($pdo),
            new PersonnelActionApprovalAdapter($pdo),
            new LeaveApprovalAdapter($pdo),
            new EmploymentRuleApprovalAdapter($pdo),
            new RegularEmploymentIncomeApprovalAdapter($pdo),
            new DailyEmploymentIncomeApprovalAdapter($pdo),
            new BusinessIncomeApprovalAdapter($pdo),
        ] as $adapter) {
            $this->adapters[$adapter->documentType()] = $adapter;
        }
    }

    public function get(string $documentType): ?ApprovalDocumentAdapterInterface
    {
        return $this->adapters[$documentType] ?? null;
    }

    public function metadata(string $documentType): array
    {
        return $this->get($documentType)?->uiMetadata() ?? [
            'document_type' => $documentType,
            'display_name' => $documentType,
            'detail_section_title' => '문서 상세내용',
            'item_columns' => [],
            'total_fields' => [],
            'final_approval_message' => '이 문서를 최종 승인하시겠습니까?',
            'attachment_supported' => false,
            'supported' => false,
        ];
    }
}
