<?php
namespace App\Controllers\Dashboard\Settings;

use Core\DbPdo;
use App\Services\Auth\RoleService;

class RoleController
{
    private RoleService $service;

    public function __construct()
    {
        $this->service = new RoleService(DbPdo::conn());
    }

    public function webIndex()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/organization/roles.php';
    }

    public function apiList()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            echo json_encode([
                'success' => true,
                'data'    => $this->service->getAll($this->readFilters())
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => 'list failed',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiDetail()
    {
        header('Content-Type: application/json; charset=utf-8');

        $id = $_GET['id'] ?? $_POST['id'] ?? '';

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'id required'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $row = null;
            foreach ($this->service->getAll([]) as $item) {
                if (($item['id'] ?? '') === $id) {
                    $row = $item;
                    break;
                }
            }

            echo json_encode([
                'success' => (bool)$row,
                'data'    => $row,
                'message' => $row ? null : 'not_found',
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => 'detail failed',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        $action = $_POST['action'] ?? '';
        $id = $_POST['id'] ?? '';

        try {
            switch ($action) {
                case 'create':
                    $result = $this->createRole();
                    break;

                case 'update':
                    $result = $this->updateRole($id);
                    break;

                default:
                    throw new \Exception('Invalid action');
            }

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => 'save failed',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiDelete()
    {
        header('Content-Type: application/json; charset=utf-8');

        $id = $_POST['id'] ?? '';
        echo json_encode($this->deleteRole($id), JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function createRole(): array
    {
        $roleKey = trim($_POST['role_key'] ?? '');
        $roleName = trim($_POST['role_name'] ?? '');

        if (!$roleKey || !$roleName) {
            return ['success' => false, 'message' => 'required'];
        }

        return $this->service->create([
            'role_key'    => $roleKey,
            'role_name'   => $roleName,
            'description' => trim($_POST['description'] ?? '') ?: null,
            'is_active'   => (int)($_POST['is_active'] ?? 1),
        ]);
    }

    private function updateRole(string $id): array
    {
        if (!$id) {
            return ['success' => false, 'message' => 'id required'];
        }

        return $this->service->update($id, [
            'role_key'    => trim($_POST['role_key'] ?? ''),
            'role_name'   => trim($_POST['role_name'] ?? ''),
            'description' => trim($_POST['description'] ?? '') ?: null,
            'is_active'   => (int)($_POST['is_active'] ?? 1),
        ]);
    }

    private function deleteRole(string $id): array
    {
        if (!$id) {
            return ['success' => false, 'message' => 'id required'];
        }

        return $this->service->delete($id);
    }


    public function apiReorder(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);
        $changes = $input['changes'] ?? [];

        try {
            $ok = $this->service->reorder($changes);
            echo json_encode([
                'success' => (bool)$ok,
                'message' => $ok ? 'reordered' : 'fail'
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => 'reorder failed',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    private function readFilters(): array
    {
        $filters = [];
        $rawFilters = $_GET['filters'] ?? $_POST['filters'] ?? '';

        if ($rawFilters !== '') {
            $decoded = json_decode($rawFilters, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $filters = $decoded;
            }
        }

        return $filters;
    }
}
