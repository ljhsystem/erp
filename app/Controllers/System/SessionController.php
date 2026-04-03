<?php
// 경로: PROJECT_ROOT . '/app/controllers/system/SessionController.php'
namespace App\Controllers\System;

use Core\Session;

class SessionController
{
    // ============================================================
    // API: 세션 유지(apiKeepalive) (permission: session.keepalive)
    // URL: GET /autologout/keepalive
    // ============================================================
    public function apiKeepalive()
    {
        header('Content-Type: application/json; charset=UTF-8');
        if (!Session::isAuthenticated()) {
            echo json_encode([
                'success' => false,
                'message' => 'Session expired'
            ]);
            exit;
        }
        $expireTime = Session::extend();

        // 사용자명 가져오기
        $username = '';
        if (!empty($_SESSION['user'])) {
            if (is_array($_SESSION['user']) && !empty($_SESSION['user']['username'])) {
                $username = (string) $_SESSION['user']['username'];
            } elseif (is_string($_SESSION['user'])) {
                $username = (string) $_SESSION['user'];
            }
        }
        if ($username === '' && !empty($_SESSION['username'])) {
            $username = (string) $_SESSION['username'];
        }

        echo json_encode([
            'success'      => true,
            'expire_time'  => $expireTime,
            'username'     => $username
        ]);
    }

    // ============================================================
    // WEB: 세션 연장 팝업(webExtendView) (permission: public)
    // URL: GET /autologout/extend
    // ============================================================
    public function webExtendView()
    {
        $alertSound = Session::getAlertSound();
        $alertTime  = Session::getAlertTime();

        require PROJECT_ROOT . '/app/views/auth/session_extend.php';
    }

    // ============================================================
    // WEB: 세션 만료 화면(webExpired) (permission: public)
    // URL: GET /autologout/expired
    // ============================================================
    public function webExpired()
    {
        require PROJECT_ROOT . '/app/views/autologout/session_expired.php';
    }
}
