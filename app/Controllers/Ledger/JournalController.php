<?php

namespace App\Controllers\Ledger;

use App\Controllers\System\LayoutController;
use App\Services\Ledger\EvidenceTypePolicyService;
use Core\DbPdo;
use PDO;

class JournalController
{
    private LayoutController $layout;
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = DbPdo::conn();
        $this->layout = new LayoutController($this->pdo);
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
        $evidenceTypePolicies = (new EvidenceTypePolicyService(null, $this->pdo))
            ->statusViewImportTypePolicies();

        $this->renderPage('/app/views/ledger/journal/index.php', [
            'pageTitle' => '일반전표',
            'evidenceTypePolicies' => $evidenceTypePolicies,
        ]);
    }
}
