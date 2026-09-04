<?php
namespace App\Controllers\Main\Settings;

use Core\DbPdo;
use Core\Session;
use App\Services\User\DepartmentService;

class DepartmentController
{
    private DepartmentService $service;

    public function __construct()
    {
        $this->service = new DepartmentService(DbPdo::conn());
    }

    public function webIndex()
    {
        include PROJECT_ROOT . '/app/views/main/settings/organization/department.php';
    }

    public function apiList()
    {
        header('Content-Type: application/json; charset=utf-8');
        Session::write();

        try {
            $filters = $this->readFilters();
            $rows = $this->service->getAll($filters);

            echo json_encode([
                'success' => true,
                'data'    => $rows
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => '부서 목록 조회 중 오류가 발생했습니다.'
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiDetail()
    {
        header('Content-Type: application/json; charset=utf-8');
        Session::write();

        $id = $_GET['id'] ?? $_POST['id'] ?? '';

        if (!$id) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => '부서 ID가 필요합니다.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $row = $this->service->getById($id);

            echo json_encode([
                'success' => (bool)$row,
                'data'    => $row,
                'message' => $row ? null : '부서 정보를 찾을 수 없습니다.',
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => '부서 조회 중 오류가 발생했습니다.'
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        $action = trim((string) ($_POST['action'] ?? ''));
        $id = trim((string) ($_POST['id'] ?? ''));
        $payload = [
            'dept_name' => $_POST['dept_name'] ?? '',
            'manager_id' => $_POST['manager_id'] ?? null,
            'description' => $_POST['description'] ?? '',
            'is_active' => $_POST['is_active'] ?? null,
        ];

        try {
            switch ($action) {
                case 'create':
                    $result = $this->service->create($payload);
                    break;

                case 'update':
                    $result = $this->service->update($id, $payload);
                    break;

                default:
                    throw new \InvalidArgumentException('지원하지 않는 저장 요청입니다.');
            }

            if (empty($result['success'])) {
                http_response_code(422);
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\InvalidArgumentException $e) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => '저장 중 오류가 발생했습니다.'
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiDelete()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $result = $this->service->delete((string) ($_POST['id'] ?? ''));
            if (empty($result['success'])) {
                http_response_code(409);
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\InvalidArgumentException $e) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => '삭제 중 오류가 발생했습니다.'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }


    public function apiReorder(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);
        $changes = $input['changes'] ?? [];

        try {
            $ok = $this->service->reorder($changes);
            echo json_encode([
                'success' => (bool)$ok,
                'message' => $ok ? 'reordered' : 'fail'
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => '정렬 저장 중 오류가 발생했습니다.'
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    private function readFilters(): array
    {
        $filters = [];
        $rawFilters = $_GET['filters'] ?? $_POST['filters'] ?? '';

        if ($rawFilters !== '') {
            $decoded = json_decode($rawFilters, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $filters = $decoded;
            }
        }

        return $filters;
    }

}
