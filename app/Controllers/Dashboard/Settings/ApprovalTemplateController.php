<?php
namespace App\Controllers\Dashboard\Settings;

use Core\DbPdo;
use App\Services\Approval\TemplateService;
use App\Services\Approval\TemplateStepService;

class ApprovalTemplateController
{
    private TemplateService $templateService;
    private TemplateStepService $stepService;

    public function __construct()
    {
        $pdo = DbPdo::conn();
        $this->templateService = new TemplateService($pdo);
        $this->stepService = new TemplateStepService($pdo);
    }

    public function apiTemplateList(): void
    {
        $this->json(['success' => true, 'data' => $this->templateService->getAll()]);
    }

    public function apiTemplateSave(): void
    {
        $id = trim($_POST['id'] ?? '');
        $data = [
            'template_name' => $_POST['name'] ?? '',
            'document_type' => $_POST['document_type'] ?? '',
            'description' => $_POST['description'] ?? '',
            'is_active' => $_POST['is_active'] ?? 0,
        ];
        $this->json($id !== '' ? $this->templateService->update($id, $data) : $this->templateService->create($data));
    }

    public function apiTemplateDelete(): void
    {
        $id = trim($_POST['id'] ?? '');
        if ($id === '') {
            $this->json(['success' => false, 'message' => '템플릿 ID가 누락되었습니다.']);
            return;
        }
        $this->json($this->templateService->delete($id));
    }

    public function apiTemplateReorder(): void
    {
        $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
        $changes = $payload['changes'] ?? $_POST['changes'] ?? [];
        if (is_string($changes)) {
            $changes = json_decode($changes, true) ?: [];
        }
        if (!is_array($changes)) {
            $this->json(['success' => false, 'message' => '순번 변경 데이터가 올바르지 않습니다.']);
            return;
        }
        try {
            $ok = $this->templateService->reorder($changes);
            $this->json(['success' => $ok, 'message' => $ok ? '순번이 저장되었습니다.' : '순번 저장에 실패했습니다.']);
        } catch (\Throwable) {
            $this->json(['success' => false, 'message' => '순번 저장 중 오류가 발생했습니다.']);
        }
    }

    public function apiStepList(): void
    {
        $templateId = trim($_POST['template_id'] ?? $_GET['template_id'] ?? '');
        $this->json(['success' => true, 'data' => $templateId === '' ? [] : $this->stepService->getSteps($templateId)]);
    }

    public function apiStepSave(): void
    {
        $id = trim($_POST['id'] ?? '');
        $templateId = trim($_POST['template_id'] ?? '');
        $stepName = trim($_POST['step_name'] ?? '');
        $stepType = strtoupper(trim($_POST['step_type'] ?? 'APPROVAL'));
        $roleInput = trim($_POST['role_id'] ?? '');
        $approverId = trim($_POST['approver_id'] ?? '');
        $isActive = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;

        $data = [
            'template_id' => $templateId,
            'step_name' => $stepName,
            'step_type' => $stepType,
            'role_id' => $roleInput !== '' ? $roleInput : null,
            'approver_id' => $approverId !== '' ? $approverId : null,
            'is_active' => $isActive,
        ];
        $this->json($id !== '' ? $this->stepService->update($id, $data) : $this->stepService->create($data));
    }

    public function apiStepReorder(): void
    {
        $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
        $changes = is_array($payload) ? ($payload['changes'] ?? []) : [];
        if (!is_array($changes) || $changes === []) {
            $this->json(['success' => false, 'message' => '단계 순서 변경 데이터가 올바르지 않습니다.']);
            return;
        }

        $templateIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => trim((string) ($row['template_id'] ?? '')),
            array_filter($changes, 'is_array')
        ))));
        if (count($templateIds) !== 1) {
            $this->json(['success' => false, 'message' => '하나의 결재템플릿 단계만 순서를 변경할 수 있습니다.']);
            return;
        }

        try {
            $ok = $this->stepService->reorder($templateIds[0], $changes);
            $this->json([
                'success' => $ok,
                'message' => $ok ? '단계 순서가 변경되었습니다.' : '단계 순서 변경에 실패했습니다.',
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable) {
            $this->json(['success' => false, 'message' => '단계 순서 변경 중 오류가 발생했습니다.']);
        }
    }

    public function apiStepDelete(): void
    {
        $id = trim($_POST['step_id'] ?? '');
        if ($id === '') {
            $this->json(['success' => false, 'message' => '삭제할 단계 ID가 없습니다.']);
            return;
        }
        $ok = $this->stepService->delete($id);
        $this->json(['success' => $ok, 'message' => $ok ? '삭제되었습니다.' : '삭제에 실패했습니다.']);
    }

    private function json(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
