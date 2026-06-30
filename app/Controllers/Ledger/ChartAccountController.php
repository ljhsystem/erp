<?php

namespace App\Controllers\Ledger;

use App\Controllers\System\LayoutController;
use App\Services\Ledger\ChartAccountExcelService;
use App\Services\Ledger\ChartAccountService;
use Core\Helpers\ExcelTemplateFilenameHelper;
use Core\DbPdo;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ChartAccountController
{
    private ChartAccountService $service;
    private ChartAccountExcelService $excelService;
    private LayoutController $layout;

    public function __construct()
    {
        $pdo = DbPdo::conn();
        $this->service = new ChartAccountService($pdo);
        $this->excelService = new ChartAccountExcelService($this->service);
        $this->layout = new LayoutController($pdo);
    }

    public function index(): void
    {
        $this->renderPage('/app/views/ledger/account/index.php', [
            'pageTitle' => '계정과목',
        ]);
    }

    public function apiList(): void
    {
        try {
            $filters = $this->decodeFilters($_GET['filters'] ?? '[]');
            $this->json([
                'success' => true,
                'data' => $this->service->getList($filters),
            ]);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage());
        }
    }

    public function apiTree(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->getTreeStructured(),
            ]);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage());
        }
    }

    public function apiDetail(): void
    {
        try {
            $id = trim((string) ($_GET['id'] ?? ''));
            $code = trim((string) ($_GET['code'] ?? ''));

            if ($id === '' && $code === '') {
                $this->jsonError('계정코드 또는 ID는 필수입니다.', 400);
                return;
            }

            if ($id !== '') {
                $basic = $this->service->getById($id);
                $code = (string) ($basic['account_code'] ?? '');
            }

            $row = $code !== '' ? $this->service->getDetailByAccountCode($code) : null;
            if (!$row) {
                $this->jsonError('계정을 찾을 수 없습니다.', 404);
                return;
            }

            $this->json([
                'success' => true,
                'data' => $row,
            ]);
        } catch (\Throwable $e) {
            $this->jsonError('계정과목 상세 조회 중 오류가 발생했습니다.', 500, $e->getMessage());
        }
    }

    public function apiSave(): void
    {
        try {
            $payload = [
                'account_code' => $_POST['account_code'] ?? null,
                'account_name' => $_POST['account_name'] ?? null,
                'parent_id' => !empty($_POST['parent_id']) ? $_POST['parent_id'] : null,
                'account_group' => $_POST['account_group'] ?? null,
                'account_category' => $_POST['account_category'] ?? ($_POST['account_group'] ?? null),
                'normal_balance' => $_POST['normal_balance'] ?? 'debit',
                'is_posting' => isset($_POST['is_posting']) ? (int) $_POST['is_posting'] : 1,
                'is_postable' => isset($_POST['is_postable'])
                    ? (string) $_POST['is_postable']
                    : (isset($_POST['is_posting']) && (int) $_POST['is_posting'] === 1 ? 'Y' : 'N'),
                'allow_sub_account' => isset($_POST['allow_sub_account']) ? (int) $_POST['allow_sub_account'] : 0,
                'note' => $_POST['note'] ?? null,
                'memo' => $_POST['memo'] ?? null,
                'is_active' => isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1,
                'status' => isset($_POST['is_active']) && (int) $_POST['is_active'] === 0 ? 'inactive' : 'active',
                'new_parent_code' => $_POST['new_parent_code'] ?? null,
                'new_parent_name' => $_POST['new_parent_name'] ?? null,
            ];

            if (array_key_exists('sub_policies', $_POST)) {
                $payload['sub_policies'] = json_decode($_POST['sub_policies'] ?? '[]', true) ?? [];
            }

            if (array_key_exists('sub_accounts', $_POST)) {
                $payload['sub_accounts'] = json_decode($_POST['sub_accounts'] ?? '[]', true) ?? [];
            }

            if (empty($payload['account_code']) || empty($payload['account_name'])) {
                $this->jsonError('계정코드와 계정과목명은 필수입니다.', 422);
                return;
            }

            $id = trim((string) ($_POST['id'] ?? ''));
            $result = $id !== ''
                ? $this->service->update($id, $payload)
                : $this->service->create($payload);

            $this->json($result);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    public function apiStatus(): void
    {
        try {
            $id = trim((string) ($_POST['id'] ?? ''));
            $isActive = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 0;

            if ($id === '') {
                $this->jsonError('계정 ID는 필수입니다.', 400);
                return;
            }

            $this->json($this->service->updateStatus($id, $isActive === 1 ? 1 : 0));
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage());
        }
    }

    public function apiDelete(): void
    {
        try {
            $id = trim((string) ($_POST['id'] ?? ''));
            if ($id === '') {
                $this->jsonError('계정 ID가 없습니다.', 400);
                return;
            }

            $this->json($this->service->softDelete($id));
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage());
        }
    }

    public function apiSoftDelete(): void
    {
        $this->apiDelete();
    }

    public function apiTrashList(): void
    {
        try {
            $this->json([
                'success' => true,
                'data' => $this->service->getTrashList(),
            ]);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage());
        }
    }

    public function apiTemplate(): void
    {
        $this->downloadSpreadsheet(
            $this->excelService->createTemplateSpreadsheet($_GET['columns'] ?? null),
            'account_template.xlsx'
        );
    }

    public function apiExcel(): void
    {
        $filename = 'accounts_' . date('Ymd_His') . '.xlsx';
        $this->downloadSpreadsheet(
            $this->excelService->createExportSpreadsheet($_GET['columns'] ?? null),
            $filename
        );
    }

    public function apiDownloadAllExcel(): void
    {
        $this->apiExcel();
    }

    public function apiExcelUpload(): void
    {
        try {
            if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
                $this->jsonError('업로드할 엑셀 파일을 선택해 주세요.', 400);
                return;
            }

            $this->json($this->service->saveFromExcelFile($_FILES['file']['tmp_name']));
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    public function apiReorder(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input') ?: '{}', true);
            $changes = is_array($input['changes'] ?? null) ? $input['changes'] : [];

            if ($changes === []) {
                $this->jsonError('변경 데이터가 없습니다.', 400);
                return;
            }

            $this->service->reorder($changes);
            $this->json(['success' => true]);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage());
        }
    }

    public function apiRestore(): void
    {
        try {
            $id = trim((string) ($_POST['id'] ?? ''));
            if ($id === '') {
                $this->jsonError('계정 ID가 없습니다.', 400);
                return;
            }

            $this->json($this->service->restore($id));
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage());
        }
    }

    public function apiRestoreBulk(): void
    {
        try {
            $ids = $this->jsonIds();
            if ($ids === []) {
                $this->jsonError('복구할 계정이 없습니다.', 400);
                return;
            }

            $this->service->restoreBulk($ids);
            $this->json(['success' => true]);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage());
        }
    }

    public function apiRestoreAll(): void
    {
        try {
            $this->service->restoreAll();
            $this->json(['success' => true]);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage());
        }
    }

    public function apiPurge(): void
    {
        try {
            $id = trim((string) ($_POST['id'] ?? ''));
            if ($id === '') {
                $this->jsonError('계정 ID가 없습니다.', 400);
                return;
            }

            $this->json($this->service->hardDelete($id));
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage());
        }
    }

    public function apiHardDelete(): void
    {
        $this->apiPurge();
    }

    public function apiPurgeBulk(): void
    {
        try {
            $ids = $this->jsonIds();
            if ($ids === []) {
                $this->jsonError('삭제할 계정이 없습니다.', 400);
                return;
            }

            foreach ($ids as $id) {
                $this->service->hardDelete($id);
            }

            $this->json(['success' => true]);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage());
        }
    }

    public function apiHardDeleteBulk(): void
    {
        $this->apiPurgeBulk();
    }

    public function apiPurgeAll(): void
    {
        try {
            $this->service->hardDeleteAll();
            $this->json(['success' => true]);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage());
        }
    }

    public function apiHardDeleteAll(): void
    {
        $this->apiPurgeAll();
    }

    public function apiSearch(): void
    {
        try {
            $keyword = trim((string) ($_GET['q'] ?? $_GET['keyword'] ?? ''));
            $filters = $keyword === ''
                ? []
                : [
                    ['field' => 'account_code', 'value' => $keyword],
                    ['field' => 'account_name', 'value' => $keyword],
                  ];

            $rows = $keyword === ''
                ? $this->service->getList([])
                : array_values(array_filter(
                    $this->service->getList([]),
                    static function (array $row) use ($keyword): bool {
                        $name = (string) ($row['account_name'] ?? '');
                        $code = (string) ($row['account_code'] ?? '');
                        return str_contains($name, $keyword) || str_contains($code, $keyword);
                    }
                ));

            $this->json([
                'success' => true,
                'data' => $rows,
                'filters' => $filters,
            ]);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage());
        }
    }

    public function apiPosting(): void
    {
        try {
            $rows = array_values(array_filter(
                $this->service->getList([]),
                static fn (array $row): bool => (int) ($row['is_posting'] ?? 0) === 1 && (int) ($row['is_active'] ?? 0) === 1
            ));

            $this->json([
                'success' => true,
                'data' => $rows,
            ]);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage());
        }
    }

    private function renderPage(string $viewPath, array $params = []): void
    {
        if ($params !== []) {
            extract($params, EXTR_SKIP);
        }

        ob_start();
        require PROJECT_ROOT . $viewPath;
        $content = ob_get_clean();

        $this->layout->render([
            'pageTitle' => $pageTitle ?? '',
            'content' => $content,
            'layoutOptions' => $layoutOptions ?? [],
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
        ]);
    }

    private function downloadSpreadsheet(Spreadsheet $spreadsheet, string $filename): void
    {
        $filename = ExcelTemplateFilenameHelper::normalize($filename, 'account');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }

    private function decodeFilters(mixed $filters): array
    {
        if (is_array($filters)) {
            return $filters;
        }

        $decoded = json_decode((string) $filters, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function jsonIds(): array
    {
        $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
        return array_values(array_filter(array_map('strval', is_array($payload['ids'] ?? null) ? $payload['ids'] : [])));
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    private function jsonError(string $message, int $status = 500, ?string $error = null): void
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($error !== null && $error !== '') {
            $payload['error'] = $error;
        }

        $this->json($payload, $status);
    }
}
