<?php

namespace App\Controllers\Dashboard\Settings;

use App\Services\Auth\UserContextService;
use App\Services\System\DatabaseActiveSwitchService;
use App\Services\System\DatabaseReplicationStatusService;
use Core\DbPdo;
use RuntimeException;

class DatabaseActiveController
{
    private DatabaseReplicationStatusService $statusService;
    private DatabaseActiveSwitchService $switchService;
    private UserContextService $userContextService;

    public function __construct()
    {
        $this->statusService = new DatabaseReplicationStatusService(DbPdo::conn());
        $this->switchService = new DatabaseActiveSwitchService();
        $this->userContextService = new UserContextService();
    }

    public function apiStatus(): void
    {
        try {
            $status = $this->statusService->check();
            $guard = $this->switchService->getSwitchGuardStatus();
            $isAdmin = $this->canSwitchActiveDatabase();

            $this->respondJson([
                'success' => true,
                'primary' => $status['primary'] ?? null,
                'secondary' => $status['secondary'] ?? null,
                'active_db' => $status['active_db'] ?? null,
                'checked_at' => $status['checked_at'] ?? null,
                'latest_switch' => $this->switchService->getLatestSwitch(),
                'can_switch_active' => $isAdmin && !$guard['blocked'],
                'switch_blocked' => (bool) ($guard['blocked'] ?? false),
                'switch_block_reason' => $guard['message'] ?? null,
                'switch_block_source' => $guard['source'] ?? null,
                'can_switch_active_by_role' => $isAdmin,
            ]);
        } catch (\Throwable) {
            $this->respondJson([
                'success' => false,
                'message' => '현재 DB 상태 조회 중 오류가 발생했습니다.',
            ], 500);
        }
    }

    public function apiSwitchActive(): void
    {
        try {
            if (!$this->canSwitchActiveDatabase()) {
                $this->respondJson([
                    'success' => false,
                    'message' => '관리자만 Active DB를 전환할 수 있습니다.',
                ], 403);
                return;
            }

            $payload = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                $payload = $_POST;
            }

            $target = (string) ($payload['target'] ?? '');
            if ($target === '') {
                $this->respondJson([
                    'success' => false,
                    'message' => '전환 대상 DB를 선택해 주세요.',
                ], 422);
                return;
            }

            $result = $this->switchService->switchActiveDatabase($target, [
                'user_id' => (string) ($this->userContextService->currentUserId() ?? ''),
                'display_name' => $this->userContextService->currentDisplayName(),
            ]);

            $this->respondJson([
                'success' => true,
                'data' => $result,
                'message' => 'Active DB 전환이 완료되었습니다.',
            ]);
        } catch (RuntimeException $e) {
            $this->respondJson([
                'success' => false,
                'message' => $e->getMessage() ?: 'Active DB 전환 중 오류가 발생했습니다.',
            ], 409);
        } catch (\Throwable $e) {
            $this->respondJson([
                'success' => false,
                'message' => $e->getMessage() ?: 'Active DB 전환 중 오류가 발생했습니다.',
            ], 500);
        }
    }

    private function canSwitchActiveDatabase(): bool
    {
        $roleKey = strtolower((string) ($this->userContextService->currentRoleKey() ?? ''));
        return in_array($roleKey, ['admin', 'super_admin'], true);
    }

    private function respondJson(array $payload, int $status = 200): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            http_response_code(500);
            $json = json_encode([
                'success' => false,
                'message' => 'JSON 응답 생성 중 오류가 발생했습니다.',
            ], JSON_UNESCAPED_UNICODE);
        }

        echo $json;
    }
}
