<?php
// 경로: PROJECT_ROOT . '/app/Controllers/Auth/PasswordController.php'
namespace App\Controllers\Auth;
/*
 * PasswordController
 * - 아이디 찾기, 비밀번호 찾기 뷰 렌더링 전담
 * - 비밀번호/아이디를 찾는 실제 비즈니스 로직은 API(Service)에서 처리하며
 *   이 컨트롤러는 "단순 뷰 페이지 반환"만 역할로 한다.
 */
use Core\DbPdo;
use App\Services\Auth\AuthService;

class PasswordController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService(DbPdo::conn());
    }    

    //// ============================================================
    // WEB: 아이디 찾기 화면
    // URL: GET /find-id
    // permission: 없음
    // controller: PasswordController@webFindId
    //// ============================================================
    public function webFindId()
    {
        include PROJECT_ROOT . '/app/views/auth/find_id.php';
    }

    //// ============================================================
    // WEB: 아이디 찾기 결과 화면
    // URL: GET /find-id/result
    // permission: 없음
    // controller: PasswordController@webFindIdResult
    //// ============================================================
    public function webFindIdResult()
    {
        include PROJECT_ROOT . '/app/views/auth/find_id_result.php';
    }

    //// ============================================================
    // WEB: 비밀번호 찾기 화면
    // URL: GET /find-password
    // permission: 없음
    // controller: PasswordController@webFindPassword
    //// ============================================================
    public function webFindPassword()
    {
        include PROJECT_ROOT . '/app/views/auth/find_password.php';
    }

    //// ============================================================
    // WEB: 비밀번호 찾기 결과 화면
    // URL: GET /find-password/result
    // permission: 없음
    // controller: PasswordController@webFindPasswordResult
    //// ============================================================
    public function webFindPasswordResult()
    {
        include PROJECT_ROOT . '/app/views/auth/find_password_result.php';
    }

    //// ============================================================
    //// WEB: 비밀번호 만료 / 변경 통합 페이지
    //// URL: GET /password/change
    //// permission: 없음 (로그인 직후 강제 이동 전용)
    //// controller: PasswordController@webChangePassword
    //// ============================================================
    public function webChangePassword()
    {
        // 🔍 세션 상태 로깅
        error_log("🌐 PasswordController@webChangePassword - 세션 값 확인: " . var_export([
            'pw_change_required' => $_SESSION['pw_change_required'] ?? null,
            'user'               => $_SESSION['user'] ?? null,
        ], true));
    
        // 🔴 강제 변경 여부 판단
        $isForceChange = !empty($_SESSION['pw_change_required']);
    
        // ❌ 접근 불가: 로그인도 아니고 강제 변경도 아님
        if (!$isForceChange && empty($_SESSION['user']['id'])) {
            error_log("⚠ 비밀번호 변경 페이지 접근 차단 → /login 이동");
            header('Location: /login');
            exit;
        }
    
        // 🟢 일반 변경으로 접근한 경우
        // → 혹시 남아 있을 pw_change_required 제거
        if (!$isForceChange && !empty($_SESSION['pw_change_required'])) {
            unset($_SESSION['pw_change_required']);
        }
    
        // ✅ 뷰에서 사용할 변수
        $viewData = [
            'isForceChange' => $isForceChange
        ];
    
        // PHP include 특성상 extract 사용
        extract($viewData);
    
        include PROJECT_ROOT . '/app/views/auth/password_change.php';
        exit;
    }
    

    
    // WEB: 비밀번호 변경 유예 처리
    public function webChangeLater()
    {
        if (empty($_SESSION['pw_change_required'])) {
            header('Location: /login');
            exit;
        }

        // 🔥 로그인 세션 생성 (핵심)
        $_SESSION['user'] = [
            'id' => $_SESSION['pw_change_required']['user_id'],
            // 필요하면 username, role 등 추가
        ];

        unset($_SESSION['pw_change_required']);

        header('Location: /dashboard');
        exit;
    }

    
    //// ============================================================
    // API: 비밀번호 변경 처리
    // URL: POST /api/auth/password/change
    // permission: 로그인 필요
    // controller: PasswordController@apiChangePassword
    //// ============================================================
    public function apiChangePassword()
    {
        header('Content-Type: application/json; charset=UTF-8');
    
        // 🔐 로그인 체크 (이건 진짜 에러이므로 401 유지)
        if (empty($_SESSION['user']['id'])) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => '로그인이 필요합니다.'
            ]);
            return;
        }
    
        $input = json_decode(file_get_contents('php://input'), true);
    
        $currentPassword = trim($input['current_password'] ?? '');
        $newPassword     = trim($input['new_password'] ?? '');
        $confirmPassword = trim($input['confirm_password'] ?? '');
    
        // 🔥 비밀번호 만료 강제 변경 여부
        $isForceChange = !empty($_SESSION['pw_change_required']);
    
        // ✅ 필수값 검사 (UX 검증 → 200 OK)
        if ($newPassword === '' || $confirmPassword === '') {
            echo json_encode([
                'success' => false,
                'message' => '새 비밀번호를 입력해 주세요.'
            ]);
            return;
        }
    
        // ✅ 일반 변경일 때만 현재 비밀번호 필요
        if (!$isForceChange && $currentPassword === '') {
            echo json_encode([
                'success' => false,
                'message' => '현재 비밀번호를 입력해 주세요.'
            ]);
            return;
        }
    
        if ($newPassword !== $confirmPassword) {
            echo json_encode([
                'success' => false,
                'message' => '새 비밀번호가 일치하지 않습니다.'
            ]);
            return;
        }
    
        // 🔥 서비스 로직 호출
        $result = $this->authService->changePasswordWithVerify(
            $_SESSION['user']['id'],
            $currentPassword ?: null,
            $newPassword
        );
    
        // ❌ 여기서도 400 쓰지 않는다
        if (!$result['success']) {
            echo json_encode($result);
            return;
        }
    
        // ✅ 정상 성공
        echo json_encode([
            'success'  => true,
            'message'  => $result['message'],
            'redirect' => '/dashboard'
        ]);
    }
    
    

    //// ============================================================
    // API: 비밀번호 변경 유예
    // URL: POST /api/auth/password/change-later
    // permission: 비밀번호 만료 상태
    // controller: PasswordController@apiChangeLater
    //// ============================================================
    public function apiChangeLater()
    {
        header('Content-Type: application/json; charset=UTF-8');
    
        // 이미 로그인된 경우 → 그냥 통과
        if (!empty($_SESSION['user']['id'])) {
            echo json_encode([
                'success' => true,
                'redirect' => '/dashboard'
            ]);
            return;
        }
    
        // pw_change_required가 있을 때만 처리
        if (!empty($_SESSION['pw_change_required']['user_id'])) {
            $_SESSION['user'] = [
                'id' => $_SESSION['pw_change_required']['user_id']
            ];
            unset($_SESSION['pw_change_required']);
        }
    
        echo json_encode([
            'success' => true,
            'redirect' => '/dashboard'
        ]);
    }
    
    

}
