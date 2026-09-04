<?php

namespace App\Controllers\Main\Settings;

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
        include PROJECT_ROOT . '/app/views/main/settings/organization/role_permissions.php';
    }

    public function apiList()
    {
        header('Content-Type: application/json; charset=utf-8');

        $roleId = trim((string) ($_POST['role_id'] ?? ''));
        $mode = trim((string) ($_POST['mode'] ?? 'selection'));

        if ($mode !== 'master' && $roleId === '') {
            echo json_encode(['success' => false, 'message' => '역할 ID가 필요합니다.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        try {
            $rows = $mode === 'master'
                ? $this->service->getPermissionTreeForRole()
                : $this->service->getPermissionSelectionForRole($roleId);
        } catch (\InvalidArgumentException $exception) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => $rows,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function apiSave(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $input = json_decode(file_get_contents('php://input') ?: '[]', true);
            $input = is_array($input) ? $input : [];
            $roleId = trim((string) ($input['role_id'] ?? ''));
            $permissionIds = $input['permission_ids'] ?? [];
            if (!is_array($permissionIds)) {
                throw new \InvalidArgumentException('저장할 권한 목록이 올바르지 않습니다.');
            }
            $result = $this->service->saveRolePermissions($roleId, $permissionIds);
            echo json_encode([
                'success' => true,
                'data' => $result,
                'message' => '권한이 저장되었습니다.',
            ], JSON_UNESCAPED_UNICODE);
        } catch (\InvalidArgumentException $exception) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $exception) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => '권한 저장 중 오류가 발생했습니다.'], JSON_UNESCAPED_UNICODE);
        }
    }

    public function apiReorder()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $changes = $input['changes'] ?? [];

            if (!is_array($changes) || $changes === []) {
                throw new \InvalidArgumentException('변경할 권한 순서가 없습니다.');
            }

            $this->service->reorderPermissions($changes);

            echo json_encode([
                'success' => true,
                'message' => '권한 순서가 저장되었습니다.',
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e instanceof \InvalidArgumentException
                    ? $e->getMessage()
                    : '정렬 저장 중 오류가 발생했습니다.',
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
