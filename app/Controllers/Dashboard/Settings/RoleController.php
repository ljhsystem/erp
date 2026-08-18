<?php

namespace App\Controllers\Dashboard\Settings;

use App\Services\Auth\RoleService;
use Core\DbPdo;
use Core\Session;

class RoleController
{
    private RoleService $service;

    public function __construct()
    {
        $this->service = new RoleService(DbPdo::conn());
    }

    public function webIndex(): void
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/organization/role.php';
    }

    public function apiList(): void
    {
        Session::write();
        $this->respond(function (): array {
            return ['success' => true, 'data' => $this->service->getAll($this->readFilters())];
        }, '목록 조회 중 오류가 발생했습니다.');
    }

    public function apiDetail(): void
    {
        Session::write();
        $this->respond(function (): array {
            $id = trim((string) ($_GET['id'] ?? $_POST['id'] ?? ''));
            if ($id === '') {
                throw new \InvalidArgumentException('역할 ID가 필요합니다.');
            }
            $row = $this->service->getById($id);
            return [
                'success' => $row !== null,
                'data' => $row,
                'message' => $row === null ? '역할 정보를 찾을 수 없습니다.' : null,
            ];
        }, '상세 조회 중 오류가 발생했습니다.');
    }

    public function apiSave(): void
    {
        $this->respond(function (): array {
            $input = [
                'role_key' => $_POST['role_key'] ?? '',
                'role_name' => $_POST['role_name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'is_active' => $_POST['is_active'] ?? null,
            ];
            return match ((string) ($_POST['action'] ?? '')) {
                'create' => $this->service->create($input),
                'update' => $this->service->update((string) ($_POST['id'] ?? ''), $input),
                default => throw new \InvalidArgumentException('올바르지 않은 요청입니다.'),
            };
        }, '저장 중 오류가 발생했습니다.');
    }

    public function apiDelete(): void
    {
        $this->respond(
            fn (): array => $this->service->delete((string) ($_POST['id'] ?? '')),
            '영구삭제 중 오류가 발생했습니다.'
        );
    }

    public function apiReorder(): void
    {
        $this->respond(function (): array {
            $input = json_decode(file_get_contents('php://input'), true);
            $ok = $this->service->reorder(is_array($input['changes'] ?? null) ? $input['changes'] : []);
            return [
                'success' => $ok,
                'message' => $ok ? '정렬이 저장되었습니다.' : '정렬 저장 중 오류가 발생했습니다.',
            ];
        }, '정렬 저장 중 오류가 발생했습니다.');
    }

    private function readFilters(): array
    {
        $raw = $_GET['filters'] ?? $_POST['filters'] ?? '';
        if ($raw === '') {
            return [];
        }
        $filters = json_decode((string) $raw, true);
        return is_array($filters) ? $filters : [];
    }

    private function respond(callable $callback, string $errorMessage): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $payload = $callback();
        } catch (\InvalidArgumentException $e) {
            http_response_code(422);
            $payload = ['success' => false, 'data' => null, 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            http_response_code(500);
            $payload = ['success' => false, 'data' => null, 'message' => $errorMessage];
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
