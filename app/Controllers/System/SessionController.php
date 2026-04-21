<?php

namespace App\Controllers\System;

use App\Services\Auth\AuthSessionService;
use App\Services\System\SessionConfigService;
use Core\Database;
use Core\Session;

class SessionController
{
    public function apiKeepalive()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $authSession = new AuthSessionService();
        if (!$authSession->isAuthenticated()) {
            echo json_encode([
                'success' => false,
                'message' => 'Session expired'
            ]);
            exit;
        }

        $configService = new SessionConfigService(Database::getInstance()->getConnection());
        $expireTime = Session::extend($configService->getTimeoutMinutes());
        $username = (string)($authSession->getCurrentUser()['username'] ?? '');

        echo json_encode([
            'success' => true,
            'expire_time' => $expireTime,
            'username' => $username
        ]);
        exit;
    }

    public function webExtendView()
    {
        $configService = new SessionConfigService(Database::getInstance()->getConnection());
        $alertSound = $configService->getAlertSound();
        $alertTime = $configService->getAlertTimeMinutes();

        require PROJECT_ROOT . '/app/views/auth/session_extend.php';
    }

    public function webExpired()
    {
        require PROJECT_ROOT . '/app/views/auth/session_expired.php';
    }
}
