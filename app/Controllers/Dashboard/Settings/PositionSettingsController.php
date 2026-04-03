<?php
// 경로: PROJECT_ROOT/app/controllers/dashboard/settings/PositionSettingsController.php
// 대시보드>설정>조직관리>직책 API 컨트롤러
namespace App\Controllers\Dashboard\Settings;

use Core\Session;
use Core\DbPdo;
use App\Services\User\PositionService;

class PositionSettingsController
{
    private PositionService $service;

    public function __construct()
    {
        Session::requireAuth();
        $this->service = new PositionService(DbPdo::conn());
    }

    // ============================================================
    // WEB: 직책 관리 화면
    // URL: GET /dashboard/settings/position
    // permission: settings.position.view
    // controller: PositionSettingsController@webIndex
    // ============================================================
    public function webIndex()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/employee/positions.php';
    }

    // ============================================================
    // API: 직책 목록
    // URL: POST /api/settings/position/list
    // permission: settings.position.list
    // controller: PositionSettingsController@apiList
    // ============================================================
    public function apiList()
    {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => true,
            'data'    => $this->service->getAll()
        ], JSON_UNESCAPED_UNICODE);
    }

    // ============================================================
    // API: 직책 저장 (create / update / delete)
    // URL: POST /api/settings/position/save
    // permission: settings.position.save
    // controller: PositionSettingsController@apiSave
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
            'created_by'    => $_SESSION['user']['id'] ?? null
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
            'updated_by'    => $_SESSION['user']['id'] ?? null
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
}