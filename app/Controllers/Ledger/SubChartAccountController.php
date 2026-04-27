<?php

namespace App\Controllers\Ledger;

use App\Services\Ledger\CustomSubAccountService;
use Core\DbPdo;

class SubChartAccountController
{
    private CustomSubAccountService $service;

    public function __construct()
    {
        $this->service = new CustomSubAccountService(DbPdo::conn());
    }

    public function apiList(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $accountId = $_GET['account_id'] ?? null;

            if (!$accountId) {
                echo json_encode([
                    'success' => false,
                    'message' => 'account_id가 없습니다.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $rows = $this->service->getByAccountId($accountId);
            error_log('[SubChartAccountController] apiList data count=' . count($rows) . ' account_id=' . $accountId);

            echo json_encode([
                'success' => true,
                'data' => $rows,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiSave(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $payload = [
                'account_id' => $_POST['account_id'] ?? null,
                'sub_code' => $_POST['sub_code'] ?? null,
                'sub_name' => $_POST['sub_name'] ?? null,
                'is_required' => isset($_POST['is_required']) ? (int) $_POST['is_required'] : 0,
                'note' => $_POST['note'] ?? null,
                'memo' => $_POST['memo'] ?? null,
            ];

            echo json_encode(
                $this->service->create($payload),
                JSON_UNESCAPED_UNICODE
            );
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiUpdate(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $id = $_POST['id'] ?? null;
            if (!$id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'id가 없습니다.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $payload = [
                'sub_code' => $_POST['sub_code'] ?? null,
                'sub_name' => $_POST['sub_name'] ?? null,
                'is_required' => isset($_POST['is_required']) ? (int) $_POST['is_required'] : 0,
                'note' => $_POST['note'] ?? null,
                'memo' => $_POST['memo'] ?? null,
            ];

            echo json_encode(
                $this->service->update($id, $payload),
                JSON_UNESCAPED_UNICODE
            );
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiDelete(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $id = $_POST['id'] ?? null;
            if (!$id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'id가 없습니다.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            echo json_encode(
                $this->service->delete($id),
                JSON_UNESCAPED_UNICODE
            );
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }
}
