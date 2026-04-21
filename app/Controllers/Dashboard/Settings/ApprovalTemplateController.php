<?php
// 경로: PROJECT_ROOT/app/Controllers/Dashboard/Settings/ApprovalTemplateController.php
// 대시보드>설정>조직관리>결재템플릿 API 컨트롤러
namespace App\Controllers\Dashboard\Settings;

use Core\DbPdo;
use App\Services\Approval\TemplateService;
use App\Services\Approval\TemplateStepService;
use App\Services\Auth\RoleService;

class ApprovalTemplateController
{
    private TemplateService $templateService;
    private TemplateStepService $stepService;
    private RoleService $roleService;

    public function __construct()
    {
        $this->templateService = new TemplateService(DbPdo::conn());
        $this->stepService     = new TemplateStepService(DbPdo::conn());
        $this->roleService     = new RoleService(DbPdo::conn());
    }

    // // ============================================================
    // // WEB: 결재 설정 화면
    // // URL: GET /dashboard/settings/approval
    // // permission: settings.approval.view
    // // controller: ApprovalController@webIndex
    // // ============================================================
    // public function webIndex()
    // {
    //     include PROJECT_ROOT . '/app/views/dashboard/settings/employee/approval.php';
    // }


    // ============================================================
    // API: 템플릿 목록
    // URL: POST /api/settings/approval/template/list
    // permission: settings.approval.template.list
    // ============================================================
    public function apiTemplateList()
    {        
        header('Content-Type: application/json; charset=utf-8');

        $rows = $this->templateService->getAll();

        echo json_encode([
            'success' => true,
            'data'    => $rows
        ], JSON_UNESCAPED_UNICODE);         
    }


