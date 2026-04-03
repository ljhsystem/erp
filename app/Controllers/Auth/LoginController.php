<?php
// 경로: PROJECT_ROOT . '/app/controllers/auth/LoginController.php'
namespace App\Controllers\Auth;

use Core\DbPdo;
use App\Services\Auth\AuthService; 
use App\Services\Auth\TwoFactorService; 
use App\Services\Mail\MailService;

class LoginController
{
    private AuthService $authService;
    
    public function __construct()
    {
        $this->authService = new AuthService(DbPdo::conn());
    }
    

    // ============================================================
    // WEB: 로그인 페이지 렌더링
    // URL: GET /login
    // permission: 없음
    // controller: LoginController@webLoginPage
    // ============================================================
    public function webLoginPage()
    {
        // 이미 로그인된 경우 → 대시보드
        if (!empty($_SESSION['user']['id'])) {
            header('Location: /dashboard');
            exit;
        }

        include PROJECT_ROOT . '/app/views/auth/login.php';
    }

    // ============================================================
    // API: 로그인 처리
    // URL: POST /login
    // permission: auth.login
    // controller: LoginController@apiLogin
    // ============================================================
    public function apiLogin()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) $data = $_POST;
        
        $twoFactor = new TwoFactorService();
        $mailer    = new MailService();
        $result = $this->authService->login($data, $twoFactor, $mailer);

        // ✅ 항상 JSON + 200 응답
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result);
        exit;
    }

    // ============================================================
    // API: 로그아웃 처리
    // URL: GET /logout
    // permission: auth.logout
    // controller: LoginController@apiLogout
    // ============================================================
    public function apiLogout()
    {

        $currentUser = $_SESSION['user'] ?? [];
        $userId      = $currentUser['id']       ?? null;
        $username    = $currentUser['username'] ?? null;

        try {
            $this->authService->logout($userId, $username);
        } catch (\Throwable $e) {
            // DB 로그 실패는 무시
        }

        // 세션 정리
        $_SESSION = [];
        session_unset();
        session_destroy();

        $sessName = session_name();
        if (!empty($sessName)) {
            setcookie($sessName, '', time() - 3600, '/');
        }

        header('Location: /login');
        exit;
    }
}
