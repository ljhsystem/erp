<?php

namespace App\Controllers\Auth;

use App\Services\Auth\AuthService;
use Core\DbPdo;
use PDO;

class PasswordController
{
    private AuthService $authService;

    public function __construct(?PDO $pdo = null)
    {
        $this->authService = new AuthService($pdo ?? DbPdo::conn());
    }

    public function webFindId()
    {
        include PROJECT_ROOT . '/app/views/auth/find_id.php';
    }

    public function webFindIdResult()
    {
        $result = $this->authService->findUsername($_POST);
        $found = (bool) ($result['found'] ?? false);
        $username = (string) ($result['username'] ?? '');

        include PROJECT_ROOT . '/app/views/auth/find_id_result.php';
    }

    public function webFindPassword()
    {
        include PROJECT_ROOT . '/app/views/auth/find_password.php';
    }

    public function webFindPasswordResult()
    {
        $viewData = $this->authService->recoverPassword($_POST);
        $alertClass = (string)($viewData['alertClass'] ?? 'alert-warning');
        $resultHtml = (string)($viewData['resultHtml'] ?? '');

        include PROJECT_ROOT . '/app/views/auth/find_password_result.php';
    }

    public function webChangePassword()
    {
        $pageData = $this->authService->getPasswordChangePageData();
        if (empty($pageData['allowed'])) {
            header('Location: ' . ($pageData['redirect'] ?? '/login'));
            exit;
        }

        $isForceChange = (bool)($pageData['isForceChange'] ?? false);
        $message = $pageData['message'] ?? '';

        include PROJECT_ROOT . '/app/views/auth/password_change.php';
    }

    public function apiChangePassword()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $result = $this->authService->changePassword($input);

        header('Content-Type: application/json; charset=UTF-8');
        if (!empty($result['status'])) {
            http_response_code((int)$result['status']);
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiChangeLater()
    {
        $result = $this->authService->changePasswordLater();
        if (($result['status'] ?? null) === 400) {
            if ($this->authService->refreshCurrentUserSession()) {
                $result = [
                    'success' => true,
                    'message' => '다음에 변경하도록 처리했습니다.',
                    'redirect' => '/dashboard',
                ];
            }
        }

        header('Content-Type: application/json; charset=UTF-8');
        if (!empty($result['status'])) {
            http_response_code((int)$result['status']);
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
