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

        $rows = $this->service->getPermissionsForRole($roleId);

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
}
