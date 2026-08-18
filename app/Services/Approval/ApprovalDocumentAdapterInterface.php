<?php

namespace App\Services\Approval;

interface ApprovalDocumentAdapterInterface
{
    public function documentType(): string;
    /**
     * 결재함 공용 UI가 사용하는 최소 표시 계약을 반환한다.
     * 승인/반려 및 최종승인 후속처리는 도메인 Service의 트랜잭션 안에서 원자적으로 수행해야 한다.
     */
    public function uiMetadata(): array;
    public function detail(array $request): array;
    public function act(string $stepId, string $decision, ?string $comment): array;
}
