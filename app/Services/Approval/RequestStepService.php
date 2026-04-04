<?php
// 경로: PROJECT_ROOT . '/app/Services/Approval/RequestStepService.php'
namespace App\Services\Approval;

use PDO;
use App\Models\User\ApprovalRequestModel;
use App\Models\User\ApprovalRequestStepModel;
use App\Models\User\ApprovalTemplateStepModel;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;


class RequestStepService
{
    private readonly PDO $pdo;
    private  $requestModel;
    private  $stepModel;
    private  $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->requestModel = new ApprovalRequestModel($pdo);
        $this->stepModel    = new ApprovalRequestStepModel($pdo);
        $this->logger          = LoggerFactory::getLogger('service-approval.step');
    }

    /* ============================================================
     * 1) 요청에 모든 스텝 생성 (템플릿 기반 생성)
     * ============================================================ */
    public function createStep(array $data): bool
    {
        $data['id'] = UuidHelper::generate();

        $this->logger->info("스텝 생성", ['data' => $data]);

        return $this->stepModel->create($data);
    }

    /* ============================================================
     * 2) 특정 요청의 스텝 전체 조회
     * ============================================================ */
    public function getSteps(string $requestId): array
    {
        return $this->stepModel->getSteps($requestId);
    }

    /* ============================================================
     * 3) 특정 스텝 단일 조회
     * ============================================================ */
    public function getStepById(string $stepId): ?array
    {
        return $this->stepModel->getById($stepId);
    }

    /* ============================================================
     * 4) 스텝 승인 / 반려 적용
     * ============================================================ */
    public function updateStepStatus(string $stepId, string $status, ?string $comment, ?string $userId): bool
    {
        $step = $this->stepModel->getById($stepId);
        if (!$step) {
            $this->logger->warning("스텝 없음", ['step_id' => $stepId]);
            return false;
        }

        $this->logger->info("스텝 상태 변경", [
            'step_id' => $stepId,
            'status'  => $status
        ]);

        $ok = $this->stepModel->updateStatus($stepId, $status, $comment, $userId);
        if (!$ok) {
            return false;
        }

        /* --------------------------
         * 승인일 경우 → 다음 스텝으로 이동
         * 반려일 경우 → 요청 자체 반려 처리
         * -------------------------- */
        if ($status === 'approved') {
            return $this->moveToNextStep($step);
        }

        if ($status === 'rejected') {
            return $this->rejectRequest($step['request_id'], $userId);
        }

        return true;
    }

    /* ============================================================
     * 5) 다음 스텝으로 이동
     * ============================================================ */
    private function moveToNextStep(array $currentStep): bool
    {
        $requestId = $currentStep['request_id'];
        $sequence  = (int)$currentStep['sequence'];

        $steps = $this->stepModel->getSteps($requestId);

        // 총 스텝 개수
        $total = count($steps);

        // 마지막 스텝 검사
        if ($sequence >= $total) {
            return $this->approveRequest($requestId);
        }

        // 다음 스텝 번호
        $next = $sequence + 1;

        $this->logger->info("다음 스텝 이동", [
            'request_id' => $requestId,
            'from'       => $sequence,
            'to'         => $next,
        ]);

        return $this->requestModel->updateCurrentStep($requestId, $next);
    }

    /* ============================================================
     * 6) 요청 전체 승인
     * ============================================================ */
    private function approveRequest(string $requestId): bool
    {
        $this->logger->info("요청 전체 승인", ['request_id' => $requestId]);
        return $this->requestModel->updateStatus($requestId, 'approved');
    }

    /* ============================================================
     * 7) 요청 전체 반려
     * ============================================================ */
    private function rejectRequest(string $requestId, ?string $userId): bool
    {
        $this->logger->info("요청 전체 반려", [
            'request_id'  => $requestId,
            'rejected_by' => $userId
        ]);

        return $this->requestModel->updateStatus($requestId, 'rejected', $userId);
    }

    /* ============================================================
     * 8) 템플릿 스텝 조회
     * ============================================================ */
    public function getTemplateSteps(string $templateId): array
    {
        $tplModel = new ApprovalTemplateStepModel($this->pdo);

        return $tplModel->getSteps($templateId);
    }

    /* ============================================================
     * 9) 스텝 삭제 (단일 스텝 삭제)
     * ============================================================ */
    public function deleteStep(string $stepId): bool
    {
        try {
            $this->logger->info("스텝 삭제 시도", ['step_id' => $stepId]);

            $step = $this->stepModel->getById($stepId);
            if (!$step) {
                $this->logger->warning("삭제 대상 스텝 없음", ['step_id' => $stepId]);
                return false;
            }

            $ok = $this->stepModel->delete($stepId);

            $this->logger->info("스텝 삭제 결과", [
                'step_id' => $stepId,
                'success' => $ok
            ]);

            return $ok;

        } catch (\Throwable $e) {
            $this->logger->error("deleteStep error: " . $e->getMessage(), [
                'step_id'   => $stepId,
                'exception' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}
