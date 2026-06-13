<?php
namespace App\Controllers\Dashboard\Settings;

use Core\DbPdo;
use App\Services\System\BankAccountService;

class BankAccountController
{
    private BankAccountService $service;

    public function __construct()
    {
        $this->service = new BankAccountService(DbPdo::conn());
    }

    public function apiList(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            $filters = [];

            if (!empty($_GET['filters'])) {
                $decoded = json_decode($_GET['filters'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $filters = $decoded;
                }
            }

            $rows = $this->service->getList($filters);

            echo json_encode([
                'success' => true,
                'data' => $rows
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '계좌 목록 조회 중 오류가 발생했습니다.',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiDetail(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_GET['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '?④쑴伊?ID ?⑥뵭'
            ]);
            exit;
        }

        try {

            $row = $this->service->getById($id);

            echo json_encode([
                'success' => true,
                'data' => $row
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '?④쑴伊?鈺곌퀬????쎈솭',
                'error' => $e->getMessage()
            ]);
        }

        exit;
    }


    public function apiSearchPicker(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $keyword = $_GET['q'] ?? '';

        $rows = $this->service->searchPicker($keyword);

        echo json_encode([
            'success' => true,
            'data' => $rows
        ]);

        exit;
    }





    public function apiSave(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $result = $this->service->save($_POST, 'USER', $_FILES);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '?? ?? ? ??? ??????.',
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }


    public function apiDelete(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $input = json_decode((string) file_get_contents('php://input'), true);
        $input = is_array($input) ? $input : [];
        $ids = array_values(array_filter(array_map('strval', is_array($input['ids'] ?? null) ? $input['ids'] : [])));
        $id = $_POST['id'] ?? $input['id'] ?? $ids[0] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => 'ID ?⑥뵭'
            ]);
            exit;
        }

        if (count($ids) > 1) {
            $failed = [];
            foreach ($ids as $deleteId) {
                $result = $this->service->delete($deleteId, 'USER');
                if (empty($result['success'])) {
                    $failed[] = $result['message'] ?? $deleteId;
                }
            }
            echo json_encode([
                'success' => $failed === [],
                'message' => $failed === [] ? '삭제되었습니다.' : ($failed[0] ?? '삭제에 실패했습니다.'),
                'data' => [
                    'deleted_count' => count($ids) - count($failed),
                    'failed_count' => count($failed),
                ],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $result = $this->service->delete($id, 'USER');

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }


    public function apiTrashList(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $rows = $this->service->getTrashList();

        echo json_encode([
            'success' => true,
            'data' => $rows
        ]);

        exit;
    }
    public function apiRestore(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '?④??⑹???⑥뵭'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {

            $result = $this->service->restore($id, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '?④?蹂듭????뙣',
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }
    public function apiRestoreBulk(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            $input = json_decode(file_get_contents('php://input'), true);

            $ids = $input['ids'] ?? [];

            if (empty($ids) || !is_array($ids)) {
                echo json_encode([
                    'success' => false,
                    'message' => '蹂듭????④??⑹뵠?遺? ??곷뮸??덈뼄.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $result = $this->service->restoreBulk($ids, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '?좏깮 蹂듭????뙣',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiRestoreAll(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            $result = $this->service->restoreAll('USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '?⑷ 蹂듭????뙣',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiPurge(): void
{
    header('Content-Type: application/json; charset=UTF-8');

    $id = $_POST['id'] ?? null;

    if (!$id) {
        echo json_encode([
            'success' => false,
            'message' => '삭제할 항목을 선택해주세요.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {

        $result = $this->service->purge($id, 'USER');

        echo json_encode($result, JSON_UNESCAPED_UNICODE);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => '영구삭제 중 오류가 발생했습니다.',
            'error'   => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }

    exit;
}
public function apiPurgeBulk(): void
{
    header('Content-Type: application/json; charset=UTF-8');

    try {

        $input = json_decode(file_get_contents('php://input'), true);

        $ids = $input['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            echo json_encode([
                'success' => false,
                'message' => '영구삭제할 항목을 선택해주세요.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $result = $this->service->purgeBulk($ids, 'USER');

        echo json_encode($result, JSON_UNESCAPED_UNICODE);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => '선택 항목 영구삭제 중 오류가 발생했습니다.',
            'error'   => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }

    exit;
}


public function apiPurgeAll(): void
{
    header('Content-Type: application/json; charset=UTF-8');

    try {

        $result = $this->service->purgeAll('USER');

        echo json_encode($result, JSON_UNESCAPED_UNICODE);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => '전체 영구삭제 중 오류가 발생했습니다.',
            'error'   => $e->getMessage()
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

        if (empty($changes) || !is_array($changes)) {
            echo json_encode([
                'success' => false,
                'message' => '변경 데이터가 없습니다.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $this->service->reorder($changes);

        echo json_encode([
            'success' => true,
            'message' => '정렬 순서가 저장되었습니다.'
        ], JSON_UNESCAPED_UNICODE);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => '정렬 저장 중 오류가 발생했습니다.',
            'error'   => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }

    exit;
}


public function apiDownloadTemplate(): void
{
    try {

        if (ob_get_length()) {
            ob_end_clean();
        }

        $columnsCsv = trim((string) ($_GET['columns'] ?? ''));
        $this->service->downloadTemplate($columnsCsv);

    } catch (\Throwable $e) {

        http_response_code(500);

        header('Content-Type: text/plain; charset=UTF-8');

        echo '엑셀 템플릿 다운로드 중 오류가 발생했습니다: ' . $e->getMessage();
    }

    exit;
}

    public function apiSaveFromExcel(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            if (!isset($_FILES['excel']) || !is_uploaded_file($_FILES['excel']['tmp_name'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '업로드할 엑셀 파일을 선택하세요.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $fileTmp  = $_FILES['excel']['tmp_name'];
            $fileName = $_FILES['excel']['name'];
            $fileSize = $_FILES['excel']['size'];

            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (!in_array($ext, ['xlsx', 'xls'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '엑셀 파일만 업로드할 수 있습니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($fileSize > 10 * 1024 * 1024) {
                echo json_encode([
                    'success' => false,
                    'message' => '엑셀 파일 용량은 최대 10MB입니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }


            $columnsCsv = trim((string) ($_POST['excel_template_columns'] ?? ''));
            $result = $this->service->saveFromExcelFile($fileTmp, $columnsCsv);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '엑셀 업로드 중 오류가 발생했습니다.',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

public function apiDownload(): void
{
    try {

        if (ob_get_length()) {
            ob_end_clean();
        }

        $columnsCsv = trim((string) ($_GET['columns'] ?? ''));
        $this->service->downloadExcel($columnsCsv);

    } catch (\Throwable $e) {

        http_response_code(500);

        header('Content-Type: text/plain; charset=UTF-8');

        echo '엑셀 다운로드 중 오류가 발생했습니다: ' . $e->getMessage();
    }

    exit;
}

}
