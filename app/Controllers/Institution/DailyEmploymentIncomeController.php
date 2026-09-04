<?php

namespace App\Controllers\Institution;

use App\Controllers\System\LayoutController;
use App\Services\Institution\DailyEmploymentIncomeService;
use App\Services\Institution\DailyEmploymentIncomeExcelService;
use Core\DbPdo;
use Core\Helpers\ApiErrorResponseHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PDO;

final class DailyEmploymentIncomeController
{
    private PDO $db;
    private DailyEmploymentIncomeService $service;
    private DailyEmploymentIncomeExcelService $excel;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DbPdo::conn();
        $this->service = new DailyEmploymentIncomeService($this->db);
        $this->excel = new DailyEmploymentIncomeExcelService($this->db);
    }

    public function webIndex(): void
    {
        ob_start();
        require PROJECT_ROOT . '/app/views/institution/daily-employment-income/index.php';
        $content = ob_get_clean();
        (new LayoutController($this->db))->render(['pageTitle' => '일용근로소득', 'content' => $content, 'pageStyles' => $pageStyles ?? '', 'pageScripts' => $pageScripts ?? '']);
    }

    public function apiList(): void { $this->respond(fn() => $this->service->page(\Core\Helpers\DataTableRequestHelper::input())); }
    public function apiDetail(): void { $this->respond(fn() => $this->service->detail(trim((string) ($_GET['id'] ?? '')))); }
    public function apiOptions(): void { $this->respond(fn() => $this->service->options($_GET)); }
    public function apiCalculate(): void { $this->respond(fn() => $this->service->calculate($this->input())); }
    public function apiPreflight(): void { $this->respond(fn() => $this->service->submissionPreflight(trim((string) ($_GET['id'] ?? '')))); }
    public function apiSave(): void { $this->respond(fn() => $this->service->save($this->input())); }
    public function apiSubmit(): void { $this->respond(fn() => $this->service->submit(trim((string) ($this->input()['id'] ?? '')))); }
    public function apiWithdraw(): void { $this->respond(fn() => $this->service->withdraw(trim((string) ($this->input()['request_id'] ?? '')))); }
    public function apiDelete(): void { $this->respond(fn() => $this->service->delete(trim((string) ($this->input()['id'] ?? '')))); }
    public function apiTrashList(): void { $this->respond(fn() => $this->service->trash()); }
    public function apiRestore(): void { $this->respond(fn() => $this->service->restore(trim((string) ($this->input()['id'] ?? '')))); }
    public function apiRestoreBulk(): void { $input=$this->input(); $this->respond(fn() => $this->service->restoreMany(is_array($input['ids'] ?? null) ? $input['ids'] : [])); }
    public function apiRestoreAll(): void { $this->respond(fn() => $this->service->restoreAll()); }
    public function apiPurge(): void { $this->respond(fn() => $this->service->purge(trim((string) ($this->input()['id'] ?? '')))); }
    public function apiPurgeBulk(): void { $input=$this->input(); $this->respond(fn() => $this->service->purgeMany(is_array($input['ids'] ?? null) ? $input['ids'] : [])); }
    public function apiPurgeAll(): void { $this->respond(fn() => $this->service->purgeAll()); }
    public function apiTemplate(): void { $this->downloadSpreadsheet($this->excel->createTemplate(), 'daily_employment_income_template.xlsx'); }
    public function apiExcel(): void
    {
        $input = $this->input();
        $groups = $input['groups'] ?? [];
        if (is_string($groups)) {
            $decoded = json_decode($groups, true);
            $groups = is_array($decoded) ? $decoded : [];
        }
        $header = $input['header'] ?? [];
        if (is_string($header)) $header = json_decode($header, true) ?: [];
        $this->downloadSpreadsheet($this->excel->createDownload(is_array($groups) ? $groups : [], is_array($header) ? $header : []), 'daily_employment_income.xlsx');
    }
    public function apiExcelUploadPreview(): void
    {
        $this->respond(function (): array {
            foreach ($_FILES as $file) {
                if (is_array($file) && !empty($file['tmp_name']) && is_uploaded_file((string) $file['tmp_name'])) {
                    return $this->excel->preview((string) $file['tmp_name'], trim((string) ($_POST['income_year_month'] ?? '')));
                }
            }
            throw new \InvalidArgumentException('업로드할 엑셀 파일을 선택해 주세요.');
        });
    }
    private function input(): array
    {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : $_POST;
    }

    private function respond(callable $callback): void
    {
        try {
            $result = $callback();
            $status = empty($result['success']) ? 400 : 200;
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            $result = ApiErrorResponseHelper::exception($exception, '일용근로소득 처리 중 오류가 발생했습니다.');
            $status = 400;
        } catch (\Throwable) {
            $result = ApiErrorResponseHelper::payload('INTERNAL_ERROR', '일용근로소득 처리 중 오류가 발생했습니다.');
            $status = 500;
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function downloadSpreadsheet(Spreadsheet $spreadsheet, string $filename): void
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        (new Xlsx($spreadsheet))->save('php://output');
    }
}
