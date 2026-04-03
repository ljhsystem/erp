<?php
// 경로: PROJECT_ROOT . '/app/controllers/user/ProfileController.php'
namespace App\Controllers\User;

use Core\Session;
use Core\DbPdo;
use App\Controllers\System\LayoutController;
use App\Services\Auth\AuthService;
use App\Services\User\ProfileService;


class ProfileController
{
    private AuthService $authService;
    private ProfileService $profileService;
    private LayoutController $layout;

    public function __construct()
    {
        Session::requireAuth();
        $this->authService = new AuthService(DbPdo::conn());
        $this->profileService = new ProfileService(DbPdo::conn());
        $this->layout = new LayoutController(DbPdo::conn());

    }

    private function renderPage(string $viewPath, array $params = []): void
    {

        // 1️⃣ 컨트롤러에서 전달한 기본 파라미터만 먼저 주입
        if (!empty($params)) {
            extract($params, EXTR_SKIP);
        }
    
        // 2️⃣ 뷰 실행 (여기서 $pageTitle, $pageStyles, $pageScripts, $layoutOptions 세팅됨)
        ob_start();
        require PROJECT_ROOT . $viewPath;
        $content = ob_get_clean();
    
        // 3️⃣ 뷰가 설정한 값 우선, 없으면 기본값
        $pageTitle     = $pageTitle     ?? '내 정보 관리';
        $pageStyles    = $pageStyles    ?? '';
        $pageScripts   = $pageScripts   ?? '';
        $layoutOptions = $layoutOptions ?? [];
    
        // 4️⃣ 레이아웃 렌더
        $this->layout->render([
            'pageTitle'     => $pageTitle,
            'content'       => $content,
            'layoutOptions' => $layoutOptions,
            'pageStyles'    => $pageStyles,
            'pageScripts'   => $pageScripts,
        ]);
    }

