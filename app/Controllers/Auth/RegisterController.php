<?php
// 경로: PROJECT_ROOT . '/app/controllers/auth/RegisterController.php'
namespace App\Controllers\Auth;

use Core\DbPdo;
use App\Services\Auth\RegisterService;
use App\Services\File\FileService;
use App\Services\Mail\MailService;
use App\Services\System\SettingService;


class RegisterController
{
    private MailService $mailer;
    private SettingService $systemSettingService;
    private array $smtpConfig;

    public function __construct()
    {
        $this->mailer = new MailService(DbPdo::conn());
        $this->systemSettingService = new SettingService(DbPdo::conn());

        // SMTP 설정 로드
        $configFile = PROJECT_ROOT . '/config/appsetting.json';
        if (file_exists($configFile)) {
            $raw = file_get_contents($configFile);
            // 1. JSON 내 주석 제거 (Mailer 와 동일한 방식)
            $raw = preg_replace('#^\s*//.*$#m', '', $raw);
            $raw = preg_replace('#/\*.*?\*/#s', '', $raw);

            $cfg = json_decode($raw, true) ?: [];
            $this->smtpConfig = $cfg['SmtpSettings'] ?? [];
        } else {
            $this->smtpConfig = [];
        }
    }

    // ============================================================
    // WEB: 회원가입 화면
    // URL: GET /register
    // permission: 없음
    // controller: RegisterController@webRegisterPage
    // ============================================================
    public function webRegisterPage()
    { 
        if (!empty($_SESSION['user']['id'])) {
            header('Location: /dashboard');
            exit;
        }

        include PROJECT_ROOT . '/app/views/auth/register.php';
    }

    // ============================================================
    // WEB: 회원가입 성공 페이지
    // URL: GET /register_success
    // permission: 없음
    // controller: RegisterController@webRegisterSuccess
    // ============================================================
    public function webRegisterSuccess()
    {
        include PROJECT_ROOT . '/app/views/auth/register_success.php';
    }

    //// ============================================================
    // WEB: 관리자 승인 대기 화면
    // URL: GET /waiting-approval
    // permission: 없음
    // controller: RegisterController@webWaitingApproval
    //// ============================================================
    public function webWaitingApproval()
    {
        $message = $_SESSION['register_message'] ?? null;
        unset($_SESSION['register_message']);

        include PROJECT_ROOT . '/app/views/auth/waiting_approval.php';
    }

