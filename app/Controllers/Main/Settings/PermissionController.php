<?php
namespace App\Controllers\Main\Settings;

use App\Services\Auth\PermissionService;
use Core\DbPdo;

class PermissionController
{
    private PermissionService $service;

    public function __construct()
    {
        $this->service = new PermissionService(DbPdo::conn());
    }

    public function apiList()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            echo json_encode([
                'success' => true,
                'data' => $this->service->getAll($this->readFilters()),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '목록 조회 중 오류가 발생했습니다.',
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiDelete()
    {
        header('Content-Type: application/json; charset=utf-8');

        $id = trim((string) ($_POST['id'] ?? ''));
        if ($id === '') {
            echo json_encode([
                'success' => false,
                'message' => '권한 ID가 필요합니다.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode($this->service->delete($id), JSON_UNESCAPED_UNICODE);
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
