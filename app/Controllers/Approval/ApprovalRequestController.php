<?php
// 경로: PROJECT_ROOT . '/app/Controllers/Approval/ApprovalRequestController.php'
namespace App\Controllers\Approval;

use Core\Session;
use Core\DbPdo;
use App\Services\Approval\RequestService;
use App\Services\Approval\RequestStepService;

class ApprovalRequestController
{
    private RequestService $requestService;
    private RequestStepService $stepService;

    public function __construct()
    {
        Session::requireAuth();
        $this->requestService = new RequestService(DbPdo::conn());
        $this->stepService    = new RequestStepService(DbPdo::conn());
    }

    // ============================================================
    // API: 결재 요청 생성
    // URL: POST /approval/request/create
    // permission: api.approval.request.create
    // controller: ApprovalRequestController@apiCreate
    // ============================================================
    public function apiCreate()
    {
        $data = [
            'template_id'  => $_POST['template_id'] ?? null,
            'document_id'  => $_POST['document_id'] ?? null,
            'requester_id' => $_SESSION['user']['id'],
        ];

        $res = $this->requestService->createRequestWithSteps($data);
        echo json_encode($res);
    }

    // ============================================================
    // API: 결재 요청 상세 조회 (요청 + 스텝 전체)
    // URL: GET /approval/request/detail?id={requestId}
    // permission: api.approval.request.detail
    // controller: ApprovalRequestController@apiDetail
    // ============================================================
    public function apiDetail()
    {
        $id = $_GET['id'] ?? '';

        $res = $this->requestService->getRequestFullDetail($id);
        echo json_encode($res);
    }

    // ============================================================
    // API: 결재 스텝 승인
    // URL: POST /approval/request/approve
    // permission: api.approval.step.approve
    // controller: ApprovalRequestController@apiApproveStep
    // ============================================================
    public function apiApproveStep()
    {
        $stepId  = $_POST['step_id'] ?? null;
        $comment = $_POST['comment'] ?? null;
        $userId  = $_SESSION['user']['id'];

        $res = $this->stepService->updateStepStatus($stepId, 'approved', $comment, $userId);
        echo json_encode(['success' => $res]);
    }

    // ============================================================
    // API: 결재 스텝 반려
    // URL: POST /approval/request/reject
    // permission: api.approval.step.reject
    // controller: ApprovalRequestController@apiRejectStep
    // ============================================================
    public function apiRejectStep()
    {
        $stepId  = $_POST['step_id'] ?? null;
        $comment = $_POST['comment'] ?? null;
        $userId  = $_SESSION['user']['id'];

        $res = $this->stepService->updateStepStatus($stepId, 'rejected', $comment, $userId);
        echo json_encode(['success' => $res]);
    }

    // ============================================================
    // API: 결재 요청 상태 조회
    // URL: GET /approval/request/status?id={requestId}
    // permission: api.approval.request.status
    // controller: ApprovalRequestController@apiStatus
    // ============================================================
    public function apiStatus()
    {
        $id  = $_GET['id'] ?? '';
        $res = $this->requestService->getStatus($id);

        echo json_encode($res);
    }

    // ============================================================
    // API: 결재 스텝 삭제
    // URL: POST /approval/request/step/delete
    // permission: api.approval.step.delete
    // controller: ApprovalRequestController@apiDeleteStep
    // ============================================================
    public function apiDeleteStep()
    {
        $stepId = $_POST['step_id'] ?? null;

        if (!$stepId) {
            echo json_encode([
                'success' => false,
                'message' => 'step_id 누락됨'
            ]);
            return;
        }

        $ok = $this->stepService->deleteStep($stepId);

        echo json_encode([
            'success' => $ok,
            'step_id' => $stepId
        ]);
    }
}
