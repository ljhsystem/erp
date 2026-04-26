<?php
namespace App\Controllers\Dashboard\Settings;

use App\Services\System\CodeService;
use Core\DbPdo;

class CodeController
{
    private CodeService $service;

    public function __construct()
    {
        $this->service = new CodeService(DbPdo::conn());
    }

    public function apiList(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $codeGroup = trim((string)($_GET['code_group'] ?? ''));
            $isDataTableRequest = isset($_GET['draw']) || isset($_GET['filters']);

            if ($codeGroup !== '' && !$isDataTableRequest) {
                echo json_encode($this->service->getOptionsByGroup($codeGroup), JSON_UNESCAPED_UNICODE);
                exit;
            }

            $filters = [];

            if (!empty($_GET['filters'])) {
                $decoded = json_decode($_GET['filters'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $filters = $decoded;
                }
            }

            if ($codeGroup !== '') {
                $filters[] = [
                    'field' => 'code_group',
                    'value' => $codeGroup,
                ];
            }

            echo json_encode([
                'success' => true,
                'data' => $this->service->getList($filters),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '기준정보 목록 조회 중 오류가 발생했습니다.',
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiDetail(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID가 없습니다.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $row = $this->service->getById((string)$id);

        echo json_encode([
            'success' => $row !== null,
            'data' => $row,
            'message' => $row ? null : '기준정보를 찾을 수 없습니다.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiGroups(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        echo json_encode([
            'success' => true,
            'data' => $this->service->getGroups(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiSave(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $payload = [
            'id' => $_POST['id'] ?? null,
            'sort_no' => $_POST['sort_no'] ?? null,
            'code_group' => trim((string)($_POST['code_group'] ?? '')),
            'code' => trim((string)($_POST['code'] ?? '')),
            'code_name' => trim((string)($_POST['code_name'] ?? '')),
            'note' => $_POST['note'] ?? null,
            'memo' => $_POST['memo'] ?? null,
            'is_active' => isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1,
            'extra_data' => $_POST['extra_data'] ?? null,
        ];

        if ($payload['code_group'] === '') {
            echo json_encode(['success' => false, 'message' => '분류 정보가 올바르지 않습니다.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($payload['code'] === '') {
            echo json_encode(['success' => false, 'message' => '코드는 필수입니다.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($payload['code_name'] === '') {
            echo json_encode(['success' => false, 'message' => '코드명은 필수입니다.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $result = $this->service->save($payload, 'USER');

        if (!$result['success'] && str_contains((string)($result['message'] ?? ''), 'uq_code_group_code')) {
            $result['message'] = '이미 등록된 코드입니다.';
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiDelete(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID가 없습니다.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $result = $this->service->delete((string)$id, 'USER');
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '기준정보 삭제 중 오류가 발생했습니다.',
                'error' => $e->getMessage(),
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
                'data' => [],
                'message' => '휴지통 목록 조회 중 오류가 발생했습니다.',
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiRestore(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID가 없습니다.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode($this->service->restore((string)$id, 'USER'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiRestoreBulk(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        echo json_encode($this->service->restoreBulk($input['ids'] ?? [], 'USER'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiRestoreAll(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        echo json_encode($this->service->restoreAll('USER'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiPurge(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID가 없습니다.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode($this->service->purge((string)$id, 'USER'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiPurgeBulk(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        echo json_encode($this->service->purgeBulk($input['ids'] ?? [], 'USER'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiPurgeAll(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        echo json_encode($this->service->purgeAll('USER'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiReorder(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $this->service->reorder($input['changes'] ?? []);

        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiDownloadTemplate(): void
    {
        try {
            $this->service->downloadMigrationTemplate();
        } catch (\Throwable $e) {
            http_response_code(500);
            echo '양식 다운로드 실패: ' . $e->getMessage();
            exit;
        }
    }

    public function apiDownloadExcel(): void
    {
        try {
            $this->service->downloadMigrationExcel();
        } catch (\Throwable $e) {
            http_response_code(500);
            echo '다운로드 실패: ' . $e->getMessage();
            exit;
        }
    }

    public function apiExcelUpload(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            if (!isset($_FILES['excel']) || !is_uploaded_file($_FILES['excel']['tmp_name'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '업로드할 엑셀 파일을 선택하세요.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $result = $this->service->saveFromMigrationExcelFile($_FILES['excel']['tmp_name']);

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
