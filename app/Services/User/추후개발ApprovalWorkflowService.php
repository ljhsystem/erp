<?php
// 경로: PROJECT_ROOT . '/app/Services/User/ApprovalWorkflowService.php'
namespace App\Services\User;

use App\Models\User\ApprovalTemplateModel;
use App\Models\User\ApprovalTemplateStepModel;
use App\Models\User\ApprovalRequestModel;
use App\Models\User\ApprovalRequestStepModel;
use App\Services\User\ProfileService;
use App\Services\Mail\MailService;
use Core\LoggerFactory;

class ApprovalWorkflowService
{
    private $pdo;

    private $templateModel;
    private $templateStepModel;

    private $requestModel;
    private $requestStepModel;

    private $profileService;
    private $mailService;
    private $logger;

    public function __construct($pdo)
    {
        $this->pdo               = $pdo;

        $this->templateModel     = new ApprovalTemplateModel($pdo);
        $this->templateStepModel = new ApprovalTemplateStepModel($pdo);

        $this->requestModel      = new ApprovalRequestModel($pdo);
        $this->requestStepModel  = new ApprovalRequestStepModel($pdo);

        $this->profileService    = new ProfileService($pdo);
        $this->mailService       = new MailService();

        $this->logger            = LoggerFactory::getLogger("service-user.ApprovalWorkflowService");
    }

    /* ===============================================================
     * 1) 결재 요청 생성 (기안)
     * =============================================================== */
    public function startApproval(string $templateId, string $documentId, string $requesterId): ?string
    {
        $this->logger->info("결재 요청 생성", [
            'template_id' => $templateId,
            'document_id' => $documentId,
            'requester'   => $requesterId
        ]);

        // 템플릿 스텝 조회 (JOIN 포함)
        $steps = $this->templateStepModel->getSteps($templateId);
        if (empty($steps)) {
            $this->logger->warning("템플릿 스텝 없음: {$templateId}");
            return null;
        }

        // 요청 생성
        $requestId = $this->requestModel->create([
            'template_id' => $templateId,
            'document_id' => $documentId,
            'requester_id'=> $requesterId,
            'status'      => 'pending',
            'is_active'   => 1,
            'created_by'  => $requesterId
        ]);

        // 각 템플릿 스텝 → 실제 결재 스텝 생성
        foreach ($steps as $step) {

            // 역할 기준 결재자 찾기 또는 step에 지정된 approver_id 사용
            $approverId = $step['approver_id'] ?? $this->findApproverForRole($step['role_id']);

            $this->requestStepModel->create([
                'request_id'  => $requestId,
                'sequence'    => $step['sequence'],
                'step_name'   => $step['step_name'],
                'approver_id' => $approverId,
                'role_id'     => $step['role_id'],
                'status'      => 'pending',
                'created_by'  => $requesterId
            ]);
        }

        // 첫 스텝 결재자에게 알림
        $firstStep = $this->requestStepModel->getSteps($requestId)[0] ?? null;

        if ($firstStep) {
            $profile = $this->profileService->getByUserId($firstStep['approver_id']);
            $this->sendApprovalRequestMail($profile, $firstStep['sequence']);
        }

        return $requestId;
    }

    /* ===============================================================
     * 2) 역할 기반 결재자 찾기
     * =============================================================== */
    private function findApproverForRole(?string $roleId): ?string
    {
        if (!$roleId) return null;

        $stmt = $this->pdo->prepare("
            SELECT user_id
            FROM user_employees
            WHERE role_id = ?
            ORDER BY user_id
            LIMIT 1
        ");
        $stmt->execute([$roleId]);

        return $stmt->fetchColumn() ?: null;
    }


    /* ===============================================================
     * 3) 결재 승인 처리
     * =============================================================== */
    public function approveStep(string $stepId, string $approverId): array
    {
        $step = $this->requestStepModel->getById($stepId);

        if (!$step) {
            return ['success' => false, 'message' => '결재 스텝을 찾을 수 없습니다.'];
        }

        if ($step['approver_id'] !== $approverId) {
            return ['success' => false, 'message' => '결재 권한이 없습니다.'];
        }

        // 승인 처리
        $this->requestStepModel->updateStatus($stepId, 'approved', $approverId);

        // 다음 스텝 찾기
        $steps = $this->requestStepModel->getSteps($step['request_id']);
        $next = null;

        foreach ($steps as $s) {
            if ($s['sequence'] > $step['sequence']) {
                $next = $s;
                break;
            }
        }

        // 마지막 스텝 → 전체 승인
        if (!$next) {
            $this->requestModel->updateStatus($step['request_id'], 'approved', $approverId);
            return ['success' => true, 'is_final' => true];
        }

        // 다음 결재자에게 메일
        $profile = $this->profileService->getByUserId($next['approver_id']);
        $this->sendApprovalRequestMail($profile, $next['sequence']);

        return [
            'success' => true,
            'is_final' => false,
            'next_approver' => $next['approver_id']
        ];
    }


    /* ===============================================================
     * 4) 반려 처리
     * =============================================================== */
    public function rejectStep(string $stepId, string $approverId, string $reason): array
    {
        $step = $this->requestStepModel->getById($stepId);

        if (!$step) {
            return ['success' => false, 'message' => '결재 스텝을 찾을 수 없습니다.'];
        }

        if ($step['approver_id'] !== $approverId) {
            return ['success' => false, 'message' => '결재 권한이 없습니다.'];
        }

        // 스텝 반려
        $this->requestStepModel->updateStatus($stepId, 'rejected', $approverId);

        // 전체 요청 반려
        $this->requestModel->updateStatus($step['request_id'], 'rejected', $approverId);

        // 기안자에게 알림
        $req = $this->requestModel->getById($step['request_id']);
        $profile = $this->profileService->getByUserId($req['requester_id']);

        $this->sendRejectionMail($profile, $reason);

        return [
            'success' => true,
            'is_final' => true,
            'message' => '반려 처리되었습니다.'
        ];
    }

    /* ===============================================================
     * 5) 메일 발송
     * =============================================================== */
    private function sendApprovalRequestMail(array $profile = null, int $stepSeq): void
    {
        if (!$profile || empty($profile['email'])) return;

        $this->mailService->sendContactMail([
            'fromName'  => "ERP 결재 시스템",
            'fromEmail' => $profile['email'],
            'subject'   => "[결재요청] {$stepSeq}차 결재가 도착했습니다",
            'message'   => "ERP에서 결재 요청 문서가 도착했습니다."
        ]);
    }

    private function sendRejectionMail(array $profile = null, string $reason): void
    {
        if (!$profile || empty($profile['email'])) return;

        $this->mailService->sendContactMail([
            'fromName'  => "ERP 결재 시스템",
            'fromEmail' => $profile['email'],
            'subject'   => "[반려알림] 문서가 반려되었습니다",
            'message'   => "반려 사유: {$reason}"
        ]);
    }
}
