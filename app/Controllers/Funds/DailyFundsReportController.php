<?php

namespace App\Controllers\Funds;

use App\Controllers\System\LayoutController;
use App\Services\Funds\DailyFundsReportService;
use Core\DbPdo;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PDO;

class DailyFundsReportController
{
    private DailyFundsReportService $service;
    private LayoutController $layout;

    public function __construct(?PDO $pdo = null)
    {
        $pdo = $pdo ?? DbPdo::conn();
        $this->service = new DailyFundsReportService($pdo);
        $this->layout = new LayoutController($pdo);
    }

    public function index(): void
    {
        $pageTitle = '자금일보';
        $filterOptions = $this->service->filterOptions();
        $initialFilters = $this->service->filters($_GET);
        ob_start();
        include PROJECT_ROOT . '/app/views/funds/daily-report/index.php';
        $content = ob_get_clean();
        $this->layout->render(compact('pageTitle', 'content') + [
            'layoutOptions' => $layoutOptions ?? [], 'pageStyles' => $pageStyles ?? '', 'pageScripts' => $pageScripts ?? '',
        ]);
    }

    public function apiReport(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'data' => $this->service->report($_GET)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function apiExcel(): void
    {
        $book = $this->service->spreadsheet($_GET);
        $filename = '자금일보_' . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="daily-funds-report.xlsx"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('Cache-Control: max-age=0');
        (new Xlsx($book))->save('php://output');
        $book->disconnectWorksheets();
        exit;
    }
}
