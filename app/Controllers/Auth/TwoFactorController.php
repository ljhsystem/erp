<?php
// 경로: PROJECT_ROOT . '/app/controllers/auth/TwoFactorController.php'
namespace App\Controllers\Auth;

use Core\DbPdo;
use App\Services\Auth\AuthService;

class TwoFactorController
{
    public function __construct(){}

    // ============================================================
    // WEB: 2단계 인증 화면
    // URL: GET /2fa
    // permission: 없음
    // controller: TwoFactorController@webTwoFactor
    // ============================================================
    public function webTwoFactor()
    {
        $pending = $_SESSION['pending_2fa'] ?? null;

        if (!is_array($pending)) {
            $_SESSION['two_factor_message'] = '인증이 필요합니다. 다시 로그인하세요.';
            header('Location: /login');
            exit;
        }

        // 🔒 캐시 방지
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        include PROJECT_ROOT . '/app/views/auth/two_factor.php';
    }

    // ============================================================
    // api(JSON): 2단계 인증 코드 검증
    // URL: POST /api/2fa/verify
    // permission: 없음 (로그인 전 플로우)
    // controller: TwoFactorController@apiVerify
    // ============================================================
    public function apiVerify()
    {
        header('Content-Type: application/json; charset=utf-8');

        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            $data = $_POST;
        }

        $code = trim((string)($data['code'] ?? ''));
        if ($code === '') {
            return $this->jsonError('코드를 입력하세요.');
        }

        $pending = $_SESSION['pending_2fa'] ?? null;
        if (!is_array($pending)) {
            return $this->jsonError('인증 세션이 없습니다. 다시 로그인하세요.', '/login');
        }

        // 만료 체크
        if (!empty($pending['expires_at']) && $pending['expires_at'] < time()) {
            unset($_SESSION['pending_2fa']);
            return $this->jsonError('인증 코드가 만료되었습니다.', '/login');
        }

        // 코드 검증
        if (
            empty($pending['code_hash']) ||
            !hash_equals($pending['code_hash'], hash('sha256', $code))
        ) {
            return $this->jsonError('인증 코드가 올바르지 않습니다.');
        }

        // 사용자 정보 검증 (🔥 제일 중요)
        $user = $pending['user'] ?? null;
        if (!is_array($user) || empty($user['id'])) {
            unset($_SESSION['pending_2fa']);
            return $this->jsonError('사용자 정보를 찾을 수 없습니다.', '/login');
        }

        // ====================================================
        // ✅ 2FA 성공 → 로그인 성공 처리 (DB 기록)
        // ====================================================     
        $authService = new AuthService(DbPdo::conn());
        $authService->handleLoginSuccess(
            $user['id'],
            $_SERVER['REMOTE_ADDR'] ?? ''
        );

        // ====================================================
        // ✅ 로그인 세션 확정
        // ====================================================
        $_SESSION['user'] = [
            'id'        => $user['id'],
            'username'  => $user['username'],
            'role_id'   => $user['role_id'] ?? null,
            'role_key'  => $user['role_key'] ?? null,
            'role_name' => $user['role_name'] ?? null,
            'email'     => $user['email'] ?? null,
        ];

        $_SESSION['expire_time'] = time() + (30 * 60);
        unset($_SESSION['pending_2fa']);

        return $this->jsonSuccess('인증 성공', '/dashboard');
    }

    // ============================================================
    // JSON 성공
    // ============================================================
    private function jsonSuccess(string $message, string $redirect = null)
    {
        echo json_encode([
            'success'  => true,
            'message'  => $message,
            'redirect' => $redirect
        ]);
        exit;
    }

    // ============================================================
    // JSON 실패
    // ============================================================
    private function jsonError(string $message, string $redirect = null)
    {
        echo json_encode([
            'success'  => false,
            'message'  => $message,
            'redirect' => $redirect
        ]);
        exit;
    }
}
