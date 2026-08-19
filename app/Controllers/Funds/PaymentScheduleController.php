<?php

namespace App\Controllers\Funds;

use App\Controllers\System\LayoutController;
use App\Services\Funds\PaymentScheduleService;
use Core\DbPdo;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PDO;

class PaymentScheduleController
{
    private PaymentScheduleService $service;
    private LayoutController $layout;

    public function __construct(?PDO $pdo = null)
    {
        $pdo = $pdo ?? DbPdo::conn();
        $this->service = new PaymentScheduleService($pdo);
        $this->layout = new LayoutController($pdo);
    }

    public function index(): void
    {
        $pageTitle = '지급예정현황';
        $filterOptions = $this->service->options();
        ob_start();
        include PROJECT_ROOT . '/app/views/funds/payment-schedule/index.php';
        $content = ob_get_clean();
        $this->layout->render(compact('pageTitle', 'content') + [
            'layoutOptions' => $layoutOptions ?? [],
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
        ]);
    }

    public function apiList(): void
    {
        $input = \Core\Helpers\DataTableRequestHelper::input();
        try {
            $this->json($this->service->list($input));
        } catch (\Throwable) {
            http_response_code(500);
            $this->json([
                'draw' => (int) ($input['draw'] ?? 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'summary' => [],
                'error' => '조회 중 오류가 발생했습니다.',
            ]);
        }
    }

    public function apiDetail(): void
    {
        $this->respond(fn() => $this->service->detail(trim((string) ($_GET['id'] ?? ''))));
    }

    public function apiSave(): void
    {
        $this->respond(function (): array {
            $input = $this->input();
            $id = trim((string) ($input['id'] ?? ''));
            if ($id === '') {
                throw new \InvalidArgumentException('지급의무는 전표 승인 시 자동 생성됩니다.');
            }
            return $this->service->update($id, $input);
        }, '저장 중 오류가 발생했습니다.');
    }

    public function apiHold(): void
    {
        $this->respond(function (): array {
            $input = $this->input();
            return $this->service->hold(
                trim((string) ($input['id'] ?? '')),
                trim((string) ($input['reason'] ?? ''))
            );
        }, '수정 중 오류가 발생했습니다.');
    }

    public function apiReleaseHold(): void
    {
        $this->respond(function (): array {
            $input = $this->input();
            return $this->service->releaseHold(
                trim((string) ($input['id'] ?? '')),
                trim((string) ($input['reason'] ?? ''))
            );
        }, '수정 중 오류가 발생했습니다.');
    }

    public function apiDelete(): void
    {
        $this->respond(function (): array {
            $this->service->delete(trim((string) ($this->input()['id'] ?? '')));
            return [];
        }, '삭제 중 오류가 발생했습니다.');
    }

    public function apiTrashList(): void
    {
        $request = $_GET;
        $request['deleted_scope'] = 'DELETED';
        $this->json($this->service->list($request));
    }

    public function apiRestore(): void
    {
        $this->respond(
            fn(): array => $this->service->restore(trim((string) ($this->input()['id'] ?? ''))),
            '복구 중 오류가 발생했습니다.'
        );
    }

    public function apiBankWithdrawals(): void
    {
        $this->respond(fn(): array => $this->service->bankWithdrawals($_GET));
    }

    public function apiAllocate(): void
    {
        $this->respond(function (): array {
            $input = $this->input();
            return $this->service->allocateBankWithdrawal(
                trim((string) ($input['schedule_id'] ?? '')),
                trim((string) ($input['evidence_id'] ?? '')),
                $input['amount'] ?? 0,
                trim((string) ($input['memo'] ?? ''))
            );
        }, '지급 연결 중 오류가 발생했습니다.');
    }

    public function apiReleaseAllocation(): void
    {
        $this->respond(function (): array {
            $input = $this->input();
            return $this->service->releaseAllocation(
                trim((string) ($input['schedule_id'] ?? '')),
                trim((string) ($input['link_id'] ?? '')),
                trim((string) ($input['reason'] ?? ''))
            );
        }, '지급 연결 해제 중 오류가 발생했습니다.');
    }

    public function apiExcel(): void
    {
        $book = $this->service->spreadsheet($_GET);
        $filename = '지급예정현황_' . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="payment-schedule.xlsx"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('Cache-Control: max-age=0');
        (new Xlsx($book))->save('php://output');
        $book->disconnectWorksheets();
        exit;
    }

    private function input(): array
    {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            return is_array($decoded) ? $decoded : [];
        }
        return $_POST;
    }

    private function respond(callable $callback, string $fallback = '조회 중 오류가 발생했습니다.'): void
    {
        try {
            $this->json(['success' => true, 'data' => $callback(), 'message' => '']);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            http_response_code(422);
            $this->json(['success' => false, 'data' => null, 'message' => $e->getMessage()]);
        } catch (\Throwable) {
            http_response_code(500);
            $this->json(['success' => false, 'data' => null, 'message' => $fallback]);
        }
    }

    private function json(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
