<?php

namespace App\Controllers\Auth;

use App\Services\Auth\AuthService;
use Core\DbPdo;
use PDO;

class TwoFactorController
{
    private AuthService $authService;

    public function __construct(?PDO $pdo = null)
    {
        $this->authService = new AuthService($pdo ?? DbPdo::conn());
    }

    public function webTwoFactor()
    {
        $pageData = $this->authService->getTwoFactorPageData();
        if (empty($pageData['allowed'])) {
            header('Location: ' . ($pageData['redirect'] ?? '/login'));
            exit;
        }

        $message = $pageData['message'] ?? '';
        $email = $pageData['email'] ?? null;
        $activeReasons = $pageData['activeReasons'] ?? [];

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        include PROJECT_ROOT . '/app/views/auth/two_factor.php';
    }

    public function apiVerify()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            $data = $_POST;
        }

        $result = $this->authService->verifyTwoFactor((string)($data['code'] ?? ''));

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
