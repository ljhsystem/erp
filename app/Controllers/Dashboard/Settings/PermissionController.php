<?php
// 경로: PROJECT_ROOT/app/Controllers/Dashboard/Settings/PermissionController.php
// 대시보드>설정>조직관리>권한 API 컨트롤러
namespace App\Controllers\Dashboard\Settings;

use Core\Session;
use Core\DbPdo;
use App\Services\Auth\PermissionService;

class PermissionController
{
    private PermissionService $service;

    public function __construct()
    {
        Session::requireAuth();
        $this->service = new PermissionService(DbPdo::conn());
    }

    // ============================================================
    // WEB: 권한(Permission) 관리 화면
    // URL: GET /dashboard/settings/permission
    // permission: settings.permission.view
    // controller: PermissionController@webIndex
    // ============================================================
    public function webIndex()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/employee/permissions.php';
    }

    // ============================================================
    // API: 권한 목록 조회
    // URL: POST /api/settings/permission/list
    // permission: settings.permission.list
    // controller: PermissionController@apiList
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
    // API: 권한 저장(create/update/delete)
    // URL: POST /api/settings/permission/save
    // permission: settings.permission.save
    // controller: PermissionController@apiSave
    // ============================================================
    public function apiSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        $action = $_POST['action'] ?? '';
        $id     = $_POST['id'] ?? '';

        switch ($action) {

            case 'create':
                $result = $this->createPermission();
                break;

            case 'update':
                $result = $this->updatePermission($id);
                break;

            case 'delete':
                $result = $this->deletePermission($id);
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
    // 내부 헬퍼: 권한 생성
    // ============================================================
    private function createPermission(): array
    {
        $data = [
            'permission_key'  => trim($_POST['permission_key'] ?? ''),
            'permission_name' => trim($_POST['permission_name'] ?? ''),
            'description'     => trim($_POST['description'] ?? '') ?: null,
            'category'        => trim($_POST['category'] ?? '') ?: null,
            'is_active'       => (int)($_POST['is_active'] ?? 1),
            'created_by'      => $_SESSION['user']['id'] ?? null
        ];

        if (!$data['permission_key'] || !$data['permission_name']) {
            return ['success' => false, 'message' => 'required'];
        }

        $result = $this->service->create($data);

        if (!$result['success']) {
            // Model은 duplicate 라고 보내므로 JS가 기대하는 duplicate_key 로 변경
            if ($result['message'] === 'duplicate') {
                return ['success' => false, 'message' => 'duplicate_key'];
            }
            return ['success' => false, 'message' => 'fail'];
        }

        return ['success' => true, 'message' => 'created'];
    }


    // ============================================================
    // 내부 헬퍼: 권한 수정
    // ============================================================
    private function updatePermission(string $id): array
    {
        if (!$id) {
            return ['success' => false, 'message' => 'invalid_id'];
        }

        $data = [
            'permission_key'  => trim($_POST['permission_key'] ?? ''),
            'permission_name' => trim($_POST['permission_name'] ?? ''),
            'description'     => trim($_POST['description'] ?? '') ?: null,
            'category'        => trim($_POST['category'] ?? '') ?: null,
            'is_active'       => (int)($_POST['is_active'] ?? 1),
            'updated_by'      => $_SESSION['user']['id'] ?? null
        ];

        $result = $this->service->update($id, $data);

        if (!$result['success']) {
            if ($result['message'] === 'duplicate') {
                return ['success' => false, 'message' => 'duplicate_key'];
            }
            return ['success' => false, 'message' => 'fail'];
        }

        return ['success' => true, 'message' => 'updated'];
    }

    // ============================================================
    // 내부 헬퍼: 권한 삭제
    // ============================================================
    private function deletePermission(string $id): array
    {
        if (!$id) {
            return ['success' => false, 'message' => 'invalid_id'];
        }

        $result = $this->service->delete($id);

        return [
            'success' => $result['success'],
            'message' => $result['success'] ? 'deleted' : 'fail'
        ];
    }
}
