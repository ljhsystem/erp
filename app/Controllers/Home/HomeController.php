<?php
// 경로: PROJECT_ROOT . '/app/Controllers/Home/HomeController.php';
namespace App\Controllers\Home;

use App\Services\Auth\AuthSessionService;
use Core\DbPdo;
use App\Controllers\System\LayoutController;
use PDO;


class HomeController
{
    private LayoutController $layout;
    private AuthSessionService $authSessionService;

    public function __construct(?PDO $pdo = null)
    {
        $this->layout = new LayoutController($pdo ?? DbPdo::conn()); 
        $this->authSessionService = new AuthSessionService();
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
        $pageTitle     = $pageTitle     ?? '사이트맵';
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
    // WEB: 루트 페이지 - 로그인 여부에 따라 redirect
    // URL: GET /
    // permission: none
    // controller: HomeController@webRoot
    // ============================================================
    public function webRoot()
    {
        $this->redirectAuthenticatedUser();
        header("Location: /home");
        exit;
    }    

    // ============================================================
    // WEB: 홈페이지
    // URL: GET /home
    // permission: none
    // controller: HomeController@webIndex
    // ============================================================
    public function webIndex()
    {
        $this->redirectAuthenticatedUser();
        include PROJECT_ROOT . '/app/views/home/index.php';
    }

    private function redirectAuthenticatedUser(): void
    {
        if (!$this->authSessionService->isAuthenticated()) {
            return;
        }

        header('Location: /dashboard');
        exit;
    }

    // ============================================================
    // WEB: 회사 소개
    // URL: GET /about
    // permission: none
    // controller: HomeController@webAbout
    // ============================================================
    public function webAbout()
    {
        include PROJECT_ROOT . '/app/views/home/about.php';
    }

    // ============================================================
    // WEB: 기업 비전
    // URL: GET /vision
    // permission: none
    // controller: HomeController@webVision
    // ============================================================
    public function webVision()
    {
        include PROJECT_ROOT . '/app/views/home/vision.php';
    }

    // ============================================================
    // WEB: 문의하기
    // URL: GET /contact
    // permission: none
    // controller: HomeController@webContact
    // ============================================================
    public function webContact()
    {
        include PROJECT_ROOT . '/app/views/home/contact.php';
    }

    // ============================================================
    // WEB: 개인정보 처리방침
    // URL: GET /privacy
    // permission: none
    // controller: HomeController@webPrivacy
    // ============================================================
    public function webPrivacy()
    {
        include PROJECT_ROOT . '/app/views/home/privacy.php';
    }

    // ============================================================
    // WEB: 사이트맵
    // URL: GET /sitemap
    // permission: none
    // controller: HomeController@webSitemap
    // ============================================================
    public function webSitemap()
    { 
        $this->renderPage('/app/views/sitemap/index.php', [
            'pageTitle' => '사이트맵'
        ]);
    }
}
