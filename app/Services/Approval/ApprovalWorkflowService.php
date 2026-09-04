<?php

namespace App\Services\Approval;

use App\Models\User\ApprovalRequestModel;
use App\Models\User\ApprovalRequestStepModel;
use App\Models\User\ApprovalTemplateModel;
use App\Models\User\ApprovalTemplateStepModel;
use App\Models\User\EmployeeModel;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use Psr\Log\LoggerInterface;
use PDO;

class ApprovalWorkflowService
{
    private ApprovalRequestModel $requests;
    private ApprovalRequestStepModel $steps;
    private ApprovalTemplateModel $templates;
    private ApprovalTemplateStepModel $templateSteps;
    private EmployeeModel $employees;
    private LoggerInterface $logger;

    public function __construct(private readonly PDO $pdo)
    {
        $this->requests = new ApprovalRequestModel($pdo);
        $this->steps = new ApprovalRequestStepModel($pdo);
        $this->templates = new ApprovalTemplateModel($pdo);
        $this->templateSteps = new ApprovalTemplateStepModel($pdo);
        $this->employees = new EmployeeModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-approval-workflow');
    }

    public function submit(string $documentType, string $documentId, string $userId, string $actor): array
    {
        return $this->logged('APPROVAL_REQUEST_SUBMIT', 'submit', ['document_type' => $documentType, 'document_id' => $documentId, 'actor' => $actor], fn(): array => $this->submitInternal($documentType, $documentId, $userId, $actor));
    }

    private function submitInternal(string $documentType, string $documentId, string $userId, string $actor): array
    {
        $latest = $this->requests->latestForDocument($documentType, $documentId, true);
        if ($latest && !in_array((string) $latest['status'], ['rejected', 'withdrawn'], true)) {
            throw new \RuntimeException('이미 결재 진행 중이거나 승인된 문서입니다.');
        }
        $template = $this->templates->findActiveByDocumentType($documentType, true);
        if (!$template) {
            throw new \RuntimeException('활성 결재템플릿이 없습니다. 관리자에게 문의해 주세요.');
        }
        $templateId = (string) $template['id'];
        $this->templateSteps->lockTemplate($templateId);
        $sourceSteps = $this->templateSteps->getActiveSteps($templateId);
        if ($sourceSteps === []) {
            throw new \RuntimeException('결재단계가 설정되지 않았습니다.');
        }

        $resolved = [];
        $firstApprovalSortNo = null;
        foreach ($sourceSteps as $index => $source) {
            $sortNo = $index + 1;
            $stepType = strtoupper(trim((string) ($source['step_type'] ?? 'APPROVAL')));
            $roleId = trim((string) ($source['role_id'] ?? ''));
            $approverId = trim((string) ($source['approver_id'] ?? ''));
            $stepName = trim((string) ($source['step_name'] ?? '')) ?: $sortNo . '단계';

            if ($stepType === 'SUBMIT') {
                $resolved[] = [
                    'sort_no' => $sortNo, 'step_name' => $stepName, 'step_type' => 'SUBMIT',
                    'role_id' => null, 'approver_id' => null, 'acted_by' => $userId,
                    'action_at' => date('Y-m-d H:i:s'), 'status' => 'approved',
                ];
                continue;
            }
            if (!in_array($stepType, ['APPROVAL', 'FINAL_APPROVAL'], true)) {
                throw new \RuntimeException($stepName . '의 단계유형이 올바르지 않습니다.');
            }
            if ($approverId === '' && $roleId === '') {
                throw new \RuntimeException($stepName . '의 결재 역할 또는 결재자를 설정해 주세요.');
            }
            if ($approverId !== '' && $roleId !== ''
                && !$this->employees->userIsEligibleForRole($approverId, $roleId)) {
                throw new \RuntimeException($stepName . '의 지정 결재자가 역할 조건을 충족하지 않습니다.');
            }
            if ($approverId !== '' && $roleId === '') {
                $eligibility = $this->employees->userEligibility($approverId);
                if (!($eligibility['eligible'] ?? false)) {
                    throw new \RuntimeException($stepName . ': ' . ($eligibility['message'] ?? '지정 결재자가 적격하지 않습니다.'));
                }
            }
            if ($approverId === '' && !$this->employees->hasEligibleUserForRole($roleId)) {
                throw new \RuntimeException($stepName . '을 처리할 적격 역할 사용자가 없습니다.');
            }
            $firstApprovalSortNo ??= $sortNo;
            $resolved[] = [
                'sort_no' => $sortNo, 'step_name' => $stepName, 'step_type' => $stepType,
                'role_id' => $roleId ?: null, 'approver_id' => $approverId ?: null,
                'acted_by' => null, 'action_at' => null,
                'status' => $firstApprovalSortNo === $sortNo ? 'pending' : 'waiting',
            ];
        }
        if ($firstApprovalSortNo === null) {
            throw new \RuntimeException('실제 승인단계가 설정되지 않았습니다.');
        }

        $requestId = UuidHelper::generate();
        if (!$this->requests->create([
            'id' => $requestId, 'sort_no' => $this->requests->nextSortNo(),
            'template_id' => $templateId, 'document_type' => $documentType,
            'document_id' => $documentId, 'requester_id' => $userId,
            'status' => 'pending', 'current_step' => $firstApprovalSortNo,
            'is_active' => 1, 'created_by' => $actor,
        ])) {
            throw new \RuntimeException('결재요청을 생성하지 못했습니다.');
        }
        foreach ($resolved as $step) {
            if (!$this->steps->create($step + [
                'id' => UuidHelper::generate(), 'request_id' => $requestId, 'created_by' => $actor,
            ])) {
                throw new \RuntimeException('결재단계 스냅샷을 생성하지 못했습니다.');
            }
        }
        $this->logger->info('결재요청이 생성되었습니다.', [
            'event_code' => 'APPROVAL_REQUEST_SUBMITTED', 'result' => 'SUCCESS',
            'service' => self::class, 'action' => 'submit', 'actor' => $actor,
            'document_type' => $documentType, 'document_id' => $documentId,
            'request_id' => $requestId, 'current_step' => $firstApprovalSortNo,
        ]);
        return ['request_id' => $requestId, 'current_step' => $firstApprovalSortNo];
    }

