<?php
// 경로: PROJECT_ROOT/app/controllers/dashboard/settings/RolePermissionSettingsController.php
// 대시보드>설정>조직관리>권한부여 API 컨트롤러
namespace App\Controllers\Dashboard\Settings;

use Core\Session;
use Core\DbPdo;
use App\Services\Auth\RolePermissionService;

class RolePermissionSettingsController
{
    private RolePermissionService $service;

    public function __construct()
    {
        Session::requireAuth();
        $this->service = new RolePermissionService(DbPdo::conn());
    }

    // ============================================================
    // WEB: 역할별 권한 관리 화면
    // URL: GET /dashboard/settings/role-permission
    // permission: settings.rolepermission.view
    // controller: RolePermissionSettingsController@webIndex
    // ============================================================
    public function webIndex()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/employee/role-permissions.php';
    }

    // ============================================================
    // API: 특정 역할의 권한 목록 조회
    // URL: POST /api/settings/role-permission/list
    // permission: settings.rolepermission.list
    // controller: RolePermissionSettingsController@apiList
    // ============================================================
    public function apiList()
    {
        header('Content-Type: application/json; charset=utf-8');

        $roleId = $_POST['role_id'] ?? '';

        if (!$roleId) {
            echo json_encode(['success' => false, 'message' => 'role_id required'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $rows = $this->service->getPermissionsForRole($roleId);

        echo json_encode([
            'success' => true,
            'data'    => $rows
        ], JSON_UNESCAPED_UNICODE);
    }

    // ============================================================
    // API: 권한 추가
    // URL: POST /api/settings/role-permission/assign
    // permission: settings.rolepermission.assign
    // controller: RolePermissionSettingsController@apiAssign
    // ============================================================
    public function apiAssign()
    {
        header('Content-Type: application/json; charset=utf-8');

        $roleId       = $_POST['role_id'] ?? '';
        $permissionId = $_POST['permission_id'] ?? '';
        $userId       = $_SESSION['user']['id'] ?? null;

        if (!$roleId || !$permissionId) {
            echo json_encode(['success' => false], JSON_UNESCAPED_UNICODE);
            return;
        }

        $ok = $this->service->assign($roleId, $permissionId, $userId);

        echo json_encode(['success' => (bool)$ok], JSON_UNESCAPED_UNICODE);
    }

    // ============================================================
    // API: 권한 제거
    // URL: POST /api/settings/role-permission/remove
    // permission: settings.rolepermission.remove
    // controller: RolePermissionSettingsController@apiRemove
    // ============================================================
    public function apiRemove()
    {
        header('Content-Type: application/json; charset=utf-8');

        $roleId       = $_POST['role_id'] ?? '';
        $permissionId = $_POST['permission_id'] ?? '';
        $userId       = $_SESSION['user']['id'] ?? null;

        if (!$roleId || !$permissionId) {
            echo json_encode(['success' => false], JSON_UNESCAPED_UNICODE);
            return;
        }

        $ok = $this->service->remove($roleId, $permissionId);

        echo json_encode(['success' => (bool)$ok], JSON_UNESCAPED_UNICODE);
    }
}
