<?php
// 경로: PROJECT_ROOT . '/app/Controllers/Dashboard/Settings/DepartmentController.php'
// 대시보드>설정>조직관리>부서 API 컨트롤러
namespace App\Controllers\Dashboard\Settings;

use Core\Session;
use Core\DbPdo;
use App\Services\User\DepartmentService;

class DepartmentController
{
    private DepartmentService $service;

    public function __construct()
    {
        Session::requireAuth();
        $this->service = new DepartmentService(DbPdo::conn());
    }

    // ============================================================
    // WEB: 부서 관리 화면
    // URL: GET /dashboard/settings/department
    // permission: settings.department.view
    // controller: DepartmentController@webIndex
    // ============================================================
    public function webIndex()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/organization/departments.php';
    }

    // ============================================================
    // API: 부서 목록
    // URL: POST /api/settings/department/list
    // permission: settings.department.list
    // controller: DepartmentController@apiList
    // ============================================================
    public function apiList()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $filters = [];

            $rawFilters = $_GET['filters'] ?? $_POST['filters'] ?? '';

            if ($rawFilters !== '') {
                $decoded = json_decode($rawFilters, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $filters = $decoded;
                }
            }

            $rows = $this->service->getAll($filters);

            echo json_encode([
                'success' => true,
                'data'    => $rows
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '부서 목록 조회 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    // ============================================================
    // API: 부서 저장 (신규 + 수정 + 삭제)
    // URL: POST /api/settings/department/save
    // permission: settings.department.save
    // controller: DepartmentController@apiSave
    // ============================================================
    public function apiSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        $action = $_POST['action'] ?? '';
        $id     = $_POST['id'] ?? '';

        try {
            switch ($action) {

                case 'create':
                    $result = $this->handleCreate();
                    break;

                case 'update':
                    $result = $this->handleUpdate($id);
                    break;

                case 'delete':
                    $result = $this->handleDelete($id);
                    break;

                default:
                    throw new \Exception("Invalid action");
            }

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '처리 중 오류 발생'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    // ============================================================
    // 내부 헬퍼: 부서 생성
    // ============================================================
    private function handleCreate(): array
    {
        $deptName    = trim($_POST['dept_name'] ?? '');
        $managerId   = $_POST['manager_id'] ?? null;
        $description = trim($_POST['description'] ?? '');
        $isActive    = (int)($_POST['is_active'] ?? 1);

        if (!$deptName) {
            return ['success' => false, 'message' => '부서명은 필수입니다.'];
        }

        $payload = [
            'dept_name'  => $deptName,
            'manager_id' => $managerId ?: null,
            'description' => $description,
            'is_active'  => $isActive,
            'created_by' => $_SESSION['user']['id'] ?? null
        ];

        $ok = $this->service->create($payload);

        if ($ok === "duplicate") {
            return ['success' => false, 'message' => 'duplicate'];
        }
        if (!$ok) {
            return ['success' => false, 'message' => 'error'];
        }

        return ['success' => true, 'message' => '부서 생성 완료'];
    }


    // ============================================================
    // 내부 헬퍼: 부서 수정
    // ============================================================
    private function handleUpdate(string $id): array
    {
        if (!$id) {
            return ['success' => false, 'message' => 'id 누락'];
        }

        $managerId = $_POST['manager_id'] ?? null;
        if ($managerId === "" || $managerId === "undefined") {
            $managerId = null;
        }

        $payload = [
            'dept_name'   => trim($_POST['dept_name'] ?? ''),
            'manager_id'  => $managerId,
            'description' => trim($_POST['description'] ?? ''),
            'is_active'   => (int)($_POST['is_active'] ?? 1),
            'updated_by'  => $_SESSION['user']['id'] ?? null
        ];

        $ok = $this->service->update($id, $payload);

        if ($ok === "duplicate") {
            return ['success' => false, 'message' => 'duplicate'];
        }
        if (!$ok) {
            return ['success' => false, 'message' => 'error'];
        }


        return ['success' => true, 'message' => '부서 수정 완료'];
    }

    // ============================================================
    // 내부 헬퍼: 부서 삭제
    // ============================================================
    private function handleDelete(string $id): array
    {
        if (!$id) {
            return ['success' => false, 'message' => 'id 누락'];
        }

        $ok = $this->service->delete($id);

        return [
            'success' => (bool)$ok,
            'message' => '부서 삭제 완료'
        ];
    }
    public function apiReorder()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $changes = json_decode(file_get_contents('php://input'), true)['changes'] ?? [];

        if (!$changes) {
            echo json_encode(['success' => false, 'message' => '변경 데이터 없음']);
            return;
        }

        $this->service->reorder($changes);

        echo json_encode(['success' => true]);
    }
}