    // ============================================================
    // API: 내 프로필 조회 (user_id 없이)
    // URL: GET /api/user/profile
    // permission: api.profile.me
    // controller: ProfileController@apiProfileMe
    // ============================================================
    public function apiProfileMe()
    {
        header('Content-Type: application/json; charset=UTF-8');
    
        $userId = $_SESSION['user']['id'] ?? null;
    
        if (!$userId) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => '로그인이 필요합니다.'
            ]);
            return;
        }
    
        $profile = $this->profileService->getProfile($userId);
    
        if (!$profile) {
            echo json_encode([
                'success' => false,
                'message' => '프로필 정보를 찾을 수 없습니다.'
            ]);
            return;
        }
    
        echo json_encode([
            'success' => true,
            'data'    => $profile
        ], JSON_UNESCAPED_UNICODE);
    }
    
    
    
    

    // ============================================================
    // WEB: 내 프로필 페이지
    // URL: GET /profile
    // permission: web.profile.view
    // controller: ProfileController@webProfile
    // ============================================================
    public function webProfile()
    {
        $this->renderPage('/app/views/user/profile.php', [
            'pageTitle' => '내 정보 관리'
        ]);
    }
    
    

    // ============================================================
    // API: 프로필 단일 조회
    // URL: GET /user/profile/get?user_id=xxx
    // permission: api.profile.view
    // controller: ProfileController@apiGet
    // ============================================================
    public function apiGet()
    {
        header('Content-Type: application/json; charset=utf-8');

        $userId = $_GET['user_id'] ?? '';
        echo json_encode(
            $this->profileService->getProfile($userId)
        );
    }

    // ============================================================
    // API: 프로필 생성
    // URL: POST /user/profile/create
    // permission: api.profile.create
    // controller: ProfileController@apiCreate
    // ============================================================
    public function apiCreate()
    {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(
            $this->profileService->createProfile($_POST)
        );
    }

    // ============================================================
    // API: 프로필 이미지 업로드
    // URL: POST /user/profile/update-image
    // permission: api.profile.update_image
    // controller: ProfileController@apiUpdateImage
    // ============================================================
    public function apiUpdateImage()
    {
        header('Content-Type: application/json; charset=utf-8');
    
        try {
            // ✅ 로그인 사용자 기준 (POST로 user_id 받지 않음)
            if (empty($_SESSION['user']['id'])) {
                throw new \Exception('인증이 필요합니다.');
            }
    
            if (empty($_FILES['profile_image'])) {
                throw new \Exception('업로드된 파일이 없습니다.');
            }
    
            $userId = $_SESSION['user']['id'];
            $file   = $_FILES['profile_image'];
    
            $result = $this->profileService->updateProfileImage($userId, $file);
    
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }
    

    // ============================================================
    // API: 직원 이름 수정
    // URL: POST /user/profile/update-name
    // permission: api.profile.update_name
    // controller: ProfileController@apiUpdateName
    // ============================================================
    public function apiUpdateName()
    {
        header('Content-Type: application/json; charset=utf-8');

        $userId = $_POST['user_id'] ?? '';
        $name   = $_POST['employee_name'] ?? '';

        echo json_encode(
            $this->profileService->updateBasicInfo($userId, [
                'employee_name' => $name
            ])
        );
    }

    // ============================================================
    // API: 사용자 + 프로필 통합 조회
    // URL: GET /user/profile/user-info?user_id=xxx
    // permission: api.profile.userinfo
    // controller: ProfileController@apiGetUserInfo
    // ============================================================
    public function apiGetUserInfo()
    {
        header('Content-Type: application/json; charset=utf-8');

        $userId = $_GET['user_id'] ?? '';
        echo json_encode(
            $this->profileService->getProfile($userId)
        );
    }

    // ============================================================
    // API: 2단계 인증 활성/비활성 변경
    // URL: POST /user/profile/update-2fa
    // permission: api.profile.update_2fa
    // controller: ProfileController@apiUpdateTwoFactor
    // ============================================================
    public function apiUpdateTwoFactor()
    {
        header('Content-Type: application/json; charset=utf-8');

        $userId  = $_POST['user_id'] ?? '';
        $enabled = (int)($_POST['enabled'] ?? 0);

        echo json_encode(
            $this->profileService->updateTwoFactorEnabled($userId, $enabled)
        );
    }

    // ============================================================
    // API: 내 프로필 전체 수정 (세션 기반)
    // URL: POST /api/user/profile/update
    // permission: api.profile.update
    // controller: ProfileController@apiUpdateProfile
    // ============================================================
    public function apiUpdateProfile()
    {  
        header('Content-Type: application/json; charset=utf-8');

        $userId = $_SESSION['user']['id'];

        /* =========================
        * 🔹 FormData 수신
        * ========================= */
        $input = $_POST;

        /* =========================
        * 🔐 auth_users
        * ========================= */
        $authData = [];

        if (isset($input['email'])) {
            $authData['email'] = trim($input['email']);
        }
        if (isset($input['two_factor_enabled'])) {
            $authData['two_factor_enabled'] = (int)$input['two_factor_enabled'];
        }
        if (isset($input['email_notify'])) {
            $authData['email_notify'] = (int)$input['email_notify'];
        }
        if (isset($input['sms_notify'])) {
            $authData['sms_notify'] = (int)$input['sms_notify'];
        }

        /* =========================
        * 👤 user_profiles
        * ========================= */
        $profileData = [];

        foreach ([
            'employee_name',
            'phone',
            'emergency_phone',
            'address',
            'address_detail',
            'certificate_name'
        ] as $field) {
            if (array_key_exists($field, $input)) {
                $profileData[$field] = $input[$field];
            }
        }

        /* =========================
        * 📎 자격증 파일
        * ========================= */
        if (!empty($_FILES['certificate_file']['name'])) {
            $upload = $this->profileService->updateCertificateFile(
                $userId,
                $_FILES['certificate_file']
            );

            if (empty($upload['success'])) {
                echo json_encode($upload, JSON_UNESCAPED_UNICODE);
                return;
            }

            $profileData['certificate_file'] = $upload['certificate_file'];
        }

        /* =========================
        * 🔄 통합 업데이트
        * ========================= */
        $result = $this->profileService->updateFullProfile(
            $userId,
            $authData,
            $profileData
        );

        echo json_encode([
            'success' => $result['success'],
            'message' => $result['success']
                ? '저장되었습니다.'
                : '저장 실패'
        ], JSON_UNESCAPED_UNICODE);
    }

    // ============================================================
    // API: 내 비밀번호 변경 (세션 기반)
    // URL: POST /api/user/profile/change-password
    // permission: api.profile.changepassword
    // controller: ProfileController@apiChangePassword
    // ============================================================

    public function apiChangePassword()
    {
        header('Content-Type: application/json; charset=utf-8');

        $userId = $_SESSION['user']['id'];

        $current = trim($_POST['current_password'] ?? '');
        $new     = trim($_POST['new_password'] ?? '');
        $confirm = trim($_POST['confirm_password'] ?? '');

        // 1️⃣ 기본 검증
        if ($current === '' || $new === '' || $confirm === '') {
            echo json_encode([
                'success' => false,
                'message' => '모든 항목을 입력하세요.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($new !== $confirm) {
            echo json_encode([
                'success' => false,
                'message' => '새 비밀번호가 일치하지 않습니다.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 2️⃣ AuthService에 위임 (검증 포함)
        $result = $this->authService->changePasswordWithVerify(
            $userId,
            $current,
            $new
        );

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }


}
