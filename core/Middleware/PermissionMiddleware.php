<?php

namespace Core\Middleware;

use App\Services\Auth\AuthSessionService;
use App\Services\Auth\PermissionService;
use Core\Database;
use Core\Helpers\ConfigHelper;
use Core\LoggerFactory;

class PermissionMiddleware
{
    private static array $autoAllowed = [
        'autologout/keepalive',
        'autologout/extend',
        'autologout/expired',
    ];

    public static function check($required = null): void
    {
        $logger = LoggerFactory::getLogger('core.middleware-PermissionMiddleware');
        $path = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

        if (ConfigHelper::get('IsDevelopment') === true) {
            $logger->info('DEV MODE permission bypass');
            return;
        }

        if (in_array($path, self::$autoAllowed, true)) {
            $logger->info('Autologout route permission bypass', ['path' => $path]);
            return;
        }

        if (!$required) {
            return;
        }

        if (is_array($required)) {
            if (empty($required['key'])) {
                return;
            }

            $required = $required['key'];
        }

        $authSession = new AuthSessionService();
        $user = $authSession->getCurrentUser();

        if (!$user || empty($user['id'])) {
            self::respondError(401, '로그인이 필요합니다.');
        }

        $userId = (string)$user['id'];

        if (!empty($user['roles']) && is_array($user['roles']) && in_array('super_admin', $user['roles'], true)) {
            return;
        }

        $service = new PermissionService(Database::getInstance()->getConnection());

        try {
            $hasPermission = $service->hasPermission($userId, (string)$required);
        } catch (\Throwable $e) {
            $logger->error('PermissionService failure', [
                'user_id' => $userId,
                'permission' => $required,
                'error' => $e->getMessage(),
            ]);
            self::respondError(500, '권한 확인 중 오류가 발생했습니다.');
        }

        if (!$hasPermission) {
            self::respondError(403, '접근 권한이 없습니다.');
        }
    }

    private static function respondError(int $status, string $message): void
    {
        http_response_code($status);

        $path = (string)($_SERVER['REQUEST_URI'] ?? '');
        $isApi = str_starts_with($path, '/api/');

        if ($isApi) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => true,
                'status' => $status,
                'message' => $message,
                'redirect' => $status === 401 ? '/login' : null,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($status === 401) {
            header('Location: /login');
            exit;
        }

        header('Location: /403');
        exit;
    }
}
