<?php
// 경로: PROJECT_ROOT . '/app/Controllers/Dashboard/Settings/ClientController.php'
// 시스템설정 > 기준정보 관리 > 거래처 API 컨트롤러
namespace App\Controllers\Dashboard\Settings;

use App\Services\System\ClientService;
use Core\DbPdo;

class ClientController
{
    private ClientService $service;

    public function __construct()
    {
        $this->service = new ClientService(DbPdo::conn());
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
                'data' => $rows,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '거래처 목록 조회 실패',
                'error'   => $e->getMessage(),
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
                'message' => '거래처 ID가 없습니다.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $row = $this->service->getById($id);

            if (!$row) {
                echo json_encode([
                    'success' => false,
                    'message' => '거래처가 없습니다.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            echo json_encode([
                'success' => true,
                'data' => $row,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '거래처 조회 실패',
                'error'   => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiDeleteCompanyNameHistory(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $input = json_decode((string) file_get_contents('php://input'), true);
        $input = is_array($input) ? $input : [];
        $id = trim((string) ($_POST['id'] ?? $input['id'] ?? ''));

        if ($id === '') {
            echo json_encode([
                'success' => false,
                'message' => '삭제할 상호 변경 이력이 없습니다.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode($this->service->deleteCompanyNameHistory($id), JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiSearchPicker(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $keyword = trim($_GET['q'] ?? '');
            $options = [];

            if (isset($_GET['client_type'])) {
                $options['client_type'] = trim((string) $_GET['client_type']);
            }

            if (isset($_GET['is_active'])) {
                $options['is_active'] = (int) $_GET['is_active'];
            }

            $rows = $this->service->searchPicker($keyword, $options);

            echo json_encode([
                'success' => true,
                'data'    => $rows,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'data'    => [],
                'message' => '검색 실패',
                'error'   => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

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
                'message' => '저장 중 오류가 발생했습니다.',
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
                'message' => '거래처 ID가 없습니다.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
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
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '거래처 삭제 실패',
                'error'   => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiTrashList(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $rows = $this->service->getTrashList();

            echo json_encode([
                'success' => true,
                'data' => $rows,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '휴지통 조회 실패',
                'error'   => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiRestore(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '거래처 ID가 없습니다.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $result = $this->service->restore($id, 'USER');
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '거래처 복원 실패',
                'error'   => $e->getMessage(),
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
                    'message' => '복원할 거래처 ID가 없습니다.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $result = $this->service->restoreBulk($ids, 'USER');
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '선택 복원 실패',
                'error'   => $e->getMessage(),
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
                'message' => '전체 복원 실패',
                'error'   => $e->getMessage(),
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
                'message' => '거래처 ID가 없습니다.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $result = $this->service->purge($id, 'USER');
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '영구삭제 실패',
                'error'   => $e->getMessage(),
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
                    'message' => '영구삭제할 거래처 ID가 없습니다.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $result = $this->service->purgeBulk($ids, 'USER');
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '선택 영구삭제 실패',
                'error'   => $e->getMessage(),
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
                'message' => '전체 영구삭제 실패',
                'error'   => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiReorder()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $changes = json_decode(file_get_contents('php://input'), true)['changes'] ?? [];

        if (!$changes) {
            echo json_encode(['success' => false, 'message' => '변경할 데이터가 없습니다.']);
            return;
        }

        $this->service->reorder($changes);

        echo json_encode(['success' => true]);
    }

    public function apiDownloadTemplate(): void
    {
        try {
            $columnsCsv = trim((string) ($_GET['columns'] ?? ''));
            $this->service->downloadMigrationTemplate($columnsCsv);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo '양식 다운로드 실패: ' . $e->getMessage();
            exit;
        }
    }

    public function apiSaveFromExcel(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            if (!isset($_FILES['excel']) || !is_uploaded_file($_FILES['excel']['tmp_name'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '엑셀 파일이 업로드되지 않았습니다.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $file = $_FILES['excel']['tmp_name'];
            $columnsCsv = trim((string) ($_POST['excel_template_columns'] ?? ''));
            $result = $this->service->saveFromMigrationExcelFile($file, $columnsCsv);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '엑셀 업로드 실패',
                'error'   => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiDownload(): void
    {
        try {
            $columnsCsv = trim((string) ($_GET['columns'] ?? ''));
            $this->service->downloadMigrationExcel($columnsCsv);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo '엑셀 다운로드 실패: ' . $e->getMessage();
            exit;
        }
    }
}
