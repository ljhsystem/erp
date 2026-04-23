<?php
// 경로: PROJECT_ROOT . '/app/Controllers/Api/ExternalApiController.php'
namespace App\Controllers\Api;

use App\Services\System\EmployeeService;
use Core\Database;

class ExternalApiController
{
    private EmployeeService $employeeService;

    public function __construct()
    {
        $pdo = Database::getInstance()->getConnection();
        $this->employeeService = new EmployeeService($pdo);
    }

    /**
     * 외부 API 연결 테스트 (Ping)
     * URL: GET /api/external/ping
     * 인증: ApiAccessMiddleware
     */
    public function ping()
    {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success'     => true,
            'message'     => '외부 API 연결 성공',
            'server_time' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 외부 API - 직원 목록 조회
     * URL: GET /api/external/employees
     * 인증: ApiAccessMiddleware (API Key)
     */
    public function employees()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            // ✅ 내부에서 이미 쓰고 있는 로직 재사용
            $rows = $this->employeeService->getList();

            /**
             * 🔐 외부 노출용으로 필드 제한
             * (민감 정보 절대 포함 ❌)
             */
            $employees = array_map(function ($row) {
                return [
                    'user_id'        => $row['user_id']        ?? null,
                    'employee_code'  => $row['sort_no']        ?? null,
                    'employee_name'  => $row['employee_name']  ?? null,
                    'department'     => $row['department_name']?? null,
                    'position'       => $row['position_name']  ?? null,
                    'email'          => $row['email']          ?? null,
                    'phone'          => $row['phone']          ?? null,
                    'is_active'      => (int)($row['is_active'] ?? 0),
                ];
            }, $rows);

            echo json_encode([
                'success' => true,
                'count'   => count($employees),
                'data'    => $employees
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => '직원 목록 조회 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
