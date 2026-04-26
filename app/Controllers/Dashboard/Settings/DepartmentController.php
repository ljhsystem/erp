<?php
namespace App\Controllers\Dashboard\Settings;

use Core\DbPdo;
use App\Services\User\DepartmentService;

class DepartmentController
{
    private DepartmentService $service;

    public function __construct()
    {
        $this->service = new DepartmentService(DbPdo::conn());
    }

    public function webIndex()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/organization/departments.php';
    }

    public function apiList()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $filters = $this->readFilters();
            $rows = $this->service->getAll($filters);

            echo json_encode([
                'success' => true,
                'data'    => $rows
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
            $row = $this->service->getById($id);

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
                    $result = $this->handleCreate();
                    break;

                case 'update':
                    $result = $this->handleUpdate($id);
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
        echo json_encode($this->handleDelete($id), JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function handleCreate(): array
    {
        $deptName = trim($_POST['dept_name'] ?? '');
        $managerId = $_POST['manager_id'] ?? null;
        $description = trim($_POST['description'] ?? '');
        $isActive = (int)($_POST['is_active'] ?? 1);

        if (!$deptName) {
            return ['success' => false, 'message' => 'required'];
        }

        $ok = $this->service->create([
            'dept_name'   => $deptName,
            'manager_id'  => $managerId ?: null,
            'description' => $description,
            'is_active'   => $isActive,
        ]);

        if ($ok === 'duplicate') {
            return ['success' => false, 'message' => 'duplicate'];
        }

        return [
            'success' => (bool)$ok,
            'message' => $ok ? 'created' : 'error'
        ];
    }

    private function handleUpdate(string $id): array
    {
        if (!$id) {
            return ['success' => false, 'message' => 'id required'];
        }

        $managerId = $_POST['manager_id'] ?? null;
        if ($managerId === '' || $managerId === 'undefined') {
            $managerId = null;
        }

        $ok = $this->service->update($id, [
            'dept_name'   => trim($_POST['dept_name'] ?? ''),
            'manager_id'  => $managerId,
            'description' => trim($_POST['description'] ?? ''),
            'is_active'   => (int)($_POST['is_active'] ?? 1),
        ]);

        if ($ok === 'duplicate') {
            return ['success' => false, 'message' => 'duplicate'];
        }

        return [
            'success' => (bool)$ok,
            'message' => $ok ? 'updated' : 'error'
        ];
    }

    private function handleDelete(string $id): array
    {
        if (!$id) {
            return ['success' => false, 'message' => 'id required'];
        }

        $ok = $this->service->delete($id);

        return [
            'success' => (bool)$ok,
            'message' => $ok ? 'deleted' : 'error'
        ];
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
