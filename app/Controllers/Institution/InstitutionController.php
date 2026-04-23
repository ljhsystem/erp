<?php

namespace App\Controllers\Institution;

use App\Controllers\System\LayoutController;
use Core\DbPdo;
use PDO;

class InstitutionController
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
            'pageTitle' => $pageTitle ?? '대외기관업무',
            'content' => $content,
            'layoutOptions' => $layoutOptions ?? [],
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
        ]);
    }

    public function webIndex(): void
    {
        $this->renderPage('/app/views/institution/index.php', [
            'pageTitle' => '대외기관업무',
        ]);
    }
}