    public function act(
        string $stepId,
        string $documentType,
        string $decision,
        ?string $comment,
        string $userId,
        string $actor
    ): array {
        return $this->logged('APPROVAL_STEP_ACT', 'act', ['document_type' => $documentType, 'step_id' => $stepId, 'actor' => $actor], fn(): array => $this->actInternal($stepId, $documentType, $decision, $comment, $userId, $actor));
    }

    private function actInternal(string $stepId, string $documentType, string $decision, ?string $comment, string $userId, string $actor): array {
        $decision = strtolower(trim($decision));
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new \InvalidArgumentException('결재 처리구분이 올바르지 않습니다.');
        }
        if ($decision === 'rejected' && trim((string) $comment) === '') {
            throw new \InvalidArgumentException('반려사유를 입력해 주세요.');
        }
        $step = $this->steps->getById($stepId, true);
        $request = $step ? $this->requests->getById((string) $step['request_id'], true) : null;
        if (!$step || !$request || (string) $request['document_type'] !== $documentType) {
            throw new \RuntimeException('결재요청을 찾을 수 없습니다.');
        }
        if (!in_array((string) $request['status'], ['pending', 'in_progress'], true)
            || (int) $request['current_step'] !== (int) $step['sort_no']
            || (string) $step['status'] !== 'pending') {
            throw new \RuntimeException('현재 처리할 결재단계가 아닙니다.');
        }
        $approverId = trim((string) ($step['approver_id'] ?? ''));
        $roleId = trim((string) ($step['role_id'] ?? ''));
        if (($approverId !== '' && $approverId !== $userId)
            || ($approverId === '' && ($roleId === ''
                || !$this->employees->userIsEligibleForRole($userId, $roleId)))) {
            throw new \RuntimeException('현재 사용자는 이 결재단계를 처리할 수 없습니다.');
        }
        if (!$this->steps->act($stepId, $decision, $comment, $userId, $roleId ?: null, $actor)) {
            throw new \RuntimeException('이미 처리되었거나 처리 권한이 없습니다.');
        }
        if ($decision === 'rejected') {
            $this->steps->cancelRemaining((string) $request['id'], $actor);
            $this->requests->updateStatus((string) $request['id'], 'rejected', $actor);
            $this->logDecision($actor, $documentType, $request, $stepId, 'REJECTED');
            return ['state' => 'REJECTED', 'request' => $request];
        }
        foreach ($this->steps->getSteps((string) $request['id']) as $next) {
            if ((int) $next['sort_no'] > (int) $step['sort_no']
                && (string) $next['status'] === 'waiting') {
                if (!$this->steps->activate((string) $request['id'], (int) $next['sort_no'], $actor)) {
                    throw new \RuntimeException('다음 결재단계를 활성화하지 못했습니다.');
                }
                $this->requests->updateCurrentStep(
                    (string) $request['id'], (int) $next['sort_no'], $actor
                );
                $this->logDecision($actor, $documentType, $request, $stepId, 'IN_PROGRESS');
                return ['state' => 'IN_PROGRESS', 'request' => $request];
            }
        }
        $this->requests->updateStatus((string) $request['id'], 'approved', $actor);
        $this->logDecision($actor, $documentType, $request, $stepId, 'APPROVED');
        return ['state' => 'APPROVED', 'request' => $request];
    }

    public function withdraw(
        string $requestId,
        string $documentType,
        string $userId,
        string $actor
    ): array {
        return $this->logged('APPROVAL_REQUEST_WITHDRAW', 'withdraw', ['document_type' => $documentType, 'request_id' => $requestId, 'actor' => $actor], fn(): array => $this->withdrawInternal($requestId, $documentType, $userId, $actor));
    }

    private function withdrawInternal(string $requestId, string $documentType, string $userId, string $actor): array {
        $request = $this->requests->getById($requestId, true);
        if (!$request || (string) $request['document_type'] !== $documentType
            || (string) $request['requester_id'] !== $userId
            || !$this->requests->withdraw($requestId, $userId, $actor)) {
            throw new \RuntimeException('현재 상태에서는 기안을 회수할 수 없습니다.');
        }
        $this->steps->cancelRemaining($requestId, $actor);
        $this->logger->info('결재요청이 회수되었습니다.', [
            'event_code' => 'APPROVAL_REQUEST_WITHDRAWN', 'result' => 'SUCCESS',
            'service' => self::class, 'action' => 'withdraw', 'actor' => $actor,
            'document_type' => $documentType, 'document_id' => (string) $request['document_id'],
            'request_id' => $requestId,
        ]);
        return $request;
    }

    private function logged(string $eventCode, string $action, array $context, callable $operation): array
    {
        try { return $operation(); }
        catch (\PDOException $exception) {
            $this->logger->error('결재 업무 처리에 실패했습니다.', ['event_code' => $eventCode . '_FAILED', 'result' => 'FAILED', 'service' => self::class, 'action' => $action, 'error_code' => get_class($exception), 'error' => $exception] + $context); throw $exception;
        } catch (\InvalidArgumentException|\DomainException|\RuntimeException $exception) {
            $this->logger->warning('결재 업무 처리가 차단되었습니다.', ['event_code' => $eventCode . '_BLOCKED', 'result' => 'BLOCKED', 'service' => self::class, 'action' => $action, 'error_code' => get_class($exception), 'error' => $exception] + $context); throw $exception;
        } catch (\Throwable $exception) {
            $this->logger->error('결재 업무 처리에 실패했습니다.', ['event_code' => $eventCode . '_FAILED', 'result' => 'FAILED', 'service' => self::class, 'action' => $action, 'error_code' => get_class($exception), 'error' => $exception] + $context); throw $exception;
        }
    }

    private function logDecision(string $actor, string $documentType, array $request, string $stepId, string $state): void
    {
        $this->logger->info('결재단계가 처리되었습니다.', [
            'event_code' => 'APPROVAL_STEP_ACTED', 'result' => 'SUCCESS',
            'service' => self::class, 'action' => 'act', 'actor' => $actor,
            'document_type' => $documentType, 'document_id' => (string) $request['document_id'],
            'request_id' => (string) $request['id'], 'step_id' => $stepId, 'state' => $state,
        ]);
    }
}
