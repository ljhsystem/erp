<?php

namespace App\Controllers\System;

use App\Services\Auth\AuthSessionService;
use App\Services\System\SessionConfigService;
use Core\Session;
use PDO;

class SessionController
{
    private AuthSessionService $authSession;
    private SessionConfigService $configService;

    public function __construct(PDO $pdo)
    {
        $this->authSession = new AuthSessionService();
        $this->configService = new SessionConfigService($pdo);
    }

    public function apiKeepalive()
    {
        header('Content-Type: application/json; charset=UTF-8');

        if (!$this->authSession->isAuthenticated()) {
            echo json_encode([
                'success' => false,
                'message' => 'Session expired'
            ]);
            exit;
        }

        $expireTime = Session::extend($this->configService->getTimeoutMinutes());
        $username = (string)($this->authSession->getCurrentUser()['username'] ?? '');

        echo json_encode([
            'success' => true,
            'expire_time' => $expireTime,
            'username' => $username
        ]);
        exit;
    }

    public function webExtendView()
    {
        $alertSound = $this->configService->getAlertSound();
        $alertTime = $this->configService->getAlertTimeMinutes();

        require PROJECT_ROOT . '/app/views/auth/session_extend.php';
    }

    public function webExpired()
    {
        require PROJECT_ROOT . '/app/views/auth/session_expired.php';
    }
}
