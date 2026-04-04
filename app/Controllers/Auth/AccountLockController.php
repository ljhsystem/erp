<?php
// 경로: PROJECT_ROOT/app/Controllers/Auth/AccountLockController.php
namespace App\Controllers\Auth;

use Core\DbPdo;
use App\Services\Auth\AccountLockService;

class AccountLockController
{
    private AccountLockService $lockService;

    public function __construct()
    {
        $this->lockService = new AccountLockService(DbPdo::conn());
    }

    // ============================================================
    // API: 계정 잠금 상태 조회
    // URL: GET /auth/account/lock/status
    // permission: api.auth.lock.status
    // controller: AccountLockController@apiStatus
    // ============================================================
    public function apiStatus()
    {     
        header('Content-Type: application/json; charset=utf-8');

        $userId = $_GET['user_id'] ?? '';

        if ($userId === '') {
            echo json_encode([
                'success' => false,
                'message' => 'user_id가 필요합니다.'
            ]);
            exit;
        }

        try {
            $failCount = $this->lockService->getFailCount($userId);

            echo json_encode([
                'success'    => true,
                'user_id'    => $userId,
                'fail_count' => $failCount
            ]);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '상태 조회 실패'
            ]);
        }
    }

    // ============================================================
    // API: 계정 잠금 설정
    // URL: POST /auth/account/lock/set
    // permission: auth.lock.set
    // controller: AccountLockController@apiLock
    // ============================================================
    public function apiLock()
    {
        header('Content-Type: application/json; charset=utf-8');

        $userId  = $_POST['user_id'] ?? '';
        $minutes = intval($_POST['minutes'] ?? 30); // 기본 30분

        if ($userId === '') {
            echo json_encode([
                'success' => false,
                'message' => 'user_id가 필요합니다.'
            ]);
            exit;
        }

        try {
            $ok = $this->lockService->lockAccount($userId, $minutes);

            echo json_encode([
                'success' => $ok,
                'message' => $ok ? '계정이 잠겼습니다.' : '계정 잠금 실패'
            ]);

        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '처리 중 오류'
            ]);
        }
    }

    // ============================================================
    // API: 계정 잠금 해제
    // URL: POST /auth/account/lock/unlock
    // permission: auth.lock.unlock
    // controller: AccountLockController@apiUnlock
    // ============================================================
    public function apiUnlock()
    {
        header('Content-Type: application/json; charset=utf-8');

        $userId = $_POST['user_id'] ?? '';

        if ($userId === '') {
            echo json_encode([
                'success' => false,
                'message' => 'user_id가 필요합니다.'
            ]);
            exit;
        }

        try {
            $ok = $this->lockService->unlockAccount($userId);

            echo json_encode([
                'success' => $ok,
                'message' => $ok ? '계정이 정상적으로 해제되었습니다.' : '계정 해제 실패'
            ]);

        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '처리 중 오류'
            ]);
        }
    }
}
