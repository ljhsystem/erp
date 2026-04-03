<?php
// /core/Helpers/AuthHelper.php

namespace Core\Helpers;

use Core\Database;
use App\Services\Auth\PermissionService;

class AuthHelper
{
    /**
     * 현재 로그인 사용자 ID 반환
     */
    public static function userId(): ?string
    {
        return $_SESSION['user']['id'] ?? null;
    }

    /**
     * 로그인 여부 체크
     */
    public static function check(): bool
    {
        return isset($_SESSION['user']['id']);
    }

    /**
     * 권한 체크
     */
    public static function hasPermission(string $key): bool
    {
        $userId = self::userId();

        if (!$userId) {
            return false;
        }

        $pdo = Database::getInstance()->getConnection();
        $service = new PermissionService($pdo);

        return $service->hasPermission($userId, $key);
    }
}