<?php
// 경로: PROJECT_ROOT . '/app/Controllers/Ledger/JournalController.php'

namespace App\Controllers\Ledger;

use App\Controllers\System\LayoutController;
use Core\DbPdo;

class JournalController
{
    private LayoutController $layout;

    public function __construct()
    {
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

        $this->layout->render([
            'pageTitle' => $pageTitle ?? '',
            'content' => $content,
            'layoutOptions' => $layoutOptions ?? [],
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
        ]);
    }

    public function webIndex(): void
    {
        $this->renderPage('/app/views/ledger/journal/index.php', [
            'pageTitle' => '일반전표',
        ]);
    }
}
