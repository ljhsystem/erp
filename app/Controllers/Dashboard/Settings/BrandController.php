<?php

namespace App\Controllers\Dashboard\Settings;

use App\Services\System\BrandService;
use Core\DbPdo;
use Core\Session;

class BrandController
{
    private BrandService $service;

    public function __construct()
    {
        Session::requireAuth();
        $this->service = new BrandService(DbPdo::conn());
    }

    public function apiList()
    {
        header('Content-Type: application/json; charset=utf-8');

        $filters = [];
        if (!empty($_POST['asset_type'])) {
            $filters['asset_type'] = (string) $_POST['asset_type'];
        }

        if (isset($_POST['is_active'])) {
            $filters['is_active'] = (int) $_POST['is_active'];
        }

        echo json_encode([
            'success' => true,
            'data' => $this->service->getList($filters),
        ], JSON_UNESCAPED_UNICODE);
    }

    public function apiDetail()
    {
        header('Content-Type: application/json; charset=utf-8');

        $id = trim((string) ($_POST['id'] ?? ''));
        if ($id === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => '파일 ID가 필요합니다.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => $this->service->getById($id),
        ], JSON_UNESCAPED_UNICODE);
    }

    public function apiActiveType(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $assetType = trim((string) ($_POST['asset_type'] ?? ''));
        if ($assetType === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'asset_type 값이 필요합니다.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => $this->service->getActive($assetType),
        ], JSON_UNESCAPED_UNICODE);
    }

    public function apiSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        $userId = $_SESSION['user']['id'] ?? null;
        if (!$userId) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => '인증 정보가 없습니다.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $assetType = trim((string) ($_POST['asset_type'] ?? ''));
        $file = $_FILES['file'] ?? null;

        if ($assetType === '' || !$file) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => '자산 구분과 업로드 파일이 필요합니다.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $result = $this->service->save($assetType, $file);
        http_response_code(!empty($result['success']) ? 200 : 400);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    public function apiPurge()
    {
        header('Content-Type: application/json; charset=utf-8');

        $fileId = trim((string) ($_POST['file_id'] ?? ''));
        if ($fileId === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => '파일 ID가 필요합니다.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $result = $this->service->purge($fileId);
        http_response_code(!empty($result['success']) ? 200 : 400);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    public function apiUpdateStatus()
    {
        header('Content-Type: application/json; charset=utf-8');

        $id = trim((string) ($_POST['id'] ?? ''));
        $status = isset($_POST['status']) ? (int) $_POST['status'] : null;

        if ($id === '' || $status === null) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => '필수값이 누락되었습니다.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $result = $status === 1
            ? $this->service->activate($id)
            : $this->service->deactivate($id);

        http_response_code(!empty($result['success']) ? 200 : 400);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }
}
