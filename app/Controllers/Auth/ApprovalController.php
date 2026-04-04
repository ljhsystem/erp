<?php
// 경로: PROJECT_ROOT/app/Controllers/Auth/ApprovalController.php
namespace App\Controllers\Auth;

use Core\DbPdo;
use App\Services\Auth\ApprovalService;

class ApprovalController
{
    private ApprovalService $approvalService;

    public function __construct()
    {
        $this->approvalService = new ApprovalService(DbPdo::conn());
    }

    // ============================================================
    // WEB: 승인 요청 화면 표시
    // URL: GET /approve_request
    // permission: 없음 (메일을 통해 접근 가능)
    // controller: ApprovalController@webApproveRequest
    // ============================================================
    public function webApproveRequest()
    {
        include PROJECT_ROOT . '/app/views/auth/approve_request.php';
        exit;
    }

    // ============================================================
    // WEB: 승인 결과 화면 표시
    // URL: GET /approve_result
    // permission: 없음 (메일을 통해 접근 가능)
    // controller: ApprovalController@webApproveResult
    // ============================================================
    public function webApproveResult()
    {
        include PROJECT_ROOT . '/app/views/auth/approve_result.php';
        exit;
    }

    // ============================================================
    // API: 승인 처리
    // URL: POST /approve_user
    // permission: api.auth.approval.submit
    // controller: ApprovalController@apiApproveUser
    // ============================================================
    public function apiApproveUser()
    {
        $code         = $_REQUEST['code'] ?? '';
        $approveToken = $_REQUEST['approve_token'] ?? '';

        // TODO: 승인 검증 로직 작성
        // ex) 토큰 검증 → 유저 승인 update → 결과 페이지 렌더링

        // 🔐 토큰 검증
        $data = $this->approvalService->verifyApprovalToken($approveToken);

        if (!$data || empty($data['user_id'])) {
            include PROJECT_ROOT . '/app/views/auth/approve_result.php';
            exit;
        }

        // ✅ 승인 처리
        $this->approvalService->approveUser(
            $data['user_id'],
            $data['admin'] ?? null
        );

        include PROJECT_ROOT . '/app/views/auth/approve_result.php';
        exit;
    }
}
