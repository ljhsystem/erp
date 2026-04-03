<?php
// 경로: PROJECT_ROOT . '/app/controllers/dashboard/settings/EmployeeSettingsController.php'
namespace App\Controllers\Dashboard\Settings;

use Core\Session;
use Core\Security\Crypto;
use App\Models\Auth\AuthUserModel;
use App\Services\User\ProfileService;
use App\Services\Auth\AuthService;

class EmployeeSettingsController
{
    private \PDO $pdo;
    private AuthUserModel $userModel;
    private ProfileService $profileService;
    private AuthService $authService;

    public function __construct(\PDO $pdo)
    {
        $this->pdo            = $pdo;
        $this->userModel      = new AuthUserModel($pdo);
        $this->profileService = new ProfileService($pdo);
        $this->authService    = new AuthService($pdo);
    }



    // ============================================================
    // API: 직원 목록
    // URL: POST /api/settings/employee/list
    // permission: settings.employee.list
    // controller: EmployeeSettingsController@apiList
    // ============================================================
    public function apiList()
    {
        header('Content-Type: application/json; charset=utf-8');
    
        try {
            $filtersRaw = $_POST['filters'] ?? '[]';
            $filters = json_decode($filtersRaw, true);
    
            if (!is_array($filters)) {
                $filters = [];
            }
    
            $rows = $this->profileService->getList($filters);

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



    // =====// ============================================================
    // API: 직원 검색 (Select2용)
    // URL: GET /api/settings/employee/search?q=홍길동
    // controller: EmployeeSettingsController@apiSearch
    // ============================================================
    public function apiSearch()
    {
        header('Content-Type: application/json; charset=utf-8');
    
        try {
            $q = trim((string)($_GET['q'] ?? ''));
            $limit = (int)($_GET['limit'] ?? 20);
    
            if ($limit < 1) $limit = 20;
            if ($limit > 100) $limit = 100;
    
            $rows = $this->profileService->search($q, $limit);
    
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

            $result = $this->profileService->save($data, $files);

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

            $result = $this->profileService->delete($userId);

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

            $result = $this->profileService->updateStatus($userId, $isActive);

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
    
            $this->profileService->reorder($changes);
    
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
