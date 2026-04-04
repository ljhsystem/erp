<?php
// 경로: PROJECT_ROOT . '/app/Controllers/Dashboard/Settings/EmployeeController.php'
namespace App\Controllers\Dashboard\Settings;

use Core\Session;
use Core\Security\Crypto;
use App\Services\System\EmployeeService;
use App\Services\Auth\AuthService;

class EmployeeController
{
    private \PDO $pdo;
    private EmployeeService $employeesService;
    private AuthService $authService;

    public function __construct(\PDO $pdo)
    {
        $this->pdo            = $pdo;
        $this->employeesService = new EmployeeService($pdo);
        $this->authService    = new AuthService($pdo);
    }



    // ============================================================
    // API: 직원 목록
    // URL: POST /api/settings/employee/list
    // permission: settings.employee.list
    // controller: EmployeeController@apiList
    // ============================================================
    public function apiList()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $filtersRaw = $_POST['filters'] ?? $_GET['filters'] ?? '[]';
            $filters = json_decode($filtersRaw, true);

            if (!is_array($filters)) {
                $filters = [];
            }

            $rows = $this->employeesService->getList($filters);

            /* 🔥 주민번호 복호화 추가 */
            $crypto = new Crypto();

            foreach ($rows as &$row) {
                $row['rrn'] = $crypto->decryptResidentNumber($row['rrn'] ?? null);
            }

            echo json_encode([
                'success' => true,
                'data'    => $rows
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '직원 목록 조회 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    // ============================================================
    // API: 직원 일반 검색 (빠른 검색 / 필터용)
    // URL: GET /api/settings/employee/search?q=홍길동
    // ============================================================
    public function apiSearch()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $q = trim((string)($_GET['q'] ?? ''));
            $limit = (int)($_GET['limit'] ?? 50);
            $filtersRaw = $_GET['filters'] ?? '[]';
            $filters = json_decode($filtersRaw, true);

            if (!is_array($filters)) {
                $filters = [];
            }

            if ($limit < 1) $limit = 50;
            if ($limit > 200) $limit = 200;

            $rows = $this->employeesService->search($q, $filters, $limit);

            echo json_encode([
                'success' => true,
                'data'    => $rows
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '직원 검색 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    // =====// ============================================================
    // API: 직원 검색 (Select2용)
    // URL: GET /api/settings/employee/search?q=홍길동
    // controller: EmployeeController@apiSearchPicker
    // ============================================================
    public function apiSearchPicker()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $q = trim((string)($_GET['q'] ?? ''));
            $limit = (int)($_GET['limit'] ?? 20);

            if ($limit < 1) $limit = 20;
            if ($limit > 100) $limit = 100;

            $rows = $this->employeesService->searchPicker($q, $limit);

            echo json_encode([
                'success' => true,
                'debug' => [
                    'controller' => __METHOD__,
                    'q' => $q,
                    'limit' => $limit,
                    'row_count' => count($rows),
                    'rows' => $rows,
                ],
                'results' => array_map(function ($row) {
                    $employeeName = trim((string)($row['employee_name'] ?? ''));
                    $username     = trim((string)($row['username'] ?? ''));
                    $department   = trim((string)($row['department_name'] ?? $row['dept_name'] ?? ''));
                    $position     = trim((string)($row['position_name'] ?? ''));

                    $mainName = $employeeName !== '' ? $employeeName : $username;

                    $subParts = array_filter([
                        $department,
                        $position,
                        $username !== '' ? '@' . $username : ''
                    ]);

                    return [
                        'id'              => $row['id'] ?? '',
                        'text'            => $mainName . (count($subParts) ? ' (' . implode(' / ', $subParts) . ')' : ''),
                        'employee_name'   => $employeeName,
                        'username'        => $username,
                        'department_name' => $department,
                        'position_name'   => $position,
                        'is_active'       => (int)($row['is_active'] ?? 0),
                    ];
                }, $rows)
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'debug' => [
                    'controller' => __METHOD__,
                    'q' => $_GET['q'] ?? null,
                ],
                'message' => '직원 검색 실패',
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'results' => []
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }




    // ============================================================
    // API: 직원 저장 (완전 통합)
    // ============================================================
    public function apiSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {

            $data  = $_POST;
            $files = $_FILES;

            $result = $this->employeesService->save($data, $files);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '저장 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    // ============================================================
    // API: 직원 삭제
    // URL: POST /api/settings/employee/delete
    // ============================================================

    public function apiDelete(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $userId = trim((string)($_POST['id'] ?? ''));

            if ($userId === '') {
                echo json_encode([
                    'success' => false,
                    'message' => 'user_id 누락'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $result = $this->employeesService->delete($userId);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '삭제 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }


    // ============================================================
    // API: 직원 상태 변경 (활성/비활성)
    // URL: POST /api/settings/employee/update-status
    // ============================================================
    public function apiUpdateStatus(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $userId  = trim((string)($_POST['id'] ?? ''));
            $isActive = ($_POST['is_active'] ?? '') === '1';

            if ($userId === '') {
                echo json_encode([
                    'success' => false,
                    'message' => 'user_id 누락'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $result = $this->employeesService->updateStatus($userId, $isActive);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '상태 변경 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }










    // ============================================================
    // API: 직원 순서 변경
    // URL: POST /api/settings/employee/reorder
    // ============================================================
    public function apiReorder(): void
    {
        Session::requireAuth();
        header('Content-Type: application/json; charset=UTF-8');

        try {

            $payload = json_decode(file_get_contents('php://input'), true);
            $changes = $payload['changes'] ?? [];

            if (!$changes) {
                echo json_encode([
                    'success' => false,
                    'message' => '변경 데이터 없음'
                ]);
                return;
            }

            $this->employeesService->reorder($changes);

            echo json_encode([
                'success' => true
            ]);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '순서 저장 실패',
                'error'   => $e->getMessage()
            ]);
        }

        exit;
    }
}
