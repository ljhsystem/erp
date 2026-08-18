<?php
namespace App\Controllers\Dashboard\Settings;

use Core\DbPdo;
use Core\Session;
use App\Services\System\EmployeeService;
use App\Services\Auth\AuthService;

class EmployeeController
{
    private EmployeeService $service;
    private AuthService $authService;

    public function __construct()
    {
        $this->service = new EmployeeService(DbPdo::conn());

        $this->authService = new AuthService(DbPdo::conn());
    }

    public function apiList(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        Session::write();

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

    public function apiDetail(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        Session::write();

        $id = $_GET['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '직원 ID 누락'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {

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


    public function apiSearchPicker(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        Session::write();

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

    public function apiSave(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $payload = [
                'id'       => $_POST['id'] ?? null,
                'sort_no'     => $_POST['sort_no'] ?? null,
                'user_id'  => $_POST['user_id'] ?? null,
                'employee_name' => trim($_POST['employee_name'] ?? ''),

                'phone'           => $_POST['phone'] ?? null,
                'emergency_phone' => $_POST['emergency_phone'] ?? null,

                'address'        => $_POST['address'] ?? null,
                'address_detail' => $_POST['address_detail'] ?? null,

                'department_id' => $_POST['department_id'] ?? null,
                'position_id'   => $_POST['position_id'] ?? null,
                'job_id'        => $_POST['job_id'] ?? null,
                'employment_status' => $_POST['employment_status'] ?? null,

                'doc_hire_date'  => $_POST['doc_hire_date'] ?? null,
                'real_hire_date' => $_POST['real_hire_date'] ?? null,

                'doc_retire_date'  => $_POST['doc_retire_date'] ?? null,
                'real_retire_date' => $_POST['real_retire_date'] ?? null,

                'rrn' => (
                    isset($_POST['rrn']) &&
                    trim($_POST['rrn']) !== ''
                ) ? trim($_POST['rrn']) : null,

                'bank_name'      => $_POST['bank_name'] ?? null,
                'account_number' => $_POST['account_number'] ?? null,
                'account_holder' => $_POST['account_holder'] ?? null,

                'note' => $_POST['note'] ?? null,
                'memo' => $_POST['memo'] ?? null,

                'profile_image_delete'    => $_POST['profile_image_delete'] ?? '0',
                'rrn_image_delete'        => $_POST['rrn_image_delete'] ?? '0',
                'bank_file_delete'        => $_POST['bank_file_delete'] ?? '0',
                'representative_qualification_name' => trim($_POST['representative_qualification_name'] ?? ''),
                'representative_qualification_delete' => $_POST['representative_qualification_delete'] ?? '0',

                'username' => trim($_POST['username'] ?? ''),
                'email'    => trim($_POST['email'] ?? ''),
                'role_id'  => $_POST['role_id'] ?? null,
                'password' => $_POST['password'] ?? '',

                'two_factor_enabled' => $_POST['two_factor_enabled'] ?? '0',
                'email_notify'       => $_POST['email_notify'] ?? '0',
                'sms_notify'         => $_POST['sms_notify'] ?? '0',
            ];

            if (!empty($payload['id'])) {
                foreach (['department_id', 'position_id', 'job_id', 'employment_status', 'doc_hire_date', 'real_hire_date', 'doc_retire_date', 'real_retire_date'] as $protectedField) {
                    if (!array_key_exists($protectedField, $_POST)) {
                        unset($payload[$protectedField]);
                    }
                }
            }

            if ($payload['employee_name'] === '') {

                echo json_encode([
                    'success' => false,
                    'message' => '직원명은 필수입니다.'
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


            $result = $this->service->save(
                $payload,
                'USER',
                $_FILES
            );

            if (!$result['success']) {

                http_response_code((int) ($result['status'] ?? 400));

                echo json_encode([
                    'success' => false,
                    'message' => $result['message'] ?? '직원 저장 실패'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            echo json_encode([
                'success' => true,
                'id'      => $result['id'] ?? null,
                'sort_no'    => $result['sort_no'] ?? null,
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

    public function apiUpdateStatus(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;
        $isActive = ($_POST['is_active'] ?? '') === '1';

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '직원 ID 누락'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {

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

    public function apiDelete(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if (!$id) {

            echo json_encode([
                'success' => false,
                'message' => '직원 ID 누락'
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        try {

            $result = $this->service->purge($id, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '직원 영구 삭제 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
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
            echo json_encode([
                'success' => false,
                'message' => 'reorder failed',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }


}
