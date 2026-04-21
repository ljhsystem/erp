<?php

namespace App\Controllers\Approval;

use App\Services\Approval\RequestService;
use App\Services\Approval\RequestStepService;
use Core\DbPdo;

class ApprovalRequestController
{
    private RequestService $requestService;
    private RequestStepService $stepService;

    public function __construct()
    {
        $this->requestService = new RequestService(DbPdo::conn());
        $this->stepService = new RequestStepService(DbPdo::conn());
    }

    public function apiCreate()
    {
        $data = [
            'template_id' => $_POST['template_id'] ?? null,
            'document_id' => $_POST['document_id'] ?? null,
        ];

        echo json_encode($this->requestService->createRequestWithSteps($data));
    }

    public function apiDetail()
    {
        $id = $_GET['id'] ?? '';
        echo json_encode($this->requestService->getRequestFullDetail($id));
    }

    public function apiApproveStep()
    {
        $stepId = $_POST['step_id'] ?? null;
        $comment = $_POST['comment'] ?? null;

        $res = $this->stepService->updateStepStatus($stepId, 'approved', $comment);
        echo json_encode(['success' => $res]);
    }

    public function apiRejectStep()
    {
        $stepId = $_POST['step_id'] ?? null;
        $comment = $_POST['comment'] ?? null;

        $res = $this->stepService->updateStepStatus($stepId, 'rejected', $comment);
        echo json_encode(['success' => $res]);
    }

    public function apiStatus()
    {
        $id = $_GET['id'] ?? '';
        echo json_encode($this->requestService->getStatus($id));
    }

    public function apiDeleteStep()
    {
        $stepId = $_POST['step_id'] ?? null;

        if (!$stepId) {
            echo json_encode([
                'success' => false,
                'message' => 'step_id가 필요합니다.',
            ]);
            return;
        }

        echo json_encode([
            'success' => $this->stepService->deleteStep($stepId),
            'step_id' => $stepId,
        ]);
    }
}
