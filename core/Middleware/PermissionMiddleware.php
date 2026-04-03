<?php
// 경로: PROJECT_ROOT . '/core/Middleware/PermissionMiddleware.php';

namespace Core\Middleware;

//require_once PROJECT_ROOT . '/app/services/auth/PermissionService.php';

use App\Services\Auth\PermissionService;
use Core\Database;
use Core\LoggerFactory;
use Core\Helpers\ConfigHelper;

class PermissionMiddleware
{
    /** -------------------------------------------------
     *  Autologout 경로는 권한 검사 제외
     * ------------------------------------------------- */
    private static array $autoAllowed = [
        'autologout/keepalive',
        'autologout/extend',
        'autologout/expired'
    ];

    public static function check($required = null)
    {
        $logger = LoggerFactory::getLogger('core.middleware-PermissionMiddleware');

        $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

        /* ============================================================
         * 0) 개발모드 → PASS
         * ============================================================ */
        if (ConfigHelper::get('IsDevelopment') === true) {
            $logger->info("DEV MODE → Permission bypass");
            return;
        }

        /* ============================================================
         * 1) autologout 경로는 권한 검사 제외
         * ============================================================ */
        if (in_array($path, self::$autoAllowed, true)) {
            $logger->info("PASS → Autologout 경로 예외 처리", ['path' => $path]);
            return;
        }

        /* ============================================================
         * 2) 권한 요구 없는 라우트 → PASS
         * ============================================================ */
        if (!$required) {
            $logger->info("PASS → 이 라우트는 권한 없음");
            return;
        }

        /* ============================================================
         * 3) permission 배열이면 key 추출
         * ============================================================ */
        if (is_array($required)) {
            if (!isset($required['key'])) {
                $logger->error("Permission 배열 오류: key 없음", [
                    'permission' => $required
                ]);
                return self::respondError(500, "권한 설정 오류(permission key 누락)");
            }
            $required = $required['key'];
        }

        /* ============================================================
         * 4) 로그인 여부 확인
         * ============================================================ */
        $user = $_SESSION['user'] ?? null;

        if (!$user || empty($user['id'])) {
            $logger->warning("로그인되지 않은 사용자");
            return self::respondError(401, "로그인이 필요합니다.");
        }

        $userId = $user['id'];

        $logger->info("권한 검사 시작", [
            'user_id'    => $userId,
            'permission' => $required
        ]);

        /* ============================================================
         * 5) SUPER ADMIN 체크
         *    NOTE: user['role'] 로 확인하면 안 됨!!
         *    로그인 시 user_roles JOIN 해서 배열로 저장하는 것이 정석
         * ============================================================ */
        if (!empty($user['roles']) && is_array($user['roles'])) {
            if (in_array('super_admin', $user['roles'], true)) {
                $logger->info("PASS → super_admin 권한 무제한 허용");
                return;
            }
        }

        /* ============================================================
         * 6) PermissionService 로 실제 권한 체크
         * ============================================================ */
        $pdo = Database::getInstance()->getConnection();
        $service = new PermissionService($pdo);

        try {
            $hasPermission = $service->hasPermission($userId, $required);
        } catch (\Throwable $e) {
            $logger->error("PermissionService 오류", [
                'exception' => $e->getMessage()
            ]);
            return self::respondError(500, "권한 시스템 오류(PermissionService)");
        }

        /* ============================================================
         * 7) 권한 없음 → 차단
         * ============================================================ */
        if (!$hasPermission) {
            $logger->warning("권한 부족", [
                'user_id'    => $userId,
                'permission' => $required
            ]);
            return self::respondError(403, "접근 권한이 없습니다.");
        }

        /* ============================================================
         * 8) 권한 있음 → PASS
         * ============================================================ */
        $logger->info("PASS → 권한 승인됨");
    }

    /* ============================================================
     * 공통 오류 처리 (API / WEB 구분)
     * ============================================================ */
    private static function respondError(int $status, string $message)
    {
        http_response_code($status);

        $path = $_SERVER['REQUEST_URI'] ?? '';
        $isApi = (strpos($path, '/api/') === 0);

        /* -----------------------------------------------------------
         * 1) API 요청 → JSON 반환
         * ----------------------------------------------------------- */
        if ($isApi) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error'   => true,
                'status'  => $status,
                'message' => $message
            ]);
            exit;
        }

        /* -----------------------------------------------------------
         * 2) 로그인 안 된 경우 → login으로 이동
         * ----------------------------------------------------------- */
        if ($status === 401) {
            header("Location: /login");
            exit;
        }

        /* -----------------------------------------------------------
         * 3) 권한 없음 → /403 이동 (단일 에러 페이지)
         * ----------------------------------------------------------- */
        header("Location: /403");
        exit;
    }
}
