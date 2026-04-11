<?php
// 경로: PROJECT_ROOT . '/app/Controllers/Dashboard/Settings/EmployeeController.php'
namespace App\Controllers\Dashboard\Settings;

use Core\Session;
use Core\DbPdo;
use App\Services\System\EmployeeService;
use App\Services\Auth\AuthService;

class EmployeeController
{
    private EmployeeService $service;
    private AuthService $authService;

    public function __construct()
    {
        Session::requireAuth();
        $this->service = new EmployeeService(DbPdo::conn());    

        $this->authService = new AuthService(DbPdo::conn());  
    }


    // ============================================================
    // API: 직원 목록 조회
    // URL: GET /api/settings/employee/list
    // permission: settings.employee.list
    // controller: EmployeeController@apiList
    // ============================================================
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
    
            // 🔥 Service에서 모든 처리 (복호화 포함)
            $rows = $this->service->getList($filters);
    
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
    // API: 직원 상세 조회
    // URL: GET /api/settings/employee/detail
    // ============================================================
    public function apiDetail(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
    
        $id = $_GET['id'] ?? null;
    
        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '직원 아이디 누락'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    
        try {
    
            // 🔥 Service에서 모든 처리 (복호화 포함)
            $row = $this->service->getById($id);
    
            if (!$row) {
                echo json_encode([
                    'success' => false,
                    'message' => '직원 없음'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
    
            echo json_encode([
                'success' => true,
                'data' => $row
            ], JSON_UNESCAPED_UNICODE);
    
        } catch (\Throwable $e) {
    
            echo json_encode([
                'success' => false,
                'message' => '직원 조회 실패',
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
    
    public function apiSearchPicker(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $keyword = trim($_GET['q'] ?? '');
            $rows = $this->service->searchPicker($keyword);

            echo json_encode([
                'success' => true,
                'data'    => $rows
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'data'    => [],
                'message' => '검색 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }


    // ============================================================
    // API: 직원 저장 (신규 + 수정)
    // URL: POST /api/settings/employee/save
    // ============================================================
    public function apiSave(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            /* =========================================================
            🔥 payload 생성 (최종 통합본)
            ========================================================= */

            $payload = [

                // =========================
                // 기준
                // =========================
                'id'       => $_POST['id'] ?? null,
                'code'     => $_POST['code'] ?? null,
                'user_id'  => $_POST['user_id'] ?? null,

                // =========================
                // 기본 정보
                // =========================
                'employee_name' => trim($_POST['employee_name'] ?? ''),

                'phone'           => $_POST['phone'] ?? null,
                'emergency_phone' => $_POST['emergency_phone'] ?? null,

                'address'        => $_POST['address'] ?? null,
                'address_detail' => $_POST['address_detail'] ?? null,

                'department_id' => $_POST['department_id'] ?? null,
                'position_id'   => $_POST['position_id'] ?? null,

                // =========================
                // 날짜
                // =========================
                'doc_hire_date'  => $_POST['doc_hire_date'] ?? null,
                'real_hire_date' => $_POST['real_hire_date'] ?? null,

                'doc_retire_date'  => $_POST['doc_retire_date'] ?? null,
                'real_retire_date' => $_POST['real_retire_date'] ?? null,

                // =========================
                // 주민번호
                // =========================
                'rrn' => (
                    isset($_POST['rrn']) &&
                    trim($_POST['rrn']) !== ''
                ) ? trim($_POST['rrn']) : null,

                // =========================
                // 계좌
                // =========================
                'bank_name'      => $_POST['bank_name'] ?? null,
                'account_number' => $_POST['account_number'] ?? null,
                'account_holder' => $_POST['account_holder'] ?? null,

                // =========================
                // 자격증
                // =========================
                'certificate_name' => $_POST['certificate_name'] ?? null,

                // =========================
                // 기타
                // =========================
                'note' => $_POST['note'] ?? null,
                'memo' => $_POST['memo'] ?? null,

                // =========================
                // 🔥 파일 삭제 플래그 (핵심)
                // =========================
                'profile_image_delete'    => $_POST['profile_image_delete'] ?? '0',
                'rrn_image_delete'        => $_POST['rrn_image_delete'] ?? '0',
                'certificate_file_delete' => $_POST['certificate_file_delete'] ?? '0',
                'bank_file_delete'        => $_POST['bank_file_delete'] ?? '0',

                // =========================
                // 🔥 계정 (auth_users)
                // =========================
                'username' => trim($_POST['username'] ?? ''),
                'email'    => trim($_POST['email'] ?? ''),
                'role_id'  => $_POST['role_id'] ?? null,
                'password' => $_POST['password'] ?? '',

                'two_factor_enabled' => $_POST['two_factor_enabled'] ?? '0',
                'email_notify'       => $_POST['email_notify'] ?? '0',
                'sms_notify'         => $_POST['sms_notify'] ?? '0',
            ];

            /* =========================================================
            필수값 체크
            ========================================================= */

            if ($payload['employee_name'] === '') {

                echo json_encode([
                    'success' => false,
                    'message' => '직원명은 필수입니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            /* =========================================================
            저장 (Service 위임)
            ========================================================= */

            $result = $this->service->save(
                $payload,
                'USER',
                $_FILES
            );

            /* =========================================================
            실패 처리
            ========================================================= */

            if (!$result['success']) {

                echo json_encode([
                    'success' => false,
                    'message' => $result['message'] ?? '직원 저장 실패'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            /* =========================================================
            성공
            ========================================================= */

            echo json_encode([
                'success' => true,
                'id'      => $result['id'] ?? null,
                'code'    => $result['code'] ?? null,
                'message' => '저장 완료'
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '직원 저장 중 오류 발생',
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
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;
        $isActive = ($_POST['is_active'] ?? '') === '1';

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '직원 아이디 누락'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {

            // 🔥 employee_id 기준으로 전달
            $result = $this->service->updateStatus($id, $isActive);

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
    // API: 직원 영구삭제
    // URL: POST /api/settings/employee/purge
    // ============================================================
    public function apiPurge(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if (!$id) {

            echo json_encode([
                'success' => false,
                'message' => '직원 아이디 누락'
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        try {

            // 🔥 반드시 user_employees.id 기준
            $result = $this->service->purge($id, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '직원 완전삭제 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }






    // ============================================================
    // API: 직원 순서 변경
    // URL: POST /api/settings/employee/reorder
    // ============================================================
    public function apiReorder()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $changes = json_decode(file_get_contents('php://input'), true)['changes'] ?? [];

        if (!$changes) {
            echo json_encode(['success'=>false,'message'=>'변경 데이터 없음']);
            return;
        }

        $this->service->reorder($changes);

        echo json_encode(['success'=>true]);
    }

}
