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
    // API: 프로필 상세 조회 (통합)
    // URL: GET /api/user/profile/detail
    // permission: api.profile.view
    // controller: ProfileController@apiDetail
    // ============================================================
    public function apiDetail()
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            // 🔥 1️⃣ user_id 우선
            $userId = $_GET['user_id'] ?? null;

            // 🔥 2️⃣ 없으면 세션 fallback
            if (!$userId) {
                $userId = $_SESSION['user']['id'] ?? null;
            }

            if (!$userId) {
                throw new \Exception('user_id 또는 로그인 정보가 필요합니다.');
            }

            $profile = $this->profileService->getDetail($userId);

            if (!$profile) {
                echo json_encode([
                    'success' => false,
                    'message' => '프로필 정보를 찾을 수 없습니다.'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            echo json_encode([
                'success' => true,
                'data' => $profile
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }


















    public function apiSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {

            if (empty($_SESSION['user']['id'])) {
                throw new \Exception('인증이 필요합니다.');
            }

            $userId = $_SESSION['user']['id'];

            /* =========================
            * 🔹 FormData 수신
            * ========================= */
            $input = $_POST;
            $files = $_FILES;

            /* =========================
            * 🔥 핵심: id만 추가
            * ========================= */
            $input['id'] = $userId;

            /* =========================
            * 🔥 서비스에 전부 위임
            * ========================= */
            $result = $this->profileService->save($input, $files);

            echo json_encode([
                'success' => $result['success'],
                'message' => $result['message'] ?? '',
                'error'   => $result['error'] ?? null   // 🔥 디버깅용
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }


}
