<?php

namespace App\Controllers\Ledger;

use App\Controllers\System\LayoutController;
use App\Services\Ledger\LedgerDashboardService;
use Core\DbPdo;
use Throwable;

final class LedgerDashboardController
{
    private LayoutController $layout;
    private LedgerDashboardService $service;

    public function __construct()
    {
        $pdo = DbPdo::conn();
        $this->layout = new LayoutController($pdo);
        $this->service = LedgerDashboardService::fromPdo($pdo);
    }

    public function index(): void
    {
        $pageTitle = '회계관리';
        ob_start();
        require PROJECT_ROOT . '/app/views/ledger/index.php';
        $content = ob_get_clean();
        $this->layout->render(compact('pageTitle','content','pageStyles','pageScripts','layoutOptions'));
    }

    public function apiSummary(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $fiscalYear = filter_input(INPUT_GET, 'fiscal_year', FILTER_VALIDATE_INT);
            echo json_encode(['success'=>true,'data'=>$this->service->summary(null, $fiscalYear ?: null),'message'=>'회계 현황을 불러왔습니다.'], JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Throwable) {
            http_response_code(500);
            echo json_encode(['success'=>false,'data'=>null,'message'=>'회계 현황을 불러오지 못했습니다.'], JSON_UNESCAPED_UNICODE);
        }
    }
}
