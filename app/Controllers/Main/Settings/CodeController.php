<?php
namespace App\Controllers\Main\Settings;

use App\Services\System\CodeService;
use Core\DbPdo;
use Core\Session;

class CodeController
{
    private CodeService $service;

    public function __construct()
    {
        $this->service = new CodeService(DbPdo::conn());
    }

    public function apiList(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        Session::write();

        try {
            $codeGroup = trim((string)($_GET['code_group'] ?? ''));
            $isDataTableRequest = isset($_GET['draw']) || isset($_GET['filters']);

            if ($codeGroup !== '' && !$isDataTableRequest) {
                echo json_encode($this->service->getOptionsByGroup($codeGroup), JSON_UNESCAPED_UNICODE);
                exit;
            }

            $filters = [];

            if (!empty($_GET['filters'])) {
                $decoded = json_decode($_GET['filters'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $filters = $decoded;
                }
            }

            if ($codeGroup !== '') {
                $filters[] = [
                    'field' => 'code_group',
                    'value' => $codeGroup,
                ];
            }

            echo json_encode([
                'success' => true,
                'data' => $this->service->getList($filters),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '기준정보 목록 조회 중 오류가 발생했습니다.',
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiDetail(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID가 없습니다.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $row = $this->service->getById((string)$id);

        echo json_encode([
            'success' => $row !== null,
            'data' => $row,
            'message' => $row ? null : '기준정보를 찾을 수 없습니다.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiGroups(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        echo json_encode([
            'success' => true,
            'data' => $this->service->getGroups(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiReferences(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $id = trim((string) ($_GET['id'] ?? ''));
        if ($id === '') {
            echo json_encode(['success' => false, 'message' => '코드 정보를 찾을 수 없습니다.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            echo json_encode($this->service->referenceStatus($id), JSON_UNESCAPED_UNICODE);
        } catch (\Throwable) {
            echo json_encode([
                'success' => false,
                'message' => '참조 상태를 확인할 수 없습니다.',
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    public function apiSave(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $payload = [
            'id' => $_POST['id'] ?? null,
            'sort_no' => $_POST['sort_no'] ?? null,
            'code_group' => trim((string)($_POST['code_group'] ?? '')),
            'group_name' => trim((string)($_POST['group_name'] ?? '')),
            'code' => trim((string)($_POST['code'] ?? '')),
            'code_name' => trim((string)($_POST['code_name'] ?? '')),
            'note' => $_POST['note'] ?? null,
            'memo' => $_POST['memo'] ?? null,
            'is_active' => isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1,
            'extra_data' => $_POST['extra_data'] ?? null,
        ];

        if ($payload['code_group'] === '') {
            echo json_encode(['success' => false, 'message' => '분류 정보가 올바르지 않습니다.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($payload['group_name'] === '') {
            echo json_encode(['success' => false, 'message' => '그룹명은 필수입니다.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($payload['code'] === '') {
            echo json_encode(['success' => false, 'message' => '코드는 필수입니다.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($payload['code_name'] === '') {
            echo json_encode(['success' => false, 'message' => '코드명은 필수입니다.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $result = $this->service->save($payload, 'USER');

        if (!$result['success'] && str_contains((string)($result['message'] ?? ''), 'uq_code_group_code')) {
            $result['message'] = '이미 등록된 코드입니다.';
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiDelete(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID가 없습니다.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $result = $this->service->delete((string)$id, 'USER');
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '삭제 중 오류가 발생했습니다.',
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    public function apiReorder(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $this->service->reorder($input['changes'] ?? [], 'USER');
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable) {
            echo json_encode(['success' => false, 'message' => '정렬 저장 중 오류가 발생했습니다.'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

}
