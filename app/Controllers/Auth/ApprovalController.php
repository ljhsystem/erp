<?php

namespace App\Controllers\Auth;

use App\Services\Auth\ApprovalService;
use Core\DbPdo;

class ApprovalController
{
    private ApprovalService $approvalService;

    public function __construct()
    {
        $this->approvalService = new ApprovalService(DbPdo::conn());
    }

    public function webApproveRequest()
    {
        include PROJECT_ROOT . '/app/views/auth/approve_request.php';
        exit;
    }

    public function webApproveResult()
    {
        [$message, $isSuccess, $approvedBy] = $this->resolveApprovalResult();

        include PROJECT_ROOT . '/app/views/auth/approve_result.php';
        exit;
    }

    public function apiApproveUser()
    {
        [$message, $isSuccess, $approvedBy] = $this->resolveApprovalResult();

        include PROJECT_ROOT . '/app/views/auth/approve_result.php';
        exit;
    }

    private function resolveApprovalResult(): array
    {
        $approveToken = trim((string)($_REQUEST['approve_token'] ?? ''));
        $tokenData = $approveToken !== '' ? $this->approvalService->verifyApprovalToken($approveToken) : null;

        if (!$tokenData || empty($tokenData['user_id'])) {
            return ['유효한 승인 요청이 아닙니다.', false, null];
        }

        $approvedBy = $tokenData['admin'] ?? null;
        $isSuccess = $this->approvalService->approveUser($tokenData['user_id'], $approvedBy);
        $message = $isSuccess
            ? '회원 승인 처리가 완료되었습니다.'
            : '이미 승인되었거나 존재하지 않는 사용자입니다.';

        return [$message, $isSuccess, $approvedBy];
    }
}
