<?php

namespace App\Controllers\Ledger;

use App\Controllers\System\LayoutController;
use App\Services\Ledger\ChartAccountService;
use Core\DbPdo;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ChartAccountController
{
    private ChartAccountService $service;
    private LayoutController $layout;

    public function __construct()
    {
        $this->service = new ChartAccountService(DbPdo::conn());
        $this->layout = new LayoutController(DbPdo::conn());
    }

    private function renderPage(string $viewPath, array $params = []): void
    {
        if (!empty($params)) {
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

    public function index(): void
    {
        $this->renderPage('/app/views/ledger/account/index.php');
    }

    public function apiList(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $filters = [];
            if (!empty($_GET['filters'])) {
                $filters = json_decode($_GET['filters'], true) ?? [];
            }

            $rows = $this->service->getList($filters);
            error_log('[ChartAccountController] apiList data count=' . count($rows));

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

    public function apiTree(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            echo json_encode([
                'success' => true,
                'data' => $this->service->getTreeStructured(),
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
                'account_code' => $_POST['account_code'] ?? null,
                'account_name' => $_POST['account_name'] ?? null,
                'parent_id' => !empty($_POST['parent_id']) ? $_POST['parent_id'] : null,
                'account_group' => $_POST['account_group'] ?? null,
                'normal_balance' => $_POST['normal_balance'] ?? 'debit',
                'is_posting' => isset($_POST['is_posting']) ? (int) $_POST['is_posting'] : 1,
                'note' => $_POST['note'] ?? null,
                'memo' => $_POST['memo'] ?? null,
                'is_active' => isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1,
                'sub_policies' => json_decode($_POST['sub_policies'] ?? '[]', true) ?? [],
            ];

            if (empty($payload['account_code']) || empty($payload['account_name'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '계정코드와 계정명은 필수입니다.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $id = $_POST['id'] ?? null;

            if ($id) {
                $result = $this->service->update($id, $payload);
            } else {
                $result = $this->service->create($payload);
            }

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiSoftDelete(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $id = $_POST['id'] ?? null;
            if (!$id) {
                echo json_encode([
                    'success' => false,
                    'message' => '계정 ID가 없습니다.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            echo json_encode(
                $this->service->softDelete($id),
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

    public function apiTrashList(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            echo json_encode([
                'success' => true,
                'data' => $this->service->getTrashList(),
            ], JSON_UNESCAPED_UNICODE);
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
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('계정과목 업로드');

        $sheet->fromArray([
            '계정코드',
            '계정과목명',
            '상위계정코드',
            '계정구분',
            '정상잔액',
            '전표입력',
            '사용여부',
            '비고',
            '메모',
            '보조계정명',
        ], null, 'A1');

        $sheet->fromArray([
            ['1000', '현금', '', '자산', '차변', '가능', '사용', '현금성 계정', '시재 관리용', ''],
            ['1100', '보통예금', '1000', '자산', '차변', '가능', '사용', '은행 예금 계정', '', ''],
            ['1110', '국민은행', '1100', '자산', '차변', '가능', '사용', '', '계좌별 보조계정 예시', '일반'],
            ['2000', '외상매입금', '', '부채', '대변', '가능', '사용', '', '', ''],
            ['3000', '자본금', '', '자본', '대변', '불가', '사용', '', '', ''],
            ['4000', '매출', '', '수익', '대변', '불가', '사용', '', '', ''],
            ['4100', '상품매출', '4000', '수익', '대변', '가능', '사용', '', '', ''],
            ['5100', '차량유지비', '', '비용', '차변', '가능', '사용', '', '', ''],
        ], null, 'A2');

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="account_template.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;

        $sheet->fromArray([
            '계정코드',
            '계정명',
            '상위계정',
            '구분',
            '사용여부',
            '비고',
            '보조계정',
        ], null, 'A1');

        $sheet->fromArray([
            ['1000', '현금', '', '자산', '사용', '현금성 계정', ''],
            ['1100', '보통예금', '1000', '자산', '사용', '예금 계정', ''],
            ['1110', '국민은행', '1100', '자산', '사용', '주거래 통장', '일반'],
            ['1120', '기업은행', '1100', '자산', '사용', '', '법인'],
            ['2000', '외상매입금', '', '부채', '사용', '', ''],
            ['2100', '단기차입금', '2000', '부채', '사용', '', ''],
            ['3000', '자본금', '', '자본', '사용', '', ''],
            ['4000', '매출', '', '수익', '사용', '', ''],
            ['4100', '상품매출', '4000', '수익', '사용', '', ''],
            ['5100', '임차료', '', '비용', '사용', '', ''],
        ], null, 'A2');

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="account_template.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;

        $sheet->fromArray([
            'account_code',
            'account_name',
            'parent_code',
            'account_group',
            'sub_name',
        ], null, 'A1');

        $rows = [
            ['1000', '현금', '', '자산', ''],
            ['1100', '보통예금', '1000', '자산', ''],
            ['1110', '국민은행', '1100', '자산', '일반'],
            ['1110', '국민은행', '1100', '자산', '법인'],
            ['1120', '기업은행', '1100', '자산', ''],
            ['2000', '외상매입금', '', '부채', ''],
            ['2100', '단기차입금', '2000', '부채', ''],
            ['3000', '자본금', '', '자본', ''],
            ['4000', '매출', '', '수익', ''],
            ['4100', '상품매출', '4000', '수익', ''],
            ['5000', '급여', '', '비용', ''],
            ['5100', '임차료', '', '비용', ''],
            ['5200', '통신비', '', '비용', ''],
        ];

        $sheet->fromArray($rows, null, 'A2');

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="account_template.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function apidownloadAllExcel(): void
    {
        try {
            $accounts = $this->service->getAll();
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('계정과목 목록');

            $sheet->fromArray([
                '계정코드',
                '계정과목명',
                '상위계정코드',
                '계정구분',
                '정상잔액',
                '전표입력',
                '사용여부',
                '비고',
                '메모',
                '보조계정명',
            ], null, 'A1');

            $accountMap = [];
            foreach ($accounts as $account) {
                if (!empty($account['id'])) {
                    $accountMap[$account['id']] = $account;
                }
            }

            $row = 2;
            foreach ($accounts as $account) {
                $parentCode = '';
                if (!empty($account['parent_id']) && isset($accountMap[$account['parent_id']])) {
                    $parentCode = (string) ($accountMap[$account['parent_id']]['account_code'] ?? '');
                }

                $sheet->setCellValue('A' . $row, $account['account_code'] ?? '');
                $sheet->setCellValue('B' . $row, $account['account_name'] ?? '');
                $sheet->setCellValue('C' . $row, $parentCode);
                $sheet->setCellValue('D' . $row, $account['account_group'] ?? '');
                $sheet->setCellValue('E' . $row, ($account['normal_balance'] ?? '') === 'credit' ? '대변' : '차변');
                $sheet->setCellValue('F' . $row, ((int) ($account['is_posting'] ?? 0)) === 1 ? '가능' : '불가');
                $sheet->setCellValue('G' . $row, ((int) ($account['is_active'] ?? 0)) === 1 ? '사용' : '미사용');
                $sheet->setCellValue('H' . $row, $account['note'] ?? '');
                $sheet->setCellValue('I' . $row, $account['memo'] ?? '');
                $sheet->setCellValue('J' . $row, '');
                $row++;
            }

            foreach (range('A', 'J') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $filename = '계정과목목록_' . date('Ymd_His') . '.xlsx';

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header('Cache-Control: max-age=0');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            exit;

            $accounts = $this->service->getAll();
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = ['코드', '계정명', '구분', '상위계정', '레벨', '차/대', '전표입력', '사용여부', '보조계정', '비고', '메모'];
            $sheet->fromArray($headers, null, 'A1');

            $row = 2;
            foreach ($accounts as $acc) {
                $sheet->setCellValue('A' . $row, $acc['account_code'] ?? '');
                $sheet->setCellValue('B' . $row, $acc['account_name'] ?? '');
                $sheet->setCellValue('C' . $row, $acc['account_group'] ?? '');
                $sheet->setCellValue('D' . $row, $acc['parent_name'] ?? '');
                $sheet->setCellValue('E' . $row, $acc['level'] ?? '');
                $sheet->setCellValue('F' . $row, ($acc['normal_balance'] ?? '') === 'debit' ? '차변' : '대변');
                $sheet->setCellValue('G' . $row, ($acc['is_posting'] ?? 0) ? '가능' : '불가');
                $sheet->setCellValue('H' . $row, ($acc['is_active'] ?? 0) ? '사용' : '미사용');
                $sheet->setCellValue('I' . $row, ($acc['allow_sub_account'] ?? 0) ? '사용' : '미사용');
                $sheet->setCellValue('J' . $row, $acc['note'] ?? '');
                $sheet->setCellValue('K' . $row, $acc['memo'] ?? '');
                $row++;
            }

            $filename = '계정목록_' . date('Ymd_His') . '.xlsx';

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header('Cache-Control: max-age=0');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo '엑셀 다운로드 실패: ' . $e->getMessage();
            exit;
        }
    }

    public function apiExcelUpload(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            if (empty($_FILES['file']['tmp_name'])) {
                throw new \Exception('파일이 없습니다.');
            }

            echo json_encode(
                $this->service->saveFromExcelFile($_FILES['file']['tmp_name']),
                JSON_UNESCAPED_UNICODE
            );
            exit;

            $spreadsheet = IOFactory::load($_FILES['file']['tmp_name']);
            $rows = $spreadsheet->getActiveSheet()->toArray();
            array_shift($rows);

            $createdAccounts = [];
            $errors = [];

            foreach ($rows as $row) {
                [$code, $name, $parentCode, $group, $subName] = array_pad($row, 5, null);

                $code = trim((string) $code);
                $name = trim((string) $name);
                $parentCode = trim((string) $parentCode);
                $group = trim((string) $group);
                $subName = trim((string) $subName);

                if ($code === '' || $name === '') {
                    $errors[] = "필수값 누락: {$code}";
                    continue;
                }

                $parentId = null;
                if ($parentCode !== '') {
                    $parent = $this->service->findByCode($parentCode);
                    $parentId = $parent['id'] ?? null;
                }

                $account = $this->service->findByCode($code);

                if (!$account) {
                    $result = $this->service->create([
                        'account_code' => $code,
                        'account_name' => $name,
                        'parent_id' => $parentId,
                        'account_group' => $group,
                        'normal_balance' => in_array($group, ['자산', '비용'], true) ? 'debit' : 'credit',
                        'is_posting' => 1,
                        'is_active' => 1,
                    ]);

                    if (!$result['success']) {
                        $errors[] = "계정 생성 실패: {$code}";
                        continue;
                    }

                    $account = ['id' => $result['id']];
                } else {
                    $this->service->update($account['id'], [
                        'account_code' => $code,
                        'account_name' => $name,
                        'parent_id' => $parentId,
                        'account_group' => $group,
                        'normal_balance' => in_array($group, ['자산', '비용'], true) ? 'debit' : 'credit',
                        'is_posting' => 1,
                        'is_active' => 1,
                    ]);
                }

                if ($subName !== '') {
                    $this->service->createSubAccount([
                        'account_id' => $account['id'],
                        'sub_name' => $subName,
                    ]);
                }

                $createdAccounts[] = $code;
            }

            echo json_encode([
                'success' => true,
                'created_count' => count($createdAccounts),
                'errors' => $errors,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiReorder(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $changes = $input['changes'] ?? [];

            if (empty($changes)) {
                throw new \Exception('변경 데이터가 없습니다.');
            }

            $this->service->reorder($changes);

            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiRestore(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $id = $_POST['id'] ?? null;
            if (!$id) {
                echo json_encode([
                    'success' => false,
                    'message' => '계정 ID가 없습니다.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            echo json_encode(
                $this->service->restore($id),
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

    public function apiHardDelete(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $id = $_POST['id'] ?? null;
            if (!$id) {
                echo json_encode([
                    'success' => false,
                    'message' => '계정 ID가 없습니다.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            echo json_encode(
                $this->service->hardDelete($id),
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

    public function apiDetail(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_GET['id'] ?? null;
        $code = $_GET['code'] ?? null;
        if (!$id && !$code) {
            echo json_encode(['success' => false], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($id) {
            $basic = $this->service->getById($id);
            $code = $basic['account_code'] ?? null;
        }

        $row = $code ? $this->service->getDetailByAccountCode($code) : null;

        if (!$row) {
            echo json_encode([
                'success' => false,
                'message' => '계정을 찾을 수 없습니다.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'success' => true,
            'data' => $row,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function apiRestoreBulk(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];

            if (empty($ids)) {
                throw new \Exception('ids가 없습니다.');
            }

            $this->service->restoreBulk($ids);

            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiHardDeleteBulk(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];

            if (empty($ids)) {
                throw new \Exception('ids가 없습니다.');
            }

            foreach ($ids as $id) {
                $this->service->hardDelete($id);
            }

            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiRestoreAll(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $this->service->restoreAll();
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiHardDeleteAll(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $this->service->hardDeleteAll();
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }
}
