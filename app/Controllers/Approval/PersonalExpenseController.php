<?php

namespace App\Controllers\Approval;

use App\Controllers\System\LayoutController;
use App\Services\Approval\PersonalExpenseApprovalService;
use App\Services\Approval\PersonalExpenseExcelService;
use App\Services\Approval\PersonalExpenseService;
use Core\DbPdo;
use Core\Helpers\ExcelTemplateFilenameHelper;
use InvalidArgumentException;
use PDO;
use PDOException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

class PersonalExpenseController
{
    private PDO $pdo;
    private PersonalExpenseService $service;
    private PersonalExpenseApprovalService $approval;
    private PersonalExpenseExcelService $excel;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbPdo::conn();
        $this->service = new PersonalExpenseService($this->pdo);
        $this->approval = new PersonalExpenseApprovalService($this->pdo);
        $this->excel = new PersonalExpenseExcelService($this->service);
    }

    public function webIndex(): void
    {
        $formOptions = $this->service->formOptions();
        $currentEmployee = $formOptions['current_employee'];
        $expenseCategories = $formOptions['expense_categories'];
        $paymentMethods = $formOptions['payment_methods'];
        $receiptTypes = $formOptions['receipt_types'];
        $units = $formOptions['units'];
        ob_start();
        require PROJECT_ROOT . '/app/views/approval/personal-expense/index.php';
        $content = ob_get_clean();
        (new LayoutController($this->pdo))->render([
            'pageTitle' => '개인경비 신청', 'content' => $content,
            'pageStyles' => $pageStyles ?? '', 'pageScripts' => $pageScripts ?? '',
        ]);
    }

    public function apiList(): void { $this->respond(fn () => $this->service->list($_GET)); }
    public function apiDetail(): void { $this->respond(fn () => $this->service->detail(trim((string) ($_GET['id'] ?? '')))); }
    public function apiSave(): void { $this->respond(fn () => $this->service->save($this->input())); }
    public function apiTrashList(): void { $this->respond(fn () => $this->service->trashList()); }
    public function apiRestoreAll(): void { $this->respond(fn () => $this->service->restoreAll()); }
    public function apiPurgeAll(): void { $this->respond(fn () => $this->service->purgeAll()); }
    public function apiSaveAndSubmit(): void { $this->respond(fn () => $this->approval->saveAndSubmit($this->input())); }

    public function apiReorder(): void
    {
        $this->respond(function () {
            $input = $this->input();
            return $this->service->reorder(is_array($input['changes'] ?? null) ? $input['changes'] : []);
        });
    }

    public function apiDelete(): void
    {
        $this->respond(function () {
            $input = $this->input();
            $ids = is_array($input['ids'] ?? null) ? $input['ids'] : [$input['id'] ?? ''];
            return $this->service->deleteMany($ids);
        });
    }

    public function apiRestore(): void { $this->respond(fn () => $this->service->restore(trim((string) ($this->input()['id'] ?? '')))); }
    public function apiRestoreBulk(): void { $this->respond(fn () => $this->service->restoreMany($this->input()['ids'] ?? [])); }
    public function apiPurge(): void { $this->respond(fn () => $this->service->purge(trim((string) ($this->input()['id'] ?? '')))); }
    public function apiPurgeBulk(): void { $this->respond(fn () => $this->service->purgeMany($this->input()['ids'] ?? [])); }
    public function apiWithdraw(): void { $this->respond(fn () => $this->approval->withdraw(trim((string) ($this->input()['request_id'] ?? '')))); }

    public function apiTemplate(): void
    {
        $this->downloadSpreadsheet($this->excel->createTemplate($_GET['columns'] ?? null), 'personal_expense_items_template.xlsx');
    }

    public function apiExcel(): void
    {
        $payload = $this->input();
        $rows = $payload['rows'] ?? [];
        if (is_string($rows)) {
            $decoded = json_decode($rows, true);
            $rows = is_array($decoded) ? $decoded : [];
        }
        $this->downloadSpreadsheet(
            $this->excel->createDownload(is_array($rows) ? $rows : [], $payload['columns'] ?? $_GET['columns'] ?? null),
            'personal_expense_items.xlsx'
        );
    }

    public function apiExcelUpload(): void
    {
        $this->respond(function () {
            foreach ($_FILES as $file) {
                if (is_array($file) && !empty($file['tmp_name']) && is_uploaded_file((string) $file['tmp_name'])) {
                    return $this->excel->import(
                        (string) $file['tmp_name'],
                        isset($_POST['personal_expense_id']) ? (string) $_POST['personal_expense_id'] : null
                    );
                }
            }
            throw new InvalidArgumentException('업로드할 엑셀 파일을 선택해 주세요.');
        });
    }

    private function input(): array
    {
        $raw = file_get_contents('php://input');
        $json = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($json) ? $json : $_POST;
    }

    private function respond(callable $callback): void
    {
        try {
            $result = $callback();
            $status = empty($result['success']) ? 400 : 200;
        } catch (PDOException) {
            $result = ['success' => false, 'message' => '개인경비 처리 중 오류가 발생했습니다.'];
            $status = 500;
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $result = ['success' => false, 'message' => $exception->getMessage()];
            $status = 400;
        } catch (Throwable) {
            $result = ['success' => false, 'message' => '개인경비 처리 중 오류가 발생했습니다.'];
            $status = 500;
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function downloadSpreadsheet(Spreadsheet $spreadsheet, string $filename): void
    {
        $filename = ExcelTemplateFilenameHelper::normalize($filename, 'personal_expense_items');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }
}
