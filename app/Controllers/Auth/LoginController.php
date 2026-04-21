<?php

namespace App\Controllers\Auth;

use App\Services\Auth\AuthService;
use App\Services\Auth\AuthSessionService;
use Core\DbPdo;
use PDO;

class LoginController
{
    private AuthService $authService;
    private AuthSessionService $authSessionService;

    public function __construct(?PDO $pdo = null)
    {
        $connection = $pdo ?? DbPdo::conn();
        $this->authService = new AuthService($connection);
        $this->authSessionService = new AuthSessionService();
    }

    public function webLoginPage()
    {
        $message = $this->authSessionService->pullFlash('login_message');
        $usernameValue = '';

        include PROJECT_ROOT . '/app/views/auth/login.php';
    }

    public function apiLogin()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            $data = $_POST;
        }

        $result = $this->authService->login($data);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiLogout()
    {
        $this->authService->logoutCurrentSession();

        header('Location: /login');
        exit;
    }
}
