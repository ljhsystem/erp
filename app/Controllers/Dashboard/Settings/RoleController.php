<?php
// 경로: PROJECT_ROOT/app/Controllers/Dashboard/Settings/RoleController.php
// 대시보드>설정>조직관리>역할 API 컨트롤러
namespace App\Controllers\Dashboard\Settings;

use Core\Session;
use Core\DbPdo;
use App\Services\Auth\RoleService;

class RoleController
{
    private RoleService $service;

    public function __construct()
    {
        Session::requireAuth();
        $this->service = new RoleService(DbPdo::conn());
    }

    // ============================================================
    // WEB: 역할(Role) 관리 화면
    // URL: GET /dashboard/settings/role
    // permission: settings.role.view
    // controller: RoleController@webIndex
    // ============================================================
    public function webIndex()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/employee/roles.php';
    }

    // ============================================================
    // API: 역할 목록
    // URL: POST /api/settings/role/list
    // permission: settings.role.list
    // controller: RoleController@apiList
    // ============================================================
    public function apiList()
    {
        header('Content-Type: application/json; charset=utf-8');

        $rows = $this->service->getAll();

        echo json_encode([
            'success' => true,
            'data'    => $rows
        ], JSON_UNESCAPED_UNICODE);
    }

    // ============================================================
    // API: 역할 저장 (create / update / delete)
    // URL: POST /api/settings/role/save
    // permission: settings.role.save
    // controller: RoleController@apiSave
    // ============================================================
    public function apiSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        $action = $_POST['action'] ?? '';
        $id     = $_POST['id'] ?? '';

        try {
            switch ($action) {

                case 'create':
                    $result = $this->createRole();
                    break;

                case 'update':
                    $result = $this->updateRole($id);
                    break;

                case 'delete':
                    $result = $this->deleteRole($id);
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
    // 내부 헬퍼: 역할 생성
    // ============================================================
    private function createRole(): array
    {
        $roleKey   = trim($_POST['role_key'] ?? '');
        $roleName  = trim($_POST['role_name'] ?? '');
        $desc      = trim($_POST['description'] ?? '') ?: null;
        $active    = (int)($_POST['is_active'] ?? 1);

        if (!$roleKey || !$roleName) {
            return ['success' => false, 'message' => 'role_key, role_name은 필수입니다.'];
        }

        // UUID 제거, CODE 제거 → 서비스가 모두 처리함
        $payload = [
            'role_key'    => $roleKey,
            'role_name'   => $roleName,
            'description' => $desc,
            'is_active'   => $active,
            'created_by'  => $_SESSION['user']['id'] ?? null
        ];

        $result = $this->service->create($payload);

        if (!$result['success']) {
            return [
                'success' => false,
                'message' => $result['message'] ?? '역할 생성 실패 또는 중복 발생'
            ];
        }

        return ['success' => true, 'message' => '역할 생성 완료'];
    }

    // ============================================================
    // 내부 헬퍼: 역할 수정
    // ============================================================
    private function updateRole(string $id): array
    {
        if (!$id) {
            return ['success' => false, 'message' => 'id 누락'];
        }

        $payload = [
            'role_key'    => trim($_POST['role_key'] ?? ''),
            'role_name'   => trim($_POST['role_name'] ?? ''),
            'description' => trim($_POST['description'] ?? '') ?: null,
            'is_active'   => (int)($_POST['is_active'] ?? 1),
            'updated_by'  => $_SESSION['user']['id'] ?? null
        ];

        $result = $this->service->update($id, $payload);

        if (!$result['success']) {
            return [
                'success' => false,
                'message' => $result['message'] ?? '역할 수정 실패 또는 중복 발생'
            ];
        }

        return ['success' => true, 'message' => '역할 수정 완료'];
    }

    // ============================================================
    // 내부 헬퍼: 역할 삭제
    // ============================================================
    private function deleteRole(string $id): array
    {
        if (!$id) {
            return ['success' => false, 'message' => 'id 누락'];
        }

        $result = $this->service->delete($id);

        return [
            'success' => (bool)$result,
            'message' => '역할 삭제 완료'
        ];
    }
}
