<?php

namespace App\Controllers\Ledger;

use App\Services\Ledger\CustomSubAccountService;
use App\Services\Ledger\ChartAccountService;
use App\Services\Ledger\SubChartAccountExcelService;
use App\Models\Ledger\SubChartAccountModel;
use Core\DbPdo;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SubChartAccountController
{
    private CustomSubAccountService $service;
    private SubChartAccountExcelService $excelService;

    public function __construct()
    {
        $pdo = DbPdo::conn();
        $this->service = new CustomSubAccountService($pdo);
        $this->excelService = new SubChartAccountExcelService(
            $this->service,
            new ChartAccountService($pdo),
            new SubChartAccountModel($pdo)
        );
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

    public function apiTemplate(): void
    {
        $this->downloadSpreadsheet(
            $this->excelService->createTemplateSpreadsheet($_GET['columns'] ?? null),
            'sub_account_template.xlsx'
        );
    }

    public function apiExcel(): void
    {
        $this->downloadSpreadsheet(
            $this->excelService->createExportSpreadsheet($_GET['columns'] ?? null),
            'sub_accounts_' . date('Ymd_His') . '.xlsx'
        );
    }

    public function apiExcelUpload(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '업로드할 엑셀 파일을 선택해 주세요.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            echo json_encode(
                $this->excelService->saveFromExcelFile($_FILES['file']['tmp_name']),
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

    private function downloadSpreadsheet(Spreadsheet $spreadsheet, string $filename): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }
}
