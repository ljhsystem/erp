<?php
// 寃쎈줈: PROJECT_ROOT . '/app/Controllers/Dashboard/Settings/EmployeeController.php'
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
    // API: 吏곸썝 紐⑸줉 議고쉶
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
    
            // ?뵦 Service?먯꽌 紐⑤뱺 泥섎━ (蹂듯샇???ы븿)
            $rows = $this->service->getList($filters);
    
            echo json_encode([
                'success' => true,
                'data'    => $rows
            ], JSON_UNESCAPED_UNICODE);
    
        } catch (\Throwable $e) {
    
            echo json_encode([
                'success' => false,
                'message' => '吏곸썝 紐⑸줉 議고쉶 ?ㅽ뙣',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    
        exit;
    }

    // ============================================================
    // API: 吏곸썝 ?곸꽭 議고쉶
    // URL: GET /api/settings/employee/detail
    // ============================================================
    public function apiDetail(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
    
        $id = $_GET['id'] ?? null;
    
        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '吏곸썝 ?꾩씠???꾨씫'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    
        try {
    
            // ?뵦 Service?먯꽌 紐⑤뱺 泥섎━ (蹂듯샇???ы븿)
            $row = $this->service->getById($id);
    
            if (!$row) {
                echo json_encode([
                    'success' => false,
                    'message' => '吏곸썝 ?놁쓬'
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
                'message' => '吏곸썝 議고쉶 ?ㅽ뙣',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    
        exit;
    }



    // =====// ============================================================
    // API: 吏곸썝 寃??(Select2??
    // URL: GET /api/settings/employee/search?q=?띻만??
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
                'message' => '寃???ㅽ뙣',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }


    // ============================================================
    // API: 吏곸썝 ???(?좉퇋 + ?섏젙)
    // URL: POST /api/settings/employee/save
    // ============================================================
    public function apiSave(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            /* =========================================================
            ?뵦 payload ?앹꽦 (理쒖쥌 ?듯빀蹂?
            ========================================================= */

            $payload = [

                // =========================
                // 湲곗?
                // =========================
                'id'       => $_POST['id'] ?? null,
                'code'     => $_POST['code'] ?? null,
                'user_id'  => $_POST['user_id'] ?? null,

                // =========================
                // 湲곕낯 ?뺣낫
                // =========================
                'employee_name' => trim($_POST['employee_name'] ?? ''),

                'phone'           => $_POST['phone'] ?? null,
                'emergency_phone' => $_POST['emergency_phone'] ?? null,

                'address'        => $_POST['address'] ?? null,
                'address_detail' => $_POST['address_detail'] ?? null,

                'department_id' => $_POST['department_id'] ?? null,
                'position_id'   => $_POST['position_id'] ?? null,

                // =========================
                // ?좎쭨
                // =========================
                'doc_hire_date'  => $_POST['doc_hire_date'] ?? null,
                'real_hire_date' => $_POST['real_hire_date'] ?? null,

                'doc_retire_date'  => $_POST['doc_retire_date'] ?? null,
                'real_retire_date' => $_POST['real_retire_date'] ?? null,

                // =========================
                // 二쇰?踰덊샇
                // =========================
                'rrn' => (
                    isset($_POST['rrn']) &&
                    trim($_POST['rrn']) !== ''
                ) ? trim($_POST['rrn']) : null,

                // =========================
                // 怨꾩쥖
                // =========================
                'bank_name'      => $_POST['bank_name'] ?? null,
                'account_number' => $_POST['account_number'] ?? null,
                'account_holder' => $_POST['account_holder'] ?? null,

                // =========================
                // ?먭꺽利?
                // =========================
                'certificate_name' => $_POST['certificate_name'] ?? null,

                // =========================
                // 湲고?
                // =========================
                'note' => $_POST['note'] ?? null,
                'memo' => $_POST['memo'] ?? null,

                // =========================
                // ?뵦 ?뚯씪 ??젣 ?뚮옒洹?(?듭떖)
                // =========================
                'profile_image_delete'    => $_POST['profile_image_delete'] ?? '0',
                'rrn_image_delete'        => $_POST['rrn_image_delete'] ?? '0',
                'certificate_file_delete' => $_POST['certificate_file_delete'] ?? '0',
                'bank_file_delete'        => $_POST['bank_file_delete'] ?? '0',

                // =========================
                // ?뵦 怨꾩젙 (auth_users)
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
            ?꾩닔媛?泥댄겕
            ========================================================= */

            if ($payload['employee_name'] === '') {

                echo json_encode([
                    'success' => false,
                    'message' => '吏곸썝紐낆? ?꾩닔?낅땲??'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $isCreate = empty($payload['id']);

            if ($isCreate && $payload['username'] === '') {
                echo json_encode([
                    'success' => false,
                    'message' => '아이디는 필수입니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($isCreate && trim((string)$payload['password']) === '') {
                echo json_encode([
                    'success' => false,
                    'message' => '비밀번호는 필수입니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($payload['username'] !== '' && !preg_match('/^[A-Za-z0-9._-]{4,50}$/', $payload['username'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '아이디는 4~50자의 영문, 숫자, 점(.), 밑줄(_), 하이픈(-)만 사용할 수 있습니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($payload['email'] !== '' && !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
                echo json_encode([
                    'success' => false,
                    'message' => '이메일 형식이 올바르지 않습니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            foreach (['phone' => '연락처', 'emergency_phone' => '비상연락처', 'account_number' => '계좌번호'] as $field => $label) {
                $value = trim((string)($payload[$field] ?? ''));
                if ($value !== '' && !preg_match('/^[0-9-]+$/', $value)) {
                    echo json_encode([
                        'success' => false,
                        'message' => $label . '는 숫자와 하이픈(-)만 입력할 수 있습니다.'
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }

            $rrnDigits = preg_replace('/\D+/', '', (string)($payload['rrn'] ?? ''));
            if ($rrnDigits !== '' && strlen($rrnDigits) !== 13) {
                echo json_encode([
                    'success' => false,
                    'message' => '주민등록번호는 숫자 13자리여야 합니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            foreach ([
                'doc_hire_date' => '서류입사일',
                'real_hire_date' => '실입사일',
                'doc_retire_date' => '서류퇴사일',
                'real_retire_date' => '실퇴사일'
            ] as $field => $label) {
                $value = trim((string)($payload[$field] ?? ''));
                if ($value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    echo json_encode([
                        'success' => false,
                        'message' => $label . ' 형식이 올바르지 않습니다.'
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }

            if (
                !empty($payload['real_hire_date']) &&
                !empty($payload['real_retire_date']) &&
                $payload['real_hire_date'] > $payload['real_retire_date']
            ) {
                echo json_encode([
                    'success' => false,
                    'message' => '실퇴사일은 실입사일보다 빠를 수 없습니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            /* =========================================================
            ???(Service ?꾩엫)
            ========================================================= */

            $result = $this->service->save(
                $payload,
                'USER',
                $_FILES
            );

            /* =========================================================
            ?ㅽ뙣 泥섎━
            ========================================================= */

            if (!$result['success']) {

                echo json_encode([
                    'success' => false,
                    'message' => $result['message'] ?? '吏곸썝 ????ㅽ뙣'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            /* =========================================================
            ?깃났
            ========================================================= */

            echo json_encode([
                'success' => true,
                'id'      => $result['id'] ?? null,
                'code'    => $result['code'] ?? null,
                'message' => '????꾨즺'
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '吏곸썝 ???以??ㅻ쪟 諛쒖깮',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }


    // ============================================================
    // API: 吏곸썝 ?곹깭 蹂寃?(?쒖꽦/鍮꾪솢??
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
                'message' => '吏곸썝 ?꾩씠???꾨씫'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {

            // ?뵦 employee_id 湲곗??쇰줈 ?꾨떖
            $result = $this->service->updateStatus($id, $isActive);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '?곹깭 蹂寃??ㅽ뙣',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }


    // ============================================================
    // API: 吏곸썝 ?곴뎄??젣
    // URL: POST /api/settings/employee/purge
    // ============================================================
    public function apiPurge(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if (!$id) {

            echo json_encode([
                'success' => false,
                'message' => '吏곸썝 ?꾩씠???꾨씫'
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        try {

            // ?뵦 諛섎뱶??user_employees.id 湲곗?
            $result = $this->service->purge($id, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '吏곸썝 ?꾩쟾??젣 ?ㅽ뙣',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }






    // ============================================================
    // API: 吏곸썝 ?쒖꽌 蹂寃?
    // URL: POST /api/settings/employee/reorder
    // ============================================================
    public function apiReorder()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $changes = json_decode(file_get_contents('php://input'), true)['changes'] ?? [];

        if (!$changes) {
            echo json_encode(['success'=>false,'message'=>'蹂寃??곗씠???놁쓬']);
            return;
        }

        $this->service->reorder($changes);

        echo json_encode(['success'=>true]);
    }

}