<?php

namespace App\Controllers\Ledger;

use App\Controllers\System\LayoutController;
use App\Services\Ledger\OpeningBalanceService;
use Core\DbPdo;
use InvalidArgumentException;
use PDO;
use Throwable;

class OpeningBalanceController
{
    private PDO $pdo;
    private LayoutController $layout;
    private OpeningBalanceService $service;

    public function __construct()
    {
        $this->pdo = DbPdo::conn();
        $this->layout = new LayoutController($this->pdo);
        $this->service = new OpeningBalanceService($this->pdo);
    }

    public function index(): void
    {
        ob_start();
        require PROJECT_ROOT . '/app/views/ledger/opening-balances/index.php';
        $content = ob_get_clean();
        $this->layout->render([
            'pageTitle' => $pageTitle ?? '기초금액',
            'content' => $content,
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
            'layoutOptions' => $layoutOptions ?? [],
        ]);
    }

    public function apiList(): void
    {
        $this->respond(fn(): array => [
            'success' => true,
            'data' => $this->service->getList([
                'company_id' => trim((string) ($_GET['company_id'] ?? '')),
                'fiscal_year' => trim((string) ($_GET['fiscal_year'] ?? '')),
            ]),
        ], '조회 중 오류가 발생했습니다.');
    }

    public function apiDetail(): void
    {
        $this->respond(function (): array {
            $row = $this->service->getDetail(trim((string) ($_GET['id'] ?? '')));
            if (!$row) throw new InvalidArgumentException('기초금액 문서를 찾을 수 없습니다.');
            return ['success' => true, 'data' => $row];
        }, '조회 중 오류가 발생했습니다.');
    }

    public function apiOptions(): void
    {
        $this->respond(fn(): array => ['success' => true, 'data' => $this->service->options()], '조회 중 오류가 발생했습니다.');
    }

    public function apiSave(): void
    {
        $this->respond(fn(): array => $this->service->save($this->input()), '저장 중 오류가 발생했습니다.');
    }

    public function apiDelete(): void
    {
        $this->respond(fn(): array => $this->service->delete(trim((string) ($this->input()['id'] ?? ''))), '삭제 중 오류가 발생했습니다.');
    }

    public function apiRequestReview(): void { $this->transition('request-review'); }
    public function apiCancelReview(): void { $this->transition('cancel-review'); }
    public function apiReview(): void { $this->transition('review'); }
    public function apiCancelReviewed(): void { $this->transition('cancel-reviewed'); }
    public function apiPost(): void { $this->transition('post'); }

    public function apiReverse(): void
    {
        $this->respond(fn(): array => $this->service->reverse(trim((string) ($this->input()['id'] ?? ''))), '취소전표 생성 중 오류가 발생했습니다.');
    }

    private function transition(string $action): void
    {
        $this->respond(fn(): array => $this->service->transition(trim((string) ($this->input()['id'] ?? '')), $action), '상태 변경 중 오류가 발생했습니다.');
    }

    private function input(): array
    {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode(file_get_contents('php://input') ?: '{}', true);
            return is_array($decoded) ? $decoded : [];
        }
        return $_POST;
    }

    private function respond(callable $callback, string $fallback): void
    {
        try {
            $this->json($callback());
        } catch (InvalidArgumentException $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable) {
            $this->json(['success' => false, 'message' => $fallback], 500);
        }
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
