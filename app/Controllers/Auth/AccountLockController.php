<?php

namespace App\Controllers\Auth;

use App\Services\Auth\AccountLockService;
use Core\DbPdo;
use PDO;

class AccountLockController
{
    private AccountLockService $lockService;

    public function __construct(?PDO $pdo = null)
    {
        $this->lockService = new AccountLockService($pdo ?? DbPdo::conn());
    }

    public function apiStatus()
    {
        $userId = trim((string)($_GET['user_id'] ?? ''));

        if ($userId === '') {
            $this->json(['success' => false, 'message' => 'user_id가 필요합니다.'], 400);
        }

        try {
            $this->json([
                'success' => true,
                'user_id' => $userId,
                'fail_count' => $this->lockService->getFailCount($userId),
            ]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => '상태 조회에 실패했습니다.'], 500);
        }
    }

    public function apiLock()
    {
        $data = $this->getRequestData();
        $userId = trim((string)($data['user_id'] ?? ''));
        $minutes = (int)($data['minutes'] ?? 30);

        if ($userId === '') {
            $this->json(['success' => false, 'message' => 'user_id가 필요합니다.'], 400);
        }

        try {
            $ok = $this->lockService->lockAccount($userId, $minutes);
            $this->json([
                'success' => $ok,
                'message' => $ok ? '계정을 잠갔습니다.' : '계정 잠금에 실패했습니다.',
            ], $ok ? 200 : 500);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => '처리 중 오류가 발생했습니다.'], 500);
        }
    }

    public function apiUnlock()
    {
        $data = $this->getRequestData();
        $userId = trim((string)($data['user_id'] ?? ''));

        if ($userId === '') {
            $this->json(['success' => false, 'message' => 'user_id가 필요합니다.'], 400);
        }

        try {
            $ok = $this->lockService->unlockAccount($userId);
            $this->json([
                'success' => $ok,
                'message' => $ok ? '계정 잠금을 해제했습니다.' : '계정 잠금 해제에 실패했습니다.',
            ], $ok ? 200 : 500);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => '처리 중 오류가 발생했습니다.'], 500);
        }
    }

    private function getRequestData(): array
    {
        $data = json_decode(file_get_contents('php://input'), true);
        return is_array($data) ? $data : $_POST;
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
