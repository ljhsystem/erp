<?php

namespace App\Controllers\Auth;

use App\Services\Auth\AuthSessionService;
use App\Services\Auth\RegisterService;
use Core\DbPdo;
use PDO;

class RegisterController
{
    private RegisterService $registerService;
    private AuthSessionService $authSessionService;

    public function __construct(?PDO $pdo = null)
    {
        $connection = $pdo ?? DbPdo::conn();
        $this->registerService = new RegisterService($connection);
        $this->authSessionService = new AuthSessionService();
    }

    public function webRegisterPage()
    {
        include PROJECT_ROOT . '/app/views/auth/register.php';
    }

    public function webRegisterSuccess()
    {
        include PROJECT_ROOT . '/app/views/auth/register_success.php';
    }

    public function webWaitingApproval()
    {
        $message = $this->authSessionService->pullFlash('register_message', '관리자 승인 대기 중입니다.');
        include PROJECT_ROOT . '/app/views/auth/waiting_approval.php';
    }

    public function apiRegister()
    {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        $input = is_array($json) ? $json : $_POST;

        $isJson =
            ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest' ||
            str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');

        $result = $this->registerService->register($input, $_FILES);

        if ($isJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!empty($result['success'])) {
            $this->authSessionService->setFlash('register_message', (string)($result['message'] ?? '회원가입이 완료되었습니다.'));
            header('Location: /register_success');
            exit;
        }

        $this->authSessionService->setFlash('register_message', (string)($result['message'] ?? '회원가입에 실패했습니다.'));
        header('Location: /register');
        exit;
    }
}
