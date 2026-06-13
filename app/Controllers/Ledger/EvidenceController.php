<?php

namespace App\Controllers\Ledger;

use App\Controllers\System\LayoutController;
use App\Services\Ledger\EvidenceDualWriteService;
use Core\DbPdo;
use PDO;

class EvidenceController
{
    private PDO $pdo;
    private LayoutController $layout;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbPdo::conn();
        $this->layout = new LayoutController($this->pdo);
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
            'pageTitle' => $pageTitle ?? '',
            'content' => $content,
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
            'layoutOptions' => $layoutOptions ?? [],
        ]);
    }

    public function webIndex(): void
    {
        $this->renderPage('/app/views/ledger/data/list.php', [
            'pageTitle' => '증빙원본',
        ]);
    }

    public function webRaw(): void
    {
        $this->renderPage('/app/views/ledger/data/raw.php', [
            'pageTitle' => '원본자료',
        ]);
    }

    public function apiEvidenceSummarySearch(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));

        $this->json([
            'success' => true,
            'items' => $this->searchEvidenceVoucherSummaryTexts($query, 10),
        ]);
    }

    public function apiEvidenceDualWriteSync(): void
    {
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }
        $evidenceId = trim((string) ($payload['evidence_id'] ?? $payload['id'] ?? ''));
        if ($evidenceId === '') {
            $this->json(['success' => false, 'message' => 'evidence_id is required.'], 400);
            return;
        }

        (new EvidenceDualWriteService($this->pdo))->syncByEvidenceId($evidenceId);
        $this->json(['success' => true, 'message' => 'Dual-write sync completed.', 'evidence_id' => $evidenceId]);
    }

    private function searchEvidenceVoucherSummaryTexts(string $keyword, int $limit = 10): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        $limit = max(1, min($limit, 20));
        $stmt = $this->pdo->prepare("
            SELECT mapped_payload_json, updated_at, created_at
            FROM ledger_data_evidences
            WHERE deleted_at IS NULL
              AND mapped_payload_json LIKE :keyword
            ORDER BY updated_at DESC, created_at DESC
            LIMIT 1000
        ");
        $stmt->execute([
            ':keyword' => '%' . $keyword . '%',
        ]);

        $summaries = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $payload = json_decode((string) ($row['mapped_payload_json'] ?? ''), true);
            if (!is_array($payload)) {
                continue;
            }

            $lastUsedAt = (string) ($row['updated_at'] ?? $row['created_at'] ?? '');
            $rowSummaries = [];
            foreach (['voucher_summary_text', 'summary_text'] as $field) {
                $summaryText = trim((string) ($payload[$field] ?? ''));
                if (isset($rowSummaries[$summaryText])) {
                    continue;
                }
                $matched = function_exists('mb_stripos')
                    ? mb_stripos($summaryText, $keyword)
                    : stripos($summaryText, $keyword);
                if ($summaryText === '' || $matched === false) {
                    continue;
                }
                $rowSummaries[$summaryText] = true;

                if (!isset($summaries[$summaryText])) {
                    $summaries[$summaryText] = [
                        'summary_text' => $summaryText,
                        'used_count' => 0,
                        'last_used_at' => $lastUsedAt,
                    ];
                }

                $summaries[$summaryText]['used_count']++;
                if ($lastUsedAt !== '' && strcmp($lastUsedAt, (string) $summaries[$summaryText]['last_used_at']) > 0) {
                    $summaries[$summaryText]['last_used_at'] = $lastUsedAt;
                }
            }
        }

        $items = array_values($summaries);
        usort($items, static function (array $a, array $b): int {
            $countCompare = ((int) ($b['used_count'] ?? 0)) <=> ((int) ($a['used_count'] ?? 0));
            if ($countCompare !== 0) {
                return $countCompare;
            }

            return strcmp((string) ($b['last_used_at'] ?? ''), (string) ($a['last_used_at'] ?? ''));
        });

        return array_slice($items, 0, $limit);
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
