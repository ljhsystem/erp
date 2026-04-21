<?php

namespace App\Controllers\User;

use App\Controllers\System\LayoutController;
use App\Services\Auth\AuthService;
use App\Services\User\ProfileService;
use Core\DbPdo;

class ProfileController
{
    private AuthService $authService;
    private ProfileService $profileService;
    private LayoutController $layout;

    public function __construct()
    {
        $this->authService = new AuthService(DbPdo::conn());
        $this->profileService = new ProfileService(DbPdo::conn());
        $this->layout = new LayoutController(DbPdo::conn());
    }

    private function renderPage(string $viewPath, array $params = []): void
    {
        if (!empty($params)) {
            extract($params, EXTR_SKIP);
        }

        ob_start();
        require PROJECT_ROOT . $viewPath;
        $content = ob_get_clean();

        $pageTitle = $pageTitle ?? '내정보 관리';
        $pageStyles = $pageStyles ?? '';
        $pageScripts = $pageScripts ?? '';
        $layoutOptions = $layoutOptions ?? [];

        $this->layout->render([
            'pageTitle' => $pageTitle,
            'content' => $content,
            'layoutOptions' => $layoutOptions,
            'pageStyles' => $pageStyles,
            'pageScripts' => $pageScripts,
        ]);
    }

    public function webProfile()
    {
        $this->renderPage('/app/views/user/profile.php', [
            'pageTitle' => '내정보 관리',
        ]);
    }

    public function apiDetail()
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $userId = $_GET['user_id'] ?? null;
            $profile = $userId
                ? $this->profileService->getById((string) $userId)
                : $this->profileService->getCurrentProfile();

            if (!$profile) {
                echo json_encode([
                    'success' => false,
                    'message' => '프로필 정보를 찾을 수 없습니다.',
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            echo json_encode([
                'success' => true,
                'data' => $profile,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function apiSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $result = $this->profileService->saveCurrent($_POST, $_FILES);

            echo json_encode([
                'success' => $result['success'],
                'message' => $result['message'] ?? '',
                'error' => $result['error'] ?? null,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
