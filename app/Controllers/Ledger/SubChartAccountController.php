<?php

namespace App\Controllers\Ledger;

use App\Services\Ledger\CustomSubAccountService;
use Core\DbPdo;
use Core\Session;

class SubChartAccountController
{
    private CustomSubAccountService $service;

    public function __construct()
    {
        $pdo = DbPdo::conn();
        $this->service = new CustomSubAccountService($pdo);
    }

    public function apiList(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        Session::write();

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
            echo json_encode([
                'success' => true,
                'data' => $rows,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '조회 중 오류가 발생했습니다.',
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
            ];

            echo json_encode(
                $this->service->create($payload),
                JSON_UNESCAPED_UNICODE
            );
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '저장 중 오류가 발생했습니다.',
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiUpdate(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $id = $_POST['id'] ?? null;
            $accountId = trim((string) ($_POST['account_id'] ?? ''));
            if (!$id || $accountId === '') {
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
            ];

            echo json_encode(
                $this->service->update($accountId, $id, $payload),
                JSON_UNESCAPED_UNICODE
            );
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '수정 중 오류가 발생했습니다.',
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiDelete(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $id = $_POST['id'] ?? null;
            $accountId = trim((string) ($_POST['account_id'] ?? ''));
            if (!$id || $accountId === '') {
                echo json_encode([
                    'success' => false,
                    'message' => 'id가 없습니다.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            echo json_encode(
                $this->service->delete($accountId, $id),
                JSON_UNESCAPED_UNICODE
            );
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '삭제 중 오류가 발생했습니다.',
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

}