    // ============================================================
    // API: 회원가입 처리
    // URL: POST /api/auth/register
    // permission: auth.register
    // controller: RegisterController@apiRegister
    // ============================================================
    public function apiRegister()
    {
        // JSON 또는 FORM 입력 자동 처리
        $raw   = file_get_contents('php://input');
        $json  = json_decode($raw, true);
        $input = is_array($json) ? $json : $_POST;

        $isJson =
            ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest' ||
            str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');

        $username      = trim($input['username'] ?? '');
        $password      = trim($input['password'] ?? '');
        $confirm       = trim($input['confirm_password'] ?? '');
        $employee_name = trim($input['employee_name'] ?? '');
        $email         = trim($input['email'] ?? '');

        /* =====================================================
        * 1. 기본 입력 검증 (형식 레벨)
        * ===================================================== */
        if ($username === '' || $password === '' || $confirm === '' ||
            $employee_name === '' || $email === '') {
            return $this->apiFail($isJson, '모든 필드를 입력해주세요.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->apiFail($isJson, '이메일 형식이 올바르지 않습니다.');
        }

        if ($password !== $confirm) {
            return $this->apiFail($isJson, '비밀번호가 일치하지 않습니다.');
        }

        /* =====================================================
        * 2. 🔐 비밀번호 정책 검사 (시스템 설정 기반)
        *    ❗ 중복 검사 아님 → 컨트롤러 책임
        * ===================================================== */
        try {
            $rows = $this->systemSettingService->getByCategory('SECURITY');

            $policy = [];
            foreach ($rows as $row) {
                $policy[$row['config_key']] = $row['config_value'];
            }

            $policyEnabled = ($policy['security_password_policy_enabled'] ?? '0') === '1';

            if ($policyEnabled) {

                $min = (int)($policy['security_password_min'] ?? 0);
                if ($min > 0 && mb_strlen($password) < $min) {
                    return $this->apiFail(
                        $isJson,
                        "비밀번호는 최소 {$min}자 이상이어야 합니다."
                    );
                }

                if (($policy['security_pw_upper'] ?? '0') === '1' &&
                    !preg_match('/[A-Z]/', $password)) {
                    return $this->apiFail(
                        $isJson,
                        '비밀번호에 대문자를 최소 1자 이상 포함해야 합니다.'
                    );
                }

                if (($policy['security_pw_number'] ?? '0') === '1' &&
                    !preg_match('/\d/', $password)) {
                    return $this->apiFail(
                        $isJson,
                        '비밀번호에 숫자를 최소 1자 이상 포함해야 합니다.'
                    );
                }

                if (($policy['security_pw_special'] ?? '0') === '1' &&
                    !preg_match('/[^a-zA-Z0-9]/', $password)) {
                    return $this->apiFail(
                        $isJson,
                        '비밀번호에 특수문자를 최소 1자 이상 포함해야 합니다.'
                    );
                }
            }

        } catch (\Throwable $e) {
            return $this->apiFail($isJson, '비밀번호 정책 확인 중 오류가 발생했습니다.');
        }

        /* =====================================================
        * 3. 프로필 이미지 업로드 (선택)
        * ===================================================== */
        $profile_image = '';
        if (!empty($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $fileService = new FileService(DbPdo::conn());
            $upload = $fileService->uploadProfile($_FILES['profile_image']);
            if (!empty($upload['success'])) {
                $profile_image = $upload['db_path'];
            }
        }

        /* =====================================================
        * 4. RegisterService 위임
        *    🔥 중복 검사, 트랜잭션은 서비스 책임
        * ===================================================== */
        try {
            $registerService = new RegisterService(DbPdo::conn());

            $input['profile_image'] = $profile_image;

            $result = $registerService->register($input);

        } catch (\Throwable $e) {
            return $this->apiFail($isJson, '회원가입 처리 중 오류가 발생했습니다.');
        }

        if (empty($result['success'])) {
            return $this->apiFail(
                $isJson,
                $result['message'] ?? '회원가입 실패'
            );
        }

        /* =====================================================
        * 5. 성공 응답
        * ===================================================== */
        if ($isJson) {
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $_SESSION['register_message'] = '회원가입이 완료되었습니다. 관리자 승인 후 로그인 가능합니다.';
        header('Location: /register_success');
        exit;
    }




    // ============================================================
    // 내부 헬퍼: 실패 처리(JSON/Redirect 통합)
    // ============================================================
    private function apiFail(bool $isJson, string $msg)
    {
        if ($isJson) {
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        }

        $_SESSION['register_message'] = $msg;
        header('Location: /register');
        exit;
    }

    /* ================================================================
     * 관리자가 승인하도록 메일 발송
     *    🔽 RegisterService 가 이미 메일을 보내고 있으므로
     *       이 메서드는 더 이상 사용되지 않습니다.
     *       원하면 완전히 삭제해도 됩니다.
     * ================================================================ */
    private function sendAdminNotification(string $username, string $employee_name, string $userCode): void
    {
        // $userCode 는 이미 auth_users.code 값
        $adminEmail = $this->smtpConfig['AdminEmail'] ?? null;
        if (!$adminEmail) {            
            return;
        }

        $data = [
            'to'            => $adminEmail,
            'username'      => $username,
            'employee_name' => $employee_name,
            'user_code'     => $userCode,
            'host'          => $_SERVER['HTTP_HOST'] ?? 'localhost',
        ];

        try {
            $this->mailer->sendAdminApprovalMail($data);
        } catch (\Throwable $e) {           
        }
    }
}