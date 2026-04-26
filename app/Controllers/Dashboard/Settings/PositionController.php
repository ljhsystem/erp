<?php
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

    public function webIndex()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/organization/positions.php';
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
            echo json_encode(['success' => false, 'message' => 'invalid_id'], JSON_UNESCAPED_UNICODE);
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
                'message' => 'detail_failed',
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

        switch ($action) {
            case 'create':
                $result = $this->createPosition();
                break;

            case 'update':
                $result = $this->updatePosition($id);
                break;

            default:
                $result = ['success' => false, 'message' => 'invalid_action'];
                break;
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiDelete()
    {
        header('Content-Type: application/json; charset=utf-8');

        $id = $_POST['id'] ?? '';
        echo json_encode($this->deletePosition($id), JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function createPosition(): array
    {
        return $this->service->create([
            'position_name' => trim($_POST['position_name'] ?? ''),
            'level_rank'    => (int)($_POST['level_rank'] ?? 0),
            'description'   => $_POST['description'] ?? null,
            'is_active'     => (int)($_POST['is_active'] ?? 1),
        ]);
    }

    private function updatePosition(string $id): array
    {
        if (!$id) {
            return ['success' => false, 'message' => 'invalid_id'];
        }

        return $this->service->update($id, [
            'position_name' => trim($_POST['position_name'] ?? ''),
            'level_rank'    => (int)($_POST['level_rank'] ?? 0),
            'description'   => $_POST['description'] ?? null,
            'is_active'     => (int)($_POST['is_active'] ?? 1),
        ]);
    }

    private function deletePosition(string $id): array
    {
        if (!$id) {
            return ['success' => false, 'message' => 'invalid_id'];
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
