<?php

namespace App\Controllers\Funds;

use App\Controllers\System\LayoutController;
use App\Services\Funds\FundsOverviewService;
use Core\DbPdo;
use PDO;

class FundsOverviewController
{
    private FundsOverviewService $service;
    private LayoutController $layout;

    public function __construct(?PDO $pdo = null)
    {
        $pdo = $pdo ?? DbPdo::conn();
        $this->service = new FundsOverviewService($pdo);
        $this->layout = new LayoutController($pdo);
    }

    public function index(): void
    {
        $pageTitle = '자금현황';
        $overview = $this->service->overview();

        ob_start();
        include PROJECT_ROOT . '/app/views/funds/funds-overview/index.php';
        $content = ob_get_clean();

        $this->layout->render([
            'pageTitle' => $pageTitle,
            'content' => $content,
            'layoutOptions' => $layoutOptions ?? [],
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
        ]);
    }

    public function legacyAccountBalances(): void
    {
        header('Location: /ledger/funds', true, 302);
        exit;
    }
}
