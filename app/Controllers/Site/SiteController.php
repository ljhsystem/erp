<?php

namespace App\Controllers\Site;

use App\Controllers\System\LayoutController;
use Core\DbPdo;
use PDO;

class SiteController
{
    private LayoutController $layout;

    public function __construct(?PDO $pdo = null)
    {
        $this->layout = new LayoutController($pdo ?? DbPdo::conn());
    }

    private function renderPage(string $viewPath, array $params = []): void
    {
        if (!empty($params)) {
            extract($params, EXTR_SKIP);
        }

        ob_start();
        require PROJECT_ROOT . $viewPath;
        $content = ob_get_clean();

        $this->layout->render([
            'pageTitle' => $pageTitle ?? '현장대시보드',
            'content' => $content,
            'layoutOptions' => $layoutOptions ?? [],
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
        ]);
    }

    public function dashboard(): void
    {
        $this->renderPage('/app/views/site/index.php', [
            'pageTitle' => '현장대시보드',
        ]);
    }
}
