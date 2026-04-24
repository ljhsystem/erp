<?php

namespace Core\Middleware;

use App\Services\Auth\AuthSessionService;
use App\Services\Auth\PermissionService;
use Core\Database;

class AuthMiddleware
{
    public static function handle(string $path, array $route = []): void
    {
        $authSession = new AuthSessionService();
        $meta = $route['permission'] ?? [];
        $status = $authSession->getStatus();
        $loggedIn = $authSession->isAuthenticated();
        $isApi = self::isApiRequest($path);

        if (!empty($meta['guest_only'])) {
            if ($status === AuthSessionService::STATUS_PASSWORD_EXPIRED) {
                self::redirect('/password/change', $isApi);
            }

            if ($loggedIn) {
                self::redirect(self::authenticatedLanding($authSession), $isApi);
            }

            return;
        }

        $allowStatuses = $meta['allow_statuses'] ?? [];
        if ($allowStatuses !== []) {
            if (in_array($status, $allowStatuses, true)) {
                return;
            }

            if ($status === AuthSessionService::STATUS_PASSWORD_EXPIRED) {
                self::redirect('/password/change', $isApi);
            }

            if ($status === AuthSessionService::STATUS_TWO_FACTOR_PENDING) {
                self::redirect('/2fa', $isApi);
            }

            if ($loggedIn) {
                self::redirect(self::authenticatedLanding($authSession), $isApi);
            }

            if ($status === null) {
                self::rejectUnauthorized($isApi);
            }

            self::rejectUnauthorized($isApi);
        }

        if (($meta['auth'] ?? false) === true && !$loggedIn) {
            self::rejectUnauthorized($isApi);
        }
    }

    private static function isApiRequest(string $path): bool
    {
        if (str_starts_with($path, '/api/')) {
            return true;
        }

        if (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) {
            return true;
        }

        return !empty($_SERVER['HTTP_ACCEPT']) &&
            str_contains((string)$_SERVER['HTTP_ACCEPT'], 'application/json');
    }

    private static function rejectUnauthorized(bool $isApi): void
    {
        if ($isApi) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => '로그인이 필요합니다.',
                'redirect' => '/login',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Location: /login');
        exit;
    }

    private static function authenticatedLanding(AuthSessionService $authSession): string
    {
        $userId = $authSession->getCurrentUserId();

        if (!$userId) {
            return '/home';
        }

        try {
            $permissionService = new PermissionService(Database::getInstance()->getConnection());

            if ($permissionService->hasPermission((string) $userId, 'web.dashboard.main')) {
                return '/dashboard';
            }
        } catch (\Throwable) {
            // Fall back to the public home page if permission resolution fails.
        }

        return '/home';
    }

    private static function redirect(string $location, bool $isApi): void
    {
        if ($isApi) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'redirect' => $location,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Location: ' . $location);
        exit;
    }
}
