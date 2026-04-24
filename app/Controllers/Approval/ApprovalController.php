<?php

namespace App\Controllers\Approval;

use App\Controllers\System\LayoutController;
use Core\DbPdo;
use PDO;

class ApprovalController
{
    private LayoutController $layout;

    public function __construct(?PDO $pdo = null)
    {
        $connection = $pdo ?? DbPdo::conn();
        $this->layout = new LayoutController($connection);
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
            'pageTitle' => $pageTitle ?? '전자결재',
            'content' => $content,
            'layoutOptions' => $layoutOptions ?? [],
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
        ]);
    }

    public function webIndex(): void
    {
        $this->renderPage('/app/views/approval/index.php', [
            'pageTitle' => '전자결재',
        ]);
    }
}
