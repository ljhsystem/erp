<?php
// 경로: PROJECT_ROOT . '/app/Controllers/Dashboard/Settings/BankAccountController.php'

namespace App\Controllers\Dashboard\Settings;

use Core\Session;
use Core\DbPdo;
use App\Services\System\BankAccountService;

class BankAccountController
{
    private BankAccountService $service;

    public function __construct()
    {
        Session::requireAuth();
        $this->service = new BankAccountService(DbPdo::conn());
    }

    /* ============================================================
     API: 계좌 목록
     ============================================================ */
    public function apiList(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            $filters = [];

            if (!empty($_GET['filters'])) {
                $decoded = json_decode($_GET['filters'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $filters = $decoded;
                }
            }

            $rows = $this->service->getList($filters);

            echo json_encode([
                'success' => true,
                'data' => $rows
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '계좌 목록 조회 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    /* ============================================================
     API: 계좌 상세
     ============================================================ */
    public function apiDetail(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_GET['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '계좌 ID 누락'
            ]);
            exit;
        }

        try {

            $row = $this->service->getById($id);

            echo json_encode([
                'success' => true,
                'data' => $row
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '계좌 조회 실패',
                'error' => $e->getMessage()
            ]);
        }

        exit;
    }

    /* ============================================================
     API: 계좌 저장
     ============================================================ */
    public function apiSave(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            $payload = [

                'id' => $_POST['id'] ?? null,
                'code' => $_POST['code'] ?? null,

                'alias' => trim($_POST['alias'] ?? ''),
                'account_name' => $_POST['account_name'] ?? null,
                'bank_name' => $_POST['bank_name'] ?? null,
                'account_number' => $_POST['account_number'] ?? null,
                'account_holder' => $_POST['account_holder'] ?? null,
                'currency' => $_POST['currency'] ?? 'KRW',

                'delete_bank_book_file' => $_POST['delete_bank_book_file'] ?? '0',

                'is_active' => 1
            ];

            if ($payload['alias'] === '') {
                echo json_encode([
                    'success' => false,
                    'message' => '별칭은 필수입니다.'
                ]);
                exit;
            }

            $result = $this->service->save(
                $payload,
                'USER',
                $_FILES
            );

            echo json_encode($result);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '계좌 저장 실패',
                'error' => $e->getMessage()
            ]);
        }

        exit;
    }

    /* ============================================================
     API: 삭제
     ============================================================ */
    public function apiDelete(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => 'ID 누락'
            ]);
            exit;
        }

        $result = $this->service->delete($id, 'USER');

        echo json_encode($result);
        exit;
    }

    /* ============================================================
     API: 휴지통
     ============================================================ */
    public function apiTrashList(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $rows = $this->service->getTrashList();

        echo json_encode([
            'success' => true,
            'data' => $rows
        ]);

        exit;
    }

    public function apiRestore(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        $result = $this->service->restore($id, 'USER');

        echo json_encode($result);
        exit;
    }

    public function apiPurge(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        $result = $this->service->purge($id, 'USER');

        echo json_encode($result);
        exit;
    }

    /* ============================================================
     API: 정렬
     ============================================================ */
    public function apiReorder(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $changes = json_decode(file_get_contents('php://input'), true)['changes'] ?? [];

        $this->service->reorder($changes);

        echo json_encode(['success' => true]);
        exit;
    }
}