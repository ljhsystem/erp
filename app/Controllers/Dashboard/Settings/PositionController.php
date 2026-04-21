<?php
// 경로: PROJECT_ROOT/app/Controllers/Dashboard/Settings/PositionController.php
// 대시보드>설정>조직관리>직책 API 컨트롤러
namespace App\Controllers\Dashboard\Settings;

use Core\DbPdo;
use App\Services\User\PositionService;

class PositionController
{
    private PositionService $service;

    public function __construct()
    {
        $this->service = new PositionService(DbPdo::conn());
    }

    // ============================================================
    // WEB: 직책 관리 화면
    // URL: GET /dashboard/settings/position
    // permission: settings.position.view
    // controller: PositionController@webIndex
    // ============================================================
    public function webIndex()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/organization/positions.php';
    }

    // ============================================================
    // API: 직책 목록
    // URL: POST /api/settings/position/list
    // permission: settings.position.list
    // controller: PositionController@apiList
    // ============================================================
    public function apiList()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $filters = [];
            $rawFilters = $_GET['filters'] ?? $_POST['filters'] ?? '';

            if ($rawFilters !== '') {
                $decoded = json_decode($rawFilters, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $filters = $decoded;
                }
            }

            echo json_encode([
                'success' => true,
                'data'    => $this->service->getAll($filters)
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '직책 목록 조회 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    // ============================================================
    // API: 직책 저장 (create / update / delete)
    // URL: POST /api/settings/position/save
    // permission: settings.position.save
    // controller: PositionController@apiSave
    // ============================================================
    public function apiSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        $action = $_POST['action'] ?? '';
        $id     = $_POST['id'] ?? '';

        switch ($action) {

            case 'create':
                $result = $this->createPosition();
                break;

            case 'update':
                $result = $this->updatePosition($id);
                break;

            case 'delete':
                $result = $this->deletePosition($id);
                break;

            default:
                $result = [
                    'success' => false,
                    'message' => 'invalid_action'
                ];
                break;
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ============================================================
    // 내부 헬퍼: 직책 생성
    // ============================================================
    private function createPosition(): array
    {
        $data = [
            'position_name' => trim($_POST['position_name'] ?? ''),
            'level_rank'    => (int)($_POST['level_rank'] ?? 0),
            'description'   => $_POST['description'] ?? null,
            'is_active'     => (int)($_POST['is_active'] ?? 1),
        ];

        // Service에서 success, message 통일 반환
        return $this->service->create($data);
    }

    // ============================================================
    // 내부 헬퍼: 직책 수정
    // ============================================================
    private function updatePosition(string $id): array
    {
        if (!$id) {
            return ['success' => false, 'message' => 'invalid_id'];
        }

        $data = [
            'position_name' => trim($_POST['position_name'] ?? ''),
            'level_rank'    => (int)($_POST['level_rank'] ?? 0),
            'description'   => $_POST['description'] ?? null,
            'is_active'     => (int)($_POST['is_active'] ?? 1),
        ];

        return $this->service->update($id, $data);
    }

    // ============================================================
    // 내부 헬퍼: 직책 삭제
    // ============================================================
    private function deletePosition(string $id): array
    {
        if (!$id) {
            return ['success' => false, 'message' => 'invalid_id'];
        }

        return $this->service->delete($id);
    }
    public function apiReorder()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $changes = json_decode(file_get_contents('php://input'), true)['changes'] ?? [];

        if (!$changes) {
            echo json_encode(['success' => false, 'message' => '변경 데이터 없음']);
            return;
        }

        $this->service->reorder($changes);

        echo json_encode(['success' => true]);
    }
}
