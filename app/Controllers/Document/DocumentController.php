<?php

namespace App\Controllers\Document;

use App\Controllers\System\LayoutController;
use Core\DbPdo;
use PDO;

class DocumentController
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
            'pageTitle' => $pageTitle ?? '내부문서',
            'content' => $content,
            'layoutOptions' => $layoutOptions ?? [],
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
        ]);
    }

    public function webIndex(): void
    {
        $this->renderPage('/app/views/document/index.php', [
            'pageTitle' => '내부문서',
        ]);
    }

    public function webFileRegister(): void
    {
        $this->renderPage('/app/views/sukhyang/file_register.php', [
            'pageTitle' => '?대?臾몄꽌',
        ]);
    }

    public function webView(): void
    {
        $this->renderPage('/app/views/sukhyang/view.php', [
            'pageTitle' => '?대?臾몄꽌',
        ]);
    }

    public function webEdit(): void
    {
        $this->renderPage('/app/views/sukhyang/edit.php', [
            'pageTitle' => '?대?臾몄꽌',
        ]);
    }

    public function webStats(): void
    {
        $this->renderPage('/app/views/sukhyang/stats.php', [
            'pageTitle' => '?대?臾몄꽌',
        ]);
    }
}
