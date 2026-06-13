<?php

namespace App\Controllers\Dashboard\Settings;

use App\Services\Auth\RolePermissionService;
use Core\DbPdo;

class RolePermissionController
{
    private RolePermissionService $service;

    public function __construct()
    {
        $this->service = new RolePermissionService(DbPdo::conn());
    }

    public function webIndex()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/organization/role_permissions.php';
    }

    public function apiList()
    {
        header('Content-Type: application/json; charset=utf-8');

        $roleId = $_POST['role_id'] ?? '';

        if (!$roleId) {
            echo json_encode(['success' => false, 'message' => 'role_id required'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $rows = $this->service->getPermissionTreeForRole($roleId);

        echo json_encode([
            'success' => true,
            'data' => $rows,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function apiAssign()
    {
        header('Content-Type: application/json; charset=utf-8');

        $roleId = $_POST['role_id'] ?? '';
        $permissionId = $_POST['permission_id'] ?? '';

        if (!$roleId || !$permissionId) {
            echo json_encode(['success' => false], JSON_UNESCAPED_UNICODE);
            return;
        }

        $ok = $this->service->assign($roleId, $permissionId);

        echo json_encode(['success' => (bool) $ok], JSON_UNESCAPED_UNICODE);
    }

    public function apiRemove()
    {
        header('Content-Type: application/json; charset=utf-8');

        $roleId = $_POST['role_id'] ?? '';
        $permissionId = $_POST['permission_id'] ?? '';

        if (!$roleId || !$permissionId) {
            echo json_encode(['success' => false], JSON_UNESCAPED_UNICODE);
            return;
        }

        $ok = $this->service->remove($roleId, $permissionId);

        echo json_encode(['success' => (bool) $ok], JSON_UNESCAPED_UNICODE);
    }

    public function apiReorder()
    {
        header('Content-Type: application/json; charset=utf-8');
        error_log('[RolePermissionController] apiReorder entered');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $changes = $input['changes'] ?? [];

            if (!is_array($changes) || $changes === []) {
                throw new \InvalidArgumentException('변경할 권한 순서가 없습니다.');
            }

            $this->service->reorderPermissions($changes);

            echo json_encode([
                'success' => true,
                'message' => '순서가 저장되었습니다.',
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
