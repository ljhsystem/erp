<?php

namespace App\Controllers\Auth;

use App\Services\Auth\ApprovalService;
use App\Services\Auth\AuthSessionService;
use Core\DbPdo;
use PDO;

class UserApprovalController
{
    private const FLASH_MESSAGE = 'user_approval.message';
    private const FLASH_SUCCESS = 'user_approval.success';
    private const FLASH_APPROVED_BY = 'user_approval.approved_by';

    private ApprovalService $approvalService;
    private AuthSessionService $authSessionService;

    public function __construct(?PDO $pdo = null)
    {
        $connection = $pdo ?? DbPdo::conn();
        $this->approvalService = new ApprovalService($connection);
        $this->authSessionService = new AuthSessionService();
    }

    public function webApproveRequest(): void
    {
        include PROJECT_ROOT . '/app/views/auth/approve_request.php';
        exit;
    }

    public function webApproveResult(): void
    {
        $message = $this->authSessionService->pullFlash(
            self::FLASH_MESSAGE,
            '승인 요청 처리 결과를 찾을 수 없습니다.'
        );
        $isSuccess = $this->authSessionService->pullFlash(self::FLASH_SUCCESS, '0') === '1';
        $approvedBy = $this->authSessionService->pullFlash(self::FLASH_APPROVED_BY, '');
        $approvedBy = $approvedBy !== '' ? $approvedBy : null;

        include PROJECT_ROOT . '/app/views/auth/approve_result.php';
        exit;
    }

    public function apiApprove(): void
    {
        [$message, $isSuccess, $approvedBy] = $this->resolveApprovalExecution();

        $this->authSessionService->setFlash(self::FLASH_MESSAGE, $message);
        $this->authSessionService->setFlash(self::FLASH_SUCCESS, $isSuccess ? '1' : '0');
        $this->authSessionService->setFlash(self::FLASH_APPROVED_BY, (string) ($approvedBy ?? ''));

        header('Location: /auth/approval/result', true, 303);
        exit;
    }

    private function resolveApprovalExecution(): array
    {
        $approveToken = trim((string) ($_POST['approve_token'] ?? ''));
        $tokenData = $approveToken !== '' ? $this->approvalService->verifyApprovalToken($approveToken) : null;

        if (!$tokenData || empty($tokenData['user_id'])) {
            return ['유효한 승인 요청이 아닙니다.', false, null];
        }

        $approvedBy = $tokenData['admin'] ?? null;
        $isSuccess = $this->approvalService->approveUser((string) $tokenData['user_id'], $approvedBy);
        $message = $isSuccess
            ? '회원 승인 처리가 완료되었습니다.'
            : '이미 승인되었거나 존재하지 않는 사용자입니다.';

        return [$message, $isSuccess, $approvedBy];
    }
}
