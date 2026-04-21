<?php

namespace App\Services\Approval;

use App\Models\User\ApprovalRequestModel;
use App\Models\User\ApprovalRequestStepModel;
use App\Models\User\ApprovalTemplateStepModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;

class RequestStepService
{
    private readonly PDO $pdo;
    private ApprovalRequestModel $requestModel;
    private ApprovalRequestStepModel $stepModel;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->requestModel = new ApprovalRequestModel($pdo);
        $this->stepModel = new ApprovalRequestStepModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-approval.step');
    }

    private function currentActor(): string
    {
        return ActorHelper::user();
    }

    public function createStep(array $data): bool
    {
        $data['id'] = UuidHelper::generate();
        $data['created_by'] = $data['created_by'] ?? $this->currentActor();

        $this->logger->info('스텝 생성', ['data' => $data]);

        return $this->stepModel->create($data);
    }

    public function getSteps(string $requestId): array
    {
        return $this->stepModel->getSteps($requestId);
    }

    public function getStepById(string $stepId): ?array
    {
        return $this->stepModel->getById($stepId);
    }

    public function updateStepStatus(string $stepId, string $status, ?string $comment): bool
    {
        $step = $this->stepModel->getById($stepId);
        if (!$step) {
            $this->logger->warning('스텝 없음', ['step_id' => $stepId]);
            return false;
        }

        $actor = $this->currentActor();

        $this->logger->info('스텝 상태 변경', [
            'step_id' => $stepId,
            'status' => $status,
            'actor' => $actor,
        ]);

        $ok = $this->stepModel->updateStatus($stepId, $status, $comment, $actor);
        if (!$ok) {
            return false;
        }

        if ($status === 'approved') {
            return $this->moveToNextStep($step);
        }

        if ($status === 'rejected') {
            return $this->rejectRequest($step['request_id'], $actor);
        }

        return true;
    }

    private function moveToNextStep(array $currentStep): bool
    {
        $requestId = $currentStep['request_id'];
        $sequence = (int) $currentStep['sequence'];

        $steps = $this->stepModel->getSteps($requestId);
        $total = count($steps);

        if ($sequence >= $total) {
            return $this->approveRequest($requestId);
        }

        $next = $sequence + 1;

        $this->logger->info('다음 스텝 이동', [
            'request_id' => $requestId,
            'from' => $sequence,
            'to' => $next,
        ]);

        return $this->requestModel->updateCurrentStep($requestId, $next);
    }

    private function approveRequest(string $requestId): bool
    {
        $this->logger->info('요청 전체 승인', ['request_id' => $requestId]);
        return $this->requestModel->updateStatus($requestId, 'approved');
    }

    private function rejectRequest(string $requestId, string $actor): bool
    {
        $this->logger->info('요청 전체 반려', [
            'request_id' => $requestId,
            'rejected_by' => $actor,
        ]);

        return $this->requestModel->updateStatus($requestId, 'rejected', $actor);
    }

    public function getTemplateSteps(string $templateId): array
    {
        $tplModel = new ApprovalTemplateStepModel($this->pdo);

        return $tplModel->getSteps($templateId);
    }

    public function deleteStep(string $stepId): bool
    {
        try {
            $this->logger->info('스텝 삭제 시도', ['step_id' => $stepId]);

            $step = $this->stepModel->getById($stepId);
            if (!$step) {
                $this->logger->warning('삭제 대상 스텝 없음', ['step_id' => $stepId]);
                return false;
            }

            $ok = $this->stepModel->delete($stepId);

            $this->logger->info('스텝 삭제 결과', [
                'step_id' => $stepId,
                'success' => $ok,
            ]);

            return $ok;
        } catch (\Throwable $e) {
            $this->logger->error('deleteStep error: ' . $e->getMessage(), [
                'step_id' => $stepId,
                'exception' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }
}
