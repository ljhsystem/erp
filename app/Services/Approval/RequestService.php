<?php

namespace App\Services\Approval;

use App\Models\User\ApprovalRequestModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;

class RequestService
{
    private readonly PDO $pdo;
    private ApprovalRequestModel $model;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->model = new ApprovalRequestModel($this->pdo);
        $this->logger = LoggerFactory::getLogger('service-user.ApprovalRequestService');
    }

    private function currentActor(): string
    {
        return ActorHelper::user();
    }

    private function currentUserId(): ?string
    {
        $actor = ActorHelper::parse($this->currentActor());
        return $actor['id'] ?? null;
    }

    public function createRequest(array $data): array
    {
        try {
            $id = UuidHelper::generate();
            $requesterId = $data['requester_id'] ?? $this->currentUserId();
            $actor = $data['created_by'] ?? $this->currentActor();

            $payload = [
                'id' => $id,
                'template_id' => $data['template_id'],
                'document_id' => $data['document_id'],
                'requester_id' => $requesterId,
                'status' => $data['status'] ?? 'pending',
                'current_step' => $data['current_step'] ?? 1,
                'is_active' => $data['is_active'] ?? 1,
                'created_by' => $actor,
            ];

            $this->logger->info('Approval request create', $payload);

            $ok = $this->model->create($payload);

            return [
                'success' => $ok,
                'id' => $id,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Approval request create error: ' . $e->getMessage(), [
                'data' => $data,
                'exception' => $e->getTraceAsString(),
            ]);

            return ['success' => false];
        }
    }

    public function updateStatus(string $id, string $status, string $userId): bool
    {
        try {
            $this->logger->info('Update approval status', [
                'id' => $id,
                'status' => $status,
                'updated_by' => $userId,
            ]);

            return $this->model->updateStatus($id, $status, $userId);
        } catch (\Throwable $e) {
            $this->logger->error('Approval updateStatus error: ' . $e->getMessage());
            return false;
        }
    }

    public function moveToStep(string $id, int $step, string $userId): bool
    {
        try {
            $this->logger->info('Approval move step', [
                'id' => $id,
                'step' => $step,
            ]);

            return $this->model->updateCurrentStep($id, $step, $userId);
        } catch (\Throwable $e) {
            $this->logger->error('Approval moveToStep error: ' . $e->getMessage());
            return false;
        }
    }

    public function getById(string $id): ?array
    {
        return $this->model->getById($id);
    }

    public function delete(string $id): bool
    {
        try {
            $this->logger->info('Approval request delete', ['id' => $id]);
            return $this->model->delete($id);
        } catch (\Throwable $e) {
            $this->logger->error('Approval delete error: ' . $e->getMessage());
            return false;
        }
    }

    public function createRequestWithSteps(array $data): array
    {
        try {
            $actor = $this->currentActor();
            $requesterId = $data['requester_id'] ?? $this->currentUserId();

            $req = $this->createRequest([
                'template_id' => $data['template_id'],
                'document_id' => $data['document_id'],
                'requester_id' => $requesterId,
                'created_by' => $actor,
            ]);

            if (empty($req['success']) || empty($req['id'])) {
                return ['success' => false, 'message' => '요청 생성 실패'];
            }

            $requestId = $req['id'];

            $stepService = new RequestStepService($this->pdo);
            $steps = $stepService->getTemplateSteps($data['template_id']);
            if (empty($steps)) {
                return [
                    'success' => false,
                    'message' => '템플릿 스텝이 없습니다.',
                ];
            }

            $seq = 1;
            foreach ($steps as $step) {
                $stepService->createStep([
                    'request_id' => $requestId,
                    'sequence' => $seq++,
                    'approver_id' => $step['approver_id'],
                    'role_id' => $step['role_id'],
                    'status' => 'pending',
                    'created_by' => $actor,
                ]);
            }

            return [
                'success' => true,
                'request_id' => $requestId,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('createRequestWithSteps error: ' . $e->getMessage(), [
                'data' => $data,
                'exception' => $e->getTraceAsString(),
            ]);

            return ['success' => false, 'message' => '결재 요청 처리 중 오류'];
        }
    }

    public function getRequestFullDetail(string $requestId): array
    {
        try {
            $request = $this->model->getById($requestId);
            if (!$request) {
                return [
                    'success' => false,
                    'message' => '요청 정보를 찾을 수 없습니다.',
                ];
            }

            $stepService = new RequestStepService($this->pdo);
            $steps = $stepService->getSteps($requestId);

            return [
                'success' => true,
                'request' => $request,
                'steps' => $steps,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('getRequestFullDetail error: ' . $e->getMessage(), [
                'request_id' => $requestId,
            ]);

            return ['success' => false, 'message' => '조회 오류'];
        }
    }

    public function getStatus(string $requestId): array
    {
        try {
            $request = $this->model->getById($requestId);

            if (!$request) {
                return [
                    'success' => false,
                    'message' => '요청 없음',
                ];
            }

            return [
                'success' => true,
                'status' => $request['status'],
                'current_step' => $request['current_step'],
            ];
        } catch (\Throwable $e) {
            $this->logger->error('getStatus error: ' . $e->getMessage(), [
                'request_id' => $requestId,
            ]);

            return ['success' => false];
        }
    }
}
