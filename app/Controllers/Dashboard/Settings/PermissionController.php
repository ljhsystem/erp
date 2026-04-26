<?php
namespace App\Controllers\Dashboard\Settings;

use Core\DbPdo;
use App\Services\Auth\PermissionService;

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
