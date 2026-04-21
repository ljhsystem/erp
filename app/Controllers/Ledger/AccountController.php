<?php

namespace App\Controllers\Ledger;

use App\Services\Ledger\ChartAccountService;
use Core\DbPdo;

class AccountController
{
    private ChartAccountService $service;

    public function __construct()
    {
        $this->service = new ChartAccountService(DbPdo::conn());
    }

    public function apiSaveFromExcel(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            if (!isset($_FILES['excel']) || !is_uploaded_file($_FILES['excel']['tmp_name'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '파일이 업로드되지 않았습니다.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $file = $_FILES['excel']['tmp_name'];
            $result = $this->service->saveFromExcelFile($file);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '엑셀 업로드에 실패했습니다.',
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }
}
