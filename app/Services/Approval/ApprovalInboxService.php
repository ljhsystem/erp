<?php

namespace App\Services\Approval;

use App\Models\Approval\ApprovalInboxModel;
use App\Models\User\EmployeeModel;
use Core\Helpers\ActorHelper;
use PDO;

class ApprovalInboxService
{
    private ApprovalInboxModel $inbox;
    private ApprovalDocumentAdapterRegistry $adapters;
    private EmployeeModel $employees;
    private ApprovalDocumentSummaryResolver $summaries;

    public function __construct(private readonly PDO $pdo)
    {
        $this->inbox = new ApprovalInboxModel($pdo);
        $this->adapters = new ApprovalDocumentAdapterRegistry($pdo);
        $this->employees = new EmployeeModel($pdo);
        $this->summaries = new ApprovalDocumentSummaryResolver($pdo);
    }

    public function list(array $query): array
    {
        $userId = $this->userId();
        $box = trim((string) ($query['box'] ?? 'actionable'));
        $page = $this->inbox->page($userId, $box, $query);
        $page['rows'] = $this->summaries->resolve($page['rows']);
        foreach ($page['rows'] as &$row) {
            $row['document_type_name'] = $this->adapters
                ->metadata((string) $row['document_type'])['display_name'];
        }
        unset($row);
        return [
            'success' => true,
            'data' => $page['rows'],
            'draw' => (int) ($query['draw'] ?? 0),
            'recordsTotal' => $page['total'],
            'recordsFiltered' => $page['filtered'],
        ];
    }

    public function detail(string $requestId): array
    {
        if ($requestId === '') {
            throw new \InvalidArgumentException('결재요청을 선택해 주세요.');
        }
        $userId = $this->userId();
        $request = $this->inbox->requestDetail($requestId, $userId);
        if (!$request) {
            throw new \RuntimeException('조회할 수 있는 결재문서가 아닙니다.');
        }
        $documentType = (string) $request['document_type'];
        $adapter = $this->adapters->get($documentType);
        $historyRows = $this->inbox->history($documentType, (string) $request['document_id']);
        $stepsByRequest = $this->inbox->stepsForRequests(array_column($historyRows, 'id'));
        $steps = $stepsByRequest[$requestId] ?? [];
        $history = $this->history($historyRows, $stepsByRequest, $requestId);
        $canAct = $adapter !== null && $this->canAct($request, $steps, $userId);
        $metadata = $this->adapters->metadata($documentType);
        $document = $adapter?->detail($request) ?? [
                'type' => $documentType,
                'type_name' => $metadata['display_name'],
                'header' => $request,
                'items' => [],
                'totals' => [],
                'attachments' => [],
                'attachment_supported' => false,
                'detail_supported' => false,
            ];
        $document['ui'] = $metadata;
        return ['success' => true, 'data' => [
            'request' => $request,
            'document' => $document,
            'steps' => $steps,
            'history' => $history,
            'actions' => ['can_act' => $canAct, 'step_id' => $canAct ? (string) $request['current_step_id'] : null],
        ]];
    }

    public function act(string $stepId, string $decision, ?string $comment): array
    {
        if ($stepId === '') {
            throw new \InvalidArgumentException('처리할 결재단계를 확인해 주세요.');
        }
        $decision = strtolower(trim($decision));
        if ($decision === 'rejected' && trim((string) $comment) === '') {
            throw new \InvalidArgumentException('반려사유를 입력해 주세요.');
        }
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new \InvalidArgumentException('결재 처리구분이 올바르지 않습니다.');
        }
        $request = $this->inbox->requestByStep($stepId);
        $adapter = $request ? $this->adapters->get((string) $request['document_type']) : null;
        if (!$adapter) {
            throw new \RuntimeException('지원하지 않는 결재문서 유형입니다.');
        }
        return $adapter->act($stepId, $decision, $comment);
    }

    private function history(array $rows, array $stepsByRequest, string $currentRequestId): array
    {
        foreach ($rows as &$row) {
            $row['is_current'] = (string) $row['id'] === $currentRequestId;
            $row['steps'] = $stepsByRequest[(string) $row['id']] ?? [];
        }
        unset($row);
        return $rows;
    }

    private function canAct(array $request, array $steps, string $userId): bool
    {
        if (!in_array((string) $request['status'], ['pending', 'in_progress'], true)) {
            return false;
        }
        foreach ($steps as $step) {
            if (
                (int) $step['sort_no'] < (int) $request['current_step']
                && (string) $step['status'] !== 'approved'
            ) {
                return false;
            }
            if (
                (int) $step['sort_no'] === (int) $request['current_step']
                && (string) $step['status'] === 'pending'
            ) {
                if (!in_array(strtoupper((string) ($step['step_type'] ?? '')), ['APPROVAL', 'FINAL_APPROVAL'], true)) {
                    return false;
                }
                $approverId = trim((string) ($step['approver_id'] ?? ''));
                if ($approverId !== '') {
                    return $approverId === $userId;
                }
                $roleId = trim((string) ($step['role_id'] ?? ''));
                return $roleId !== '' && $this->employees->userIsEligibleForRole($userId, $roleId);
            }
        }
        return false;
    }

    private function userId(): string
    {
        $parsed = ActorHelper::parse(ActorHelper::user());
        $userId = trim((string) ($parsed['id'] ?? ''));
        if ($userId === '') {
            throw new \RuntimeException('로그인 사용자 정보를 확인할 수 없습니다.');
        }
        return $userId;
    }
}
