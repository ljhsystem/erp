<?php
// /core/Helpers/AuthHelper.php

namespace Core\Helpers;

use App\Services\Auth\AuthSessionService;

class AuthHelper
{
    /**
     * 현재 로그인 사용자 ID 반환
     */
    public static function userId(): ?string
    {
        return (new AuthSessionService())->getCurrentUserId();
    }

    /**
     * 로그인 여부 체크
     */
    public static function check(): bool
    {
        return (new AuthSessionService())->isAuthenticated();
    }
}
