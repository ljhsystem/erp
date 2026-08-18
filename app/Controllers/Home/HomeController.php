<?php
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

        if (!empty($params)) {
            extract($params, EXTR_SKIP);
        }

        ob_start();
        require PROJECT_ROOT . $viewPath;
        $content = ob_get_clean();

        $pageTitle     = $pageTitle     ?? '사이트맵';
        $pageStyles    = $pageStyles    ?? '';
        $pageScripts   = $pageScripts   ?? '';
        $layoutOptions = $layoutOptions ?? [];

        $this->layout->render([
            'pageTitle'     => $pageTitle,
            'content'       => $content,
            'layoutOptions' => $layoutOptions,
            'pageStyles'    => $pageStyles,
            'pageScripts'   => $pageScripts,
        ]);
    }

    public function webRoot()
    {
        $this->redirectAuthenticatedUser();
        header("Location: /home");
        exit;
    }

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

    public function webAbout()
    {
        include PROJECT_ROOT . '/app/views/home/about.php';
    }

    public function webVision()
    {
        include PROJECT_ROOT . '/app/views/home/vision.php';
    }

    public function webContact()
    {
        include PROJECT_ROOT . '/app/views/home/contact.php';
    }

    public function webPrivacy()
    {
        include PROJECT_ROOT . '/app/views/home/privacy.php';
    }

    public function webSitemap()
    {
        $this->renderPage('/app/views/sitemap/index.php', [
            'pageTitle' => '사이트맵'
        ]);
    }
}
