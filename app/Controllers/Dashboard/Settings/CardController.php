<?php
// 경로: PROJECT_ROOT . '/app/Controllers/Dashboard/Settings/CardController.php'

namespace App\Controllers\Dashboard\Settings;

use Core\Session;
use Core\DbPdo;
use App\Services\System\CardService;

class CardController
{
    private CardService $service;

    public function __construct()
    {
        Session::requireAuth();
        $this->service = new CardService(DbPdo::conn());
    }

    /* =========================================================
     * 카드 목록
     * ========================================================= */
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
                'message' => '카드 목록 조회 실패',
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    /* =========================================================
     * 카드 상세
     * ========================================================= */
    public function apiDetail(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_GET['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '카드 ID 누락'
            ]);
            exit;
        }

        $result = $this->service->getById($id);

        echo json_encode($result);
        exit;
    }

    /* =========================================================
     * 카드 저장
     * ========================================================= */
    public function apiSave(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            $payload = [
                'id' => $_POST['id'] ?? null,
                'code' => $_POST['code'] ?? null,

                'alias' => trim($_POST['alias'] ?? ''),
                'card_name' => $_POST['card_name'] ?? null,
                'card_number' => $_POST['card_number'] ?? null,
                'card_company' => $_POST['card_company'] ?? null,

                'owner_name' => $_POST['owner_name'] ?? null,
                'valid_thru' => $_POST['valid_thru'] ?? null,

                'is_active' => 1
            ];

            if ($payload['alias'] === '') {
                echo json_encode([
                    'success' => false,
                    'message' => '별칭은 필수입니다.'
                ]);
                exit;
            }

            $result = $this->service->save($payload, 'USER');

            echo json_encode($result);

        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '카드 저장 실패',
                'error' => $e->getMessage()
            ]);
        }

        exit;
    }

    /* =========================================================
     * 카드 삭제
     * ========================================================= */
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

    /* =========================================================
     * 카드 휴지통
     * ========================================================= */
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

        $result = $this->service->purge($id);

        echo json_encode($result);
        exit;
    }

    /* =========================================================
     * 순서 변경
     * ========================================================= */
    public function apiReorder(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $changes = json_decode(file_get_contents('php://input'), true)['changes'] ?? [];

        $this->service->reorder($changes);

        echo json_encode(['success' => true]);
        exit;
    }
}