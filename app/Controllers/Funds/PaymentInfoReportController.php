<?php

namespace App\Controllers\Funds;

use App\Controllers\System\LayoutController;
use App\Services\Funds\PaymentInfoReportService;
use Core\DbPdo;
use PDO;

class PaymentInfoReportController
{
    private PDO $pdo;
    private PaymentInfoReportService $service;
    private LayoutController $layout;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbPdo::conn();
        $this->service = new PaymentInfoReportService($this->pdo);
        $this->layout = new LayoutController($this->pdo);
    }

    public function index(): void
    {
        $this->renderPage('/app/views/funds/payment-info/index.php', [
            'pageTitle' => '결제정보',
        ]);
    }

    public function apiList(): void
    {
        $filters = $this->requestFilters();
        $this->json([
            'success' => true,
            'data' => $this->service->rows($filters),
            'summary' => $this->service->summary($filters),
        ]);
    }

    private function requestFilters(): array
    {
        $filters = [];
        $raw = (string) ($_GET['filters'] ?? '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $filters = array_values(array_filter($decoded, 'is_array'));
            }
        }

        foreach ([
            'payment_direction',
            'payment_type',
            'payment_name',
            'voucher_no',
            'summary_text',
        ] as $field) {
            $value = $_GET[$field] ?? null;
            if ($value !== null && trim((string) $value) !== '') {
                $filters[] = ['field' => $field, 'value' => trim((string) $value)];
            }
        }

        return $filters;
    }

    private function renderPage(string $viewPath, array $params = []): void
    {
        if ($params !== []) {
            extract($params, EXTR_SKIP);
        }

        ob_start();
        require PROJECT_ROOT . $viewPath;
        $content = ob_get_clean();

        $this->layout->render([
            'pageTitle' => $pageTitle ?? '결제정보',
            'content' => $content,
            'layoutOptions' => $layoutOptions ?? [],
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
        ]);
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
