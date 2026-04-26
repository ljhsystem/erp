<?php
namespace App\Controllers\Dashboard\Settings;

use App\Services\System\WorkTeamService;
use Core\DbPdo;

class WorkTeamController
{
    private WorkTeamService $service;

    public function __construct()
    {
        $this->service = new WorkTeamService(DbPdo::conn());
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

            echo json_encode([
                'success' => true,
                'data' => $this->service->getList($filters),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '작업팀 목록 조회 중 오류가 발생했습니다.',
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
            'message' => $row ? null : '작업팀을 찾을 수 없습니다.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiSave(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $payload = [
            'id' => $_POST['id'] ?? null,
            'team_name' => trim((string)($_POST['team_name'] ?? '')),
            'team_leader_client_id' => $_POST['team_leader_client_id'] ?? null,
            'note' => $_POST['note'] ?? null,
            'memo' => $_POST['memo'] ?? null,
            'is_active' => isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1,
        ];

        if ($payload['team_name'] === '') {
            echo json_encode(['success' => false, 'message' => '팀명은 필수입니다.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $result = $this->service->save($payload, 'USER');

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

        echo json_encode($this->service->delete((string)$id, 'USER'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiTrashList(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        echo json_encode([
            'success' => true,
            'data' => $this->service->getTrashList(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiRestore(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        echo json_encode($this->service->restore((string)($_POST['id'] ?? ''), 'USER'), JSON_UNESCAPED_UNICODE);
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

        echo json_encode($this->service->purge((string)($_POST['id'] ?? '')), JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiPurgeBulk(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        echo json_encode($this->service->purgeBulk($input['ids'] ?? []), JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiPurgeAll(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        echo json_encode($this->service->purgeAll(), JSON_UNESCAPED_UNICODE);
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
        $this->service->downloadTemplate();
    }

    public function apiDownloadExcel(): void
    {
        $this->service->downloadExcel();
    }

    public function apiExcelUpload(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            if (!isset($_FILES['excel']) || !is_uploaded_file($_FILES['excel']['tmp_name'])) {
                echo json_encode(['success' => false, 'message' => '업로드할 엑셀 파일을 선택하세요.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            echo json_encode($this->service->saveFromExcelFile($_FILES['excel']['tmp_name']), JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => '엑셀 업로드에 실패했습니다.', 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }
}