    // ============================================================
    // API: 템플릿 저장 (신규 + 수정)
    // URL: POST /api/settings/approval/template/save
    // permission: settings.approval.template.save
    // ============================================================
    public function apiTemplateSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        $id           = trim($_POST['id'] ?? '');
        $name         = trim($_POST['name'] ?? '');
        $documentType = trim($_POST['document_type'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $isActive     = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

        // 입력값 normalize
        $name = preg_replace('/\s+/', ' ', $name);
        $documentType = preg_replace('/\s+/', ' ', $documentType);

        if (!$name || !$documentType) {
            echo json_encode([
                'success' => false,
                'message' => '템플릿명과 문서유형은 필수 항목입니다.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 수정
        if ($id) {
            $result = $this->templateService->update($id, [
                'template_name' => $name,
                'document_type' => $documentType,
                'description'   => $description,
                'is_active'     => $isActive,
            ]);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            return;
        }

        // 신규
        $result = $this->templateService->create([
            'template_name' => $name,
            'document_type' => $documentType,
            'description'   => $description,
            'is_active'     => $isActive,
        ]);

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }


    // ============================================================
    // API: 템플릿 삭제
    // URL: POST /api/settings/approval/template/delete
    // permission: settings.approval.template.delete
    // ============================================================
    public function apiTemplateDelete()
    {
        header('Content-Type: application/json; charset=utf-8');

        $id = trim($_POST['id'] ?? '');
        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '템플릿 ID가 누락되었습니다.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $ok = $this->templateService->delete($id);

        echo json_encode([
            'success' => (bool)$ok,
            'message' => $ok ? '삭제되었습니다.' : '삭제에 실패했습니다.'
        ], JSON_UNESCAPED_UNICODE);
    }


    // ============================================================
    // API: 스텝 목록
    // URL: POST /api/settings/approval/step/list
    // permission: settings.approval.step.list
    // ============================================================
    public function apiStepList()
    {
        header('Content-Type: application/json; charset=utf-8');

        $templateId = trim($_POST['template_id'] ?? '');

        if (!$templateId) {
            echo json_encode([
                'success' => false,
                'message' => 'template_id 값이 필요합니다.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $rows = $this->stepService->getSteps($templateId);

        echo json_encode([
            'success' => true,
            'data'    => $rows
        ], JSON_UNESCAPED_UNICODE);
    }


    // ============================================================
    // API: 스텝 저장 (신규 + 수정 + 순서변경)
    // URL: POST /api/settings/approval/step/save
    // permission: settings.approval.step.save
    // ============================================================
    public function apiStepSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        /* ------------------------------
         * 1) 순서변경 처리
         * ------------------------------ */
        if (!empty($_POST['reorder'])) {

            $templateId = trim($_POST['template_id'] ?? '');
            $steps      = json_decode($_POST['steps'] ?? '[]', true);            

            if (!$templateId || !is_array($steps)) {
                echo json_encode([
                    'success' => false,
                    'message' => '스텝 순서 변경 데이터가 올바르지 않습니다.'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            // 기존값 불러와서 모든 필드 명시적으로 넘김
            foreach ($steps as $row) {
                if (!empty($row['id'])) {
                    $existing = $this->stepService->getById($row['id']);
                    if ($existing) {
                        $updateData = [
                            'step_name'   => $existing['step_name'],
                            'template_id' => $existing['template_id'],
                            'role_id'     => $existing['role_id'],
                            'approver_id' => $existing['approver_id'],
                            'is_active'   => $existing['is_active'],
                            'sequence'    => $row['sequence'] + 1000,
                        ];
                        $this->stepService->update($row['id'], $updateData);
                    }
                }
            }

            foreach ($steps as $row) {
                if (!empty($row['id'])) {
                    $existing = $this->stepService->getById($row['id']);
                    if ($existing) {
                        $updateData = [
                            'step_name'   => $existing['step_name'],
                            'template_id' => $existing['template_id'],
                            'role_id'     => $existing['role_id'],
                            'approver_id' => $existing['approver_id'],
                            'is_active'   => $existing['is_active'],
                            'sequence'    => $row['sequence'],
                        ];
                        $this->stepService->update($row['id'], $updateData);
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'message' => '스텝 순서가 변경되었습니다.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }


        /* ------------------------------
         * 2) 신규 / 수정 처리
         * ------------------------------ */
        $id         = trim($_POST['id'] ?? '');
        $templateId = trim($_POST['template_id'] ?? '');
        $stepName   = trim($_POST['step_name'] ?? '');
        $roleInput  = trim($_POST['role_id'] ?? '');
        $approverId = trim($_POST['approver_id'] ?? '');
        $isActive   = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;



        if (!$templateId) {
            echo json_encode([
                'success' => false, 
                'message' => 'template_id 값이 필요합니다.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!$stepName) {
            echo json_encode([
                'success' => false, 
                'message' => '스텝명은 필수 입력값입니다.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!$roleInput) {
            echo json_encode([
                'success' => false, 
                'message' => '역할(role_id)은 필수 입력값입니다.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $role = $this->roleService->findByIdOrKey($roleInput);
        if (!$role) {
            echo json_encode([
                'success' => false,
                'message' => '유효하지 않은 역할입니다.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $roleId = $role['id'];


        /* ------------------------------
         * 수정
         * ------------------------------ */
        if ($id) {
            $result = $this->stepService->update($id, [
                'step_name'   => $stepName,
                'template_id' => $templateId,
                'role_id'     => $roleId,
                'approver_id' => $approverId ?: null,
                'is_active'   => $isActive,
            ]);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            return;
        }


        /* ------------------------------
         * 신규
         * ------------------------------ */
        $existingSteps = $this->stepService->getSteps($templateId);
        $sequence      = count($existingSteps) + 1;

        $result = $this->stepService->create([
            'template_id' => $templateId,
            'sequence'    => $sequence,
            'step_name'   => $stepName,
            'role_id'     => $roleId,
            'approver_id' => $approverId ?: null,
            'is_active'   => $isActive,
        ]);

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }


    // ============================================================
    // API: 스텝 삭제
    // URL: POST /api/settings/approval/step/delete
    // permission: settings.approval.step.delete
    // ============================================================
    public function apiStepDelete()
    {
        header('Content-Type: application/json; charset=utf-8');

        $id = trim($_POST['step_id'] ?? '');

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '삭제할 스텝 ID가 없습니다.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $ok = $this->stepService->delete($id);

        echo json_encode([
            'success' => (bool)$ok,
            'message' => $ok ? '삭제되었습니다.' : '삭제에 실패했습니다.'
        ], JSON_UNESCAPED_UNICODE);
    }
}
